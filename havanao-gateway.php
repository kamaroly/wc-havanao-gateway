<?php
/**
 * Plugin Name:     WooCommerce Havanao Payment Gateway
 * Plugin URI:      https://havanao.com
 * Description:     Handle payments to havanao, Lambert contributed to have this working in production.
 * Version:         1.0.2
 * Author:          support@havanao.com
 * Author URI:      https://havanao.com
 * Text Domain:     havanao
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:     /languages
 */

add_action( 'plugins_loaded', 'HavanaoGateWay' );

function HavanaoGateWay() {

	add_filter( 'woocommerce_payment_gateways', 'addHavanaoGateWayClass' );

	function addHavanaoGateWayClass( $methods ) {
		$methods[] = 'WooCommerceHavanaoGateWay';
		return $methods;
	}
	
	if ( ! class_exists( 'WooCommerceHavanaoGateWay' ) ) {
		/**
		 * havanao Payment Gateway
		 *
		 * Provides an havanao Payment Gateway
		 *
		 * @class WooCommerceHavanaoGateWay
		 * @extends WC_Payment_Gateway
		 * @version 1.0.0
		 * @author Kanzu Code
		 */
		class WooCommerceHavanaoGateWay extends WC_Payment_Gateway {
			/**
			 * Constructor for the gateway.
			 */
			public function __construct() {
				$this->id                 = 'havanao';
				$this->icon               = apply_filters( 'woocommerce_havanao_icon', '' );
				$this->has_fields         = true;
				$this->method_title       = __( 'Havanao Payments', 'havanao' );
				$this->method_description = __( '', 'havanao' );

				// Define havanao specific configuration
				if ( 'yes' == $this->get_option( 'test_enabled' ) ) {
					$this->gateway_url = 'https://staging.havanao.com/api/sale/purchase';
				} else {
					$this->gateway_url = 'https://api.havanao.com/api/sale/purchase';
				}

				$this->havanao_api_key      = $this->get_option( 'havanao_api_key' );
				$this->consumer_secret      = $this->get_option( 'consumer_secret' );
				
				$this->successPaymentStatus = trim($this->get_option( 'success_payment_status' ),'wc-');
				$this->pendingPaymentStatus = trim($this->get_option( 'pending_payment_status' ),'wc-');
				$this->erroredPaymentStatus = trim($this->get_option( 'error_payment_status' ),'wc-');
				
				$this->callBackURL          = add_query_arg( 'wc-api', 'WC_Callback_Gateway', home_url( '/' ) );

				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();

				// Define user set variables
				$this->title        = $this->get_option( 'title' );
				$this->description  = $this->get_option( 'description' );
				$this->instructions = $this->get_option( 'instructions' );

				// Actions
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou_havanao', array( $this, 'thankyou_page' ) );

				// Customer Emails
				add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

				// The end-point that receives the response of the transaction
				add_action( 'woocommerce_api_wc_callback_gateway', array( $this, 'handle_result' ) );
			}

			/**
			 * Initialise Gateway Settings Form Fields.
			 */
			public function init_form_fields() {

				$this->form_fields = array(
					'enabled'            => array(
						'title'   => __( 'Enable/Disable', 'havanao' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable havanao payments', 'havanao' ),
						'default' => 'no',
					),
					'title'           => array(
						'title'       => __( 'Title', 'havanao' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'havanao' ),
						'default'     => __( 'Havanao Payments', 'havanao' ),
						'desc_tip'    => true,
					),
					'description'        => array(
						'title'       => __( 'Description', 'havanao' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description that the customer will see on your checkout.', 'havanao' ),
						'default'     => __( 'Please have your phone ready to confirm payment', 'havanao' ),
						'desc_tip'    => true,
					),
					'instructions'       => array(
						'title'       => __( 'Instructions', 'havanao' ),
						'type'        => 'textarea',
						'description' => __( 'Instructions that will be added to the thank you page and emails.', 'havanao' ),
						'default'     => __( 'Dial *182*7# on MTN to confirm pending payment', 'havanao' ),
						'desc_tip'    => true,
					),
					'success_payment_status'       => array(
						'title'   => __( 'Successful Payment Order Status', 'havanao' ),
						'type'    => 'select',
						'default' => 'wc-completed',
						'options' => wc_get_order_statuses(),
					),
					'pending_payment_status'       => array(
						'title'   => __( 'Pending Payment Order Status', 'havanao' ),
						'type'    => 'select',
						'default' => 'wc-on-hold',
						'options' => wc_get_order_statuses(),
					),
					'error_payment_status'       => array(
						'title'   => __( 'Errored Payment Order Status', 'havanao' ),
						'type'    => 'select',
						'default' => 'wc-pending',
						'options' => wc_get_order_statuses(),
					),
					'test_enabled'       => array(
						'title'   => __( 'Enable/Disable Test Mode', 'havanao' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Test Mode', 'havanao' ),
						'default' => 'no',
					),
					'havanao_api_key'       => array(
						'title'       => __( 'Havanao API Key', 'havanao' ),
						'type'        => 'text',
						'description' => __( 'Havanao API Key required for authentication.' ),
						'default'     => '',
						'desc_tip'    => true,
					)
				);
			}

			/**
			 * Output for the order received page.
			 */
			public function thankyou_page() {
				if ( $this->instructions ) {
					echo wpautop( wptexturize( $this->instructions ) );
				}
			}

			/**
			 * Add content to the WC emails.
			 *
			 * @access public
			 * @param WC_Order $order
			 * @param bool $sent_to_admin
			 * @param bool $plain_text
			 */
			public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
				if ( $this->instructions && ! $sent_to_admin && 'havanao' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
					echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
				}
			}

			/**
			 * Process the payment and return the result.
			 *
			 * @param int $order_id
			 * @return array
			 */
			public function process_payment( $order_id ) {

				$order        = wc_get_order( $order_id );
				$phone        = $this->sanatizePhone( $_POST['havanao_phone_number'] );
				$timestamp    = date( 'Ymdhis' );

				// Do transaction push to customer
				$data = [
					'customer'      => $phone,
					'amount'        => (int) $order->get_total(),
					'transactionid' => implode("-", str_split(strtoupper(uniqid('TXN')), 4)). '-'.$order_id,
					'comment'       =>  __('Payment for order number ') . $order_id,
					'callback_url'  => add_query_arg( 'order-id', $order_id, $this->callBackURL ),
				];

				// Add Authentication to the URL 
				$this->gateway_url = $this->gateway_url.'?api_token=' . $this->havanao_api_key;
		

				$response = wp_remote_post(
					$this->gateway_url, 
					['body'    => json_encode( $data ),'timeout' => 45 ]
				);
			  	
			   // ONLY LOG ON TEST ENABLED
			   if( 'yes' == $this->get_option( 'test_enabled' ) ) {
					$order->add_order_note(json_encode( $data ));
					$order->add_order_note($this->gateway_url);
				}
				// Log request and response to havanao
				if ( ! is_wp_error( $response ) ) {
					
					$order->add_order_note($response['body']);
					
					$response = json_decode( $response['body'] );

					if ( isset( $response->code ) && '200' == $response->code ) {
						// Mark as on-hold (we're awaiting the havanao payment)
						$order->update_status( $this->pendingPaymentStatus, __( 'Awaiting havanao payment.', 'havanao' ) );

						// Reduce stock levels
						wc_reduce_stock_levels( $order_id );

						// Remove cart
						WC()->cart->empty_cart();

						// Return thankyou redirect
						return array(
							'result'   => 'success',
							'redirect' => $this->get_return_url( $order ),
						);
					} else {
						wc_add_notice( __( 'There was an error making the payment.', 'havanao' ), 'error' );

						return;
					}
				} else {
				    // Only Log when test mode is enabled
				    if( 'yes' == $this->get_option( 'test_enabled' ) ) {
				    	$order->add_order_note($this->gateway_url);
						// Add error to the note
						$order->add_order_note($response->get_error_message(),'error');
				    }
					
					wc_add_notice( __( 'There was an error making the payment. Please try again.', 'havanao' ), 'error' );
					$order->update_status( $this->erroredPaymentStatus, __( 'There was an error making the payment.', 'havanao' ) );
					// return;
				}


				// Return thankyou redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}

			/**
			 * Handle the response of the transaction
			 */
			public function handle_result() {
				if ( ! isset( $_REQUEST['order-id'] ) || empty( $_REQUEST['order-id'] ) ) {
					return;
				}

				$response      = file_get_contents( 'php://input' );
				$jsonResponse  = json_decode( $response );
				$responseArray = json_decode($response,true);
				
				$order         = wc_get_order( $_REQUEST['order-id'] );
				
			    $order->add_order_note( $this->generateTable($responseArray),'success' );
			    // Only Log when test mode is enabled
			    if( 'yes' == $this->get_option( 'test_enabled' ) ) {
			    	$order->add_order_note( $response );
			    }
			    

				if ( isset( $jsonResponse->transactionStatus ) && 'APPROVED' == $jsonResponse->transactionStatus ) {

					if ( $order ) {
						// Complete this order, otherwise set it to another status as per configurations
						$order->payment_complete();
						$order->update_status( $this->get_option( 'success_payment_status' ), __( 'Havano Payment was Successful.', 'havanao' ) );	
					}
				}
			}

			/**
			 * Add payment field to get phone number to charge
			 */
			public function payment_fields() {
				?>
				<div class="wc-gateway-havanao">
					<p><?php _e( 'Please enter the mobile number you want to charge:' ); ?></p>
					<input type="text" name="havanao_phone_number" class="wc-gateway-havanao__phone-number" />
				</div>
				<?php
			}

			/**
			 * Validate that the phone number is Kenyan
			 */
			public function validate_fields() {

				$phone = $this->sanatizePhone( $_POST['havanao_phone_number'] );

				if ( strlen( trim( preg_replace( '/^(\+?250)\d{9}/', '', $phone ) ) ) ) {
					wc_add_notice( __( 'Invalid phone number provided. Please provide a valid Rwanda mobile phone number', 'havanao' ), 'error' );
					return false;
				}
				return true;
			}

	
			
			/**
			 * Clean phone numbesr
			 * @param   $phonenumber 
			 * @return    string    
			 */
			private function sanatizePhone($phonenumber)
			{
				$phonenumber =preg_replace('/[^0-9]+/', '', $phonenumber);
				// INVALID PHONE
				if (strlen($phonenumber)<>12 && strlen($phonenumber)<>10 && strlen($phonenumber)<>9) {
				    return 'Invalid phone number provided, Please check provided correct phone number try again.';
				}
				// Convert any phone into 254 format
				return '250'.substr($phonenumber,-9);
			}
			/**
			 * Generate table from the array
			 * @param   $myTableArrayBody 
			 * @return  
			 */
			public function generateTable($myTableArrayBody) {
			    $x = 0;
			    $y = 0;
			    $seTableStr = '<table><caption><h3>HAVANAO PAYMENT DETAILS</h3></caption><tbody>';		    
			    foreach ($myTableArrayBody as $key => $value) {
			    	$seTableStr = $seTableStr.'<tr><th>'.strtoupper($key).'</th><td>'.$value.'</td></tr>';
			    }
			    $seTableStr .= '</tbody></table>';
			    return $seTableStr;
			}

		}
	}
}
