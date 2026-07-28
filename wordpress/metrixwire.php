<?php
/**
 * Plugin Name: MetrixWire
 * Description: Zero-config APM for WordPress — traces each request and its database queries.
 * Version: 0.1.0
 * License: MIT
 *
 * Install: drop this file into wp-content/mu-plugins/ (must-use, always active).
 * Configure with constants in wp-config.php:
 *
 *   define('METRIXWIRE_KEY', 'mw_xxx');
 *   define('METRIXWIRE_ENDPOINT', 'http://localhost:3000');
 *   define('SAVEQUERIES', true); // required for per-query spans
 *
 * SDK autoloading: set METRIXWIRE_AUTOLOAD to your composer autoload.php, or
 * install the SDK so vendor/autoload.php is discoverable below.
 */

declare(strict_types=1);

use MetrixWire\MetrixWire;

if (! class_exists(MetrixWire::class, false)) {
    foreach ([
        defined('METRIXWIRE_AUTOLOAD') ? METRIXWIRE_AUTOLOAD : '',
        defined('ABSPATH') ? ABSPATH.'vendor/autoload.php' : '',
        __DIR__.'/../../../autoload.php',
    ] as $metrixwireAutoload) {
        if ($metrixwireAutoload !== '' && is_file($metrixwireAutoload)) {
            require_once $metrixwireAutoload;
            break;
        }
    }
}

if (! class_exists(MetrixWire::class)) {
    return; // SDK not available — do nothing.
}

MetrixWire::init([
    'apiKey' => defined('METRIXWIRE_KEY') ? (string) METRIXWIRE_KEY : (string) (getenv('METRIXWIRE_KEY') ?: ''),
    'endpoint' => defined('METRIXWIRE_ENDPOINT') ? (string) METRIXWIRE_ENDPOINT : (string) (getenv('METRIXWIRE_ENDPOINT') ?: 'http://localhost:3000'),
    'enabled' => defined('METRIXWIRE_ENABLED') ? (bool) METRIXWIRE_ENABLED : true,
]);

if (! MetrixWire::isEnabled()) {
    return;
}

$metrixwireMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$metrixwirePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
MetrixWire::startTrace($metrixwireMethod.' '.$metrixwirePath, $metrixwireMethod);

// WordPress fires `shutdown` after the response is sent. Harvest $wpdb->queries
// (populated only when SAVEQUERIES is on) and close the trace.
add_action('shutdown', static function (): void {
    global $wpdb;
    if (isset($wpdb->queries) && is_array($wpdb->queries)) {
        foreach ($wpdb->queries as $q) {
            // $q = [ sql, duration_seconds, caller, ... ]
            $sql = isset($q[0]) ? (string) $q[0] : '';
            $durationMs = isset($q[1]) ? (float) $q[1] * 1000 : 0.0;
            if ($sql !== '') {
                MetrixWire::addSpan('db_query', $sql, $durationMs);
            }
        }
    }
    MetrixWire::endTrace(function_exists('http_response_code') ? (http_response_code() ?: 200) : 200);
    MetrixWire::flush();
}, PHP_INT_MAX);
