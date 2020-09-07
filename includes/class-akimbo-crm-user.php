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
				$this->student_ids[] = $student_id->student_id;
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
	
	function get_id(){
		return $this->user_id;
	}

	function get_firstname(){
		return $this->firstname;
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
			$wpdb->update( $table, $data, $where);
		}else{//add new student
			$id = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1 ") + 1;
			akimbo_crm_auto_add_user_as_student($this->user_id, $this->firstname);
		}
		return $id;
	}

	function get_student_ids(){
		return $this->student_ids;
	}

	function get_students($format = "object"){
		$result = false;
		if(isset($this->student_ids)){
			if($format == "ids"){
				$result = $this->student_ids;
			}else{
				foreach($this->student_ids as $student_id){
					$student = new Akimbo_Crm_Student($student_id);
					if($format == "object"){
						$result[] = $student;
					}else{
						$result .= $student->full_name().". ".$student->student_admin_link("View")."<br/>";
					}
				}
			}
		}
		
		return $result;
	}

	function student_dropdown($name = "student_id", $exclude = NULL){
		return crm_student_dropdown($name, $exclude, $this->user_id);
	}

	function get_available_user_orders($age = NULL, $single = false, $available = true){
		global $wpdb;
		if($age != NULL){$product_ids = akimbo_crm_return_posts_by_meta("age_slug", $age, "id");}

		/*
		*Check for a valid subscription first
		*/
		if($single == true){
			$users_subscriptions = wcs_get_users_subscriptions($this->user_id);
			if(isset($users_subscriptions)){
				foreach ($users_subscriptions as $subscription){
					if ($subscription->has_status(array('active'))) {
						$subscription_info = array('id' => $subscription->get_id());
						//$parent = method_exists( $subscription, 'get_parent_id' ) ? $subscription->get_parent_id() : $subscription->order->id;
						$orders = $subscription->get_related_orders('all');
						$available_order = reset( $orders );
						$items = $available_order->get_items();
						foreach ( $items as $item_id => $item_data ) {
							if($age != NULL){//check against product ids
								if(!in_array($item_data['product_id'], $product_ids)){
									continue;//skip rest of iteration
								}
							}
							$order_info = crm_get_item_available_passes($item_id, $available_order);
							if($order_info['available'] == true){
								$order_info['subscription'] = true;
								$order_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-subscription/".$subscription_info['id']."/'>View Subscription</a>" ;
								$available_orders = $order_info;
								break;
							}			
						}
					}
				}
			}
		}
		
		/**
		 * If none found or single != true, continue searching for all available orders
		 */
		if($single == true && isset($available_orders)){//do nothing, use subscription order first
		}else{//check for all orders
			$statuses = ['completed','processing'];//only count paid orders
			$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC','customer_id' => $this->user_id,'status' => $statuses) );
			$orders = $query->get_orders();//this replaces subscription orders too, to avoid getting orders twice
			foreach ( $orders as $available_order ) {
				$items = $available_order->get_items();
				foreach ( $items as $item_id => $item_data ) {
					if($age != NULL){//check against product ids
						if(!in_array($item_data['product_id'], $product_ids)){
							continue;//skip rest of iteration
						}
					}
					$order_info = crm_get_item_available_passes($item_id, $available_order);
					if($order_info['available'] == true){						
						if($single == true){
							$available_orders = $order_info;
							break;
						}else{
							$available_orders[] = $order_info;
						}
					}			
				}				
			}
		}

		if(!isset($available_orders)){//no subscriptions, no orders
			return false;
		}else{
			return $available_orders;
		}
	}
	/**
	 * Links to student details page by default, or to Wordpress user edit page
	 */
	function user_admin_link($display = NULL, $link = "admin"){//text to display or link only
		$url = ($link == "admin") ? akimbo_crm_permalinks("students")."&user=".$this->user_id : get_edit_user_link( $this->user_id);
		$url = ($display != NULL) ? "<a href='".$url."'>".$display."</a>" : $url;
		return $url;
	}
}