<?php
/**
 * The file that handles checking if the current merchant exists in ShipBlink, and
 * letting the user know they need a ShipBlink account if it doesn't
 *
 * @package ShipBlink
 */

if ( ! class_exists( 'SELCRLShipBlinkMerchantChecker' ) ) {
	/**
	 * The ShipBlink shipping method main class
	 */
	class SELCRLShipBlinkMerchantChecker {

		/**
		 * This checks if the current merchant exists in ShipBlink's backend and
		 * displays some UI if they don't. It will also return true if it exists,
		 * and false if it doesn't
		 */
		public function handle_shipblink_merchant_active() {
			// get the store url for checking if it's registered with ShipBlink.
			$site_url = get_site_url();

			// try checking the API for if the store exists, and default to false.
			try {
				$exists = $this->make_request( $site_url );
			} catch ( Exception $e ) {
				$exists = false;
			}

			if ( ! $exists ) {
				$this->display_warning_message( $site_url );
			}

			return $exists;
		}

		/**
		 * Actually make the API request. Returns if the merchant exists.
		 *
		 * @param string $site_url the url for the store.
		 */
		private function make_request( $site_url ) {
			$json = array( 'store_id' => $site_url );

			$response = wp_remote_post(
				'https://api.shipblink.com/merchant/exists',
				array(
					'body'    => wp_json_encode( $json ),
					'headers' => array( 'Content-Type' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$body   = wp_remote_retrieve_body( $response );
			$exists = json_decode( $body, true )['exists'];

			return $exists;
		}

		/**
		 * Displays a warning message that the user needs to create an account for
		 * this plugin to work.
		 *
		 * @param string $site_url the url for the store.
		 */
		private function display_warning_message( $site_url ) {
			$email = get_option( 'admin_email', '' );
			$href  =
				'https://app.shipblink.com/connect-store?platform=woocommerce&domain=' .
				rawurlencode( $site_url ) .
				'&email=' .
				rawurlencode( $email );
			?>
				<div class="error">
					<p>
						<?php
						esc_html_e(
							'This store does not appear to have a ShipBlink account associated with it. A ShipBlink account is required for this plugin to work.',
							'shipblink-easypost-live-checkout-rates-labels'
						);
						?>
						<?php
						// use `printf` so we can interpolate the href into the string.
						printf(
						// wp_kses allows us to escape any html that is not passed into the second arg.
							wp_kses(
							/* translators: %s: URL to connect store */
								__(
									'Please <a href="%s" target="_blank" rel="noopener noreferrer">connect your store</a> and try activating this plugin again.',
									'shipblink-easypost-live-checkout-rates-labels'
								),
								// only allow the <a> tag with `href`, `target`, and `rel` attrs.
								array(
									'a' => array(
										'href'   => array(),
										'target' => array(),
										'rel'    => array(),
									),
								)
							),
							esc_url( $href )
						);
						?>
					</p>
				</div>
			<?php
		}
	}
}
