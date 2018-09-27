<?php

namespace Jigoshop\Extension\PayseraPayments;


class Admin
{
	const PLUGIN_KEY = "jigoshop_paysera";

	public function __construct()
	{
		add_filter('plugin_action_links_' . plugin_basename(JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_DIR . '/bootstrap.php'), array($this, 'actionLinks'));
	}

	/**
	 * Show action links on plugins page.
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function actionLinks($links)
	{
		$links[] = '<a href="https://www.jigoshop.com/support/" target="_blank">' . __('Support', self::PLUGIN_KEY) . '</a>';
		$links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/jigoshop#$postform" target="_blank">' . __('Rate Us', self::PLUGIN_KEY) . '</a>';
		$links[] = '<a href="https://www.jigoshop.com/product-category/extensions/" target="_blank">' . __('More plugins for Jigoshop', self::PLUGIN_KEY) . '</a>';

		return $links;
	}
}
new Admin();