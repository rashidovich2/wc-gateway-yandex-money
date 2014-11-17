<?php
/**
 * Plugin Name: JAW Yandex.Money Gateway for WooCommerce
 * Plugin URI: https://joyatwork.ru
 * Description: Yandex.Money Gateway plugin for WooCommerce
 * Version: 0.0.7
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

function jawYandexMoneyGeneralOptions( $settings ) {
  $updated_settings = array();
  foreach ( $settings as $section ) {
    if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
       isset( $section['type'] ) && 'sectionend' == $section['type'] ) {

      $updated_settings[] = array(
        'name'     => __('Яндекс.Деньги shopPassword','jaw_yandex_money'),
        'id'       => 'ym_shopPassword',
        'type'     => 'text',
        'css'      => 'min-width:300px;',
        'std'      => '',  // WC < 2.0
        'default'  => '',  // WC >= 2.0
        'desc'     => __( '<br/>Необходим для корректной работы paymentAvisoURL и checkURL. shopPassword устанавливается при регистрации магазина в системе Яндекс.Деньги', 'jaw_yandex_money' ),
      );

      $pages = get_pages();
      $p_arr = array();
      foreach ( $pages as $page )
        $p_arr[$page->ID] = $page->post_title;

      $updated_settings[] = array(
        'name'     => __('Яндекс.Деньги Страница успешной оплаты','jaw_yandex_money'),
        'id'       => 'ym_success_pay',
        'type'     => 'select',
        'options'  => $p_arr,
        'css'      => 'min-width:300px;',
        'std'      => '',  // WC < 2.0
        'default'  => '',  // WC >= 2.0
        'desc'     => __( 'Страница перехода при успешной оплаты (successURL)', 'jaw_yandex_money' ),
      );

      $updated_settings[] = array(
        'name'     => __('Яндекс.Деньги Страница ошибки оплаты','jaw_yandex_money'),
        'id'       => 'ym_fail_pay',
        'type'     => 'select',
        'options'  => $p_arr,
        'css'      => 'min-width:300px;',
        'std'      => '',  // WC < 2.0
        'default'  => '',  // WC >= 2.0
        'desc'     => __( 'Страница перехода при ошибки оплаты (failURL)', 'jaw_yandex_money' ),
      );


    }
    $updated_settings[] = $section;
  }
  return $updated_settings;
}
add_filter( 'woocommerce_general_settings', 'jawYandexMoneyGeneralOptions' );

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

