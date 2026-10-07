<?php
/**
 * Orders list column, dot button, and details modal.
 *
 * @package WooCommerceRepeatPurchaseDetector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for the repeat-purchase column.
 */
class WRPD_Admin {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );

		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_legacy_column' ), 20, 2 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_wrpd_repeat_details', array( __CLASS__, 'ajax_details' ) );
	}

	/**
	 * Insert Repeat Purchase after the status column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_column( $columns ) {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$updated['wrpd_repeat'] = __( 'Repeat Purchase', 'woocommerce-repeat-purchase-detector' );
			}
		}

		if ( ! isset( $updated['wrpd_repeat'] ) ) {
			$updated['wrpd_repeat'] = __( 'Repeat Purchase', 'woocommerce-repeat-purchase-detector' );
		}

		return $updated;
	}

	/**
	 * Render the HPOS column.
	 *
	 * @param string        $column Column key.
	 * @param WC_Order|int  $order  Order object or ID.
	 */
	public static function render_column( $column, $order ) {
		if ( 'wrpd_repeat' !== $column ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::preload_page();
		self::render_dot( $order );
	}

	/**
	 * Render the legacy posts-list column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order post ID.
	 */
	public static function render_legacy_column( $column, $post_id ) {
		if ( 'wrpd_repeat' !== $column ) {
			return;
		}

		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::preload_page();
		self::render_dot( $order );
	}

	/**
	 * Styles and script on the orders list only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		unset( $hook_suffix );

		$screen = get_current_screen();
		if ( ! $screen || ! self::is_orders_screen( $screen->id ) ) {
			return;
		}

		wp_enqueue_style(
			'wrpd-admin',
			WRPD_URL . 'assets/admin.css',
			array(),
			WRPD_VERSION
		);

		wp_enqueue_script(
			'wrpd-admin',
			WRPD_URL . 'assets/admin.js',
			array(),
			WRPD_VERSION,
			true
		);

		wp_localize_script(
			'wrpd-admin',
			'wrpdAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wrpd_repeat_details' ),
				'i18n'    => array(
					'titleSame'  => sprintf(
						/* translators: %d: lookback window in days. */
						__( 'Same product within %d days', 'woocommerce-repeat-purchase-detector' ),
						WRPD_Settings::window_days()
					),
					'titleOther' => sprintf(
						/* translators: %d: lookback window in days. */
						__( 'Different product within %d days', 'woocommerce-repeat-purchase-detector' ),
						WRPD_Settings::window_days()
					),
					'loading'    => __( 'Loading earlier orders…', 'woocommerce-repeat-purchase-detector' ),
					'error'      => __( 'Could not load repeat purchase details.', 'woocommerce-repeat-purchase-detector' ),
					'empty'      => __( 'No earlier order was found for this phone number.', 'woocommerce-repeat-purchase-detector' ),
					'copy'       => __( 'Copy', 'woocommerce-repeat-purchase-detector' ),
					'copied'     => __( 'Copied', 'woocommerce-repeat-purchase-detector' ),
					'close'      => __( 'Close', 'woocommerce-repeat-purchase-detector' ),
					'order'      => __( 'Order', 'woocommerce-repeat-purchase-detector' ),
					'customer'   => __( 'Customer', 'woocommerce-repeat-purchase-detector' ),
					'products'   => __( 'Products', 'woocommerce-repeat-purchase-detector' ),
					'name'       => __( 'Name', 'woocommerce-repeat-purchase-detector' ),
					'phone'      => __( 'Phone', 'woocommerce-repeat-purchase-detector' ),
					'email'      => __( 'Email', 'woocommerce-repeat-purchase-detector' ),
					'address'    => __( 'Address', 'woocommerce-repeat-purchase-detector' ),
					'sku'        => __( 'SKU', 'woocommerce-repeat-purchase-detector' ),
					'qty'        => __( 'Qty', 'woocommerce-repeat-purchase-detector' ),
					'matched'    => __( 'Same product', 'woocommerce-repeat-purchase-detector' ),
				),
			)
		);
	}

	/**
	 * Return earlier-order details for the clicked dot.
	 */
	public static function ajax_details() {
		check_ajax_referer( 'wrpd_repeat_details', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( ! $order_id || ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You cannot view this order.', 'woocommerce-repeat-purchase-detector' ),
				),
				403
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error(
				array(
					'message' => __( 'Order not found.', 'woocommerce-repeat-purchase-detector' ),
				),
				404
			);
		}

		wp_send_json_success( WRPD_Detector::popup_data( $order ) );
	}

	/**
	 * Build the page cache from the orders already loaded by the list table.
	 */
	private static function preload_page() {
		if ( WRPD_Detector::is_preloaded() ) {
			return;
		}

		WRPD_Detector::preload( self::current_page_orders() );
	}

	/**
	 * Orders on the current admin page.
	 *
	 * @return WC_Order[]
	 */
	private static function current_page_orders() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			if ( ! function_exists( 'wc_get_container' ) ) {
				return array();
			}

			$list_table = wc_get_container()->get( \Automattic\WooCommerce\Internal\Admin\Orders\ListTable::class );
			$items      = ( $list_table && isset( $list_table->items ) && is_array( $list_table->items ) ) ? $list_table->items : array();

			return array_values(
				array_filter(
					$items,
					static function ( $item ) {
						return $item instanceof WC_Order;
					}
				)
			);
		}

		global $wp_query;

		$orders = array();
		$posts  = ( $wp_query && ! empty( $wp_query->posts ) ) ? $wp_query->posts : array();
		foreach ( $posts as $post ) {
			$order = wc_get_order( $post );
			if ( $order instanceof WC_Order ) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Print the colored dot, or nothing when this order is not a repeat.
	 *
	 * @param WC_Order $order Order row.
	 */
	private static function render_dot( WC_Order $order ) {
		$result = WRPD_Detector::result_for_order( $order );
		if ( 'red' !== $result['level'] && 'purple' !== $result['level'] ) {
			return;
		}

		$days = WRPD_Settings::window_days();
		if ( 'red' === $result['level'] ) {
			$label = sprintf(
				/* translators: %d: lookback window in days. */
				__( 'Same product ordered again within %d days', 'woocommerce-repeat-purchase-detector' ),
				$days
			);
		} else {
			$label = sprintf(
				/* translators: %d: lookback window in days. */
				__( 'Different product ordered again within %d days', 'woocommerce-repeat-purchase-detector' ),
				$days
			);
		}

		printf(
			'<button type="button" class="wrpd-dot wrpd-dot--%1$s" data-order-id="%2$d" aria-label="%3$s" title="%3$s"><span class="screen-reader-text">%4$s</span></button>',
			esc_attr( $result['level'] ),
			absint( $order->get_id() ),
			esc_attr( $label ),
			esc_html( $label )
		);
	}

	/**
	 * Orders list screen IDs for HPOS and the legacy post list.
	 *
	 * @param string $screen_id Current screen ID.
	 * @return bool
	 */
	private static function is_orders_screen( $screen_id ) {
		$hpos_screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : '';

		return in_array( $screen_id, array( 'edit-shop_order', 'woocommerce_page_wc-orders', 'admin_page_wc-orders', $hpos_screen ), true );
	}
}
