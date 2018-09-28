<?php

namespace Jigoshop\Extension\PayseraPayments\Common\Payment;

use Jigoshop\Admin\Pages;
use Jigoshop\Core\Messages;
use Jigoshop\Entity\Cart;
use Jigoshop\Entity\Order;
use Jigoshop\Entity\Product\Virtual;
use Jigoshop\Exception;
use Jigoshop\Core\Options;
use Jigoshop\Helper\Currency;
use Jigoshop\Helper\Product;
use Jigoshop\Helper\Scripts;
use Jigoshop\Integration;
use Jigoshop\Integration\Helper\Render;
use Jigoshop\Payment\Method3;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\OrderServiceInterface;
use Jigoshop\Helper\Options as OptionsHelper;
use Jigoshop\Extension\PayseraPayments\Common\Payment\WebToPay\WebToPay;


class PayseraPayments implements Method3
{
	const ID = 'paysera_payments';
	const PLUGIN_KEY = "jigoshop_paysera";

	/**@var Options $options */
	private $options;
	/**@var Messages $messages */
	private $messages;
	/**@var CartServiceInterface $cartService */
	private $cartService;
	/**@var OrderServiceInterface $orderService */
	private $orderService;
	/**@var array $settings */
	private static $settings;

	private static $currency;


	public function __construct(Options $options, CartServiceInterface $cartService, OrderServiceInterface $orderService, Messages $messages)
	{


		$this->options = $options;
		$this->messages = $messages;
		$this->cartService = $cartService;
		$this->orderService = $orderService;

		$this->country = $options->get('general.country');
		$this->currency = $options->get('general.currency');

		OptionsHelper::setDefaults(self::ID, [
			'enabled' => false,
			'title' => __('PaySera Payments', self::PLUGIN_KEY),
			'description' => __('Pay via PaySera', self::PLUGIN_KEY),
			'paysera_project_id' => '',
			'paysera_project_password' => '',
			'testMode' => false,
		]);

		self::$settings = $options->get('payment.' . self::ID);

		add_action('init', [$this, 'checkResponseFromHostedPayment']);
		add_action('init', [$this, 'cancelPayment']);
		add_action('init', [$this, 'returnPayment']);
	}



	/**
	 * @return array|mixed
	 */
	public static function getSettings()
	{
		return self::$settings;
	}

	/**
	 * @return string ID of payment method.
	 */
	public function getId()
	{
		return self::ID;
	}

	/**
	 * @return string Human readable name of method.
	 */
	public function getName()
	{
		return $this->isAdmin() ? $this->getLogoImage() . ' ' . __('PaySera Payments', self::PLUGIN_KEY) : self::$settings['title'];
	}

	/**
	 * @return bool
	 */
	private function isAdmin()
	{
		return is_admin() ? true : false;
	}

	private function getLogoImage()
	{
		return '<img src="/wp-content/plugins/jigoshop-paysera/assets/images/Paysera_logotype_internet.gif" alt="PaySera" style="width:60px"/>';
	}

	/**
	 * @return bool Whether current method is enabled and able to work.
	 */
	public function isEnabled()
	{
		return self::$settings['enabled'];
	}

	/**
	 * @return array List of options to display on Payment settings page.
	 */
	public function getOptions()
	{
		$defaults = [
			[
				'name' => 'enabled',
				'title' => __(
					'Enable',
					self::PLUGIN_KEY
				),
				'description' => __('', self::PLUGIN_KEY),
				'type' => 'checkbox',
				'checked' => self::$settings['enabled'],
				'classes' => ['switch-medium'],
			],
			[
				'name' => 'title',
				'title' => __(
					'Method Title',
					self::PLUGIN_KEY
				),
				'description' => __('This controls the title on checkout page', self::PLUGIN_KEY),
				'type' => 'text',
				'value' => self::$settings['title'],
			],
			[
				'name' => 'description',
				'title' => __(
					'Method Description',
					self::PLUGIN_KEY
				),
				'description' => __('This controls the description on checkout page', self::PLUGIN_KEY),
				'type' => 'text',
				'value' => self::$settings['description'],
			],
			[
				'name' => 'paysera_project_id',
				'title' => __(
					'Paysera project id',
					self::PLUGIN_KEY
				),
				'description' => __('Your paysera project id', self::PLUGIN_KEY),
				'tip' => __('Your paysera project id', self::PLUGIN_KEY),
				'type' => 'text',
				'value' => self::$settings['paysera_project_id'],
			],
			[
				'name' => 'paysera_project_password',
				'title' => __(
					'Paysera project password',
					self::PLUGIN_KEY
				),
				'description' => __('Your paysera paysera project password', self::PLUGIN_KEY),
				'tip' => __('Your paysera paysera project password', self::PLUGIN_KEY),
				'type' => 'text',
				'value' => self::$settings['paysera_project_password'],
			],
			[
				'name' => 'testMode',
				'title' => __('Sandbox/Testing', self::PLUGIN_KEY),
				'description' => __('Enable Sandbox for testing payment', self::PLUGIN_KEY),
				'type' => 'checkbox',
				'checked' => self::$settings['testMode'],
				'classes' => ['switch-medium'],
			]
		];

		for ($i = 0; $i < count($defaults); $i++) {
			$defaults[$i]['name'] = sprintf('[%s][%s]', self::ID, $defaults[$i]['name']);
		}

		return $defaults;
	}

