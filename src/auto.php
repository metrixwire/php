<?php

declare(strict_types=1);

/**
 * Zero-code bootstrap. Point PHP's `auto_prepend_file` at this file (php.ini,
 * .htaccess, or `-d auto_prepend_file=`) and every request — native PHP or any
 * framework — opens a trace, records duration/memory/uncaught exceptions, and
 * flushes on shutdown, with no changes to the application.
 *
 *   php.ini:  auto_prepend_file = /path/to/vendor/metrixwire/php/src/auto.php
 *
 * Database queries still need either the drop-in {@see \MetrixWire\PDO} or a
 * framework adapter (Laravel/Symfony) — those carry per-query timing this
 * request-level hook can't see on its own.
 */

use MetrixWire\MetrixWire;

// Run at most once per request.
if (defined('METRIXWIRE_AUTO_BOOTSTRAPPED')) {
    return;
}

// auto_prepend_file runs before the app boots, so the Composer autoloader may
// not be loaded yet. Find and require it best-effort.
if (! class_exists(MetrixWire::class, false)) {
    $candidates = [
        getenv('METRIXWIRE_AUTOLOAD') ?: '',
        __DIR__.'/../../../autoload.php',   // vendor/metrixwire/php/src/auto.php → vendor/autoload.php
        __DIR__.'/../vendor/autoload.php',
        (getcwd() ?: '.').'/vendor/autoload.php',
    ];
    foreach ($candidates as $autoload) {
        if ($autoload !== '' && is_file($autoload)) {
            require_once $autoload;
            break;
        }
    }
}

// SDK not installed / not autoloadable — do nothing, silently.
if (! class_exists(MetrixWire::class)) {
    return;
}

define('METRIXWIRE_AUTO_BOOTSTRAPPED', true);

$metrixwireEnv = static function (string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    return ($v === null || $v === '') ? $default : (string) $v;
};

MetrixWire::init([
    'apiKey' => $metrixwireEnv('METRIXWIRE_KEY', ''),
    'endpoint' => $metrixwireEnv('METRIXWIRE_ENDPOINT', 'http://localhost:3000'),
    'enabled' => filter_var($metrixwireEnv('METRIXWIRE_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
]);

if (! MetrixWire::isEnabled()) {
    return;
}

// Build the route label. HTTP: "GET /path" (query stripped); CLI: "CLI script".
if (PHP_SAPI === 'cli') {
    $metrixwireMethod = 'CLI';
    $metrixwireRoute = 'CLI '.basename($_SERVER['argv'][0] ?? $_SERVER['SCRIPT_NAME'] ?? 'script');
} else {
    $metrixwireMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $metrixwirePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $metrixwireRoute = $metrixwireMethod.' '.$metrixwirePath;
}

MetrixWire::startTrace($metrixwireRoute, $metrixwireMethod);

// Buffer output on web requests so the shutdown handler can measure the response
// size for the large_response_payload detector. Harmless for native apps; modern
// frameworks manage their own buffers and are traced via their adapters instead.
if (PHP_SAPI !== 'cli') {
    ob_start();
}

// Uncaught exceptions from native code / thin frameworks. Modern frameworks
// (Laravel/Symfony) catch their own before this fires — their adapters cover
// that case via framework exception events.
set_exception_handler(static function (\Throwable $e): void {
    MetrixWire::captureException($e);
    MetrixWire::endTrace(500);
    MetrixWire::flush();
});

// Final flush — runs after the response has been sent to the client.
register_shutdown_function(static function (): void {
    // Measure the buffered response body (if buffering is still active).
    if (PHP_SAPI !== 'cli') {
        $bytes = ob_get_length();
        if (is_int($bytes) && $bytes > 0) {
            MetrixWire::addTraceMeta('responseBytes', $bytes);
        }
    }

    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($err !== null && in_array($err['type'], $fatal, true)) {
        MetrixWire::captureException(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
        MetrixWire::endTrace(500);
    } else {
        MetrixWire::endTrace(function_exists('http_response_code') ? (http_response_code() ?: 200) : 200);
    }
    MetrixWire::flush();
});
