<?php
/**
 * Description: Class definition for Yandex.Money Gateway plugin for WooCommerce
 * Author: pshentsoff
 * Author URI: http://pshentsoff.ru/
 *
 * License: GPL version 3 or later - http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package JAW-WC-Gateway-Yandex-Money
 * @category Class definition
 * @author pshentsoff
 */

// Exit if accessed directly
defined('ABSPATH') or exit;

class jaw_yandex_money extends WC_Payment_Gateway {

  static $sub_query;

  /**
   * Live request API URL
   * @var string
   */
  public $liveURL;
  /**
   * Request API URL for demo mode
   * @var string
   */
  public $testURL;
  /**
   * Client scid (assigned at Yandex.Money service)
   * @var int
   */
  public $scid;
  /**
   * Client shop ID (assigned at Yandex.Money service)
   * @var int
   */
  public $shopId;
  /**
   * Demo mode switch
   * @var boolean|string
   */
  public $demoMode;
  /**
   * Debug mode switch
   * @var boolean|string
   */
  public $debug;
  /**
   * URL при удачной оплате
   * @var string
   */
  public $callbackSuccessURL;
  /**
   * URL при неудачной оплате
   * @var string
   */
  public $callbackFailURL;

  public function __construct(){

    global $woocommerce;

    $this->id         = 'jaw_yandex_money';
    static::$sub_query = 'wc-api='.$this->id;
    $this->method_title  = __('Яндекс.Деньги', 'jaw_yandex_money');
    $this->method_description = __('Для подключения системы Яндекс.Деньги нужно получить одобрение заявки на подключение ','jaw_yandex_money');
    $this->method_description .= '<a href="https://money.yandex.ru/shoprequest/">https://money.yandex.ru/shoprequest</a><br/>';
    $this->method_description .= __('После этого Вы получите свой Идентификатор Контрагента <strong>shopId</strong> и Номер витрины Контрагента <strong>scid</strong> которые Вам будет необходимо указат в полях ниже.','jaw_yandex_money');
    $this->has_fields   = true;
    $this->liveURL      = 'https://money.yandex.ru/eshop.xml';
    $this->testURL      = 'https://demomoney.yandex.ru/eshop.xml';

    $this->init_form_fields();

    $this->init_settings();
    $this->enabled      = $this->settings['enabled'];
    $this->title        = $this->settings['title'];
    $this->description  = $this->settings['description'];
    $this->scid         = $this->settings['scid'];
    $this->shopId       = $this->settings['shopId'];
    $this->demoMode     = $this->settings['demomode'];
    $this->debug        = $this->settings['debug'];

    // Logs
    $this->log = ('yes' == $this->debug) ? $woocommerce->logger() : false;

    // callback URLs
    $this->callbackSuccessURL = get_permalink($this->settings['shopSuccessURL']);
    if(false === $this->callbackSuccessURL) $this->callbackSuccessURL = site_url('/');
    if(substr_count($this->callbackSuccessURL,'?page_id=')) $this->callbackSuccessURL .= '&'; else $this->callbackSuccessURL .= '?';
    $this->callbackSuccessURL .= static::$sub_query.'&success=1';

    $this->callbackFailURL = get_permalink($this->settings['shopFailURL']);
    if(false === $this->callbackFailURL) $this->callbackFailURL = site_url('/');
    if(substr_count($this->callbackFailURL,'?page_id=')) $this->callbackFailURL .= '&'; else $this->callbackFailURL .= '?';
    $this->callbackFailURL .= static::$sub_query.'&fail=1';

    if($this->log) {
      $this->log->add($this->id, 'Success URL = '.$this->callbackSuccessURL);
      $this->log->add($this->id, 'Fail URL = '.$this->callbackFailURL);
    }

    // Hooks
    if (version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
      add_action('woocommerce_update_options', array(&$this, 'process_admin_options'));
      add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
      add_action('init', array(&$this, 'check_callback'));
    } else {
      add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
      add_action('woocommerce_api_'.$this->id, array($this, 'check_callback'));
    }
    add_action( 'woocommerce_api_wc_gateway_'.$this->id, array($this, 'check_callback'));
    add_action('woocommerce_receipt_'. $this->id, array(&$this, 'receipt_page'));

  }

