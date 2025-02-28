<?php 
/**
 * Plugin Name: Nagad
 * Plugin URI:  http://teemteem.com/ 
 * Description: Nagad is a mobile financial service (MFS) in Bangladesh operating under the authority of Bangladesh Post. This plugin depends on woo-commerce and will provide an extra payment gateway through nagad on checkout page.
 * Version:     2.0.0
 * Author:      TeemTeem 
 * Author URI:  http://teemteem.com/shakib
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: skr
 */
 
defined('ABSPATH') or die('do not try to access directly to see the page. :-) ');

/**
 * Plugin language
 */
 
add_action( 'init', 'teemteem_nagad_language_setup' );
function teemteem_nagad_language_setup() {
  load_plugin_textdomain( 'skr', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * nagad gateway register
 */
add_filter('woocommerce_payment_gateways', 'teemteem_nagad_payment_gateways');
function teemteem_nagad_payment_gateways( $gateways ){
	$gateways[] = 'teemteem_nagad';
	return $gateways;
}

/**
 * nagad gateway init
 */
add_action('plugins_loaded', 'teemteem_nagad_plugin_activation');
function teemteem_nagad_plugin_activation(){
	
	class teemteem_nagad extends WC_Payment_Gateway {

		public $nagad_number;
		public $number_type;
		public $order_status;
		public $instructions;
		public $nagad_charge;

		public function __construct(){
			$this->id 					= 'teemteem_nagad';
			$this->title 				= $this->get_option('title', 'Nagad');
			$this->description 			= $this->get_option('description', 'Nagad Payment Gateway');
			$this->method_title 		= esc_html__("Nagad", "skr");
			$this->method_description 	= esc_html__("Nagad Payment Gateway for Personal or Agent Account", "skr" );
			$this->icon 				= plugins_url('images/nagad.png', __FILE__);
			$this->has_fields 			= true;

			$this->teemteem_nagad_options_fields();
			$this->init_settings();
			
			$this->nagad_number = $this->get_option('nagad_number');
			$this->number_type 	= $this->get_option('number_type');
			$this->order_status = $this->get_option('order_status');
			$this->instructions = $this->get_option('instructions');
			$this->nagad_charge = $this->get_option('nagad_charge');

			add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );
            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'teemteem_nagad_thankyou_page' ) );
            add_action( 'woocommerce_email_before_order_table', array( $this, 'teemteem_nagad_email_instructions' ), 10, 3 );
		}


		public function teemteem_nagad_options_fields(){
			$this->form_fields = array(
				'enabled' 	=>	array(
					'title'		=> esc_html__( 'Enable/Disable', "skr" ),
					'type' 		=> 'checkbox',
					'label'		=> esc_html__( 'Enable Nagad Payment', "skr" ),
					'default'	=> 'yes'
				),
				'title' 	=> array(
					'title' 	=> esc_html__( 'Title', "skr" ),
					'type' 		=> 'text',
					'default'	=> esc_html__( 'Nagad', "skr" )
				),
				'description' => array(
					'title'		=> esc_html__( 'Description', "skr" ),
					'type' 		=> 'textarea',
					'default'	=> esc_html__( 'At first complete your nagad payment, then try to fill up the form below.', "skr" ),
					'desc_tip'    => true
				),
                'order_status' => array(
                    'title'       => esc_html__( 'Order Status', "skr" ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => esc_html__( 'Choose whether status you wish after checkout.', "skr" ),
                    'default'     => 'wc-on-hold',  
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),				
				'nagad_number'	=> array(
					'title'			=> 'Nagad Number',
					'description' 	=> esc_html__( 'Add a nagad mobile number to show on checkout page', "skr" ),
					'type'			=> 'text',
					'desc_tip'      => true
				),
				'number_type'	=> array(
					'title'			=> esc_html__( 'Agent/Personal', "skr" ),
					'type'			=> 'select',
					'class'       	=> 'wc-enhanced-select',
					'description' 	=> esc_html__( 'Select nagad account type', "skr" ),
					'options'	=> array(
						'Agent'		=> esc_html__( 'Agent', "skr" ),
						'Personal'	=> esc_html__( 'Personal', "skr" )
					),
					'desc_tip'      => true
				),
				'nagad_charge' 	=>	array(
					'title'			=> esc_html__( 'Add Nagad Charge', "skr" ),
					'type' 			=> 'checkbox',
					'label'			=> esc_html__( 'Add 1.8% "Cash Out" Charge with net price', "skr" ),
					'description' 	=> esc_html__( 'If a product price is 100 then customer have to pay ( 100 + 2 ) = 102. Here 2 is Cash Out charge', "skr" ),
					'default'		=> 'no',
					'desc_tip'    	=> true
				),						
                'instructions' => array(
                    'title'       	=> esc_html__( 'Instructions', "skr" ),
                    'type'        	=> 'textarea',
                    'description' 	=> esc_html__( 'Instructions that will be added to the thank you page and emails.', "skr" ),
                    'default'     	=> esc_html__( 'Thanks for purchasing through Nagad. We will check and process as soon as possible.', "skr" ),
                    'desc_tip'    	=> true
                ),								
			);
		}


		public function payment_fields(){

			global $woocommerce;
			$nagad_charge = ($this->nagad_charge == 'yes') ? esc_html__(' Also note that 1.8% nagad "SEND MONEY" cost will be added with net price. Total amount you need to send us at', "skr" ). ' ' . get_woocommerce_currency_symbol() . $woocommerce->cart->total : '';
			echo wpautop( wptexturize( esc_html__( $this->description, "skr" ) ) . $nagad_charge  );
			echo wpautop( wptexturize( "Nagad ".$this->number_type." Number : ".$this->nagad_number ) );

			?>
				<p>
					<label for="nagad_number"><?php esc_html_e( 'Nagad Number', "skr" );?></label>
					<input type="text" name="nagad_number" id="Nagad_number" placeholder="017XXXXXXXX">
				</p>
				<p>
					<label for="nagad_transaction_id"><?php esc_html_e( 'Transaction ID', "skr" );?></label>
					<input type="text" name="nagad_transaction_id" id="nagad_transaction_id" placeholder="A7D8H65FGH90">
				</p>
			<?php 
		}
		

		public function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			$status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
			// Mark as on-hold (we're awaiting the nagad)
			$order->update_status( $status, esc_html__( 'Checkout with nagad payment. ', "skr" ) );

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}	


        public function teemteem_nagad_thankyou_page() {
		    $order_id = get_query_var('order-received');
		    $order = new WC_Order( $order_id );
		    if( $order->payment_method == $this->id ){
	            $thankyou = $this->instructions;
	            return $thankyou;		        
		    } else {
		    	return esc_html__( 'Thank you. Your order has been received.', "skr" );
		    }

        }


        public function teemteem_nagad_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		    if( $order->payment_method != $this->id )
		        return;        	
            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

	}

}

