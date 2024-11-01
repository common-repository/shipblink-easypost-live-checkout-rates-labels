<?php
/**
 * The file that contains the main logic for ShipBlinks shipping method
 *
 * @package ShipBlink
 */

/**
 * Initialize the ShipBlink plugin
 */
function selcrl_create_shipblink_shipping_method() {
	if ( ! class_exists( 'SELCRLWCShipBlinkMethod' ) ) {
		/**
		 * The ShipBlink shipping method main class
		 */
		class SELCRLWCShipBlinkMethod extends WC_Shipping_Method {

			/**
			 * Add the ability to support shipping zones. Please see
			 * https://woocommerce.github.io/code-reference/files/woocommerce-includes-abstracts-abstract-wc-shipping-method.html#source-view.33
			 * for more information about which of these are.
			 *
			 * @var array $supports adds ability to support shipping zones.
			 */
			public $supports = array(
				'shipping-zones',
				'settings',
				'instance-settings',
				'instance-settings-modal',
			);

			/**
			 * Construction of the class
			 *
			 * @param int $instance_id provided by WooCommerce.
			 */
			public function __construct( $instance_id = 0 ) {
				// require the shipblink api and settings classes.
				require_once plugin_dir_path( __FILE__ ) . 'class-selcrlpluginsettings.php';
				require_once plugin_dir_path( __FILE__ ) . 'class-selcrlshipblinkratesapi.php';

				// required in order to support shipping zones, but not quite sure what it does.
				$this->instance_id = absint( $instance_id );
				// the ID of this plugin.
				$this->id = 'shipblink';
				// the title of the shipping method.
				$this->method_title = __(
					'ShipBlink',
					'shipblink-easypost-live-checkout-rates-labels'
				);
				// the description of the shipping method.
				$this->method_description = __(
					'Live rating provided by ShipBlink',
					'shipblink-easypost-live-checkout-rates-labels'
				);

				$this->init();

				$plugin_settings = new SELCRLPluginSettings( $this->settings, $this->$instance_settings );

				// get the 'is enabled' and 'title' from the user settings.
				$this->enabled = $plugin_settings->get_setting( 'selcrl_enabled', 'yes' );
				$this->title   = $plugin_settings->get_setting(
					'selcrl_title',
					__(
						'ShipBlink',
						'shipblink-easypost-live-checkout-rates-labels'
					)
				);

				add_action( 'woocommerce_after_shipping_rate', array( $this, 'checkout_shipping_add_css' ), 10, 2 );
			}

			/**
			 * Do init stuff
			 */
			public function init() {
				$this->init_form_fields();
				$this->init_settings();

				add_action(
					'woocommerce_update_options_shipping_' . $this->id,
					array( $this, 'process_admin_options' )
				);
			}

			/**
			 * This is the method that is called when the user gets to the cart
			 * and is ready for rates. This makes the API call to ShipBlink and
			 * adds the rates to WooCommerce.
			 *
			 * @param array $package a package array provided by WooCommerce.
			 */
			public function calculate_shipping( $package = array() ) {
				// if the user turned off this extension, don't calculate rates.
				if ( ! $this->enabled ) {
					return;
				}

				// clear any previously calculated rates.
				$this->rates = array();

				// fetch the new rates.
				$shipblink_api = new SELCRLShipBlinkRatesAPI( $this->settings );
				$rates         = $shipblink_api->make_request( $package );

				if ( ! $rates || empty( $rates ) ) {
					return;
				}

				// flatten/filter/sort the rates.
				$normalized_rates = $this->normalize_rates( $rates );

				// add each rate returned by the API.
				foreach ( $normalized_rates as $rate ) {
					$this->add_rate(
						array(
							'id'       => 'selcrl' . $rate['rate_id'],
							'label'    => $rate['display_name'],
							'cost'     => $rate['cost']['amount'],
							'calc_tax' => 'per_order',
						)
					);
				}
			}

			/** This is for the WordPress Admin settings. */
			public function init_form_fields() {
				$plugin_settings = new SELCRLPluginSettings( $this->settings, $this->instance_settings );

				$this->form_fields          = $plugin_settings->create_woocommerce_admin_settings();
				$this->instance_form_fields = $plugin_settings->create_woocommerce_shipping_zone_settings();
			}

			/**
			 * Takes a carrier object from the rates response and flattens/filters
			 * the rates appropriately
			 *
			 * @param array $carrier_rate the carrier object from the rates response.
			 */
			private function normalize_carrier_rates( $carrier_rate ) {
				$quotes = $carrier_rate['quotes'];

				return array_reduce(
					$quotes,
					function ( $new_carrier_rates, $rate ) {
						$should_show_rate = $this->should_show_rate( $rate );

						if ( $should_show_rate ) {
							return array_merge( $new_carrier_rates, array( $rate ) );
						}

						return $new_carrier_rates;
					},
					array()
				);
			}

			/**
			 * Given a flat array of rate objects, sorts them by price
			 *
			 * @param array $rates the flat list of rates.
			 */
			private function sort_rates_by_price( $rates ) {
				// duplicate the array.
				$new_rates = array_merge( array(), $rates );

				// mutate the new array.
				usort(
					$new_rates,
					function ( $rate_a, $rate_b ) {
						return $rate_a['cost']['amount'] <=> $rate_b['cost']['amount'];
					}
				);

				return $new_rates;
			}

			/**
			 * Given the rates response from the API, this flattens out the actual
			 * rates nested in the carriers objects, filters out any we don't want to
			 * show based on the zones settings, and sorts them by price
			 *
			 * @param array $rates_response the array/object of carrier objects from the api.
			 */
			private function normalize_rates( $rates_response ) {
				$flattened_rates = array_reduce(
					$rates_response,
					function ( $new_rates, $carrier_rates ) {
						$flattened_carrier_rates = $this->normalize_carrier_rates( $carrier_rates );

						return array_merge( $new_rates, $flattened_carrier_rates );
					},
					array()
				);

				$sorted_rates = $this->sort_rates_by_price( $flattened_rates );

				return $sorted_rates;
			}

			/**
			 * A function that checks the settings to determine if we should show this rate
			 *
			 * @param array $rate the individual rate to check.
			 */
			private function should_show_rate( $rate ) {
				$plugin_settings = new SELCRLPluginSettings( $this->settings, $this->instance_settings );

				$show_all = $plugin_settings->get_instance_setting( 'selcrl_show_all_shipping_options', 'yes' );

				// if they have selected that we should show all settings, just return true.
				if ( 'yes' === $show_all ) {
					return true;
				}

				// if they haven't explicitly enabled it, then default to not showing it.
				return 'yes' === $plugin_settings->get_instance_setting(
					"selcrl_{$rate['display_name']}_enabled",
					'no'
				);
			}

			/**
			 * Our class doesn't need custom css, but apparently some themes check
			 * for this regardless of whether or not it's implemented.
			 */
			public function checkout_shipping_add_css() {
				// Empty implementation.
			}
		}
	}
}
