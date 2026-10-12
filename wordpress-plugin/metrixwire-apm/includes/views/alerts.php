<?php
/**
 * Alert rules covering this site.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection            $connection Current pairing.
 * @var array<int, array<string, mixed>>|\WP_Error $rules  GET /api/wp/alerts.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_title  = __( 'Alerts', 'metrixwire-apm' );
$metrixwire_apm_screen = 'alerts';
$metrixwire_apm_intro  = __( 'Get told when MetrixWire detects something on this site.', 'metrixwire-apm' );

require __DIR__ . '/partials/header.php';

if ( View::maybe_error( $rules ) ) {
	echo '</div>';

	return;
}

$metrixwire_apm_rules = is_array( $rules ) ? $rules : array();
?>

	<div class="mw-card">
		<h2><?php esc_html_e( 'Add a rule', 'metrixwire-apm' ); ?></h2>
		<form class="mw-form mw-form--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php Actions::nonce_field(); ?>
			<input type="hidden" name="action" value="metrixwire_apm_alert_create">

			<p class="mw-field">
				<label for="mw-alert-type"><?php esc_html_e( 'Send to', 'metrixwire-apm' ); ?></label>
				<select id="mw-alert-type" name="type">
					<option value="email"><?php esc_html_e( 'Email', 'metrixwire-apm' ); ?></option>
					<option value="webhook"><?php esc_html_e( 'Webhook', 'metrixwire-apm' ); ?></option>
				</select>
			</p>

			<p class="mw-field mw-field--grow">
				<label for="mw-alert-target"><?php esc_html_e( 'Destination', 'metrixwire-apm' ); ?></label>
				<input type="text" id="mw-alert-target" name="target" class="regular-text" required
					value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>"
					placeholder="<?php esc_attr_e( 'you@example.com', 'metrixwire-apm' ); ?>">
			</p>

			<p class="mw-field">
				<label for="mw-alert-severity"><?php esc_html_e( 'Notify from', 'metrixwire-apm' ); ?></label>
				<select id="mw-alert-severity" name="min_severity">
					<option value="critical"><?php esc_html_e( 'Critical only', 'metrixwire-apm' ); ?></option>
					<option value="high"><?php esc_html_e( 'High and up', 'metrixwire-apm' ); ?></option>
					<option value="medium" selected><?php esc_html_e( 'Medium and up', 'metrixwire-apm' ); ?></option>
					<option value="low"><?php esc_html_e( 'Everything', 'metrixwire-apm' ); ?></option>
				</select>
			</p>

			<p class="mw-field">
				<label for="mw-alert-name"><?php esc_html_e( 'Label', 'metrixwire-apm' ); ?></label>
				<input type="text" id="mw-alert-name" name="name" class="regular-text"
					placeholder="<?php esc_attr_e( 'Optional', 'metrixwire-apm' ); ?>">
			</p>

			<p class="mw-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add rule', 'metrixwire-apm' ); ?></button>
			</p>
		</form>
	</div>

	<div class="mw-card">
		<h2><?php esc_html_e( 'Rules covering this site', 'metrixwire-apm' ); ?></h2>

		<?php if ( empty( $metrixwire_apm_rules ) ) : ?>
			<p class="description"><?php esc_html_e( 'No alert rules yet — this site is monitored, but nobody is being told.', 'metrixwire-apm' ); ?></p>
		<?php else : ?>
			<table class="widefat striped mw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Destination', 'metrixwire-apm' ); ?></th>
						<th><?php esc_html_e( 'From severity', 'metrixwire-apm' ); ?></th>
						<th><?php esc_html_e( 'Status', 'metrixwire-apm' ); ?></th>
						<th class="mw-actions-col"><?php esc_html_e( 'Actions', 'metrixwire-apm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $metrixwire_apm_rules as $metrixwire_apm_rule ) : ?>
						<?php
						$metrixwire_apm_config = isset( $metrixwire_apm_rule['config'] ) && is_array( $metrixwire_apm_rule['config'] ) ? $metrixwire_apm_rule['config'] : array();
						$metrixwire_apm_target = (string) ( $metrixwire_apm_config['email'] ?? $metrixwire_apm_config['url'] ?? '' );
						$metrixwire_apm_scoped = ! empty( $metrixwire_apm_rule['scopedToThisSite'] );
						$metrixwire_apm_id     = (string) ( $metrixwire_apm_rule['id'] ?? '' );
						$metrixwire_apm_active = ! empty( $metrixwire_apm_rule['isActive'] );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( '' !== (string) ( $metrixwire_apm_rule['name'] ?? '' ) ? (string) $metrixwire_apm_rule['name'] : $metrixwire_apm_target ); ?></strong>
								<div class="mw-muted"><?php echo esc_html( $metrixwire_apm_target ); ?></div>
							</td>
							<td><?php echo esc_html( View::severity_label( (string) ( $metrixwire_apm_rule['minSeverity'] ?? 'medium' ) ) ); ?></td>
							<td>
								<?php if ( $metrixwire_apm_active ) : ?>
									<span class="mw-ok"><?php esc_html_e( 'Active', 'metrixwire-apm' ); ?></span>
								<?php else : ?>
									<span class="mw-muted"><?php esc_html_e( 'Paused', 'metrixwire-apm' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="mw-actions-col">
								<?php if ( ! $metrixwire_apm_scoped ) : ?>
									<span class="description"><?php esc_html_e( 'Account-wide rule — edit it in MetrixWire.', 'metrixwire-apm' ); ?></span>
								<?php else : ?>
									<?php
									$metrixwire_apm_ops = array(
										'test' => __( 'Send test', 'metrixwire-apm' ),
										$metrixwire_apm_active ? 'pause' : 'resume' => $metrixwire_apm_active
											? __( 'Pause', 'metrixwire-apm' )
											: __( 'Resume', 'metrixwire-apm' ),
										'delete' => __( 'Delete', 'metrixwire-apm' ),
									);
									foreach ( $metrixwire_apm_ops as $metrixwire_apm_op => $metrixwire_apm_label ) :
										?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mw-inline-form">
											<?php Actions::nonce_field(); ?>
											<input type="hidden" name="action" value="metrixwire_apm_alert_update">
											<input type="hidden" name="rule_id" value="<?php echo esc_attr( $metrixwire_apm_id ); ?>">
											<input type="hidden" name="op" value="<?php echo esc_attr( $metrixwire_apm_op ); ?>">
											<button type="submit" class="button-link <?php echo 'delete' === $metrixwire_apm_op ? 'mw-danger' : ''; ?>">
												<?php echo esc_html( $metrixwire_apm_label ); ?>
											</button>
										</form>
									<?php endforeach; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