/**
 * If nagad charge is activated
 */
$nagad_charge = get_option( 'woocommerce_teemteem_nagad_settings' );
if( $nagad_charge['nagad_charge'] == 'yes' ){

	add_action( 'wp_enqueue_scripts', 'teemteem_nagad_script' );
	function teemteem_nagad_script(){
		wp_enqueue_script( 'skr-script', plugins_url( 'js/scripts.js', __FILE__ ), array('jquery'), '1.0', true );
	}

	add_action( 'woocommerce_cart_calculate_fees', 'teemteem_nagad_charge' );
	function teemteem_nagad_charge(){

	    global $woocommerce;
	    $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
	    $current_gateway = '';

	    if ( !empty( $available_gateways ) ) {
	        if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
	            $current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
	        } 
	    }
	    
	    if( $current_gateway!='' ){

	        $current_gateway_id = $current_gateway->id;

			if ( is_admin() && ! defined( 'DOING_AJAX' ) )
				return;

			if ( $current_gateway_id =='teemteem_nagad' ) {
				$percentage = 0.018;
				$surcharge = ( $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total ) * $percentage;	
				$woocommerce->cart->add_fee( esc_html__('Nagad Charge', 'skr'), $surcharge, true, '' ); 
			}
	       
	    }    	
	    
	}
	
}

/**
 * Empty field validation
 */
add_action( 'woocommerce_checkout_process', 'teemteem_nagad_payment_process' );
function teemteem_nagad_payment_process(){

    if($_POST['payment_method'] != 'teemteem_nagad')
        return;

    $nagad_number = sanitize_text_field( $_POST['nagad_number'] );
    $nagad_transaction_id = sanitize_text_field( $_POST['nagad_transaction_id'] );

    $match_number = isset($nagad_number) ? $nagad_number : '';
    $match_id = isset($nagad_transaction_id) ? $nagad_transaction_id : '';

    $validate_number = preg_match( '/^01[5-9]\d{8}$/', $match_number );
    $validate_id = preg_match( '/[a-zA-Z0-9]+/',  $match_id );

    if( !isset($nagad_number) || empty($nagad_number) )
        wc_add_notice( esc_html__( 'Please add your mobile number', 'skr'), 'error' );

	if( !empty($nagad_number) && $validate_number == false )
        wc_add_notice( esc_html__( 'Incorrect mobile number. It must be 11 digit, starts with 015 / 016 / 017 / 018 / 019', 'skr'), 'error' );

    if( !isset($nagad_transaction_id) || empty($nagad_transaction_id) )
        wc_add_notice( esc_html__( 'Please add your nagad transaction ID', 'skr' ), 'error' );

	if( !empty($nagad_transaction_id) && $validate_id == false )
        wc_add_notice( esc_html__( 'Only number or letter is acceptable', 'skr'), 'error' );

}

