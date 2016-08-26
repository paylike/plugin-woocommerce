<?php
/*
* Plugin Name: Paylike Payment for WooCommerce
* Plugin URI: https://wordpress.org/plugins/paylike-payments
* Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via paylike.
* Version: 1.0
* Author: Syed Nazrul Hassan
* Author URI: https://nazrulhassan.wordpress.com/
* Author Email : nazrulhassanmca@gmail.com
*
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
function paylike_init()
{


function add_paylike_gateway_class( $methods )
{
$methods[] = 'WC_paylike_Gateway';
return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_paylike_gateway_class' );

if(class_exists('WC_Payment_Gateway'))
{
class WC_paylike_Gateway extends WC_Payment_Gateway
{

protected $_current_order = null;
public function __construct()
{

$this->id               = 'paylike';
$this->icon             = plugins_url( 'images/paylike.png' , __FILE__ ) ;
$this->has_fields       = true;
$this->method_title     = 'Paylike Cards Settings';
$this->init_form_fields();
$this->init_settings();
$this->supports                 = array('products','refunds');
$this->title               	   	= $this->get_option( 'paylike_title' );
$this->paylike_description       = $this->get_option( 'paylike_description');

$this->paylike_testpublickey     = $this->get_option( 'paylike_testpublickey' );
$this->paylike_testappkey     = $this->get_option( 'paylike_testappkey' );

$this->paylike_livepublickey     = $this->get_option( 'paylike_livepublickey' );
$this->paylike_liveappkey     = $this->get_option( 'paylike_liveappkey' );

$this->paylike_sandbox           = $this->get_option( 'paylike_sandbox' );
$this->paylike_authorize_only    = $this->get_option( 'paylike_authorize_only');
;
$this->paylike_cardtypes         = $this->get_option( 'paylike_cardtypes');

$this->paylike_zerocurrency    = array("CLP","JPY","VND","BYR");

if(!defined("PAYLIKE_TRANSACTION_MODE"))
{ define("PAYLIKE_TRANSACTION_MODE"  , ($this->paylike_authorize_only =='yes'? true : false)); }



if (is_admin())
{
add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
add_action('admin_notices' , array($this, 'do_ssl_check'    ));
}



add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'return_handler' ) );
add_action( 'woocommerce_order_status_completed', array( $this, 'capture_payment' ) );

$this->publickey = 'yes'== $this->paylike_sandbox ? $this->paylike_testpublickey:$this->paylike_livepublickey;

$this->appkey = 'yes'== $this->paylike_sandbox ? $this->paylike_testappkey:$this->paylike_liveappkey;


}



public function do_ssl_check()
{
if( 'yes'  != $this->paylike_sandbox && "no" == get_option( 'woocommerce_force_ssl_checkout' )  && "yes" == $this->enabled ) {
echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
}
}

public function admin_options()
{
?>
<h3><?php _e( 'Paylike addon for Woocommerce', 'woocommerce' ); ?></h3>
<p><?php  _e( 'Paylike enables you to accept credit and debit cards on your WooCommerce platform. If you don\'t already have an account with Paylike, you can create it <a href="https://paylike.io" target="_blank">here</a>. Need help with the setup? Read our documentation  <a href="#" target="_blank">here</a>', 'woocommerce' ); ?></p>
<table class="form-table">
<?php $this->generate_settings_html(); ?>
</table>
<?php
}



public function init_form_fields()
{

$this->form_fields = array(
'enabled' => array(
'title' => __( 'Enable/Disable', 'woocommerce' ),
'type' => 'checkbox',
'label' => __( 'Enable Paylike', 'woocommerce' ),
'default' => 'yes'
),
'paylike_title' => array(
'title' => __( 'Title', 'woocommerce' ),
'type' => 'text',
'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
'default' => __( 'Credit card (Paylike)', 'woocommerce' ),
'desc_tip'      => true,
),
'paylike_description' => array(
'title' => __( 'Description', 'woocommerce' ),
'type' => 'textarea',
'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
'default' => __( 'Secure payment via &copy; <a href="https://paylike.io" target="_blank">Paylike</a>', 'woocommerce' ),
'desc_tip'      => true,
),
'paylike_sandbox' => array(
'title'       => __( 'Test mode', 'woocommerce' ),
'type'        => 'checkbox',
'label'       => __( 'Enable Paylike sandbox (Live Mode if unchecked)', 'woocommerce' ),
'description' => __( 'If checked it\'s in sanbox mode and if unchecked it\'s in live mode', 'woocommerce' ),
'desc_tip'      => true,
'default'     => 'no',
),

'paylike_testpublickey' => array(
'title' => __( 'Test public key', 'woocommerce' ),
'type' => 'text',
'description' => __( 'This is the API Key found in Account Dashboard.', 'woocommerce' ),
'default' => '',
'desc_tip'      => true,
'placeholder' => 'Paylike test api Key'
),

'paylike_testappkey' => array(
'title' => __( 'Test App Key', 'woocommerce' ),
'type' => 'text',
'description' => __( 'This is the App Key found in App Settings.', 'woocommerce' ),
'default' => '',
'desc_tip'      => true,
'placeholder' => 'Paylike test app key'
),

'paylike_livepublickey' => array(
'title' => __( 'Live public key', 'woocommerce' ),
'type' => 'text',
'description' => __( 'This is the API Key found in Account Dashboard.', 'woocommerce' ),
'default' => '',
'desc_tip'      => true,
'placeholder' => 'Paylike live api key'
),



'paylike_liveappkey' => array(
'title' => __( 'Live App Key', 'woocommerce' ),
'type' => 'text',
'description' => __( 'This is the App Key found in App Settings.', 'woocommerce' ),
'default' => '',
'desc_tip'      => true,
'placeholder' => 'Paylike live app key'
),


'paylike_authorize_only' => array(
'title'       => __( 'Authorize Only', 'woocommerce' ),
'type'        => 'checkbox',
'label'       => __( 'Enable Authorize Only Mode (Authorize & Capture If Unchecked)', 'woocommerce' ),
'description' => __( 'If checked will only authorize the credit card only upon checkout.', 'woocommerce' ),
'desc_tip'      => true,
'default'     => 'no',
),

'paylike_cardtypes' => array(
'title'    => __( 'Accepted Cards', 'woocommerce' ),
'type'     => 'multiselect',
'class'    => 'chosen_select',
'css'      => 'width: 350px;',
'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
'options'  => array(
'mastercard'       => 'MasterCard',
'maestro'          => 'Maestro',
'visa'             => 'Visa',
'visaelectron'     => 'Visa Electron'
),
'default' => array( 'mastercard', 'maestro', 'visa', 'visaelectron' ),
),

);
}



//Function to check IP
function get_client_ip()
{
$ipaddress = '';
if (getenv('HTTP_CLIENT_IP'))
$ipaddress = getenv('HTTP_CLIENT_IP');
else if(getenv('HTTP_X_FORWARDED_FOR'))
$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
else if(getenv('HTTP_X_FORWARDED'))
$ipaddress = getenv('HTTP_X_FORWARDED');
else if(getenv('HTTP_FORWARDED_FOR'))
$ipaddress = getenv('HTTP_FORWARDED_FOR');
else if(getenv('HTTP_FORWARDED'))
$ipaddress = getenv('HTTP_FORWARDED');
else if(getenv('REMOTE_ADDR'))
$ipaddress = getenv('REMOTE_ADDR');
else
$ipaddress = '0.0.0.0';
return $ipaddress;
}

//End of function to check IP

public function get_description() {
return apply_filters( 'woocommerce_gateway_description',$this->paylike_description, $this->id );
}


/*Is Avalaible*/
public function is_available() {
if( 'yes'== $this->paylike_sandbox  && (empty($this->paylike_testpublickey) || empty($this->paylike_testappkey) ) ) {return false;
}

if( 'yes'!= $this->paylike_sandbox  && (empty($this->paylike_livepublickey) || empty($this->paylike_liveappkey) ) ) {return false;
}

if ( ! in_array( get_woocommerce_currency(), apply_filters( 'paylike_woocommerce_supported_currencies', array( 'AED','ARS','AUD','AZN','BAM','BGN','BRL','BYR','CAD','CHF','CLP','CNY','CZK','DKK','DOP','EGP','EUR','GBP','HKD','HRK','HUF','ILS','INR','ISK','JPY','LTL','MAD','MXN','MYR','NOK','NZD','PHP','PLN','RON','RSD','RUB','SAR','SEK','SGD','THB','TND','TRY','TWD','UAH','USD','VND','ZAR' ) ) ) ) {return false;}

return true;
}
/*end is availaible*/



