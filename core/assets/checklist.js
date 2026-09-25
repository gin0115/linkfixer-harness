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
			case 'text':
				return {
					say: c.say || ( c.has !== undefined ? '"' + c.has + '" is shown' : '"' + c.lacks + '" is not shown' ),
					pass: el !== null && ( c.has !== undefined ? text.includes( c.has ) : ! text.includes( c.lacks ) ),
					detail: el ? '"' + text.slice( 0, 160 ) + '"' : 'not found: ' + c.selector,
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
		}

		return { say: 'Unknown check ' + c.type, pass: false, detail: '' };
	}

	/* Judging the items that apply to this page. */
	async function judge() {
		for ( const item of C.items ) {
			if ( state[ item.id ] && state[ item.id ].state === 'pass' ) {
				continue;
			}
			if ( ! item.when.map( evaluate ).every( ( r ) => r.pass ) ) {
				continue;
			}

			let results = item.checks.map( evaluate );
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
				  '<ol>' + items + '</ol>' +
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
			}
		} catch ( e ) {
			status = action + ' failed: ' + e.message;
		}
		render();
	} );

	window.LFH_CHECKLIST_API = { report, state, judge };

	render();

	// Judge once the page has finished loading.
	( async () => {
		while ( document.readyState !== 'complete' ) {
			await sleep( 200 );
		}
		await sleep( 700 );
		try {
			await judge();
		} catch ( e ) {
			status = 'Could not check this page: ' + e.message;
			render();
		}
	} )();
} )();
