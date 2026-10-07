<?php
/**
 * Settings for the lookback window and how many earlier orders to show.
 *
 * @package WooCommerceRepeatPurchaseDetector
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce submenu settings page.
 */
class WRPD_Settings {

	const OPTION = 'wrpd_settings';

	const DEFAULT_WINDOW_DAYS = 10;

	const DEFAULT_PREVIOUS_LIMIT = 5;

	/**
	 * Register the page and the option.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WRPD_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	/**
	 * Days to look back from each order. Default 10.
	 *
	 * @return int
	 */
	public static function window_days() {
		$settings = self::get();
		return (int) $settings['window_days'];
	}

	/**
	 * How many earlier orders to use for the dot and the popup. Default 5.
	 *
	 * @return int
	 */
	public static function previous_limit() {
		$settings = self::get();
		return (int) $settings['previous_limit'];
	}

	/**
	 * Saved settings with defaults and bounds applied.
	 *
	 * @return array{window_days: int, previous_limit: int}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array(
			'window_days'    => self::bound( isset( $saved['window_days'] ) ? $saved['window_days'] : self::DEFAULT_WINDOW_DAYS, 1, 365, self::DEFAULT_WINDOW_DAYS ),
			'previous_limit' => self::bound( isset( $saved['previous_limit'] ) ? $saved['previous_limit'] : self::DEFAULT_PREVIOUS_LIMIT, 1, 50, self::DEFAULT_PREVIOUS_LIMIT ),
		);
	}

	/**
	 * Submenu under WooCommerce.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Repeat Purchase Detector', 'woocommerce-repeat-purchase-detector' ),
			__( 'Repeat Purchase', 'woocommerce-repeat-purchase-detector' ),
			'manage_woocommerce',
			'wrpd-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function plugin_links( $links ) {
		$url = admin_url( 'admin.php?page=wrpd-settings' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'woocommerce-repeat-purchase-detector' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Register the option group.
	 */
	public static function register() {
		register_setting(
			'wrpd_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(
					'window_days'    => self::DEFAULT_WINDOW_DAYS,
					'previous_limit' => self::DEFAULT_PREVIOUS_LIMIT,
				),
			)
		);
	}

	/**
	 * Keep both values inside the allowed range.
	 *
	 * @param mixed $input Posted settings.
	 * @return array{window_days: int, previous_limit: int}
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'window_days'    => self::bound( isset( $input['window_days'] ) ? $input['window_days'] : self::DEFAULT_WINDOW_DAYS, 1, 365, self::DEFAULT_WINDOW_DAYS ),
			'previous_limit' => self::bound( isset( $input['previous_limit'] ) ? $input['previous_limit'] : self::DEFAULT_PREVIOUS_LIMIT, 1, 50, self::DEFAULT_PREVIOUS_LIMIT ),
		);
	}

	/**
	 * Settings form.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = self::get();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Repeat Purchase Detector', 'woocommerce-repeat-purchase-detector' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wrpd_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wrpd-window-days"><?php echo esc_html__( 'Days to check', 'woocommerce-repeat-purchase-detector' ); ?></label>
						</th>
						<td>
							<input
								name="<?php echo esc_attr( self::OPTION ); ?>[window_days]"
								id="wrpd-window-days"
								type="number"
								min="1"
								max="365"
								step="1"
								class="small-text"
								value="<?php echo esc_attr( (string) $settings['window_days'] ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'How many days back to look for another order with the same phone number. From 1 to 365.', 'woocommerce-repeat-purchase-detector' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wrpd-previous-limit"><?php echo esc_html__( 'Previous orders to show', 'woocommerce-repeat-purchase-detector' ); ?></label>
						</th>
						<td>
							<input
								name="<?php echo esc_attr( self::OPTION ); ?>[previous_limit]"
								id="wrpd-previous-limit"
								type="number"
								min="1"
								max="50"
								step="1"
								class="small-text"
								value="<?php echo esc_attr( (string) $settings['previous_limit'] ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'How many earlier orders to use for the dot and show in the popup. The most recent ones are kept. From 1 to 50.', 'woocommerce-repeat-purchase-detector' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Clamp a posted number into an inclusive range.
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $min     Minimum.
	 * @param int   $max     Maximum.
	 * @param int   $default Value used when the input is empty.
	 * @return int
	 */
	private static function bound( $value, $min, $max, $default ) {
		if ( '' === $value || null === $value ) {
			$value = $default;
		}

		$value = (int) $value;
		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}

		return $value;
	}
}
