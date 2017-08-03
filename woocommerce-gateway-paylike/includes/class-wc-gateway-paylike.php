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
	public $direct_checkout;

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
			'multiple_subscriptions'
		);
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		// Get setting values.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->testmode           = 'yes' === $this->get_option( 'testmode' );
		$this->capture            = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->direct_checkout    = 'yes' === $this->get_option( 'direct_checkout', 'yes' );
		$this->compatibility_mode = 'yes' === $this->get_option( 'compatibility_mode', 'yes' );
		$this->secret_key         = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->public_key         = $this->testmode ? $this->get_option( 'test_public_key' ) : $this->get_option( 'public_key' );
		$this->logging            = 'yes' === $this->get_option( 'logging' );
		$this->card_types         = $this->get_option( 'card_types' );
		$this->order_button_text  = __( 'Continue to payment', 'woocommerce-gateway-paylike' );
		if ( $this->testmode ) {
			$this->description .= PHP_EOL . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4100 0000 0000 0000 with any CVC and a valid expiration date. "<a href="%s">See Documentation</a>".', 'woocommerce-gateway-paylike' ), 'https://github.com/paylike/sdk' );
			$this->description = trim( $this->description );
		}
		if ( $this->secret_key != '' ) {
			Paylike\Client::setKey( $this->secret_key );
		}
		// Hooks.
		if ( $this->direct_checkout ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			$this->has_fields = true;
		}
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'return_handler' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-paylike.php' );
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon = '';
		if ( is_array( $this->card_types ) ) {
			foreach ( $this->card_types as $card_type ) {
				if ( $url = $this->get_active_card_logo_url( $card_type ) ) {
					$icon .= '<img width="45" src="' . esc_url( $url ) . '" alt="' . esc_attr( strtolower( $card_type ) ) . '" />';
				}
			}
		} else {
			$icon .= '<img  src="' . esc_url( plugins_url( 'images/paylike.png', __FILE__ ) ) . '" alt="Paylike Gateway" />';
		}

		return apply_filters( 'woocommerce_paylike_icon', $icon, $this->id );
	}

	public function get_active_card_logo_url( $type ) {
		$image_type = strtolower( $type );

		return WC_HTTPS::force_https_url( plugins_url( '../assets/images/' . $image_type . '.png', __FILE__ ) );
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
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->direct_checkout ) {
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		} else {
			if ( $order->get_total() > 0 ) {
				$transaction_id = $_POST['paylike_token'];
				if ( empty( $transaction_id ) ) {
					wc_add_notice( __( 'The transaction id is missing, it seems that the authorization failed or the reference was not sent. Please try the payment again. The previous payment will not be captured.', 'woocommerce-gateway-paylike' ), 'error' );

					return;
				}
				$this->handle_payment( $transaction_id, $order );
			} else {
				// used for trials, and changing payment method
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
				'redirect' => $this->get_return_url( $order )
			);

		}
	}

	/**
	 * Handles API interaction for the order
	 * by either only authorizing the payment
	 * or making the capture directly
	 *
	 * @param $transaction_id
	 * @param WC_Order $order
	 * @param $amount
	 *
	 * @return bool|int|mixed
	 */
	protected function handle_payment( $transaction_id, $order, $amount = false ) {
		$order_id = get_woo_id( $order );
		WC_Paylike::log( "------------- Start payment --------------" . PHP_EOL . "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		if ( false == $this->capture ) {
			$result = Paylike\Transaction::fetch( $transaction_id );
			$this->handle_authorize_result( $result, $order, $amount );
		} else {
			$data = array(
				'amount'   => $this->get_paylike_amount( $order->get_total(), dk_get_order_currency( $order ) ),
				'currency' => dk_get_order_currency( $order )
			);
			WC_Paylike::log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$result = Paylike\Transaction::capture( $transaction_id, $data );
			$this->handle_capture_result( $result, $order, $amount );
		}

		return $result;
	}

	/**
	 * @param WC_Order $order
	 * @param $result // array result returned by the api wrapper
	 * @param int $amount
	 */
	function handle_authorize_result( $result, $order, $amount = 0 ) {
		$transaction = $result;
		$result      = $this->parse_api_transaction_response( $result, $order, $amount );
		if ( is_wp_error( $result ) ) {
			WC_Paylike::log( "Fatal Error: Authorize has failed, the result from the verification threw and wp error:" . $result->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
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
			WC_Paylike::log( "Info: Authorize was successful" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			update_post_meta( get_woo_id( $order ), '_paylike_transaction_captured', 'no' );
		}
	}

	/**
	 * Parses api transaction response to for errors
	 *
	 * @param $result
	 * @param WC_Order $order
	 * @param bool $amount
	 *
	 * @return WP_Error
	 */
	protected function parse_api_transaction_response( $result, $order = null, $amount = false ) {
		if ( ! $result ) {
			return new WP_Error( 'paylike_error', __( 'cURL request failed.', 'woocommerce-gateway-paylike' ) );
		}
		if ( ! $this->is_transaction_successful( $result, $order, $amount ) ) {
			$error_message = $this->get_response_error( $result );

			return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message );
		}

		return $result;
	}

	/**
	 * Checks if the transaction was successful and
	 * the data was not tempered with
	 *
	 *
	 * @param $result
	 * @param WC_Order $order
	 * @param bool|false $amount used to overwrite the amount, when we don't pay the full order
	 *
	 * @return bool
	 */
	protected function is_transaction_successful( $result, $order = null, $amount = false ) {
		// if we don't have the order, we only check the successful status
		if ( ! $order ) {
			return 1 == $result['transaction']['successful'];
		}
		// we need to overwrite the amount in the case of a subscription
		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		return 1 == $result['transaction']['successful'] &&
		       $result['transaction']['currency'] == dk_get_order_currency( $order ) &&
		       $result['transaction']['amount'] == $this->get_paylike_amount( $amount, dk_get_order_currency( $order ) );
	}

	/**
	 * Get Paylike amount to pay
	 *
	 * @param float $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public function get_paylike_amount( $total, $currency = '' ) {
		if ( $currency == '' ) {
			$currency = get_woocommerce_currency();
		}
		$multiplier = get_paylike_currency_multiplier( $currency );
		$amount     = ceil( $total * $multiplier ); // round to make sure we are always minor units

		return $amount;
	}

	/**
	 * Gets errors from a failed api request
	 *
	 * @param $result
	 *
	 * @return string
	 */
	protected function get_response_error( $result ) {
		$error = array();
		foreach ( $result as $field_error ) {
			$error[] = $field_error['field'] . ':' . $field_error['message'];
		}
		$error_message = implode( " ", $error );

		return $error_message;
	}

	/**
	 * @param $result
	 *
	 * @return string
	 */
	protected function get_transaction_authorization_details( $result ) {
		return __( 'Paylike authorization complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
		       __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
		       __( 'Payment Amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['transaction']['amount'], $result['transaction']['currency'] ) . PHP_EOL .
		       __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created'];
	}

	/**
	 * Convert the cents amount into the full readable amount
	 *
	 * @param $amount_in_cents
	 * @param string $currency
	 *
	 * @return string
	 */
	function real_amount( $amount_in_cents, $currency = '' ) {
		return strip_tags( wc_price( $amount_in_cents / get_paylike_currency_multiplier( $currency ), array(
			'ex_tax_label' => false,
			'currency'     => $currency
		) ) );
	}

	/**
	 * @param $result
	 * @param $order
	 */
	protected function save_transaction_id( $result, $order ) {
		update_post_meta( $order->id, '_paylike_transaction_id', $result['transaction']['id'] );
	}

	/**
	 * @param WC_Order $order
	 * @param $result // array result returned by the api wrapper
	 * @param int $amount
	 */
	function handle_capture_result( $result, $order, $amount = 0 ) {
		$result = $this->parse_api_transaction_response( $result, $order, $amount );
		if ( is_wp_error( $result ) ) {
			WC_Paylike::log( "Fatal Error: Capture has failed, the result from the verification threw and wp error:" . $result->get_error_message() . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->add_order_note(
				__( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				$result->get_error_message()
			);
		} else {
			$order->add_order_note(
				$this->get_transaction_capture_details( $result )
			);
			WC_Paylike::log( "Info: Capture was successful" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			$order->payment_complete();
			$this->save_transaction_id( $result, $order );
			update_post_meta( get_woo_id( $order ), '_paylike_transaction_captured', 'yes' );
		}
	}

	/**
	 * @param $result
	 *
	 * @return string
	 */
	protected function get_transaction_capture_details( $result ) {
		return __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
		       __( 'Authorized amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['transaction']['amount'], $result['transaction']['currency'] ) . PHP_EOL .
		       __( 'Captured amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['transaction']['capturedAmount'], $result['transaction']['currency'] ) . PHP_EOL .
		       __( 'Charge authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created'];
	}

	/**
	 * Saves the card id
	 * used for trials, and changing payment option
	 *
	 * @param $card_id
	 * @param $order
	 */
	protected function save_card_id( $card_id, $order ) {
		update_post_meta( $order->id, '_paylike_card_id', $card_id );
	}

	/**
	 * Refund a transaction
	 *
	 * @param  int $order_id
	 * @param  float $amount
	 * @param  string $reason
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order          = wc_get_order( $order_id );
		$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
		if ( ! $order || ! $transaction_id ) {
			return false;
		}
		$data = array();
		if ( ! is_null( $amount ) ) {
			$data['amount'] = $this->get_paylike_amount( $amount, dk_get_order_currency( $order ) );
		}
		WC_Paylike::log( "Info: Beginning refund for order $order_id for the amount of {$amount}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
		$captured = get_post_meta( $order_id, '_paylike_transaction_captured', true );
		if ( 'yes' == $captured ) {
			if ( $reason ) {
				$data['descriptor'] = $reason;
			}
			$result = Paylike\Transaction::refund( $transaction_id, $data );
		} else {
			$result = Paylike\Transaction::void( $transaction_id, $data );
		}

		return $this->handle_refund_result( $order, $result, $captured );

	}

	/**
	 * @param WC_Order $order
	 * @param $result // array result returned by the api wrapper
	 * @param $captured
	 *
	 * @return bool
	 */
	function handle_refund_result( $order, $result, $captured ) {
		if ( ! $result ) {
			WC_Paylike::log( 'Unable to refund transaction!' . PHP_EOL . 'cURL request failed.' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

			return false;
		} else {
			if ( 1 == $result['transaction']['successful'] ) {
				if ( 'yes' == $captured ) {
					$refunded_amount = $result['transaction']['refundedAmount'];
				} else {
					$refunded_amount = $result['transaction']['voidedAmount'];
				}

				$refund_message = __( 'Paylike transaction refunded.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				                  __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
				                  __( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $refunded_amount, $result['transaction']['currency'] ) . PHP_EOL .
				                  __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created'];
				$order->add_order_note( $refund_message );
				WC_Paylike::log( "Info: Refund was successful" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

				return true;
			} else {
				$error_message = $this->get_response_error( $result );
				$order->add_order_note(
					__( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message
				);
				WC_Paylike::log( "Fatal Error: Refund has failed there has been an issue with the transaction." . $error_message . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );

				return false;
			}
		}
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		if ( $this->direct_checkout ) {
			$user = wp_get_current_user();
			if ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
			} else {
				$user_email = '';
			}

			$token = '';
			/* This may be in ajax, so we need to check if the total has changed */
			if ( isset( $_POST['post_data'] ) ) {
				$post_data = array();
				parse_str( $_POST['post_data'], $post_data );
				if ( isset( $post_data['paylike_token'] ) ) {
					$transaction_id = $post_data['paylike_token'];
					$result         = Paylike\Transaction::fetch( $transaction_id );
					$amount         = WC()->cart->total;
					$currency       = get_woocommerce_currency();

					if ( $result['transaction']['successful'] &&
					     $result['transaction']['currency'] == $currency &&
					     $result['transaction']['amount'] == $this->get_paylike_amount( $amount, $currency )
					) {
						// all good everything is still valid

						$token = '<input type="hidden" class="paylike_token" name="paylike_token" value="' . $transaction_id . '">';
					} else {
						$data   = array(
							'amount' => $result['transaction']['amount'],
						);
						$result = Paylike\Transaction::void( $transaction_id, $data );
					}
				}
			}

			echo '<div
			id="paylike-payment-data"
			data-email="' . esc_attr( $user_email ) . '"
			data-locale="' . get_locale() . '"
			data-amount="' . esc_attr( $this->get_paylike_amount( WC()->cart->total ) ) . '"
			data-totalTax="' . esc_attr( $this->get_paylike_amount( WC()->cart->tax_total ) ) . '"
			data-totalShipping="' . esc_attr( $this->get_paylike_amount( WC()->cart->shipping_total ) ) . '"
			data-customerIP="' . $this->get_client_ip() . '"
			data-title="' . get_bloginfo( 'name' ) . '"
			data-currency="' . esc_attr( get_woocommerce_currency() ) . '"
			">';
			echo $token;
			echo '</div>';
		}
		if ( $this->description ) {
			echo apply_filters( 'wc_paylike_description', wpautop( wp_kses_post( $this->description ) ) );
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
			echo '<div class="error paylike-ssl-message"><p>' . sprintf( __( 'Paylike: <a href="%s">Force SSL</a> is disabled; your checkout page may not be secure! Unless you have a valid SSL certificate and force the checkout pages to be secure, only test mode will be allowed.', 'woocommerce-gateway-paylike' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}
	}

	/**
	 *
	 *
	 */
	public function payment_scripts() {
		global $wp_version;
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}
		wp_enqueue_script( 'paylike', 'https://sdk.paylike.io/3.js', '', '3.0', true );
		wp_enqueue_script( 'woocommerce_paylike', plugins_url( 'assets/js/paylike_checkout.js', WC_PAYLIKE_MAIN_FILE ), array( 'paylike' ), WC_PAYLIKE_VERSION, true );
		$products = array();
		$items    = WC()->cart->get_cart();
		foreach ( $items as $item => $values ) {
			$_product   = $values['data'];
			$product    = array(
				'ID'       => $values['product_id'],
				'name'     => $_product->get_title(),
				'quantity' => $values['quantity']
			);
			$products[] = $product;
		}
		$paylike_params = array(
			'key'               => $this->public_key,
			'customer_IP'       => $this->get_client_ip(),
			'products'          => $products,
			'platform_version'  => $wp_version,
			'ecommerce_version' => WC()->version,
			'version'           => WC_PAYLIKE_VERSION,
			'ajax_url'          => admin_url( 'admin-ajax.php' )
		);
		wp_localize_script( 'woocommerce_paylike', 'wc_paylike_params', apply_filters( 'wc_paylike_params', $paylike_params ) );
	}

	/**
	 * Display the pay button on the receipt page.
	 *
	 * @param $order_id
	 */
	public function receipt_page( $order_id ) {
		global $wp_version;
		$order    = wc_get_order( $order_id );
		$amount   = $this->get_paylike_amount( $order->get_total(), $order->get_order_currency() );
		$products = array();
		$items    = $order->get_items();
		foreach ( $items as $item => $values ) {
			$_product   = $values['data'];
			$product    = array(
				'ID'       => $values['product_id'],
				'name'     => $_product->get_title(),
				'quantity' => $values['quantity']
			);
			$products[] = $product;
		}
		echo '<p>' . __( 'Thank you for your order, please click below to pay and complete your order.', 'woocommerce-gateway-paylike' ) . '</p>';
		?>
		<button onclick="pay(event);"><?php _e( 'Pay Now', 'woocommerce-gateway-paylike' ); ?></button>
		<script src="https://sdk.paylike.io/3.js"></script>
		<script>
            var paylike = Paylike('<?php echo $this->public_key;?>');

            function pay(e) {
                e.preventDefault();
                paylike.popup({
                    title: '<?php echo get_bloginfo( 'name' ); ?>',
                    currency: '<?php echo get_woocommerce_currency() ?>',
                    amount:  <?php echo $amount; ?>,
                    locale: '<?php echo get_locale(); ?>',
                    custom: {
                        orderNo: '<?php echo $order->get_order_number() ?>',
                        products: [<?php echo json_encode( $products ) ?>],
                        customer: {
                            email: '<?php echo $order->get_billing_email() ?>',
                            name: '<?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ?>',
                            address: '<?php echo $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ?>',
                            customerIp: '<?php echo $this->get_client_ip() ?>',
                        },
                        totalTax: '<?php echo $order->get_total_tax()?>',
                        totalShipping: '<?php echo $order->get_shipping_total()?>',
                        platform: {
                            name: 'WordPress',
                            version: '<?php echo $wp_version ?>''
                        },
                        ecommerce: {
                            name: 'WooCommerce',
                            version: '<?php echo WC()->version; ?>'
                        },
                        version: '<?php echo WC_PAYLIKE_VERSION ?>'
                    }
                }, function (err, res) {
                    if (err)
                        return console.warn(err);

                    var trxid = res.transaction.id;
                    jQuery("#complete_order").append('<input type="hidden" name="transaction_id" value="' + trxid + '" /> ');
                    document.getElementById("complete_order").submit();
                });
            }
		</script>
		<form id="complete_order" action="<?php echo WC()->api_request_url( get_class( $this ) ) ?>">
			<input type="hidden" name="reference" value="<?php echo $order_id ?>"/>
			<input type="hidden" name="amount" value="<?php echo $this->get_order_total() ?>"/>
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
						$result = Paylike\Transaction::fetch( $transaction_id );
						$this->handle_authorize_result( $result, $order );
					} else {
						$data = array(
							'amount'   => $this->get_paylike_amount( $order->get_total(), dk_get_order_currency( $order ) ),
							'currency' => dk_get_order_currency( $order )
						);
						WC_Paylike::log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
						$result = Paylike\Transaction::capture( $transaction_id, $data );
						$this->handle_capture_result( $result, $order );
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
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}


	/**
	 * Parses api card response to for errors
	 *
	 * @param $result
	 *
	 * @return null
	 */
	protected function parse_api_card_response( $result ) {
		if ( ! $result ) {
			return new WP_Error( 'paylike_error', __( 'cURL request failed.', 'woocommerce-gateway-paylike' ) );
		} else if ( ! isset( $result['card'] ) || ! isset( $result['card']['id'] ) ) {
			return new WP_Error( 'paylike_error', __( 'Error: ', 'woocommerce-gateway-paylike' ) . $this->get_response_error( $result ) );
		}

		return $result;
	}

	/**
	 * @param $order
	 *
	 * @return mixed
	 */
	protected function get_transaction_id( $order ) {
		return get_post_meta( $order->id, '_paylike_transaction_id', true );
	}

	/**
	 * @param $order
	 *
	 * @return mixed
	 */
	protected function get_card_id( $order ) {
		return get_post_meta( $order->id, '_paylike_card_id', true );
	}
}
