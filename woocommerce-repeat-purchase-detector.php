<?php
/**
 * Plugin Name: WooCommerce Repeat Purchase Detector
 * Plugin URI: https://github.com/coderjahidul/woocommerce-repeat-purchase-detector
 * Description: Shows a red or purple dot on the orders list when the same billing phone orders again within 10 days.
 * Version: 1.0.0
 * Author: Grocoder Software Solutions
 * Author URI: https://grocoder.net
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * Text Domain: woocommerce-repeat-purchase-detector
 *
 * @package WooCommerceRepeatPurchaseDetector
 */

defined( 'ABSPATH' ) || exit;

define( 'WRPD_VERSION', '1.0.0' );
define( 'WRPD_FILE', __FILE__ );
define( 'WRPD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WRPD_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WRPD_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', 'wrpd_bootstrap' );

/**
 * Load the plugin after WooCommerce is available.
 */
function wrpd_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wrpd_woocommerce_missing_notice' );
		return;
	}

	require_once WRPD_PATH . 'includes/class-wrpd-settings.php';
	require_once WRPD_PATH . 'includes/class-wrpd-detector.php';
	require_once WRPD_PATH . 'includes/class-wrpd-admin.php';

	WRPD_Settings::init();
	WRPD_Admin::init();
}

/**
 * Admin notice when WooCommerce is not active.
 */
function wrpd_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'WooCommerce Repeat Purchase Detector requires WooCommerce to be active.', 'woocommerce-repeat-purchase-detector' );
	echo '</p></div>';
}
