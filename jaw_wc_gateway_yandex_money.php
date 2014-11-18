<?php
/**
 * Plugin Name: JAW Yandex.Money Gateway for WooCommerce
 * Plugin URI: http://joyatwork.ru
 * Description: Yandex.Money Gateway plugin for WooCommerce from <a href="http://joyatwork.ru" target="_blank">Joy@Work</a>
 * Version: 0.0.13
 * Author: pshentsoff
 * Author URI: http://pshentsoff.ru/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: jaw_yandex_money
 * Domain Path: /languages/
 *
 * License: GPL version 3 or later - http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package JAW-WC-Gateway-Yandex-Money
 * @category Add-on
 * @author pshentsoff
 */

// Exit if accessed directly
defined('ABSPATH') or exit;

include_once 'class-jaw-wc-gateway-yandex-money.php';

/**
 * Check Payment function
 */
function jawYandexMoneyCheckPayment()
{
	global $wpdb;
	if ($_REQUEST['jaw_yandex_money'] == 'check') {
		//die('1');
		$hash = md5($_POST['action'].';'.$_POST['orderSumAmount'].';'.$_POST['orderSumCurrencyPaycash'].';'.
					$_POST['orderSumBankPaycash'].';'.$_POST['shopId'].';'.$_POST['invoiceId'].';'.
					$_POST['customerNumber'].';'.get_option('ym_shopPassword'));
		if (strtolower($hash) != strtolower($_POST['md5'])) { // !=
			$code = 1;
		} else {
			$order = $wpdb->get_row('SELECT * FROM '.$wpdb->prefix.'posts WHERE ID = '.(int)$_POST['customerNumber']);
			$order_summ = get_post_meta($order->ID,'_order_total',true);
			if (!$order) {
				$code = 200;
			} elseif ($order_summ != $_POST['orderSumAmount']) { // !=
				$code = 100;
			} else {
				$code = 0;
				if ($_POST['action'] == 'paymentAviso') {
					$order_w = new WC_Order( $order->ID );
					$order_w->update_status('processing', __( 'Awaiting BACS payment', 'woocommerce' ));
					$order_w->reduce_order_stock();
					
					$code = 0;
					header('Content-Type: application/xml');
					include('payment_xml.php');
					die();
				}
				else{
					header('Content-Type: application/xml');
					include('check_xml.php');
					die();
				}
			}
		}
		
		die();
		
	}
}
add_action('parse_request', 'jawYandexMoneyCheckPayment');

/**
 * Function return Yandex.Money Icon URL
 * @return string
 */
function jawYandexMoneyGetIconURL() {
  return WP_PLUGIN_URL.'/assets/images'.dirname(plugin_basename( __FILE__ )).'/icon.png';
}

function jawYandexMoneyIcon( $gateways ) {

  if ( isset( $gateways['jaw_yandex_money'] ) ) {
    $gateways['jaw_yandex_money']->icon = jawYandexMoneyGetIconURL();;
  }

  return $gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'jawYandexMoneyIcon' );

