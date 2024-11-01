<?php
/**
 * The file that contains the logic for interaction with the ShipBlink API
 *
 * @package ShipBlink
 */

if ( ! class_exists( 'SELCRLShipBlinkShippingOptionsAPI' ) ) {

	/**
	 * A class to handle getting the available shipping options
	 */
	class SELCRLShipBlinkShippingOptionsAPI {
		/**
		 * Makes the request to ShipBlink to get the shipping options
		 */
		public function make_request() {
			$site_url = get_site_url();

			$json = array(
				'store_id' => $site_url,
			);

			$response = wp_remote_post(
				'https://api.shipblink.com/merchant/available_shipping_options',
				array(
					'body'    => wp_json_encode( $json ),
					'headers' => array( 'Content-Type' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return array();
			}

			$body             = wp_remote_retrieve_body( $response );
			$shipping_options = json_decode( $body, true )['shipping_options'];

			return $shipping_options;
		}
	}
}
