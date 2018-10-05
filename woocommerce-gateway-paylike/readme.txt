=== WooCommerce Paylike Payment Gateway ===
Contributors: ionut.calara
Tags: credit card, gateway, paylike, woocommerce
Requires at least: 4.4
Tested up to: 4.9.6
Stable tag: 1.5.8
WC requires at least: 2.5
WC tested up to: 3.4.5
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

= How do i capture a payment if i have set the option to not capture the money on checkout? =

In order to capture a payment you can do so by moving the order into the on hold status, and after that move to either processing or complete. This will automatically trigger the capturing process.

== Screenshots ==

1. The settings panel for the Paylike gateway
2. Checkout screen
3. Payment screen

== Changelog ==

= 1.5.8 =
* Fix quote escape for customer data

= 1.5.7 =
* Fix not included file

= 1.5.6 =
* Update merchants calls to the cursor implementation

= 1.5.5 =
* Update tested up to tags

= 1.5.4 =
* Minor key fix

= 1.5.3 =
* Key check fix

= 1.5.2 =
* Minor messages update

= 1.5.1 =
* Fix version issue

= 1.5.0 =
* Moved to new api wrapper
* More verbose http communication response
* Prevent capturing when user is not allowed to

= 1.4.5 =
* Fix manual payment page issues with older woocommerce versions
* Disable pay button on payment loading for manual payment page

= 1.4.4 =
* Fatal error fix for manual payment page
* Update in payment data sent over manual payment after order page

= 1.4.3 =
* Added better support for retry payment in subscriptions

= 1.4.2 =
* Added hungarian translation

= 1.4.1 =
* Fixed fatal error for missing keys

= 1.4.0 =
* Fixed key name for manual subscriptions
* Updated language file

= 1.3.9 =
* Minor logo fix

= 1.3.8 =
* Added account key validation
* Updated custom attributes to match standards
* Updated subcriptions merchant id to match the public key merchant
* Consistency & deprecated notices related updates

= 1.3.7 =
* Updated language files
* Fixed failed payment page

= 1.3.6 =
* Added more verbose debugging for every api related operation

= 1.3.5 =
* Added fallback for empty transaction id, added verbose logging for connections until
  this gets switched to the 2.0 api wrapper

= 1.3.4 =
* Added better currency support
* A change in total voids the authorization and resets the token.
* Minor language file update


= 1.3.3 =
* Fixed issue with legacy get total
* Updated return tags

= 1.3.2 =
* Added readable amounts in logs
* Fixed amount difference in legacy mode
* Updated api wrapper

= 1.3.1 =
* Added return for the error so that the order isn't completed on different amount.

= 1.3.0 =
* Fixed minor bug, lack of quotes around the address field on the payment script.

= 1.2.9 =
* Fixed issue with file including not in the repository

= 1.2.8 =
* Updated supported currencies list

= 1.2.7 =
* Added data collection before order gets created
* Added legacy support for woocommerce < 3.0

= 1.2.6 =
* Added danish translation for frontend text

= 1.2.5 =
* Updated POT file

= 1.2.4 =
* Minor fields, moved has fields method down so that it doesn't get overwritten

= 1.2.3 =
* Updated api wrapper

= 1.2.2 =
* Fixed communication issue with the api on some php versions.

= 1.2.1 =
* Added missing files

= 1.2.0 =
* Added subscriptions support

= 1.1.1 =
* Added compatibility mode,moving an order from processing to complete can capture the payment

= 1.1 =
* Added support for direct checkout

= 1.0 =
* Initial release