/*Get Icon*/
public function get_icon() {
$icon = '';
if(is_array($this->paylike_cardtypes))
{
foreach ( $this->paylike_cardtypes as $card_type ) {

if ( $url = $this->paylike_get_active_card_logo_url( $card_type ) ) {

$icon .= '<img width="45" src="'.esc_url( $url ).'" alt="'.esc_attr( strtolower( $card_type ) ).'" />';
}
}
}
else
{
$icon .= '<img  src="'.esc_url( plugins_url( 'images/paylike.png' , __FILE__ ) ).'" alt="Paylike Gateway" />';
}

return apply_filters( 'woocommerce_paylike_icon', $icon, $this->id );
}

public function paylike_get_active_card_logo_url( $type ) {

$image_type = strtolower( $type );
return  WC_HTTPS::force_https_url( plugins_url( 'images/' . $image_type . '.png' , __FILE__ ) );
}


public function process_payment( $order_id ) {
$order = wc_get_order( $order_id );
$this->_current_order = $order;

return $this->process_hosted_payment();
}


protected function process_hosted_payment( $order = null ) {
if ( empty( $order ) ) {
$order = $this->_current_order;
}

return array(
'result'   => 'success',
'redirect' => $order->get_checkout_payment_url( true )
);
}




public function receipt_page( $order_id ) {

$order = wc_get_order( $order_id );

echo '<p>' . __( 'Thank you for your order, please click below to pay and complete your order.', 'woocommerce' ) . '</p>';

?>
<button onclick="pay();"><?php _e( 'Pay Now', 'woocommerce' ); ?></button>
<script src="https://sdk.paylike.io/3.js"></script>
<script>
var paylike = Paylike('<?php echo $this->publickey ;?>');

function pay(){

paylike.popup({
locale          : '<?php echo get_locale() ?>',
title           : '<?php echo bloginfo('name'); ?>',
description     : '<?php echo '#Order '.$order->get_order_number() ?>',  
currency        : '<?php echo get_woocommerce_currency() ?>',
amount          :  <?php echo $this->paylike_order_total($order) ; ?>,
custom: {
OrderNo     : '<?php echo $order->get_order_number() ?>',
email       : '<?php echo $order->billing_email ?>',
name        : '<?php echo $order->billing_first_name.' '.$order->billing_last_name ?>',
country     : '<?php echo $order->shipping_country ; ?>',
totalTax    : '<?php echo $order->get_total_tax()?>',
totalShipping : '<?php echo $order->get_total_shipping()?>',
customerIP	: '<?php echo $this->get_client_ip() ?>',
},
}, function( err, res ){
if (err)
return console.warn(err);

var trxid = res.transaction.id;
jQuery("#completeorder").append('<input type="hidden" name="transactionid" value="'+trxid+'" /> ');
document.getElementById("completeorder").submit();
});
}
</script>
<form id="completeorder" action="<?php echo WC()->api_request_url( get_class( $this ) ) ?>">
<input type="hidden" name="reference" value="<?php echo $order_id ?>" />
<input type="hidden" name="amount" value="<?php echo $this->get_order_total() ?>" />
<input type="hidden" name="signature" value="<?php echo strtoupper( md5( $this->get_order_total() . $order_id .$this->publickey ) ) ?>" />
</form>
<?php
}


