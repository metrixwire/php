<?php
/**
 * Dashboard — this site's health, without leaving wp-admin.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection    $connection Current pairing.
 * @var array<string, mixed>|\WP_Error $status    GET /api/wp/status.
 * @var array<string, mixed>|\WP_Error $overview  GET /api/wp/overview.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_title  = __( 'MetrixWire', 'metrixwire-apm' );
$metrixwire_apm_screen = 'dashboard';
$metrixwire_apm_intro  = '';

require __DIR__ . '/partials/header.php';

if ( View::maybe_error( $status ) || View::maybe_error( $overview ) ) {
	echo '</div>';

	return;
}

$metrixwire_apm_project    = isset( $status['project'] ) && is_array( $status['project'] ) ? $status['project'] : array();
$metrixwire_apm_plan       = isset( $status['plan'] ) && is_array( $status['plan'] ) ? $status['plan'] : array();
$metrixwire_apm_usage      = isset( $status['usage'] ) && is_array( $status['usage'] ) ? $status['usage'] : array();
$metrixwire_apm_totals     = isset( $overview['totals'] ) && is_array( $overview['totals'] ) ? $overview['totals'] : array();
$metrixwire_apm_prev       = isset( $overview['prevTotals'] ) && is_array( $overview['prevTotals'] ) ? $overview['prevTotals'] : array();
$metrixwire_apm_days       = isset( $overview['byDayLast7'] ) && is_array( $overview['byDayLast7'] ) ? $overview['byDayLast7'] : array();
$metrixwire_apm_top_issues = isset( $overview['topIssues'] ) && is_array( $overview['topIssues'] ) ? $overview['topIssues'] : array();
$metrixwire_apm_routes     = isset( $overview['byRoute'] ) && is_array( $overview['byRoute'] ) ? $overview['byRoute'] : array();
$metrixwire_apm_last_trace = isset( $overview['lastTraceAt'] ) ? (string) $overview['lastTraceAt'] : '';
$metrixwire_apm_traces     = (int) ( $metrixwire_apm_usage['totalTracesThisMonth'] ?? 0 );
$metrixwire_apm_quota      = isset( $metrixwire_apm_plan['maxTracesPerMonth'] ) ? (int) $metrixwire_apm_plan['maxTracesPerMonth'] : null;
?>

	<?php if ( '' === $metrixwire_apm_last_trace ) : ?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'No traces received yet.', 'metrixwire-apm' ); ?></strong>
				<?php esc_html_e( 'Open any page on this site — the first trace is sent after that request finishes, and issues appear here once enough traffic has been analysed.', 'metrixwire-apm' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="mw-grid mw-grid--stats">
		<?php
		$metrixwire_apm_cards = array(
			array( 'total', __( 'Issues (7 days)', 'metrixwire-apm' ) ),
			array( 'high', __( 'High severity', 'metrixwire-apm' ) ),
			array( 'medium', __( 'Medium severity', 'metrixwire-apm' ) ),
			array( 'low', __( 'Low severity', 'metrixwire-apm' ) ),
		);

		foreach ( $metrixwire_apm_cards as list( $metrixwire_apm_key, $metrixwire_apm_label ) ) :
			$metrixwire_apm_value = (int) ( $metrixwire_apm_totals[ $metrixwire_apm_key ] ?? 0 );
			?>
			<div class="mw-card mw-stat mw-stat--<?php echo esc_attr( $metrixwire_apm_key ); ?>">
				<span class="mw-stat__label"><?php echo esc_html( $metrixwire_apm_label ); ?></span>
				<span class="mw-stat__value"><?php echo esc_html( number_format_i18n( $metrixwire_apm_value ) ); ?></span>
				<span class="mw-stat__trend"><?php echo esc_html( View::trend( $metrixwire_apm_value, (int) ( $metrixwire_apm_prev[ $metrixwire_apm_key ] ?? 0 ) ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="mw-grid mw-grid--split">
		<div class="mw-card">
			<h2><?php esc_html_e( 'Issues per day', 'metrixwire-apm' ); ?></h2>
			<?php
			// Chart markup is built by View::day_chart() from numbers the API
			// returned; every interpolated value is escaped there.
			echo View::day_chart( $metrixwire_apm_days ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<div class="mw-chart__labels">
				<?php echo View::day_labels( $metrixwire_apm_days ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<p class="mw-legend">
				<span class="mw-legend__key mw-legend__key--high"></span><?php esc_html_e( 'High', 'metrixwire-apm' ); ?>
				<span class="mw-legend__key mw-legend__key--medium"></span><?php esc_html_e( 'Medium', 'metrixwire-apm' ); ?>
				<span class="mw-legend__key mw-legend__key--low"></span><?php esc_html_e( 'Low', 'metrixwire-apm' ); ?>
			</p>
		</div>

		<div class="mw-card">
			<h2><?php esc_html_e( 'This site', 'metrixwire-apm' ); ?></h2>
			<table class="mw-facts">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Project', 'metrixwire-apm' ); ?></th>
						<td><?php echo esc_html( (string) ( $metrixwire_apm_project['name'] ?? '—' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last trace', 'metrixwire-apm' ); ?></th>
						<td title="<?php echo esc_attr( View::datetime( $metrixwire_apm_last_trace ) ); ?>"><?php echo esc_html( View::ago( $metrixwire_apm_last_trace ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Collection', 'metrixwire-apm' ); ?></th>
						<td>
							<?php
							echo ! empty( $metrixwire_apm_project['isActive'] )
								? esc_html__( 'Accepting traces', 'metrixwire-apm' )
								: esc_html__( 'Paused in MetrixWire', 'metrixwire-apm' );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Query spans', 'metrixwire-apm' ); ?></th>
						<td>
							<?php if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) : ?>
								<span class="mw-ok"><?php esc_html_e( 'Enabled', 'metrixwire-apm' ); ?></span>
							<?php else : ?>
								<span class="mw-warn"><?php esc_html_e( 'Disabled', 'metrixwire-apm' ); ?></span>
								— <a href="<?php echo esc_url( Admin::url( 'settings' ) ); ?>"><?php esc_html_e( 'how to turn on', 'metrixwire-apm' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plan', 'metrixwire-apm' ); ?></th>
						<td><?php echo esc_html( (string) ( $metrixwire_apm_plan['name'] ?? '—' ) ); ?></td>
					</tr>
				</tbody>
			</table>

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
			<?php endif; ?>
		</div>
	</div>

	<div class="mw-grid mw-grid--split">
		<div class="mw-card">
			<h2><?php esc_html_e( 'Top issues', 'metrixwire-apm' ); ?></h2>
			<?php if ( empty( $metrixwire_apm_top_issues ) ) : ?>
				<p class="description"><?php esc_html_e( 'Nothing detected in the last 7 days.', 'metrixwire-apm' ); ?></p>
			<?php else : ?>
				<table class="widefat striped mw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Issue', 'metrixwire-apm' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'metrixwire-apm' ); ?></th>
							<th class="mw-num"><?php esc_html_e( 'Seen', 'metrixwire-apm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $metrixwire_apm_top_issues as $metrixwire_apm_issue ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( View::issue_url( (string) ( $metrixwire_apm_issue['id'] ?? '' ) ) ); ?>">
										<?php echo esc_html( (string) ( $metrixwire_apm_issue['title'] ?? '' ) ); ?>
									</a>
									<div class="mw-muted"><?php echo esc_html( (string) ( $metrixwire_apm_issue['route'] ?? '' ) ); ?></div>
								</td>
								<td><?php echo View::severity_badge( (string) ( $metrixwire_apm_issue['severity'] ?? 'low' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td class="mw-num"><?php echo esc_html( number_format_i18n( (int) ( $metrixwire_apm_issue['occurrenceCount'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><a href="<?php echo esc_url( Admin::url( 'issues' ) ); ?>"><?php esc_html_e( 'View all issues', 'metrixwire-apm' ); ?></a></p>
			<?php endif; ?>
		</div>

		<div class="mw-card">
			<h2><?php esc_html_e( 'Most affected routes', 'metrixwire-apm' ); ?></h2>
			<?php if ( empty( $metrixwire_apm_routes ) ) : ?>
				<p class="description"><?php esc_html_e( 'No affected routes yet.', 'metrixwire-apm' ); ?></p>
			<?php else : ?>
				<ul class="mw-list">
					<?php foreach ( $metrixwire_apm_routes as $metrixwire_apm_route ) : ?>
						<li>
							<code><?php echo esc_html( (string) ( $metrixwire_apm_route['route'] ?? '' ) ); ?></code>
							<span class="mw-count"><?php echo esc_html( number_format_i18n( (int) ( $metrixwire_apm_route['c'] ?? 0 ) ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
