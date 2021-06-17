=== WooCommerce Paylike Payment Gateway ===
Contributors: ionut.calara
Tags: credit card, gateway, paylike, woocommerce, multisite
Requires at least: 4.4
Tested up to: 5.7.2
Stable tag: 3.0.0
WC requires at least: 3.0
WC tested up to: 5.3.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Take payments in your WooCommerce store using the Paylike Gateway

== Description ==

Accept payments with Visa and MasterCard instantly. Paylike is the modern full-stack payment platform for which you have been waiting.

WooCommerce is one of the most utilized eCommerce platforms in the world and it’s not without reason. The simplicity in setting it up is incredible while maintaining the ability to customise it indefinitely. Together with our payment plugin for WooCommerce you will have a strong and lean setup to take your business to the next level.

= Countries =

Countries supported by Paylike.

Notice that this has nothing to do with accepting cards. Cards from all over
the world is accepted.

You will need a registered company or citizenship from one of these countries:

- Austria
- Belgium
- Bulgaria
- Croatia
- Cyprus
- Czech Republic
- Denmark
- Estonia
- Finland
- France
- Germany
- Greece
- Hungary
- Iceland
- Ireland
- Italy
- Latvia
- Lichtenstein
- Lithuania
- Luxembourg
- Malta
- Netherlands
- Norway
- Poland
- Portugal
- Romania
- Slovakia
- Slovenia
- Spain
- Sweden
- United Kingdom

== Installation ==

Once you have installed WooCommerce on your Wordpress setup, follow these simple steps:
Signup at (paylike.io) [https://paylike.io] (it’s free)

1. Create a live account
1. Create an app key for your WooCommerce website
1. Upload the plugin files to the `/wp-content/plugins/woocommerce-gateway-paylike` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Insert the app key and your public key in the Checkout settings for the Paylike payment plugin

Start earning revenue on your WooCommerce website!
When you have the first transaction the team at Paylike will reach out to you for some additional details (required by financial regulations) so we can payout your profits.
If you expect to have a volume higher than EUR 40.000 / month, reach out for volume pricing.


== Frequently Asked Questions ==

= Does the plugin support test mode? =

Yes, the plugin supports test mode.

= Does the plugin support subscriptions? =

Yes, the plugin supports the subscriptions plugin.

= Can the plugin be used in a multisite environment? =

Yes, the plugin works normally under multisite, the automated tests we run get ran on standard installations as well as on testing multisite installs.

= How do i capture a payment if i have set the option to not capture the money on checkout? =

In order to capture a payment you can do so by moving the order into the on hold status, and after that move to either processing or complete. This will automatically trigger the capturing process.

= Where can I find more info? =

You can find more information on the [Paylike website](https://paylike.io/plugins/woocommerce) and on [GitHub](https://github.com/paylike/plugin-woocommerce)

= Is upsell supported? =

Yes, we currently support integration with [WooCommerce One Click Upsell plugin](https://buildwoofunnels.com/woocommerce-one-click-upsells-upstroke/)

= Is upsell supported? =

Yes, we currently support integration with [WooCommerce One Click Upsell plugin](https://buildwoofunnels.com/woocommerce-one-click-upsells-upstroke/)

== Screenshots ==

1. The settings panel for the Paylike gateway
2. Checkout screen
3. Payment screen

== Changelog ==

= 3.0.0 =
* Update sdk and add unplanned flag for subscriptions
* Updated hungarian translation

= 2.4.2 =
* Fix subscription api update bug
* Updated danish translation

= 2.4.1 =
* Fix tokenization bug
* Update romanian translation

= 2.4.0 =
* Updated SDK version to 6.js
* Updated Hungarian translation

= 2.3.1 =
* Add Keys filters to allow changing accounts based on condition

= 2.3.0 =
* Add recurring flag
* Add ajax validation aside the javascript validation

= 2.2.0 =
* Revert to 2.0.0


= 2.1.0 =
* Update payment before order with validation call to server to avoid any errors related to validation

= 2.0.0 =
* Update SDK version
* Add option to opt in to the beta version of the sdk

= 1.9.0 =
* Add support for upstroke one click upsell plugin
* Update tested up to wordpress and woocommerce
* Add filter for individual credit card icons url

= 1.8.8 =
* Add no optimize flag to avoid caching
* Add additional payment checks

= 1.8.7 =
* Update php api
* Update tested up to wordpress and woocommerce

= 1.8.6 =
* Add limit on products count to avoid errors

= 1.8.5 =
* Added Romanian language bundle
* Added Hungarian language bundle

= 1.8.4 =
* Update default checkout mode
* Add automated popup show when the payment page is displayed

= 1.8.3 =
* Added germanized support
* Update custom popup details to retrieve city, state and postcode as well as variation id

= 1.8.2 =
* Updated tested up to for woocommerce

= 1.8.1 =
* Additional subscription fixes

= 1.8.0 =
* Updated legacy code regarding subscriptions