public function return_handler() {
@ob_clean();
header( 'HTTP/1.1 200 OK' );

try 
{

// Array
// (
//     [reference] => 28
//     [amount] => 8.17
//     [signature] => 0F4FCAA36A80E42501EB8ABC067CC561
//     [transactionid] => 57b782674466127803da1523
// )


if (  isset( $_REQUEST['reference'] )  && isset($_REQUEST['amount']) && isset( $_REQUEST['signature'] ) && isset( $_REQUEST['transactionid'] )  ) 
{

$signature = strtoupper( md5( sanitize_text_field($_REQUEST['amount']).sanitize_text_field($_REQUEST['reference']).$this->publickey ) );
$order_id  = absint( sanitize_text_field($_REQUEST['reference'] ) );
$order     = wc_get_order( $order_id );



if ( $signature === sanitize_text_field($_REQUEST['signature']) ) 
{
$transactionid = sanitize_text_field($_REQUEST['transactionid']);

if(true == PAYLIKE_TRANSACTION_MODE)
{
//Authorize only Transaction
$verifyurl = 'https://api.paylike.io/transactions/'.$transactionid ; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$verifyurl);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERPWD, ":" .$this->appkey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
$result = curl_exec($ch);
curl_close($ch);
$verifyres = json_decode($result,true) ; 


if( $verifyres['transaction']['currency'] == $order->get_order_currency() &&  $verifyres['transaction']['custom']['OrderNo'] == $order->get_order_number() && $verifyres['transaction']['amount'] == $this->paylike_order_total($order) )
{

$order->payment_complete($transactionid);
$order->add_order_note(__( 'Trx ID = '.$verifyres['transaction']['id'].' Payment Amount : '.$verifyres['transaction']['amount'].', Charge authorized at : '.$verifyres['transaction']['created'] ,'woocommerce'));
add_post_meta( $order->id, '_paylike_charge_status', 'charge_auth_only');

WC()->cart->empty_cart();
}


}
else
{
//Authorize and Capture only Transaction
$data = array(
'amount'     => $this->paylike_order_total($order) ,
'currency'   => $order->get_order_currency()  ) ;


$verifyurl = 'https://api.paylike.io/transactions/'.$transactionid.'/captures' ; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$verifyurl);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERPWD, ":" .$this->appkey);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verifyres = json_decode($result,true) ; 

if($httpcode >= 200 || $httpcode <= 299 )
{

$order->payment_complete($transactionid);

$order->add_order_note(__( 'Trx ID = '.$verifyres['transaction']['id'].' Authorized Amount : '.$verifyres['transaction']['amount'].', Captured Amount '.$verifyres['transaction']['capturedAmount'].', Charge authorized at : '.$verifyres['transaction']['created'] ,'woocommerce'));
add_post_meta( $order->id, '_paylike_charge_status', 'charge_auth_captured');
WC()->cart->empty_cart();
}
else
{
foreach ($verifyres as $value) 
{
$error[] = $value['message'];
}

$errorstring =  implode(" ",$error);

$order->add_order_note(__( 'Error = '.$errorstring ,'woocommerce'));
}


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





public function capture_payment($order_id){

$order = new WC_Order($order_id);

try
{
$transactionid     = get_post_meta( $order_id, '_transaction_id', true );
$chargestatus = get_post_meta( $order_id, '_paylike_charge_status', true );
if($chargestatus == 'charge_auth_only')
{



$data = array(
	'amount'     => $this->paylike_capture_total($order) ,
	'currency'   => $order->get_order_currency()  
 ) ;


$verifyurl = 'https://api.paylike.io/transactions/'.$transactionid.'/captures' ; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$verifyurl);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERPWD, ":" .$this->appkey);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verifyres = json_decode($result,true) ; 

if($httpcode >= 200 || $httpcode <= 299 )
{



$order->add_order_note(__( 'Trx ID = '.$verifyres['transaction']['id'].' Authorized Amount : '.$verifyres['transaction']['amount'].', Captured Amount '.$verifyres['transaction']['capturedAmount'].', Charge authorized at : '.$verifyres['transaction']['created'] ,'woocommerce'));


}
else
{
foreach ($verifyres as $value) 
{
$error[] = $value['message'];
}

$errorstring =  implode(" ",$error);

$order->add_order_note(__( 'Error = '.$errorstring ,'woocommerce'));
}


}

}
catch(Exception $e)
{
$wc_order->add_order_note(__( $e->getMessage(),'woocommerce'));
}

}

