<?php

namespace Woocommerce;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * @group woocommerce_quick_test
 */
class WoocommerceQuickTest extends AbstractTestCase {

	public $runner;

//	/**
//	 * @throws NoSuchElementException
//	 * @throws \Facebook\WebDriver\Exception\TimeOutException
//	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
//	 */
//	public function testGeneralFunctions() {
//		$this->runner = new WoocommerceRunner( $this );
//		$this->runner->ready( array(
//				'settings_check' => true,
//			)
//		);
//	}
//
//	/**
//	 *
//	 * @throws NoSuchElementException
//	 * @throws \Facebook\WebDriver\Exception\TimeOutException
//	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
//	 */
//	public function testDkkPaymentBeforeOrderInstant() {
//		$this->runner = new WoocommerceRunner( $this );
//		$this->runner->ready( array(
//				'currency'               => 'DKK',
//				'capture_mode'           => 'instant',
//				'checkout_mode'          => 'before_order',
//				'exclude_manual_payment' => false,
//				'store_payment_method'   => true,
//				'use_existing_token'     => true,
//			)
//		);
//	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testJpyPaymentBeforeOrderDelayed() {
		$this->runner = new WoocommerceRunner( $this );
		$this->runner->ready( array(
				'currency'               => 'JPY',
				'capture_mode'           => 'delayed',
				'checkout_mode'          => 'before_order',
				'exclude_manual_payment' => false,
			)
		);
	}
}