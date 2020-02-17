=== WooCommerce Paylike Payment Gateway ===
Contributors: ionut.calara
Tags: credit card, gateway, paylike, woocommerce, multisite
Requires at least: 4.4
Tested up to: 5.3.2
Stable tag: 1.7.6
WC requires at least: 3.0
WC tested up to: 3.8.1
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


== Screenshots ==

1. The settings panel for the Paylike gateway
2. Checkout screen
3. Payment screen

== Changelog ==

= 1.7.6 =
* Fix pay after order bug

= 1.7.5 =
* Fix 0 amount payment after order bug

= 1.7.4 =
* Svn fix

= 1.7.3 =
* Update php lib
* Increment tested up to

= 1.7.2 =
* Fix capture bug o order status change
* Add Shipmondo validation support

= 1.7.1 =
* Update translation files
* Increment tested up to
* Move payment complete after transaction data has been saved

= 1.7.0 =
* Update amount calculation to include previous refunds and voids

= 1.6.6 =
* Update amount calculation to use the bc math extension for better floating point precision

= 1.6.5 =
* Minor IE fix

= 1.6.4 =
* Minor fix

= 1.6.3 =
* Fix message not properly decoded from some paylike errors
* Tested up increment, fully supporting WP 5.0

= 1.6.2 =
* Fix deprecation notice cause

= 1.6.1 =
* Minor log update

= 1.6.0 =
* Update last tested version for WooCommerce
