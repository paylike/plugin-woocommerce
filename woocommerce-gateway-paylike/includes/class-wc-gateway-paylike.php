<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Paylike class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Paylike extends WC_Payment_Gateway {

    /**
     * Should we capture Credit cards
     *
     * @var bool
     */
    public $capture;

    /**
     * API access secret key
     *
     * @var string
     */
    public $secret_key;

    /**
     * Api access public key
     *
     * @var string
     */
    public $public_key;

    /**
     * Is test mode active?
     *
     * @var bool
     */
    public $testmode;

    /**
     * Logging enabled?
     *
     * @var bool
     */
    public $logging;

    /**
     * Allowed card types
     *
     * @var bool
     */
    public $card_types;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'paylike';
        $this->method_title       = __( 'Paylike', 'woocommerce-gateway-paylike' );
        $this->method_description = __( 'Paylike enables you to accept credit and debit cards on your WooCommerce platform. If you don\'t already have an account with Paylike, you can create it <a href="https://paylike.io/">here</a>. Need help with the setup? Read our documentation <a href="https://paylike.io/payment-modules/woocommerce-plugin">here</a>.', 'woocommerce-gateway-paylike' );
        $this->has_fields         = true;
        $this->supports           = array(
            'products',
            'refunds',
        );
        // Load the form fields.
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        // Get setting values.
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled' );
        $this->testmode    = 'yes' === $this->get_option( 'testmode' );
        $this->capture     = 'yes' === $this->get_option( 'capture', 'yes' );
        $this->secret_key  = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
        $this->public_key  = $this->testmode ? $this->get_option( 'test_public_key' ) : $this->get_option( 'public_key' );
        $this->logging     = 'yes' === $this->get_option( 'logging' );
        $this->card_types  = $this->get_option( 'card_types' );
        if ( $this->testmode ) {
            $this->description .= PHP_EOL . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4100 0000 0000 0000 with any CVC and a valid expiration date. "<a href="%s">See Documentation</a>".', 'woocommerce-gateway-paylike' ), 'https://github.com/paylike/sdk' );
            $this->description = trim( $this->description );
        }
        if ( $this->secret_key != '' ) {
            Paylike\Client::setKey( $this->secret_key );
        }
        // Hooks.
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'return_handler' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ) );
    }

    /**
     * Get_icon function.
     *
     * @access public
     * @return string
     */
    public function get_icon() {
        $icon = '';
        if ( is_array( $this->card_types ) ) {
            foreach ( $this->card_types as $card_type ) {
                if ( $url = $this->get_active_card_logo_url( $card_type ) ) {
                    $icon .= '<img width="45" src="' . esc_url( $url ) . '" alt="' . esc_attr( strtolower( $card_type ) ) . '" />';
                }
            }
        } else {
            $icon .= '<img  src="' . esc_url( plugins_url( 'images/paylike.png', __FILE__ ) ) . '" alt="Paylike Gateway" />';
        }

        return apply_filters( 'woocommerce_paylike_icon', $icon, $this->id );
    }

    public function get_active_card_logo_url( $type ) {
        $image_type = strtolower( $type );

        return WC_HTTPS::force_https_url( plugins_url( '../assets/images/' . $image_type . '.png', __FILE__ ) );
    }

    /**
     * Get Paylike amount to pay
     *
     * @param float $total Amount due.
     * @param string $currency Accepted currency.
     *
     * @return float|int
     */
    public function get_paylike_amount( $total, $currency = '' ) {
        $zero_decimal_currency = array(
            "CLP",
            "JPY",
            "VND"
        );
        $currency_code         = $currency != '' ? $currency : get_woocommerce_currency();
        if ( in_array( $currency_code, $zero_decimal_currency ) ) {
            $total = number_format( $total, 0, ".", "" );
        } else {
            $total = $total * 100;
        }

        return $total;
    }

    /**
     * Check if SSL is enabled and notify the user
     */
    public function admin_notices() {
        if ( 'no' === $this->enabled ) {
            return;
        }
        // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
        if ( ( function_exists( 'wc_site_is_https' ) && ! wc_site_is_https() ) && ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! class_exists( 'WordPressHTTPS' ) ) ) {
            echo '<div class="error paylike-ssl-message"><p>' . sprintf( __( 'Paylike: <a href="%s">Force SSL</a> is disabled; your checkout page may not be secure! Unless you have a valid SSL certificate and force the checkout pages to be secure, only test mode will be allowed.', 'woocommerce-gateway-paylike' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
        }
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available() {
        $supported_currencies = array(
            'AED',
            'ARS',
            'AUD',
            'AZN',
            'BAM',
            'BGN',
            'BRL',
            'BYR',
            'CAD',
            'CHF',
            'CLP',
            'CNY',
            'CZK',
            'DKK',
            'DOP',
            'EGP',
            'EUR',
            'GBP',
            'HKD',
            'HRK',
            'HUF',
            'ILS',
            'INR',
            'ISK',
            'JPY',
            'LTL',
            'MAD',
            'MXN',
            'MYR',
            'NOK',
            'NZD',
            'PHP',
            'PLN',
            'RON',
            'RSD',
            'RUB',
            'SAR',
            'SEK',
            'SGD',
            'THB',
            'TND',
            'TRY',
            'TWD',
            'UAH',
            'USD',
            'VND',
            'ZAR'
        );
        if ( 'yes' === $this->enabled ) {
            if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
                return false;
            }
            if ( ! $this->secret_key || ! $this->public_key ) {
                return false;
            }
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'paylike_woocommerce_supported_currencies', $supported_currencies ) ) ) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = include( 'settings-paylike.php' );
    }


    /**
     * Process the payment
     *
     * @param int $order_id Reference.
     *
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        WC()->cart->empty_cart();

        // Return thank you page redirect.
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true )
        );
    }

    /**
     * Refund a transaction
     *
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     *
     * @return bool
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order          = wc_get_order( $order_id );
        $transaction_id = get_post_meta( $order_id, '_paylike_transaction_id', true );
        if ( ! $order || ! $transaction_id ) {
            return false;
        }
        $data = array();
        if ( ! is_null( $amount ) ) {
            $data['amount'] = $this->get_paylike_amount( $amount );
        }

        WC_Paylike::log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );
        $captured = get_post_meta( $order_id, '_paylike_transaction_captured', true );
        if ( 'yes' == $captured ) {
            if ( $reason ) {
                $data['descriptor'] = $reason;
            }
            $result = Paylike\Transaction::refund( $transaction_id, $data );
        } else {
            $result = Paylike\Transaction::void( $transaction_id, $data );
        }

        return $this->handle_refund_result( $order, $result, $captured );

    }

    /**
     * Display the pay button on the receipt page.
     *
     * @param $order_id
     */
    public function receipt_page( $order_id ) {
        $order  = wc_get_order( $order_id );
        $amount = $this->get_paylike_amount( $order->order_total, $order->get_order_currency() );
        echo '<p>' . __( 'Thank you for your order, please click below to pay and complete your order.', 'woocommerce-gateway-paylike' ) . '</p>';
        ?>
        <button onclick="pay();"><?php _e( 'Pay Now', 'woocommerce-gateway-paylike' ); ?></button>
        <script src="https://sdk.paylike.io/3.js"></script>
        <script>
            var paylike = Paylike('<?php echo $this->public_key;?>');

            function pay() {

                paylike.popup({
                    title: '<?php echo get_bloginfo( 'name' ); ?>',
                    currency: '<?php echo get_woocommerce_currency() ?>',
                    amount:  <?php echo $amount; ?>,
                    locale: '<?php echo get_locale(); ?>',
                    custom: {
                        orderNo: '<?php echo $order->get_order_number() ?>',
                        email: '<?php echo $order->billing_email ?>',
                        name: '<?php echo $order->billing_first_name . ' ' . $order->billing_last_name ?>',
                        totalTax: '<?php echo $order->get_total_tax()?>',
                        totalShipping: '<?php echo $order->get_total_shipping()?>',
                        customerIP: '<?php echo $this->get_client_ip() ?>',
                    },
                }, function (err, res) {
                    if (err)
                        return console.warn(err);

                    var trxid = res.transaction.id;
                    jQuery("#complete_order").append('<input type="hidden" name="transaction_id" value="' + trxid + '" /> ');
                    document.getElementById("complete_order").submit();
                });
            }
        </script>
        <form id="complete_order" action="<?php echo WC()->api_request_url( get_class( $this ) ) ?>">
            <input type="hidden" name="reference" value="<?php echo $order_id ?>"/>
            <input type="hidden" name="amount" value="<?php echo $this->get_order_total() ?>"/>
            <input type="hidden" name="signature"
                   value="<?php echo $this->get_signature( $order_id ); ?>"/>
        </form>
        <?php
    }

    /**
     * Handle return call from paylike
     */
    public function return_handler() {
        @ob_clean();
        header( 'HTTP/1.1 200 OK' );
        try {
            if ( isset( $_REQUEST['reference'] ) && isset( $_REQUEST['signature'] ) && isset( $_REQUEST['transaction_id'] ) && isset( $_REQUEST['amount'] ) ) {
                $signature = strtoupper( md5( $_REQUEST['amount'] . $_REQUEST['reference'] . $this->public_key ) );
                $order_id  = absint( $_REQUEST['reference'] );
                $order     = wc_get_order( $order_id );
                if ( $signature === $_REQUEST['signature'] ) {
                    $transaction_id = $_REQUEST['transaction_id'];
                    if ( false == $this->capture ) {
                        $result = Paylike\Transaction::fetch( $transaction_id );
                        $this->handle_authorize_result( $order, $result );
                    } else {
                        $data   = array(
                            'amount'   => $this->get_paylike_amount( $order->order_total, $order->get_order_currency() ),
                            'currency' => $order->get_order_currency()
                        );
                        $result = Paylike\Transaction::capture( $transaction_id, $data );
                        $this->handle_capture_result( $order, $result );
                    }
                    wp_redirect( $this->get_return_url( $order ) );
                    exit();
                }
            }
        } catch ( Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            wp_redirect( get_permalink( wc_get_page_id( 'checkout' ) ) );
            exit();
        }
        wp_redirect( get_permalink( wc_get_page_id( 'cart' ) ) );
        exit();
    }

    /**
     * @param $order
     * @param $result // array result returned by the api wrapper
     *
     */
    function handle_authorize_result( $order, $result ) {
        if ( ! $result ) {
            $order->add_order_note(
                __( 'Unable to verify transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                __( 'cURL request failed.', 'woocommerce-gateway-paylike' )
            );
        } elseif (
            1 == $result['transaction']['successful'] &&
            $result['transaction']['currency'] == $order->get_order_currency() &&
            $result['transaction']['custom']['orderNo'] == $order->get_order_number() &&
            $result['transaction']['amount'] == $this->get_paylike_amount( $order->order_total, $order->get_order_currency() )
        ) {
            $order->add_order_note(
                __( 'Paylike authorization complete.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
                __( 'Payment Amount: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['amount'] . PHP_EOL .
                __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
            );
            $order->payment_complete();
            WC()->cart->empty_cart();
            update_post_meta( $order->id, '_paylike_transaction_id', $result['transaction']['id'] );
            update_post_meta( $order->id, '_paylike_transaction_captured', 'no' );

        } else {
            $error = array();
            foreach ( $result as $field_error ) {
                $error[] = $field_error['field'] . ' :' . $field_error['message'];
            }
            $error_message = implode( " ", $error );
            $order->add_order_note(
                __( 'Unable to authorize transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message
            );
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
            if ( 1 == $result['transaction']['successful'] &&
                 $result['transaction']['currency'] == $order->get_order_currency() &&
                 $result['transaction']['custom']['orderNo'] == $order->get_order_number() &&
                 $result['transaction']['amount'] == $this->get_paylike_amount( $order->order_total, $order->get_order_currency() )
            ) {
                $order->add_order_note(
                    __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
                    __( 'Authorized amount: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['amount'] . PHP_EOL .
                    __( 'Captured amount: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['capturedAmount'] . PHP_EOL .
                    __( 'Charge authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created']
                );
                $order->payment_complete();
                WC()->cart->empty_cart();
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
                    __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message
                );
            }
        }
    }

    /**
     * @param $order
     * @param $result // array result returned by the api wrapper
     * @param $captured
     *
     * @return bool
     */
    function handle_refund_result( $order, $result, $captured ) {
        if ( ! $result ) {
            WC_Paylike::log( 'Unable to refund transaction!' . PHP_EOL . 'cURL request failed.' );

            return false;
        } else {
            if ( 1 == $result['transaction']['successful'] ) {
                if ( 'yes' == $captured ) {
                    $refunded_amount = $result['transaction']['refundedAmount'];
                } else {
                    $refunded_amount = $result['transaction']['voidedAmount'];
                }
                $refund_message = __( 'Paylike transaction refunded.', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                                  __( 'Transaction ID: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['id'] . PHP_EOL .
                                  __( 'Refund amount: ', 'woocommerce-gateway-paylike' ) . $refunded_amount.PHP_EOL.
                                  __( 'Transaction authorized at: ', 'woocommerce-gateway-paylike' ) . $result['transaction']['created'];
                $order->add_order_note( $refund_message );
                WC_Paylike::log( 'Success!' . PHP_EOL . $refund_message );

                return true;
            } else {
                $error = array();
                foreach ( $result as $field_error ) {
                    $error[] = $field_error['field'] . ': ' . $field_error['message'];
                }
                $error_message = implode( " ", $error );
                $order->add_order_note(
                    __( 'Unable to refund transaction!', 'woocommerce-gateway-paylike' ) . PHP_EOL .
                    __( 'Error: ', 'woocommerce-gateway-paylike' ) . $error_message
                );
                WC_Paylike::log( 'Unable to refund transaction!' . PHP_EOL . 'Error :' . $error_message );

                return false;
            }
        }
    }

    /**
     * Get transaction signature
     *
     * @param $order_id
     *
     * @return string
     */
    function get_signature( $order_id ) {
        return strtoupper( md5( $this->get_order_total() . $order_id . $this->public_key ) );
    }

    /**
     * Retrieve client ip.
     *
     * @return string
     */
    function get_client_ip() {
        if ( getenv( 'HTTP_CLIENT_IP' ) ) {
            $ip_address = getenv( 'HTTP_CLIENT_IP' );
        } elseif ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
            $ip_address = getenv( 'HTTP_X_FORWARDED_FOR' );
        } elseif ( getenv( 'HTTP_X_FORWARDED' ) ) {
            $ip_address = getenv( 'HTTP_X_FORWARDED' );
        } elseif ( getenv( 'HTTP_FORWARDED_FOR' ) ) {
            $ip_address = getenv( 'HTTP_FORWARDED_FOR' );
        } elseif ( getenv( 'HTTP_FORWARDED' ) ) {
            $ip_address = getenv( 'HTTP_FORWARDED' );
        } elseif ( getenv( 'REMOTE_ADDR' ) ) {
            $ip_address = getenv( 'REMOTE_ADDR' );
        } else {
            $ip_address = '0.0.0.0';
        }

        return $ip_address;
    }

    /**
     * Sends the failed order email to admin
     *
     * @version 3.1.0
     * @since 3.1.0
     *
     * @param int $order_id
     *
     * @return null
     */
    public function send_failed_order_email( $order_id ) {
        $emails = WC()->mailer()->get_emails();
        if ( ! empty( $emails ) && ! empty( $order_id ) ) {
            $emails['WC_Email_Failed_Order']->trigger( $order_id );
        }
    }

}
