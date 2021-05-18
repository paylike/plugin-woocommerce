<?php
/*
 * Plugin Name: WooCommerce Paylike Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-paylike/
 * Description: Allow customers to pay with credit cards via Paylike in your WooCommerce store.
 * Author: Derikon Development
 * Author URI: https://derikon.com/
 * Version: 2.4.1
 * Text Domain: woocommerce-gateway-paylike
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 4.9.2
 *
 * Copyright (c) 2016 Derikon Development
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.xdebu
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Required minimums and constants
 */
define( 'WC_PAYLIKE_VERSION', '2.4.1' );
define( 'WC_PAYLIKE_MIN_PHP_VER', '5.3.0' );
define( 'WC_PAYLIKE_MIN_WC_VER', '2.5.0' );
define( 'WC_PAYLIKE_CURRENT_SDK', 6 );
define( 'WC_PAYLIKE_BETA_SDK', 6 );
define( 'WC_PAYLIKE_MAIN_FILE', __FILE__ );
define( 'WC_PAYLIKE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_PAYLIKE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_PAYLIKE_PLUGIN_TEMPLATE_DIR', plugin_dir_path( __FILE__ ) . '/templates' );
if ( ! class_exists( 'WC_Paylike' ) ) {
	class WC_Paylike {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
		 */
		private static $log;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
		}

		/**
		 * Flag to indicate whether or not we need to load code for / support subscriptions.
		 *
		 * @var bool
		 */
		private $subscription_support_enabled = false;

		/**
		 * Secret API Key.
		 * @var string
		 */
		private $secret_key = '';

		/**
		 * Compatibility mode, capture only from on hold to processing or completed, and not also from processing to completed if this is checked.
		 * @var string
		 */
		private $compatibility_mode = 'yes';

		/**
		 * Capture mode, is this instant or delayed
		 * @var string
		 */
		private $capture_mode = 'instant';

		/**
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 25 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );

		}

		/**
		 * Holds an instance of the Paylike client
		 *
		 * @var $paylike_client \Paylike\Paylike
		 */
		public $paylike_client;

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment.
			if ( self::get_environment_warning() ) {
				return;
			}
			include_once( plugin_basename( 'vendor/autoload.php' ) );
			// Init the gateway itself.
			$this->init_gateways();
			$this->db_update();
			// make sure client is set
			$secret = $this->get_secret_key();
			add_action( 'wp_ajax_paylike_log_transaction_data', array( $this, 'log_transaction_data' ) );
			add_action( 'wp_ajax_nopriv_paylike_log_transaction_data', array( $this, 'log_transaction_data' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			if ( ! $this->get_compatibility_mode() ) {
				add_action( 'woocommerce_order_status_processing_to_completed', array( $this, 'capture_payment' ) );
			} else {
				add_action( 'woocommerce_order_status_processing_to_completed', array(
					$this,
					'maybe_capture_warning'
				) );
			}
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
		}

		/**
		 * Set secret API Key.
		 *
		 * @param string $secret_key
		 */
		public function set_secret_key( $secret_key ) {
			$this->secret_key = $secret_key;
			$this->secret_key = apply_filters( 'paylike_secret_key', $this->secret_key );
			if ( '' != $this->secret_key ) {
				$this->paylike_client = new Paylike\Paylike( $this->secret_key );
			}
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation.
		 */
		public function check_environment() {
			$environment_warning = self::get_environment_warning();
			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'paylike_bad_environment', 'error', $environment_warning );
			}
			// Check if secret key present. Otherwise prompt, via notice, to go to
			// setting.
			if ( ! class_exists( 'Paylike\Paylike' ) ) {
				include_once( plugin_basename( 'vendor/autoload.php' ) );
			}
			$secret = $this->get_secret_key();
			if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'paylike' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				/* translators: %1$s is replaced with settings link */
				$this->add_admin_notice( 'paylike_prompt_connect', 'notice notice-warning', sprintf( __( 'Paylike will not work until you <a href="%s">configure your Paylike api keys</a>.', 'woocommerce-gateway-paylike' ), $setting_link ) );
			}
		}

		/**
		 * @return string
		 * Get the stored secret key depending on the type of payment sent.
		 */
		public function get_secret_key() {
			if ( ! $this->secret_key ) {
				$options = get_option( 'woocommerce_paylike_settings' );
				if ( isset( $options['testmode'], $options['secret_key'], $options['test_secret_key'] ) ) {
					$this->set_secret_key( 'yes' === $options['testmode'] ? $options['test_secret_key'] : $options['secret_key'] );
				}
			}

			return $this->secret_key;
		}

		/**
		 * Check if we are in incompatibility mode or not
		 */
		public function get_compatibility_mode() {
			$options = get_option( 'woocommerce_paylike_settings' );
			if ( isset( $options['compatibility_mode'] ) ) {
				$this->compatibility_mode = ( 'yes' === $options['compatibility_mode'] ? $options['compatibility_mode'] : 0 );
			} else {
				$this->compatibility_mode = 0;
			}

			return $this->compatibility_mode;
		}

		/**
		 * Check if the capture mode is instant or delayed
		 */
		public function get_capture_mode() {
			$options = get_option( 'woocommerce_paylike_settings' );
			if ( isset( $options['capture'] ) ) {
				$this->capture_mode = ( 'instant' === $options['capture'] ? $options['capture'] : 0 );
			} else {
				$this->capture_mode = 0;
			}

			return $this->capture_mode;
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_PAYLIKE_MIN_PHP_VER, '<' ) ) {
				/* translators: %1$s is replaced with the php version %2$s is replaced with the current php version */
				$message = __( 'WooCommerce Paylike - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paylike' );

				return sprintf( $message, WC_PAYLIKE_MIN_PHP_VER, phpversion() );
			}
			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce Paylike requires WooCommerce to be activated to work.', 'woocommerce-gateway-paylike' );
			}
			if ( version_compare( WC_VERSION, WC_PAYLIKE_MIN_WC_VER, '<' ) ) {
				/* translators: %1$s is replaced with the woocommerce version %2$s is replaced with the current woocommerce version */
				$message = __( 'WooCommerce Paylike - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paylike' );

				return sprintf( $message, WC_PAYLIKE_MIN_WC_VER, WC_VERSION );
			}
			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce Paylike - cURL is not installed.', 'woocommerce-gateway-paylike' );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-paylike' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @return string Setting link
		 * @since 1.0.0
		 *
		 */
		public function get_setting_link() {
			if ( function_exists( 'WC' ) ) {
				$use_id_as_section = version_compare( WC()->version, '2.6', '>=' );
			} else {
				$use_id_as_section = false;
			}
			$section_slug = $use_id_as_section ? 'paylike' : strtolower( 'WC_Gateway_Paylike' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array(
					'a' => array(
						'href' => array(),
					),
				) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$this->subscription_support_enabled = true;
			}
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}
			include_once( plugin_basename( 'includes/helpers.php' ) );
			include_once( plugin_basename( 'includes/legacy.php' ) );
			include_once( plugin_basename( 'includes/currencies.php' ) );
			include_once( plugin_basename( 'includes/class-wc-paylike-payment-tokens.php' ) );
			include_once( plugin_basename( 'includes/class-wc-paylike-payment-token.php' ) );
			include_once( plugin_basename( 'includes/class-wc-gateway-paylike.php' ) );

			/** Third Party */
			include_once( plugin_basename( 'third-party/upstroke/upstroke-woocommerce-one-click-upsell-paylike.php' ) );

			load_plugin_textdomain( 'woocommerce-gateway-paylike', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
			if ( $this->subscription_support_enabled ) {
				require_once( plugin_basename( 'includes/class-wc-gateway-paylike-addons.php' ) );
			}
		}

		/**
		 *  Perform database updates when changing structure
		 */
		public function db_update() {
			$current_db_version = get_option( 'paylike_db_version', 1 );
			$current_sdk_version = get_option( 'paylike_sdk_version', 0 );
			$beta_sdk_version = get_option( 'paylike_beta_version', 0 );

			$options = get_option( 'woocommerce_paylike_settings' );
			if ( 1 == $current_db_version ) {

				if ( 'yes' === $options['capture'] ) {
					$options['capture'] = 'instant';
				} else {
					$options['capture'] = 'delayed';
				}
				$current_db_version ++;
			}
			if ( 2 == $current_db_version ) {

				if ( 'yes' === $options['direct_checkout'] ) {
					$options['checkout_mode'] = 'before_order';
				} else {
					$options['checkout_mode'] = 'after_order';
				}
				$current_db_version ++;
			}


			if ( $current_sdk_version < WC_PAYLIKE_CURRENT_SDK ) {
				//reset beta checkbox
				$options['use_beta_sdk'] = 'no';

				update_option( 'paylike_sdk_version', WC_PAYLIKE_CURRENT_SDK );
				update_option( 'paylike_beta_version', WC_PAYLIKE_BETA_SDK );
			}

			update_option( 'woocommerce_paylike_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_paylike', $options ) );
			update_option( 'paylike_db_version', $current_db_version );
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
			if ( $this->subscription_support_enabled ) {
				$methods[] = 'WC_Gateway_Paylike_Addons';
			} else {
				$methods[] = 'WC_Gateway_Paylike';
			}

			return $methods;
		}

		/**
		 * Return order that can be captured, check for partial void or refund
		 *
		 * @param WC_Order $order
		 *
		 * @return mixed
		 */
		protected function get_order_amount( $order ) {
			return $order->get_total() - $order->get_total_refunded();
		}

		/**
		 * Capture payment when the order is changed from on-hold to complete or processing
		 *
		 * @param $order_id int
		 */
		public function capture_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'paylike' != $order->get_payment_method() ) {
				return false;
			}
			$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
			$captured = get_post_meta( $order_id, '_paylike_transaction_captured', true );
			if ( ! ( $transaction_id && 'no' === $captured ) ) {
				return false;
			}
			$data = array(
				'amount'   => $this->get_paylike_amount( $this->get_order_amount( $order ), dk_get_order_currency( $order ) ),
				'currency' => dk_get_order_currency( $order ),
			);
			WC_Paylike::log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			try {
				$result = $this->paylike_client->transactions()->capture( $transaction_id, $data );
				$this->handle_capture_result( $order, $result );
			} catch ( \Paylike\Exception\ApiException $exception ) {
				WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Capture has failed!' );
			}
		}

		/**
		 * @param WC_Order $order
		 * @param array    $result // array result returned by the api wrapper.
		 */
		public function handle_capture_result( $order, $result ) {

			if ( 1 == $result['successful'] ) {
				$order->add_order_note(
					__( 'Paylike capture complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['id'] . PHP_EOL .
					__( 'Payment Amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['capturedAmount'], $result['currency'] ) . PHP_EOL .
					__( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['created']
				);
				WC_Paylike::log( 'Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				update_post_meta( get_woo_id( $order ), '_paylike_transaction_id', $result['id'] );
				update_post_meta( get_woo_id( $order ), '_paylike_transaction_captured', 'yes' );
			} else {
				$error = array();
				foreach ( $result as $field_error ) {
					$error[] = $field_error['field'] . ':' . $field_error['message'];
				}
				WC_Paylike::log( 'Issue: Capture has failed there has been an issue with the transaction.' . json_encode( $result ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				$error_message = implode( ' ', $error );
				$order->add_order_note(
					__( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
				);
			}

		}

		/**
		 * Handler for moving from processing to completed
		 * while the compatibility mode is enabled
		 *
		 * @param $order_id int
		 */
		public function maybe_capture_warning( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'paylike' != $order->get_payment_method() ) {
				return false;
			}
			$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
			$captured = get_post_meta( $order_id, '_paylike_transaction_captured', true );
			if ( ! ( $transaction_id && 'no' === $captured ) ) {
				return false;
			}

			// at this point the user has moved an order that is not captured
			// which was paid via paylike and we will add a warning stating that no capture has taken place

			$order->add_order_note(
				__( '<b>Warning:</b> Order has not been captured!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				__( 'Compatibility mode is enabled. This means that moving the order from processing to completed does not actually capture the order.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				__( 'You can either move the order to on hold and then to processing or go to settings and uncheck the `Compatibility Mode` checkbox.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
				__( 'If you choose the latter, then you can come back to the order move it back to processing, then move it again to completed.', 'woocommerce-gateway-paylike' )
			);
		}

		/**
		 * @param      $total
		 * @param null $currency
		 *  Format the amount based on the currency
		 *
		 * @return string
		 */
		public function get_paylike_amount( $total, $currency = null ) {
			if ( '' == $currency ) {
				$currency = get_woocommerce_currency();
			}
			$multiplier = get_paylike_currency_multiplier( $currency );
			$amount = ceil( $total * $multiplier ); // round to make sure we are always minor units.
			if ( function_exists( 'bcmul' ) ) {
				$amount = ceil( bcmul( $total, $multiplier ) );
			}

			return $amount;
		}

		/**
		 * Convert the cents amount into the full readable amount
		 *
		 * @param        $amount_in_cents
		 * @param string $currency
		 *
		 * @return string
		 */
		public function real_amount( $amount_in_cents, $currency = '' ) {
			return strip_tags( wc_price( $amount_in_cents / get_paylike_currency_multiplier( $currency ), array(
				'ex_tax_label' => false,
				'currency'     => $currency,
			) ) );
		}

		/**
		 * Cancel pre-auth on refund/cancellation
		 *
		 * @param int $order_id
		 */
		public function cancel_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'paylike' != dk_get_order_data( $order, 'payment_method' ) ) {
				return false;
			}
			$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
			$captured = get_post_meta( $order_id, '_paylike_transaction_captured', true );
			if ( ! $transaction_id ) {
				return false;
			}
			$data = array(
				'amount' => $this->get_paylike_amount( $this->get_order_amount( $order ), dk_get_order_currency( $order ) ),
			);
			$currency = dk_get_order_currency( $order );
			if ( 'yes' == $captured ) {
				WC_Paylike::log( "Info: Starting to refund {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				try {
					$result = $this->paylike_client->transactions()->refund( $transaction_id, $data );
					$this->handle_refund_result( $order, $result );
				} catch ( \Paylike\Exception\ApiException $exception ) {
					WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Automatic Refund has failed!' );
				}
			} else {
				WC_Paylike::log( "Info: Starting to void {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				try {
					$result = $this->paylike_client->transactions()->void( $transaction_id, $data );
					$this->handle_refund_result( $order, $result );
				} catch ( \Paylike\Exception\ApiException $exception ) {
					WC_Paylike::handle_exceptions( $order, $exception, 'Issue: Automatic Void has failed!' );
				}
			}

		}

		/**
		 * @param WC_Order $order
		 * @param          $result // array result returned by the api wrapper
		 */
		function handle_refund_result( $order, $result ) {
			if ( 1 == $result['successful'] ) {
				$order->add_order_note(
					__( 'Paylike refund complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['id'] . PHP_EOL .
					__( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['refundedAmount'], $result['currency'] ) . PHP_EOL .
					__( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['created']
				);
				WC_Paylike::log( 'Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				delete_post_meta( get_woo_id( $order ), '_paylike_transaction_captured' );
			} else {
				$error = array();
				foreach ( $result as $field_error ) {
					$error[] = $field_error['field'] . ':' . $field_error['message'];
				}
				$error_message = implode( ' ', $error );
				WC_Paylike::log( 'Issue: Capture has failed there has been an issue with the transaction.' . json_encode( $result ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				$order->add_order_note(
					__( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
				);
			}
		}

		/**
		 * @param WC_Order $order
		 * @param          $result // array result returned by the api wrapper
		 */
		function handle_void_result( $order, $result ) {
			if ( 1 == $result['successful'] ) {
				$order->add_order_note(
					__( 'Paylike void complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['id'] . PHP_EOL .
					__( 'Voided amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['voidedAmount'], $result['currency'] ) . PHP_EOL .
					__( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['created']
				);
				WC_Paylike::log( 'Info: Void was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				delete_post_meta( get_woo_id( $order ), '_paylike_transaction_captured' );
			} else {
				$error = array();
				foreach ( $result as $field_error ) {
					$error[] = $field_error['field'] . ':' . $field_error['message'];
				}
				$error_message = implode( ' ', $error );
				WC_Paylike::log( 'Issue: Void has failed there has been an issue with the transaction.' . json_encode( $result ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				$order->add_order_note(
					__( 'Unable to void transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
				);

			}
		}

		/**
		 * Log exceptions.
		 *
		 * @param WC_Order                        $order
		 * @param \Paylike\Exception\ApiException $exception
		 * @param string                          $context
		 */
		public static function handle_exceptions( $order, $exception, $context = '' ) {
			if ( ! $exception ) {
				return false;
			}
			$exception_type = get_class( $exception );
			$message = '';
			switch ( $exception_type ) {
				case 'Paylike\\Exception\\NotFound':
					$message = __( 'Transaction not found! Check the transaction key used for the operation.', 'woocommerce-gateway-paylike' );
					break;
				case 'Paylike\\Exception\\InvalidRequest':
					$message = __( 'The request is not valid! Check if there is any validation bellow this message and adjust if possible, if not, and the problem persists, contact the developer.', 'woocommerce-gateway-paylike' );
					break;
				case 'Paylike\\Exception\\Forbidden':
					$message = __( 'The action is not allowed! You do not have the rights to perform the action. Reach out to hello@paylike.io to get help.', 'woocommerce-gateway-paylike' );
					break;
				case 'Paylike\\Exception\\Unauthorized':
					$message = __( 'The operation is not properly authorized! Check the credentials set in settings for Paylike.', 'woocommerce-gateway-paylike' );
					break;
				case 'Paylike\\Exception\\Conflict':
					$message = __( 'The operation leads to a conflict! The same transaction is being requested for modification at the same time. Try again later.', 'woocommerce-gateway-paylike' );
					break;
				case 'Paylike\\Exception\\ApiConnection':
					$message = __( 'Network issues! Check your connection and try again.', 'woocommerce-gateway-paylike' );
					break;
				case 'Paylike\\Exception\\ApiException':
					$message = __( 'There has been a server issue! If this problem persists contact the developer.', 'woocommerce-gateway-paylike' );
					break;
			}
			$message = __( 'Error: ', 'woocommerce-gateway-paylike' ) . $message;
			$error_message = WC_Gateway_Paylike::get_response_error( $exception->getJsonBody() );
			if ( $context ) {
				$message = $context . PHP_EOL . $message;
			}
			if ( $error_message ) {
				$message = $message . PHP_EOL . 'Validation:' . PHP_EOL . $error_message;
			}

			if ( $order ) {
				$order->add_order_note( $message );
			}
			WC_Paylike::log( $message . PHP_EOL . json_encode( $exception->getJsonBody() ) );

			return $message;
		}

		/**
		 *  Ajax handler used to log details of the response of the popup
		 */
		public function log_transaction_data() {
			$err = $_POST['err'];
			$res = $_POST['res'];
			WC_Paylike::log( 'Info: Popup transaction data: err -> ' . json_encode( $err ) . ' - res:' . json_encode( $res ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
			die();
		}


		/**
		 * Log function
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->debug( $message, array( 'source' => 'woocommerce-gateway-paylike' ) );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $message );
			}
		}


	}

	$GLOBALS['wc_paylike'] = WC_Paylike::get_instance();
}
