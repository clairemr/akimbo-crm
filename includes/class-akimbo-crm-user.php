<?php

/**
 * User object
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.0
 *
 * @package    Akimbo_Crm
 * @subpackage Akimbo_Crm/user
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */

class Akimbo_Crm_User extends WP_User{

	public $user_id;
	private $student_ids;
	private $user_student_id;
	private $userdata;
	private $firstname;

	function __construct($user_id){
		global $wpdb;
		$this->user_id = $user_id;
		parent::__construct( $user_id );
		$student_ids = $wpdb->get_results("SELECT student_id,student_rel FROM {$wpdb->prefix}crm_students WHERE user_id = $user_id");
		$this->metadata = get_user_meta( $user_id );
		$this->firstname = $this->metadata['first_name'][0];//for some reason first_name is an array
		if(isset($student_ids)){
			foreach($student_ids as $student_id){
				if($student_id->student_rel == 'user'){$this->user_student_id = $student_id->student_id;}
				$this->student_ids .= $student_id->student_id;
			}
		}else{//add new user student
			$previous_student = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1");
			$table = $wpdb->prefix.'crm_students';
			$data = array('student_firstname' => $customer->first_name,'student_rel' => 'user','user_id' => $user_id,);
			$wpdb->insert($table, $data);
			$student_id = $previous_student+1;
			$this->user_student_id = $student_id->student_id;
		}
	}

	function get_user_student_id(){
		global $wpdb;
		if(isset($this->user_student_id)){//student_rel == user
			$id = $this->user_student_id;
		}elseif(isset($this->student_ids)){
			/* Construct function should automatically add new user student, making everything below
			* this mostly redundant, but it's here as a back up
			*/
			$id = $this->student_ids[0];//defaults first student to user
			$table = $wpdb->prefix.'crm_students';
			$data = array('student_rel' => 'user',);
			$data = array('student_id' => $id,);
			update( $table, $data, $where);
		}else{//add new student
			$id = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1 ") + 1;
			akimbo_crm_auto_add_user_as_student($this->user_id, $this->firstname);
		}
		return $id;
	}

	function get_student_ids(){
		return $this->student_ids;
	}

	function student_dropdown($name = "student_id", $exclude = NULL){
		global $wpdb;
		$exclude = ($exclude != NULL) ? $exclude : array();
		$select = "<select name= '".$name."'>";
		$user_id = $this->user_id;
		$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = '$user_id'");
		foreach ($students as $student){
			$student_id = $student->student_id;
			if(in_array($student_id, $exclude)){//don't show
			} else {
				$select .= "<option value='".$student_id."'>".$student->student_firstname." ".$student->student_lastname."</option>";
			}	
		}
		$select .= "</select>";
		
		return $select;
	}

	function add_student(){

		
		return $this->firstname;
	}

	function get_firstname(){
		return $this->firstname;
	}
	
	function get_user_subscriptions($age = NULL){
		global $wpdb;
		$age = ($age != NULL) ? $age : "adult";
		$option = 'akimbo_crm_'.$age.'_class_products';
		$product_ids = get_option($option);		

		$users_subscriptions = wcs_get_users_subscriptions($this->user_id);
		//currently will only work for one subscription, check for multiple subscription behaviour
		foreach ($users_subscriptions as $subscription){
			if ($subscription->has_status(array('active'))) {
				$subscription_info = array('id' => $subscription->get_id());
				
				$parent = method_exists( $subscription, 'get_parent_id' ) ? $subscription->get_parent_id() : $subscription->order->id;
				$orders = $subscription->get_related_orders('all');
				$order = reset( $orders );
				$items = $order->get_items();
				
				//$subscription_info['items'] = $order->get_items();
				foreach ( $items as $item_id => $item_data ) {
					$product_id = $item_data['product_id'];
					//$subscription_info['product_id'] = $item_data['product_id'];
					//$subscription_info['options'] = $product_ids;
					if(in_array($product_id, $product_ids)){
						$subscription_info['order_id'] = $order->get_id();
						$subscription_info['product_id'] = $item_data['product_id'];
						$subscription_info['item_id'] = $item_id;
						$subscription_info['options'] = $product_ids;
						if(isset($item_data['pa_sessions']) || isset($item_data['sessions'])){
							$subscription_info['sessions'] = (isset($item_data['sessions'])) ? $item_data['sessions'] : $item_data['pa_sessions'];
							$subscription_info['passes'] = $subscription_info['sessions'];//allows sessions & weeks to be used interchangeably
							$subscription_info['sessions_used'] = (isset($item_data['sessions_used'])) ? $item_data['sessions_used'] : 0;
							$subscription_info['remaining'] = $subscription_info['sessions'] - $subscription_info['sessions_used'];
						}elseif(isset($item_data['weeks'])){
							$subscription_info['weeks'] = $item_data['weeks'];
							$subscription_info['passes'] = $subscription_info['weeks'];//allows sessions & weeks to be used interchangeably
							$subscription_info['weeks_used']= (isset($item_data['weeks_used'])) ? $item_data['weeks_used'] : 0;
							$subscription_info['remaining'] = $subscription_info['weeks'] - $subscription_info['weeks_used'];
						}
						$subscription_info['expiry'] = (isset($item_data['expiry_date'])) ? $item_data['expiry_date'] : $subscription->get_date( 'next_payment' );
						$subscription_info['parent'] = $parent;
						$subscription_info['active'] = true;
						$subscription_info['type'] = "subscription";
						$subscription_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-subscription/".$subscription_info['id']."/'>View Subscription</a>";
						$subscription_info['next'] = $subscription->get_date( 'next_payment' );
					}			
				}
			}			  
		}
		
		return $subscription_info;
	}

