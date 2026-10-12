=== MetrixWire APM ===
Contributors: platkadigital
Tags: apm, performance, monitoring, observability, database
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Zero-config APM for WordPress — one click to start, then trace every request and read the results without leaving wp-admin.

== Description ==

MetrixWire APM traces every WordPress request end-to-end: response time, status, and (when `SAVEQUERIES`
is enabled) each `$wpdb` query as its own span. Detectors flag N+1 queries, slow endpoints, slow queries,
`SELECT *`, query floods and unhandled exceptions in the traces this plugin sends.

Setup is one button. Open **MetrixWire → Connect** and click **Start monitoring**: the plugin creates your
MetrixWire workspace, its project and its API key for you. No email address, no password, no URL to enter —
add an email and password later, under **Account**, whenever you want to sign in on the web too.

From then on everything lives in wp-admin:

* **Dashboard** — issue counts and their 7-day trend, a per-day chart, top issues, worst-affected routes, plan usage.
* **Issues** — every detection, filterable by severity, time range and route, with a detail view showing the sample request and each database query as a waterfall.
* **Alerts** — email or webhook rules for this site, with a test send.
* **Settings** — project name, collection toggles, API key rotation, `SAVEQUERIES` status.
* **Account** — set the account's email and password, see its plan and this month's trace usage, sign the site out.

A single **Open MetrixWire** button signs you into the full dashboard in a browser tab when you want it —
no password prompt, no second login.

Already have a MetrixWire account? Choose **Sign in instead** on the connect screen — the site attaches to
your existing account, and reuses its project if that site was connected before.

= External services =

This plugin is a client for MetrixWire, a hosted application performance monitoring service at
https://metrixwire.com (or your own instance, if you self-host it).

**When you connect**, the plugin sends this site's URL and title and its WordPress and plugin versions, so
MetrixWire can create your workspace and its project. No personal data is sent at this point: the account
starts without an email address. If you later set one from the Account screen, or sign in to an account you
already have, that address and password are sent too — the password is verified remotely and is never
stored on this site.

**While connected**, each request sends the HTTP method, path, response status and duration, and — when
`SAVEQUERIES` is enabled — the SQL text and duration of each database query. The admin screens read this
site's own monitoring data back from the same service. Nothing is sent before you connect, and nothing is
sent while the "Send traces from this site" toggle is off. Traces leave the site on the `shutdown` hook,
after the response has already reached the visitor.

Service terms: https://metrixwire.com/terms — Privacy policy: https://metrixwire.com/privacy

== Installation ==

1. Install through **Plugins → Add New**, or upload the `metrixwire-apm` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Go to **MetrixWire → Connect** and click **Start monitoring**. That's the whole setup.
4. Optionally add `define( 'SAVEQUERIES', true );` to `wp-config.php` to capture per-query spans.

== Frequently Asked Questions ==

= Do I need a MetrixWire account before installing? =

No — and you don't need to create one by hand either. One click on the connect screen sets everything up.
The account has no email or password until you add them under **Account**, which you only need if you want
to sign in on metrixwire.com directly or recover the account later. If you already have an account, use
**Sign in instead** on the connect screen.

= Where do I enter my MetrixWire URL? =

Nowhere. The plugin uses the hosted service at metrixwire.com. If you run MetrixWire yourself, point the
plugin at it with `define( 'METRIXWIRE_ENDPOINT', 'https://your-instance.example.com' );` in
`wp-config.php`.

= Do I have to open the MetrixWire website at all? =

No. Connecting, reading issues, changing settings, managing alert rules and checking plan usage all happen
in wp-admin. The **Open MetrixWire** button is there when you want the full dashboard, and signs you in
automatically. Changing your paid plan is the one thing that leaves WordPress, because checkout is handled
by the payment provider.

= Can I configure this via wp-config.php instead? =

Yes. Define `METRIXWIRE_KEY`, `METRIXWIRE_ENDPOINT`, and optionally `METRIXWIRE_ENABLED` as constants in
`wp-config.php` — they take precedence over the saved settings, and the fields they cover are shown
read-only. A site configured this way traces without connecting, though the in-dashboard screens still
need a connection, since reading data back takes its own credential.

