<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Payment_Gateway_Via_Paylike_For_Woocommerce
 */

if ( PHP_MAJOR_VERSION >= 8 ) {
	echo "The scaffolded tests cannot currently be run on PHP 8.0+. See https://github.com/wp-cli/scaffold-command/issues/285" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

$_tests_dir = __DIR__.'/../tmp/wordpress-tests-lib';

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/../../../../Code/woocommerce/wp-content/plugins/woocommerce/woocommerce.php';
	update_option('woocommerce_db_version',3.5);
	require dirname( dirname( __FILE__ ) ) . '/../../../../Code/woocommerce/wp-content/plugins/woocommerce-subscriptions/woocommerce-subscriptions.php';
	require dirname( dirname( __FILE__ ) ) . '/woocommerce-gateway-paylike/woocommerce-gateway-paylike.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

function is_woocommerce_active() {
	return true;
}

function woothemes_queue_update($file, $file_id, $product_id) {
	return true;
}

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

$wc_tests_framework_base_dir = dirname( dirname( __FILE__ ) ) . '/../../../../Code/woocommerce/wp-content/plugins/woocommerce/tests/legacy/';
require_once( $wc_tests_framework_base_dir . 'includes/wp-http-testcase.php' );
require_once( $wc_tests_framework_base_dir . 'framework/class-wc-mock-session-handler.php' );
require_once( $wc_tests_framework_base_dir . 'framework/class-wc-unit-test-case.php' );
require_once( $wc_tests_framework_base_dir . 'framework/helpers/class-wc-helper-product.php'  );
require_once( $wc_tests_framework_base_dir . 'framework/helpers/class-wc-helper-coupon.php'  );
require_once( $wc_tests_framework_base_dir . 'framework/helpers/class-wc-helper-fee.php'  );
require_once( $wc_tests_framework_base_dir . 'framework/helpers/class-wc-helper-shipping.php'  );
require_once( $wc_tests_framework_base_dir . 'framework/helpers/class-wc-helper-customer.php'  );
require_once( $wc_tests_framework_base_dir . 'framework/helpers/class-wc-helper-order.php'  );
