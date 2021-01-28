<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFOCU_Paylike_Gateway_Credit_Cards
 */
class WFOCU_Paylike_Gateway_Credit_Cards extends WFOCU_Gateway {
	public $key = 'paylike';
	public $token = false;
	protected static $ins = null;

	/**
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Holds an instance of the Paylike client
	 *
	 * @var $paylike_client \Paylike\Paylike
	 */
	public $paylike_client;

	/**
	 * Secret API Key.
	 * @var string
	 */
	private $secret_key = '';

	/**
	 * WFOCU_Paylike_Gateway_Credit_Cards constructor.
	 */
	public function __construct() {
		parent::__construct();

		$options          = get_option( 'woocommerce_paylike_settings', array() );
		$this->capture    = 'capture';
		$secret_key       = isset( $options['secret_key'] ) ? $options['secret_key'] : '';
		$test_secret_key  = isset( $options['test_secret_key'] ) ? $options['test_secret_key'] : '';
		$this->secret_key = ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) ? $test_secret_key : $secret_key;
		$this->secret_key = apply_filters( 'paylike_secret_key', $this->secret_key );

		if ( ! empty( $this->secret_key ) ) {
			$this->paylike_client = new Paylike\Paylike( $this->secret_key );
		}
		$this->refund_supported = true;

