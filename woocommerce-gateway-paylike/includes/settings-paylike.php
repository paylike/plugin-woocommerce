<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
return apply_filters( 'wc_paylike_settings',
    array(
        'enabled'         => array(
            'title'       => __( 'Enable/Disable', 'woocommerce-gateway-paylike' ),
            'label'       => __( 'Enable Paylike', 'woocommerce-gateway-paylike' ),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no'
        ),
        'title'           => array(
            'title'       => __( 'Title', 'woocommerce-gateway-paylike' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paylike' ),
            'default'     => __( 'Credit card (Paylike)', 'woocommerce-gateway-paylike' ),
            'desc_tip'    => true,
        ),
        'description'     => array(
            'title'       => __( 'Description', 'woocommerce-gateway-paylike' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paylike' ),
            'default'     => __( 'Secure payment with credit card via &copy; <a href="https://paylike.io" target="_blank">Paylike</a>', 'woocommerce-gateway-paylike' ),
            'desc_tip'    => true,
        ),
        'testmode'        => array(
            'title'       => __( 'Test mode', 'woocommerce-gateway-paylike' ),
            'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-paylike' ),
            'type'        => 'checkbox',
            'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-paylike' ),
            'default'     => 'yes',
            'desc_tip'    => true,
        ),
        'secret_key'      => array(
            'title'       => __( 'Live App Key', 'woocommerce-gateway-paylike' ),
            'type'        => 'text',
            'description' => __( 'This is the App Key found in App Settings.', 'woocommerce-gateway-paylike' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'public_key'      => array(
            'title'       => __( 'Live Public Key', 'woocommerce-gateway-paylike' ),
            'type'        => 'text',
            'description' => __( 'This is the API Key found in Account Dashboard.', 'woocommerce-gateway-paylike' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'test_secret_key' => array(
            'title'       => __( 'Test App Key', 'woocommerce-gateway-paylike' ),
            'type'        => 'text',
            'description' => __( 'This is the App Key found in App Settings.', 'woocommerce-gateway-paylike' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'test_public_key' => array(
            'title'       => __( 'Test Public key', 'woocommerce-gateway-paylike' ),
            'type'        => 'text',
            'description' => __( 'This is the API Key found in Account Dashboard.', 'woocommerce-gateway-paylike' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'direct_checkout'         => array(
            'title'       => __( 'Direct Checkout', 'woocommerce-gateway-paylike' ),
            'label'       => __( 'Pay on checkout', 'woocommerce-gateway-paylike' ),
            'type'        => 'checkbox',
            'description' => __( 'Whether or not to show the payment popup on the checkout page or show it on the receipt page. Uncheck this if you have issues on the checkout page with the payment.', 'woocommerce-gateway-paylike' ),
            'default'     => 'yes',
            'desc_tip'    => true,
        ),
        'capture'         => array(
            'title'       => __( 'Capture', 'woocommerce-gateway-paylike' ),
            'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-paylike' ),
            'type'        => 'checkbox',
            'description' => __( 'Whether or not to immediately capture the transaction. When unchecked, the transaction only gets authorized.', 'woocommerce-gateway-paylike' ),
            'default'     => 'yes',
            'desc_tip'    => true,
        ),
        'card_types'      => array(
            'title'    => __( 'Accepted Cards', 'woocommerce' ),
            'type'     => 'multiselect',
            'class'    => 'chosen_select',
            'css'      => 'width: 350px;',
            'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
            'options'  => array(
                'mastercard' => 'MasterCard',
                'visa'       => 'Visa',
            ),
            'default'  => array( 'mastercard', 'maestro', 'visa', 'visaelectron' ),
        ),
        'logging'         => array(
            'title'       => __( 'Logging', 'woocommerce-gateway-paylike' ),
            'label'       => __( 'Log debug messages', 'woocommerce-gateway-paylike' ),
            'type'        => 'checkbox',
            'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-paylike' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
    )
);