/**
 * Update nagad field to database
 */
add_action( 'woocommerce_checkout_update_order_meta', 'teemteem_nagad_additional_fields_update' );
function teemteem_nagad_additional_fields_update( $order_id ){

    if($_POST['payment_method'] != 'teemteem_nagad' )
        return;

    $nagad_number = sanitize_text_field( $_POST['nagad_number'] );
    $nagad_transaction_id = sanitize_text_field( $_POST['nagad_transaction_id'] );

	$number = isset($nagad_number) ? $nagad_number : '';
	$transaction = isset($nagad_transaction_id) ? $nagad_transaction_id : '';

	update_post_meta($order_id, '_nagad_number', $number);
	update_post_meta($order_id, '_nagad_transaction', $transaction);

}

/**
 * Admin order page nagad data output
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'teemteem_nagad_admin_order_data' );
function teemteem_nagad_admin_order_data( $order ){
    
    if( $order->payment_method != 'teemteem_nagad' )
        return;

	$number = (get_post_meta($order->id, '_nagad_number', true)) ? get_post_meta($order->id, '_nagad_number', true) : '';
	$transaction = (get_post_meta($order->id, '_nagad_transaction', true)) ? get_post_meta($order->id, '_nagad_transaction', true) : '';

	?>
	<div class="form-field form-field-wide">
		<img src='<?php echo plugins_url("images/nagad.png", __FILE__); ?>' alt="nagad">	
		<table class="wp-list-table widefat fixed striped posts">
			<tbody>
				<tr>
					<th><strong><?php esc_html_e('Nagad No', 'skr') ;?></strong></th>
					<td>: <?php echo esc_attr( $number );?></td>
				</tr>
				<tr>
					<th><strong><?php esc_html_e('Transaction ID', 'skr') ;?></strong></th>
					<td>: <?php echo esc_attr( $transaction );?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php 
	
}

/**
 * Order review page nagad data output
 */
add_action('woocommerce_order_details_after_customer_details', 'teemteem_nagad_additional_info_order_review_fields' );
function teemteem_nagad_additional_info_order_review_fields( $order ){
    
    if( $order->payment_method != 'teemteem_nagad' )
        return;

	$number = (get_post_meta($order->id, '_nagad_number', true)) ? get_post_meta($order->id, '_nagad_number', true) : '';
	$transaction = (get_post_meta($order->id, '_nagad_transaction', true)) ? get_post_meta($order->id, '_nagad_transaction', true) : '';

	?>
		<tr>
			<th><?php esc_html_e('Nagad No:', 'skr');?></th>
			<td><?php echo esc_attr( $number );?></td>
		</tr>
		<tr>
			<th><?php esc_html_e('Transaction ID:', 'skr');?></th>
			<td><?php echo esc_attr( $transaction );?></td>
		</tr>
	<?php 
	
}	

/**
 * Register new admin column
 */
add_filter( 'manage_edit-shop_order_columns', 'teemteem_nagad_admin_new_column' );
function teemteem_nagad_admin_new_column($columns){

    $new_columns = (is_array($columns)) ? $columns : array();
    unset( $new_columns['order_actions'] );
    $new_columns['mobile_no'] 	= esc_html__('Nagad No', 'skr');
    $new_columns['tran_id'] 	= esc_html__('TXN ID', 'skr');

    $new_columns['order_actions'] = $columns['order_actions'];
    return $new_columns;

}

/**
 * Load data in new column
 */
add_action( 'manage_shop_order_posts_custom_column', 'teemteem_nagad_admin_column_value', 2 );
function teemteem_nagad_admin_column_value($column){

    global $post;

    $mobile_no = (get_post_meta($post->ID, '_nagad_number', true)) ? get_post_meta($post->ID, '_nagad_number', true) : '';
    $tran_id = (get_post_meta($post->ID, '_nagad_transaction', true)) ? get_post_meta($post->ID, '_nagad_transaction', true) : '';

    if ( $column == 'mobile_no' ) {    
        echo esc_attr( $mobile_no );
    }
    if ( $column == 'tran_id' ) {    
        echo esc_attr( $tran_id );
    }
}

//setting link check up
function nagad_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=teemteem_nagad">' . __( 'Settings' ) . '</a>';
	
	
    array_push( $links, $settings_link );
  	return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'nagad_settings_link' );
