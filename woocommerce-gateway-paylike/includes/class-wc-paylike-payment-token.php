<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Payment_Token_Paylike extends WC_Payment_Token {
	/** @protected string Token Type String */
	protected $type = 'paylike';

	/**
	 * Returns the last four digits.
	 *
	 * @param string $context
	 *
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}
}