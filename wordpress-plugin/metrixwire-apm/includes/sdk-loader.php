<?php
/**
 * Loads the vendored MetrixWire PHP SDK.
 *
 * Vendored copy of metrixwire/php (sdks/php/src) — zero external dependencies, so
 * the plugin works standalone without requiring Composer on the host. Skipped when
 * the site already autoloads the Composer package. Keep in sync with the package,
 * except Transport.php, which is a WordPress-specific wp_remote_post() rewrite.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( \MetrixWire\MetrixWire::class, false ) ) {
	require_once __DIR__ . '/sdk/Span.php';
	require_once __DIR__ . '/sdk/Trace.php';
	require_once __DIR__ . '/sdk/Transport.php';
	require_once __DIR__ . '/sdk/MetrixWire.php';
}
