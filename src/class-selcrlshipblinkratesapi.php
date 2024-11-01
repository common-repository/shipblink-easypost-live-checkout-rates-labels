<?php
/**
 * The file that contains the logic for interaction with the ShipBlink API
 *
 * @package ShipBlink
 */

if ( ! class_exists( 'SELCRLShipBlinkRatesAPI' ) ) {

	/**
	 * The ShipBlink shipping method main class
	 */
	class SELCRLShipBlinkRatesAPI {
		/**
		 * The settings provided.
		 *
		 * @var array $settings the settings provided.
		 */
		private $settings;

		/**
		 * Initialize class dependencies.
		 *
		 * @param array $settings the array of settings from the plugin.
		 */
		public function __construct( $settings ) {
			$this->settings = $settings;
			// require the settings class.
			require_once plugin_dir_path( __FILE__ ) . 'class-selcrlpluginsettings.php';
		}

		/**
		 * Makes the actual request to ShipBlink given a WooCommerce package.
		 *
		 * @param array $package a package array provided by WooCommerce.
		 */
		public function make_request( $package = array() ) {
			$origin      = $this->get_origin_address();
			$destination = $this->get_destination_address( $package['destination'] );
			$items       = $this->get_normalized_packages( $package );

			$site_url = get_site_url();

			// if any of the options we get wouldn't result in a usable rate, return early.
			if ( ! $this->options_are_valid( $origin, $destination, $items ) ) {
				return;
			}

			$json = array(
				'origin'      => $origin,
				'destination' => $destination,
				'items'       => $items,
				'store_id'    => $site_url,
			);

			$response = wp_remote_post(
				'https://api.shipblink.com/woocommerce/live_rates',
				array(
					'body'    => wp_json_encode( $json ),
					'headers' => array( 'Content-Type' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				// TODO: what do we want to do with errors?
				return;
			}

			$body  = wp_remote_retrieve_body( $response );
			$rates = json_decode( $body, true )['carrier_quotes'];

			return $rates;
		}

		/**
		 * Map the packages we got from WooCommerce to something more usable by us.
		 *
		 * @param array $package the package as provided by WooCommerce.
		 */
		private function get_normalized_packages( $package ) {
			$mapped_products = array_map(
				function ( $values ) {
					$dimension_unit = get_option( 'woocommerce_dimension_unit' );
					$weight_unit    = get_option( 'woocommerce_weight_unit' );
					$product        = $values['data'];

					return array(
						'quantity'       => $values['quantity'],
						'id'             => $product->get_id(),
						'sku'            => $product->get_sku(),
						'name'           => $product->get_name(),
						'declared_value' => array(
							'currency' => get_woocommerce_currency(),
							'amount'   => $values['line_total'],
						),
						'weight'         => array(
							'units' => $weight_unit,
							'value' => $product->get_weight(),
						),
						'height'         => array(
							'units' => $dimension_unit,
							'value' => $product->get_height(),
						),
						'length'         => array(
							'units' => $dimension_unit,
							'value' => $product->get_length(),
						),
						'width'          => array(
							'units' => $dimension_unit,
							'value' => $product->get_width(),
						),
					);
				},
				$package['contents']
			);

			return array_values( $mapped_products );
		}

		/**
		 * Get the origin address from the plugin settings, defaulted to the
		 * woocommerce store address.
		 */
		private function get_origin_address() {
			$plugin_settings = new SELCRLPluginSettings( $this->settings, array() );

			$default_address = $plugin_settings->get_default_store_address();

			return array(
				'street_1' => $plugin_settings->get_setting( 'selcrl_supplied_address_1', $default_address['address_1'] ),
				'street_2' => $plugin_settings->get_setting( 'selcrl_supplied_address_2', $default_address['address_2'] ),
				'city'     => $plugin_settings->get_setting( 'selcrl_supplied_city', $default_address['city'] ),
				'state'    => $plugin_settings->get_setting( 'selcrl_supplied_state', $default_address['state'] ),
				'zip'      => $plugin_settings->get_setting( 'selcrl_supplied_postcode', $default_address['postcode'] ),
				'country'  => $plugin_settings->get_setting( 'selcrl_supplied_country', $default_address['country'] ),
			);
		}

		/**
		 * Creates an EasyPost-ish address from the WordPress address.
		 *
		 * @param array $wp_address the address as provided by WooCommerce.
		 */
		private function get_destination_address( $wp_address ) {
			return array(
				'street_1' => $wp_address['address_1'],
				'street_2' => $wp_address['address_2'],
				'city'     => $wp_address['city'],
				'state'    => $wp_address['state'],
				'zip'      => $wp_address['postcode'],
				'country'  => $wp_address['country'],
			);
		}

		/**
		 * Get whether or not a provided address is valid and has necessary fields.
		 *
		 * @param array $address a normalized address created by us.
		 */
		private function address_is_valid( $address ) {
			return is_string( $address['city'] ) && strlen( $address['city'] ) > 0 &&
			is_string( $address['state'] ) && strlen( $address['state'] ) > 0 &&
			is_string( $address['zip'] ) && strlen( $address['zip'] ) > 0 &&
			is_string( $address['country'] ) && strlen( $address['country'] ) > 0;
		}

		/**
		 * Check that the options we have for the package are valid, addresses are
		 * complete, packages has at least 1 package in it.
		 *
		 * @param array $origin_address the normalized origin address.
		 * @param array $destination_address the normalized destination address.
		 * @param array $packages the normalized packages in the order.
		 */
		private function options_are_valid( $origin_address, $destination_address, $packages ) {
			$origin_address_is_valid      = $this->address_is_valid( $origin_address );
			$destination_address_is_valid = $this->address_is_valid( $destination_address );
			$packages_are_valid           = is_array( $packages ) && count( $packages ) >= 1;

			return $origin_address_is_valid && $destination_address_is_valid && $packages_are_valid;
		}
	}
}