	function available_orders($age = NULL){
		global $wpdb;
		$age = ($age != NULL) ? $age : "adult";
		$option = 'akimbo_crm_'.$age.'_class_products';
		$product_ids = get_option($option);
		$order_info['options'] = $product_ids;
		$today = current_time('Y-m-d-h:ia');
		$statuses = ['completed','processing'];
		$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC','customer_id' => $this->user_id,'status' => $statuses) );
		$orders = $query->get_orders();
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			$crm_order = wc_get_order($order_id);
			$order_info['$order_id'] = $order_id;
			$items = $crm_order->get_items();
			foreach ( $items as $item_id => $item_data ) {
				if(in_array($item_data['product_id'], $product_ids)){
					$order_info['product_id'] = $item_data['product_id'];
					
					if(isset($item_data['pa_sessions']) || isset($item_data['sessions'])){
						$order_info['sessions'] = (isset($item_data['sessions'])) ? $item_data['sessions'] : $item_data['pa_sessions'];
						$order_info['passes'] = $order_info['sessions'];//allows sessions & weeks to be used interchangeably
						$order_info['sessions_used'] = (isset($item_data['sessions_used'])) ? $item_data['sessions_used'] : 0;
						$order_info['remaining'] = $order_info['sessions'] - $order_info['sessions_used'];
						$order_info['product_id'] = $item_data['product_id'];
						if($order_info['remaining'] >= 1 ){
							$order_info['type'] = "casual";
							$order_info['order_id'] = $order_id;
							$order_info['product_id'] = $item_data['product_id'];
							$order_info['item_id'] = $item_id;
							$exp_date = $order->get_meta('expiry_date');
							if($exp_date <= 0){
								$year = date("Y", strtotime($crm_order->order_date)) + 1;
								$format = $year."-m-d-h:ia";
								$exp_date = date($format, strtotime($crm_order->order_date));
							}
							$order_info['expiry'] = $exp_date;
							$order_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-order/".$order_info['$order_id']."/'>View Order</a>";	
							return $order_info;
							break;//use the first available order with remaining sessions
						}
						
					}elseif(isset($item_data['weeks'])){
						$order_info['weeks'] = $item_data['weeks'];
						$order_info['passes'] = $order_info['weeks'];//allows sessions & weeks to be used interchangeably
						$order_info['weeks_used']= (isset($item_data['weeks_used'])) ? $item_data['weeks_used'] : 0;
						$order_info['remaining'] = $order_info['weeks'] - $order_info['weeks_used'];
						if($order_info['remaining'] >= 1 ){
							$order_info['type'] = "enrolment";
							$order_info['order_id'] = $order_id;
							$order_info['product_id'] = $item_data['product_id'];
							$order_info['item_id'] = $item_id;
							$exp_date = $order->get_meta('expiry_date');
							if($exp_date <= 0){
								$year = date("Y", strtotime($crm_order->order_date)) + 1;
								$format = $year."-m-d-h:ia";
								$exp_date = date($format, strtotime($crm_order->order_date));
							}
							$order_info['expiry'] = $exp_date;
							$order_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-order/".$order_info['$order_id']."/'>View Order</a>";	
							return $order_info;
							break;//use the first available order with remaining weeks
						}
					}

					
				}		
			}
		}
		
		return $order_info;
	}

	function user_admin_link($display = NULL){//text to display or link only
		if($display != NULL){
			$url = "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".$this->user_id."'>".$display."</a>";
		}else{
			$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".$this->user_id;
		}
		return $url;
	}
}