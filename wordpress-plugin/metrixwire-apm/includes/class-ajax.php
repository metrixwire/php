<?php
/**
 * Admin-ajax endpoint backing the "Test Connection" button.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the credentials this site would actually use are accepted,
 * without sending a real trace.
 *
 * Kept as AJAX rather than a form post because it changes nothing: the point is
 * a yes/no answer next to the button, not a new page.
 */
final class Ajax {

	public const NONCE_ACTION = 'metrixwire_apm_test_connection';

	public const AJAX_ACTION = 'metrixwire_apm_test_connection';

	private const TIMEOUT_SECONDS = 8;

	private Connection $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, $this->test_connection( ... ) );
	}

	public function test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'metrixwire-apm' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$api_key  = $this->connection->api_key();
		$endpoint = $this->connection->endpoint();

		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'This site has no API key yet — connect it first.', 'metrixwire-apm' ) ) );
		}

		// An empty traces array is a valid, side-effect-free request — it exercises
		// the same auth path (x-api-key → project lookup) as a real trace would.
		$response = wp_remote_post(
			untrailingslashit( $endpoint ) . '/ingest',
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $api_key,
				),
				'body'    => wp_json_encode( array( 'traces' => array() ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $status ) {
			wp_send_json_success( array( 'message' => __( 'Connected — MetrixWire accepted the request.', 'metrixwire-apm' ) ) );
		}

		wp_send_json_error( array( 'message' => $this->error_message( $status, $response ) ) );
	}

	/**
	 * Prefers the server's own error text, falling back to a status-specific hint.
	 *
	 * @param int             $status   HTTP status code.
	 * @param array|\WP_Error $response Raw wp_remote_post() response.
	 */
	private function error_message( int $status, $response ): string {
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
			return (string) $decoded['error'];
		}

		return match ( $status ) {
			401     => __( 'Invalid API key.', 'metrixwire-apm' ),
			402     => __( 'Trace quota exceeded for this project.', 'metrixwire-apm' ),
			403     => __( 'This project is inactive.', 'metrixwire-apm' ),
			default => sprintf(
				/* translators: %d: HTTP status code returned by the MetrixWire endpoint. */
				__( 'Unexpected response (HTTP %d).', 'metrixwire-apm' ),
				$status
			),
		};
	}
}
