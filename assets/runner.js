/**
 * Link Fixer Harness runner.
 *
 * Works through the test suites a step at a time. The run lives in localStorage so it carries on
 * across page loads. Each step's setup and server checks go through the harness REST routes
 * (lfh/v1/step, lfh/v1/verify), sending the run token rather than a nonce so it keeps working
 * whoever is logged in.
 */
( function () {
	'use strict';

	const C = window.LFH_RUNNER;
	if ( ! C ) {
		return;
	}

	// One run per site: Playground sites share an origin, so key by the site's own URL.
	const KEY = 'lfh-run:' + C.tests_url;
	const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	const esc = ( value ) =>
		String( value === null || value === undefined ? '' : value ).replace(
			/[&<>"']/g,
			( c ) =>
				( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ]
		);

	let leaving = false;
	window.addEventListener( 'beforeunload', () => ( leaving = true ) );
	window.addEventListener( 'pagehide', () => ( leaving = true ) );

	/* Stored run. */
	function load() {
		try {
			return JSON.parse( window.localStorage.getItem( KEY ) || 'null' );
		} catch ( e ) {
			return null;
		}
	}

	function store( run ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( run ) );
		} catch ( e ) {
			// Without storage the run cannot survive a page load.
		}
	}

	function clear() {
		try {
			window.localStorage.removeItem( KEY );
		} catch ( e ) {
			// Nothing to clear.
		}
	}

	/* REST. */
	async function api( path, body, run ) {
		const headers = { 'Content-Type': 'application/json' };
		if ( run && run.token ) {
			headers[ 'X-LFH-Token' ] = run.token;
		} else {
			headers[ 'X-WP-Nonce' ] = C.nonce;
		}

		const response = await fetch( C.rest_root + 'lfh/v1/' + path, {
			method: body === undefined ? 'GET' : 'POST',
			credentials: 'same-origin',
			headers,
			body: body === undefined ? undefined : JSON.stringify( body ),
		} );
		if ( ! response.ok ) {
			throw new Error( path + ': HTTP ' + response.status );
		}
		return response.json();
	}

	/* Starting and stopping. */
	async function start( which ) {
		const response = await api( 'run/start', {} );
		const suites =
			which === 'all'
				? response.suites
				: response.suites.filter( ( s ) => s.slug === which );

		const run = {
			id: 'run-' + Date.now(),
			token: response.token,
			started: new Date().toISOString(),
			finished: null,
			s: 0,
			i: 0,
			phase: 'setup',
			step: null,
			suites: suites.map( ( s ) => ( {
				slug: s.slug,
				title: s.title,
				total: s.steps.length,
				steps: [],
			} ) ),
		};

		store( run );
		go();
	}

	function forSave( run ) {
		return {
			id: run.id,
			started: run.started,
			finished: run.finished,
			suites: run.suites,
		};
	}

	async function finish( run ) {
		run.finished = new Date().toISOString();
		try {
			await api( 'run/save', forSave( run ), run );
		} catch ( e ) {
			// The report page shows whatever was saved last.
		}
		clear();
		leaving = true;
		window.location.href = C.tests_url + '&iawmlf_onboarding=1';
	}

	async function stop() {
		const run = load();
		clear();
		if ( run ) {
			try {
				await api( 'run/save', forSave( run ), run );
			} catch ( e ) {
				// Stopped anyway.
			}
		}
		leaving = true;
		window.location.href = C.tests_url + '&iawmlf_onboarding=1';
	}

	/* The loop. */
	let busy = false;

	async function go() {
		if ( busy ) {
			return;
		}
		busy = true;

		try {
			for ( ;; ) {
				const run = load();
				if ( ! run || run.finished || leaving ) {
					return;
				}

				const suite = run.suites[ run.s ];
				if ( ! suite ) {
					await finish( run );
					return;
				}

				strip( run );

				if ( run.phase === 'setup' ) {
					const step = await api( 'step', { suite: suite.slug, index: run.i }, run );
					if ( step.done ) {
						run.s++;
						run.i = 0;
						store( run );
						continue;
					}

					run.step = step;
					run.phase = 'verify';
					store( run );

					// Setup may have changed what the page shows, so always load it fresh.
					if ( step.url ) {
						leaving = true;
						window.location.href = step.url;
						return;
					}
				}

				if ( run.phase === 'verify' ) {
					await settle( run.step );

					const result = {
						title: run.step.title,
						error: run.step.error || '',
						checks: evaluateAll( run.step.checks ),
					};
					try {
						result.checks = result.checks.concat(
							await api( 'verify', { suite: suite.slug, index: run.i }, run )
						);
					} catch ( e ) {
						result.checks.push( { say: 'Server checks', pass: false, detail: e.message } );
					}
					result.pass = ! result.error && result.checks.every( ( c ) => c.pass );

					const then = run.step.then || [];
					suite.steps[ run.i ] = result;
					run.i++;
					run.phase = 'setup';
					run.step = null;
					store( run );
					strip( run, result );

					try {
						await api( 'run/save', forSave( run ), run );
					} catch ( e ) {
						// Saved again after the next step.
					}

					if ( then.length ) {
						await perform( then );
						await sleep( 400 );
						// A click that submitted a form is navigating; the next page load carries on.
						if ( leaving ) {
							return;
						}
					}
				}
			}
		} catch ( e ) {
			strip( load(), { title: 'The runner stopped: ' + e.message, pass: false } );
		} finally {
			busy = false;
		}
	}

	/* Waiting for the page. */
	async function settle( step ) {
		while ( document.readyState !== 'complete' ) {
			await sleep( 200 );
		}
		await sleep( 800 );

		const needsLinks = ( step.checks || [] ).some(
			( c ) => c.type === 'links' || c.type === 'link'
		);
		if ( ! needsLinks || ! window.LFH ) {
			return;
		}

		// The Link Fixer only checks links as they scroll into view.
		const stepSize = Math.max( 200, Math.round( window.innerHeight * 0.8 ) );
		for ( let y = 0; y <= document.documentElement.scrollHeight; y += stepSize ) {
			window.scrollTo( 0, y );
			await sleep( 400 );
		}

		const deadline = Date.now() + 30000;
		while ( Date.now() < deadline ) {
			const rows = window.LFH.rows;
			if ( rows.every( ( r ) => r.verdict === 'pass' || r.verdict === 'fail' ) ) {
				break;
			}
			rows.filter( ( r ) => r.verdict === 'scroll' && r.anchors[ 0 ] ).forEach( ( r ) =>
				r.anchors[ 0 ].scrollIntoView( { block: 'center' } )
			);
			await sleep( 500 );
		}
		window.scrollTo( 0, 0 );
	}

	/* Browser checks. */
	function evaluateAll( checks ) {
		return ( checks || [] ).map( ( check ) => {
			try {
				return evaluate( check );
			} catch ( e ) {
				return { say: check.say || check.type, pass: false, detail: e.message };
			}
		} );
	}

	function evaluate( c ) {
		const el = c.selector ? document.querySelector( c.selector ) : null;
		const text = el ? el.textContent.replace( /\s+/g, ' ' ).trim() : null;

		switch ( c.type ) {
			case 'url': {
				const href = window.location.href;
				return {
					say: c.say || ( c.has ? 'The address contains ' + c.has : 'The address does not contain ' + c.lacks ),
					pass: c.has ? href.includes( c.has ) : ! href.includes( c.lacks ),
					detail: href,
				};
			}
			case 'text':
				return {
					say: c.say || ( c.has !== undefined ? '"' + c.has + '" is shown' : '"' + c.lacks + '" is not shown' ),
					pass: el !== null && ( c.has !== undefined ? text.includes( c.has ) : ! text.includes( c.lacks ) ),
					detail: el ? '"' + text.slice( 0, 200 ) + '"' : 'not found: ' + c.selector,
				};
			case 'exists':
				return { say: c.say || c.selector + ' is on the page', pass: el !== null, detail: el ? 'found' : 'not found' };
			case 'missing':
				return { say: c.say || c.selector + ' is not on the page', pass: el === null, detail: el ? 'found' : 'not found' };
			case 'checked':
				return {
					say: c.say || c.selector + ( c.is ? ' is ticked' : ' is not ticked' ),
					pass: el !== null && el.checked === c.is,
					detail: el ? 'ticked: ' + el.checked : 'not found',
				};
			case 'count': {
				const count = document.querySelectorAll( c.selector ).length;
				return { say: c.say || c.is + ' x ' + c.selector, pass: count === c.is, detail: 'found ' + count };
			}
			case 'script':
				return {
					say: c.say || ( c.loaded ? 'The Link Fixer script is loaded' : 'The Link Fixer script is not loaded' ),
					pass: !! window.iawmlfArchivedLinks === c.loaded,
					detail: 'loaded: ' + !! window.iawmlfArchivedLinks,
				};
			case 'links': {
				if ( ! window.LFH ) {
					return { say: 'Every link did what was predicted', pass: false, detail: 'the harness panel is not on this page' };
				}
				const rows = window.LFH.rows;
				const failed = rows.filter( ( r ) => r.verdict !== 'pass' );
				return {
					say: c.say || 'Every link on the page did what was predicted (' + rows.length + ' links)',
					pass: failed.length === 0,
					detail: failed.map( ( r ) => r.link.id + ': ' + ( r.problems.join( '; ' ) || r.verdict ) ).join( ' | ' ) || 'all passed',
				};
			}
			case 'link': {
				const row = window.LFH && window.LFH.rows.find( ( r ) => r.link.id === c.id );
				const act = row && row.act;
				return {
					say: c.say || c.id + ( c.swapped ? ' is swapped' : ' is not swapped' ),
					pass: !! act && act.swapped === c.swapped,
					detail: act ? 'swapped: ' + act.swapped : 'link not found',
				};
			}
		}

		return { say: 'Unknown check ' + c.type, pass: false, detail: '' };
	}

	/* Browser actions. */
	async function perform( actions ) {
		for ( const action of actions ) {
			const selector = action.click || action.check || action.uncheck || action.fill;
			const el = document.querySelector( selector );
			if ( ! el ) {
				continue;
			}
			if ( action.click ) {
				el.click();
			} else if ( action.fill ) {
				el.value = action.value;
				el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} else {
				el.checked = !! action.check;
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			await sleep( 150 );
		}
	}

	/* Progress strip. */
	function strip( run, last ) {
		if ( ! run ) {
			return;
		}
		let bar = document.getElementById( 'lfh-strip' );
		if ( ! bar ) {
			bar = document.createElement( 'div' );
			bar.id = 'lfh-strip';
			document.body.appendChild( bar );
			bar.addEventListener( 'click', ( event ) => {
				if ( event.target.closest( '[data-lfh-stop]' ) ) {
					stop();
				}
			} );
		}

		let passed = 0;
		let failed = 0;
		run.suites.forEach( ( s ) =>
			s.steps.forEach( ( step ) => ( step && step.pass ? passed++ : failed++ ) )
		);

		const suite = run.suites[ run.s ] || run.suites[ run.suites.length - 1 ];
		const current = run.step ? run.step.title : last ? last.title : 'Starting...';

		bar.className = last ? ( last.pass ? 'lfh-strip-pass' : 'lfh-strip-fail' ) : '';
		bar.innerHTML =
			'<strong>Link Fixer Tests</strong>' +
			'<span>Suite ' + Math.min( run.s + 1, run.suites.length ) + ' of ' + run.suites.length + ': ' + esc( suite.title ) + '</span>' +
			'<span>Step ' + Math.min( run.i + 1, suite.total ) + ' of ' + suite.total + ': ' + esc( current ) + '</span>' +
			'<span class="lfh-strip-count"><b class="lfh-ok">&#10003; ' + passed + '</b> <b class="lfh-no">&#10007; ' + failed + '</b></span>' +
			'<button type="button" data-lfh-stop>Stop</button>';
	}

	/* Report as Markdown, for tickets. */
	function markdown( run ) {
		let steps = 0;
		let passed = 0;
		const lines = [];
		( run.suites || [] ).forEach( ( suite ) => {
			const ok = ( suite.steps || [] ).filter( ( s ) => s && s.pass ).length;
			lines.push( '', '## ' + ( ok === ( suite.steps || [] ).length ? '✅' : '❌' ) + ' ' + suite.title + ' (' + ok + '/' + ( suite.steps || [] ).length + ')' );
			( suite.steps || [] ).forEach( ( step, i ) => {
				steps++;
				passed += step.pass ? 1 : 0;
				lines.push( ( i + 1 ) + '. ' + ( step.pass ? '✅' : '❌' ) + ' ' + step.title );
				( step.checks || [] ).filter( ( c ) => ! c.pass ).forEach( ( c ) =>
					lines.push( '    - ❌ ' + c.say + ( c.detail ? ' (' + c.detail + ')' : '' ) )
				);
			} );
		} );
		return (
			'# Link Fixer Tests: ' + passed + ' of ' + steps + ' steps passed\n' +
			'Started ' + run.started + ( run.finished ? ', finished ' + run.finished : ', not finished' ) + '\n' +
			lines.join( '\n' ) + '\n'
		);
	}

	/* Tests page buttons. */
	if ( C.on_tests ) {
		document.addEventListener( 'click', async ( event ) => {
			const runButton = event.target.closest( '[data-lfh-run]' );
			if ( runButton ) {
				runButton.disabled = true;
				try {
					await start( runButton.getAttribute( 'data-lfh-run' ) );
				} catch ( e ) {
					runButton.disabled = false;
					runButton.textContent = 'Could not start: ' + e.message;
				}
				return;
			}

			const copyButton = event.target.closest( '[data-lfh-copy]' );
			if ( copyButton ) {
				const json = document.getElementById( 'lfh-latest-run' );
				if ( json ) {
					const text = markdown( JSON.parse( json.textContent ) );
					try {
						await navigator.clipboard.writeText( text );
						copyButton.textContent = 'Copied';
					} catch ( e ) {
						window.console.log( text );
						copyButton.textContent = 'Copy failed, report is in the console';
					}
				}
			}
		} );
	}

	window.LFH_RUN = { load, stop, markdown };

	/* Carry on with a run in progress. */
	const run = load();
	if ( run && ! run.finished ) {
		strip( run );
		go();
	}
} )();
