<?php


namespace Woocommerce;

use Facebook\WebDriver\Exception\NoAlertOpenException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\ConfigProvider;

class WoocommerceRunner extends WoocommerceTestHelper {

	/**
	 * @param $args
	 *
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function ready( $args ) {
		$this->set( $args );
		$this->go();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function loginAdmin() {
		$this->goToPage( 'wp-admin', '#user_login' );
		while ( ! $this->hasValue( '#user_login', $this->user ) ) {
			$this->typeLogin();
		}
		$this->click( '#wp-submit' );
		$this->waitForPage( 'wp-admin/' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function changeCurrency() {
		$this->goToPage( 'wp-admin/admin.php?page=wc-settings', '#select2-woocommerce_currency-container' );
		$this->click( '#select2-woocommerce_currency-container' );
		$this->click( "//*[contains(@id, '" . $this->currency . "')]" );

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function changeDecimal() {
		$this->goToPage( 'wp-admin/admin.php?page=wc-settings', '#select2-woocommerce_currency-container' );
		$this->type( '#woocommerce_price_decimal_sep', '.' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function disableEmail() {
		if ( $this->stop_email === true ) {
			$this->goToPage( 'wp-admin/options-general.php?page=disable-emails', 'disable_emails[wp_mail]' );
			$this->checkbox( 'disable_emails[wp_mail]' );
		}
	}

	/**
	 *
	 */
	public function checkoutMode() {
		$this->click( '#woocommerce_paylike_checkout_mode' );
		$this->click( "//*[contains(@value, '" . $this->checkout_mode . "')]" );
	}

	/**
	 *
	 */
	public function captureMode() {
		$this->click( '#woocommerce_paylike_capture' );
		$this->click( "//*[contains(@value, '" . $this->capture_mode . "')]" );

	}

	/**
	 * @param $status
	 *
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function moveOrderToStatus( $status ) {
		$this->click( '#select2-order_status-container' );
		$this->click( "//*[contains(text(), '" . $status . "')]" );
		if ( ! $this->isSelected( '#order_status', $status ) ) {
			$this->moveOrderToStatus( $status );
		}
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function changeMode() {
		$this->goToPage( 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=paylike', '#woocommerce_paylike_checkout_mode' );
		$this->checkoutMode();
		$this->captureMode();
		$this->submitAdmin();
	}

	/**
	 *
	 */
	public function submitAdmin() {
		$this->click( '.submit .button-primary.woocommerce-save-button' );
	}

