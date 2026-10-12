<?php
/**
 * The MetrixWire admin menu and its screens.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level menu and renders its pages.
 *
 * The menu shape follows the connection state rather than being fixed: an
 * unconnected site gets exactly one screen (Setup), because every other screen
 * would need data it can't fetch yet, and a menu full of dead ends is worse
 * than no menu.
 */
final class Admin {

	public const SLUG = 'metrixwire-apm';

	private const PAGES = array(
		'dashboard' => self::SLUG,
		'issues'    => self::SLUG . '-issues',
		'alerts'    => self::SLUG . '-alerts',
		'settings'  => self::SLUG . '-settings',
		'account'   => self::SLUG . '-account',
	);

	private Connection $connection;

	private Client $client;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
		$this->client     = new Client( $connection );
	}

	public function register(): void {
		add_action( 'admin_menu', $this->register_menu( ... ) );
		add_action( 'admin_enqueue_scripts', $this->enqueue_assets( ... ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), $this->action_links( ... ) );
	}

	/** Page slug for a screen key, e.g. `issues`. */
	public static function page( string $key ): string {
		return self::PAGES[ $key ] ?? self::SLUG;
	}

	/** Admin URL for one of our screens, with optional extra query arguments. */
	public static function url( string $key, array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::page( $key ) ), $args ),
			admin_url( 'admin.php' )
		);
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'MetrixWire APM', 'metrixwire-apm' ),
			__( 'MetrixWire', 'metrixwire-apm' ),
			'manage_options',
			self::SLUG,
			$this->render_dashboard( ... ),
			'dashicons-chart-area',
			76
		);

		if ( ! $this->connection->is_connected() ) {
			// One screen only, and labelled for what it does: the site has no
			// credential yet, so there is nothing else to show. No callback: a
			// submenu sharing the parent's slug renames the auto-generated first
			// item, and passing one again would render the screen twice.
			add_submenu_page(
				self::SLUG,
				__( 'Connect MetrixWire', 'metrixwire-apm' ),
				__( 'Connect', 'metrixwire-apm' ),
				'manage_options',
				self::SLUG
			);

			return;
		}

		$screens = array(
			array( self::PAGES['dashboard'], __( 'Dashboard', 'metrixwire-apm' ), __( 'MetrixWire Dashboard', 'metrixwire-apm' ), $this->render_dashboard( ... ) ),
			array( self::PAGES['issues'], __( 'Issues', 'metrixwire-apm' ), __( 'Issues', 'metrixwire-apm' ), $this->render_issues( ... ) ),
			array( self::PAGES['alerts'], __( 'Alerts', 'metrixwire-apm' ), __( 'Alert rules', 'metrixwire-apm' ), $this->render_alerts( ... ) ),
			array( self::PAGES['settings'], __( 'Settings', 'metrixwire-apm' ), __( 'MetrixWire Settings', 'metrixwire-apm' ), $this->render_settings( ... ) ),
			array( self::PAGES['account'], __( 'Account', 'metrixwire-apm' ), __( 'Account & plan', 'metrixwire-apm' ), $this->render_account( ... ) ),
		);

		foreach ( $screens as list( $slug, $label, $title, $callback ) ) {
			// The entry sharing the parent's slug only renames the auto-generated
			// first item — it must not re-register the callback, or WordPress runs
			// it twice and the screen renders twice.
			if ( self::SLUG === $slug ) {
				add_submenu_page( self::SLUG, $title, $label, 'manage_options', $slug );

				continue;
			}

			add_submenu_page( self::SLUG, $title, $label, 'manage_options', $slug, $callback );
		}
	}

	/**
	 * Adds shortcuts to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$own = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::url( 'dashboard' ) ),
				$this->connection->is_connected()
					? esc_html__( 'Dashboard', 'metrixwire-apm' )
					: esc_html__( 'Connect', 'metrixwire-apm' )
			),
		);

		if ( $this->connection->is_connected() ) {
			$own[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::url( 'settings' ) ),
				esc_html__( 'Settings', 'metrixwire-apm' )
			);
		}

		return array_merge( $own, $links );
	}

	/**
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'metrixwire-apm-admin',
			plugins_url( 'assets/admin.css', PLUGIN_FILE ),
			array(),
			VERSION
		);
		wp_enqueue_script(
			'metrixwire-apm-admin',
			plugins_url( 'assets/admin.js', PLUGIN_FILE ),
			array( 'wp-i18n' ),
			VERSION,
			true
		);
		wp_set_script_translations( 'metrixwire-apm-admin', 'metrixwire-apm', PLUGIN_DIR . '/languages' );
		wp_localize_script(
			'metrixwire-apm-admin',
			'metrixwireApmAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => Ajax::AJAX_ACTION,
				'nonce'   => wp_create_nonce( Ajax::NONCE_ACTION ),
			)
		);
	}

	// --- Screens ---------------------------------------------------------------

	public function render_dashboard(): void {
		if ( ! $this->may_render() ) {
			return;
		}

		if ( ! $this->connection->is_connected() ) {
			$this->view( 'setup' );

			return;
		}

		$status = $this->client->get( '/status' );

		if ( $this->handle_unlinked( $status ) ) {
			return;
		}

		$this->view(
			'dashboard',
			array(
				'status'   => $status,
				'overview' => $this->client->get( '/overview' ),
			)
		);
	}

	public function render_issues(): void {
		if ( ! $this->may_render() || ! $this->require_connection() ) {
			return;
		}

		// A single issue id in the query string turns this screen into the detail
		// view, so the list and the thing it links to stay one menu entry.
		$issue_id = isset( $_GET['issue'] ) ? sanitize_text_field( wp_unslash( $_GET['issue'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.

		if ( '' !== $issue_id ) {
			$issue = $this->client->get( '/issues/' . rawurlencode( $issue_id ) );

			if ( $this->handle_unlinked( $issue ) ) {
				return;
			}

			$this->view( 'issue-detail', array( 'issue' => $issue ) );

			return;
		}

		$query  = $this->issue_query_from_request();
		$result = $this->client->get( '/issues', $query );

		if ( $this->handle_unlinked( $result ) ) {
			return;
		}

		$this->view(
			'issues',
			array(
				'result' => $result,
				'query'  => $query,
			)
		);
	}

	public function render_alerts(): void {
		if ( ! $this->may_render() || ! $this->require_connection() ) {
			return;
		}

		$rules = $this->client->get( '/alerts' );

		if ( $this->handle_unlinked( $rules ) ) {
			return;
		}

		$this->view( 'alerts', array( 'rules' => $rules ) );
	}

	public function render_settings(): void {
		if ( ! $this->may_render() || ! $this->require_connection() ) {
			return;
		}

		$status = $this->client->get( '/status' );

		if ( $this->handle_unlinked( $status ) ) {
			return;
		}

		$this->view( 'settings', array( 'status' => $status ) );
	}

	public function render_account(): void {
		if ( ! $this->may_render() || ! $this->require_connection() ) {
			return;
		}

		$status = $this->client->get( '/status' );

		if ( $this->handle_unlinked( $status ) ) {
			return;
		}

		$this->view( 'account', array( 'status' => $status ) );
	}

	// --- Plumbing ---------------------------------------------------------------

	private function may_render(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Guard for the screens that need a site token. Renders the setup screen in
	 * place of the requested one — reachable when a session had a submenu open
	 * as the site was disconnected in another tab.
	 */
	private function require_connection(): bool {
		if ( $this->connection->is_connected() ) {
			return true;
		}

		$this->view( 'setup' );

		return false;
	}

	/**
	 * Recover from a credential MetrixWire no longer recognises.
	 *
	 * The site believes it is connected, so without this it would render a
	 * screen of buttons that all fail the same way. Dropping the dead token
	 * puts the administrator back on the setup screen, which explains what
	 * happened and offers to sign in again.
	 *
	 * @param mixed $result Whatever a Client call returned.
	 * @return bool True when the screen has been handled and the caller must stop.
	 */
	private function handle_unlinked( $result ): bool {
		if ( ! is_wp_error( $result ) || 'metrixwire_site_unlinked' !== $result->get_error_code() ) {
			return false;
		}

		$this->connection->forget_pairing();
		$this->view( 'setup', array( 'unlinked' => true ) );

		return true;
	}

	/**
	 * Read the issue list's filters off the query string.
	 *
	 * @return array<string, mixed>
	 */
	private function issue_query_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters on a GET screen.
		$severity = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '';
		$since    = isset( $_GET['since'] ) ? sanitize_key( wp_unslash( $_GET['since'] ) ) : '7d';
		$sort     = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'recent';
		$route    = isset( $_GET['route'] ) ? sanitize_text_field( wp_unslash( $_GET['route'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$limit = 20;
		$query = array(
			'limit'  => $limit,
			'offset' => ( $paged - 1 ) * $limit,
			'sort'   => in_array( $sort, array( 'recent', 'occurrences', 'severity' ), true ) ? $sort : 'recent',
		);

		if ( in_array( $severity, array( 'critical', 'high', 'medium', 'low' ), true ) ) {
			$query['severity'] = $severity;
		}
		if ( in_array( $since, array( '1h', '24h', '7d', '30d', 'all' ), true ) && 'all' !== $since ) {
			$query['since'] = $since;
		}
		if ( '' !== $route ) {
			$query['route'] = $route;
		}

		return $query;
	}

	/**
	 * Render a view file with `$data` in scope.
	 *
	 * @param array<string, mixed> $data Values the view expects.
	 */
	private function view( string $name, array $data = array() ): void {
		$data['connection'] = $this->connection;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- fixed, developer-supplied keys; this is the view's parameter list.
		extract( $data, EXTR_SKIP );

		require PLUGIN_DIR . '/includes/views/' . $name . '.php';
	}
}
