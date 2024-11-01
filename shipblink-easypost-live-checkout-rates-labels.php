<?php
/**
 * Plugin Name: ShipBlink: EasyPost Live Checkout Rates & Labels
 * Version: 1.0.2
 * Author: ShipBlink
 * Author URI: https://shipblink.com
 * Description: EasyPost Live Checkout Rates & Label Generation. All-in-one shipping solution.
 * Text Domain: shipblink-easypost-live-checkout-rates-labels
 *
 * License: GPLv2 or later
 *
 * @package ShipBlink
 */

// a security measure to make sure people aren't trying to run this file directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get the list of active plugins
 *
 * @since 1.0.0 - I don't actually know but its required for linting purposes.
 */
$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

// Check if WooCommerce is active.
if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
	// require the merchant checker class.

	add_action( 'admin_init', 'selcrl_check_if_merchant_exists' );

	/** Checks if the current merchant is active, and if not displays an error. */
	function selcrl_check_if_merchant_exists() {
		require_once plugin_dir_path( __FILE__ ) . 'src/class-selcrlshipblinkmerchantchecker.php';

		$merchant_checker = new SELCRLShipBlinkMerchantChecker();

		$merchant_checker->handle_shipblink_merchant_active();
	}

	// require our shipping method class file.
	require_once plugin_dir_path( __FILE__ ) . 'src/class-selcrlwcshipblinkmethod.php';

	// Init our ShipBlink shipping method class.
	add_action( 'woocommerce_shipping_init', 'selcrl_create_shipblink_shipping_method' );

	/**
	 * Adds our shipping method to WooCommerce.
	 *
	 * @param array $methods a list of the available shipping methods.
	 */
	function selcrl_add_shipblink_method( $methods ) {
		$methods['shipblink'] = 'SELCRLWCShipBlinkMethod';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'selcrl_add_shipblink_method' );
} else {
	// if WooCommerce isn't active, we should let the user know that this is a
	// WooCommerce plugin and will not work without it.
	echo '<div class="error"><p>ShipBlink requires WooCommerce to be active.</p></div>';
}
