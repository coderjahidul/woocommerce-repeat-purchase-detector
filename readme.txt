=== WooCommerce Repeat Purchase Detector ===
Contributors: coderjahidul
Tags: woocommerce, orders, repeat purchase, phone, customers
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.2
WC tested up to: 11.1

Shows a red or purple dot when the same billing phone orders again within a set number of days.

== Description ==

WooCommerce Repeat Purchase Detector adds a **Repeat Purchase** column to the orders list. When a customer places another order with the same billing phone number, the later order shows a colored dot.

= Red dot =

The same phone number ordered at least one of the same products again inside the lookback window.

= Purple dot =

The same phone number ordered again, but none of the products match the earlier order.

The first order has no dot. Only the later order is marked. Click the dot to see the earlier orders: order number (with a copy button), customer name, phone, email, billing address, and products. A product that matches the current order is marked.

Phone numbers are compared after formatting, so `017...`, `+880...`, and `880...` are treated as the same number. A different variation of the same product counts as the same product.

These order statuses are counted: pending, on hold, processing, and completed. Cancelled, failed, refunded, and trashed orders are ignored.

The plugin works with High-Performance Order Storage and with the legacy orders list.

= Settings =

Open **WooCommerce → Settings → Repeat Purchase**.

* **Days to check** — how many days back to look. Default: 10. Allowed: 1–365.
* **Previous orders to show** — how many of the most recent earlier orders to use for the dot and the popup. Default: 5. Allowed: 1–50.

**Author:** [Grocoder Software Solutions](https://grocoder.net)
**Plugin page:** [GitHub](https://github.com/coderjahidul/woocommerce-repeat-purchase-detector)

== Installation ==

1. Upload the `woocommerce-repeat-purchase-detector` folder to `/wp-content/plugins/`, or install the plugin through the WordPress plugins screen.
2. Activate **WooCommerce Repeat Purchase Detector** from the Plugins screen. WooCommerce must already be active.
3. Open **WooCommerce → Orders**. The Repeat Purchase column is shown after the order status.
4. Optional: open **WooCommerce → Settings → Repeat Purchase** and set the number of days and how many earlier orders to show.

== Frequently Asked Questions ==

= Which phone number is used? =

The order billing phone. The plugin does not match by customer account, so guest checkout is included when the phone number is the same.

= Why is there no dot? =

The order is the first one for that phone in the lookback window, the billing phone is empty or too short, or the only earlier orders are cancelled, failed, refunded, or in the trash.

= An order has both a repeated product and a new product. Which color is used? =

Red. Red is used when at least one product matches an earlier order. Purple is used only when none of the products match.

= Does a different size or variation count as the same product? =

Yes. Products are compared by the parent product, so another variation of the same product is a repeat.

= Where do the earlier orders in the popup come from? =

They are the most recent earlier orders with the same billing phone, inside the number of days set in the plugin settings, up to the “Previous orders to show” limit. The dot uses that same set of orders.

= Who can open the popup? =

A user who can edit that shop order. The order details are loaded when the dot is clicked. They are not stored in the orders list.

== Changelog ==

= 1.0.0 =
* First release.
* Repeat Purchase column with a red dot for the same product and a purple dot for a different product.
* Popup with order number copy, customer details, and products.
* Settings for the lookback window and how many earlier orders to show.
* Support for High-Performance Order Storage and the legacy orders list.
