<?php
/*
 * Plugin Name: WooCommerce Paylike Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-paylike/
 * Description: Allow customers to pay with credit cards via the Paylike gateway in your WooCommerce store.
 * Author: Derikon Development
 * Author URI: https://derikon.com/
 * Version: 1.4.2
 * Text Domain: woocommerce-gateway-paylike
 * Domain Path: /languages
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
 * GNU General Public License for more details.
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
define( 'WC_PAYLIKE_VERSION', '1.4.2' );
define( 'WC_PAYLIKE_MIN_PHP_VER', '5.3.0' );
define( 'WC_PAYLIKE_MIN_WC_VER', '2.5.0' );
define( 'WC_PAYLIKE_MAIN_FILE', __FILE__ );
define( 'WC_PAYLIKE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
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
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}
			include_once( plugin_basename( 'includes/api/Paylike/Client.php' ) );
			// Init the gateway itself
			$this->init_gateways();
			$this->db_update();
			add_action( 'wp_ajax_paylike_log_transaction_data', array( $this, 'log_transaction_data' ) );
			add_action( 'wp_ajax_nopriv_paylike_log_transaction_data', array( $this, 'log_transaction_data' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			if ( ! $this->get_compatibility_mode() ) {
				add_action( 'woocommerce_order_status_processing_to_completed', array( $this, 'capture_payment' ) );
			}
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
		}

		/**
		 * Set secret API Key.
		 *
		 * @param string $secret_key
		 */
		public function set_secret_key( $secret_key ) {
			$this->secret_key = $secret_key;
			if ( '' != $secret_key ) {
				Paylike\Client::setKey( $secret_key );
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
			if ( ! class_exists( 'Paylike\Client' ) ) {
				include_once( plugin_basename( 'includes/api/Paylike/Client.php' ) );
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
		 * @since 1.0.0
		 *
		 * @return string Setting link
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
			include_once( plugin_basename( 'includes/legacy.php' ) );
			include_once( plugin_basename( 'includes/currencies.php' ) );
			include_once( plugin_basename( 'includes/class-wc-gateway-paylike.php' ) );
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
			$options            = get_option( 'woocommerce_paylike_settings' );
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
		 * Capture payment when the order is changed from on-hold to complete or processing
		 *
		 * @param  int $order_id
		 */
		public function capture_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'paylike' === $order->payment_method ) {
				$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
				$captured       = get_post_meta( $order_id, '_paylike_transaction_captured', true );
				if ( $transaction_id && 'no' === $captured ) {
					$data = array(
						'amount'   => $this->get_paylike_amount( $order->get_total(), dk_get_order_currency( $order ) ),
						'currency' => dk_get_order_currency( $order ),
					);
					WC_Paylike::log( "Info: Starting to capture {$data['amount']} in {$data['currency']}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					$result = Paylike\Transaction::capture( $transaction_id, $data );
					$this->handle_capture_result( $order, $result );
				}
			}
		}

		/**
		 * @param WC_Order $order
		 * @param          $result // array result returned by the api wrapper
		 */
		function handle_capture_result( $order, $result ) {
			if ( ! $result ) {

				WC_Paylike::log( 'Fatal Error: Capture has failed, the result from curl was false' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				$order->add_order_note(
					__( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'cURL request failed.', 'woocommerce-gateway-paylike' )
				);
			} else {
				if ( 1 == $result['transaction']['successful'] ) {
					$order->add_order_note(
						__( 'Paylike capture complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
						__( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
						__( 'Payment Amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['transaction']['amount'], $result['transaction']['currency'] ) . PHP_EOL .
						__( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
					);
					WC_Paylike::log( 'Info: Capture was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					update_post_meta( get_woo_id( $order ), '_paylike_transaction_id', $result['transaction']['id'] );
					update_post_meta( get_woo_id( $order ), '_paylike_transaction_captured', 'yes' );

				} else {
					$error = array();
					foreach ( $result as $field_error ) {
						$error[] = $field_error['field'] . ':' . $field_error['message'];
					}
					WC_Paylike::log( 'Fatal Error: Capture has failed there has been an issue with the transaction.' . json_encode( $result ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					$error_message = implode( " ", $error );
					$order->add_order_note(
						__( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
						__( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
					);
				}
			}
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
			$amount     = ceil( $total * $multiplier ); // round to make sure we are always minor units

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
		function real_amount( $amount_in_cents, $currency = '' ) {
			return strip_tags( wc_price( $amount_in_cents / get_paylike_currency_multiplier( $currency ), array(
				'ex_tax_label' => false,
				'currency'     => $currency,
			) ) );
		}

		/**
		 * Cancel pre-auth on refund/cancellation
		 *
		 * @param  int $order_id
		 */
		public function cancel_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'paylike' === $order->payment_method ) {
				$transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
				$captured       = get_post_meta( $order_id, '_paylike_transaction_captured', true );
				if ( $transaction_id ) {
					$data     = array(
						'amount' => $this->get_paylike_amount( $order->get_total(), dk_get_order_currency( $order ) ),
					);
					$currency = dk_get_order_currency( $order );
					if ( 'yes' == $captured ) {
						WC_Paylike::log( "Info: Starting to refund {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
						$result = Paylike\Transaction::refund( $transaction_id, $data );
						$this->handle_refund_result( $order, $result );
					} else {
						WC_Paylike::log( "Info: Starting to void {$data['amount']} in {$currency}" . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
						$result = Paylike\Transaction::void( $transaction_id, $data );
						$this->handle_void_result( $order, $result );
					}
				}
			}
		}

		/**
		 * @param WC_Order $order
		 * @param          $result // array result returned by the api wrapper
		 */
		function handle_refund_result( $order, $result ) {
			if ( ! $result ) {
				WC_Paylike::log( 'Fatal Error: Refund has failed, the result from curl was false' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				$order->add_order_note(
					__( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'cURL request failed.', 'woocommerce-gateway-paylike' )
				);
			} else {
				if ( 1 == $result['transaction']['successful'] ) {
					$order->add_order_note(
						__( 'Paylike refund complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
						__( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
						__( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['transaction']['amount'], $result['transaction']['currency'] ) . PHP_EOL .
						__( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
					);
					WC_Paylike::log( 'Info: Refund was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					delete_post_meta( get_woo_id( $order ), '_paylike_transaction_captured' );
				} else {
					$error = array();
					foreach ( $result as $field_error ) {
						$error[] = $field_error['field'] . ':' . $field_error['message'];
					}
					$error_message = implode( ' ', $error );
					WC_Paylike::log( 'Fatal Error: Capture has failed there has been an issue with the transaction.' . json_encode( $result ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					$order->add_order_note(
						__( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
						__( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
					);

				}
			}
		}

		/**
		 * @param WC_Order $order
		 * @param          $result // array result returned by the api wrapper
		 */
		function handle_void_result( $order, $result ) {
			if ( ! $result ) {
				WC_Paylike::log( 'Fatal Error: Void has failed, the result from curl was false' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
				$order->add_order_note(
					__( 'Unable to void transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
					__( 'cURL request failed.', 'woocommerce-gateway-paylike' )
				);
			} else {
				if ( 1 == $result['transaction']['successful'] ) {
					$order->add_order_note(
						__( 'Paylike void complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
						__( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
						__( 'Voided amount: ', 'woocommerce-gateway-paylike' ) . $this->real_amount( $result['transaction']['amount'], $result['transaction']['currency'] ) . PHP_EOL .
						__( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
					);
					WC_Paylike::log( 'Info: Void was successful' . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					delete_post_meta( get_woo_id( $order ), '_paylike_transaction_captured' );
				} else {
					$error = array();
					foreach ( $result as $field_error ) {
						$error[] = $field_error['field'] . ':' . $field_error['message'];
					}
					$error_message = implode( ' ', $error );
					WC_Paylike::log( 'Fatal Error: Void has failed there has been an issue with the transaction.' . json_encode( $result ) . PHP_EOL . ' -- ' . __FILE__ . ' - Line:' . __LINE__ );
					$order->add_order_note(
						__( 'Unable to void transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
						__( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
					);

				}
			}
		}

		/**
		 *  Ajax handler used to log details of the response of the popup
		 */
		function log_transaction_data() {
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
			self::$log->add( 'woocommerce-gateway-paylike', $message );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $message );
			}
		}



	}

	$GLOBALS['wc_paylike'] = WC_Paylike::get_instance();
}