		add_filter( 'wfocu_subscriptions_get_supported_gateways', array( $this, 'enable_subscription_upsell_support' ), 10, 1 );
		add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_paylike_payment_token_to_subscription' ), 10, 3 );
	}

	/**
	 * @return null|WFOCU_Paylike_Gateway_Credit_Cards
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	/**
	 * Check if token is saved by the gateway
	 * @param WC_Order $order
	 *
	 * @return bool|true
	 */
	public function has_token( $order ) {
		$this->token = $this->get_token( $order );

		if ( ! empty( $this->token ) && ( $this->get_key() === $order->get_payment_method() ) ) {
			return true;
		}
		WFOCU_Core()->log->log( 'PayLike: Token ( Paylike transaction id) is missing or invalid gatway. ' . $this->token );

		return false;
	}

	public function get_token( $order ) {
		$this->token = empty( $this->token ) ? $order->get_meta( '_paylike_transaction_id', true ) : $this->token;
		if ( ! empty( $this->token ) ) {
			return $this->token;
		}

		$order_id    = WFOCU_WC_Compatibility::get_order_id( $order );
		$this->token = get_post_meta( $order_id, '_paylike_transaction_id', true );

		return $this->token;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return true
	 *
	 */
	public function process_charge( $order ) {
		$is_successful = false;
		$get_package   = WFOCU_Core()->data->get( '_upsell_package' );
		$get_offer_id  = WFOCU_Core()->data->get( 'current_offer' );

		$amount = isset( $get_package['total'] ) ? $get_package['total'] : 0;

		WFOCU_Core()->log->log( "Paylike: creating new transaction for offer id: $get_offer_id" );

		$new_transaction = $this->create_new_transaction( $this->token, $order, $amount );

		WFOCU_Core()->log->log( "Paylike: created new transaction for offer id: $get_offer_id: " . print_r( $new_transaction, true ) );

		if ( ! is_wp_error( $new_transaction ) ) {
			$result = $this->handle_payment( $new_transaction, $order, $amount );
			WFOCU_Core()->log->log( "Paylike: created result for new transaction: " . print_r( $result, true ) );
			if ( is_array( $result ) && $result['successful'] === true ) {
				WFOCU_Core()->data->set( '_transaction_id', $result['id'] );
				$is_successful = true;
			}
		}

		return $this->handle_result( $is_successful );
	}

	/**
	 * @param $entity_id
	 * @param $renewal_order
	 * @param $amount
	 *
	 * @return string|WP_Error
	 */
	protected function create_new_transaction( $entity_id, $renewal_order, $amount ) {
		$get_offer_id = WFOCU_Core()->data->get( 'current_offer' );

		$merchant_id = $this->get_wc_gateway()->get_global_merchant_id();
		if ( is_wp_error( $merchant_id ) ) {
			return $merchant_id;
		}
		// create a new transaction by card or transaction.
		$data = array(
			'amount'        => $this->get_paylike_amount( $amount, dk_get_order_currency( $renewal_order ) ),
			'currency'      => dk_get_order_currency( $renewal_order ),
			'custom'        => array(
				'email' => $renewal_order->get_billing_email(),
			),
			'transactionId' => $entity_id,
		);

		WFOCU_Core()->log->log( "Info: Starting to create a transaction {$data['amount']} in {$data['currency']} for {$merchant_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

		try {
			$new_transaction = $this->paylike_client->transactions()->create( $merchant_id, $data );
		} catch ( \Paylike\Exception\ApiException $exception ) {

			WFOCU_Core()->log->log( 'Payment for offer ' . print_r( $get_offer_id, true ) . ' using paylike credit card is failed. Reason is below: ' . print_r( $exception->getMessage(), true ) );

			return new WP_Error( 'paylike_error', __( 'There was a problem creating the transaction!.', 'woocommerce-gateway-paylike' ) );
		}

		return $new_transaction;
	}

	/**
	 * @param $transaction_id
	 * @param $order
	 * @param bool $amount
	 *
	 * @return array
	 */
	protected function handle_payment( $transaction_id, $order, $amount = false ) {
		$get_offer_id = WFOCU_Core()->data->get( 'current_offer' );
		$order_id     = get_woo_id( $order );
		WFOCU_Core()->log->log( '------------- Start payment --------------' . PHP_EOL . "Info: Begin processing payment for order $order_id for the amount of " . $amount . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

		$data = array(
			'amount'   => $this->get_paylike_amount( $amount, dk_get_order_currency( $order ) ),
			'currency' => dk_get_order_currency( $order ),

		);
		WFOCU_Core()->log->log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		try {
			$result = $this->paylike_client->transactions()->capture( $transaction_id, $data );
			$this->handle_capture_result( $result, $order, $amount );
		} catch ( \Paylike\Exception\ApiException $exception ) {
			WFOCU_Core()->log->log( 'Issue: Capture Failed! ' . print_r( $get_offer_id, true ) . ' . Reason is below: ' . print_r( $exception->getMessage(), true ) );

		}

		return $result;
	}

	/**
	 * @param $transaction
	 * @param $order
	 * @param int $amount
	 */
	function handle_capture_result( $transaction, $order, $amount = 0 ) {
		$result = $this->parse_api_transaction_response( $transaction, $order, $amount );
		if ( is_wp_error( $result ) ) {
			WFOCU_Core()->log->log( 'Issue: Capture has failed, the result from the verification threw an wp error:' . $result->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

		} else {

			WFOCU_Core()->log->log( 'Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->payment_complete();
		}
	}

	/**
	 * @param $transaction
	 * @param null $order
	 * @param bool $amount
	 *
	 * @return WP_Error
	 */
	protected function parse_api_transaction_response( $transaction, $order = null, $amount = false ) {
		if ( ! $this->is_transaction_successful( $transaction, $order, $amount ) ) {
			$error_message = WC_Gateway_Paylike::get_response_error( $transaction );
			WFOCU_Core()->log->log( 'Transaction with error:' . json_encode( $transaction ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message );
		}

		return $transaction;
	}

	/**
	 * @param $transaction
	 * @param null $order
	 * @param bool $amount
	 *
	 * @return bool
	 */
	protected function is_transaction_successful( $transaction, $order = null, $amount = false ) {
		// if we don't have the order, we only check the successful status.
		if ( ! $order ) {
			return 1 === $transaction['successful'];
		}
		// we need to overwrite the amount in the case of a subscription.

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		$match_currency = dk_get_order_currency( $order ) == $transaction['currency'];
		$match_amount   = $this->get_paylike_amount( $amount, dk_get_order_currency( $order ) ) == $transaction['amount'];

		return ( 1 == $transaction['successful'] && $match_currency && $match_amount );
	}

	/**
	 * @param $total
	 * @param string $currency
	 *
	 * @return false|float
	 */
	public function get_paylike_amount( $total, $currency = '' ) {
		if ( empty( $currency ) ) {
			$currency = get_woocommerce_currency();
		}
		$multiplier = get_paylike_currency_multiplier( $currency );
		$amount     = ceil( $total * $multiplier ); // round to make sure we are always minor units.
		if ( function_exists( 'bcmul' ) ) {
			$amount = ceil( bcmul( $total, $multiplier ) );
		}

		return $amount;
	}

	/**
	 * @param $order
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund_offer( $order ) {
		$refund_data    = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$order_id       = get_woo_id( $order );
		$transaction_id = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
		$amount         = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
		$captured       = get_post_meta( $order_id, '_paylike_transaction_captured', true );

		$data     = array();
		$currency = dk_get_order_currency( $order );

		if ( ! is_null( $amount ) ) {
			$data['amount'] = $this->get_paylike_amount( $amount, $currency );
		}

		if ( 'yes' === $captured ) {
			WFOCU_Core()->log->log( "Info: Starting to refund {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			try {
				$result = $this->paylike_client->transactions()->refund( $transaction_id, $data );
				$this->handle_refund_result( $order, $result, $captured );
			} catch ( \Paylike\Exception\ApiException $exception ) {
				WFOCU_Core()->log->log( 'Issue: Refund has failed!:' . print_r( $exception->getMessage(), true ) );
				$error_message = WC_Gateway_Paylike::get_response_error( $exception->getJsonBody() );
				if ( ! $error_message ) {
					$error_message = 'There has been an problem with the refund. Refresh the order to see more details';
				}

				return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message );
			}
		}

		return true;

	}

	/**
	 * @param $order
	 * @param $transaction
	 * @param $captured
	 *
	 * @return bool
	 */
	function handle_refund_result( $order, $transaction, $captured ) {

		if ( 1 === $transaction['successful'] ) {
			if ( 'yes' === $captured ) {
				$refunded_amount = $transaction['refundedAmount'];
			} else {
				$refunded_amount = $transaction['voidedAmount'];
			}

			$refund_message = __( 'Paylike transaction refunded.', 'woocommerce-gateway-paylike' ) . PHP_EOL . __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $transaction['id'] . PHP_EOL . __( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $refunded_amount, $transaction['currency'] ) . PHP_EOL . __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $transaction['created'];
			$order->add_order_note( $refund_message );
			WFOCU_Core()->log->log( 'Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return true;
		} else {
			$error_message = WC_Gateway_Paylike::get_response_error( $transaction );
			$order->add_order_note( __( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL . __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message );
			WFOCU_Core()->log->log( 'Issue: Refund has failed there has been an issue with the transaction.' . $error_message . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return false;
		}
	}

	/**
	 * @param $amount_in_cents
	 * @param string $currency
	 *
	 * @return string
	 */
	function real_amount( $amount_in_cents, $currency = '' ) {
		return strip_tags( wc_price( $amount_in_cents / get_paylike_currency_multiplier( $currency ), array(
			'ex_tax_label' => false,
			'currency'     => $currency,
		) ) );
	}

	/**
	 * Adding this gateway as Subscriptions upsell supported gateway
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function enable_subscription_upsell_support( $gateways ) {
		if ( is_array( $gateways ) ) {
			$gateways[] = $this->get_key();
		}

		return $gateways;
	}

	/**
	 * @param WC_Subscription $subscription
	 * @param $key
	 * @param WC_Order $order
	 */
	public function save_paylike_payment_token_to_subscription( $subscription, $key, $order ) {

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$get_token = $order->get_meta( '_paylike_transaction_id', true );
		if ( ! empty( $get_token ) ) {
			$subscription->update_meta_data( '_paylike_transaction_id', $get_token );
			$subscription->save();
		}
	}
}
