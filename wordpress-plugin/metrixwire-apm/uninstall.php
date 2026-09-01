<?php
/**
 * Removes every option the plugin created, on all sites of a network.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Connection owns the list of options the plugin writes and Client owns its
// transients, so uninstall can't drift from either as new ones are added.
require_once __DIR__ . '/includes/class-connection.php';
require_once __DIR__ . '/includes/class-client.php';

/** Deletes the plugin's options from the current site. */
function metrixwire_apm_delete_options(): void {
	$options = array_merge(
		\MetrixWire\APM\Connection::options(),
		// Pre-1.1 names, in case activation never ran to migrate them.
		array( 'metrixwire_api_key', 'metrixwire_endpoint', 'metrixwire_enabled' )
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Cached API responses live in transients keyed off an index transient.
	\MetrixWire\APM\Client::flush_cache();
}

/** Walks every site of a network, or just the current one on single-site. */
function metrixwire_apm_uninstall(): void {
	if ( ! is_multisite() ) {
		metrixwire_apm_delete_options();

		return;
	}

	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		metrixwire_apm_delete_options();
		restore_current_blog();
	}
}

metrixwire_apm_uninstall();
