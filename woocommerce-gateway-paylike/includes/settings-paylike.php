<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_paylike_settings',
	array(
		'enabled'            => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-paylike' ),
			'label'       => __( 'Enable Paylike', 'woocommerce-gateway-paylike' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'              => array(
			'title'       => __( 'Payment method title', 'woocommerce-gateway-paylike' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paylike' ),
			'default'     => __( 'Credit card (Paylike)', 'woocommerce-gateway-paylike' ),
			'desc_tip'    => true,
		),
		'description'        => array(
			'title'       => __( 'Payment method description', 'woocommerce-gateway-paylike' ),
			'type'        => 'textarea',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paylike' ),
			'default'     => __( 'Secure payment with credit card via &copy; <a href="https://paylike.io" target="_blank">Paylike</a>', 'woocommerce-gateway-paylike' ),
			'desc_tip'    => true,
		),
		'popup_title'        => array(
			'title'       => __( 'Payment popup title', 'woocommerce-gateway-paylike' ),
			'type'        => 'text',
			'description' => __( 'The text shown in the popup where the customer inserts the card details.', 'woocommerce-gateway-paylike' ),
			'default'     => get_bloginfo( 'name' ),
			'desc_tip'    => true,
		),
		'testmode'           => array(
			'title'       => __( 'Transaction mode', 'woocommerce-gateway-paylike' ),
			'type'        => 'select',
			'description' => __( 'In test mode, you can create a successful transaction with the card number 4100 0000 0000 0000 with any CVC and a valid expiration date.', 'woocommerce-gateway-paylike' ),
			'options'     => array(
				'yes' => __( 'Test', 'woocommerce-gateway-paylike' ),
				'no'  => __( 'Live', 'woocommerce-gateway-paylike' ),
			),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'secret_key'         => array(
			'title'       => __( 'Live mode App Key', 'woocommerce-gateway-paylike' ),
			'type'        => 'text',
			'description' => __( 'Get it from your Paylike dashboard', 'woocommerce-gateway-paylike' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'public_key'         => array(
			'title'       => __( 'Live mode Public Key', 'woocommerce-gateway-paylike' ),
			'type'        => 'text',
			'description' => __( 'Get it from your Paylike dashboard', 'woocommerce-gateway-paylike' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_secret_key'    => array(
			'title'       => __( 'Test mode App Key', 'woocommerce-gateway-paylike' ),
			'type'        => 'text',
			'description' => __( 'Get it from your Paylike dashboard', 'woocommerce-gateway-paylike' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_public_key'    => array(
			'title'       => __( 'Test mode Public key', 'woocommerce-gateway-paylike' ),
			'type'        => 'text',
			'description' => __( 'Get it from your Paylike dashboard', 'woocommerce-gateway-paylike' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'compatibility_mode' => array(
			'title'       => __( 'Compatibility Mode', 'woocommerce-gateway-paylike' ),
			'label'       => __( 'Don\'t capture from processing to completed', 'woocommerce-gateway-paylike' ),
			'type'        => 'checkbox',
			'description' => __( 'When this is checked you can capture payment by moving an order to On Hold and then to complete or processing status, when its not checked you can also complete them from processing to complete as well as the other 2 options', 'woocommerce-gateway-paylike' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		/*'direct_checkout'    => array(
			'title'       => __( 'Direct Checkout', 'woocommerce-gateway-paylike' ),
			'label'       => __( 'Pay on checkout', 'woocommerce-gateway-paylike' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to show the payment popup on the checkout page or show it on the receipt page. Uncheck this if you have issues on the checkout page with the payment.', 'woocommerce-gateway-paylike' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		*/
		'checkout_mode'      => array(
			'title'    => __( 'Checkout mode', 'woocommerce-gateway-paylike' ),
			'type'     => 'select',
			'options'  => array(
				'before_order' => __( 'Payment before order created', 'woocommerce-gateway-paylike' ),
				'after_order'  => __( 'Redirect to payment page after order created', 'woocommerce-gateway-paylike' ),

			),
			'default'  => 'before_order',
			'desc_tip' => true,
		),
		'capture'            => array(
			'title'       => __( 'Capture mode', 'woocommerce-gateway-paylike' ),
			'type'        => 'select',
			'options'     => array(
				'instant' => __( 'Instant' ),
				'delayed' => __( 'Delayed' ),
			),
			'description' => __( 'If you deliver your product instantly (e.g. a digital product), choose Instant mode. If not, use Delayed. In Delayed mode, you can capture the order when moving from on hold to complete or from on hold to processing.', 'woocommerce-gateway-paylike' ),
			'default'     => 'instant',
			'desc_tip'    => true,
		),
		'card_types'         => array(
			'title'    => __( 'Accepted Cards', 'woocommerce' ),
			'type'     => 'multiselect',
			'class'    => 'chosen_select',
			'css'      => 'width: 350px;',
			'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
			'options'  => array(
				'mastercard'   => 'MasterCard',
				'maestro'      => 'Maestro',
				'visa'         => 'Visa',
				'visaelectron' => 'Visa Electron',
			),
			'default'  => array( 'mastercard', 'maestro', 'visa', 'visaelectron' ),
		)
	)
);
