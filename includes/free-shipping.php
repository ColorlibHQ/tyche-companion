<?php
/**
 * How far the cart is from free shipping.
 *
 * Everything comes from WooCommerce's own settings: the free shipping method on
 * the shipping zone that matches the customer, its minimum amount, and whether
 * that minimum counts discounts. There is no threshold setting in this plugin,
 * so the bar can never disagree with what checkout actually charges.
 *
 * @package TycheCompanion
 */

defined( 'ABSPATH' ) || exit;

/**
 * The free shipping rule that applies to the current customer, if any.
 *
 * Only methods that can be unlocked by spending qualify: `min_amount` and
 * `either`. A method that also needs a coupon (`both`) would make the bar promise
 * something spending alone cannot deliver.
 *
 * @return array|null { min: float, ignore_discounts: bool }
 */
function tyche_companion_free_shipping_rule() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! wc_shipping_enabled() ) {
		return null;
	}

	$packages = WC()->cart->get_shipping_packages();
	$package  = $packages ? reset( $packages ) : null;

	if ( ! $package ) {
		$customer = WC()->customer;
		$package  = array(
			'destination' => array(
				'country'  => $customer ? $customer->get_shipping_country() : '',
				'state'    => $customer ? $customer->get_shipping_state() : '',
				'postcode' => $customer ? $customer->get_shipping_postcode() : '',
			),
		);
	}

	$zone = WC_Shipping_Zones::get_zone_matching_package( $package );

	foreach ( $zone->get_shipping_methods( true ) as $method ) {
		if ( 'free_shipping' !== $method->id || ! in_array( $method->requires, array( 'min_amount', 'either' ), true ) ) {
			continue;
		}

		$min = (float) $method->min_amount;
		if ( $min > 0 ) {
			return array(
				'min'              => $min,
				'ignore_discounts' => 'yes' === $method->ignore_discounts,
			);
		}
	}

	return null;
}

/**
 * Progress towards free shipping, computed the way WooCommerce decides it.
 *
 * Mirrors WC_Shipping_Free_Shipping::is_available(): the displayed subtotal,
 * less discounts unless the method ignores them, rounded to the store's decimals.
 *
 * @return array|null { min, total, remaining, percent, reached }
 */
function tyche_companion_free_shipping_progress() {
	$rule = tyche_companion_free_shipping_rule();
	if ( ! $rule ) {
		return null;
	}

	$cart  = WC()->cart;
	$total = (float) $cart->get_displayed_subtotal();

	if ( ! $rule['ignore_discounts'] ) {
		$total -= (float) $cart->get_discount_total();
		if ( $cart->display_prices_including_tax() ) {
			$total -= (float) $cart->get_discount_tax();
		}
	}

	$total     = round( $total, wc_get_price_decimals() );
	$remaining = max( 0, $rule['min'] - $total );

	return array(
		'min'       => $rule['min'],
		'total'     => $total,
		'remaining' => $remaining,
		'percent'   => min( 100, (int) floor( $total / $rule['min'] * 100 ) ),
		'reached'   => $remaining <= 0,
	);
}