= Why aren't database query spans showing up? =

`SAVEQUERIES` must be defined as a constant in `wp-config.php`; WordPress reads it before any plugin loads,
so it can't be turned on from a settings screen at runtime.

= Does this slow down my site? =

Traces are sent on the `shutdown` hook, after the response has already been sent to the visitor, over a
short-timeout non-blocking request. Failures to reach MetrixWire are swallowed silently. The admin screens
cache what they read for a minute, so browsing them doesn't hammer the service either.

= Who can see the data? =

Anyone who can administer this WordPress site — the screens require the `manage_options` capability, and
the **Open MetrixWire** button signs that administrator into the connected account.

= Does it work on multisite? =

Yes. Each site connects separately, keeping its own project and credentials, and uninstalling removes
those from every site in the network.

= What happens if I sign the site out? =

The site stops tracing and its credentials are removed. Your MetrixWire project and its history are kept,
so signing back in later picks that project up instead of starting a new one.

== Screenshots ==

1. Connect: one button creates the account, its project and its API key — no email, no password, no URL to enter.
2. Dashboard: issue counts and their 7-day trend, a per-day chart, top issues, worst-affected routes and plan usage.
3. Issues: every detection on this site, filterable by severity, time range and route.
4. Issue detail: what was detected, a suggested fix, and the sample request with each database query as a waterfall.
5. Alerts: email or webhook rules for this site, with a test send.
6. Settings: project name, collection toggles, the endpoint in force, API key rotation and SAVEQUERIES status.

== Changelog ==

= 1.3.0 =
* New "Critical" severity for requests that are broken — exceptions, 5xx responses and failing
  external APIs — shown above High on the dashboard, in the issue list filter and the daily chart.
* Alert rules can be limited to critical issues only; "High only" is now "High and up".

= 1.2.1 =
* The bundled SDK now defaults to https://metrixwire.com instead of a local address, so a site
  configured through `wp-config.php` constants alone no longer posts its traces where nothing is
  listening. Sites that set `METRIXWIRE_ENDPOINT` themselves are unaffected.

= 1.2.0 =
* Setup is now a single button: the account, project and API key are created with nothing to type — no
  email, no password, no URL — and no visit to metrixwire.com.
* Added an Account screen for setting the account's email and password afterwards, and for signing the
  site out.
* New top-level MetrixWire menu with Dashboard, Issues, Alerts, Settings and Account screens — issue
  lists, detail views with a query waterfall, alert rules and plan usage, all inside WordPress.
* Added an "Open MetrixWire" button that signs the administrator into the dashboard with a one-time,
  60-second ticket.
* The service URL field is gone; the hosted service is assumed, and self-hosted installs set
  `METRIXWIRE_ENDPOINT` in `wp-config.php`.
* The API key is issued automatically, shown masked, and can be rotated from Settings.
* Added Sign out, which revokes this site's credentials while keeping the project and its history.

= 1.1.0 =
* Renamed the plugin slug and text domain to `metrixwire-apm`, with translation-ready strings and a
  bundled `.pot` template.
* Settings screen rebuilt on the WordPress Settings API, with a "Settings" shortcut on the Plugins row.
* wp-config.php constants now render as read-only values per field instead of disabling the form, so
  saving no longer clears the options they cover.
* Options are prefixed `metrixwire_apm_*`; activation migrates values saved by the 1.0 plugin.
* Uninstall now clears options on every site of a multisite network.
* Traces are posted with `wp_remote_post()` instead of cURL, so the site's HTTP transport, proxy
  settings and filters apply.

= 1.0.0 =
* Initial release: settings screen, wp-config.php constant override, test-connection check, automatic
  request + database query tracing.

== Upgrade Notice ==

= 1.2.0 =
Settings moved to a new top-level MetrixWire menu, which now shows your monitoring data directly in
wp-admin. Existing API keys keep working; one click on MetrixWire → Connect unlocks the new screens.
