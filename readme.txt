=== RozetkaPay Payparts for WooCommerce ===
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card payments and instalment payments through RozetkaPay in your WooCommerce store.

== Description ==

This plugin connects a WooCommerce store to the RozetkaPay payment service and adds two payment gateways:

* RozetkaPay Instalments (`rp_payparts`) - the customer selects a partner bank and the number of monthly instalments, then confirms the agreement on the RozetkaPay side.
* RozetkaPay Enhanced (`rp_standard`) - a regular bank card payment through RozetkaPay.

Additional functionality:

* An instalment widget for product and cart pages that shows the estimated monthly payment.
* A shortcode `[rp_payparts_widget]` for placing the widget in page content.
* Callback handling that updates order status when a payment is confirmed, declined or cancelled.
* Optional scheduled status polling for orders that are still awaiting payment.
* Refunds initiated from the WooCommerce order screen.
* Admin screens with payment statistics and the list of banks available through the RozetkaPay API.

The plugin is compatible with WooCommerce High-Performance Order Storage (custom order tables).

= Cart and checkout page requirement =

The plugin supports the classic WooCommerce cart and checkout pages built with the `[woocommerce_cart]` and `[woocommerce_checkout]` shortcodes.

The Cart and Checkout blocks are not supported: the RozetkaPay payment methods are not listed in the block checkout, the instalment message is not displayed on the block cart page, and the selected bank and term are not stored in the order. The plugin declares this incompatibility to WooCommerce, so a notice is displayed in the admin area. If your cart and checkout pages use the blocks, replace their content with the shortcodes above.

== Installation ==

1. Upload the plugin to the `wp-content/plugins` directory, or install the ZIP archive through Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins screen in WordPress.
3. Make sure WooCommerce is active.
4. Go to WooCommerce > Settings > Payments, open RozetkaPay Instalments and enter the API user and API password provided by RozetkaPay.
5. Configure the allowed banks, allowed instalment terms, order amount limits and the display style of the instalment options.

== External services ==

This plugin relies on the RozetkaPay payment service, provided by LLC "ROZETKA PAY". Payments cannot be processed without it. All requests are made from your server; the visitor's browser does not contact RozetkaPay directly.

The plugin sends data to the RozetkaPay API at `https://api.rozetkapay.com` in the following cases:

* Creating a payment, when the customer places an order with a RozetkaPay payment method. The request contains the order total and currency, the order reference, the payment description, the selected bank and number of instalments, the cart contents (product id, name, category, quantity and unit price), shipping and fee lines, and the billing first name, last name, email address and phone number of the customer.
* Requesting the list of available banks, when an administrator opens the banks screen and when the widget calculates instalment limits. Only the store API credentials are sent.
* Checking a payment status, when a callback from RozetkaPay is processed and on a scheduled ten minute interval for orders that are still awaiting payment. Only the order reference is sent.
* Testing the API connection from the gateway settings screen, which creates a test payment of 0.01 UAH.

Legal information about the service provider and its licences: https://rozetkapay.com/legal-info/liczenziyi/

== Changelog ==

= 2.0.9 =
* Fixed loading of the slider and bank card styles on the cart and checkout pages.
* Improved the bank selection interface and logo fallbacks.
* Extended the product data sent to RozetkaPay with category, unit price, tax, shipping and fee lines.
* Improved payment status handling for scheduled status checks and callbacks.
* Declared the incompatibility with the Cart and Checkout blocks.
* Moved diagnostic logging to the WooCommerce logger, behind the gateway debug setting.
