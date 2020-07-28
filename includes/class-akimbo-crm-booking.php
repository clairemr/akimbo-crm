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

    function __construct($booking_id){
		global $wpdb;
        $this->booking_id = $booking_id;
        $this->booking_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = '$booking_id'");
        $this->order_id = $this->booking_info->class_id;
        
        //return order information
		if($this->order_id >= 1){
			$this->order = wc_get_order( $this->order_id );
			$this->items = $this->order->get_items();
			$this->customer = $this->order->get_user();
			
			foreach ( $this->items as $item_id => $item_data ){
				if($item_data['book_date']){
					$this->booking_info['book_date'] = $item_data['book_date'];
					$this->booking_info['product_id'] = $item_data['product_id'];
					$this->booking_info['variation'] = $item_data['name'];
					$this->variation = $item_data['name'];
				}
				$this->guest_of_honour = ($item_data['birthday_name']) ? $item_data['birthday_name'] : NULL;
			}
		}
    }

    function get_booking_info(){
        return $this->booking_info;
    }
	
}