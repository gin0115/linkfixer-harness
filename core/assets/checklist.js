/**
 * Link Fixer Harness checklist panel.
 *
 * Shows the site's checklist (window.LFH_CHECKLIST, from Checklist::enqueue()) and, on every page load,
 * judges each item whose "when" checks say it applies to this page: browser checks here, server checks
 * through lfh/v1/checklist/evaluate. Results are saved, so ticks carry across page loads.
 */
( function () {
	'use strict';

	const C = window.LFH_CHECKLIST;
	if ( ! C ) {
		return;
	}

	const state = Object.assign( {}, C.state || {} );
	const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	const esc = ( value ) =>
		String( value === null || value === undefined ? '' : value ).replace(
			/[&<>"']/g,
			( c ) =>
				( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ]
		);

	let status = '';
	let collapsed = false;
	try {
		collapsed = window.localStorage.getItem( 'lfh-checklist-collapsed' ) === '1';
	} catch ( e ) {
		collapsed = false;
	}

	/* REST. */
	async function api( path, body ) {
		const response = await fetch( C.rest_root + 'lfh/v1/' + path, {
			method: body === undefined ? 'GET' : 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
			body: body === undefined ? undefined : JSON.stringify( body ),
		} );
		if ( ! response.ok ) {
			throw new Error( path + ': HTTP ' + response.status );
		}
		return response.json();
	}

	/* Browser checks. */
	function evaluate( c ) {
		const el = c.selector ? document.querySelector( c.selector ) : null;
		const text = el ? el.textContent.replace( /\s+/g, ' ' ).trim() : null;

		switch ( c.type ) {
			case 'url': {
				const href = window.location.href;
				return {
					say: c.say || ( c.has ? 'The address contains ' + c.has : 'The address does not contain ' + c.lacks ),
					pass: c.has ? href.includes( c.has ) : ! href.includes( c.lacks ),
					detail: window.location.pathname,
				};
			}
			case 'param': {
				// A query parameter's value; '' also matches a missing parameter (list forms send empty ones).
				const value = new URLSearchParams( window.location.search ).get( c.name ) || '';
				return {
					say: c.say || ( c.is === '' ? c.name + ' is not set' : c.name + ' is ' + c.is ),
					pass: value === String( c.is ),
					detail: c.name + '=' + value,
				};
			}
			case 'text': {
				// is: the whole text, has: contains, lacks: does not contain.
				let pass = false;
				if ( el !== null ) {
					if ( c.is !== undefined ) {
						pass = text === String( c.is );
					} else {
						pass = c.has !== undefined ? text.includes( c.has ) : ! text.includes( c.lacks );
					}
				}
				const said = c.is !== undefined ? '"' + c.is + '" is shown' : c.has !== undefined ? '"' + c.has + '" is shown' : '"' + c.lacks + '" is not shown';
				return {
					say: c.say || said,
					pass,
					detail: el ? '"' + text.slice( 0, 160 ) + '"' : 'not found: ' + c.selector,
				};
			}
			case 'value':
				return {
					say: c.say || c.selector + ' shows "' + c.is + '"',
					pass: el !== null && String( el.value ) === String( c.is ),
					detail: el ? 'value "' + el.value + '"' : 'not found',
				};
			case 'visible': {
				const shown = el !== null && el.getClientRects().length > 0 && window.getComputedStyle( el ).visibility !== 'hidden';
				return {
					say: c.say || c.selector + ( c.is ? ' is showing' : ' is hidden' ),
					pass: shown === c.is,
					detail: el ? 'showing: ' + shown : 'not found',
				};
			}
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
			case 'fetches': {
				// The Link Fixer's front end link checks seen by watch.js: count, done (answered),
				// most (the most waiting at the same moment) or seconds (first sent to last answered).
				const all = ( window.LFH_LOG && window.LFH_LOG.fetches ) || [];
				const done = all.filter( ( f ) => f.ended );
				const most = all.reduce(
					( top, a ) => Math.max( top, all.filter( ( b ) => b.started <= a.started && ( ! b.ended || b.ended > a.started ) ).length ),
					0
				);
				const seconds = done.length ? ( Math.max( ...done.map( ( f ) => f.ended ) ) - Math.min( ...all.map( ( f ) => f.started ) ) ) / 1000 : 0;
				const value = { count: all.length, done: done.length, most, seconds }[ c.field ];
				let pass = false;
				if ( c.is !== undefined ) {
					pass = value === c.is;
				} else if ( c.max !== undefined ) {
					pass = value <= c.max;
				} else {
					pass = value >= ( c.min ?? 1 );
				}
				return {
					say: c.say || 'Front end link checks: ' + c.field,
					pass,
					detail: c.field + ' ' + ( 'seconds' === c.field ? value.toFixed( 1 ) : value ) + ' (' + all.length + ' sent, ' + done.length + ' answered, most at once ' + most + ')',
				};
			}
			case 'timing': {
				// How long the server took to answer this page, redirects included.
				const nav = window.performance.getEntriesByType( 'navigation' )[ 0 ];
				const seconds = nav ? nav.responseStart / 1000 : null;
				return {
					say: c.say || 'The page came back within ' + c.max + ' seconds',
					pass: seconds !== null && seconds <= c.max,
					detail: seconds === null ? 'no timing' : 'took ' + seconds.toFixed( 1 ) + ' s',
				};
			}
			case 'page': {
				// Fetched as the logged in user, for pages the panel cannot load on.
				// from: fetch the address of a link on this page instead of url.
				const link = c.from ? document.querySelector( c.from ) : null;
				const url = c.from ? ( link ? link.href : '' ) : c.url;
				const say =
					c.say ||
					( c.from || c.url ) + ( c.status !== undefined ? ' answers HTTP ' + c.status : '' ) + ( c.has !== undefined ? ' and says "' + c.has + '"' : '' );
				if ( ! url ) {
					return { say, pass: false, detail: 'not found: ' + c.from };
				}
				return fetch( url, { credentials: 'same-origin' } )
					.then( async ( response ) => {
						const html = await response.text();
						// rows: how many rows the Links table on that page lists.
						if ( c.rows !== undefined ) {
							const rows = new window.DOMParser().parseFromString( html, 'text/html' ).querySelectorAll( '#the-list tr:not(.no-items)' ).length;
							return { say, pass: rows === c.rows, detail: 'the table lists ' + rows };
						}
						const body = html
							.replace( /<head[\s\S]*?<\/head>/i, ' ' )
							.replace( /<[^>]+>/g, ' ' )
							.replace( /\s+/g, ' ' )
							.trim();
						const statusOk = c.status === undefined || response.status === c.status;
						const textOk = c.has !== undefined ? body.includes( c.has ) : c.lacks === undefined || ! body.includes( c.lacks );
						return { say, pass: statusOk && textOk, detail: 'HTTP ' + response.status + ', "' + body.slice( 0, 120 ) + '"' };
					} )
					.catch( ( error ) => ( { say, pass: false, detail: error.message } ) );
			}
			case 'notice': {
				// Any admin notice on the page, not just the first.
				const notices = Array.from( document.querySelectorAll( '.notice, .updated, .error' ) ).map( ( n ) =>
					n.textContent.replace( /\s+/g, ' ' ).trim()
				);
				const found = notices.find( ( n ) => n.includes( c.has !== undefined ? c.has : c.lacks ) );
				// With count: exactly that many notices contain the text.
				if ( c.count !== undefined ) {
					const matching = notices.filter( ( n ) => n.includes( c.has ) ).length;
					return {
						say: c.say || c.count + ' notices say "' + c.has + '"',
						pass: matching === c.count,
						detail: 'found ' + matching + ' of ' + notices.length + ' notices',
					};
				}
				return {
					say: c.say || ( c.has !== undefined ? 'A notice says "' + c.has + '"' : 'No notice says "' + c.lacks + '"' ),
					pass: c.has !== undefined ? !! found : ! found,
					detail: found ? '"' + found.slice( 0, 200 ) + '"' : notices.length + ' notice(s), none matching',
				};
			}
		}

		return { say: 'Unknown check ' + c.type, pass: false, detail: '' };
	}

	/* Judging the items that apply to this page. */
	async function judge() {
		for ( const item of C.items ) {
			if ( state[ item.id ] && state[ item.id ].state === 'pass' ) {
				continue;
			}
			// A page check answers with a promise.
			if ( ! ( await Promise.all( item.when.map( evaluate ) ) ).every( ( r ) => r.pass ) ) {
				continue;
			}

			let results = await Promise.all( item.checks.map( evaluate ) );
			if ( item.server ) {
				const server = await api( 'checklist/evaluate', { id: item.id } );
				if ( ! server.applies ) {
					continue;
				}
				results = results.concat( server.results );
			}

			const pass = results.every( ( r ) => r.pass );
			const detail = results
				.filter( ( r ) => ! r.pass )
				.map( ( r ) => r.say + ' (' + r.detail + ')' )
				.join( '; ' );

			state[ item.id ] = { state: pass ? 'pass' : 'fail', detail, at: new Date().toISOString() };
			await api( 'checklist/state', { id: item.id, state: pass ? 'pass' : 'fail', detail } );
		}
		render();
	}

	/* Report. */
	function report() {
		const done = C.items.filter( ( i ) => state[ i.id ] && state[ i.id ].state === 'pass' ).length;
		const lines = [ '# ' + C.title + ': ' + done + ' of ' + C.items.length + ' passed', '' ];
		C.items.forEach( ( item, i ) => {
			const s = state[ item.id ];
			const mark = ! s ? '⬜' : s.state === 'pass' ? '✅' : '❌';
			lines.push( ( i + 1 ) + '. ' + mark + ' ' + item.do );
			lines.push( '    - Expect: ' + item.expect );
			if ( s && s.state === 'fail' && s.detail ) {
				lines.push( '    - Problem: ' + s.detail );
			}
		} );
		return lines.join( '\n' ) + '\n';
	}

	/* Background jobs (sites with "queue"). */
	let queue = null;
	let lastRun = '';

	const shortLink = ( url ) => {
		if ( ! url ) {
			return '';
		}
		const match = url.match( /^https?:\/\/harness\.test\/([^/]+)/ );
		return match ? match[ 1 ] : url.replace( /^https?:\/\//, '' ).slice( 0, 40 );
	};

	const due = ( seconds ) => {
		if ( seconds <= 60 ) {
			return 'due now';
		}
		if ( seconds < 3600 ) {
			return 'in ' + Math.round( seconds / 60 ) + ' min';
		}
		return 'in ' + Math.round( seconds / 3600 ) + ' h';
	};

	const jobLine = ( job ) =>
		esc( job.label ) +
		( job.link ? ' · <b>' + esc( shortLink( job.link ) ) + '</b>' : '' ) +
		( job.attempt !== null && job.attempt !== undefined ? ' · attempt ' + esc( job.attempt ) : '' );

	async function loadQueue() {
		queue = await api( 'queue' );
		render();
	}

	async function runQueue( mode ) {
		// One job per request, so a long run never holds a single request open.
		const max = mode === 'all' ? 60 : 1;
		const ran = [];
		for ( let i = 0; i < max; i++ ) {
			status = mode === 'all' ? 'Running every job... (' + ran.length + ' done)' : 'Running the next job...';
			render();
			const result = await api( 'queue/run', { mode: 'next' } );
			queue = result.queue;
			if ( ! result.ran.length ) {
				break;
			}
			ran.push( ...result.ran );
		}
		lastRun = ran.length
			? ran.map( ( j ) => j.label + ( j.link ? ' (' + shortLink( j.link ) + ')' : '' ) + ': ' + j.status + ( j.message ? ', ' + j.message : '' ) ).join( ' | ' )
			: 'Nothing was waiting.';
		status = '';
		render();
		await judgeSoon();
	}

	function queueHtml() {
		if ( ! C.queue ) {
			return '';
		}
		if ( ! queue ) {
			return '<h4 class="lfh-cl-h">Background jobs</h4><p>Loading...</p>';
		}
		const pending = queue.pending
			.map( ( j ) => '<li>' + jobLine( j ) + ' · <em>' + due( j.in ) + '</em></li>' )
			.join( '' );
		const recent = queue.recent
			.map( ( j ) => '<li class="lfh-cl-job-' + esc( j.status ) + '"><span class="lfh-cl-mark">' + ( j.status === 'complete' ? '&#10003;' : '&#10007;' ) + '</span>' + jobLine( j ) + ( j.message ? ' · <em>' + esc( j.message ) + '</em>' : '' ) + '</li>' )
			.join( '' );

		return (
			'<h4 class="lfh-cl-h">Background jobs</h4>' +
			'<p class="lfh-cl-actions"><button type="button" data-cl="run-next">Run next job</button><button type="button" data-cl="run-all">Run all jobs</button><button type="button" data-cl="refresh">Refresh</button></p>' +
			( lastRun ? '<p class="lfh-cl-status">Just ran: ' + esc( lastRun ) + '</p>' : '' ) +
			'<p><b>Waiting (' + queue.pending.length + ')</b></p><ul class="lfh-cl-jobs">' + ( pending || '<li>Nothing waiting.</li>' ) + '</ul>' +
			'<p><b>Recently run</b></p><ul class="lfh-cl-jobs">' + ( recent || '<li>Nothing yet.</li>' ) + '</ul>'
		);
	}

	/* Panel. */
	const panel = document.createElement( 'div' );
	panel.id = 'lfh-checklist';
	document.body.appendChild( panel );

	let lastHtml = '';

	function render() {
		const done = C.items.filter( ( i ) => state[ i.id ] && state[ i.id ].state === 'pass' ).length;
		const next = C.items.find( ( i ) => ! state[ i.id ] || state[ i.id ].state !== 'pass' );

		const items = C.items
			.map( ( item ) => {
				const s = state[ item.id ];
				const cls = s ? s.state : item === next ? 'next' : 'todo';
				const mark = { pass: '&#10003;', fail: '&#10007;', next: '&#9656;', todo: '&#9675;' }[ cls ];
				return (
					'<li class="lfh-cl-' + cls + '">' +
					'<span class="lfh-cl-mark">' + mark + '</span>' +
					'<div><p><b>Do:</b> ' + esc( item.do ) + ( item.link ? ' <a href="' + esc( item.link ) + '">Open</a>' : '' ) + '</p>' +
					'<p><b>Expect:</b> ' + esc( item.expect ) + '</p>' +
					( s && s.state === 'fail' && s.detail ? '<p class="lfh-cl-problem">' + esc( s.detail ) + '</p>' : '' ) +
					'</div></li>'
				);
			} )
			.join( '' );

		const className = collapsed ? 'lfh-cl-collapsed' : '';
		if ( panel.className !== className ) {
			panel.className = className;
		}

		const html =
			'<div class="lfh-cl-head"><strong>' + esc( C.title ) + '</strong><span>' + done + ' of ' + C.items.length + ' done</span>' +
			'<button type="button" data-cl="toggle">' + ( collapsed ? 'Open' : 'Hide' ) + '</button></div>' +
			( collapsed
				? ''
				: '<div class="lfh-cl-body">' +
				  ( C.intro ? '<p class="lfh-cl-intro">' + esc( C.intro ) + '</p>' : '' ) +
				  ( C.online_toggle
					? '<p class="lfh-cl-status">Archive.org stand-in: <b>' + ( C.archive_online === 'no' ? 'offline' : 'online' ) + '</b> <button type="button" data-cl="toggle-online">' + ( C.archive_online === 'no' ? 'Bring back online' : 'Take offline' ) + '</button></p>'
					: '' ) +
				  '<ol>' + items + '</ol>' +
				  queueHtml() +
				  '<p class="lfh-cl-actions"><button type="button" data-cl="copy">Copy report</button><button type="button" data-cl="reset">Start again</button></p>' +
				  ( status ? '<p class="lfh-cl-status">' + esc( status ) + '</p>' : '' ) +
				  '</div>' );

		// Only touch the DOM when something changed.
		if ( html !== lastHtml ) {
			panel.innerHTML = html;
			lastHtml = html;
		}
	}

	panel.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( 'button[data-cl]' );
		if ( ! button ) {
			return;
		}
		const action = button.getAttribute( 'data-cl' );

		try {
			if ( action === 'toggle' ) {
				collapsed = ! collapsed;
				try {
					window.localStorage.setItem( 'lfh-checklist-collapsed', collapsed ? '1' : '0' );
				} catch ( e ) {
					// Not remembered, that is fine.
				}
			} else if ( action === 'copy' ) {
				const text = report();
				window.console.log( text );
				await navigator.clipboard.writeText( text );
				status = 'Report copied (also in the console).';
			} else if ( action === 'reset' ) {
				await api( 'checklist/reset', {} );
				Object.keys( state ).forEach( ( key ) => delete state[ key ] );
				status = 'Ticks cleared.';
			} else if ( action === 'run-next' || action === 'run-all' ) {
				await runQueue( action === 'run-all' ? 'all' : 'next' );
			} else if ( action === 'refresh' ) {
				await loadQueue();
			} else if ( action === 'toggle-online' ) {
				// lfh/v1/settings also clears the Link Fixer's cached online status.
				await api( 'settings', { archive_online: C.archive_online === 'no' ? 'yes' : 'no' } );
				window.location.reload();
				return;
			}
		} catch ( e ) {
			status = action + ' failed: ' + e.message;
		}
		render();
	} );

	window.LFH_CHECKLIST_API = { report, state, judge };

	// Things that change without a page load (a setting shown or hidden as a box is ticked) are
	// checked again after any change to a field, once things settle. One check at a time.
	let judging = false;
	let again = false;
	let timer = null;

	async function judgeSoon() {
		if ( judging ) {
			again = true;
			return;
		}
		judging = true;
		try {
			await judge();
		} catch ( e ) {
			status = 'Could not check this page: ' + e.message;
			render();
		}
		judging = false;
		if ( again ) {
			again = false;
			judgeSoon();
		}
	}

	[ 'change', 'input' ].forEach( ( type ) =>
		document.addEventListener( type, ( event ) => {
			if ( panel.contains( event.target ) ) {
				return;
			}
			window.clearTimeout( timer );
			timer = window.setTimeout( judgeSoon, 500 );
		} )
	);

	// Front end link checks finish after the page has loaded (watch.js).
	document.addEventListener( 'lfh:fetch', () => {
		window.clearTimeout( timer );
		timer = window.setTimeout( judgeSoon, 500 );
	} );

	render();

	// Judge once the page has finished loading.
	( async () => {
		while ( document.readyState !== 'complete' ) {
			await sleep( 200 );
		}
		await sleep( 700 );
		judgeSoon();
		if ( C.queue ) {
			loadQueue().catch( ( e ) => {
				status = 'Could not load the background jobs: ' + e.message;
				render();
			} );
		}
	} )();
} )();
