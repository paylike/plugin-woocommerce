<?php
/*
 * Plugin Name: WooCommerce Paylike Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-paylike/
 * Description: Allow customers to pay with credit cards via the Paylike gateway in your WooCommerce store.
 * Author: Derikon Development
 * Author URI: https://derikon.com/
 * Version: 1.0.0
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
define( 'WC_PAYLIKE_VERSION', '3.0.5' );
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
         * Secret API Key.
         * @var string
         */
        private $secret_key = '';

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
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
            add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
            add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
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
            if ( $secret_key != '' ) {
                Paylike\Client::setKey( $secret_key );
            }
        }

        /**
         * Allow this class and other classes to add slug keyed notices (to avoid duplication)
         */
        public function add_admin_notice( $slug, $class, $message ) {
            $this->notices[ $slug ] = array(
                'class'   => $class,
                'message' => $message
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
         * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
         * found or false if the environment has no problems.
         */
        static function get_environment_warning() {
            if ( version_compare( phpversion(), WC_PAYLIKE_MIN_PHP_VER, '<' ) ) {
                $message = __( 'WooCommerce Paylike - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paylike', 'woocommerce-gateway-paylike' );

                return sprintf( $message, WC_PAYLIKE_MIN_PHP_VER, phpversion() );
            }
            if ( ! defined( 'WC_VERSION' ) ) {
                return __( 'WooCommerce Paylike requires WooCommerce to be activated to work.', 'woocommerce-gateway-paylike' );
            }
            if ( version_compare( WC_VERSION, WC_PAYLIKE_MIN_WC_VER, '<' ) ) {
                $message = __( 'WooCommerce Paylike - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paylike', 'woocommerce-gateway-paylike' );

                return sprintf( $message, WC_STRIPE_MIN_WC_VER, WC_VERSION );
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
            $use_id_as_section = version_compare( WC()->version, '2.6', '>=' );
            $section_slug      = $use_id_as_section ? 'paylike' : strtolower( 'WC_Gateway_Paylike' );

            return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
        }

        /**
         * Display any notices we've collected thus far (e.g. for connection, disconnection)
         */
        public function admin_notices() {
            foreach ( (array) $this->notices as $notice_key => $notice ) {
                echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
                echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
                echo "</p></div>";
            }
        }

        /**
         * Initialize the gateway. Called very early - in the context of the plugins_loaded action
         *
         * @since 1.0.0
         */
        public function init_gateways() {
            if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                return;
            }
            include_once( plugin_basename( 'includes/class-wc-gateway-paylike.php' ) );
            load_plugin_textdomain( 'woocommerce-gateway-paylike', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
        }

        /**
         * Add the gateways to WooCommerce
         *
         * @since 1.0.0
         */
        public function add_gateways( $methods ) {
            $methods[] = 'WC_Gateway_Paylike';

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
                    $data   = array(
                        'amount'   => $this->get_paylike_amount( $order->order_total, $order->get_order_currency() ),
                        'currency' => $order->get_order_currency()
                    );
                    $result = Paylike\Transaction::capture( $transaction_id, $data );
                    $this->handle_capture_result( $order, $result );
                }
            }
        }

        /**
         * @param $order
         * @param $result // array result returned by the api wrapper
         */
        function handle_capture_result( $order, $result ) {
            if ( ! $result ) {
                $order->add_order_note(
                    __( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                    __( 'cURL request failed.', 'woocommerce-gateway-paylike' )
                );
            } else {
                if ( 1 == $result['transaction']['successful'] ) {
                    $order->add_order_note(
                        __( 'Paylike capture complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                        __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
                        __( 'Payment Amount: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['amount'] . PHP_EOL .
                        __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
                    );
                    update_post_meta( $order->id, '_paylike_transaction_id', $result['transaction']['id'] );
                    update_post_meta( $order->id, '_paylike_transaction_captured', 'yes' );

                } else {
                    $error = array();
                    foreach ( $result as $field_error ) {
                        $error[] = $field_error['field'] . ':' . $field_error['message'];
                    }
                    $error_message = implode( " ", $error );
                    $order->add_order_note(
                        __( 'Unable to capture transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                        __( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
                    );
                }
            }
        }

        /**
         * @param $total
         * @param null $currency
         *  Format the amount based on the currency
         *
         * @return string
         */
        public function get_paylike_amount( $total, $currency = null ) {
            $zero_decimal_currency = array(
                "CLP",
                "JPY",
                "VND"
            );
            $currency_code         = $currency != '' ? $currency : get_woocommerce_currency();
            if ( in_array( $currency_code, $zero_decimal_currency ) ) {
                $amount = number_format( $total, 0, ".", "" );
            } else {
                $amount = $total * 100;
            }

            return $amount;
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
                    $data = array(
                        'amount' => $this->get_paylike_amount( $order->order_total, $order->get_order_currency() ),
                    );
                    if ( 'yes' == $captured ) {

                        $result = Paylike\Transaction::refund( $transaction_id, $data );
                    } else {
                        $result = Paylike\Transaction::void( $transaction_id, $data );
                    }
                    $this->handle_refund_result( $order, $result );
                }
            }
        }

        /**
         * @param $order
         * @param $result // array result returned by the api wrapper
         */
        function handle_refund_result( $order, $result ) {
            if ( ! $result ) {
                $order->add_order_note(
                    __( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                    __( 'cURL request failed.', 'woocommerce-gateway-paylike' )
                );
            } else {
                if ( 1 == $result['transaction']['successful'] ) {
                    $order->add_order_note(
                        __( 'Paylike refund complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                        __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
                        __( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['amount'] . PHP_EOL .
                        __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
                    );
                    delete_post_meta( $order->id, '_paylike_transaction_captured' );
                } else {
                    $error = array();
                    foreach ( $result as $field_error ) {
                        $error[] = $field_error['field'] . ':' . $field_error['message'];
                    }
                    $error_message = implode( " ", $error );
                    $order->add_order_note(
                        __( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                        __( 'Error :', 'woocommerce-gateway-paylike' ) . $error_message
                    );

                }
            }
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
