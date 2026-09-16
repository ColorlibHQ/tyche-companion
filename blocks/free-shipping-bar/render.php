<?php
/**
 * Free shipping progress bar.
 *
 * Renders nothing when no shipping zone offers free shipping by spend, so a
 * store without a threshold never shows a bar that cannot fill.
 *
 * @package TycheCompanion
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$tyche_rule = tyche_companion_free_shipping_rule();
$tyche_is_preview = ! $tyche_rule && wp_is_serving_rest_request() && current_user_can( 'edit_theme_options' );

if ( ! $tyche_rule && ! $tyche_is_preview ) {
	return;
}

if ( $tyche_is_preview ) {
	// The block renderer runs without a cart. Show the shape with a note.
	printf(
		'<div %s><p class="tyche-fsb__message">%s</p><div class="tyche-fsb__track"><span class="tyche-fsb__fill" style="width:60%%"></span></div></div>',
		get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__( 'Free shipping progress appears here once a shipping zone has a Free shipping method with a minimum order amount.', 'tyche-companion' )
	);
	return;
}

$tyche_progress = tyche_companion_free_shipping_progress();

/* translators: %s: amount still to spend, for example $21.00. */
$tyche_remaining_text = __( 'Spend %s more for free shipping', 'tyche-companion' );
$tyche_reached_text   = __( 'You have unlocked free shipping', 'tyche-companion' );

$tyche_message = $tyche_progress['reached']
	? esc_html( $tyche_reached_text )
	: sprintf( esc_html( $tyche_remaining_text ), '<strong>' . wp_kses_post( wc_price( $tyche_progress['remaining'] ) ) . '</strong>' );

$tyche_wrapper = get_block_wrapper_attributes(
	array(
		'class'                 => $tyche_progress['reached'] ? 'is-reached' : '',
		'data-min'              => wc_format_decimal( $tyche_rule['min'] ),
		'data-ignore-discounts' => $tyche_rule['ignore_discounts'] ? '1' : '0',
		'data-incl-tax'         => WC()->cart->display_prices_including_tax() ? '1' : '0',
		'data-cart-url'         => esc_url_raw( rest_url( 'wc/store/v1/cart' ) ),
		'data-text-remaining'   => $tyche_remaining_text,
		'data-text-reached'     => $tyche_reached_text,
	)
);
?>
<div <?php echo $tyche_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="tyche-fsb__message" aria-live="polite"><?php echo $tyche_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<div class="tyche-fsb__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $tyche_progress['percent'] ); ?>" aria-label="<?php esc_attr_e( 'Progress towards free shipping', 'tyche-companion' ); ?>">
		<span class="tyche-fsb__fill" style="width:<?php echo esc_attr( $tyche_progress['percent'] ); ?>%"></span>
	</div>
</div>
