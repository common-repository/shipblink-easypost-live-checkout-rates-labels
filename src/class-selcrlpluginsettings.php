<?php
/**
 * The file that handles getting and setting of WooCommerce/Wordpress settings
 *
 * @package ShipBlink
 */

if ( ! class_exists( 'SELCRLPluginSettings' ) ) {
	/**
	 * The ShipBlink shipping method main class
	 */
	class SELCRLPluginSettings extends WC_Settings_API {
		/**
		 * Meant to hold the instance settings provided on init
		 *
		 * @var array $instance_settings the variable.
		 */
		private $instance_settings;

		/**
		 * Construct the settings class
		 *
		 * @param array $settings the list of settings to operate on.
		 * @param array $instance_settings the list of instance_settings for the shipping zones.
		 */
		public function __construct( $settings, $instance_settings ) {
			$this->settings          = $settings;
			$this->instance_settings = $instance_settings;
		}

		/** This is for the WordPress Admin settings. */
		public function create_woocommerce_admin_settings() {
			$address = $this->get_default_store_address();

			return array(
				'selcrl_enabled'            => array(
					'title'   => __(
						'Enable/Disable',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'    => 'checkbox',
					'label'   => __(
						'Enable this shipping method',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'default' => 'yes',
				),
				'selcrl_title'              => array(
					'title'       => __(
						'Method Title',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'description' => __(
						'This controls the title which the user sees during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'default'     => __(
						'ShipBlink',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
				'selcrl_supplied_address_1' => array(
					'title'       => __(
						'Address',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'default'     => $address['address_1'],
					'description' => __(
						'Override the origin address to use when providing rates during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
				'selcrl_supplied_address_2' => array(
					'title'       => __(
						'Address 2',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'default'     => $address['address_2'],
					'description' => __(
						'Override the origin address 2 to use when providing rates during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
				'selcrl_supplied_city'      => array(
					'title'       => __(
						'City',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'default'     => $address['city'],
					'description' => __(
						'Override the origin city to use when providing rates during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
				'selcrl_supplied_state'     => array(
					'title'       => __(
						'State',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'default'     => $address['state'],
					'description' => __(
						'Override the origin state to use when providing rates during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
				'selcrl_supplied_country'   => array(
					'title'       => __(
						'Country',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'default'     => $address['country'],
					'description' => __(
						'Override the origin country to use when providing rates during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
				'selcrl_supplied_postcode'  => array(
					'title'       => __(
						'Post Code',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'text',
					'default'     => $address['postcode'],
					'description' => __(
						'Override the origin post code to use when providing rates during checkout.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'desc_tip'    => true,
				),
			);
		}

		/** This is for the WordPress Shipping Zones settings. */
		public function create_woocommerce_shipping_zone_settings() {
			require_once plugin_dir_path( __FILE__ ) .
				'class-selcrlshipblinkshippingoptionsapi.php';

			$shipping_options_api = new SELCRLShipBlinkShippingOptionsAPI();

			$shipping_options = $shipping_options_api->make_request();

			// normalize the list of shipping options into usable settings checkboxes.
			$shipping_option_settings = array_reduce(
				$shipping_options,
				function ( $settings, $option ) {
					return array_merge(
						$settings,
						array(
							"selcrl_{$option}_enabled" => array(
								'title'   => "{$option} enabled",
								'type'    => 'checkbox',
								'default' => 'yes',
							),
						)
					);
				},
				array()
			);

			// add a global setting for if we should waste CPU cycles attempting to filter.
			$other_settings = array(
				'selcrl_show_all_shipping_options' => array(
					'title'       => __(
						'Show All Shipping Options',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'type'        => 'checkbox',
					'description' => __(
						'If this box is checked, then ShipBlink will not filter any shipping options and all following shipping options will be enabled.',
						'shipblink-easypost-live-checkout-rates-labels'
					),
					'default'     => 'yes',
				),
			);

			// combine the settings into one settings array.
			return array_merge( $other_settings, $shipping_option_settings );
		}

		/**
		 * Get the store address saved in WooCommerce. This may be
		 * undefined if they have not set it in the WooCommerce settings.
		 */
		public function get_default_store_address() {
			$countries = WC()->countries;

			return array(
				'address_1' => $countries->get_base_address(),
				'address_2' => $countries->get_base_address_2(),
				'city'      => $countries->get_base_city(),
				'state'     => $countries->get_base_state(),
				'postcode'  => $countries->get_base_postcode(),
				'country'   => $countries->get_base_country(),
			);
		}

		/**
		 * A wrapper around $this->get_option for classes that don't have access to that
		 *
		 * @param string $setting_name the name of the setting to retrieve.
		 * @param mixed  $fallback a default value if unset or invalid.
		 */
		public function get_setting( $setting_name, $fallback = null ) {
			return $this->get_option( $setting_name, $fallback );
		}

		/**
		 * A wrapper around getting instance_settings with a fallback to simplify the process
		 *
		 * @param string $setting_name the name of the setting to retrieve.
		 * @param mixed  $fallback a default value if unset or invalid.
		 */
		public function get_instance_setting( $setting_name, $fallback = null ) {
			$instance_settings = $this->instance_settings;
			$key_exists        = array_key_exists( $setting_name, $instance_settings );
			$key_is_null       = $key_exists ? is_null( $instance_settings[ $setting_name ] ) : true;

			return ( $key_exists && ! $key_is_null )
				? $instance_settings[ $setting_name ]
				: $fallback;
		}
	}
}
