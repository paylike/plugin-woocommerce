<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Gateway_Paylike_Addons extends WC_Gateway_Paylike {


	public function __construct() {
		parent::__construct();
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
				$this,
				'scheduled_subscription_payment',
			), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
				$this,
				'update_failing_payment_method',
			), 10, 2 );
			// display the credit card used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_payment_method', array(
				$this,
				'maybe_render_subscription_payment_method',
			), 10, 2 );
			// allow store managers to manually set Paylike as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array(
				$this,
				'add_subscription_payment_meta',
			), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array(
				$this,
				'validate_subscription_payment_meta',
			), 10, 2 );
		}
	}


	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$result = $this->process_subscription_payment( $renewal_order, $amount_to_charge );
		if ( is_wp_error( $result ) ) {
			/* translators: %1$s is replaced with the error message */
			$renewal_order->update_status( 'failed', sprintf( __( 'Paylike Transaction Failed (%s)', 'woocommerce-gateway-paylike' ), $result->get_error_message() ) );
		}
	}


	/**
	 * Process payment for subscription
	 * we mimic recurring payments, using the last
	 * transaction id - see @https://github.com/paylike/api-docs#recurring-payments
	 *
	 * @param WC_Order $order
	 * the renewal order created from the initial order
	 * only containing the subscription product we are renewing
	 * @param int $amount
	 *
	 * @return bool|int|mixed|null|WP_Error
	 */
	public function process_subscription_payment( $order = null, $amount = 0 ) {
		if ( 0 == $amount ) {
			$order->payment_complete();

			return true;
		}
		// get last transaction id used
		$last_transaction_id = $this->get_transaction_id( $order );
		$last_card_id        = $this->get_card_id( $order );
		if ( ! $last_transaction_id && ! $last_card_id ) {
			if ( ! $last_transaction_id ) {
				return new WP_Error( 'paylike_error', __( 'Neither Transaction ID nor Card ID was found', 'woocommerce-gateway-paylike' ) );
			}
		}
		$order_id = get_woo_id( $order );
		WC_Paylike::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );
		// create a new transaction from a previous one, or a card
		if ( $last_card_id ) { // card can be added after a subscription, should get checked first
			$new_transaction = $this->create_new_transaction( $last_card_id, $order, $amount, $type = 'card' );
		} else {
			$new_transaction = $this->create_new_transaction( $last_transaction_id, $order, $amount );
		}
		if ( is_wp_error( $new_transaction ) ) {
			return $new_transaction;
		}

		return $this->handle_payment( $new_transaction['transaction']['id'], $order, $amount );
	}

	/**
	 * Creates a new transaction based on a previous one
	 * used to simulate recurring payments
	 * see @https://github.com/paylike/api-docs#recurring-payments
	 *
	 * @param $entity_id
	 * @param WC_Order $renewal_order
	 * @param $amount
	 * @param string $type
	 *
	 * @return int|mixed|null
	 */
	protected function create_new_transaction( $entity_id, $renewal_order, $amount, $type = 'transaction' ) {
		$merchant_id = $this->get_merchant_id( $entity_id, $type );
		if ( is_wp_error( $merchant_id ) ) {
			return $merchant_id;
		}
		// create a new transaction by card or transaction
		$data = array(
			'amount'   => $this->get_paylike_amount( $amount, dk_get_order_currency( $renewal_order ) ),
			'currency' => dk_get_order_currency( $renewal_order ),
			'custom'   => array(
				'email' => $renewal_order->billing_email,
			),
		);
		if ( 'card' === $type ) {
			$data['cardId'] = $entity_id;
		} else {
			$data['transactionId'] = $entity_id;
		}
		WC_Paylike::log( "Info: Starting to create a transaction {$data['amount']} in {$data['currency']} for {$merchant_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		$new_transaction = Paylike\Transaction::create( $merchant_id, $data );
		// check for errors
		if ( ! $new_transaction ) {
			WC_Paylike::log( 'Fatal Error: Creation of transaction has failed, the result from the api wrapper was false' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return new WP_Error( 'paylike_error', __( 'cURL request failed.', 'woocommerce-gateway-paylike' ) );
		} elseif ( ! isset( $new_transaction['transaction'] ) || ! isset( $new_transaction['transaction']['id'] ) ) {
			WC_Paylike::log( 'Fatal Error: Creation of transaction has failed, the transaction failed' . PHP_EOL . json_encode( $new_transaction ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $this->get_response_error( $new_transaction ) );
		}

		return $new_transaction;
	}

	/**
	 * Gets merchant id
	 *
	 * @param $entity_id - card id / transaction id
	 * @param string $type - 'card' or 'transaction'
	 *
	 * @return bool|int|mixed|null|WP_Error
	 */
	private function get_merchant_id( $entity_id, $type = 'transaction' ) {
		if ( 'card' == $type ) {
			// try to get the card
			WC_Paylike::log( "Info: Attempting to fetch the card {$entity_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$entity = Paylike\Card::fetch( $entity_id );
			$entity = $this->parse_api_card_response( $entity );
			if ( is_wp_error( $entity ) ) {
				WC_Paylike::log( 'Fatal Error: Card fetching has failed, the result from the verification threw and wp error:' . $entity->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

				return $entity;
			}
			$entity = $entity['card'];
		} else {
			// try to get the transaction
			WC_Paylike::log( "Info: Attempting to fetch the transaction {$entity_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$entity = Paylike\Transaction::fetch( $entity_id );
			$entity = $this->parse_api_transaction_response( $entity );
			if ( is_wp_error( $entity ) ) {
				WC_Paylike::log( 'Fatal Error: Transaction fetching has failed, the result from the verification threw and wp error:' . $entity->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

				return $entity;
			}
			$entity = $entity['transaction'];
		}
		if ( ! $entity['merchantId'] ) {
			WC_Paylike::log( 'Fatal Error: The merchant id is missing:' . json_encode( $entity ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return new WP_Error( 'paylike_error', __( 'Merchant ID not found', 'woocommerce-gateway-paylike' ) );
		}
		WC_Paylike::log( 'Success: The merchant was found' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

		return $entity['merchantId'];
	}

	/**
	 * Update the paylike_transaction_id for a subscription after using Paylike to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 * @access public
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( get_woo_id( $subscription ), '_paylike_transaction_id', $renewal_order->paylike_transaction_id );
		update_post_meta( get_woo_id( $subscription ), '_paylike_card_id', $renewal_order->paylike_card_id );
	}

	/**
	 * Don't transfer Paylike transaction id meta to resubscribe orders.
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( get_woo_id( $resubscribe_order ), '_paylike_transaction_id' );
		delete_post_meta( get_woo_id( $resubscribe_order ), '_paylike_card_id' );
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods
		if ( $this->id !== $subscription->payment_method || ! $subscription->customer_user ) {
			return $payment_method_to_display;
		}
		$transaction_id = get_post_meta( get_woo_id( $subscription ), '_paylike_transaction_id', true );
		// add more details, if we can get the card
		$result = Paylike\Transaction::fetch( $transaction_id );
		if (
			$result &&
			1 == $result['transaction']['successful'] &&
			$result['transaction']['card']
		) {
			$card                      = $result['transaction']['card'];
			/* translators: %1$s is replaced with card type, %2$s is replaced with last4 digits and %3$s is replaced with the card id */
			$payment_method_to_display = sprintf( __( 'Via %s card ending in %s (%s)', 'woocommerce-gateway-paylike' ), ucfirst( $card['scheme'] ), $card['last4'], ucfirst( $this->id ) );
		}

		return $payment_method_to_display;
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_aylike_transaction_id' => array(
					'value' => get_post_meta( get_woo_id( $subscription ), '_paylike_transaction_id', true ),
					'label' => 'A previous transaction ID',
				),
				'paylike_card_id'        => array(
					'value' => get_post_meta( get_woo_id( $subscription ), '_paylike_card_id', true ),
					'label' => 'A previous card ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param $payment_method_id - the ID of the payment method to validate
	 * @param $payment_meta - associative array of meta data required for automatic payments
	 *
	 * @return array
	 * @throws Exception
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if ( ! isset( $payment_meta['post_meta']['_paylike_transaction_id']['value'] ) || empty( $payment_meta['post_meta']['_paylike_transaction_id']['value'] ) ) {
				if ( ! isset( $payment_meta['post_meta']['_paylike_card_id']['value'] ) || empty( $payment_meta['post_meta']['_paylike_card_id']['value'] ) ) {
					throw new Exception( 'A "_paylike_transaction_id" value is required.' );
				}
			}
		}
	}

	/**
	 * Saves the transaction id on the order and subscription
	 *
	 * @param $result
	 * @param WC_Order $order
	 */
	protected function save_transaction_id( $result, $order ) {
		parent::save_transaction_id( $result, $order );
		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( get_woo_id( $order ) );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( get_woo_id( $order ) );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			update_post_meta( $subscription->id, '_paylike_transaction_id', $result['transaction']['id'] );
		}
	}

	/**
	 * Saves the card id on the order and subscription
	 *
	 * @param $card_id
	 * @param WC_Order $order
	 */
	protected function save_card_id( $card_id, $order ) {
		parent::save_card_id( $card_id, $order );
		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( get_woo_id( $order ) );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( get_woo_id( $order ) );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			update_post_meta( $subscription->id, '_paylike_card_id', $card_id );
		}
	}
}