<?php
/**
 * Repeat-purchase matching by billing phone and product.
 *
 * @package WooCommerceRepeatPurchaseDetector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds earlier orders that share a billing phone inside the configured window.
 */
class WRPD_Detector {

	/**
	 * Order statuses that count as a purchase.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'pending', 'on-hold', 'processing', 'completed' );

	/**
	 * Page classification cache. Null until the current list page is preloaded.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static $page_cache = null;

	/**
	 * Normalize a phone number so local, 0-prefixed, and 880-prefixed forms match.
	 *
	 * @param string $phone Raw billing phone.
	 * @return string Digits without a leading country code or trunk prefix. Empty when unusable.
	 */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( ! is_string( $digits ) || '' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '880' ) ) {
			$digits = substr( $digits, 3 );
		}

		$digits = ltrim( $digits, '0' );

		if ( strlen( $digits ) < 8 ) {
			return '';
		}

		return $digits;
	}

	/**
	 * Classify every order on the current admin list page in one lookup.
	 *
	 * @param WC_Order[] $orders Orders currently listed.
	 */
	public static function preload( array $orders ) {
		if ( null !== self::$page_cache ) {
			return;
		}

		self::$page_cache = self::classify( $orders );
	}

	/**
	 * Whether the list-page cache has been built.
	 *
	 * @return bool
	 */
	public static function is_preloaded() {
		return null !== self::$page_cache;
	}

	/**
	 * Cached list-page result for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array{level: string, previous_ids: int[]}|null Null when this order was not part of the preload.
	 */
	public static function result_for( $order_id ) {
		$order_id = (int) $order_id;
		if ( null === self::$page_cache || ! isset( self::$page_cache[ $order_id ] ) ) {
			return null;
		}

		return array(
			'level'         => (string) self::$page_cache[ $order_id ]['level'],
			'previous_ids' => self::$page_cache[ $order_id ]['previous_ids'],
		);
	}

	/**
	 * List-page result, or a one-order lookup when the row was not preloaded.
	 *
	 * @param WC_Order $order Order row.
	 * @return array{level: string, previous_ids: int[]}
	 */
	public static function result_for_order( WC_Order $order ) {
		$cached = self::result_for( $order->get_id() );
		if ( null !== $cached ) {
			return $cached;
		}

		$classified = self::classify( array( $order ) );
		$row        = $classified[ $order->get_id() ];

		if ( null === self::$page_cache ) {
			self::$page_cache = array();
		}
		self::$page_cache[ $order->get_id() ] = $row;

		return array(
			'level'         => (string) $row['level'],
			'previous_ids' => $row['previous_ids'],
		);
	}

	/**
	 * Popup payload for one order: the earlier orders that produced its dot.
	 *
	 * @param WC_Order $order Current order.
	 * @return array<string, mixed>
	 */
	public static function popup_data( WC_Order $order ) {
		$classified = self::classify( array( $order ) );
		$row        = isset( $classified[ $order->get_id() ] ) ? $classified[ $order->get_id() ] : array(
			'level'         => '',
			'previous_ids' => array(),
			'product_ids'  => array(),
		);

		$product_ids = isset( $row['product_ids'] ) ? $row['product_ids'] : array();
		$blocks      = array();

		foreach ( $row['previous_ids'] as $previous_id ) {
			$previous = wc_get_order( $previous_id );
			if ( ! $previous instanceof WC_Order ) {
				continue;
			}

			$blocks[] = self::order_block( $previous, $product_ids );
		}

		return array(
			'level'  => (string) $row['level'],
			'orders' => $blocks,
		);
	}

	/**
	 * Decide red, purple, or none for each order.
	 *
	 * Red: an earlier order in the window shares the phone and at least one parent product.
	 * Purple: an earlier order shares the phone, but no parent product matches.
	 *
	 * @param WC_Order[] $orders Orders to classify.
	 * @return array<int, array<string, mixed>>
	 */
	public static function classify( array $orders ) {
		$page   = array();
		$min_ts = null;
		$max_ts = null;

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order_id = $order->get_id();
			$created  = $order->get_date_created();
			$phone    = self::normalize_phone( $order->get_billing_phone() );
			$ts       = $created ? $created->getTimestamp() : 0;
			$counted  = in_array( $order->get_status(), self::STATUSES, true );

			$page[ $order_id ] = array(
				'level'         => '',
				'previous_ids' => array(),
				'product_ids'  => array(),
				'phone'         => ( $counted && $ts ) ? $phone : '',
				'ts'            => $ts,
			);

			if ( '' === $page[ $order_id ]['phone'] ) {
				continue;
			}

			$min_ts = null === $min_ts ? $ts : min( $min_ts, $ts );
			$max_ts = null === $max_ts ? $ts : max( $max_ts, $ts );
		}

		if ( null === $min_ts || null === $max_ts ) {
			return $page;
		}

		$window         = WRPD_Settings::window_days() * DAY_IN_SECONDS;
		$previous_limit = WRPD_Settings::previous_limit();
		$candidates     = self::query_candidates( $min_ts - $window, $max_ts );
		$by_phone       = array();
		$product_orders = array();

		foreach ( $candidates as $candidate ) {
			$by_phone[ $candidate['phone'] ][] = $candidate;
		}

		foreach ( $page as $order_id => $row ) {
			if ( '' === $row['phone'] || empty( $by_phone[ $row['phone'] ] ) ) {
				continue;
			}

			$product_orders[] = $order_id;
			$previous         = array();

			foreach ( $by_phone[ $row['phone'] ] as $candidate ) {
				if ( $candidate['id'] === $order_id ) {
					continue;
				}

				$delta = $row['ts'] - $candidate['ts'];
				if ( $delta < 0 || $delta > $window ) {
					continue;
				}

				if ( 0 === $delta && $candidate['id'] > $order_id ) {
					continue;
				}

				$previous[] = $candidate;
			}

			usort(
				$previous,
				static function ( $a, $b ) {
					if ( $a['ts'] === $b['ts'] ) {
						return $b['id'] - $a['id'];
					}
					return $b['ts'] - $a['ts'];
				}
			);

			$previous = array_slice( $previous, 0, $previous_limit );

			$page[ $order_id ]['previous_ids'] = array_map(
				static function ( $candidate ) {
					return $candidate['id'];
				},
				$previous
			);

			foreach ( $page[ $order_id ]['previous_ids'] as $previous_id ) {
				$product_orders[] = $previous_id;
			}
		}

		$products = self::product_ids_for_orders( array_values( array_unique( $product_orders ) ) );

		foreach ( $page as $order_id => $row ) {
			if ( empty( $row['previous_ids'] ) ) {
				continue;
			}

			$current_products                    = isset( $products[ $order_id ] ) ? $products[ $order_id ] : array();
			$page[ $order_id ]['product_ids']    = $current_products;
			$page[ $order_id ]['level']          = 'purple';
			$current_lookup                      = array_fill_keys( $current_products, true );

			foreach ( $row['previous_ids'] as $previous_id ) {
				$previous_products = isset( $products[ $previous_id ] ) ? $products[ $previous_id ] : array();
				foreach ( $previous_products as $product_id ) {
					if ( isset( $current_lookup[ $product_id ] ) ) {
						$page[ $order_id ]['level'] = 'red';
						break 2;
					}
				}
			}
		}

		return $page;
	}

	/**
	 * Earlier orders inside the date window, keyed for phone comparison in PHP.
	 *
	 * @param int $from_ts UTC timestamp, inclusive.
	 * @param int $to_ts   UTC timestamp, inclusive.
	 * @return array<int, array{id: int, ts: int, phone: string}>
	 */
	private static function query_candidates( $from_ts, $to_ts ) {
		global $wpdb;

		$from     = gmdate( 'Y-m-d H:i:s', (int) $from_ts );
		$to       = gmdate( 'Y-m-d H:i:s', (int) $to_ts );
		$statuses = array_map(
			static function ( $status ) {
				return 'wc-' . $status;
			},
			self::STATUSES
		);

		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$rows = self::query_hpos_candidates( $statuses, $from, $to );
		} else {
			$rows = self::query_legacy_candidates( $statuses, $from, $to );
		}

		$candidates = array();
		foreach ( $rows as $row ) {
			$phone = self::normalize_phone( isset( $row['phone'] ) ? $row['phone'] : '' );
			if ( '' === $phone || empty( $row['date_created_gmt'] ) ) {
				continue;
			}

			$ts = strtotime( $row['date_created_gmt'] . ' UTC' );
			if ( false === $ts ) {
				continue;
			}

			$candidates[] = array(
				'id'    => (int) $row['id'],
				'ts'    => (int) $ts,
				'phone' => $phone,
			);
		}

		return $candidates;
	}

	/**
	 * Candidate rows from the HPOS orders and address tables.
	 *
	 * @param string[] $statuses Prefixed statuses.
	 * @param string   $from     GMT datetime.
	 * @param string   $to       GMT datetime.
	 * @return array<int, array<string, string>>
	 */
	private static function query_hpos_candidates( array $statuses, $from, $to ) {
		global $wpdb;

		$orders    = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
		$addresses = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_addresses_table_name();
		$in        = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are WooCommerce internals; placeholders cover values.
		$sql = "SELECT o.id, o.date_created_gmt, a.phone
			FROM {$orders} o
			INNER JOIN {$addresses} a ON a.order_id = o.id AND a.address_type = 'billing'
			WHERE o.type = 'shop_order'
			AND o.status IN ({$in})
			AND o.date_created_gmt >= %s
			AND o.date_created_gmt <= %s
			AND a.phone IS NOT NULL
			AND a.phone != ''";

		$params = array_merge( $statuses, array( $from, $to ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared on the next line.
		$prepared = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Candidate rows from posts and post meta when HPOS is off.
	 *
	 * @param string[] $statuses Prefixed statuses.
	 * @param string   $from     GMT datetime.
	 * @param string   $to       GMT datetime.
	 * @return array<int, array<string, string>>
	 */
	private static function query_legacy_candidates( array $statuses, $from, $to ) {
		global $wpdb;

		$in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders cover values.
		$sql = "SELECT p.ID AS id, p.post_date_gmt AS date_created_gmt, pm.meta_value AS phone
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_billing_phone'
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ({$in})
			AND p.post_date_gmt >= %s
			AND p.post_date_gmt <= %s
			AND pm.meta_value != ''";

		$params = array_merge( $statuses, array( $from, $to ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Parent product IDs for a set of orders.
	 *
	 * @param int[] $order_ids Order IDs.
	 * @return array<int, int[]>
	 */
	private static function product_ids_for_orders( array $order_ids ) {
		global $wpdb;

		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
		if ( empty( $order_ids ) ) {
			return array();
		}

		$items = $wpdb->prefix . 'woocommerce_order_items';
		$meta  = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$map   = array();

		foreach ( array_chunk( $order_ids, 200 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names use the WP prefix; IDs are placeholders.
			$sql = "SELECT oi.order_id, oim.meta_value AS product_id
				FROM {$items} oi
				INNER JOIN {$meta} oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_product_id'
				WHERE oi.order_item_type = 'line_item'
				AND oi.order_id IN ({$in})";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $sql, $chunk );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $prepared, ARRAY_A );

			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$product_id = (int) $row['product_id'];
				if ( $product_id < 1 ) {
					continue;
				}
				$map[ (int) $row['order_id'] ][ $product_id ] = $product_id;
			}
		}

		foreach ( $map as $order_id => $ids ) {
			$map[ $order_id ] = array_values( $ids );
		}

		return $map;
	}

	/**
	 * Customer and product details for one previous order.
	 *
	 * @param WC_Order $order          Previous order.
	 * @param int[]    $current_products Parent product IDs on the order being viewed.
	 * @return array<string, mixed>
	 */
	private static function order_block( WC_Order $order, array $current_products ) {
		$lookup   = array_fill_keys( array_map( 'intval', $current_products ), true );
		$products = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			$sku     = $product ? $product->get_sku() : '';

			$products[] = array(
				'name'    => $item->get_name(),
				'qty'     => (int) $item->get_quantity(),
				'sku'     => $sku ? $sku : '',
				'matched' => isset( $lookup[ (int) $item->get_product_id() ] ),
			);
		}

		$address = $order->get_formatted_billing_address();
		$address = str_replace( array( '<br/>', '<br />', '<br>' ), "\n", (string) $address );
		$address = trim( wp_strip_all_tags( $address ) );

		$created = $order->get_date_created();

		return array(
			'id'       => $order->get_id(),
			'number'   => (string) $order->get_order_number(),
			'url'      => $order->get_edit_order_url(),
			'date'     => $created ? $created->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '',
			'customer' => array(
				'name'    => $order->get_formatted_billing_full_name(),
				'phone'   => $order->get_billing_phone(),
				'email'   => $order->get_billing_email(),
				'address' => $address,
			),
			'products' => $products,
		);
	}
}
