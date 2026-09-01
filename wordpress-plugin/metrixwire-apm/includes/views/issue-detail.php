<?php
/**
 * One issue: what it is, why it matters, how to fix it, and a sample trace.
 *
 * @package MetrixWire\APM
 *
 * @var \MetrixWire\APM\Connection     $connection Current pairing.
 * @var array<string, mixed>|\WP_Error $issue      GET /api/wp/issues/{id}.
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

$metrixwire_apm_title  = __( 'Issue detail', 'metrixwire-apm' );
$metrixwire_apm_screen = 'issues';
$metrixwire_apm_intro  = '';

require __DIR__ . '/partials/header.php';

if ( View::maybe_error( $issue ) ) {
	echo '<p><a href="' . esc_url( Admin::url( 'issues' ) ) . '">' . esc_html__( '← Back to issues', 'metrixwire-apm' ) . '</a></p></div>';

	return;
}

$metrixwire_apm_trace = isset( $issue['sampleTrace'] ) && is_array( $issue['sampleTrace'] ) ? $issue['sampleTrace'] : null;
$metrixwire_apm_spans = isset( $issue['sampleSpans'] ) && is_array( $issue['sampleSpans'] ) ? $issue['sampleSpans'] : array();

// The waterfall is drawn relative to the slowest span, so one very slow query
// reads as a full bar and everything else is proportional to it.
$metrixwire_apm_slowest = 0.0;
foreach ( $metrixwire_apm_spans as $metrixwire_apm_span ) {
	$metrixwire_apm_slowest = max( $metrixwire_apm_slowest, (float) ( $metrixwire_apm_span['durationMs'] ?? 0 ) );
}
$metrixwire_apm_slowest = max( $metrixwire_apm_slowest, 0.001 );
?>

	<p><a href="<?php echo esc_url( Admin::url( 'issues' ) ); ?>">&larr; <?php esc_html_e( 'Back to issues', 'metrixwire-apm' ); ?></a></p>

	<div class="mw-card">
		<div class="mw-issue__head">
			<h2><?php echo esc_html( (string) ( $issue['title'] ?? '' ) ); ?></h2>
			<?php echo View::severity_badge( (string) ( $issue['severity'] ?? 'low' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<table class="mw-facts">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Route', 'metrixwire-apm' ); ?></th>
					<td><code><?php echo esc_html( (string) ( $issue['route'] ?? '' ) ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Detector', 'metrixwire-apm' ); ?></th>
					<td><?php echo esc_html( View::humanize( (string) ( $issue['type'] ?? '' ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Occurrences', 'metrixwire-apm' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( (int) ( $issue['occurrenceCount'] ?? 0 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'First seen', 'metrixwire-apm' ); ?></th>
					<td><?php echo esc_html( View::datetime( isset( $issue['firstSeenAt'] ) ? (string) $issue['firstSeenAt'] : null ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last seen', 'metrixwire-apm' ); ?></th>
					<td><?php echo esc_html( View::datetime( isset( $issue['lastSeenAt'] ) ? (string) $issue['lastSeenAt'] : null ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="mw-grid mw-grid--split">
		<div class="mw-card">
			<h2><?php esc_html_e( 'What is happening', 'metrixwire-apm' ); ?></h2>
			<p><?php echo esc_html( (string) ( $issue['description'] ?? '' ) ); ?></p>
		</div>
		<div class="mw-card mw-card--suggestion">
			<h2><?php esc_html_e( 'Suggested fix', 'metrixwire-apm' ); ?></h2>
			<p><?php echo esc_html( (string) ( $issue['suggestion'] ?? '' ) ); ?></p>
		</div>
	</div>

	<?php if ( null !== $metrixwire_apm_trace ) : ?>
		<div class="mw-card">
			<h2><?php esc_html_e( 'Sample request', 'metrixwire-apm' ); ?></h2>
			<p class="mw-muted">
				<code><?php echo esc_html( (string) ( $metrixwire_apm_trace['route'] ?? '' ) ); ?></code>
				<span class="mw-strip__sep">·</span>
				<?php
				printf(
					/* translators: %s: request duration in milliseconds. */
					esc_html__( '%s ms', 'metrixwire-apm' ),
					esc_html( number_format_i18n( (int) ( $metrixwire_apm_trace['durationMs'] ?? 0 ) ) )
				);
				?>
				<span class="mw-strip__sep">·</span>
				<?php echo esc_html( View::datetime( isset( $metrixwire_apm_trace['startedAt'] ) ? (string) $metrixwire_apm_trace['startedAt'] : null ) ); ?>
			</p>

			<?php if ( empty( $metrixwire_apm_spans ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'This trace has no spans. Enable SAVEQUERIES to capture each database query as its own span.', 'metrixwire-apm' ); ?>
				</p>
			<?php else : ?>
				<ul class="mw-waterfall">
					<?php foreach ( $metrixwire_apm_spans as $metrixwire_apm_span ) : ?>
						<?php $metrixwire_apm_ms = (float) ( $metrixwire_apm_span['durationMs'] ?? 0 ); ?>
						<li>
							<span class="mw-waterfall__type"><?php echo esc_html( View::humanize( (string) ( $metrixwire_apm_span['type'] ?? '' ) ) ); ?></span>
							<span class="mw-waterfall__bar">
								<span class="mw-waterfall__fill" style="width:<?php echo esc_attr( (string) round( ( $metrixwire_apm_ms / $metrixwire_apm_slowest ) * 100, 2 ) ); ?>%"></span>
							</span>
							<span class="mw-waterfall__ms"><?php echo esc_html( number_format_i18n( $metrixwire_apm_ms, 1 ) ); ?> ms</span>
							<code class="mw-waterfall__desc"><?php echo esc_html( (string) ( $metrixwire_apm_span['description'] ?? '' ) ); ?></code>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
