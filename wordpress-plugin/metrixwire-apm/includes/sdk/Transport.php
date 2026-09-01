<?php
/**
 * WordPress transport for the bundled SDK.
 *
 * Deliberately diverges from the `metrixwire/php` Composer package: that copy
 * posts with cURL, which the WordPress plugin guidelines forbid. Same class name
 * and signature, so `MetrixWire::flush()` is unchanged — only the wire call is.
 * Going through `wp_remote_post()` also honours whatever HTTP transport, proxy
 * and filters the host site has configured.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire;

defined( 'ABSPATH' ) || exit;

/** Sends a batch of traces to the ingest endpoint, swallowing every failure. */
final class Transport {

	public function __construct(
		private string $endpoint,
		private string $api_key,
		private int $timeout_ms = 3000,
	) {
	}

	/**
	 * @param array<int, array<string, mixed>> $traces Trace payloads.
	 */
	public function send( array $traces ): void {
		if ( array() === $traces || '' === $this->api_key ) {
			return;
		}

		$body = wp_json_encode( array( 'traces' => $traces ) );

		if ( false === $body ) {
			return;
		}

		// Non-blocking: the response is already on its way to the visitor by the
		// time this runs, and a slow ingest must never hold the request open.
		wp_remote_post(
			$this->ingest_url(),
			array(
				'timeout'     => max( 1, (int) ceil( $this->timeout_ms / 1000 ) ),
				'blocking'    => false,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $this->api_key,
				),
				'body'        => $body,
			)
		);
	}

	/** Accepts either the base URL or a full /ingest URL. */
	private function ingest_url(): string {
		$url = untrailingslashit( $this->endpoint );

		return str_ends_with( $url, '/ingest' ) ? $url : $url . '/ingest';
	}
}