	/**
	 *
	 */
	public function proceedToCheckout() {
		$this->wd->get( $this->helperGetUrl( 'checkout' ) );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function selectOrder() {
		$this->waitForElement( '.woocommerce-order' );
		$this->goToPage( 'wp-admin/edit.php?post_type=shop_order', '.order-view' );
		$this->wd->navigate()->refresh();
		$this->click( '.order-view' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function placeOrder() {
		$this->waitForElement( '#place_order' );
		$this->waitElementDisappear( '.blockUI.blockOverlay' );
		$this->click( '#place_order' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function addProductToCart() {
		$this->waitForElement( '.button.product_type_simple.add_to_cart_button.ajax_add_to_cart' );
		$this->click( '.button.product_type_simple.add_to_cart_button.ajax_add_to_cart' );
		$this->waitForElement( ".added_to_cart.wc-forward" );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function clearCartItem() {
		try {
			$cartCount = $this->getText( '.site-header-cart span.count' );
		} catch ( StaleElementReferenceException $exception ) {
			// try again
			$cartCount = $this->getText( '.site-header-cart span.count' );
		}
		$cartCount = preg_replace( "/[^0-9.]/", "", $cartCount );
		if ( $cartCount ) {
			$this->moveToElement( '#site-header-cart' );
			$this->waitForElement( '.mini_cart_item .remove' );
			$productRemoves = $this->findElements( '.mini_cart_item .remove' );
			$this->moveToElement( '#site-header-cart' );
			$this->waitElementDisappear( '.blockUI.blockOverlay' );
			try {
				$productRemoves[0]->click();
			} catch ( StaleElementReferenceException $exception ) {
				// can happen
			}
			$this->waitElementDisappear( '.blockUI.blockOverlay' );
			$this->clearCartItem();

		}
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function logInFrontend() {
		$this->elementExists( '.woocommerce-form-login .lost_password' );
		$this->click( '.woocommerce-form-login .showlogin' );
		$this->elementExists( '.woocommerce-form-login .form-row' );
		$this->type( '#username', 'ionut.plati@gmail.com' );
		$this->type( '#password', 'admin#522' );
		$this->click( '.woocommerce-form-login .form-row  input[type="submit"]' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function choosePaylike() {
		$this->waitElementDisappear( '.blockUI.blockOverlay' );
		try {
			$this->click( '.payment_method_paylike label' );
		} catch ( \Exception $exception ) {
			$this->waitElementDisappear( '.blockUI.blockOverlay' );
			$this->click( '.payment_method_paylike label' );
		}
		$this->waitForElement( '#place_order' );
		$this->click( '#place_order' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function manualConfirmOrder() {
		$this->waitForElement( '.payment_method_paylike' );
		$this->click( '.payment_method_paylike' );
		$this->waitForElement( '#place_order' );
		$this->click( '#place_order' );


	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function confirmOrder() {
		$this->waitForElement( '#paylike-payment-button' );
		$this->click( '#paylike-payment-button' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function finalPaylike() {
		if ( $this->checkout_mode == 'before_order' && ! $this->manual_payment ) {
			$amount = (int) $this->getElementData( '#paylike-payment-data', 'amount' );
			$expectedAmount = $this->getText( '.order-total span.amount' );
			$expectedAmount = preg_replace( "/[^0-9.]/", "", $expectedAmount );
			$expectedAmount = trim( $expectedAmount, '.' );
			$expectedAmount = ceil( round( $expectedAmount, 3 ) * get_paylike_currency_multiplier( $this->currency ) );
			$this->main_test->assertEquals( $expectedAmount, $amount, "Checking minor amount for " . $this->currency );
		}
		$this->popupPaylike();
		$this->waitForElement( '.woocommerce-order' );
		// because the title of the page matches the checkout title, we need to use the order received class on body
		$this->main_test->assertEquals( 'Order received', $this->getText( '.entry-title' ), "USD" );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function paymentPage() {
		$this->waitForElement( ".wc-order-status a" );
		$this->click( ".wc-order-status a" );

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function addOrder() {
		$this->goToPage( 'wp-admin/edit.php?post_type=shop_order' );
		$this->click( '.page-title-action' );
		$this->click( '#select2-customer_user-container' );
		$this->wd->getKeyboard()->sendKeys( "s" );
		$this->waitForElement( '.select2-results__option--highlighted' );
		$this->pressEnter();
		$this->selectValue( '#_payment_method', "paylike" );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function addItem() {
		$this->click( ".add-line-item", false );
		$this->click( ".add-order-item" );
		try {
			$this->waitForElement( "#wc-backbone-modal-dialog" );
		} catch ( \Exception $exception ) {
			$this->click( ".add-line-item", false );
			$this->click( ".add-order-item" );
		}
		$this->click( '.wc-backbone-modal .select2-selection' );
		$this->wd->getKeyboard()->sendKeys( "Hoo" );
		$this->waitForElement( ".select2-results__option--highlighted" );
		$this->pressEnter();
		$this->click( "#btn-ok" );
		$this->waitElementDisappear( '.blockUI.blockOverlay' );
		$this->click( ".save_order", false );
	}

	/**
	 *
	 *
	 */
	public function verifyTransactionNote() {
		$this->waitForElement( '.note_content p' );
		$text = $this->pluckElement( '.note_content p', 1 )->getText();
		$messages = explode( "\n", $text );
		if ( $this->capture_mode == 'instant' ) {
			$this->main_test->assertEquals( 'Paylike capture complete.', $messages[0], "Checking order note for capture." );
		} elseif ( $this->capture_mode == 'delayed' ) {
			$this->main_test->assertEquals( 'Paylike authorization complete.', $messages[0], "Checking order note for authorization." );
		}
	}

	/**
	 *
	 *
	 */
	public function verifyOrder() {
		$this->goToPage( 'wp-admin/edit.php?post_type=shop_order', '.order-view' );
		$this->click( '.order-view' );
		$this->verifyTransactionNote();
	}

	/**
	 *
	 *
	 */
	public function addToCart() {

		$this->click( ".single_add_to_cart_button" );
		$this->waitForElement( ".button.wc-forward" );
		$this->click( ".button.wc-forward" );

	}

	public function clearAdminMessage() {
		$message = $this->findElements( '#message' );
		if ( $message[0] ) {
			$dismiss = $this->findChild( '.notice-dismiss', $message[0] );
			$dismiss->click();
		}

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function saveOrder() {
		$this->waitForPageReload( function () {
			$this->click( '.save_order' );
		}, 5000 );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function popupPaylike() {
		try {
			$this->waitForElement( '.paylike.overlay .payment form #card-number' );
			$this->type( '.paylike.overlay .payment form #card-number', 41000000000000 );
			$this->type( '.paylike.overlay .payment form #card-expiry', '11/22' );
			$this->type( '.paylike.overlay .payment form #card-code', '122' );
			$this->click( '.paylike.overlay .payment form button' );
		} catch ( NoSuchElementException $exception ) {
			$this->confirmOrder();
			$this->popupPaylike();
		}

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function runSubscription() {
		$this->waitForElement( '.woocommerce_subscriptions_related_orders a' );
		$orderId = $this->getText( '.woocommerce_subscriptions_related_orders a' );
		$orderId = str_replace( '#', '', "subscription_id => $orderId" );
		$this->goToPage( 'wp-admin/tools.php?page=action-scheduler&orderby=schedule&order=desc', '.column-schedule' );
		$this->waitForElement( '.args.column-args' );
		$scheduledActions = $this->findElements( '#the-list tr.iedit' );
		/** @var RemoteWebElement $scheduledAction */
		foreach ( $scheduledActions as $scheduledAction ) {
			$code = $this->findChild( 'td.args code', $scheduledAction );
			if ( $orderId === $code->getText() ) {
				$this->moveToElement( $scheduledAction );
				$this->click( $this->findChild( '.process', $scheduledAction ) );
				break;
			};
		}
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function refund() {
		$this->click( '.refund-items' );
		$this->waitForElement( 'refund_amount' );
		$refund = preg_match_all( '!\d+!', $this->getText( '.woocommerce-Price-amount.amount' ), $refund_value );
		$refund_value = $refund_value[0];
		$this->type( '.refund .refund_line_total', $refund_value[0] );
		$this->click( '.do-api-refund' );

		try {
			$this->acceptAlert();
		}catch ( NoAlertOpenException $exception ) {
			$this->click( '.do-api-refund' );
			$this->acceptAlert();
		}

		try {
			$this->waitElementDisappear( '.blockUI.blockOverlay' );
		} catch ( NoSuchElementException $e ) {
			// the element may have already dissapeared
		}
		$this->waitForElement( '.note_content p' );
		$text = $this->pluckElement( '.note_content p', 0 )->getText();
		if ( $text == 'Order status changed from Processing to Refunded.' || $text == 'Order status changed from Completed to Refunded.' ) {
			$text = $this->pluckElement( '.note_content p', 1 )->getText();
		}
		$messages = explode( "\n", $text );
		$this->main_test->assertEquals( 'Paylike transaction refunded.', $messages[0], "Refunded" );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function capture() {

		$this->selectValue( '#order_status', 'wc-on-hold' );
		$this->saveOrder();
		$this->selectValue( '#order_status', 'wc-completed' );
		$this->saveOrder();
		$text = $this->pluckElement( '.note_content p', 1 )->getText();
		if ( $text == 'Order status changed from On hold to Completed.' ) {
			$text = $this->pluckElement( '.note_content p', 0 )->getText();
		}
		$messages = explode( "\n", $text );
		$this->main_test->assertEquals( 'Paylike capture complete.', $messages[0], "Delayed capture" );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function checkNoCaptureWarning() {
		$this->moveOrderToStatus( 'Completed' );
		$this->click( '.save_order' );
		$this->waitForElement( '#message' );
		$text = $this->pluckElement( '.note_content p', 0 )->getText();
		$messages = explode( "\n", $text );
		$this->main_test->assertEquals( 'Warning: Order has not been captured!', $messages[0], "Not captured warning" );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function verifyKeyValid() {
		$this->goToPage( 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=paylike', '#woocommerce_paylike_test_secret_key' );
		$this->type( '#woocommerce_paylike_test_secret_key', 'test' );
		$this->submitAdmin();
		$this->waitForElement( '#message' );
		$messages = $this->getText( '#message' );
		$this->main_test->assertEquals( 'The private key doesn\'t seem to be valid Error: The request is not valid! Check if there is any validation bellow this message and adjust if possible, if not, and the problem persists, contact the developer.', $messages, "Private key not valid" );
		$this->type( '#woocommerce_paylike_test_public_key', 'test' );
		$this->submitAdmin();
		$this->waitForElement( '#message' );
		$messages = $this->getText( '#message' );
		$this->main_test->assertEquals( 'The test public key doesn\'t seem to be valid', $messages, "Public key not valid" );


	}

	/**
	 *  Insert user and password on the login screen
	 */
	private function typeLogin() {
		$this->type( '#user_login', $this->user );
		$this->type( '#user_pass', $this->pass );
	}

	/**
	 * @param $args
	 */
	private function set( $args ) {
		foreach ( $args as $key => $val ) {
			$name = $key;
			if ( isset( $this->{$name} ) ) {
				$this->{$name} = $val;
			}
		}
	}

	/**
	 * @param $page
	 *
	 * @return string
	 */
	private function helperGetUrl( $page ) {
		return $this->base_url . '/' . $page;
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function manualPayment() {
		$this->manual_payment = true;
		$this->addOrder();
		$this->addItem();
		$this->paymentPage();
		$this->manualConfirmOrder();
		if ( $this->checkout_mode == 'after_order' ) {
			$this->confirmOrder();
		}
		$this->finalPaylike();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function settings() {
		$this->changeCurrency();
		$this->submitAdmin();
		$this->disableEmail();
		$this->changeMode();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function directPayment() {
		$this->goToPage( '/product/sunglasses/', '.single_add_to_cart_button' );
		$this->clearCartItem();
		$this->addToCart();
		$this->proceedToCheckout();
		$this->choosePaylike();
		if ( $this->checkout_mode == 'after_order' ) {
			$this->confirmOrder();
		}
		$this->finalPaylike();
		$this->selectOrder();
		if ( $this->capture_mode == 'delayed' ) {
			$this->checkNoCaptureWarning();
			$this->capture();
		}
		$this->refund();
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function verifyKey() {
		$this->verifyKeyValid();

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function subscriptionPayment() {
		$this->goToPage( 'product/subscription/', '.single_add_to_cart_button' );
		$this->clearCartItem();
		$this->addToCart();
		$this->proceedToCheckout();
		$this->choosePaylike();
		if ( $this->checkout_mode == 'after_order' ) {
			$this->confirmOrder();
		}
		$this->popupPaylike();
		$this->selectOrder();
		$this->runSubscription();
		$this->verifyOrder();

	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function getVersions() {
		$this->goToPage( 'wp-admin/plugins.php' );
		$this->waitForElement( '#the-list' );
		$woo = $this->getPluginVersion( 'woocommerce/woocommerce.php' );
		$paylike = $this->getPluginVersion( 'payment-gateway-via-paylike-for-woocommerce/woocommerce-gateway-paylike.php' );

		return [ 'ecommerce' => $woo, 'plugin' => $paylike ];
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function outputVersions() {
		$versions = $this->getVersions();
		$this->main_test->log( '----VERSIONS----' );
		$this->main_test->log( 'WooCommerce %s', $versions['ecommerce'] );
		$this->main_test->log( 'Paylike %s', $versions['plugin'] );
	}

	private function getPluginVersion( $file ) {
		$element = $this->wd->findElement( WebDriverBy::cssSelector( 'tr[data-plugin="' . $file . '"] .plugin-version-author-uri' ) );
		$version = $this->getText( $element );
		$version = explode( '|', $version );

		return $version[0];
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function logVersionsRemotly() {
		$versions = $this->getVersions();
		$this->wd->get( getenv( 'REMOTE_LOG_URL' ) . '&key=' . $this->get_slug( $versions['ecommerce'] ) . '&tag=woocommerce&view=html&' . http_build_query( $versions ) );
		$this->waitForElement( '#message' );
		$message = $this->getText( '#message' );
		$this->main_test->assertEquals( 'Success!', $message, "Remote log failed" );
	}


	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function orderCleanup() {
		$this->goToPage( 'wp-admin/edit.php?post_type=shop_order' );
		$this->click( '#cb-select-all-1' );
		$this->selectValue( '#bulk-action-selector-top', 'trash' );
		$this->click( '#doaction2' );
		try {
			$this->wd->switchTo()->alert()->accept();
			$this->acceptAlert();
			$this->waitForElement( '#message' );
			$this->goToPage( 'wp-admin/edit.php?post_status=trash&post_type=shop_order', '#delete_all' );
			$this->click( '#delete_all' );
			$this->waitForElement( '#message' );
		} catch ( NoAlertOpenException $exception ) {
			// we may not have the alert so just move on
		}
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function settingsCheck() {
		$this->orderCleanup();
		$this->outputVersions();
		$this->verifyKey();
		$this->changeDecimal();
		$this->submitAdmin();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function go() {
		$this->changeWindow();
		$this->loginAdmin();

		if ( $this->log_version ) {
			$this->logVersionsRemotly();

			return $this;
		}

		if ( $this->settings_check ) {
			$this->settingsCheck();

			return $this;
		}

		$this->settings();
		$this->directPayment();
		if ( ! $this->exclude_manual_payment ) {
			$this->manualPayment();
		}
		if ( ! $this->exclude_subscription ) {
			$this->subscriptionPayment();
		}
	}

	/**
	 *
	 */
	private function changeWindow() {
		$this->wd->manage()->window()->setSize( new WebDriverDimension( 1600, 996 ) );
	}


}

