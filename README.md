# metrixwire/php

Zero-config APM SDK for **PHP** — native, Laravel, Symfony, Slim/PSR-15 and WordPress. Captures endpoint duration, database queries, transactions and uncaught exceptions, then sends them to MetrixWire at the end of the request — non-blocking, errors are silently swallowed.

## Installation

```bash
composer require metrixwire/php
```

Add to your environment:

```dotenv
METRIXWIRE_KEY=mw_xxx
METRIXWIRE_ENDPOINT=https://metrixwire.com
```

Then pick the integration for your stack — each one is automatic once wired.

## Laravel

Nothing to do. Thanks to **package auto-discovery**, the service provider activates immediately: it installs a `DB::listen` hook, transaction tracking, queue-job tracing, and a global middleware that opens/closes a trace per request.

## Symfony

Register the subscriber as a service (autoconfigure tags it automatically):

```yaml
# config/services.yaml
MetrixWire\Symfony\MetrixWireSubscriber: ~
```

For Doctrine query capture (DBAL 2/3):

```php
$config->setSQLLogger(new \MetrixWire\Symfony\DoctrineSqlLogger());
```

On DBAL 4 (which removed `SQLLogger`), use the drop-in PDO below instead.

## Slim / Mezzio / any PSR-15

Add the middleware as the outermost layer:

```php
$app->add(new \MetrixWire\Psr15\MetrixWireMiddleware());
```

## WordPress

Two options, same underlying SDK:

- **Installable plugin (recommended)** — `wordpress-plugin/metrixwire-apm/` is a standalone plugin with no
  Composer dependency (the SDK core is vendored in). Upload the `metrixwire-apm` folder to
  `wp-content/plugins/`, activate it, then configure the API key and endpoint under
  **Settings → MetrixWire** (includes a "Test Connection" check). See
  `wordpress-plugin/metrixwire-apm/readme.txt`.
- **Must-use plugin** — for the earliest possible trace start (before regular plugins load) or when you
  already manage the SDK via Composer, copy `wordpress/metrixwire.php` into `wp-content/mu-plugins/` and
  set in `wp-config.php`:

  ```php
  define('METRIXWIRE_KEY', 'mw_xxx');
  define('METRIXWIRE_ENDPOINT', 'https://metrixwire.com');
  define('SAVEQUERIES', true); // required for per-query spans
  ```

Both variants read `SAVEQUERIES` the same way — it must be a `wp-config.php` constant, since WordPress
reads it before any plugin (mu or regular) loads.

## Native PHP (no framework) — fully automatic

Point PHP's `auto_prepend_file` at the bundled bootstrap and **every** request is traced with no code change:

```ini
; php.ini
auto_prepend_file = /path/to/vendor/metrixwire/php/src/auto.php
```

It opens a trace, records duration / peak memory / uncaught exceptions, and flushes on shutdown — for native scripts and any framework alike.

### Automatic database queries anywhere

For non-Laravel/Symfony code, swap the PDO class — every direct and prepared query is then captured automatically:

```php
$pdo = new \MetrixWire\PDO($dsn, $user, $pass); // instead of new \PDO(...)
```

## Configuration

| Env | Default | Notes |
|---|---|---|
| `METRIXWIRE_KEY` | *(empty)* | Project API key. Empty = SDK disabled. |
| `METRIXWIRE_ENDPOINT` | `https://metrixwire.com` | API base URL (a full `/ingest` URL also works). |
| `METRIXWIRE_ENABLED` | `true` | Set to `false` to disable entirely. |

## Non-blocking

The transport uses cURL with a short timeout and swallows all errors. Sending happens on shutdown / `terminate()` — after the response has reached the client — so it adds no latency the user can perceive.
