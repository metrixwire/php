/**
 * The one interaction on these screens that isn't a form post: checking that
 * the stored credentials are accepted, answered in place next to the button.
 *
 * @package MetrixWire\APM
 */

( function ( wp, settings ) {
	'use strict';

	const __ = wp.i18n.__;

	function setStatus( element, message, state ) {
		element.textContent = message;
		element.className = state
			? 'metrixwire-apm-status metrixwire-apm-status--' + state
			: 'metrixwire-apm-status';
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const button = document.getElementById( 'metrixwire-apm-test-connection' );
		const status = document.getElementById( 'metrixwire-apm-test-status' );

		if ( ! button || ! status ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			setStatus( status, __( 'Testing…', 'metrixwire-apm' ), '' );

			const body = new FormData();
			body.append( 'action', settings.action );
			body.append( 'nonce', settings.nonce );

			window
				.fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					const fallback = result.success
						? __( 'Connected.', 'metrixwire-apm' )
						: __( 'Failed.', 'metrixwire-apm' );

					setStatus(
						status,
						( result.data && result.data.message ) || fallback,
						result.success ? 'ok' : 'error'
					);
				} )
				.catch( function () {
					setStatus(
						status,
						__( 'Request failed — check the browser console.', 'metrixwire-apm' ),
						'error'
					);
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	} );
} )( window.wp, window.metrixwireApmAdmin );