/*Calculate order total*/
private function paylike_order_total($order)
{
$grand_total 	= $order->order_total;
$currency = '' != $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() ;

if(in_array($currency ,$this->paylike_zerocurrency ))
{
$amount 	     = number_format($grand_total,0,".","") ;
}
else
{
$amount 	     = $grand_total * 100 ;
}

return $amount;
}
/*Calculate order total*/

/*Calculate refund total */
private function paylike_refund_total($order, $ramount)
{
$currency = '' != $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() ;

if(in_array($currency ,$this->paylike_zerocurrency ))
{
$ramount      = number_format($ramount,0,".","") ;
}
else
{
$ramount      = $ramount * 100 ;
}

return $ramount;
}
/*Calculate refund total */


/*Calculate capture total */
private function paylike_capture_total($order)
{
$currency = '' != $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() ;

if(in_array($currency ,$this->paylike_zerocurrency ))
{
$camount      = number_format($order->get_remaining_refund_amount(),0,".","") ;
}
else
{
$camount      = $order->get_remaining_refund_amount() * 100 ;
}

return $camount;
}
/*Calculate capture total */



/*process refund function*/
public function process_refund($order_id, $amount = NULL, $reason = '' ) {


if($amount > 0 )
{
$transactionid 	= get_post_meta( $order_id , '_transaction_id', true );
$order          = new WC_Order( $order_id );


if(true == PAYLIKE_TRANSACTION_MODE)
{	

$data = array(
		'amount'     => $this->paylike_refund_total($order,$amount), 
	 ) ;


$verifyurl = 'https://api.paylike.io/transactions/'.$transactionid.'/voids' ; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$verifyurl);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERPWD, ":" .$this->appkey);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verifyres = json_decode($result,true) ; 


if($verifyres)
{


$order->add_order_note(__( 'Trx ID = '.$verifyres['transaction']['id'].' Voided Amount : '.$verifyres['transaction']['voidedAmount'].', Pending Amount : '.$verifyres['transaction']['pendingAmount'].', Void created at : '.$verifyres['transaction']['created']   ,'woocommerce'));

return true;
}
else
{
return false;
}
}
else
{			
$data = array(
		'amount'     => $this->paylike_refund_total($order,$amount), 
		'descriptor'   =>$reason 
	 ) ;


$verifyurl = 'https://api.paylike.io/transactions/'.$transactionid.'/refunds' ; 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$verifyurl);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERPWD, ":" .$this->appkey);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verifyres = json_decode($result,true) ; 




if($verifyres)
{


$order->add_order_note(__( 'Trx ID = '.$verifyres['transaction']['id'].' Refund Amount : '.$verifyres['transaction']['refundedAmount'].', Refund created at : '. date('Y-m-d H:i:s A e', $verifyres['transaction']['created'] )  ,'woocommerce'));

return true;
}
else
{
return false;
}
}

}
else
{
return false;
}



}// end of  process_refund()



}  // end of class WC_paylike_Gateway

} // end of if class exist WC_Gateway

}
add_action( 'plugins_loaded', 'paylike_init' );



/*Plugin Settings Link*/
function paylike_woocommerce_settings_link( $links ) {
$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=paylike">' . __( 'Settings' ) . '</a>';
$doc_link = '<a  target="_blank" href="http://www.google.com">' . __( 'Documentation' ) . '</a>';
array_push( $links, $settings_link ,$doc_link);

return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'paylike_woocommerce_settings_link' );


/*Plugin Settings Link*/