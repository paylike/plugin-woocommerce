# WooCommerce plugin for Paylike [![Build Status](https://travis-ci.org/paylike/plugin-woocommerce.svg?branch=master)](https://travis-ci.org/paylike/plugin-woocommerce)

This plugin is *not* developed or maintained by Paylike but kindly made
available by a user.

Released under the GPL V3 license: https://opensource.org/licenses/GPL-3.0


## Supported WooCommerce versions 

[![Last succesfull test](https://log.derikon.ro/api/v1/log/read?tag=woocommerce&view=svg&label=WooCommerce&key=ecommerce&background=96588a)](https://log.derikon.ro/api/v1/log/read?tag=woocommerce&view=html)

*The plugin has been tested with most versions of WooCommerce at every iteration. We recommend using the latest version of WooCommerce, but if that is not possible for some reason, test the plugin with your WooCommerce version and it would probably function properly.* 


## Installation

Once you have installed WooCommerce on your Wordpress setup, follow these simple steps:
  Signup at [paylike.io](https://paylike.io) (it’s free)
  
  1. Create a live account
  1. Create an app key for your WooCommerce website
  1. Upload the plugin files to the `/wp-content/plugins/woocommerce-gateway-paylike` directory, or install the plugin through the WordPress plugins screen directly.
  1. Activate the plugin through the 'Plugins' screen in WordPress.
  1. Insert the app key and your public key in the Checkout settings for the Paylike payment plugin
  


## Updating settings

Under the WooCommerce Paylike settings, you can:
 * Update the payment method text in the payment gateways list
 * Update the payment method description in the payment gateways list
 * Update the title that shows up in the payment popup 
 * Add test/live keys
 * Set payment mode (test/live)
 * Change the capture type (Instant/Manual by changing the order status)
 
 ## How to
 
 1. Capture
 * In Instant mode, the orders are captured automatically
 * In delayed mode you can capture an order by moving the order to the completed status. 
 2. Refund
   * To refund an order you can use the refund action in woocommerce, type the amount and use the refund via paylike button.
 3. Void
   * To void an order you can use the refund action in woocommerce, type the amount and use the refund via paylike button. You can only do this if the order is not captured, if you have captured already use the refund. 
   
## Updating the receipt payment template
   
For the payment page in the "Redirect to payment page after order created" Checkout mode, you can overwrite the template by creating a file in your theme under `paylike/receipt.php`.
Make sure to include an element with the id of `paylike-payment-button` so that the user is able to make the payment.


## Advanced

Due to the floating point precision issue with some numbers, it is recommended to have the bcmath extension installed. 
