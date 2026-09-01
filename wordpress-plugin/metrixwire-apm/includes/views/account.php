<?php
/**
 * Account & plan — who owns this site's data, and how much of the plan it uses.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection     $connection Current pairing.
 * @var array<string, mixed>|\WP_Error $status     GET /api/wp/status.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_title  = __( 'Account & plan', 'metrixwire-apm' );
$metrixwire_apm_screen = 'billing';
$metrixwire_apm_intro  = __( 'The MetrixWire account this site reports to.', 'metrixwire-apm' );

require __DIR__ . '/partials/header.php';

if ( View::maybe_error( $status ) ) {
	echo '</div>';

	return;
}

$metrixwire_apm_owner   = isset( $status['owner'] ) && is_array( $status['owner'] ) ? $status['owner'] : array();
$metrixwire_apm_plan    = isset( $status['plan'] ) && is_array( $status['plan'] ) ? $status['plan'] : array();
$metrixwire_apm_usage   = isset( $status['usage'] ) && is_array( $status['usage'] ) ? $status['usage'] : array();
$metrixwire_apm_traces  = (int) ( $metrixwire_apm_usage['totalTracesThisMonth'] ?? 0 );
$metrixwire_apm_quota   = isset( $metrixwire_apm_plan['maxTracesPerMonth'] ) ? (int) $metrixwire_apm_plan['maxTracesPerMonth'] : null;
$metrixwire_apm_price   = isset( $metrixwire_apm_plan['priceCents'] ) ? (int) $metrixwire_apm_plan['priceCents'] : null;
$metrixwire_apm_feature = isset( $metrixwire_apm_plan['features'] ) && is_array( $metrixwire_apm_plan['features'] ) ? $metrixwire_apm_plan['features'] : array();
$metrixwire_apm_provisional = ! empty( $metrixwire_apm_owner['isProvisional'] );
?>

	<?php if ( $metrixwire_apm_provisional ) : ?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'This account has no email or password yet.', 'metrixwire-apm' ); ?></strong>
				<?php esc_html_e( 'Monitoring works without them. Add both below to be able to sign in on metrixwire.com directly, and to recover the account if you lose access to this site.', 'metrixwire-apm' ); ?>
			</p>
		</div>
	<?php elseif ( isset( $metrixwire_apm_owner['emailVerified'] ) && ! $metrixwire_apm_owner['emailVerified'] ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Email not verified yet.', 'metrixwire-apm' ); ?></strong>
				<?php esc_html_e( 'Everything keeps working — follow the link in the email MetrixWire sent to confirm the address.', 'metrixwire-apm' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="mw-grid mw-grid--split">
		<div class="mw-card">
			<h2><?php esc_html_e( 'Profile', 'metrixwire-apm' ); ?></h2>

			<form class="mw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php Actions::nonce_field(); ?>
				<input type="hidden" name="action" value="metrixwire_apm_profile">

				<p class="mw-field">
					<label for="mw-account-email"><?php esc_html_e( 'Email address', 'metrixwire-apm' ); ?></label>
					<input type="email" id="mw-account-email" name="email" class="regular-text" autocomplete="email"
						value="<?php echo esc_attr( (string) ( $metrixwire_apm_owner['email'] ?? '' ) ); ?>"
						placeholder="<?php esc_attr_e( 'you@example.com', 'metrixwire-apm' ); ?>">
					<span class="description">
						<?php
						echo $metrixwire_apm_provisional
							? esc_html__( 'Not set yet. Changing it sends a verification link.', 'metrixwire-apm' )
							: esc_html__( 'Changing it sends a verification link to the new address.', 'metrixwire-apm' );
						?>
					</span>
				</p>

				<p class="mw-field">
					<label for="mw-account-password"><?php esc_html_e( 'Password', 'metrixwire-apm' ); ?></label>
					<input type="password" id="mw-account-password" name="password" class="regular-text"
						autocomplete="new-password" minlength="8"
						placeholder="<?php esc_attr_e( 'Leave empty to keep the current one', 'metrixwire-apm' ); ?>">
					<span class="description"><?php esc_html_e( 'At least 8 characters.', 'metrixwire-apm' ); ?></span>
				</p>

				<p class="mw-actions">
					<button type="submit" class="button button-primary">
						<?php
						echo $metrixwire_apm_provisional
							? esc_html__( 'Save & finish setup', 'metrixwire-apm' )
							: esc_html__( 'Save profile', 'metrixwire-apm' );
						?>
					</button>
				</p>
			</form>

			<table class="mw-facts">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verified', 'metrixwire-apm' ); ?></th>
						<td>
							<?php if ( ! empty( $metrixwire_apm_owner['emailVerified'] ) ) : ?>
								<span class="mw-ok"><?php esc_html_e( 'Yes', 'metrixwire-apm' ); ?></span>
							<?php elseif ( $metrixwire_apm_provisional ) : ?>
								<span class="mw-muted"><?php esc_html_e( 'No email set', 'metrixwire-apm' ); ?></span>
							<?php else : ?>
								<span class="mw-warn"><?php esc_html_e( 'Not yet', 'metrixwire-apm' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Projects', 'metrixwire-apm' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( (int) ( $metrixwire_apm_usage['totalProjects'] ?? 0 ) ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<p class="description">
				<?php esc_html_e( 'Anyone who can administer this WordPress site can open this MetrixWire account with the button above.', 'metrixwire-apm' ); ?>
			</p>
		</div>

		<div class="mw-card">
			<h2>
				<?php echo esc_html( (string) ( $metrixwire_apm_plan['name'] ?? __( 'Plan', 'metrixwire-apm' ) ) ); ?>
				<?php if ( null !== $metrixwire_apm_price ) : ?>
					<span class="mw-plan__price">
						<?php
						echo 0 === $metrixwire_apm_price
							? esc_html__( 'Free', 'metrixwire-apm' )
							: esc_html(
								sprintf(
									/* translators: 1: currency code, 2: monthly price. */
									__( '%1$s %2$s / month', 'metrixwire-apm' ),
									strtoupper( (string) ( $metrixwire_apm_plan['currency'] ?? 'usd' ) ),
									number_format_i18n( $metrixwire_apm_price / 100, 2 )
								)
							);
						?>
					</span>
				<?php endif; ?>
			</h2>

			<?php if ( null !== $metrixwire_apm_quota && $metrixwire_apm_quota > 0 ) : ?>
				<p class="mw-meter__label">
					<?php
					printf(
						/* translators: 1: traces used this month, 2: monthly trace allowance. */
						esc_html__( '%1$s of %2$s traces this month', 'metrixwire-apm' ),
						esc_html( number_format_i18n( $metrixwire_apm_traces ) ),
						esc_html( number_format_i18n( $metrixwire_apm_quota ) )
					);
					?>
				</p>
				<?php echo View::meter( $metrixwire_apm_traces, $metrixwire_apm_quota ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<p class="mw-meter__label">
					<?php
					printf(
						/* translators: %s: number of traces recorded this month. */
						esc_html__( '%s traces this month — no limit on this plan.', 'metrixwire-apm' ),
						esc_html( number_format_i18n( $metrixwire_apm_traces ) )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $metrixwire_apm_feature ) ) : ?>
				<ul class="mw-list mw-list--check">
					<?php foreach ( $metrixwire_apm_feature as $metrixwire_apm_line ) : ?>
						<li><?php echo esc_html( (string) $metrixwire_apm_line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="mw-actions">
				<a class="button button-primary" href="<?php echo esc_url( Actions::action_url( 'metrixwire_apm_sso', array( 'destination' => 'billing' ) ) ); ?>">
					<?php esc_html_e( 'Manage plan & billing ↗', 'metrixwire-apm' ); ?>
				</a>
			</p>
			<p class="description">
				<?php esc_html_e( 'Payment is handled by MetrixWire’s checkout — the button signs you in and takes you straight there.', 'metrixwire-apm' ); ?>
			</p>
		</div>
	</div>

	<div class="mw-card mw-card--danger">
		<h2><?php esc_html_e( 'Sign out', 'metrixwire-apm' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Stops tracing and removes this site’s credentials, so you can connect it to a different MetrixWire account. Your project and its history are kept — signing back in picks them up again.', 'metrixwire-apm' ); ?>
		</p>
		<?php if ( $metrixwire_apm_provisional ) : ?>
			<p class="description mw-warn">
				<?php esc_html_e( 'This account has no email or password yet, so signing out now would leave no way back into it. Set both above first.', 'metrixwire-apm' ); ?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			onsubmit="return window.confirm( '<?php echo esc_js( __( 'Sign this site out of MetrixWire?', 'metrixwire-apm' ) ); ?>' );">
			<?php Actions::nonce_field(); ?>
			<input type="hidden" name="action" value="metrixwire_apm_disconnect">
			<button type="submit" class="button button-secondary mw-danger"><?php esc_html_e( 'Sign out this site', 'metrixwire-apm' ); ?></button>
		</form>
	</div>
</div>