  /**
   * Check callback URL
   */
  function check_callback() {

    if($this->log) $this->log->add($this->id, 'Check callback $_REQUEST = '.print_r($_REQUEST, true));

    if(isset($_REQUEST['wc-api']) && $_REQUEST['wc-api'] == $this->id) {
      if(!empty($_REQUEST['order']) || !empty($_REQUEST['orderNumber'])) {
        $_REQUEST['order'] = isset($_REQUEST['order']) ? $_REQUEST['order']: $_REQUEST['orderNumber'];
        $order = (!class_exists('WC_Order')) ? new woocommerce_order( $_REQUEST['order'] ) : new WC_Order( $_REQUEST['order'] );
        if(!version_compare( WOOCOMMERCE_VERSION, '2.1.0', '<')) {
          wp_redirect( $this->get_return_url( $order ) );
          exit;
        }
        $downloadable_order = false;
        if(sizeof($order->get_items()) > 0) {
          foreach($order->get_items() as $item) {
            if ($item['id'] > 0) {
              $_product = $order->get_product_from_item($item);
              if ($_product->is_downloadable()) {
                $downloadable_order = true;
                continue;
              }
            }
            $downloadable_order = false;
            break;
          }
        }
        $page_redirect = $downloadable_order ? 'woocommerce_view_order_page_id' : 'woocommerce_thanks_page_id';
        wp_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $_REQUEST['order'], get_permalink(get_option($page_redirect)))));
        exit;
      }
    }
  }

  /**
   * Initialize Getway Settings Form Fields
   *
   * @access public
   * @return string|void
   */
  function init_form_fields(){

    $pages = get_pages();
    $pagesList = array(
      0 => __('Выберите из списка', 'jaw_yandex_money'),
    );

    foreach ( $pages as $page )
      $pagesList[$page->ID] = $page->post_title;

    $this->form_fields = array(

      'enabled' => array(
        'type' => 'checkbox',
        'title' => __('Включить модуль оплаты Яндекс.Деньги','jaw_yandex_money'),
        'label' => '',
        'default' => 'no',
      ),

      'demomode' => array (
        'type' => 'checkbox',
        'title' => __('Включить тестовый режим','jaw_yandex_money'),
        'label' => '',
        'default' => 'no',
      ),

      'debug' => array(
        'type' => 'checkbox',
        'title' => __('Включить отладочный режим','jaw_yandex_money'),
        'label' => '',
        'default' => 'no',
      ),

      'title' => array(
        'title' => __('Заголовок','jaw_yandex_money'),
        'type'=> 'text',
        'description' => __('Название, которое пользователь видит во время оплаты','jaw_yandex_money'),
        'default' => __('Яндекс.Деньги','jaw_yandex_money'),
      ),

      'description' => array(
        'title' => __('Описание','jaw_yandex_money'),
        'type' => 'textarea',
        'css' => 'max-width:500px;',
        'description' => __('Описание, которое пользователь видит во время оплаты','jaw_yandex_money'),
        'default' => __('Оплата через систему Яндекс.Деньги','jaw_yandex_money'),
      ),

      'shopPassword' => array(
        'title' => __('Яндекс.Деньги shopPassword','jaw_yandex_money'),
        'type' => 'text',
        'std' => '',
        'default' => '',
        'description' => __( '<br/>Необходим для корректной работы paymentAvisoURL и checkURL. shopPassword устанавливается при регистрации магазина в системе Яндекс.Деньги', 'jaw_yandex_money' ),
      ),

      'shopId' => array(
        'title' => __('Идентификатор контрагента shopId', 'jaw_yandex_money'),
        'type' => 'text',
        'css' => 'width: 100px;',
        'description' => __('Идентификатор контрагента, выдается Оператором.','jaw_yandex_money'),
      ),

      'scid' => array(
        'title' => __('Номер витрины контрагента scid', 'jaw_yandex_map'),
        'type' => 'text',
        'css' => 'width: 100px;',
        'description' => __('Номер витрины контрагента, выдается Оператором.','jaw_yandex_money'),
      ),

      'shopSuccessURL' => array(
        'title' => __('Страница успешной оплаты','jaw_yandex_money'),
        'type' => 'select',
        'options' => $pagesList,
        'css' => 'min-width:200px;',
        'std' => '',
        'default' => '',
        'description' => __( 'Страница перехода при успешной оплате (successURL Вашей заполненной анкеты)', 'jaw_yandex_money' ),
      ),

      'shopFailURL' => array(
        'title' => __('Страница ошибки оплаты','jaw_yandex_money'),
        'type' => 'select',
        'options' => $pagesList,
        'css' => 'min-width:200px;',
        'std' => '',
        'default' => '',
        'description' => __( 'Страница перехода при ошибке оплаты (failURL Вашей заполненной анкеты)', 'jaw_yandex_money' ),
      ),

    );
  }

  /**
   * Output for the order received page.
   *
   * @access public
   * @param $order_id
   */
  public function receipt_page($order_id){

    echo '<p>'.__( 'Thank you for your order, please click the button below to pay with Yandex.Money.', 'jaw_yandex_money' ).'</p>';

    echo $this -> generate_payment_form($order_id);
  }

  /**
   * Function to prepare form arguments
   *
   * @access protected
   * @param $order
   * @return array|mixed|void
   */
  protected function get_form_arguments($order) {

    global $woocommerce;

    $order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) );
    $count  = 0 ;
    $description_ = $orderDetails = '';
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
          if(($count + 1) != count($order_items) && !empty($description_)) $orderDetails .=  $description_.$_description . ', '; else $orderDetails .=  ''.$description_.$_description;
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
          if($item_id == 0)$orderDetails = esc_attr( $item['name'] ) . $_description .' ('.$item["qty"].')'; else
            $orderDetails .= ', '. esc_attr( $item['name'] ) . $_description .' ('.$item["qty"].')';
        }
      }

    if(!empty($this->currency)) $kurs = str_replace(',', '.', $this->currency); else $kurs = 1;
    $order->billing_phone = str_replace(array('+', '-', ' ', '(', ')', '.'), array('', '', '', '', '', ''), $order->billing_phone);

    $customerName = (!empty($order->billing_first_name)) ? $order->billing_first_name : $order->shipping_first_name;
    $customerName .= (!empty($order->billing_last_name)) ? ' '.$order->billing_last_name : ' '.$order->shipping_last_name;

    $customerAddress = (!empty($order->billing_city)) ? $order->billing_city : $order->shipping_city;
    $customerAddress .= (!empty($order->billing_address_1)) ? ', '.$order->billing_address_1 : ', '.$order->shippting_address_1;

    $yandex_money_args = array(
      'shopId' => $this->shopId, // 	Идентификатор Контрагента, выдается Оператором.
//        'shopArticleId' => $this->shopArticleId, // Идентификатор товара, выдается Оператором. Применяется, если Контрагент использует несколько платежных форм для разных товаров.
      'scid' => $this->scid, // Номер витрины Контрагента, выдается Оператором.
      'sum' => number_format($order->order_total*$kurs, 2, '.', ''), // Стоимость заказа.
      'customerNumber' =>  'Order ' . ltrim($order->get_order_number(), '#'), //Идентификатор плательщика в ИС Контрагента. В качестве идентификатора может использоваться номер договора плательщика, логин плательщика и т. п. Возможна повторная оплата по одному и тому же идентификатору плательщика.
      'orderNumber' =>  $order->id, // Уникальный номер заказа в ИС Контрагента. Уникальность контролируется Оператором в сочетании с параметром shopId. Если платеж с таким номер заказа уже был успешно проведен, то повторные попытки оплаты будут отвергнуты Оператором.
      'shopSuccessURL' => $this->callbackSuccessURL . '&order=' . $order->id, // URL, на который нужно отправить плательщика в случае успеха перевода. Используется при выборе соответствующей опции подключения Контрагента (см. раздел 6.1 «Параметры подключения Контрагента»).
      'shopFailURL' => $this->callbackFailURL . '&order=' . $order->id, // URL, на который нужно отправить плательщика в случае ошибки оплаты. Используется при выборе соответствующей опции подключения Контрагента.
      'cps_email' =>  $order->billing_email, // Адрес электронной почты плательщика. Если он передан, то соответствующее поле на странице подтверждения платежа будет предзаполнено (шаг 3 на схеме выше).
      'cps_phone' =>  $order->billing_phone, // Номер мобильного телефона плательщика. Если он передан, то соответствующее поле на странице подтверждения платежа будет предзаполнено (шаг 3 на схеме выше). Номер телефона используется при оплате наличными через терминалы.
      'paymentType' => array(
        'PC' => __('Оплата из кошелька в Яндекс.Деньгах','jaw_yandex_money'),
        'AC' => __('Оплата с банковской карты','jaw_yandex_money'),
        'GP' => __('По коду через терминал','jaw_yandex_money'),
        'WM' => __('Со счета WebMoney','jaw_yandex_money'),
      ),

      // Служебные параметры, используемые в email-уведомлениях о переводе:
      'CustName' => $customerName,
      'CustAddr' => $customerAddress,
      'CustEMail' =>  (!empty($order->billing_email) ? $order->billing_email : $order->shipping_email),
      'OrderDetails' => substr($orderDetails, 0, 255),

      // Параметры, добавляемые Контрагентом:
      'cms_name' => 'wordpress_woocommerce',
    );

    if($this->log) $this->log->add($this->id, '$yandex_money_args dump = '.print_r($yandex_money_args, true));

    $yandex_money_args = apply_filters( 'woocommerce_yandex_money_args', $yandex_money_args );
    return $yandex_money_args;
  }

  /**
   * Generate payment button form
   *
   * @access public
   * @param $order_id
   * @return string
   */
  public function generate_payment_form($order_id){

    global $woocommerce;

    if ('yes' == $this->debug) $this->log->add( $this->id, __('Создание платежной формы для заказа #').$order_id.'.');
    $order = class_exists('WC_Order') ? new WC_Order( $order_id ) : new woocommerce_order( $order_id );
    $yandexMoneyURL = ('yes' == $this->demoMode) ? $this->testURL : $this->liveURL;

    $yandexArguments = $this->get_form_arguments($order);

    $form = '<form name="ShopForm" method="post" id="jaw_yandex_money_gateway_form" action="'.esc_url($yandexMoneyURL).'" target="_top">';
    foreach ($yandexArguments as $name => $value) {
      $form .= '<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($value).'" />';
    }
    $form .= '<input type="submit" class="button alt" id="submit_jaw_yandex_money_payment_form" value="' . esc_attr(__( 'Pay via Yandex.Money', 'jaw_yandex_money' )) . '" />';
    //@todo check this url we need or not
    $form .= '<a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'jaw_yandex_money' ).'</a>';
    $form .= '</form>';

    $woocommerce->add_inline_js( '
			jQuery("body").block({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to Yandex.Money to make payment.', 'jaw_yandex_money' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
				        padding:        "20px",
				        zindex:         "9999999",
				        textAlign:      "center",
				        color:          "#555",
				        border:         "3px solid #aaa",
				        backgroundColor:"#fff",
				        cursor:         "wait",
				        lineHeight:		"24px",
				    }
				});
			jQuery("#submit_jaw_yandex_money_payment_form").click();
		' );

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

}
