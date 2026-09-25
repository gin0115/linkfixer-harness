/**
 * Link Fixer Harness panel.
 *
 * Predicts what the Link Fixer should do to every seeded link on this page load,
 * watches what it actually does (window.LFH_LOG from watch.js), and shows the
 * result per link plus a checklist. window.LFH.report() returns it all as JSON.
 */
( function () {
	'use strict';

	const P = window.LFH_PAGE;
	const LOG = window.LFH_LOG;
	if ( ! P || ! LOG ) {
		return;
	}

	const S = P.settings;
	const DAY = 86400000;
	const loadedAt = Date.now();

	const esc = ( value ) =>
		String( value === null || value === undefined ? '' : value ).replace(
			/[&<>"']/g,
			( c ) =>
				( {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#39;',
				} )[ c ]
		);
	const yn = ( value ) => ( value ? 'yes' : 'no' );
	const norm = ( url ) => String( url || '' ).replace( /\/$/, '' );
	const utc = ( date ) => Date.parse( String( date ).replace( ' ', 'T' ) + 'Z' );
	const age = ( date ) => {
		const days = ( Date.now() - utc( date ) ) / DAY;
		return days >= 1
			? days.toFixed( 1 ) + 'd ago'
			: Math.round( days * 24 * 60 ) + 'm ago';
	};

	/*
	 * Prediction, following the Link Fixer's own rules:
	 * - Link_Repository::get_links_for_post( id, true ) leaves out excluded links.
	 * - front_link_checker.js checkLink() skips links with no archive, and checks when
	 *   there is no check yet or Math.ceil(days since) > linkDelayInDays.
	 * - Link_Check_Rest::needs_check() re-checks when whole days since >= duration.
	 * - A checker exception is a 500 and nothing is recorded.
	 * - Link::assess_validity(): broken when the last failed_count checks are all invalid.
	 * - addDataAttributes() swaps the href only when broken and the mode is replace_link.
	 */
	function assess( codes ) {
		const last = codes.slice( -S.failed_count );
		if ( last.length === 0 || last.length < S.failed_count ) {
			return false;
		}
		return ! last.some( ( code ) => S.valid_codes.includes( code ) );
	}

	function predict( link ) {
		const db = link.db;
		const script =
			S.fixer_option !== 'do_nothing' && link.exclusion !== 'post';
		const inData = script && link.exclusion === 'none' && !! db;
		const hasArchive = !! ( db && db.archived );
		const codes = db ? db.checks.map( ( c ) => parseInt( c.http_code, 10 ) ) : [];
		const last = db && db.checks.length ? db.checks[ db.checks.length - 1 ] : null;
		const since = last ? Date.now() - utc( last.date ) : null;

		const wantsCheck =
			inData &&
			hasArchive &&
			( last === null || Math.ceil( since / DAY ) > S.duration );

		let serverChecks = false;
		let status = null;
		let result = null;
		let broken = db ? !! db.broken : false;
		let codesAfter = codes;

		if ( wantsCheck ) {
			serverChecks = last === null || Math.floor( since / DAY ) >= S.duration;
			status = serverChecks && link.next_check === 'offline' ? 500 : 200;
			if ( serverChecks && link.next_check !== 'offline' ) {
				result = parseInt( link.next_check, 10 );
				codesAfter = codes.concat( result );
				broken = assess( codesAfter );
			}
		}

		return {
			script,
			inData,
			hasArchive,
			wantsCheck,
			serverChecks,
			status,
			result,
			broken,
			codesAfter,
			swapped: inData && hasArchive && broken && S.fixer_option === 'replace_link',
			attributes: inData && hasArchive,
		};
	}

	/* What is actually on the page. */
	function pluginData() {
		const ids = new Set();
		let script = false;

		if ( window.iawmlfArchivedLinks ) {
			script = true;
			try {
				JSON.parse( window.iawmlfArchivedLinks.links ).forEach( ( l ) =>
					ids.add( l.id )
				);
			} catch ( e ) {
				// Malformed data counts as none.
			}
		}

		document
			.querySelectorAll( '.__iawmlf-post-loop-links[data-iawmlf-links]' )
			.forEach( ( span ) => {
				try {
					JSON.parse( span.getAttribute( 'data-iawmlf-links' ) ).forEach(
						( l ) => ids.add( l.id )
					);
				} catch ( e ) {
					// Malformed data counts as none.
				}
			} );

		return { script, ids };
	}

	function observe( row ) {
		const data = pluginData();
		return {
			script: data.script,
			inData: !! row.link.db && data.ids.has( row.link.db.id ),
			fetches: LOG.fetches.filter(
				( f ) => norm( f.link ) === norm( row.link.url )
			),
			swapped: row.anchors.some(
				( a ) =>
					a.classList.contains( 'iawmlf-broken-link' ) &&
					( a.getAttribute( 'href' ) || '' ).indexOf( 'web.archive.org' ) !== -1
			),
			attributes: row.anchors.some( ( a ) =>
				a.hasAttribute( 'data-iawmlf-archived-url' )
			),
			mutations: LOG.mutations.filter( ( m ) => m.id === row.link.id ),
		};
	}

	/* Rows. */
	const rows = P.links.map( ( link ) => ( {
		link,
		exp: predict( link ),
		act: null,
		anchors: Array.from(
			document.querySelectorAll( 'a[data-lfh-id="' + link.id + '"]' )
		),
		visibleAt: null,
		verdict: 'scroll',
		problems: [],
		open: false,
	} ) );

	const visibility = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) {
					return;
				}
				const row = rows.find( ( r ) => r.anchors.includes( entry.target ) );
				if ( row && ! row.visibleAt ) {
					row.visibleAt = Date.now();
					tick();
				}
			} );
		},
		{ threshold: 0 }
	);
	rows.forEach( ( row ) =>
		row.anchors.forEach( ( a ) => visibility.observe( a ) )
	);

	function judge( row ) {
		const e = row.exp;
		const a = observe( row );
		row.act = a;

		if ( ! row.visibleAt ) {
			row.verdict = row.anchors.length ? 'scroll' : 'fail';
			row.problems = row.anchors.length ? [] : [ 'link not found in the post' ];
			return;
		}

		const f = a.fetches[ 0 ];
		const sinceVisible = Date.now() - row.visibleAt;

		// Wait for a check that should come, or long enough for one that should not.
		if ( e.wantsCheck && ( ! f || ! f.ended ) && sinceVisible < 8000 ) {
			row.verdict = 'waiting';
			return;
		}
		if ( f && f.ended && Date.now() - f.ended < 400 ) {
			row.verdict = 'waiting';
			return;
		}
		if ( ! e.wantsCheck && sinceVisible < 2500 ) {
			row.verdict = 'waiting';
			return;
		}

		const p = [];
		if ( e.inData !== a.inData ) {
			p.push( 'in link data: expected ' + yn( e.inData ) + ', got ' + yn( a.inData ) );
		}
		if ( e.wantsCheck && a.fetches.length === 0 ) {
			p.push( 'expected a REST check, none was made' );
		}
		if ( ! e.wantsCheck && a.fetches.length > 0 ) {
			p.push( 'expected no REST check, got ' + a.fetches.length );
		}
		if ( a.fetches.length > 1 ) {
			p.push( a.fetches.length + ' REST checks, expected at most 1' );
		}
		if ( e.wantsCheck && f ) {
			if ( f.status !== e.status ) {
				p.push( 'REST status: expected ' + e.status + ', got ' + f.status );
			}
			if ( e.result !== null ) {
				const call = ( f.calls || [] ).find( ( c ) => c.method === 'check_single' );
				if ( ! call ) {
					p.push( 'no check_single call in the server call log' );
				} else if ( ! call.outcome || call.outcome.returned !== e.result ) {
					p.push(
						'check_single returned ' +
							JSON.stringify( call.outcome ) +
							', expected ' +
							e.result
					);
				}
				if ( f.json && typeof f.json.valid === 'boolean' && f.json.valid !== ! e.broken ) {
					p.push( 'REST valid: expected ' + ! e.broken + ', got ' + f.json.valid );
				}
			}
		}
		if ( e.swapped !== a.swapped ) {
			p.push( 'swapped: expected ' + yn( e.swapped ) + ', got ' + yn( a.swapped ) );
		}
		if ( e.attributes !== a.attributes ) {
			p.push(
				'data-iawmlf-* attributes: expected ' +
					yn( e.attributes ) +
					', got ' +
					yn( a.attributes )
			);
		}

		row.problems = p;
		row.verdict = p.length ? 'fail' : 'pass';
	}

	/* Checklist. */
	const manual = {
		hover: {
			text: 'Hover a swapped link (red outline): the browser shows a web.archive.org address',
			state: 'todo',
		},
		click: {
			text: 'Click a swapped link: it opens the Wayback Machine copy',
			state: 'todo',
		},
	};

	function checklist() {
		const items = [];
		const add = ( id, text, state, detail ) =>
			items.push( { id, text, state, detail: detail || '' } );

		add(
			'fakes',
			'Harness fake Archive.org clients are active',
			S.client_mode === 'fake' ? 'pass' : 'fail',
			'client mode: ' + S.client_mode
		);

		const scriptExpected = S.fixer_option !== 'do_nothing' && P.role !== 'excluded';
		add(
			'script',
			scriptExpected
				? 'Link Fixer front-end script is loaded'
				: 'Link Fixer front-end script is NOT loaded',
			pluginData().script === scriptExpected ? 'pass' : 'fail'
		);

		const seen = rows.filter( ( r ) => r.visibleAt ).length;
		add(
			'seen',
			'Every link has been on screen (scroll through the whole post)',
			seen === rows.length ? 'pass' : 'todo',
			seen + ' of ' + rows.length
		);

		const settled = rows.filter( ( r ) => r.verdict === 'pass' || r.verdict === 'fail' );
		const failed = rows.filter( ( r ) => r.verdict === 'fail' );
		add(
			'links',
			'Every link did what was predicted on this load',
			settled.length < rows.length ? 'todo' : failed.length ? 'fail' : 'pass',
			settled.length - failed.length + ' passed, ' + failed.length + ' failed, ' + ( rows.length - settled.length ) + ' waiting'
		);

		const excluded = rows.filter( ( r ) => r.link.exclusion !== 'none' );
		const excludedCalls = excluded.filter( ( r ) => r.act && r.act.fetches.length );
		add(
			'excluded',
			'No REST check for any excluded link',
			! excluded.length ? 'n/a' : excludedCalls.length ? 'fail' : 'pass',
			excludedCalls.map( ( r ) => r.link.id ).join( ', ' )
		);

		Object.keys( manual ).forEach( ( id ) =>
			add( id, manual[ id ].text, manual[ id ].state, 'manual' )
		);

		return items;
	}

	/* Report and saving. */
	function report() {
		return {
			scenario: P.scenario,
			role: P.role,
			post_id: P.post_id,
			load: P.load,
			url: location.href,
			at: new Date().toISOString(),
			settings: S,
			checklist: checklist(),
			links: rows.map( ( r ) => ( {
				id: r.link.id,
				label: r.link.label,
				exclusion: r.link.exclusion,
				verdict: r.verdict,
				problems: r.problems,
				expected: r.exp,
				actual: r.act && {
					script: r.act.script,
					in_data: r.act.inData,
					swapped: r.act.swapped,
					attributes: r.act.attributes,
					fetches: r.act.fetches.map( ( f ) => ( {
						status: f.status,
						updated: f.json && f.json.updated,
						valid: f.json && f.json.valid,
						calls: f.calls,
					} ) ),
				},
			} ) ),
		};
	}

	async function api( path, method, body ) {
		const response = await fetch( P.rest_root + 'lfh/v1/' + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': P.nonce,
			},
			body: body ? JSON.stringify( body ) : undefined,
		} );
		if ( ! response.ok ) {
			throw new Error( path + ': HTTP ' + response.status );
		}
		return response.json();
	}

	let saved = false;
	let status = '';

	async function save() {
		try {
			await api( 'results', 'POST', report() );
			saved = true;
			status = 'Results saved (load #' + P.load + ')';
		} catch ( e ) {
			status = 'Save failed: ' + e.message;
		}
		render();
	}

	window.LFH = { report, save, rows };

	/* Panel. */
	const panel = document.createElement( 'div' );
	panel.id = 'lfh-panel';
	document.body.appendChild( panel );

	let collapsed = false;
	try {
		collapsed = window.localStorage.getItem( 'lfh-collapsed' ) === '1';
	} catch ( e ) {
		collapsed = false;
	}

	const icon = { pass: '✓', fail: '✗', waiting: '…', scroll: '↓', todo: '○', 'n/a': '-' };

	function describeExpected( e ) {
		if ( ! e.script ) {
			return 'no Link Fixer script on this page';
		}
		if ( ! e.inData ) {
			return 'not in link data, untouched';
		}
		if ( ! e.hasArchive ) {
			return 'no archive, skipped';
		}
		let text = e.wantsCheck
			? 'check → ' + ( e.status === 500 ? '500, nothing recorded' : e.result === null ? 'not re-checked by server' : e.result )
			: 'no check';
		text += ', ' + ( e.swapped ? 'swapped' : 'not swapped' );
		return text;
	}

	function describeActual( r ) {
		const a = r.act;
		if ( ! a ) {
			return '';
		}
		const f = a.fetches[ 0 ];
		let text = a.inData ? 'in data' : 'not in data';
		text += f
			? ', check → ' + ( f.status === null ? 'pending' : f.status ) + ( f.json && typeof f.json.valid === 'boolean' ? ( f.json.valid ? ' valid' : ' broken' ) : '' )
			: ', no check';
		text += ', ' + ( a.swapped ? 'swapped' : 'not swapped' );
		return text;
	}

	function history( r ) {
		if ( ! r.link.db ) {
			return 'no database row';
		}
		if ( ! r.link.db.checks.length ) {
			return 'no checks yet';
		}
		return r.link.db.checks
			.map( ( c ) => c.http_code + ' (' + age( c.date ) + ')' )
			.join( ', ' );
	}

	function render() {
		const posts = Object.keys( P.posts )
			.map( ( role ) => {
				const post = P.posts[ role ];
				return role === P.role
					? '<strong>' + esc( post.title ) + '</strong>'
					: '<button type="button" data-go="' + esc( post.url ) + '">' + esc( post.title ) + '</button>';
			} )
			.join( ' · ' );

		const rowsHtml = rows
			.map( ( r, i ) => {
				let html =
					'<tr class="lfh-row lfh-' + r.verdict + '" data-row="' + i + '">' +
					'<td class="lfh-state">' + icon[ r.verdict ] + '</td>' +
					'<td><strong>' + esc( r.link.id ) + '</strong> ' + esc( r.link.label ) + '</td>' +
					'<td>' + esc( describeExpected( r.exp ) ) + '</td>' +
					'<td>' + esc( describeActual( r ) ) + '</td>' +
					'</tr>';

				if ( r.open || r.verdict === 'fail' ) {
					const f = r.act && r.act.fetches[ 0 ];
					html +=
						'<tr class="lfh-detail"><td></td><td colspan="3">' +
						'<p>' + esc( r.link.expect ) + '</p>' +
						( r.problems.length ? '<p class="lfh-problems">' + r.problems.map( esc ).join( '<br>' ) + '</p>' : '' ) +
						'<p><b>URL</b> ' + esc( r.link.url ) + '</p>' +
						'<p><b>Exclusion</b> ' + esc( r.link.exclusion ) + ' · <b>Next scripted check</b> ' + esc( r.link.next_check ) + '</p>' +
						'<p><b>History at page load</b> ' + esc( history( r ) ) + ( r.link.db ? ' · <b>broken</b> ' + yn( r.link.db.broken ) : '' ) + '</p>' +
						( f ? '<p><b>Server calls</b> ' + esc( ( f.calls || [] ).map( ( c ) => c.method + ' → ' + JSON.stringify( c.outcome ) ).join( ' | ' ) || 'none' ) + '</p>' : '' ) +
						( r.act && r.act.mutations.length ? '<p><b>Link changes</b> ' + esc( r.act.mutations.map( ( m ) => m.attribute + ': ' + ( m.to || '' ) ).join( ' | ' ) ) + '</p>' : '' ) +
						'</td></tr>';
				}
				return html;
			} )
			.join( '' );

		const checklistHtml = checklist()
			.map(
				( item ) =>
					'<li class="lfh-' + esc( item.state === 'n/a' ? 'na' : item.state ) + '">' +
					'<span class="lfh-state">' + icon[ item.state ] + '</span> ' + esc( item.text ) +
					( item.detail && item.detail !== 'manual' ? ' <em>(' + esc( item.detail ) + ')</em>' : '' ) +
					( item.detail === 'manual'
						? ' <button type="button" data-manual="' + esc( item.id ) + '" data-value="pass">Pass</button><button type="button" data-manual="' + esc( item.id ) + '" data-value="fail">Fail</button>'
						: '' ) +
					'</li>'
			)
			.join( '' );

		const className = collapsed ? 'lfh-collapsed' : '';
		if ( panel.className !== className ) {
			panel.className = className;
		}
		const html =
			'<div class="lfh-head">' +
			'<strong>Link Fixer Harness</strong> <span>load #' + esc( P.load ) + ' · ' + esc( P.scenario ) + ' · ' + esc( P.role ) + '</span>' +
			'<button type="button" data-action="toggle">' + ( collapsed ? 'Open' : 'Hide' ) + '</button>' +
			'</div>' +
			( collapsed
				? ''
				: '<div class="lfh-body">' +
				  '<p class="lfh-settings">Mode <b>' + esc( S.fixer_option ) + '</b> · checked every <b>' + esc( S.duration ) + '</b> days · broken after <b>' + esc( S.failed_count ) + '</b> failures · fakes <b>' + esc( S.client_mode ) + '</b> · Link Fixer ' + esc( S.link_fixer_version ) + '</p>' +
				  '<p class="lfh-posts">' + posts + '</p>' +
				  '<p class="lfh-actions">' +
				  '<button type="button" data-action="age">Age 4 days + reload</button>' +
				  '<button type="button" data-action="reload">Reload</button>' +
				  '<button type="button" data-action="seed">Reseed</button>' +
				  '<select data-action="mode">' +
				  [ 'replace_link', 'check_only', 'do_nothing' ].map( ( m ) => '<option value="' + m + '"' + ( m === S.fixer_option ? ' selected' : '' ) + '>' + m + '</option>' ).join( '' ) +
				  '</select>' +
				  '<button type="button" data-action="copy">Copy report</button>' +
				  '<button type="button" data-action="save">Save results</button>' +
				  '</p>' +
				  ( status ? '<p class="lfh-status">' + esc( status ) + '</p>' : '' ) +
				  '<h4>Checklist</h4><ul class="lfh-checklist">' + checklistHtml + '</ul>' +
				  '<h4>Links (click a row for detail)</h4>' +
				  '<table class="lfh-table"><thead><tr><th></th><th>Link</th><th>Expected</th><th>Actual</th></tr></thead><tbody>' + rowsHtml + '</tbody></table>' +
				  '</div>' );

		// Only touch the DOM when something changed, so an open select is not reset.
		if ( html !== lastHtml ) {
			panel.innerHTML = html;
			lastHtml = html;
		}
	}

	let lastHtml = '';

	function paint() {
		rows.forEach( ( r ) =>
			r.anchors.forEach( ( a ) => {
				if ( a.getAttribute( 'data-lfh-state' ) !== r.verdict ) {
					a.setAttribute( 'data-lfh-state', r.verdict );
				}
			} )
		);
	}

	async function act( action, value ) {
		try {
			if ( action === 'toggle' ) {
				collapsed = ! collapsed;
				try {
					window.localStorage.setItem( 'lfh-collapsed', collapsed ? '1' : '0' );
				} catch ( e ) {
					// Not remembered, that is fine.
				}
				render();
			} else if ( action === 'age' ) {
				status = 'Ageing checks...';
				render();
				await api( 'age', 'POST', { days: 4 } );
				location.reload();
			} else if ( action === 'reload' ) {
				location.reload();
			} else if ( action === 'seed' ) {
				status = 'Reseeding...';
				render();
				const result = await api( 'seed', 'POST' );
				location.href = result.main_url;
			} else if ( action === 'mode' ) {
				await api( 'settings', 'POST', { fixer_option: value } );
				location.reload();
			} else if ( action === 'copy' ) {
				const json = JSON.stringify( report(), null, 2 );
				window.console.log( json );
				await navigator.clipboard.writeText( json );
				status = 'Report copied (also in the console)';
				render();
			} else if ( action === 'save' ) {
				await save();
			}
		} catch ( e ) {
			status = action + ' failed: ' + e.message;
			render();
		}
	}

	panel.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( 'button' );
		if ( button && button.dataset.action ) {
			act( button.dataset.action );
			return;
		}
		// Buttons, not links, so the Link Fixer does not pick them up as page links.
		if ( button && button.dataset.go ) {
			location.href = button.dataset.go;
			return;
		}
		if ( button && button.dataset.manual ) {
			manual[ button.dataset.manual ].state = button.dataset.value;
			render();
			return;
		}
		const row = event.target.closest( 'tr.lfh-row' );
		if ( row ) {
			const r = rows[ parseInt( row.dataset.row, 10 ) ];
			r.open = ! r.open;
			render();
		}
	} );

	panel.addEventListener( 'change', ( event ) => {
		if ( event.target.dataset.action === 'mode' ) {
			act( 'mode', event.target.value );
		}
	} );

	/* Loop. */
	let timer = null;

	function tick() {
		rows.forEach( judge );
		paint();
		render();

		const settled = rows.every( ( r ) => r.verdict === 'pass' || r.verdict === 'fail' );
		if ( settled && ! saved ) {
			save();
		}
		if ( settled && timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	document.addEventListener( 'lfh:fetch', tick );
	document.addEventListener( 'lfh:mutation', tick );
	window.addEventListener( 'scroll', () => {
		if ( ! timer ) {
			timer = window.setInterval( tick, 500 );
		}
	} );

	timer = window.setInterval( tick, 500 );
	tick();

	window.LFH.loadedAt = loadedAt;
} )();
