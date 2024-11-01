=== Spryng Payments for WooCommerce ===
Contributors: roemer.bakker
Tags: payments, woocommerce, e-commerce, webshop, psp, ideal, paypal, creditcard, sepa, klarna, betalingen
Requires at least: 3.8
Tested up to: 4.8
Stable tag: 1.6.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Process payments in WooCommerce with the Spryng Payments platform

== Description ==

This is the official WooCommerce plugin from Spryng Payments. By installing this plugin, you can process payments via the following methods:

* iDEAL
* PayPal
* Credit Card
* SEPA Direct Debit
* Klarna
* SOFORT
* Bancontact

To use this plugin, you need a Spryng Payments account and API key. To get these, sign up at [signup](https://www.spryngpayments.com/).
Your webshop should also support HTTPS.

== Installation ==

= System Requirements =

* PHP version 5.2 or greater
* PHP extension JSON enabled
* WordPress version 3.8 or greater
* WooCommerce version 3.0.0 or greater

= Installation from the WordPress admin panel =

1. Go to http://YOUR-WEBSITE.com/wp-admin/
2. In the sidebar, click on Plugins -> New plugin.
3. Search for 'Spryng Payments'.
4. After the installation is complete, click on 'Activate Plugin' or go to Plugins -> Installed Plugins and click on enable.
5. In the sidebar, go to WooCommerce -> Settings -> Checkout and scroll down to 'Spryng Payments Settings'.
6. Enter your API Key and customize the other settings to your liking, then click on 'Save Changes'.
7. Scroll back up to the payment gateway settings and click on the Spryng Payments gateways you like to enable.
8. For each gateway you want to enable, make sure 'Enable/Disable' is checked and select an account. Then click 'Save Changes'.
9. In the Spryng Payments dashboard, navigate the account(s) you have selected in step 8.
10. Open the Edit screen for this account
11. Scroll down to webhooks and enter ‘https://YOUR-WEBSITE.com/?wc-api=spryng_payments_wc_webhook' for all three webhooks.

= Manual Installation =

1. Unzip the downloaded package.
2. Upload the directory 'spryng-payments-woocommerce' to the `/wp-content/plugins` directory on your server.
3. Go to http://YOUR-WEBSITE.com/wp-admin/
4. In the sidebar, click on Plugins -> Installed Plugins and click on 'Enable Plugin'.
5. In the sidebar, go to WooCommerce -> Settings -> Checkout and scroll down to 'Spryng Payments Settings'.
6. Enter your API Key and customize the other settings to your liking, then click on 'Save Changes'.
7. Scroll back up to the payment gateway settings and click on the Spryng Payments gateways you like to enable.
8. For each gateway you want to enable, make sure 'Enable/Disable' is checked and select an account. Then click 'Save Changes’.
9. In the Spryng Payments dashboard, navigate the account(s) you have selected in step 8.
10. Open the Edit screen for this account
11. Scroll down to webhooks and enter ‘https://YOUR-WEBSITE.com/?wc-api=spryng_payments_wc_webhook' for all three webhooks.

== Screenshots ==

== Changelog ==

= 1.6.7 - 2018-06-17 =
* Added mandatory organisation in create customer call

= 1.6.6 - 2018-05-24 =
* Adding checkout text
* Code style

= 1.6.5 - 2018-05-24 =
* Added option for enhanced (webhook) logging in main settings.
* Improved status update stability.
* Message on checkout page reflects payment status.

= 1.6.5 - 2018-05-23 =
* Webhook statuses are now in categories and order statuses are only changed once per category to prevent overwriting custom shipment statuses.

= 1.6.4 - 2018-05-21 =
* Allow users to choose another iDEAL issuer when transactions are recovered.

= 1.6.3 - 2018-05-04 =
* Stability fix in transaction recovery

= 1.6.2 - 2018-05-03 =
* Transactions can now be recovered during checkout

= 1.6.1 - 2018-03-03 =
* Fixed bug that caused unnecessary emails to be sent to customers.

= 1.6.0 - 2018-02-27 =
* Added support for 3D Secure!

= 1.5.18 - 2018-02-07 =
* Bancontact checkout fix

= 1.5.17 - 2018-02-06 =
* Payment page credit card fix

= 1.5.16 - 2018-02-06 =
* Bancontact fix

= 1.5.15 - 2018-01-03 =
* Fixed webhook bug

= 1.5.14 - 2017-11-10 =
* Fixed bug related to SEPA recurring availability

= 1.5.13 - 2017-11-08 =
* Stability improvements

= 1.5.12 - 2017-10-31 =
* Stability improvements for older PHP versions

= 1.5.11 - 2017-10-13 =
* Fixed bug that would display a PHP notice when saving new gateway settings.
* Re-added 'Spryng Payments - ' before payment methods in config area.

= 1.5.10 - 2017-10-08 =
* Performance and stability improvements

= 1.5.9 - 2017-09-29 =
* Added setting fields to SEPA direct debit recurring for using iDEAL for mandate signatures.
* Improved stability for processing subscription payments.

= 1.5.8 - 2017-09-22 =
* Added option to disable adding customers to transactions to improve checkout performance
* Slight checkout page performance tweaks to payment gateway availability method
* Fixed typo in 'ABN Amro' iDEAL issuer
* Removed underscores from default merchant reference setting value to improve stability in sandbox mode.

= 1.5.7 - 2017-09-20 =
* Fixed bug that caused the stock to be reduced, even when the payment was not successful

= 1.5.6 - 2017-09-12 =
* Fixed a bug that may cause the stock to be reduced multiple times.

= 1.5.5 - 2017-09-09 =
* Fixed a bug that caused payments to fail when a customer pays via the 'My Orders' screen

= 1.5.4 - 2017-06-22 =
* Added the option to give WC orders a defined status upon reaching a certain Spryng Payments status

= 1.5.3 - 2017-06-14 =
* Merchant reference now defaults to 'WC_order_{id}' where '{id}' is the ID of the order.

= 1.5.2 - 2017-06-08 =
* The status 'Settlement Requested' now completes an order

= 1.5.1 - 2017-06-07 =
* Stability improvements to the credit card checkout method

= 1.5 - 2017-06-06 =
* Support for the Belgian payment method Bancontact
* Webhook functionality for updating orders automatically is now more secure
* Various small fixes

= 1.4 - 2017-04-25 =
* Support for recurring payments via SEPA Direct Debit.
* Several bug fixes
* Rewrote underlying systems to increase stability.

= 1.3.4 - 2017-04-06 =
* Updated order identification for changes in core WooCommerce plugin

= 1.3.3 - 2017-04-04 =
* Dynamic Descriptors can no longer contain underscores due to platform update.

= 1.3.2 - 2017-03-31 =
* Klarna refunds via WooCommerce UI

= 1.3.1 - 2017-03-29 =
* Fixed frontend credit card bug

= 1.3 - 2017-03-13 =
* Added SOFORT Support
* Stability improvements

= 1.2.4 - 2017-03-13 =
* Updated plugin to latest PHP library
* Fixed bug that could potentially crash the dashboard when providing an incorrect API Key.

= 1.2.3 - 2017-03-02 =
* Several bug fixes and stability improvements.

= 1.2.2 - 2017-03-02 =
* Fixed warnings for users with strict error reporting policies.

= 1.2.1 - 2017-03-01 =
* Fixed issue related to credit card endpoint in live environments.

= 1.2.0 - 2017-02-27 =
* Added Klarna Gateway
* Organisation Selector in gateway configuration
* Updated Credit Card gateway to transmit account and organisation information
* Bug fixes

= 1.1.1 - 2017-02-09 =
* Fixed issue where abstract gateway would not load 'Enabled' option properly.

= 1.1 - 2017-02-09 =
* Added SEPA Direct Debit gateway
* Title field added to checkout form
* Separate API keys for live and sandbox
* API Keys are password fields
* Dutch postcode validation
* Fixed CreditCard Form JS issue


= 1.0 =
* This is the initial version with support for credit cards, iDEAL and PayPal.
