<?php
/**
 * HTTP client for the MetrixWire API.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to `/api/wp/*` on behalf of the site.
 *
 * Every call happens in PHP, never in the browser: the site token is a read
 * credential for this site's data and a key to the dashboard, so it must not
 * reach a page an editor (or an XSS) could read it from.
 *
 * GETs are cached in a transient for a minute. An admin screen makes several
 * calls per load and administrators refresh freely; without this, a busy
 * wp-admin would put a steady synchronous load on the API for data that
 * changes on a detection cycle, not per pageview.
 */
final class Client {

	private const TIMEOUT_SECONDS = 10;

	/**
	 * Connecting is slower than a read: MetrixWire creates the account, the
	 * project and the API key, and sends the verification email before it
	 * answers. Giving up at the read timeout would strand a site whose account
	 * was in fact created.
	 */
	private const CONNECT_TIMEOUT_SECONDS = 30;

	private const CACHE_TTL = MINUTE_IN_SECONDS;

	/** Transient holding the list of cache keys we created, so they can be swept. */
	private const CACHE_INDEX = 'metrixwire_apm_cache_index';

	private Connection $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * GET a path, served from cache when it was fetched in the last minute.
	 *
	 * @param string               $path  API path, e.g. `/status`.
	 * @param array<string, mixed> $query Query-string arguments.
	 * @return array<string, mixed>|\WP_Error Decoded body, or an error.
	 */
	public function get( string $path, array $query = array(), bool $use_cache = true ) {
		$url = $this->url( $path );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$cache_key = 'metrixwire_apm_' . md5( $url . '|' . substr( $this->connection->site_token(), -8 ) );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );

			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = $this->request( 'GET', $url, null );

		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
			$this->remember_cache_key( $cache_key );
		}

		return $result;
	}

	/**
	 * POST/PATCH/DELETE — a write, so never cached, and it drops the read cache
	 * so the screen that rendered next doesn't show what was just changed away.
	 *
	 * @param array<string, mixed>|null $body Request body, JSON-encoded.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function write( string $method, string $path, ?array $body = null ) {
		$result = $this->request( $method, $this->url( $path ), $body );

		if ( ! is_wp_error( $result ) ) {
			self::flush_cache();
		}

		return $result;
	}

	/**
	 * An unauthenticated call — used only for `/provision` and `/link`, which run
	 * before the site holds any credential.
	 *
	 * @param array<string, mixed> $body Request body, JSON-encoded.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function connect( string $path, array $body ) {
		return $this->request( 'POST', $this->url( $path ), $body, false, self::CONNECT_TIMEOUT_SECONDS );
	}

	/**
	 * Issues the request and normalizes every failure mode into a WP_Error whose
	 * message is safe to show an administrator.
	 *
	 * @param array<string, mixed>|null $body       Request body, JSON-encoded.
	 * @param bool                      $with_token Send the site token.
	 * @param int                       $timeout    Seconds to wait for a response.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request( string $method, string $url, ?array $body, bool $with_token = true, int $timeout = self::TIMEOUT_SECONDS ) {
		$headers = array( 'Accept' => 'application/json' );

		if ( $with_token ) {
			$token = $this->connection->site_token();

			if ( '' === $token ) {
				return new \WP_Error( 'metrixwire_unlinked', __( 'This site is not connected to MetrixWire yet.', 'metrixwire-apm' ) );
			}

			$headers['Authorization'] = 'Bearer ' . $token;
			// Reported so the dashboard (and support) can see what a site runs.
			$headers['X-MetrixWire-Platform-Version'] = get_bloginfo( 'version' );
			$headers['X-MetrixWire-Plugin-Version']   = VERSION;
			// Tells the API this plugin renders the `critical` severity; without
			// it the API folds critical into high for older plugin builds.
			$headers['X-MetrixWire-Capabilities'] = 'severity-critical';
		}

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => $headers,
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'metrixwire_unreachable',
				sprintf(
					/* translators: %s: the underlying HTTP error reported by WordPress. */
					__( 'Could not reach MetrixWire: %s', 'metrixwire-apm' ),
					$response->get_error_message()
				)
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $status >= 200 && $status < 300 ) {
			return $decoded;
		}

		// The API's own message is the most useful thing we can show; the status
		// line is only a fallback for a proxy or gateway that swallowed it.
		$message = isset( $decoded['error'] ) ? (string) $decoded['error'] : sprintf(
			/* translators: %d: HTTP status code returned by MetrixWire. */
			__( 'MetrixWire returned an unexpected response (HTTP %d).', 'metrixwire-apm' ),
			$status
		);

		return new \WP_Error(
			isset( $decoded['code'] ) ? 'metrixwire_' . (string) $decoded['code'] : 'metrixwire_http_' . $status,
			$message,
			array( 'status' => $status )
		);
	}

	/** Absolute URL for an `/api/wp` path on the configured endpoint. */
	private function url( string $path ): string {
		return $this->connection->endpoint() . '/api/wp' . $path;
	}

	/**
	 * Track a cache key so {@see flush_cache()} can delete it.
	 *
	 * Transients have no "delete by prefix", and on a site with an external
	 * object cache there is no options table to scan either — so the plugin
	 * keeps its own index.
	 */
	private function remember_cache_key( string $key ): void {
		$index = get_transient( self::CACHE_INDEX );
		$index = is_array( $index ) ? $index : array();

		if ( in_array( $key, $index, true ) ) {
			return;
		}

		$index[] = $key;
		set_transient( self::CACHE_INDEX, $index, HOUR_IN_SECONDS );
	}

	/** Drop every cached response. */
	public static function flush_cache(): void {
		$index = get_transient( self::CACHE_INDEX );

		if ( is_array( $index ) ) {
			foreach ( $index as $key ) {
				delete_transient( (string) $key );
			}
		}

		delete_transient( self::CACHE_INDEX );
	}
}
