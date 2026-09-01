<?php
/**
 * Presentation helpers shared by the admin screens.
 *
 * @package MetrixWire\APM
 */

declare(strict_types=1);

namespace MetrixWire\APM;

defined( 'ABSPATH' ) || exit;

/**
 * Formatting and small chart rendering for the admin views.
 *
 * The charts are hand-written SVG rather than a charting library: WordPress.org
 * won't take a plugin that pulls scripts from a CDN, and bundling a minified
 * library means shipping its source too — for two small charts, markup is the
 * cheaper answer, and it renders with JavaScript disabled.
 */
final class View {

	/** Colour per severity, matching the dashboard's own palette. */
	private const SEVERITY_COLORS = array(
		'high'   => '#b32d2e',
		'medium' => '#bd8600',
		'low'    => '#2271b1',
	);

	/** Translated severity label. */
	public static function severity_label( string $severity ): string {
		return match ( $severity ) {
			'high'   => __( 'High', 'metrixwire-apm' ),
			'medium' => __( 'Medium', 'metrixwire-apm' ),
			'low'    => __( 'Low', 'metrixwire-apm' ),
			default  => $severity,
		};
	}

	/** Coloured severity pill. */
	public static function severity_badge( string $severity ): string {
		return sprintf(
			'<span class="mw-badge mw-badge--%s">%s</span>',
			esc_attr( $severity ),
			esc_html( self::severity_label( $severity ) )
		);
	}

	/** Detector type as a readable phrase: `n_plus_one_query` → `N plus one query`. */
	public static function humanize( string $value ): string {
		return ucfirst( str_replace( '_', ' ', $value ) );
	}

	/**
	 * "3 minutes ago", or an em dash when the timestamp is missing.
	 *
	 * Times arrive as ISO-8601 UTC from the API; `human_time_diff` compares
	 * against the site's current time, so both sides must be UTC epochs.
	 */
	public static function ago( ?string $iso ): string {
		if ( empty( $iso ) ) {
			return '—';
		}

		$timestamp = strtotime( $iso );

		if ( false === $timestamp ) {
			return '—';
		}

		return sprintf(
			/* translators: %s: human-readable time difference, e.g. "5 mins". */
			__( '%s ago', 'metrixwire-apm' ),
			human_time_diff( $timestamp, time() )
		);
	}

	/** Absolute date/time in the site's timezone, for tooltips. */
	public static function datetime( ?string $iso ): string {
		if ( empty( $iso ) ) {
			return '';
		}

		$timestamp = strtotime( $iso );

		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/** Signed change between two counts, as a short "+3" / "−2" / "no change". */
	public static function trend( int $current, int $previous ): string {
		$delta = $current - $previous;

		if ( 0 === $delta ) {
			return __( 'no change vs previous 7 days', 'metrixwire-apm' );
		}

		return sprintf(
			/* translators: %s: signed difference against the previous 7-day window, e.g. "+4" or "−2". */
			__( '%s vs previous 7 days', 'metrixwire-apm' ),
			( $delta > 0 ? '+' : '−' ) . number_format_i18n( abs( $delta ) )
		);
	}

	/**
	 * Stacked column chart of issues per day, as inline SVG.
	 *
	 * @param array<int, array<string, mixed>> $days Rows of `{ date, high, medium, low }`.
	 */
	public static function day_chart( array $days ): string {
		if ( empty( $days ) ) {
			return '';
		}

		$max = 0;
		foreach ( $days as $day ) {
			$max = max( $max, (int) ( $day['high'] ?? 0 ) + (int) ( $day['medium'] ?? 0 ) + (int) ( $day['low'] ?? 0 ) );
		}
		// An all-zero week still needs a baseline to draw against.
		$max = max( $max, 1 );

		$width      = 100 / max( count( $days ), 1 );
		$bar_width  = $width * 0.56;
		$bar_offset = ( $width - $bar_width ) / 2;
		$height     = 120;

		$svg = sprintf(
			'<svg class="mw-chart" viewBox="0 0 100 %1$d" preserveAspectRatio="none" role="img" aria-label="%2$s">',
			$height,
			esc_attr__( 'Issues per day over the last 7 days', 'metrixwire-apm' )
		);

		foreach ( array_values( $days ) as $index => $day ) {
			$x       = ( $index * $width ) + $bar_offset;
			$stacked = 0.0;

			foreach ( array( 'low', 'medium', 'high' ) as $severity ) {
				$count = (int) ( $day[ $severity ] ?? 0 );

				if ( 0 === $count ) {
					continue;
				}

				$bar_height = ( $count / $max ) * ( $height - 4 );
				$stacked   += $bar_height;

				$svg .= sprintf(
					'<rect x="%1$s" y="%2$s" width="%3$s" height="%4$s" fill="%5$s"><title>%6$s</title></rect>',
					esc_attr( (string) round( $x, 2 ) ),
					esc_attr( (string) round( $height - $stacked, 2 ) ),
					esc_attr( (string) round( $bar_width, 2 ) ),
					esc_attr( (string) round( $bar_height, 2 ) ),
					esc_attr( self::SEVERITY_COLORS[ $severity ] ),
					esc_html(
						sprintf(
							/* translators: 1: number of issues, 2: severity label, 3: date. */
							__( '%1$s %2$s on %3$s', 'metrixwire-apm' ),
							number_format_i18n( $count ),
							self::severity_label( $severity ),
							(string) ( $day['date'] ?? '' )
						)
					)
				);
			}
		}

		return $svg . '</svg>';
	}

	/** Day labels under the chart, one per column. */
	public static function day_labels( array $days ): string {
		$out = '';

		foreach ( $days as $day ) {
			$timestamp = isset( $day['date'] ) ? strtotime( (string) $day['date'] ) : false;
			$out      .= '<span>' . esc_html( false !== $timestamp ? wp_date( 'D', $timestamp ) : '' ) . '</span>';
		}

		return $out;
	}

	/**
	 * Horizontal usage meter.
	 *
	 * @param int      $used  Amount consumed.
	 * @param int|null $limit Allowance, or null for unlimited.
	 */
	public static function meter( int $used, ?int $limit ): string {
		if ( null === $limit || $limit <= 0 ) {
			return '<div class="mw-meter"><div class="mw-meter__fill" style="width:0%"></div></div>';
		}

		$percent = min( 100, (int) round( ( $used / $limit ) * 100 ) );
		$state   = $percent >= 100 ? 'over' : ( $percent >= 80 ? 'warn' : 'ok' );

		return sprintf(
			'<div class="mw-meter"><div class="mw-meter__fill mw-meter__fill--%1$s" style="width:%2$d%%"></div></div>',
			esc_attr( $state ),
			$percent
		);
	}

	/** Admin URL for an issue's detail screen. */
	public static function issue_url( string $issue_id ): string {
		return Admin::url( 'issues', array( 'issue' => $issue_id ) );
	}

	/**
	 * Render a WP_Error as an admin notice, or nothing when there is no error.
	 *
	 * @param mixed $result Whatever a Client call returned.
	 */
	public static function maybe_error( $result ): bool {
		if ( ! is_wp_error( $result ) ) {
			return false;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $result->get_error_message() )
		);

		return true;
	}
}
