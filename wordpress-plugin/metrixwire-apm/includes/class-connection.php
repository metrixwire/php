<?php
/**
 * The site's pairing with a MetrixWire account: what is stored, and how it is read.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

/**
 * Owns every option this plugin persists, and is the only place that decides
 * what the plugin is actually using at runtime.
 *
 * Two credentials are kept, because MetrixWire issues two:
 *  - the API key writes traces, and is the one a wp-config.php constant can
 *    override for config-as-code setups;
 *  - the site token reads this site's data back and opens the dashboard, and is
 *    only ever obtained by connecting from the setup screen.
 */
final class Connection {

	public const OPT_API_KEY    = 'metrixwire_apm_api_key';
	public const OPT_ENDPOINT   = 'metrixwire_apm_endpoint';
	public const OPT_ENABLED    = 'metrixwire_apm_enabled';
	public const OPT_SITE_TOKEN = 'metrixwire_apm_site_token';
	public const OPT_PROJECT_ID = 'metrixwire_apm_project_id';
	public const OPT_EMAIL      = 'metrixwire_apm_account_email';

	/**
	 * The hosted service. Sites connect here unless they point the plugin at
	 * their own instance, so nobody has to know a URL to get started.
	 */
	public const DEFAULT_ENDPOINT = 'https://metrixwire.com';

	/**
	 * Option name => wp-config.php constant that overrides it.
	 *
	 * @var array<string, string>
	 */
	private const OVERRIDES = array(
		self::OPT_ENABLED  => 'METRIXWIRE_ENABLED',
		self::OPT_API_KEY  => 'METRIXWIRE_KEY',
		self::OPT_ENDPOINT => 'METRIXWIRE_ENDPOINT',
	);

	/**
	 * Legacy option name => current option name.
	 *
	 * Settings saved by the pre-1.1 `metrixwire` plugin, whose folder and option
	 * prefix both lacked the `-apm` slug suffix.
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_OPTIONS = array(
		'metrixwire_api_key'  => self::OPT_API_KEY,
		'metrixwire_endpoint' => self::OPT_ENDPOINT,
		'metrixwire_enabled'  => self::OPT_ENABLED,
	);

	/**
	 * Every option the plugin creates — the single list uninstall walks.
	 *
	 * @return string[]
	 */
	public static function options(): array {
		return array(
			self::OPT_API_KEY,
			self::OPT_ENDPOINT,
			self::OPT_ENABLED,
			self::OPT_SITE_TOKEN,
			self::OPT_PROJECT_ID,
			self::OPT_EMAIL,
		);
	}

	/**
	 * Activation: seeds defaults, adopting any settings left by the pre-1.1 plugin.
	 *
	 * The legacy options are removed once copied, so deleting the old plugin (which
	 * drops them on uninstall) can't take the migrated values with it.
	 */
	public static function install(): void {
		foreach ( self::LEGACY_OPTIONS as $legacy => $current ) {
			$value = get_option( $legacy, null );

			if ( null !== $value ) {
				add_option( $current, $value );
				delete_option( $legacy );
			}
		}

		add_option( self::OPT_API_KEY, '' );
		add_option( self::OPT_ENDPOINT, '' );
		add_option( self::OPT_ENABLED, true );
		add_option( self::OPT_SITE_TOKEN, '' );
		add_option( self::OPT_PROJECT_ID, '' );
		add_option( self::OPT_EMAIL, '' );
	}

	/**
	 * The endpoint in force: the wp-config.php constant, else the saved value,
	 * else the hosted service.
	 */
	public function endpoint(): string {
		if ( defined( self::OVERRIDES[ self::OPT_ENDPOINT ] ) ) {
			$constant = untrailingslashit( (string) constant( self::OVERRIDES[ self::OPT_ENDPOINT ] ) );

			if ( '' !== $constant ) {
				return $constant;
			}
		}

		$saved = untrailingslashit( (string) get_option( self::OPT_ENDPOINT, '' ) );

		return '' !== $saved ? $saved : self::DEFAULT_ENDPOINT;
	}

	/** The ingest API key in force. */
	public function api_key(): string {
		if ( defined( self::OVERRIDES[ self::OPT_API_KEY ] ) ) {
			return (string) constant( self::OVERRIDES[ self::OPT_API_KEY ] );
		}

		return (string) get_option( self::OPT_API_KEY, '' );
	}

