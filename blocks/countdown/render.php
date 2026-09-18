<?php
/**
 * The countdown.
 *
 * Rendered here as well as ticked in the browser, so the first paint already
 * shows the right numbers rather than four zeroes that jump a moment later, and
 * so it still says something useful with JavaScript switched off.
 *
 * The digits are hidden from screen readers -- a number that changes every
 * second is unusable read aloud -- and the date itself is given as a sentence
 * instead.
 *
 * @package TycheCompanion
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$tyche_date = isset( $attributes['date'] ) ? trim( (string) $attributes['date'] ) : '';
if ( ! $tyche_date ) {
	return;
}

try {
	$tyche_deadline = new DateTimeImmutable( $tyche_date, wp_timezone() );
} catch ( Exception $e ) {
	return;
}

$tyche_seconds  = $tyche_deadline->getTimestamp() - time();
$tyche_finished = ! empty( $attributes['finished'] ) ? $attributes['finished'] : __( 'It is live now', 'tyche-companion' );
$tyche_labels   = empty( $attributes['showLabels'] ) ? false : true;

$tyche_wrapper = get_block_wrapper_attributes(
	array(
		'class'         => $tyche_seconds > 0 ? '' : 'is-finished',
		// Milliseconds since the epoch, so the browser counts down from the same
		// moment whatever its own clock is set to.
		'data-deadline' => $tyche_deadline->getTimestamp() * 1000,
		'data-finished' => $tyche_finished,
		'data-labels'   => $tyche_labels ? '1' : '0',
	)
);

$tyche_units = array(
	'days'    => array( (int) floor( $tyche_seconds / DAY_IN_SECONDS ), __( 'days', 'tyche-companion' ) ),
	'hours'   => array( (int) floor( ( $tyche_seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ), __( 'hrs', 'tyche-companion' ) ),
	'minutes' => array( (int) floor( ( $tyche_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ), __( 'min', 'tyche-companion' ) ),
	'seconds' => array( (int) ( $tyche_seconds % MINUTE_IN_SECONDS ), __( 'sec', 'tyche-companion' ) ),
);
?>
<div <?php echo $tyche_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $tyche_seconds > 0 ) : ?>
		<p class="screen-reader-text">
			<?php
			printf(
				/* translators: %s: the date and time something opens, already formatted. */
				esc_html__( 'Opens on %s', 'tyche-companion' ),
				esc_html( wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $tyche_deadline->getTimestamp() ) )
			);
			?>
		</p>
		<div class="tyche-countdown__units" aria-hidden="true">
			<?php foreach ( $tyche_units as $tyche_unit => $tyche_value ) : ?>
				<span class="tyche-countdown__unit">
					<b class="tyche-countdown__value" data-unit="<?php echo esc_attr( $tyche_unit ); ?>"><?php echo esc_html( str_pad( (string) max( 0, $tyche_value[0] ), 2, '0', STR_PAD_LEFT ) ); ?></b>
					<?php if ( $tyche_labels ) : ?>
						<i class="tyche-countdown__label"><?php echo esc_html( $tyche_value[1] ); ?></i>
					<?php endif; ?>
				</span>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="tyche-countdown__finished"><?php echo esc_html( $tyche_finished ); ?></p>
	<?php endif; ?>
</div>
