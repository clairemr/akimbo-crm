<?php
/**
 * The booking functionality of the plugin.
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.2
 *
 * @package    Akimbo_Crm
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */

class Akimbo_Crm_Booking{
	public $booking_id;
	private $order_id;
	private $booking_info;

    function __construct($booking_id){
		global $wpdb;
        $this->booking_id = $booking_id;
        $this->booking_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = '$booking_id'");
		$this->class_id = $this->booking_info->class_id;
		$order_id = $this->booking_info->class_id;
        if($order_id <= 1){
			$order = $this->match_order();
			if(is_array($order)){//multiple orders returned
				$order = reset($order);
			}
		}else{
			$order = wc_get_order( $order_id );
		}
		
		if($order){
			$this->order = $order;
			$this->order_id = $order->get_id();
			$this->items = $order->get_items();
			$this->customer = $order->get_user();
			if($this->class_id <=1){//update order_id
				$table = $wpdb->prefix.'crm_class_list';
				$data = array("class_id" => $this->order_id);
				$where = array("list_id" => $this->booking_info->list_id);
				$result = $wpdb->update( $table, $data, $where);
				if($result){$this->class_id = $this->order_id;}
			}
			foreach ( $this->items as $item_id => $item_data ){
				if($item_data['book_date']){
					$this->booking_info->book_date = $item_data['book_date'];
					$this->booking_info->product_id = $item_data['product_id'];
					$this->booking_info->item_id = $item_id;
					$this->booking_info->variation = $item_data['name'];
				}
				$this->booking_info->guest_of_honour = ($item_data['birthday_name']) ? $item_data['birthday_name'] : NULL;
			}
		}
    }

    function get_booking_info(){
        return $this->booking_info;
	}
	
	function get_booking_order(){
		$order = false;
		if(isset($this->order)){
			$book_date = date('Y-m-d H:i', strtotime($this->booking_info->book_date));
			$session_date = date('Y-m-d H:i', strtotime($this->booking_info->session_date));
			if($book_date == $session_date){//in case of missed duplicate orders
				$order = $this->order;
			}
		}
		return $order;
	}

	function match_order(){
		$session_date = date('Y-m-d H:i', strtotime($this->booking_info->session_date));
		$matched_orders = crm_return_orders_by_meta('_book_date', $session_date, true);		
		return $matched_orders;
	}

	function get_user(){
		$order = $this->order;
		$order->get_user();
	}
}