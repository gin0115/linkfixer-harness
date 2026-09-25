/**
 * Link Fixer Harness watcher.
 *
 * Loaded in the head, before the Link Fixer's footer script, so it sees every
 * link check request and every change the Link Fixer makes to a link.
 */
( function () {
	'use strict';

	const log = ( window.LFH_LOG = {
		start: Date.now(),
		fetches: [],
		mutations: [],
	} );

	const emit = ( name, detail ) =>
		document.dispatchEvent( new CustomEvent( name, { detail } ) );

	// The Link Fixer calls the global fetch() for each link check (front_link_checker.js verifyLink).
	const originalFetch = window.fetch;
	window.fetch = function ( input, init ) {
		const url =
			typeof input === 'string' ? input : ( input && input.url ) || '';

		if ( url.indexOf( 'iawmlf/v1/link-check' ) === -1 ) {
			return originalFetch.apply( this, arguments );
		}

		const entry = {
			url,
			link: null,
			started: Date.now(),
			ended: null,
			status: null,
			json: null,
			calls: [],
			error: null,
		};

		try {
			entry.link = JSON.parse( init.body ).link;
		} catch ( e ) {
			// Body was not JSON, leave the link empty.
		}

		log.fetches.push( entry );
		emit( 'lfh:fetch', entry );

		return originalFetch.apply( this, arguments ).then(
			( response ) => {
				entry.status = response.status;
				try {
					entry.calls = JSON.parse(
						response.headers.get( 'X-LFH-Calls' ) || '[]'
					);
				} catch ( e ) {
					entry.calls = [];
				}
				response
					.clone()
					.json()
					.then( ( json ) => {
						entry.json = json;
					} )
					.catch( () => {} )
					.finally( () => {
						entry.ended = Date.now();
						emit( 'lfh:fetch', entry );
					} );
				return response;
			},
			( error ) => {
				entry.error = String( error );
				entry.ended = Date.now();
				emit( 'lfh:fetch', entry );
				throw error;
			}
		);
	};

	// Every change the Link Fixer makes to a link.
	const watched = [
		'href',
		'class',
		'data-iawmlf-archived-url',
		'data-iawmlf-current-url',
		'data-iawmlf-archived-broken',
		'data-iawmlf-archived-last-checked',
	];

	new MutationObserver( ( list ) => {
		let changed = false;
		for ( const mutation of list ) {
			const anchor = mutation.target;
			if ( anchor.tagName !== 'A' ) {
				continue;
			}
			changed = true;
			if (
				mutation.attributeName === 'href' &&
				! anchor.hasAttribute( 'data-lfh-original-href' )
			) {
				anchor.setAttribute(
					'data-lfh-original-href',
					mutation.oldValue || ''
				);
			}
			log.mutations.push( {
				at: Date.now(),
				id: anchor.getAttribute( 'data-lfh-id' ),
				attribute: mutation.attributeName,
				from: mutation.oldValue,
				to: anchor.getAttribute( mutation.attributeName ),
			} );
		}
		// Only links matter. Emitting for anything else lets the panel's own updates loop forever.
		if ( changed ) {
			emit( 'lfh:mutation' );
		}
	} ).observe( document.documentElement, {
		subtree: true,
		attributes: true,
		attributeOldValue: true,
		attributeFilter: watched,
	} );
} )();
