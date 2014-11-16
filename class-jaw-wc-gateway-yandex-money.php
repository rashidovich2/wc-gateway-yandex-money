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
    function receipt_page($order_id){
      //echo '<p>Thank you for your order, please click the button below to pay with PayU</p>';
      echo $this -> generate_payment_form($order_id);
    }

    /**
     * @access private
     * @param $order
     */
    function get_form_arguments($order) {

      global $woocommerce;

      $order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) );
      $count  = 0 ;
      $description_ = $description = '';
      foreach($order_items as $item_id => $item) {
        $description_ .= esc_attr( $item['name'] );
        $v = explode('.', WOOCOMMERCE_VERSION);
        if($v[0] >= 2) {
          if ( $metadata = $order->has_meta( $item_id )) {
            $_description = '';
            $is_ = false;
            $is_count = 0;
            foreach ( $metadata as $meta ) {

              // Skip hidden core fields
              if ( in_array( $meta['meta_key'], apply_filters( 'woocommerce_hidden_order_itemmeta', array(
                '_qty',
                '_tax_class',
                '_product_id',
                '_variation_id',
                '_line_subtotal',
                '_line_subtotal_tax',
                '_line_total',
                '_line_tax',
              ) ) ) ) continue;

              // Handle serialised fields
              if ( is_serialized( $meta['meta_value'] ) ) {
                if ( is_serialized_string( $meta['meta_value'] ) ) {
                  // this is a serialized string, so we should display it
                  $meta['meta_value'] = maybe_unserialize( $meta['meta_value'] );
                } else {
                  continue;
                }
              }
              $is_ = true;
              if($is_count == 0)
                $_description .= esc_attr(' ['.$meta['meta_key'] . ': ' . $meta['meta_value'] );
              else
                $_description .= esc_attr(', '.$meta['meta_key'] . ': ' . $meta['meta_value'] );
              $is_count++;
            }
            if($is_count > 0)
              $_description = $_description. '] - '.$item['qty']. '';
            else $_description = $_description. ' - '.$item['qty']. '';
          }
          if(($count + 1) != count($order_items) && !empty($description_)) $description .=  $description_.$_description . ', '; else $description .=  ''.$description_.$_description;
          $count++;
          $description_ = $_description = '';
        }else {
          if ( $metadata = $item["item_meta"]) {
            $_description = '';
            foreach($metadata as $k =>  $meta) {
              if($k == 0)
                $_description .= esc_attr(' - '.$meta['meta_name'] . ': ' . $meta['meta_value'] . '');
              else {
                $_description .= esc_attr('; '.$meta['meta_name'] . ': ' . $meta['meta_value'] . '');
              }
            }
          }
          if($item_id == 0)$description = esc_attr( $item['name'] ) . $_description .' ('.$item["qty"].')'; else
            $description .= ', '. esc_attr( $item['name'] ) . $_description .' ('.$item["qty"].')';
        }
      }

      if(!empty($this->currency)) $kurs = str_replace(',', '.', $this->currency); else $kurs = 1;
      $order->billing_phone = str_replace(array('+', '-', ' ', '(', ')', '.'), array('', '', '', '', '', ''), $order->billing_phone);

      $yandex_money_args = array(
        'CustomerNumber' =>  'Order ' . ltrim($order->get_order_number(), '#№'),
        'orderNumber' =>  $order->id,
        'cps_phone' =>  $order->billing_phone,
        'cps_email' =>  $order->billing_email,
        'CustEMail' =>  $order->billing_email,
        'Sum' => number_format($order->order_total*$kurs, 2, '.', ''),
        'scid' => $this->scid,
        'ShopID' => $this->ShopID,
        'comment' => substr($description, 0, 255),
        'shopSuccessURL' => $this->result_saph_ymoney_url . '&order=' . $order->id, //@todo shop urls
        'shopFailURL' => $this->yandexfailUrl . '&order=' . $order->id,
        'paymentType' => array(
          'PC' => __('Со счета в Яндекс.Деньгах','jaw_yandex_money'),
          'AC' => __('С банковской карты','jaw_yandex_money'),
          'GP' => __('По коду через терминал','jaw_yandex_money'),
          'WM' => __('Со счета WebMoney','jaw_yandex_money'),
        ),
      );

      $yandex_money_args = apply_filters( 'woocommerce_yandex_money_args', $yandex_money_args );
      return $yandex_money_args;
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

      $yandexArguments = $this->get_form_arguments($order);

      $form = '<form name="ShopForm" method="POST" id="submit_JAW_Yandex_Money_Gateway_Form" action="'.$yandexMoneyURL.'">';
      foreach ($yandexArguments as $name => $value) {
        $form .= '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
      }
      $form .= '</form>';

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
    $methods[] = 'jaw_yandex_money';
    return $methods;
  }
  add_filter('woocommerce_payment_gateways', 'jawYandexMoneyPaymentGateways' );
}