	/** Whether traces are being sent at all. */
	public function is_enabled(): bool {
		if ( defined( self::OVERRIDES[ self::OPT_ENABLED ] ) ) {
			return (bool) constant( self::OVERRIDES[ self::OPT_ENABLED ] );
		}

		return (bool) get_option( self::OPT_ENABLED, true );
	}

	/** The read credential for this site's own data. Never settable by constant. */
	public function site_token(): string {
		return (string) get_option( self::OPT_SITE_TOKEN, '' );
	}

	public function project_id(): string {
		return (string) get_option( self::OPT_PROJECT_ID, '' );
	}

	/** The MetrixWire account this site is paired with, for display only. */
	public function account_email(): string {
		return (string) get_option( self::OPT_EMAIL, '' );
	}

	/**
	 * True once the site holds a site token — the condition that unlocks the
	 * dashboard screens, as opposed to just being able to send traces.
	 */
	public function is_connected(): bool {
		return '' !== $this->site_token();
	}

	/**
	 * True when traces can flow: a key, an endpoint, and the toggle on.
	 *
	 * Deliberately separate from {@see is_connected()} — a site configured by
	 * wp-config.php constants alone traces perfectly well without ever pairing.
	 */
	public function is_tracing(): bool {
		return $this->is_enabled() && '' !== $this->api_key() && '' !== $this->endpoint();
	}

	/**
	 * Persist the payload returned by a successful connect.
	 *
	 * The endpoint is never written here. Asking a site owner for a service URL
	 * during setup only invites the question "which URL?", so the hosted service
	 * is assumed and self-hosting is configured with a wp-config.php constant.
	 *
	 * @param array<string, mixed> $payload Decoded provision/link response.
	 */
	public function store( array $payload ): void {
		update_option( self::OPT_API_KEY, (string) ( $payload['apiKey'] ?? '' ) );
		update_option( self::OPT_SITE_TOKEN, (string) ( $payload['siteToken'] ?? '' ) );
		update_option( self::OPT_PROJECT_ID, (string) ( $payload['projectId'] ?? '' ) );
		update_option( self::OPT_EMAIL, (string) ( $payload['email'] ?? '' ) );
		update_option( self::OPT_ENABLED, true );

		$this->flush_cache();
	}

	/** Update the remembered account address after a profile edit. */
	public function store_account_email( string $email ): void {
		update_option( self::OPT_EMAIL, $email );
	}

	/**
	 * Drop only the read credential, keeping the ingest key.
	 *
	 * Used when MetrixWire reports the site token as unknown — which happens
	 * when the same site is connected again from somewhere else, rotating the
	 * token. The project and its key are unchanged in that case, so traces must
	 * keep flowing; it is only these screens that need signing in again.
	 */
	public function forget_pairing(): void {
		update_option( self::OPT_SITE_TOKEN, '' );
		update_option( self::OPT_PROJECT_ID, '' );

		$this->flush_cache();
	}

	/** Forget the pairing. Traces stop; nothing on the MetrixWire side is deleted. */
	public function forget(): void {
		update_option( self::OPT_SITE_TOKEN, '' );
		update_option( self::OPT_PROJECT_ID, '' );
		update_option( self::OPT_EMAIL, '' );
		update_option( self::OPT_API_KEY, '' );

		$this->flush_cache();
	}

	/** Replace the stored ingest key after a rotation. */
	public function store_api_key( string $api_key ): void {
		update_option( self::OPT_API_KEY, $api_key );
	}

	/** True when a wp-config.php constant covers this option. */
	public function is_overridden( string $option ): bool {
		return isset( self::OVERRIDES[ $option ] ) && defined( self::OVERRIDES[ $option ] );
	}

	/** The constant name backing an option, for "set by X" hints in the UI. */
	public function override_constant( string $option ): string {
		return self::OVERRIDES[ $option ] ?? '';
	}

	/** True when any wp-config.php constant is in play. */
	public function has_overrides(): bool {
		foreach ( array_keys( self::OVERRIDES ) as $option ) {
			if ( $this->is_overridden( $option ) ) {
				return true;
			}
		}

		return false;
	}

	/** Drop every cached API response for this site. */
	public function flush_cache(): void {
		Client::flush_cache();
	}

	/** Shows only the key's prefix and last four characters. */
	public static function mask( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( strlen( $value ) <= 8 ) {
			return str_repeat( '•', strlen( $value ) );
		}

		return substr( $value, 0, 6 ) . str_repeat( '•', 6 ) . substr( $value, -4 );
	}
}
