<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Paylike class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Paylike extends WC_Payment_Gateway {

	/**
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Show payment popup on the checkout action.
	 *
	 * @var bool
	 */
	public $checkout_mode;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access public key
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Allowed card types
	 *
	 * @var bool
	 */
	public $card_types;

	/**
	 * Compatibility mode, capture only from on hold to processing or completed, and not also from processing to completed if this is checked.
	 *
	 * @var bool
	 */
	public $compatibility_mode;

	/**
	 * Used to validate the public key.
	 *
	 * @var array
	 */
	public $validation_test_public_keys = array();

	/**
	 * Used to validate the live public key.
	 *
	 * @var array
	 */
	public $validation_live_public_keys = array();

	/**
	 * Holds an instance of the Paylike client
	 *
	 * @var $paylike_client \Paylike\Paylike
	 */
	public $paylike_client;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'paylike';
		$this->method_title       = __( 'Paylike', 'woocommerce-gateway-paylike' );
		$this->method_description = __( 'Paylike enables you to accept credit and debit cards on your WooCommerce platform. If you don\'t already have an account with Paylike, you can create it <a href="https://paylike.io/">here</a>. Need help with the setup? Read our documentation <a href="https://paylike.io/plugins/woocommerce/">here</a>.', 'woocommerce-gateway-paylike' );
		$this->supports           = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility.
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		// Get setting values.
		$this->title              = $this->get_option( 'title' );
		$this->popup_title        = $this->get_option( 'popup_title' );
		$this->description        = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->testmode           = 'yes' === $this->get_option( 'testmode' );
		$this->capture            = 'instant' === $this->get_option( 'capture', 'instant' );
		$this->checkout_mode      = 'before_order' === $this->get_option( 'checkout_mode', 'before_order' );
		$this->compatibility_mode = 'yes' === $this->get_option( 'compatibility_mode', 'yes' );
		$this->secret_key         = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->public_key         = $this->testmode ? $this->get_option( 'test_public_key' ) : $this->get_option( 'public_key' );
		$this->logging            = 'yes' === $this->get_option( 'logging' );
		$this->card_types         = $this->get_option( 'card_types' );
		$this->order_button_text  = __( 'Continue to payment', 'woocommerce-gateway-paylike' );
		if ( $this->testmode ) {
			/* translators: %s is replaced with the documentation link */
			$this->description .= PHP_EOL . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4100 0000 0000 0000 with any CVC and a valid expiration date. "<a href="%s">See Documentation</a>".', 'woocommerce-gateway-paylike' ), 'https://github.com/paylike/sdk' );
			$this->description = trim( $this->description );
		}
		if ( '' !== $this->secret_key ) {
			$this->paylike_client = new Paylike\Paylike( $this->secret_key );
		}
		// Hooks.
		if ( 'before_order' == $this->checkout_mode ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			$this->has_fields = true;
		}
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'return_handler' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options',
		) );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-paylike.php' );
	}

	/**
	 * @param $key
	 * @param $value
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function validate_capture_field( $key, $value ) {
		if ( $value == 'delayed' ) {
			return $value;
		}
		// value is instant so we need to check if the user is allowed to capture
		$can_capture = $this->can_user_capture();
		if ( is_wp_error( $can_capture ) ) {
			$error = __( 'The Paylike account used is not allowed to capture. Instant mode is not available.', 'woocommerce-gateway-paylike' );
			WC_Admin_Settings::add_error( $error );
			throw new Exception( $error );
		}

		return $value;
	}

	/**
	 * Validate the secret test key.
	 *
	 * @param string $key the name of the attribute.
	 * @param string $value the value of the input.
	 *
	 * @return string
	 * @throws Exception Thrown when there is a problem so that the value is not saved.
	 */
	public function validate_test_secret_key_field( $key, $value ) {

		if ( ! $value ) {
			return $value;
		}
		$paylike_client = new \Paylike\Paylike( $value );
		try {
			$identity = $paylike_client->apps()->fetch();
		} catch ( \Paylike\Exception\ApiException $exception ) {
			$error = __( "The private key doesn't seem to be valid", 'woocommerce-gateway-paylike' );
			WC_Admin_Settings::add_error( $error );
			WC_Paylike::handle_exceptions( null, $exception, $error );
			throw new Exception( $error );
		}
		try {
			$merchants = $paylike_client->merchants()->find( $identity['id'] );
			if ( $merchants ) {
				foreach ( $merchants as $merchant ) {
					if ( $merchant['test'] ) {
						$this->validation_test_public_keys[] = $merchant['key'];
					}
				}
			}
		} catch ( \Paylike\Exception\ApiException $exception ) {
			// we handle in the following statement
		}
		if ( empty( $this->validation_test_public_keys ) ) {
			$error = __( 'The test private key is not valid or set to live mode.', 'woocommerce-gateway-paylike' );
			WC_Admin_Settings::add_error( $error );
			throw new Exception( $error );
		}

		return $value;
	}

	/**
	 * Validate the test public key.
	 *
	 * @param string $key the name of the attribute.
	 * @param string $value the value of the input.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function validate_test_public_key_field( $key, $value ) {

		if ( ! $value ) {
			return $value;
		}
		if ( empty( $this->validation_test_public_keys ) ) {
			return $value;
		}
		if ( ! in_array( $value, $this->validation_test_public_keys ) ) {
			$error = __( 'The test public key doesn\'t seem to be valid', 'woocommerce-gateway-paylike' );
			WC_Admin_Settings::add_error( $error );
			throw new Exception( $error );
		}

		return $value;
	}

	/**
	 * Validate the secret live key.
	 *
	 * @param string $key the name of the attribute.
	 * @param string $value the value of the input.
	 *
	 * @return string
	 * @throws Exception
	 */
	public function validate_secret_key_field( $key, $value ) {

		if ( ! $value ) {
			return $value;
		}
		$api_exception  = null;
		$paylike_client = new \Paylike\Paylike( $value );
		try {
			$identity = $paylike_client->apps()->fetch();
		} catch ( \Paylike\Exception\ApiException $exception ) {
			$error = __( "The live private key doesn't seem to be valid", 'woocommerce-gateway-paylike' );
			WC_Paylike::handle_exceptions( null, $exception, $error );
			WC_Admin_Settings::add_error( $error );
			throw new Exception( $error );
		}
		try {
			$merchants = $paylike_client->merchants()->find( $identity['id'] );
			if ( $merchants ) {
				foreach ( $merchants as $merchant ) {
					if ( ! $merchant['test'] ) {
						$this->validation_live_public_keys[] = $merchant['key'];
					}
				}
			}
		} catch ( \Paylike\Exception\ApiException $exception ) {
			// we handle in the following statement
			$api_exception = $exception;
		}
		if ( empty( $this->validation_live_public_keys ) ) {
			$error = __( 'The live private key is not valid or set to test mode.', 'woocommerce-gateway-paylike' );
			if ( $api_exception ) {
				WC_Paylike::handle_exceptions( null, $api_exception, $error );
			}
			WC_Admin_Settings::add_error( $error );
			throw new Exception( $error );
		}

		return $value;
	}

	/**
	 * Validate the test public key.
	 *
	 * @param string $key the name of the attribute.
	 * @param string $value the value of the input.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function validate_public_key_field( $key, $value ) {

		if ( $value ) {
			if ( ! empty( $this->validation_live_public_keys ) ) {
				if ( ! in_array( $value, $this->validation_live_public_keys ) ) {
					$error = __( 'The live public key doesn\'t seem to be valid', 'woocommerce-gateway-paylike' );
					WC_Admin_Settings::add_error( $error );
					throw new Exception( $error );
				}
			}
		}

		return $value;
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon = '';
		$url  = null;
		if ( is_array( $this->card_types ) ) {
			foreach ( $this->card_types as $card_type ) {
				$url = $this->get_active_card_logo_url( $card_type );
				if ( $url ) {
					$icon .= '<img width="45" src="' . esc_url( $url ) . '" alt="' . esc_attr( strtolower( $card_type ) ) . '" />';
				}
			}
		} else {
			$icon .= '<img  src="' . esc_url( plugins_url( '../assets/images/paylike.png', __FILE__ ) ) . '" alt="Paylike Gateway" />';
		}

		return apply_filters( 'woocommerce_paylike_icon', $icon, $this->id );
	}

	/**
	 * Get logo url.
	 *
	 * @param string $type The name of the logo.
	 *
	 * @return string
	 */
	public function get_active_card_logo_url( $type ) {
		$image_type = strtolower( $type );

		return WC_HTTPS::force_https_url( plugins_url( '../assets/images/' . $image_type . '.svg', __FILE__ ) );
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
				return false;
			}
			if ( ! $this->secret_key || ! $this->public_key ) {
				return false;
			}
			$current_currency = get_woocommerce_currency();
			$supported        = get_paylike_currency( $current_currency );
			if ( ! $supported ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id Reference.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->checkout_mode ) {
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		} else {
			if ( $order->get_total() > 0 ) {
				$transaction_id = $_POST['paylike_token'];
				if ( empty( $transaction_id ) ) {
					wc_add_notice( __( 'The transaction id is missing, it seems that the authorization failed or the reference was not sent. Please try the payment again. The previous payment will not be captured.', 'woocommerce-gateway-paylike' ), 'error' );

					return;
				}
				update_post_meta( get_woo_id( $order ), '_transaction_id', $transaction_id );
				$this->handle_payment( $transaction_id, $order );
			} else {
				// used for trials, and changing payment method.
				$card_id = $_POST['paylike_card_id'];
				if ( $card_id ) {
					$this->save_card_id( $card_id, $order );
				}
				$order->payment_complete();
			}
			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}
	}

	/**
	 * Handles API interaction for the order
	 * by either only authorizing the payment
	 * or making the capture directly
	 *
	 * @param int           $transaction_id Reference.
	 * @param WC_Order      $order Order object.
	 * @param boolean|float $amount The total amount.
	 *
	 * @return bool|int|mixed
	 */
	protected function handle_payment( $transaction_id, $order, $amount = false ) {
		$order_id = get_woo_id( $order );
		WC_Paylike::log( '------------- Start payment --------------' . PHP_EOL . "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		if ( false == $this->capture ) {
			try {
				$result = $this->paylike_client->transactions()->fetch( $transaction_id );
				$this->handle_authorize_result( $result, $order, $amount );
			} catch ( \Paylike\Exception\ApiException $exception ) {
				WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Authorization Failed!' );
			}
		} else {
			$data = array(
				'amount'   => $this->get_paylike_amount( $order->get_total(), dk_get_order_currency( $order ) ),
				'currency' => dk_get_order_currency( $order ),
			);
			WC_Paylike::log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			try {
				$result = $this->paylike_client->transactions()->capture( $transaction_id, $data );
				$this->handle_capture_result( $result, $order, $amount );
			} catch ( \Paylike\Exception\ApiException $exception ) {
				WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Capture Failed!' );
			}
		}

		return $result;
	}

	/**
	 * Handle authorization result.
	 *
	 * @param array     $result Array result returned by the api wrapper.
	 * @param WC_Order  $order The order object.
	 * @param int|float $amount The amount authorized/captured.
	 */
	function handle_authorize_result( $result, $order, $amount = 0 ) {
		$transaction = $result;
		$result      = $this->parse_api_transaction_response( $result, $order, $amount );
		if ( is_wp_error( $result ) ) {
			WC_Paylike::log( 'Issue: Authorize has failed, the result from the verification threw an wp error:' . $result->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->add_order_note(
				__( 'Unable to verify transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				$result->get_error_message()
			);

		} else {
			$order->add_order_note(
				$this->get_transaction_authorization_details( $result )
			);
			$order->payment_complete();
			$this->save_transaction_id( $result, $order );
			WC_Paylike::log( 'Info: Authorize was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			update_post_meta( get_woo_id( $order ), '_paylike_transaction_captured', 'no' );
		}
	}


	/**
	 *  Handle capture event.
	 *
	 * @param array    $transaction The transaction returned by the api wrapper.
	 * @param WC_Order $order The order object related to the transaction.
	 * @param int      $amount The amount captured.
	 */
	function handle_capture_result( $transaction, $order, $amount = 0 ) {
		$result = $this->parse_api_transaction_response( $transaction, $order, $amount );
		if ( is_wp_error( $result ) ) {
			WC_Paylike::log( 'Issue: Capture has failed, the result from the verification threw an wp error:' . $result->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->add_order_note(
				__( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				$result->get_error_message()
			);
		} else {
			$order->add_order_note(
				$this->get_transaction_capture_details( $result )
			);
			WC_Paylike::log( 'Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->payment_complete();
			$this->save_transaction_id( $result, $order );
			update_post_meta( get_woo_id( $order ), '_paylike_transaction_captured', 'yes' );
		}
	}


	/**
	 * Parses api transaction response to for errors
	 *
	 * @param array      $transaction The transaction returned by the api wrapper.
	 * @param WC_Order   $order The order object.
	 * @param bool|float $amount The amount in the transaction.
	 *
	 * @return WP_Error
	 */
	protected function parse_api_transaction_response( $transaction, $order = null, $amount = false ) {
		if ( ! $this->is_transaction_successful( $transaction, $order, $amount ) ) {
			$error_message = WC_Gateway_Paylike::get_response_error( $transaction );

			return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message );
		}

		return $transaction;
	}

	/**
	 * Checks if the transaction was successful and
	 * the data was not tempered with.
	 *
	 * @param array      $transaction The transaction returned by the api wrapper.
	 * @param WC_Order   $order The order object.
	 * @param bool|false $amount Overwrite the amount, when we don't pay the full order.
	 *
	 * @return bool
	 */
	protected function is_transaction_successful( $transaction, $order = null, $amount = false ) {
		// if we don't have the order, we only check the successful status.
		if ( ! $order ) {
			return 1 == $transaction['successful'];
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
	 * Get Paylike amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public function get_paylike_amount( $total, $currency = '' ) {
		if ( '' == $currency ) {
			$currency = get_woocommerce_currency();
		}
		$multiplier = get_paylike_currency_multiplier( $currency );
		$amount     = ceil( $total * $multiplier ); // round to make sure we are always minor units.

		return $amount;
	}

	/**
	 * Gets errors from a failed api request
	 *
	 * @param array $result The result returned by the api wrapper.
	 *
	 * @return string
	 */
	public static function get_response_error( $result ) {
		$error = array();
		if ( $result ) {
			foreach ( $result as $field_error ) {
				$error[] = $field_error['field'] . ':' . $field_error['message'];
			}
		}
		$error_message = implode( ' ', $error );

		return $error_message;
	}

	/**
	 * Get the details from a transaction.
	 *
	 * @param array $transaction The transaction returned by the api wrapper.
	 *
	 * @return string
	 */
	protected function get_transaction_authorization_details( $transaction ) {
		return __( 'Paylike authorization complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
		       __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $transaction['id'] . PHP_EOL .
		       __( 'Payment Amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $transaction['amount'], $transaction['currency'] ) . PHP_EOL .
		       __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $transaction['created'];
	}

	/**
	 * Convert the cents amount into the full readable amount
	 *
	 * @param float  $amount_in_cents The amount in cents.
	 * @param string $currency The currency for which this amount is formatted.
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
	 * Store the transaction id.
	 *
	 * @param array    $transaction The transaction returned by the api wrapper.
	 * @param WC_Order $order The order object related to the transaction.
	 */
	protected function save_transaction_id( $transaction, $order ) {
		update_post_meta( get_woo_id( $order ), '_paylike_transaction_id', $transaction['id'] );
	}


	/**
	 * Get the details from a captured transaction.
	 *
	 * @param array $transaction The transaction returned by the api wrapper.
	 *
	 * @return string
	 */
	protected function get_transaction_capture_details( $transaction ) {
		return __( 'Paylike capture complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
		       __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $transaction['id'] . PHP_EOL .
		       __( 'Authorized amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $transaction['amount'], $transaction['currency'] ) . PHP_EOL .
		       __( 'Captured amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $transaction['capturedAmount'], $transaction['currency'] ) . PHP_EOL .
		       __( 'Charge authorized at: ', 'woocommerce-gateway-paylike' ) . $transaction['created'];
	}

	/**
	 * Saves the card id
	 * used for trials, and changing payment option
	 *
	 * @param int      $card_id The card reference.
	 * @param WC_Order $order The order object related to the transaction.
	 */
	protected function save_card_id( $card_id, $order ) {
		update_post_meta( get_woo_id( $order ), '_paylike_card_id', $card_id );
	}

	/**
	 * Refund a transaction process function.
	 *
	 * @param  int    $order_id The id of the order related to the transaction.
	 * @param  float  $amount The amount that is being refunded. Defaults to full amount.
	 * @param  string $reason The reason, no longer used.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order          = wc_get_order( $order_id );
		$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
		$captured       = get_post_meta( $order_id, '_paylike_transaction_captured', true );
		if ( ! $order || ! $transaction_id ) {
			return false;
		}
		$data = array();
		if ( ! is_null( $amount ) ) {
			$data['amount'] = $this->get_paylike_amount( $amount, dk_get_order_currency( $order ) );
		}

		if ( 'yes' == $captured ) {
			WC_Paylike::log( "Info: Starting to refund {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			try {
				$result = $this->paylike_client->transactions()->refund( $transaction_id, $data );
				$this->handle_refund_result( $order, $result, $captured );
			} catch ( \Paylike\Exception\ApiException $exception ) {
				WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Refund has failed!' );
				$error_message = WC_Gateway_Paylike::get_response_error( $exception->getJsonBody() );
				if ( ! $error_message ) {
					$error_message = 'There has been an problem with the refund. Refresh the order to see more details';
				}

				return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message );
			}
		} else {
			WC_Paylike::log( "Info: Starting to void {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			try {
				$result = $this->paylike_client->transactions()->void( $transaction_id, $data );
				$this->handle_refund_result( $order, $result, $captured );
			} catch ( \Paylike\Exception\ApiException $exception ) {
				WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Void has failed!' );
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
	 * Refund handler.
	 *
	 * @param WC_Order $order The order object related to the transaction.
	 * @param array    $transaction The transaction returned by the api wrapper.
	 * @param boolean  $captured True if the order has been captured, false otherwise.
	 *
	 * @return bool
	 */
	function handle_refund_result( $order, $transaction, $captured ) {

		if ( 1 == $transaction['successful'] ) {
			if ( 'yes' == $captured ) {
				$refunded_amount = $transaction['refundedAmount'];
			} else {
				$refunded_amount = $transaction['voidedAmount'];
			}

			$refund_message = __( 'Paylike transaction refunded.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
			                  __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $transaction['id'] . PHP_EOL .
			                  __( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $refunded_amount, $transaction['currency'] ) . PHP_EOL .
			                  __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $transaction['created'];
			$order->add_order_note( $refund_message );
			WC_Paylike::log( 'Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return true;
		} else {
			$error_message = WC_Gateway_Paylike::get_response_error( $transaction );
			$order->add_order_note(
				__( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				__( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message
			);
			WC_Paylike::log( 'Issue: Refund has failed there has been an issue with the transaction.' . $error_message . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return false;
		}
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		if ( 'before_order' == $this->checkout_mode ) {
			$user = wp_get_current_user();
			if ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
			} else {
				$user_email = '';
			}
			$user_name    = '';
			$user_address = '';
			$user_phone   = '';
			$token        = '';
			/* This may be in ajax, so we need to check if the total has changed */
			if ( isset( $_POST['post_data'] ) ) {
				$post_data = array();
				parse_str( $_POST['post_data'], $post_data );
				if ( isset( $post_data['paylike_token'] ) ) {
					$transaction_id = $post_data['paylike_token'];
					try {
						$transaction = $this->paylike_client->transactions()->fetch( $transaction_id );
					} catch ( \Paylike\Exception\ApiException $exception ) {
						// we are handling this later
					}
					$amount   = WC()->cart->total;
					$currency = get_woocommerce_currency();

					if ( ! ( $transaction && $transaction['successful'] &&
					         $currency == $transaction['currency'] &&
					         $this->get_paylike_amount( $amount, $currency ) == $transaction['amount']
					) ) {
						$data = array(
							'amount' => $transaction['amount'],
						);
						WC_Paylike::log( 'Voiding the transaction as it was not succesfull or it had different amount.' . json_encode( $result ) . '--' . $currency . '--' . $amount . '--' . $this->get_paylike_amount( $amount, $currency ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
						try {
							$transaction = $this->paylike_client->transactions()->void( $transaction_id );
						} catch ( \Paylike\Exception\ApiException $exception ) {
							WC_Paylike::handle_exceptions( null, $exception, 'Voiding the orphan transaction failed!' );
						}
					} else {
						// all good everything is still valid.
						$token = '<input type="hidden" class="paylike_token" name="paylike_token" value="' . $transaction_id . '">';
					}
				}
			}

			/**
			 * If we are on the failed payment page we need to use the order instead of the cart.
			 *
			 */
			if ( ! isset( $_GET['pay_for_order'] ) ) {
				$order_id        = 'Could not be determined at this point';
				$amount          = WC()->cart->total;
				$amount_tax      = WC()->cart->tax_total;
				$amount_shipping = WC()->cart->shipping_total;
				$currency        = get_woocommerce_currency();
			} else {
				$order_id        = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) );
				$order           = wc_get_order( $order_id );
				$currency        = dk_get_order_currency( $order );
				$amount          = $order->get_total();
				$amount_tax      = $order->get_total_tax();
				$amount_shipping = dk_get_order_shipping_total( $order );
				$user_email      = dk_get_order_data( $order, 'get_billing_email' );
				$user_name       = dk_get_order_data( $order, 'get_billing_first_name' ) . ' ' . dk_get_order_data( $order, 'get_billing_last_name' );
				$user_address    = dk_get_order_data( $order, 'get_billing_address_1' ) . ' ' . dk_get_order_data( $order, 'get_billing_address_2' );
				$user_phone      = dk_get_order_data( $order, 'get_billing_phone' );
			}

			echo '<div
			id="paylike-payment-data"' . '"
			data-email="' . esc_attr( $user_email ) . '"
			data-name="' . esc_attr( $user_name ) . '"
			data-phone="' . esc_attr( $user_phone ) . '"
			data-address="' . esc_attr( $user_address ) . '"
			data-locale="' . esc_attr( get_locale() ) . '"
			data-order_id="' . esc_attr( $order_id ) . '"
			data-amount="' . esc_attr( $this->get_paylike_amount( $amount, $currency ) ) . '"
			data-totalTax="' . esc_attr( $this->get_paylike_amount( $amount_tax, $currency ) ) . '"
			data-totalShipping="' . esc_attr( $this->get_paylike_amount( $amount_shipping, $currency ) ) . '"
			data-customerIP="' . esc_attr( $this->get_client_ip() ) . '"
			data-title="' . esc_attr( $this->popup_title ) . '"
			data-currency="' . esc_attr( get_woocommerce_currency() ) . '"
			">';
			echo $token; // WPCS: XSS ok.
			echo '</div>';
		}
		if ( $this->description ) {
			echo wpautop( wp_kses_post( apply_filters( 'wc_paylike_description', $this->description ) ) );
		}
	}

	/**
	 * Retrieve client ip.
	 *
	 * @return string
	 */
	function get_client_ip() {
		if ( getenv( 'HTTP_CLIENT_IP' ) ) {
			$ip_address = getenv( 'HTTP_CLIENT_IP' );
		} elseif ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
			$ip_address = getenv( 'HTTP_X_FORWARDED_FOR' );
		} elseif ( getenv( 'HTTP_X_FORWARDED' ) ) {
			$ip_address = getenv( 'HTTP_X_FORWARDED' );
		} elseif ( getenv( 'HTTP_FORWARDED_FOR' ) ) {
			$ip_address = getenv( 'HTTP_FORWARDED_FOR' );
		} elseif ( getenv( 'HTTP_FORWARDED' ) ) {
			$ip_address = getenv( 'HTTP_FORWARDED' );
		} elseif ( getenv( 'REMOTE_ADDR' ) ) {
			$ip_address = getenv( 'REMOTE_ADDR' );
		} else {
			$ip_address = '0.0.0.0';
		}

		return $ip_address;
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}
		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
		if ( ( function_exists( 'wc_site_is_https' ) && ! wc_site_is_https() ) && ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! class_exists( 'WordPressHTTPS' ) ) ) {
			/* translators: %1$s is replaced with the admin url */
			echo '<div class="error paylike-ssl-message"><p>' . sprintf( __( 'Paylike: <a href="%s">Force SSL</a> is disabled; your checkout page may not be secure! Unless you have a valid SSL certificate and force the checkout pages to be secure, only test mode will be allowed.', 'woocommerce-gateway-paylike' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}
	}

	/**
	 * Enque the payment scripts.
	 */
	public function payment_scripts() {
		global $wp_version;
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}
		wp_enqueue_script( 'paylike', 'https://sdk.paylike.io/3.js', '', '3.0', true );
		wp_enqueue_script( 'woocommerce_paylike', plugins_url( 'assets/js/paylike_checkout.js', WC_PAYLIKE_MAIN_FILE ), array( 'paylike' ), WC_PAYLIKE_VERSION, true );
		$products = array();
		if ( ! isset( $_GET['pay_for_order'] ) ) {
			$items = WC()->cart->get_cart();

			foreach ( $items as $item => $values ) {
				$_product   = $values['data'];
				$product    = array(
					'ID'       => $values['product_id'],
					'name'     => $_product->get_title(),
					'quantity' => $values['quantity'],
				);
				$products[] = $product;
			}
		} else {
			$order_id = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) );
			$order    = wc_get_order( $order_id );
			$products = array();
			$items    = $order->get_items();
			$pf       = new WC_Product_Factory();
			foreach ( $items as $item => $values ) {
				$_product   = $pf->get_product( $values['product_id'] );
				$product    = array(
					'ID'       => $values['product_id'],
					'name'     => $_product->get_title(),
					'quantity' => isset( $values['quantity'] ) ? $values['quantity'] : $values['qty'],
				);
				$products[] = $product;
			}
		}
		$paylike_params = array(
			'key'               => $this->public_key,
			'customer_IP'       => $this->get_client_ip(),
			'products'          => $products,
			'platform_version'  => $wp_version,
			'ecommerce_version' => WC()->version,
			'version'           => WC_PAYLIKE_VERSION,
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
		);
		wp_localize_script( 'woocommerce_paylike', 'wc_paylike_params', apply_filters( 'wc_paylike_params', $paylike_params ) );
	}

	/**
	 * Process options save hook.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$this->init_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
				} catch ( Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
			}
		}

		return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
	}

	/**
	 * Display the pay button on the receipt page.
	 *
	 * @param int $order_id The order reference id.
	 */
	public function receipt_page( $order_id ) {
		global $wp_version;
		$order    = wc_get_order( $order_id );
		$amount   = $this->get_paylike_amount( $order->get_total(), $order->get_order_currency() );
		$products = array();
		$items    = $order->get_items();
		$pf       = new WC_Product_Factory();
		foreach ( $items as $item => $values ) {
			$_product   = $pf->get_product( $values['product_id'] );
			$product    = array(
				'ID'       => $values['product_id'],
				'name'     => $_product->get_title(),
				'quantity' => isset( $values['quantity'] ) ? $values['quantity'] : $values['qty'],
			);
			$products[] = $product;
		}
		echo '<p>' . __( 'Thank you for your order, please click below to pay and complete your order.', 'woocommerce-gateway-paylike' ) . '</p>';
		?>
		<button id="paylike-payment-button"
				onclick="pay(event);"><?php _e( 'Pay Now', 'woocommerce-gateway-paylike' ); ?></button>
		<script src="https://sdk.paylike.io/3.js"></script>
		<script>
            var paylike = Paylike('<?php echo $this->public_key;?>');

            function pay(e) {
                e.preventDefault();

                paylike.popup({
                    title: '<?php echo esc_attr( $this->popup_title ); ?>',
                    currency: '<?php echo get_woocommerce_currency() ?>',
                    amount:  <?php echo $amount; ?>,
                    locale: '<?php echo get_locale(); ?>',
                    custom: {
                        orderId: '<?php echo $order->get_order_number(); ?>',
                        products: [<?php echo json_encode( $products ); ?>],
                        customer: {
                            name: '<?php echo dk_get_order_data( $order, 'get_billing_first_name' ) . ' ' . dk_get_order_data( $order, 'get_billing_last_name' ); ?>',
                            email: '<?php echo dk_get_order_data( $order, 'get_billing_email' ); ?>',
                            phoneNo: '<?php echo dk_get_order_data( $order, 'get_billing_phone' ); ?>',
                            address: '<?php echo dk_get_order_data( $order, 'get_billing_address_1' ) . ' ' . dk_get_order_data( $order, 'get_billing_address_2' ); ?>',
                            IP: '<?php echo $this->get_client_ip(); ?>'
                        },
                        platform: {
                            name: 'WordPress',
                            version: '<?php echo $wp_version; ?>'
                        },
                        ecommerce: {
                            name: 'WooCommerce',
                            version: '<?php echo WC()->version; ?>'
                        },
                        paylikePluginVersion: '<?php echo WC_PAYLIKE_VERSION; ?>'
                    }
                }, function (err, res) {
                    if (err)
                        return console.warn(err);

                    var trxid = res.transaction.id;
                    jQuery("#complete_order").append('<input type="hidden" name="transaction_id" value="' + trxid + '" /> ');
                    jQuery('#paylike-payment-button').attr('disabled', 'disabled');
                    document.getElementById("complete_order").submit();
                });
            }
		</script>
		<form id="complete_order" method="POST" action="<?php echo WC()->api_request_url( get_class( $this ) ) ?>">
			<input type="hidden" name="reference" value="<?php echo $order_id; ?>"/>
			<input type="hidden" name="amount" value="<?php echo $this->get_order_total(); ?>"/>
			<input type="hidden" name="signature"
					value="<?php echo $this->get_signature( $order_id ); ?>"/>
		</form>
		<?php
	}

	/**
	 * Get transaction signature
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	function get_signature( $order_id ) {
		return strtoupper( md5( $this->get_order_total() . $order_id . $this->public_key ) );
	}

	/**
	 * Handle return call from paylike
	 */
	public function return_handler() {
		@ob_clean();
		header( 'HTTP/1.1 200 OK' );
		try {
			if ( isset( $_REQUEST['reference'] ) && isset( $_REQUEST['signature'] ) && isset( $_REQUEST['transaction_id'] ) && isset( $_REQUEST['amount'] ) ) {
				$signature = strtoupper( md5( $_REQUEST['amount'] . $_REQUEST['reference'] . $this->public_key ) );
				$order_id  = absint( $_REQUEST['reference'] );
				$order     = wc_get_order( $order_id );
				if ( $signature === $_REQUEST['signature'] ) {
					$transaction_id = $_REQUEST['transaction_id'];
					if ( false == $this->capture ) {
						WC_Paylike::log( "Info: Starting to authorize {$transaction_id}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
						try {
							$result = $this->paylike_client->transactions()->fetch( $transaction_id );
							$this->handle_authorize_result( $result, $order );
						} catch ( \Paylike\Exception\ApiException $exception ) {
							WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Capture Failed!' );
						}
					} else {
						$data = array(
							'amount'   => $this->get_paylike_amount( $order->get_total(), dk_get_order_currency( $order ) ),
							'currency' => dk_get_order_currency( $order ),
						);
						WC_Paylike::log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
						try {
							$result = $this->paylike_client->transactions()->capture( $transaction_id, $data );
							$this->handle_capture_result( $result, $order, $amount );
						} catch ( \Paylike\Exception\ApiException $exception ) {
							WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Capture Failed!' );
						}
					}
					wp_redirect( $this->get_return_url( $order ) );
					exit();
				}
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( get_permalink( wc_get_page_id( 'checkout' ) ) );
			exit();
		}
		wp_redirect( get_permalink( wc_get_page_id( 'cart' ) ) );
		exit();
	}

	/**
	 * Sends the failed order email to admin
	 *
	 * @version 3.1.0
	 * @since 3.1.0
	 *
	 * @param int $order_id
	 *
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}


	/**
	 * @param $order
	 *
	 * @return mixed
	 */
	protected function get_transaction_id( $order ) {
		$transaction_id = get_post_meta( get_woo_id( $order ), '_paylike_transaction_id', true );
		if ( $transaction_id ) {
			return $transaction_id;
		}

		// we continue our search on the subscriptions
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( get_woo_id( $order ) );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( get_woo_id( $order ) );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			$transaction_id = get_post_meta( get_woo_id( $subscription ), '_paylike_transaction_id', true );
			if ( $transaction_id ) {
				return $transaction_id;
			}
		}

		return false;
	}

	/**
	 * @param $order
	 *
	 * @return mixed
	 */
	protected function get_card_id( $order ) {
		$card_id = get_post_meta( get_woo_id( $order ), '_paylike_card_id', true );
		if ( $card_id ) {
			return $card_id;
		}

		// we continue our search on the subscriptions
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( get_woo_id( $order ) );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( get_woo_id( $order ) ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( get_woo_id( $order ) );
		} else {
			$subscriptions = array();
		}
		foreach ( $subscriptions as $subscription ) {
			$card_id = get_post_meta( get_woo_id( $subscription ), '_paylike_card_id', true );
			if ( $card_id ) {
				return $card_id;
			}
		}

		return false;
	}

	/**
	 * @return WP_Error
	 */
	protected function can_user_capture() {

		$merchant_id = $this->get_global_merchant_id();
		if ( is_wp_error( $merchant_id ) ) {
			return $merchant_id;
		}
		WC_Paylike::log( 'Info: Attempting to fetch the merchant data' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		try {
			$merchant = $this->paylike_client->merchants()->fetch( $merchant_id );
		} catch ( \Paylike\Exception\ApiException $exception ) {
			$error = __( "The merchant couldn't be found", 'woocommerce-gateway-paylike' );

			return new WP_Error( 'paylike_error', $error );
		}

		if ( true == $merchant['claim']['canCapture'] ) {
			return true;
		}

		$error = __( "The merchant is not allowed to capture", 'woocommerce-gateway-paylike' );

		return new WP_Error( 'paylike_error', $error );
	}

	/**
	 * Gets global merchant id.
	 *
	 * @param int    $entity_id Transaction or card id reference.
	 * @param string $type The type of the transaction.
	 *
	 * @return bool|int|mixed|null|WP_Error
	 */
	protected function get_global_merchant_id() {
		WC_Paylike::log( 'Info: Attempting to fetch the global merchant id ' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		if ( ! $this->paylike_client ) {
			if ( '' !== $this->secret_key ) {
				$this->paylike_client = new Paylike\Paylike( $this->secret_key );
			} else {
				$error = __( "The private key doesn't seem to be valid", 'woocommerce-gateway-paylike' );

				return new WP_Error( 'paylike_error', $error );
			}
		}
		try {
			$identity = $this->paylike_client->apps()->fetch();
		} catch ( \Paylike\Exception\ApiException $exception ) {
			$error = __( "The private key doesn't seem to be valid", 'woocommerce-gateway-paylike' );

			return new WP_Error( 'paylike_error', $error );
		}
		try {
			$merchants = $this->paylike_client->merchants()->find( $identity['id'] );
			if ( $merchants ) {
				foreach ( $merchants as $merchant ) {
					if ( $this->testmode == 'yes' && $merchant['test'] && $merchant['key'] == $this->public_key ) {
						return $merchant['id'];
					}
					if ( ! $merchant['test'] && $this->testmode != 'yes' && $merchant['key'] == $this->public_key ) {
						return $merchant['id'];
					}
				}
			}
		} catch ( \Paylike\Exception\ApiException $exception ) {
			$error = __( 'No valid merchant id was found', 'woocommerce-gateway-paylike' );

			return new WP_Error( 'paylike_error', $error );
		}


	}
}
