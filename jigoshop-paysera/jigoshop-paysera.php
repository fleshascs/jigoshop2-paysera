<?php

/**
 * Plugin Name: Jigoshop Paysera
 * Plugin URI: https://www.jigoshop.com/
 * Description: Paysera Payments Extension for your Jigoshop eCommerce online based store
 * Version: 1.0.0
 * Author: Jigoshop
 * Author URI: https://www.jigoshop.com/
 * Init File Version: 1.0
 * Init File Date: 26.09.2018
 */
// Define plugin name
define('JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_NAME', 'Jigoshop Paysera');
add_action('plugins_loaded', function () {
	load_plugin_textdomain('jigoshop_paysera', false, dirname(plugin_basename(__FILE__)) . '/languages/');
	if (class_exists('\Jigoshop\Core')) {
		//Check version.
		if (\Jigoshop\addRequiredVersionNotice(JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_NAME, '2.1.11')) {
			return;
		}
		//Check license.
		/*$licence = new \Jigoshop\Licence(__FILE__, '52561', 'http://www.jigoshop.com');
		if (!$licence->isActive()) {
			return;
		}*/
		// Define plugin directory for inclusions
		define('JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_DIR', dirname(__FILE__));
		// Define plugin URL for assets
		define('JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_URL', plugins_url('', __FILE__));
		//Init components.
		require_once(JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_DIR . '/src/Jigoshop/Extension/PayseraPayments/Init.php');
		if (is_admin()) {
			require_once(JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_DIR . '/src/Jigoshop/Extension/PayseraPayments/Admin.php');
		}
	} else {
		add_action('admin_notices', function () {
			echo '<div class="error"><p>';
			printf(__(
				'%s requires Jigoshop plugin to be active. Code for plugin %s was not loaded.',
				'jigoshop_paysera'
			), JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_NAME, JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_NAME);
			echo '</p></div>';
		});
	}
});
