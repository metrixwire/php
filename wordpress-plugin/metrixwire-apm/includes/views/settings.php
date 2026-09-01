<?php
/**
 * Settings — project identity, collection, credentials, and disconnect.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection     $connection Current pairing.
 * @var array<string, mixed>|\WP_Error $status     GET /api/wp/status.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_title  = __( 'Settings', 'metrixwire-apm' );
$metrixwire_apm_screen = 'settings';
$metrixwire_apm_intro  = __( 'Everything about this site’s monitoring, editable here.', 'metrixwire-apm' );

require __DIR__ . '/partials/header.php';

$metrixwire_apm_project      = ( ! is_wp_error( $status ) && isset( $status['project'] ) && is_array( $status['project'] ) ) ? $status['project'] : array();
$metrixwire_apm_name         = (string) ( $metrixwire_apm_project['name'] ?? '' );
$metrixwire_apm_is_active    = ! empty( $metrixwire_apm_project['isActive'] );
$metrixwire_apm_enabled_lock = $connection->is_overridden( Connection::OPT_ENABLED );
$metrixwire_apm_key_lock     = $connection->is_overridden( Connection::OPT_API_KEY );
$metrixwire_apm_endpoint_lock = $connection->is_overridden( Connection::OPT_ENDPOINT );

View::maybe_error( $status );
?>

	<?php if ( $connection->has_overrides() ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: 1: METRIXWIRE_* constant prefix, 2: wp-config.php file name. */
					esc_html__( 'One or more %1$s constants are defined in %2$s. Those values win, and the fields they cover are shown read-only below.', 'metrixwire-apm' ),
					'<code>METRIXWIRE_*</code>',
					'<code>wp-config.php</code>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form class="mw-card mw-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php Actions::nonce_field(); ?>
		<input type="hidden" name="action" value="metrixwire_apm_settings">

		<h2><?php esc_html_e( 'Collection', 'metrixwire-apm' ); ?></h2>

		<p class="mw-field">
			<label for="mw-project-name"><?php esc_html_e( 'Project name', 'metrixwire-apm' ); ?></label>
			<input type="text" id="mw-project-name" name="project_name" class="regular-text" value="<?php echo esc_attr( $metrixwire_apm_name ); ?>">
			<span class="description"><?php esc_html_e( 'How this site is labelled in MetrixWire.', 'metrixwire-apm' ); ?></span>
		</p>

		<p class="mw-field mw-field--check">
			<label>
				<input type="checkbox" name="enabled" value="1" <?php checked( $connection->is_enabled() ); ?> <?php disabled( $metrixwire_apm_enabled_lock ); ?>>
				<?php esc_html_e( 'Send traces from this site', 'metrixwire-apm' ); ?>
			</label>
			<?php if ( $metrixwire_apm_enabled_lock ) : ?>
				<span class="description">
					<?php
					printf(
						/* translators: %s: name of the PHP constant defined in wp-config.php. */
						esc_html__( 'Set by %s in wp-config.php.', 'metrixwire-apm' ),
						'<code>' . esc_html( $connection->override_constant( Connection::OPT_ENABLED ) ) . '</code>'
					);
					?>
				</span>
			<?php endif; ?>
		</p>

		<p class="mw-field mw-field--check">
			<label>
				<input type="checkbox" name="is_active" value="1" <?php checked( $metrixwire_apm_is_active ); ?>>
				<?php esc_html_e( 'Accept traces in MetrixWire', 'metrixwire-apm' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'Turning this off makes MetrixWire reject this project’s traces, wherever they come from.', 'metrixwire-apm' ); ?></span>
		</p>

		<p class="mw-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'metrixwire-apm' ); ?></button>
		</p>
	</form>

	<div class="mw-card">
		<h2><?php esc_html_e( 'Credentials', 'metrixwire-apm' ); ?></h2>

		<table class="mw-facts">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Account', 'metrixwire-apm' ); ?></th>
					<td><?php echo esc_html( '' !== $connection->account_email() ? $connection->account_email() : '—' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Endpoint', 'metrixwire-apm' ); ?></th>
					<td>
						<code><?php echo esc_html( $connection->endpoint() ); ?></code>
						<?php if ( $metrixwire_apm_endpoint_lock ) : ?>
							<span class="description">
								<?php
								printf(
									/* translators: %s: name of the PHP constant defined in wp-config.php. */
									esc_html__( 'Set by %s in wp-config.php.', 'metrixwire-apm' ),
									'<code>' . esc_html( $connection->override_constant( Connection::OPT_ENDPOINT ) ) . '</code>'
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API key', 'metrixwire-apm' ); ?></th>
					<td>
						<code><?php echo esc_html( Connection::mask( $connection->api_key() ) ); ?></code>
						<?php if ( $metrixwire_apm_key_lock ) : ?>
							<span class="description">
								<?php
								printf(
									/* translators: %s: name of the PHP constant defined in wp-config.php. */
									esc_html__( 'Set by %s in wp-config.php.', 'metrixwire-apm' ),
									'<code>' . esc_html( $connection->override_constant( Connection::OPT_API_KEY ) ) . '</code>'
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="mw-actions">
			<button type="button" id="metrixwire-apm-test-connection" class="button button-secondary">
				<?php esc_html_e( 'Test connection', 'metrixwire-apm' ); ?>
			</button>
			<span id="metrixwire-apm-test-status" class="metrixwire-apm-status" role="status"></span>
		</p>

		<?php if ( ! $metrixwire_apm_key_lock ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mw-inline-form">
				<?php Actions::nonce_field(); ?>
				<input type="hidden" name="action" value="metrixwire_apm_rotate_key">
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Rotate API key', 'metrixwire-apm' ); ?></button>
				<span class="description"><?php esc_html_e( 'Issues a new key and stores it here in one step.', 'metrixwire-apm' ); ?></span>
			</form>
		<?php endif; ?>
	</div>

	<div class="mw-card">
		<h2><?php esc_html_e( 'Database query capture', 'metrixwire-apm' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: SAVEQUERIES constant, 2: wp-config.php file name. */
				esc_html__( 'Per-query spans require WordPress’s %1$s constant, which can only be set in %2$s — WordPress reads it before plugins load:', 'metrixwire-apm' ),
				'<code>SAVEQUERIES</code>',
				'<code>wp-config.php</code>'
			);
			?>
		</p>
		<pre>define( 'SAVEQUERIES', true );</pre>
		<p>
			<?php esc_html_e( 'Current status:', 'metrixwire-apm' ); ?>
			<?php if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) : ?>
				<strong class="mw-ok"><?php esc_html_e( 'enabled', 'metrixwire-apm' ); ?></strong>
				— <?php esc_html_e( 'queries are being captured.', 'metrixwire-apm' ); ?>
			<?php else : ?>
				<strong class="mw-warn"><?php esc_html_e( 'disabled', 'metrixwire-apm' ); ?></strong>
				— <?php esc_html_e( 'traces are still sent, without per-query spans.', 'metrixwire-apm' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<p class="description">
		<?php
		printf(
			/* translators: %s: link to the plugin's Account screen. */
			esc_html__( 'Signing this site out of MetrixWire lives on the %s screen.', 'metrixwire-apm' ),
			'<a href="' . esc_url( Admin::url( 'account' ) ) . '">' . esc_html__( 'Account', 'metrixwire-apm' ) . '</a>'
		);
		?>
	</p>
</div>
