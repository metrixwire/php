<?php
/**
 * Issue list, with the same filters the MetrixWire dashboard offers.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection     $connection Current pairing.
 * @var array<string, mixed>|\WP_Error $result     GET /api/wp/issues.
 * @var array<string, mixed>           $query      Filters in force.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_title  = __( 'Issues', 'metrixwire-apm' );
$metrixwire_apm_screen = 'issues';
$metrixwire_apm_intro  = __( 'Everything MetrixWire has detected on this site.', 'metrixwire-apm' );

require __DIR__ . '/partials/header.php';

if ( View::maybe_error( $result ) ) {
	echo '</div>';

	return;
}

$metrixwire_apm_items    = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
$metrixwire_apm_total    = (int) ( $result['total'] ?? 0 );
$metrixwire_apm_limit    = max( 1, (int) ( $result['limit'] ?? 20 ) );
$metrixwire_apm_offset   = (int) ( $result['offset'] ?? 0 );
$metrixwire_apm_page     = (int) floor( $metrixwire_apm_offset / $metrixwire_apm_limit ) + 1;
$metrixwire_apm_pages    = (int) ceil( $metrixwire_apm_total / $metrixwire_apm_limit );
$metrixwire_apm_severity = (string) ( $query['severity'] ?? '' );
$metrixwire_apm_since    = (string) ( $query['since'] ?? 'all' );
$metrixwire_apm_sort     = (string) ( $query['sort'] ?? 'recent' );
$metrixwire_apm_route    = (string) ( $query['route'] ?? '' );
?>

	<form class="mw-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::page( 'issues' ) ); ?>">

		<label class="screen-reader-text" for="mw-filter-severity"><?php esc_html_e( 'Severity', 'metrixwire-apm' ); ?></label>
		<select id="mw-filter-severity" name="severity">
			<option value=""><?php esc_html_e( 'All severities', 'metrixwire-apm' ); ?></option>
			<?php foreach ( array( 'critical', 'high', 'medium', 'low' ) as $metrixwire_apm_option ) : ?>
				<option value="<?php echo esc_attr( $metrixwire_apm_option ); ?>" <?php selected( $metrixwire_apm_severity, $metrixwire_apm_option ); ?>>
					<?php echo esc_html( View::severity_label( $metrixwire_apm_option ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="mw-filter-since"><?php esc_html_e( 'Time range', 'metrixwire-apm' ); ?></label>
		<select id="mw-filter-since" name="since">
			<?php
			$metrixwire_apm_ranges = array(
				'1h'  => __( 'Last hour', 'metrixwire-apm' ),
				'24h' => __( 'Last 24 hours', 'metrixwire-apm' ),
				'7d'  => __( 'Last 7 days', 'metrixwire-apm' ),
				'30d' => __( 'Last 30 days', 'metrixwire-apm' ),
				'all' => __( 'All time', 'metrixwire-apm' ),
			);
			foreach ( $metrixwire_apm_ranges as $metrixwire_apm_value => $metrixwire_apm_label ) :
				?>
				<option value="<?php echo esc_attr( $metrixwire_apm_value ); ?>" <?php selected( $metrixwire_apm_since, $metrixwire_apm_value ); ?>><?php echo esc_html( $metrixwire_apm_label ); ?></option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="mw-filter-sort"><?php esc_html_e( 'Sort by', 'metrixwire-apm' ); ?></label>
		<select id="mw-filter-sort" name="sort">
			<?php
			$metrixwire_apm_sorts = array(
				'recent'      => __( 'Most recent', 'metrixwire-apm' ),
				'occurrences' => __( 'Most frequent', 'metrixwire-apm' ),
				'severity'    => __( 'Most severe', 'metrixwire-apm' ),
			);
			foreach ( $metrixwire_apm_sorts as $metrixwire_apm_value => $metrixwire_apm_label ) :
				?>
				<option value="<?php echo esc_attr( $metrixwire_apm_value ); ?>" <?php selected( $metrixwire_apm_sort, $metrixwire_apm_value ); ?>><?php echo esc_html( $metrixwire_apm_label ); ?></option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="mw-filter-route"><?php esc_html_e( 'Route contains', 'metrixwire-apm' ); ?></label>
		<input type="search" id="mw-filter-route" name="route" value="<?php echo esc_attr( $metrixwire_apm_route ); ?>"
			placeholder="<?php esc_attr_e( 'Route contains…', 'metrixwire-apm' ); ?>">

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'metrixwire-apm' ); ?></button>
	</form>

	<?php if ( empty( $metrixwire_apm_items ) ) : ?>
		<div class="mw-card mw-empty">
			<p><strong><?php esc_html_e( 'No issues match these filters.', 'metrixwire-apm' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'A quiet site is a good sign — widen the time range if you expected results.', 'metrixwire-apm' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped mw-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Issue', 'metrixwire-apm' ); ?></th>
					<th><?php esc_html_e( 'Type', 'metrixwire-apm' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'metrixwire-apm' ); ?></th>
					<th class="mw-num"><?php esc_html_e( 'Occurrences', 'metrixwire-apm' ); ?></th>
					<th><?php esc_html_e( 'Last seen', 'metrixwire-apm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $metrixwire_apm_items as $metrixwire_apm_issue ) : ?>
					<tr>
						<td>
							<a class="row-title" href="<?php echo esc_url( View::issue_url( (string) ( $metrixwire_apm_issue['id'] ?? '' ) ) ); ?>">
								<?php echo esc_html( (string) ( $metrixwire_apm_issue['title'] ?? '' ) ); ?>
							</a>
							<div class="mw-muted"><code><?php echo esc_html( (string) ( $metrixwire_apm_issue['route'] ?? '' ) ); ?></code></div>
						</td>
						<td><?php echo esc_html( View::humanize( (string) ( $metrixwire_apm_issue['type'] ?? '' ) ) ); ?></td>
						<td><?php echo View::severity_badge( (string) ( $metrixwire_apm_issue['severity'] ?? 'low' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td class="mw-num"><?php echo esc_html( number_format_i18n( (int) ( $metrixwire_apm_issue['occurrenceCount'] ?? 0 ) ) ); ?></td>
						<td title="<?php echo esc_attr( View::datetime( isset( $metrixwire_apm_issue['lastSeenAt'] ) ? (string) $metrixwire_apm_issue['lastSeenAt'] : null ) ); ?>">
							<?php echo esc_html( View::ago( isset( $metrixwire_apm_issue['lastSeenAt'] ) ? (string) $metrixwire_apm_issue['lastSeenAt'] : null ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $metrixwire_apm_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: total number of issues matching the filters. */
						esc_html( _n( '%s issue', '%s issues', $metrixwire_apm_total, 'metrixwire-apm' ) ),
						esc_html( number_format_i18n( $metrixwire_apm_total ) )
					);
					?>
				</span>
				<?php
				// paginate_links() emits its own escaped anchor markup.
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $metrixwire_apm_page,
						'total'     => $metrixwire_apm_pages,
						'prev_text' => '‹',
						'next_text' => '›',
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
