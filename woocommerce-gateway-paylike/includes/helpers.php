<?php

if ( ! function_exists( 'dk_get_locale' ) ) {

	function dk_get_locale() {
		$locale = get_locale();
		$norwegian_compatibility = false;
		if ( defined( 'PAYLIKE_NORWEGIAN_BOKMAL_COMPATIBILITY' ) ) {
			$norwegian_compatibility = PAYLIKE_NORWEGIAN_BOKMAL_COMPATIBILITY;
		}
		if ( in_array( $locale, array( 'nb_NO' ) ) && $norwegian_compatibility ) {
			$locale = 'no_NO';
		}

		return $locale;
	}
}

if ( ! function_exists( 'dk_order_contains_subscription' ) ) {
	/**
	 * @param WC_Order $order
	 *
	 * @return boolean
	 */
	function dk_order_contains_subscription( $order ) {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}
		foreach ( $order->get_items() as $item_id => $line_item ) {
			$product = $line_item->get_product();
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'dk_cart_contains_subscription' ) ) {
	/**
	 * @return boolean
	 */
	function dk_cart_contains_subscription() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		}

		return false;
	}
}