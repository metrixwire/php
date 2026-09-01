<?php
/**
 * Screen header: title, queued notice, and the connected-site strip.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection $connection Current pairing.
 * @var string                     $metrixwire_apm_title   Screen title.
 * @var string                     $metrixwire_apm_screen  Screen key, for the refresh link.
 * @var string                     $metrixwire_apm_intro   Optional sentence under the title.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_notice = Actions::take_notice();
?>
<div class="wrap metrixwire-apm">
	<h1 class="mw-title"><?php echo esc_html( $metrixwire_apm_title ); ?></h1>

	<?php if ( ! empty( $metrixwire_apm_intro ) ) : ?>
		<p class="mw-intro"><?php echo esc_html( $metrixwire_apm_intro ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $metrixwire_apm_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $metrixwire_apm_notice[0] ); ?> is-dismissible">
			<p><?php echo esc_html( $metrixwire_apm_notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $connection->is_connected() ) : ?>
		<div class="mw-strip">
			<div class="mw-strip__facts">
				<span class="mw-strip__dot mw-strip__dot--<?php echo $connection->is_tracing() ? 'on' : 'off'; ?>"></span>
				<strong>
					<?php
					echo $connection->is_tracing()
						? esc_html__( 'Tracing active', 'metrixwire-apm' )
						: esc_html__( 'Tracing paused', 'metrixwire-apm' );
					?>
				</strong>
				<?php if ( '' !== $connection->account_email() ) : ?>
					<span class="mw-strip__sep">·</span>
					<span><?php echo esc_html( $connection->account_email() ); ?></span>
				<?php endif; ?>
			</div>
			<div class="mw-strip__actions">
				<a class="button button-secondary" href="<?php echo esc_url( Actions::action_url( 'metrixwire_apm_refresh', array( 'screen' => $metrixwire_apm_screen ) ) ); ?>">
					<?php esc_html_e( 'Refresh', 'metrixwire-apm' ); ?>
				</a>
				<a class="button button-primary" href="<?php echo esc_url( Actions::action_url( 'metrixwire_apm_sso', array( 'destination' => $metrixwire_apm_screen ) ) ); ?>">
					<?php esc_html_e( 'Open MetrixWire ↗', 'metrixwire-apm' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>
