<?php
/**
 * Everything the admin screens can *do*: connect, edit, rotate, disconnect.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

/**
 * Form handlers, registered on `admin_post_*`.
 *
 * Plain form posts rather than AJAX: every one of these changes state, and
 * routing them through `admin-post.php` means each gets WordPress's own nonce
 * and capability handling, works without JavaScript, and leaves the browser on
 * a normal redirect-after-post — so a refresh never re-submits a connect.
 */
final class Actions {

	private const NONCE = 'metrixwire_apm_action';

	/** Where a one-shot admin notice waits between the redirect and the next render. */
	private const NOTICE_TRANSIENT = 'metrixwire_apm_notice_';

	private Connection $connection;

	private Client $client;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
		$this->client     = new Client( $connection );
	}

	public function register(): void {
		$handlers = array(
			'metrixwire_apm_connect'      => $this->handle_connect( ... ),
			'metrixwire_apm_disconnect'   => $this->handle_disconnect( ... ),
			'metrixwire_apm_sso'          => $this->handle_sso( ... ),
			'metrixwire_apm_settings'     => $this->handle_settings( ... ),
			'metrixwire_apm_profile'      => $this->handle_profile( ... ),
			'metrixwire_apm_rotate_key'   => $this->handle_rotate_key( ... ),
			'metrixwire_apm_alert_create' => $this->handle_alert_create( ... ),
			'metrixwire_apm_alert_update' => $this->handle_alert_update( ... ),
			'metrixwire_apm_refresh'      => $this->handle_refresh( ... ),
		);

		foreach ( $handlers as $action => $callback ) {
			add_action( 'admin_post_' . $action, $callback );
		}
	}

	/** The nonce field every form on our screens must render. */
	public static function nonce_field(): void {
		wp_nonce_field( self::NONCE );
	}

	/** A nonce-carrying admin-post URL, for links that act (SSO, refresh). */
	public static function action_url( string $action, array $args = array() ): string {
		return wp_nonce_url(
			add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ),
			self::NONCE
		);
	}

	// --- Handlers ----------------------------------------------------------------

	/**
	 * Create a MetrixWire account for this site, or connect an existing one.
	 *
	 * Both live in one handler because they are one decision on one screen. The
	 * create path posts no credentials at all — MetrixWire mints a provisional
	 * account, and the owner fills in an email and password later from Account,
	 * if and when they want to sign in on the web.
	 */
	public function handle_connect(): void {
		$this->authorize();

		$mode = 'signin' === $this->post_string( 'mode' ) ? 'signin' : 'create';

		$body = array(
			'siteUrl'         => home_url(),
			'siteName'        => get_bloginfo( 'name' ),
			'platformVersion' => get_bloginfo( 'version' ),
			'pluginVersion'   => VERSION,
		);

		if ( 'signin' === $mode ) {
			$email = sanitize_email( $this->post_string( 'email' ) );
			// Not sanitized beyond unslashing on purpose: a password is verified
			// remotely, never stored or echoed, and stripping characters from it
			// would silently reject valid credentials.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran in authorize().
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

			// Failures come back to the sign-in panel with the address still in
			// place — retyping it to read an error message is the fastest way to
			// make someone give up on setup.
			$retry = Admin::url( 'dashboard', array( 'mode' => 'signin', 'email' => $email ) );

			if ( ! is_email( $email ) || '' === $password ) {
				$this->fail( __( 'Enter the email and password of your MetrixWire account.', 'metrixwire-apm' ), $retry );
			}

			$body['email']    = $email;
			$body['password'] = $password;
			$result           = $this->client->connect( '/link', $body );
		} else {
			// Posted by the one button on the setup screen; the screen states what
			// is sent and links the terms directly above it.
			if ( '1' !== $this->post_string( 'consent' ) ) {
				$this->fail( __( 'Setup could not be confirmed. Try again from the MetrixWire screen.', 'metrixwire-apm' ) );
			}

			$retry           = Admin::url( 'dashboard' );
			$body['consent'] = true;
			$result          = $this->client->connect( '/provision', $body );
		}

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message(), $retry );
		}

		$this->connection->store( $result );

		$this->notice(
			'success',
			empty( $result['isProvisional'] )
				? sprintf(
					/* translators: %s: the MetrixWire project name this site is now sending to. */
					__( 'Connected. This site now reports to the “%s” project — traces start with the next page view.', 'metrixwire-apm' ),
					(string) ( $result['projectName'] ?? '' )
				)
				: __( 'You are monitored. Traces start with the next page view — add an email and password under Account whenever you want to sign in on the web.', 'metrixwire-apm' )
		);
		$this->back( Admin::url( 'dashboard' ) );
	}

	/**
	 * Set the account's email, password or display name.
	 *
	 * For a provisional account this is the claim step: until it has both an
	 * address and a password, nobody can sign in on the web or recover it.
	 */
	public function handle_profile(): void {
		$this->authorize();

		$email = $this->post_string( 'email' );
		$email = '' !== $email ? sanitize_email( $email ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran in authorize(); a password must not be filtered.
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		$payload = array();
		if ( '' !== $email ) {
			$payload['email'] = $email;
		}
		if ( '' !== $password ) {
			$payload['password'] = $password;
		}

		if ( array() === $payload ) {
			$this->fail( __( 'Enter an email address or a password to change.', 'metrixwire-apm' ), Admin::url( 'account' ) );
		}

		$result = $this->client->write( 'PATCH', '/account', $payload );

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message(), Admin::url( 'account' ) );
		}

		$owner = isset( $result['owner'] ) && is_array( $result['owner'] ) ? $result['owner'] : array();
		$this->connection->store_account_email( (string) ( $owner['email'] ?? '' ) );

		$this->notice(
			'success',
			! empty( $result['verificationSent'] )
				? __( 'Account updated. Check that inbox for a verification link.', 'metrixwire-apm' )
				: __( 'Account updated.', 'metrixwire-apm' )
		);
		$this->back( Admin::url( 'account' ) );
	}

	/** Revoke this site's credential. The project and its history are kept. */
	public function handle_disconnect(): void {
		$this->authorize();

		// Best effort: revoking server-side is the tidy path, but a site that
		// can't reach MetrixWire must still be able to detach locally.
		$this->client->write( 'POST', '/disconnect' );
		$this->connection->forget();

		$this->notice( 'success', __( 'Signed out. Your MetrixWire project and its history are untouched — connecting again picks them back up.', 'metrixwire-apm' ) );
		$this->back( Admin::url( 'dashboard' ) );
	}

	/** Trade the site token for a one-time dashboard sign-in and follow it. */
	public function handle_sso(): void {
		$this->authorize();

		$destination = $this->request_string( 'destination' );
		$result      = $this->client->write( 'POST', '/sso', array( 'destination' => $destination ) );

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message() );
		}

		$url = (string) ( $result['url'] ?? '' );

		// The response is a redirect target, so verify it points at the endpoint
		// this site is actually connected to before sending an administrator there.
		if ( '' === $url || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( $this->connection->endpoint(), PHP_URL_HOST ) ) {
			$this->fail( __( 'MetrixWire returned an unexpected sign-in link.', 'metrixwire-apm' ) );
		}

		// Deliberately wp_redirect(), not wp_safe_redirect(): the destination is
		// the MetrixWire dashboard, an external host by design, and it has just
		// been checked against the configured endpoint above.
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/** Project name, collection toggle, and the local send switch. */
	public function handle_settings(): void {
		$this->authorize();

		$name      = $this->post_string( 'project_name' );
		$is_active = '1' === $this->post_string( 'is_active' );
		$enabled   = '1' === $this->post_string( 'enabled' );

		if ( ! $this->connection->is_overridden( Connection::OPT_ENABLED ) ) {
			update_option( Connection::OPT_ENABLED, $enabled );
		}

		$result = $this->client->write(
			'PATCH',
			'/settings',
			array(
				'name'     => $name,
				'isActive' => $is_active,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message() );
		}

		$this->notice( 'success', __( 'Settings saved.', 'metrixwire-apm' ) );
		$this->back( Admin::url( 'settings' ) );
	}

	/** Roll the ingest key on both sides in one step. */
	public function handle_rotate_key(): void {
		$this->authorize();

		$result = $this->client->write( 'POST', '/rotate-key' );

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message() );
		}

		$this->connection->store_api_key( (string) ( $result['apiKey'] ?? '' ) );

		$this->notice( 'success', __( 'API key rotated. This site is already using the new one.', 'metrixwire-apm' ) );
		$this->back( Admin::url( 'settings' ) );
	}

	/** Add an alert rule covering this site. */
	public function handle_alert_create(): void {
		$this->authorize();

		$type   = 'webhook' === $this->post_string( 'type' ) ? 'webhook' : 'email';
		$target = $this->post_string( 'target' );
		$target = 'webhook' === $type ? esc_url_raw( $target ) : sanitize_email( $target );

		$result = $this->client->write(
			'POST',
			'/alerts',
			array(
				'type'        => $type,
				'target'      => $target,
				'name'        => $this->post_string( 'name' ),
				'minSeverity' => $this->post_string( 'min_severity' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message(), Admin::url( 'alerts' ) );
		}

		$this->notice( 'success', __( 'Alert rule added.', 'metrixwire-apm' ) );
		$this->back( Admin::url( 'alerts' ) );
	}

	/** Pause, resume, test, or delete one alert rule. */
	public function handle_alert_update(): void {
		$this->authorize();

		$rule_id = $this->post_string( 'rule_id' );
		$op      = $this->post_string( 'op' );

		if ( '' === $rule_id ) {
			$this->fail( __( 'No alert rule was selected.', 'metrixwire-apm' ), Admin::url( 'alerts' ) );
		}

		$path = '/alerts/' . rawurlencode( $rule_id );

		switch ( $op ) {
			case 'delete':
				$result  = $this->client->write( 'DELETE', $path );
				$message = __( 'Alert rule deleted.', 'metrixwire-apm' );
				break;
			case 'test':
				$result  = $this->client->write( 'POST', $path . '/test' );
				$message = __( 'Test alert sent.', 'metrixwire-apm' );
				break;
			case 'pause':
			case 'resume':
				$result  = $this->client->write( 'PATCH', $path, array( 'isActive' => 'resume' === $op ) );
				$message = 'resume' === $op
					? __( 'Alert rule resumed.', 'metrixwire-apm' )
					: __( 'Alert rule paused.', 'metrixwire-apm' );
				break;
			default:
				$this->fail( __( 'Unknown action.', 'metrixwire-apm' ), Admin::url( 'alerts' ) );
				return;
		}

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message(), Admin::url( 'alerts' ) );
		}

		$this->notice( 'success', $message );
		$this->back( Admin::url( 'alerts' ) );
	}

	/** Drop the read cache so the next screen fetches live numbers. */
	public function handle_refresh(): void {
		$this->authorize();

		$this->connection->flush_cache();
		$this->notice( 'success', __( 'Refreshed from MetrixWire.', 'metrixwire-apm' ) );
		$this->back( Admin::url( $this->request_string( 'screen' ) ?: 'dashboard' ) );
	}

	// --- Notices -----------------------------------------------------------------

	/** Queue a notice for the screen the browser is about to be redirected to. */
	private function notice( string $type, string $message ): void {
		set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), array( $type, $message ), MINUTE_IN_SECONDS );
	}

	/**
	 * Take the queued notice, if any.
	 *
	 * @return array{0:string,1:string}|null Type and message.
	 */
	public static function take_notice(): ?array {
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || 2 !== count( $notice ) ) {
			return null;
		}

		delete_transient( $key );

		return array( (string) $notice[0], (string) $notice[1] );
	}

	// --- Plumbing ----------------------------------------------------------------

	/** Refuse anyone without the capability or a valid nonce. */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage MetrixWire.', 'metrixwire-apm' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE );
	}

	/** Show an error on the screen the request came from, and stop. */
	private function fail( string $message, string $fallback = '' ): void {
		$this->notice( 'error', $message );
		$this->back( '' !== $fallback ? $fallback : Admin::url( 'dashboard' ) );
	}

	/** Redirect back to an admin screen and end the request. */
	private function back( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}

	/** Read a POSTed string. Nonce is verified by {@see authorize()} beforehand. */
	private function post_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() runs in authorize() before any handler reads input.
		return isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	/** Read a string from GET or POST — for links that act, like SSO. */
	private function request_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer() runs in authorize() before any handler reads input.
		return isset( $_REQUEST[ $key ] ) ? trim( sanitize_key( wp_unslash( $_REQUEST[ $key ] ) ) ) : '';
	}
}