	/**
	 * Validates and returns properly sanitized options.
	 *
	 * @param $settings array Input options.
	 *
	 * @return array Sanitized result.
	 */
	public function validateOptions($settings)
	{
		$disabled = null;
		$settings['enabled'] = $settings['enabled'] == 'on';
		$settings['testMode'] = $settings['testMode'] == 'on';
		$settings['title'] = trim(htmlspecialchars(strip_tags($settings['title'])));
		$settings['description'] = esc_attr($settings['description']);
		return $settings;
	}

	private function validate($settings)
	{
		return trim(strip_tags(esc_attr($settings)));
	}

	/**
	 * Renders method fields and data in Checkout page.
	 */
	public function render()
	{
		if (self::$settings['description']) {
			echo wpautop(self::$settings['description']);
		}
	}

	/**
	 * @param Order $order Order to process payment for.
	 *
	 * @return string URL to redirect to.
	 * @throws Exception On any payment error.
	 */
	public function process($order)
	{
		return WebToPay::redirectToPayment(array(
			"projectid" => self::$settings["paysera_project_id"],
			"sign_password" => self::$settings["paysera_project_password"],
			"orderid" => $order->getId(),
			"amount" => $order->getSubtotal() * 100,
			"currency" => $this->currency,
			"country" => $this->country,
			"accepturl" => \Jigoshop\Helper\Order::getThankYouLink($order),
			"cancelurl" => \Jigoshop\Helper\Order::getCancelLink($order),
			"callbackurl" => \Jigoshop\Helper\Order::getThankYouLink($order),
			"test" => self::$settings['testMode'] ? 1 : 0
		));
	}


	public function checkResponseFromHostedPayment()
	{
		if (isset($_POST['ss1'])) {
			try {
				$response = WebToPay::checkResponse($_POST, array('projectid' => self::$settings["paysera_project_id"], 'sign_password' => self::$settings["paysera_project_password"]));

				$orderID = strip_tags((int)$response['orderid']);

				$order = $this->orderService->find($orderID);
				$status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
				$order->setStatus($status, __('Payment Completed.'));
				$this->orderService->save($order);
				exit("OK");

			} catch (Exception $e) {
				exit('failed.' . $e->getMessage());
			}
		}

	}

	public function returnPayment()
	{
		//this will never happen
	}

	public function cancelPayment()
	{
		//change status to canceled
	}

	/**
	 * Whenever method was enabled by the user.
	 *
	 * @return boolean Method enable state.
	 */
	public function isActive()
	{
		if (self::$settings['enabled']) {
			$enabled = true;
		} else {
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 * Set method enable state.
	 *
	 * @param boolean $state Method enable state.
	 *
	 * @return array Method current settings (after enable state change).
	 */
	public function setActive($state)
	{
		self::$settings['enabled'] = $state;

		return self::$settings;
	}

	/**
	 * Whenever method was configured by the user (all required data was filled for current scenario).
	 *
	 * @return boolean Method config state.
	 */
	public function isConfigured()
	{
		if (isset(self::$settings['paysera_project_id']) && self::$settings['paysera_project_id']
			&& isset(self::$settings['paysera_project_password']) && self::$settings['paysera_project_password']) {
			return true;
		}
		return false;
	}

	/**
	 * Whenever method has some sort of test mode.
	 *
	 * @return boolean Method test mode presence.
	 */
	public function hasTestMode()
	{
		return true;
	}

	/**
	 * Whenever method test mode was enabled by the user.
	 *
	 * @return boolean Method test mode state.
	 */
	public function isTestModeEnabled()
	{
		$testModeState = false;
		if (self::$settings['testMode']) {
			$testModeState = true;
		}

		return $testModeState;
	}

	/**
	 * Set Method test mode state.
	 *
	 * @param boolean $state Method test mode state.
	 *
	 * @return array Method current settings (after test mode state change).
	 */
	public function setTestMode($state)
	{
		self::$settings['testMode'] = $state;

		return self::$settings;
	}

	/**
	 * Whenever method requires SSL to be enabled to function properly.
	 *
	 * @return boolean Method SSL requirment.
	 */
	public function isSSLRequired()
	{
		return false;
	}

	/**
	 * Whenever method is set to enabled for admin only.
	 *
	 * @return boolean Method admin only state.
	 */
	public function isAdminOnly()
	{
		if (true == self::$settings['adminOnly']) {
			return true;
		}

		return false;
	}

	/**
	 * Sets admin only state for the method and returns complete method options.
	 *
	 * @param boolean $state Method admin only state.
	 *
	 * @return array Complete method options after change was applied.
	 */
	public function setAdminOnly($state)
	{
		self::$settings['adminOnly'] = $state;

		return self::$settings;
	}


	/**
	 * @param $orderId
	 * @param $metaKey
	 * @param bool $single
	 * @return bool|mixed
	 */
	private function getOrderMeta($orderId, $metaKey, $single = false)
	{
		if (!empty($orderId)) {
			return get_post_meta($orderId, $metaKey, $single);
		}

		return false;
	}

	/* private function safeRedirect($url)
	{
		if (!empty($url)) {
			return wp_safe_redirect($url);
		}

		return false;
	} */
}
