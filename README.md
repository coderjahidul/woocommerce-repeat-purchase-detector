# WooCommerce Repeat Purchase Detector

Shows a colored dot on the WooCommerce orders list when the same billing phone number is used again within a set number of days.

**Author:** [Grocoder Software Solutions](https://grocoder.net)

Plugin documentation for WordPress is in [readme.txt](readme.txt).

## What it does

On **WooCommerce → Orders**, a **Repeat Purchase** column appears after the order status.

| Dot | Meaning |
| --- | --- |
| Red | The same phone ordered at least one of the same products again inside the lookback window. |
| Purple | The same phone ordered again, but none of the products match. |

The first order has no dot. Later orders do. Phone numbers such as `017...`, `+880...`, and `880...` are treated as the same number. A different variation of the same product counts as the same product.

Click a dot to open the earlier orders: order number (with copy), customer name, phone, email, billing address, and products. Matching products are marked.

Cancelled, failed, refunded, and trashed orders are ignored. Pending, on-hold, processing, and completed orders are counted.

## Settings

Open **WooCommerce → Settings → Repeat Purchase**.

- **Days to check** — how far back to look. Default 10. Allowed range: 1–365.
- **Previous orders to show** — how many of the most recent earlier orders to use for the dot and the popup. Default 5. Allowed range: 1–50.

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer
- WooCommerce 8.2 or newer

Works with High-Performance Order Storage and with the legacy orders list.

## Installation

1. Copy the `woocommerce-repeat-purchase-detector` folder into `wp-content/plugins/`.
2. In **Plugins**, activate **WooCommerce Repeat Purchase Detector**.
3. Open an order that uses a phone number already used inside the lookback window. The Repeat Purchase column shows a red or purple dot.
