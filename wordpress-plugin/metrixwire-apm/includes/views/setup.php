<?php
/**
 * Connect screen — the only thing an unconnected site can do.
 *
 * There is nothing to fill in. Someone who found this plugin in the WordPress
 * directory should not have to invent a password, or know what a MetrixWire is,
 * before their site is monitored: one button creates the account, the project
 * and the key. The email and password can come later, from the Account screen,
 * once they have seen whether the thing is any good.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection $connection Current pairing.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

// Set by Admin when MetrixWire rejected this site's credential — see below.
$metrixwire_apm_unlinked = ! empty( $unlinked );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state: which panel to open.
$metrixwire_apm_chosen = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( 'signin' === $metrixwire_apm_chosen || 'create' === $metrixwire_apm_chosen ) {
	$metrixwire_apm_mode = $metrixwire_apm_chosen;
} else {
	// Still remembering an account address means this site has been connected
	// before and only lost its credential — signing back in returns it to the
	// same project, where creating a second account would strand its history.
	$metrixwire_apm_mode = '' !== $connection->account_email() ? 'signin' : 'create';
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: prefills the address a failed sign-in used.
$metrixwire_apm_email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';

if ( '' === $metrixwire_apm_email ) {
	// The address this site last connected with, so signing back in is one field.
	$metrixwire_apm_email = $connection->account_email();
}

$metrixwire_apm_endpoint = $connection->endpoint();
$metrixwire_apm_title    = __( 'Connect MetrixWire', 'metrixwire-apm' );
$metrixwire_apm_screen   = 'dashboard';
$metrixwire_apm_intro    = __( 'One click and this site is monitored. Nothing to fill in, nothing to copy.', 'metrixwire-apm' );

require __DIR__ . '/partials/header.php';
?>

	<?php if ( $metrixwire_apm_unlinked ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'This site was signed out of MetrixWire.', 'metrixwire-apm' ); ?></strong>
				<?php esc_html_e( 'Its credential is no longer valid — usually because the site was connected again from somewhere else. Sign in to pick the same project back up. Tracing is unaffected.', 'metrixwire-apm' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="mw-setup">
		<div class="mw-setup__main">
			<?php if ( 'signin' !== $metrixwire_apm_mode ) : ?>
				<div class="mw-card mw-card--hero">
					<h2><?php esc_html_e( 'Start monitoring this site', 'metrixwire-apm' ); ?></h2>
					<p>
						<?php esc_html_e( 'A MetrixWire workspace is created for this site automatically — no email address or password needed to get going. You can add those later from the Account screen, whenever you want to sign in on the web.', 'metrixwire-apm' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php Actions::nonce_field(); ?>
						<input type="hidden" name="action" value="metrixwire_apm_connect">
						<input type="hidden" name="mode" value="create">
						<input type="hidden" name="consent" value="1">

						<p class="mw-actions">
							<button type="submit" class="button button-primary button-hero">
								<?php esc_html_e( 'Start monitoring', 'metrixwire-apm' ); ?>
							</button>
						</p>

						<p class="description mw-consent">
							<?php
							printf(
								/* translators: 1: link to the terms of service, 2: link to the privacy policy. */
								esc_html__( 'By starting, you agree to the %1$s and %2$s, and to this site sending MetrixWire the performance data described on the right.', 'metrixwire-apm' ),
								'<a href="' . esc_url( $metrixwire_apm_endpoint . '/terms' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Terms of Service', 'metrixwire-apm' ) . '</a>',
								'<a href="' . esc_url( $metrixwire_apm_endpoint . '/privacy' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy Policy', 'metrixwire-apm' ) . '</a>'
							);
							?>
						</p>
					</form>

					<p class="mw-alt">
						<a href="<?php echo esc_url( Admin::url( 'dashboard', array( 'mode' => 'signin' ) ) ); ?>">
							<?php esc_html_e( 'Already have a MetrixWire account? Sign in instead.', 'metrixwire-apm' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="mw-card">
					<h2><?php esc_html_e( 'Sign in to MetrixWire', 'metrixwire-apm' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Connects this site to an account you already have. If this site was connected before, it picks up the same project and its history.', 'metrixwire-apm' ); ?>
					</p>

					<form class="mw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php Actions::nonce_field(); ?>
						<input type="hidden" name="action" value="metrixwire_apm_connect">
						<input type="hidden" name="mode" value="signin">

						<p class="mw-field">
							<label for="mw-email"><?php esc_html_e( 'Email address', 'metrixwire-apm' ); ?></label>
							<input type="email" id="mw-email" name="email" class="regular-text" required
								value="<?php echo esc_attr( $metrixwire_apm_email ); ?>" autocomplete="email">
						</p>

						<p class="mw-field">
							<label for="mw-password"><?php esc_html_e( 'Password', 'metrixwire-apm' ); ?></label>
							<input type="password" id="mw-password" name="password" class="regular-text" required
								autocomplete="current-password">
							<span class="description"><?php esc_html_e( 'Checked against MetrixWire and never stored on this site.', 'metrixwire-apm' ); ?></span>
						</p>

						<p class="mw-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Sign in & connect', 'metrixwire-apm' ); ?></button>
						</p>
					</form>

					<p class="mw-alt">
						<a href="<?php echo esc_url( Admin::url( 'dashboard' ) ); ?>">
							<?php esc_html_e( '← Back to one-click setup', 'metrixwire-apm' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<aside class="mw-setup__aside">
			<div class="mw-card">
				<h2><?php esc_html_e( 'What happens', 'metrixwire-apm' ); ?></h2>
				<ol class="mw-steps">
					<li><?php esc_html_e( 'A workspace and a project are created for this site, with their own API key.', 'metrixwire-apm' ); ?></li>
					<li><?php esc_html_e( 'Every request is traced from the next page view — no code changes.', 'metrixwire-apm' ); ?></li>
					<li><?php esc_html_e( 'Detected issues appear right here in wp-admin, minutes later.', 'metrixwire-apm' ); ?></li>
					<li><?php esc_html_e( 'Add an email and password whenever you like, under Account.', 'metrixwire-apm' ); ?></li>
				</ol>
			</div>

			<div class="mw-card">
				<h2><?php esc_html_e( 'What gets sent', 'metrixwire-apm' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: the MetrixWire endpoint this site will send to. */
						esc_html__( 'This site sends the HTTP method, path, response status and duration of each request to %s — plus the SQL text and duration of each database query when SAVEQUERIES is enabled. Connecting also sends this site\'s URL and title, and its WordPress and plugin versions.', 'metrixwire-apm' ),
						esc_url( $metrixwire_apm_endpoint )
					);
					?>
				</p>
			</div>
		</aside>
	</div>
</div>
