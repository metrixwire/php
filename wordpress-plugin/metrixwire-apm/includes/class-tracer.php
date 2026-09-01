<?php
/**
 * Request + database query tracing.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

use MetrixWire\MetrixWire;

defined( 'ABSPATH' ) || exit;

/**
 * Opens a trace as early as `plugins_loaded` allows, harvests $wpdb->queries
 * (populated only when SAVEQUERIES is on) and flushes on `shutdown`.
 */
final class Tracer {

	private Connection $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function register(): void {
		// Every value comes from Connection, which already resolves wp-config.php
		// constants over saved options — so a constant-configured site and a
		// plugin-connected one trace identically.
		MetrixWire::init(
			array(
				'apiKey'   => $this->connection->api_key(),
				'endpoint' => $this->connection->endpoint(),
				'enabled'  => $this->connection->is_tracing(),
			)
		);

		if ( ! MetrixWire::isEnabled() ) {
			return;
		}

		$method = $this->request_method();
		MetrixWire::startTrace( $method . ' ' . $this->request_path(), $method );

		add_action( 'shutdown', $this->flush( ... ), PHP_INT_MAX );
	}

	public function flush(): void {
		global $wpdb;

		if ( isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $query ) {
				// $query = [ sql, duration_seconds, caller, ... ].
				$sql = isset( $query[0] ) ? (string) $query[0] : '';

				if ( '' !== $sql ) {
					MetrixWire::addSpan( 'db_query', $sql, isset( $query[1] ) ? (float) $query[1] * 1000 : 0.0 );
				}
			}
		}

		MetrixWire::endTrace( http_response_code() ?: 200 );
		MetrixWire::flush();
	}

	private function request_method(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request metadata, not form input.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'get';

		return '' !== $method ? strtoupper( $method ) : 'GET';
	}

	private function request_path(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request metadata, not form input.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return (string) ( wp_parse_url( $uri, PHP_URL_PATH ) ?: '/' );
	}
}
