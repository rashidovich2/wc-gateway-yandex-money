<?php
/**
 * Plugin Name: JAW Yandex.Money Gateway for WooCommerce
 * Plugin URI: https://joyatwork.ru
 * Description: Yandex.Money Gateway plugin for WooCommerce
 * Version: 2.0.3
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

add_action('plugins_loaded', 'jawYandexMoneyInit', 0);
function jawYandexMoneyInit(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class jawYandexMoneyWCPaymentGateway extends WC_Payment_Gateway{

    public function __construct(){

      global $woocommerce;

      $this -> id         = 'jaw_yandex_money';
      $this->icon         = apply_filters( 'jaw_yandex_money_icon', jawYandexMoneyGetIconURL() );
      $this -> method_title  = __('Яндекс.Деньги', 'jaw_yandex_money');
      $this -> has_fields = false;
      $this->liveurl      = 'https://money.yandex.ru/eshop.xml';
      $this->testurl      = 'https://demomoney.yandex.ru/eshop.xml';

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> scid = $this -> settings['scid'];
      $this -> ShopID = $this -> settings['ShopID'];
      $this -> demomode = $this -> settings['demomode'];
      $this->debug = $this->settings['debug'];

      // Logs
      if ( 'yes' == $this->debug )
        $this->log = $woocommerce->logger();


      $this -> msg['message'] = '';
      $this -> msg['class'] = '';

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      } else {
        add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
      }
      add_action('woocommerce_receipt_yandex_money', array(&$this, 'receipt_page'));
    }

    /**
     * Initialize Getway Settings Form Fields
     *
     * @access public
     * @return string|void
     */
    function init_form_fields(){

      $this -> form_fields = array(

        'enabled' => array(
          'title' => __('Включить/Выключить','jaw_yandex_money'),
          'type' => 'checkbox',
          'label' => __('Включить модуль оплаты Яндекс.Деньги','jaw_yandex_money'),
          'default' => 'no'),

        'demomode' => array (
          'title' => __('Включить/Выключить','jaw_yandex_money'),
          'type' => 'checkbox',
          'label' => __('Включить тестовый режим','jaw_yandex_money'),
          'default' => 'no'),

        'debug' => array(
          'title' => __('Включить/Выключить','jaw_yandex_money'),
          'type' => 'checkbox',
          'label' => __('Включить отладочный режим','jaw_yandex_money'),
          'default' => 'no',
        ),

        'title' => array(
          'title' => __('Заголовок','jaw_yandex_money'),
          'type'=> 'text',
          'description' => __('Название, которое пользователь видит во время оплаты','jaw_yandex_money'),
          'default' => __('Яндекс.Деньги','jaw_yandex_money')),

        'description' => array(
          'title' => __('Описание','jaw_yandex_money'),
          'type' => 'textarea',
          'description' => __('Описание, которое пользователь видит во время оплаты','jaw_yandex_money'),
          'default' => __('Оплата через систему Яндекс.Деньги','jaw_yandex_money')),

        'scid' => array(
          'title' => 'Scid',
          'type' => 'text',
          'description' => __('Номер витрины магазина ЦПП','jaw_yandex_money')),

        'ShopID' => array(
          'title' => 'ShopID',
          'type' => 'text',
          'description' => __('Номер магазина ЦПП','jaw_yandex_money') )
      );
    }

    public function admin_options(){
      echo '<h3>'.__('Оплата Яндекс.Деньги','jaw_yandex_money').'</h3>';
      echo '<h5>'.__('Для подключения системы Яндекс.Деньги нужно одобрить заявку на подключение ','jaw_yandex_money');
      echo '<a href="https://money.yandex.ru/shoprequest/">https://money.yandex.ru/shoprequest</a>';
      echo __(' После этого Вы получите и ShopID, и Scid','jaw_yandex_money').'</h5>';
      echo '<table class="form-table">';
      // Generate the HTML For the settings form.
      $this -> generate_settings_html();
      echo '</table>';

    }

    /**
     *  There are no payment fields for payu, but we want to show the description if set.
     **/
    function payment_fields(){
      if($this -> description) echo wpautop(wptexturize($this -> description));
    }

    /**
     * Receipt Page
     **/
    function receipt_page($order){
      //echo '<p>Thank you for your order, please click the button below to pay with PayU</p>';
      echo $this -> generate_payment_form($order);
    }

    /**
     * Generate payment form
     *
     * @access public
     * @param $order_id
     * @return string
     */
    public function generate_payment_form($order_id){

      global $woocommerce;

      if ('yes' == $this->debug) $this->log->add( 'jaw_yandex_money', __('Создание платежной формы для заказа #').$order_id.'.');
      $order = class_exists('WC_Order') ? new WC_Order( $order_id ) : new woocommerce_order( $order_id );
      $yandexMoneyURL = ('yes' == $this->demomode) ? $this->testurl : $this->liveurl;

      $order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) );
      $count  = 0 ;

      return $form;

    }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
      $order = new WC_Order($order_id);

      /* return array('result' => 'success', 'redirect' => add_query_arg('order',
           $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
       );*/
      return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));

    }


    function showMessage($content){
      return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
    }
    // get all pages
    function get_pages($title = false, $indent = true) {
      $wp_pages = get_pages('sort_column=menu_order');
      $page_list = array();
      if ($title) $page_list[] = $title;
      foreach ($wp_pages as $page) {
        $prefix = '';
        // show indented child pages?
        if ($indent) {
          $has_parent = $page->post_parent;
          while($has_parent) {
            $prefix .=  ' - ';
            $next_page = get_page($has_parent);
            $has_parent = $next_page->post_parent;
          }
        }
        // add to page list array array
        $page_list[$page->ID] = $prefix . $page->post_title;
      }
      return $page_list;
    }
  }
  /**
   * Add the Gateway to WooCommerce
   **/
  function jawYandexMoneyPaymentGateways($methods) {
    $methods[] = 'jawYandexMoneyWCPaymentGateway';
    return $methods;
  }
  add_filter('woocommerce_payment_gateways', 'jawYandexMoneyPaymentGateways' );
}
