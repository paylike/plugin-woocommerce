<?php

class WoocommerceGatewayPaylikeTest extends WP_UnitTestCase {

	/** @test */
	public function test_plan_no_trial() {
		$product = $this->create_subscription_product( true, [
			'type'                => 'subscription',
			'subscription_period' => 'week',
			'subscription_price'  => 234,
			'regular_price'       => 234,
			'price'               => 234
		] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		$args = [];
		$args = PaylikeSubscriptionHelper::append_plan_argument( $args );
		$this->assertEquals( [
			'plan' => [
				'amount' => [
					'currency' => 'USD',
					'amount'   => 23400,
					'exponent' => 2,
				],
				'repeat' => [
					'interval' => [
						'unit'  => 'week',
						'value' => 1
					]
				]
			]
		], $args );
	}

	/** @test */
	public function test_force_unplanned() {
		$product = $this->create_subscription_product( true, [
			'type'                => 'subscription',
			'subscription_period' => 'week',
			'subscription_price'  => 234,
			'regular_price'       => 234,
			'price'               => 234
		] );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		$args = [];
		$args = PaylikeSubscriptionHelper::append_plan_argument( $args, true );
		$this->assertArrayNotHasKey( 'plan', $args );
		$this->assertEquals( [
			'unplanned' => [ 'merchant' => true ]
		], $args );
	}

	/**
	 * Create simple product.
	 *
	 * @param bool  $save Save or return object.
	 * @param array $props Properties to be set in the new product, as an associative array.
	 *
	 * @return WC_Product_Simple
	 * @since 2.3
	 */
	public function create_subscription_product( $save = true, $props = array() ) {
		$product = new WC_Product_Subscription();
		$default_props =
			array(
				'name'          => 'Dummy Product',
				'regular_price' => 10,
				'price'         => 10,
				'sku'           => 'DUMMY SKU',
				'manage_stock'  => false,
				'tax_status'    => 'taxable',
				'downloadable'  => false,
				'virtual'       => false,
				'stock_status'  => 'instock',
				'weight'        => '1.1',
			);

		$product->set_props( array_merge( $default_props, $props ) );


		$meta = [];
		$meta = $this->add_to_array_if_set( 'subscription_price', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_period', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_period_interval', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_sign_up_fee', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_length', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_trial_length', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_trial_period', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_limit', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_one_time_shipping', $props, $meta );
		$meta = $this->add_to_array_if_set( 'subscription_payment_sync_date', $props, $meta );

		foreach ( $meta as $key => $value ) {
			$product->add_meta_data( $key, $value );
		}

		if ( $save ) {
			$product->save();

			return wc_get_product( $product->get_id() );
		} else {
			return $product;
		}
	}

	private function add_to_array_if_set( $key, $from_array, $to_array ) {
		if ( isset( $from_array[ $key ] ) ) {
			$to_array[ '_' . $key ] = $from_array[ $key ];
		}

		return $to_array;
	}

}
