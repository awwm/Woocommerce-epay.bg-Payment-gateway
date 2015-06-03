<?php
/*
Plugin Name: Woocommerce epay.bg Payment Gateway
Plugin URI: https://www.freelancer.com/u/wahab1983pk.html
Description: epay.bg Payment Gateway for woocommerce
Version: 0.1
Author: Abdul Wahab
Author URI: https://www.freelancer.com/u/wahab1983pk.html
*/

add_action('plugins_loaded', 'woocommerce_osm_epay_init', 0);
function woocommerce_osm_epay_init(){
  if(!class_exists('WC_Payment_Gateway')) return;
  add_action( 'woocommerce_api_epaycallback', array( 'WC_osm_Epay', 'woocommerce_api_epaycallback' ) );

  class WC_osm_Epay extends WC_Payment_Gateway{
    public function __construct(){
      $this->id = 'epay';
      $this->medthod_title = 'epay';
      $this->has_fields = false;

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];
      $this->merchant_id =  $this->settings['merchant_id'];
      $this->salt = $this->settings['salt'];
      $this->redirect_page_id = $this->settings['redirect_page_id'];
      $this->liveurl = 'https://www.epay.bg/en/'; //'https://devep2.datamax.bg/ep2/epay2_demo/';
      $this->epay_key = $this->settings['salt'];

      $this->msg['message'] = "";
      $this->msg['class'] = "";

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_epay', array(&$this, 'receipt_page'));
   }



    function init_form_fields(){

       $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'osm'),
                    'type' => 'checkbox',
                    'label' => __('Enable Epay.bg Payment Module.', 'osm'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'osm'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'osm'),
                    'default' => __('Epay.bg', 'osm')),
                'description' => array(
                    'title' => __('Description:', 'osm'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'osm'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through Epay.bg Secure Servers.', 'osm')),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'osm'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at "Generate Working Key" of "Settings and Options at Epay.bg."')),
                'salt' => array(
                    'title' => __('Key', 'osm'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by Epay.bg', 'osm'),
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );
    }

    public function admin_options(){
        echo '<h3>'.__('Epay.bg Payment Gateway', 'osm').'</h3>';
        echo '<p>'.__('Epay.bg is most popular payment gateway for online shopping in India').'</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        echo '</table>';

    }

    /**
     *  There are no payment fields for epay, but we want to show the description if set.
     **/

    function payment_fields(){
        if($this->description) echo wpautop(wptexturize($this->description));
    }
    /**
     * Receipt Page
     **/
    function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with epay.', 'osm').'</p>';
        echo $this->generate_epay_form($order);
    }
    /**
     * Generate epay button link
     **/
    public function generate_epay_form($order_id){

        global $woocommerce;

        $order = new WC_Order($order_id);
        $txnid = $order_id.'_'.date("ymds");

        $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);

        $productinfo = "Order $order_id";
        $exp_date   = date("d.m.Y", time() +(1 * 86400));
$epaybg_data =
<<<DATA
MIN={$this->merchant_id}
INVOICE={$order_id}
AMOUNT={$order->order_total}
EXP_TIME={$exp_date}
DESCR={$productinfo}
LANG=en
CURRENCY=BGN
ENCODING=utf-8
DATA;
    	$epay_args1['PAGE'] = 'paylogin';
    	$epay_args1['ENCODED']	= base64_encode($epaybg_data);
//    	$epay_args1['MIN']	= $this->merchant_id;
//    	$epay_args1['TOTAL']	= $order->order_total;
//    	$epay_args1['DESCR']	= $productinfo;
    	$epay_args1['CHECKSUM'] 	= $this->hmac('sha1', $epay_args1['ENCODED'], $this->epay_key);
    	$epay_args1['URL_OK']       	=  $redirect_url;
    	$epay_args1['URL_CANCEL']     =  $redirect_url;

        $epay_args_array = array();
        foreach($epay_args1 as $key => $value){
          $epay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }
        return '<form action="'.$this->liveurl.'" method="POST" id="epay_payment_form">
            ' . implode('', $epay_args_array) . '
                <input type="submit" class="button-alt" id="submit_epay_payment_form" value="'.__('Pay via epay', 'osm').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'osm').'</a>
                <script type="text/javascript">
                jQuery(function(){
                jQuery("body").block(
                {
                    message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'osm').'",
                        overlayCSS:
                {
                    background: "#fff",
                        opacity: 0.6
                },
                css: {
                    padding:        20,
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:"32px"
                }
                });
                jQuery("#submit_epay_payment_form").click();});</script>
                </form>';


    }



    public function hmac($algo,$data,$passwd){
    	$algo=strtolower($algo);
    	$p=array('md5'=>'H32','sha1'=>'H40');
    	if(strlen($passwd)>64) $passwd=pack($p[$algo],$algo($passwd));
    	if(strlen($passwd)<64) $passwd=str_pad($passwd,64,chr(0));
    	$ipad=substr($passwd,0,64) ^ str_repeat(chr(0x36),64);
    	$opad=substr($passwd,0,64) ^ str_repeat(chr(0x5C),64);

    	return($algo($opad.pack($p[$algo],$algo($ipad.$data))));
    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
        $order = new WC_Order($order_id);
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
        );
    }

    /**
     * Check for valid epay server callback
     **/
    public function woocommerce_api_epaycallback(){

	global $wpdb;

	//The user has just been redirected back by ePay.bg after either completing or denying a transaction

   $myclass =  new WC_osm_Epay();

	//Parse the actual callback from ePay.bg
	$hmac   = $myclass->hmac('sha1', $_REQUEST['encoded'], $myclass->epay_key);
	if ($hmac == $_REQUEST['checksum']) { # XXX Check if the received CHECKSUM is OK
		$data = base64_decode($_REQUEST['encoded']);
		$lines_arr = split("\n", $data);
		$info_data = '';
		foreach ($lines_arr as $line) {
			echo "$line<br>\n";
			if (preg_match("/^INVOICE=(\d+):STATUS=(PAID|DENIED|EXPIRED)(:PAY_TIME=(\d+):STAN=(\d+):BCODE=(\d+))?$/", $line, $regs)) {
				$invoice  = trim(stripslashes($regs[1]));

				//Be sure there is only digits, skip if false
				if (FALSE === ctype_digit($invoice)) {
					$info_data .= "INVOICE=$invoice:STATUS=NO\n";
					continue;
				}

				$status   = $regs[2];
				$pay_date = $regs[4]; # XXX if PAID
				$stan     = $regs[5]; # XXX if PAID
				$bcode    = $regs[6]; # XXX if PAID

				$payment_status  = trim(stripslashes($status));
				$order_id	 = $invoice;
                $order = new WC_Order((int)$order_id);

        		switch($payment_status) {
        			case 'PAID':
                		if($order->status !== 'completed')
                		{
                				// Payment completed
                				$order->add_order_note(__('Callback completed', 'woocommerce-gateway-epay-bg'));
                				$order->payment_complete();
                        }
                    break;
        			case 'DENIED':
        			case 'EXPIRED':

        			default:
        				//Do nothing
        		}

					$info_data .= "INVOICE=$invoice:STATUS=OK\n";
				} else
					$info_data .= "INVOICE=$invoice:STATUS=NO\n";
			}
            echo $info_data, "\n";
		}


    }

    function showMessage($content){
            return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
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
    function woocommerce_add_osm_epay_gateway($methods) {
        $methods[] = 'WC_osm_epay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_osm_epay_gateway' );
}
