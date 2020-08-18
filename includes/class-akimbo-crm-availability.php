<?php

/**
 * The booking functionality of the plugin.
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.0
 *
 * @package    Akimbo_Crm
 * @subpackage Akimbo_Crm/parties
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */

//class Akimbo_Crm_Booking extends WC_Order{//find the correct class to extend, this one doesn't work
//Potentially can't directly extend the class, instantiate new object instead
class Akimbo_Crm_Availability{

	public $id;
	private $booking_date;
	//private $product_id;
	private $senior_trainer;
	private $junior_trainer;
	private $availabilities;
	private $info;
	private $details;
	//private $customer_id;
	//$customer = new WP_User($this->$customer_id);


	function __construct($id){
		$this->id = $id;
		global $wpdb;
		$info = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_availability LEFT JOIN {$wpdb->prefix}crm_booking_meta ON {$wpdb->prefix}crm_availability.avail_id = {$wpdb->prefix}crm_booking_meta.avail_id WHERE {$wpdb->prefix}crm_availability.avail_id = $id");
		$this->info = $info;
		foreach($info as $detail){
			$this->details[$detail->meta_key] = $detail->meta_value;
			$this->booking_date = $detail->session_date;
			$this->products = $detail->prod_id;
		}
		if(!isset($this->details['availabilities'])){
			$table = $wpdb->prefix.'crm_booking_meta';
			$data = array(
				'avail_id' => $this->id,
				'meta_key' => 'availabilities',
				'meta_value' => serialize(array(0))
			);
			$result = $wpdb->insert($table, $data);
			$this->availabilities = serialize(array(0));
		}else{
			$this->availabilities = $this->details['availabilities'];
		}
		
	}
	
	function get_booking_meta(){
		return $this->details;
	}

	function get_booking_date($format = "g:ia, l jS M"){
		$booking_date = (!$this->booking_date) ? "Date not set (insert function)" : date($format, strtotime($this->booking_date));
		
		return $booking_date;
	}

	function get_booking_info(){
		return $this->info;
	}

	function get_availabilities($format = "unserialize"){
		$availabilities = ($format == "serialize") ? $this->details['availabilities'] : unserialize($this->details['availabilities']);
		return $availabilities;
	}

	function get_products($format = "unserialize"){
		$products = ($format == "serialize") ? $this->products : unserialize($this->products);
		return $products;
	}
	
	function book_button(){
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input type="hidden" name="id" value="<?php echo $this->id; ?>">
		<input type="hidden" name="action" value="crm_add_booking_no_order">
		<input type="submit" value="Book">
		</form><?php
	}
}