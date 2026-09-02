<?php
/**
 * Plugin Name:       MetrixWire APM
 * Plugin URI:        https://metrixwire.com
 * Description:       Zero-config APM for WordPress — create your account, trace every request and its database queries, and read the results without leaving wp-admin.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            PlatkaDigital
 * Author URI:        https://platkadigital.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       metrixwire-apm
 * Domain Path:       /languages
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.2.1';

define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );
define( __NAMESPACE__ . '\PLUGIN_DIR', __DIR__ );

// No load_plugin_textdomain() call: WordPress loads translations just in time, and
// Plugin Check flags the call as discouraged. Note that just-in-time loading only
// scans WP_LANG_DIR/plugins/ — .mo files shipped inside languages/ would not be
// picked up there; that folder holds the .pot template for translators.
require_once __DIR__ . '/includes/sdk-loader.php';
require_once __DIR__ . '/includes/class-connection.php';
require_once __DIR__ . '/includes/class-client.php';
require_once __DIR__ . '/includes/class-view.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-actions.php';
require_once __DIR__ . '/includes/class-ajax.php';
require_once __DIR__ . '/includes/class-tracer.php';

/**
 * Boots the plugin.
 *
 * Priority 1: opens the trace as early in the request lifecycle as a regular
 * (non-mu) plugin can — before other plugins' `plugins_loaded` handlers run.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		$connection = new Connection();

		( new Admin( $connection ) )->register();
		( new Actions( $connection ) )->register();
		( new Ajax( $connection ) )->register();
		( new Tracer( $connection ) )->register();
	},
	1
);

register_activation_hook( __FILE__, array( Connection::class, 'install' ) );
