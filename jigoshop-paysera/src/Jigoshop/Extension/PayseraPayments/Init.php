<?php

namespace Jigoshop\Extension\PayseraPayments;

use Jigoshop\Integration;
use Jigoshop\Container;

class Init
{
	const PLUGIN_KEY = "jigoshop_paysera";
	public function __construct()
	{
		Integration::addPsr4Autoload(__NAMESPACE__ . '\\', __DIR__);
		
		// CreditCard
		\Jigoshop\Integration\Render::addLocation(self::PLUGIN_KEY, JIGOSHOP_PAYSERA_PAYMENTS_GATEWAY_DIR);
		/**@var Container $creditCard */
		$creditCard = Integration::getService('di');
		$creditCard->services->setDetails(
			'jigoshop.payment.' . self::PLUGIN_KEY,
			__NAMESPACE__ . '\\Common\\Payment\\PayseraPayments',
			[
				'jigoshop.options',
				'jigoshop.service.cart',
				'jigoshop.service.order',
				'jigoshop.messages',
			]
		);

		$creditCard->triggers->add('jigoshop.service.payment', 'jigoshop.service.payment', 'addMethod', ['jigoshop.payment.' . self::PLUGIN_KEY]);

	}
}

new Init();