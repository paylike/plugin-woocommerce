<?php

namespace Woocommerce;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * @group woocommerce_full_test
 */
class WoocommerceFullTest extends AbstractTestCase {

	public $runner;


	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentAfterOrderInstant() {
		$this->runner = new WoocommerceRunner( $this );
		$this->runner->ready( array(
				'capture_mode'           => 'instant',
				'checkout_mode'          => 'after_order',
				'exclude_manual_payment' => true,
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentBeforeOrderDelayed() {
		$this->runner = new WoocommerceRunner( $this );
		$this->runner->ready( array(
				'capture_mode'           => 'delayed',
				'checkout_mode'          => 'before_order',
				'exclude_manual_payment' => true,
				'store_payment_method'   => true,
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentAfterOrderDelayed() {
		$this->runner = new WoocommerceRunner( $this );
		$this->runner->ready( array(
				'capture_mode'           => 'delayed',
				'checkout_mode'          => 'before_order',
				'store_payment_method'   => true,
				'use_existing_token'     => true,
				'exclude_manual_payment' => true,
			)
		);
	}

	/**
	 *
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testDkkPaymentBeforeOrderInstant() {
		$this->runner = new WoocommerceRunner( $this );
		$this->runner->ready( array(
				'currency'               => 'DKK',
				'capture_mode'           => 'instant',
				'checkout_mode'          => 'before_order',
				'exclude_manual_payment' => false,
			)
		);
	}


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
