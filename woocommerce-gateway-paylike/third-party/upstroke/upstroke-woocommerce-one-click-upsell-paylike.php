<?php
/**
 * Plugin Name: UpStroke: WooCommerce One Click Upsell for PayLike
 * Plugin URI: https://buildwoofunnels.com
 * Description: An UpStroke add-on which adds compatibility with 'WooCommerce PayLike Payment Gateway' to work for upsells.
 * Version: 1.0.0
 * Author: WooFunnels
 * Author URI: https://buildwoofunnels.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: upstroke-woocommerce-one-click-upsell-paylike
 *
 * Requires at least: 5.0
 * Tested up to: 5.4.2
 * WC requires at least: 4.0
 * WC tested up to: 4.3.0
 * WooFunnels: true
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Paylike_Compatibility' ) ) {

	class WFOCU_Paylike_Compatibility {

		/**
		 * @var $instance
		 */
		public static $instance;

		public $gateway_dir_path = '/gateways/';
		public $class_prefix = 'WFOCU_Paylike_Gateway_';

		/**
		 * WFOCU_Paylike_Compatibility constructor.
		 */
		public function __construct() {
			$this->init_constants();

			//Including gateways integration files
			spl_autoload_register( array( $this, 'paylike_integration_autoload' ) );
			$this->init_hooks();
		}

		/**
		 * Initializing constants
		 */
		public function init_constants() {
			define( 'WFOCU_PAYLIKE_VERSION', '1.0.0' );
			define( 'WFOCU_PAYLIKE_BASENAME', plugin_basename( __FILE__ ) );
			define( 'WFOCU_PAYLIKE_PLUGIN_DIR', __DIR__ );
			define( 'WFOCU_PAYLIKE_PLUGIN_FULLNAME', 'UpStroke: WooCommerce One Click Upsell for PayLike' );
		}

		/**
		 * Auto-loading the payment classes as they called.
		 *
		 * @param $class_name
		 */
		public function paylike_integration_autoload( $class_name ) {

			if ( false !== strpos( $class_name, $this->class_prefix ) ) {
				require_once WFOCU_PAYLIKE_PLUGIN_DIR . $this->gateway_dir_path . 'class-' . WFOCU_Common::slugify_classname( $class_name ) . '.php';
			}

		}

		/**
		 * @return WFOCU_Paylike_Compatibility
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Adding functions on hooks
		 */
		public function init_hooks() {

			// Initialize Localization
			add_action( 'init', array( $this, 'wfocu_paylike_compatibility_localization' ) );

			//Adding paylike gateway on global settings on upstroke admin page
			add_filter( 'wfocu_wc_get_supported_gateways', array( $this, 'wfocu_paylike_gateways_integration' ), 10, 1 );

		}

		/**
		 * Adding text-domain
		 */
		public static function wfocu_paylike_compatibility_localization() {
			load_plugin_textdomain( 'upstroke-woocommerce-one-click-upsell-paylike', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Adding gateways name for choosing on UpStroke global settings page
		 */
		public function wfocu_paylike_gateways_integration( $gateways ) {
			$gateways['paylike'] = 'WFOCU_Paylike_Gateway_Credit_Cards';

			return $gateways;
		}

	}

	if ( defined( 'WC_PAYLIKE_VERSION' ) ) {
		WFOCU_Paylike_Compatibility::get_instance();
	}
}
