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
class Akimbo_Crm_Booking{

	
	public $avail_id;
	public $order_id;
	private $booking_date;
	public $info;
	public $booking_info;

	private $booking_reference;
	private $order;
	private $items;
	private $customer;
	private $variation;
	
	/*private $product_id;
	
	private $guest_of_honour;
	private $senior_trainer;
	private $junior_trainer;
	private $trainers;
	private $availabilities;*/
	
	//private $details;
	//private $customer_id;
	//$customer = new WP_User($this->$customer_id);


	function __construct($avail_id){
		global $wpdb;
		$this->avail_id = $avail_id;
		//get availability information
		$info = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_availability LEFT JOIN {$wpdb->prefix}crm_booking_meta ON {$wpdb->prefix}crm_availability.avail_id = {$wpdb->prefix}crm_booking_meta.avail_id WHERE {$wpdb->prefix}crm_availability.avail_id = '$avail_id'");
		$this->info = $info;
		foreach($info as $detail){
			$this->booking_date = $detail->session_date;
			$this->booking_info[$detail->meta_key] = $detail->meta_value;//adds all meta data
		}
		//if(!isset($this->booking_info['order_id'])){$this->update_booking_meta("order_id", $this->order_id, "unserialize");}
		if(!isset($this->booking_info['trainers'])){
			$this->update_booking_meta("trainers", array(0,0), $format = "serialize");
			$this->booking_info['trainers'] = serialize(array(0,0));
		}
		if(!isset($this->booking_info['availabilities'])){	
			$this->update_booking_meta("availabilities", array(0,0), $format = "serialize");
			$this->booking_info['availabilities'] = serialize(array(0,0));
		}
		if(!isset($this->booking_info['order_id'])){	
			$this->update_booking_meta("order_id", 0);
			$this->booking_info['order_id'] = 0;
		}

		//get order id
		$this->order_id = $this->booking_info['order_id'];
		
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

			/*foreach($this->items as $item_data){
				//if($item_data['book_date']){
					$this->variation = $item_data['name'];
				//}
			}*/
		}
	}
	
	function update_booking_meta($meta_key, $meta_value, $format = "unserialize"){
		global $wpdb;
		$table = $wpdb->prefix.'crm_booking_meta';
		$meta_value = ($format == "unserialize") ? $meta_value : serialize($meta_value);
		$data = array(
		'avail_id' => $this->avail_id,
		'meta_key' => $meta_key,
		'meta_value' => $meta_value,
		);
		$result = $wpdb->insert($table, $data);
	}

	function get_trainers($format = "unserialize"){
		$trainers = ($format == "serialize") ? $this->booking_info['trainers']: unserialize($this->booking_info['trainers']);
		return $trainers;
	}

	function trainer_names($format = "string"){
		$trainers = unserialize($this->booking_info['trainers']);
		if($trainers != NULL){
			$count = count($trainers);
			$i = 1;
			foreach($trainers as $trainer){
				if($trainer >= 1){
					$user_info = get_userdata($trainer);
					if($format == "string"){
						$result .= $user_info->display_name;
						if($i < $count){$result .= " & ";}
					}else{
						$result[] = $user_info->display_name;
					}
				}
				
	      		$i++;
			}
		}else{
			$result = ($format == "string") ? "<i>Trainers not set</i>" : array(0,0);
		}
		
		return $result;
	}

	function get_availabilities($format = "unserialize"){
		if(isset($this->booking_info['availabilities'])){
			$availabilities = ($format == "serialize") ? $this->booking_info['availabilities'] : unserialize($this->booking_info['availabilities']);
		}else{			
			$this->update_booking_meta("availabilities", array(0,0), $format = "serialize");
		}
		return $availabilities;
	}

	function get_booking_date($format = "g:ia, l jS M", $add = false){
		if(!$this->booking_date){
			if($add == true){
				$booking_date = "</h2><i>No booking date was found for this order. If you're 100% sure it should have a booking date, add one to the correct product below</i>";
				?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
				echo crm_available_booking_dates_dropdown(0);//select name = book_date
				$items = $this->get_items();//var_dump($booking->get_items()['id']);
				?><select name="item_id"><?php
					foreach ($items as $key => $value ) {echo "<option value='".$key."'>".$value['name']."</option"; }
				?></select><?php
				?><input type="hidden" name ="url" value="/wp-admin/admin.php?page=akimbo-crm4&tab=booking_details&order=<?php echo $this->order_id;?>">
				<input type="hidden" name ="avail_id" value="<?php echo $this->avail_id; ?>">
				<input type="hidden" name ="action" value="crm_update_book_date">
				<input type="submit" value="Add Date"></form><?php //url, item_id, avail_id, book_date
			}else{$booking_date = "No date selected";}
			
		}else{
			$booking_date = date($format, strtotime($this->booking_date));
		}
		
		return $booking_date;
	}

	function get_booking_info(){
		if($this->order_id >= 1){
			echo "<h4>Event: ";
			if(isset($this->guest_of_honour)){echo $this->guest_of_honour."'s ";}
			echo $this->variation."</h4>";
			echo "Order <a href='".$this->order_url()."'>".$this->order_id."</a>, booked by ".$this->customer->display_name." (<a href='".user_edit_profile_link($this->customer->ID)."'>".$this->customer->user_email."</a>) <br/><h4>Items:</h4>";
			foreach($this->items as $item_data){
				echo "<i>Product ".$item_data['product_id'].": ".$item_data['name']."</i><br/>";
			}
		}else{
			echo "<i>No associated order</i>";
			crm_update_booking_meta($this->avail_id, "order_id");
		}
		$notes = (isset($this->booking_info['notes'])) ? $this->booking_info['notes'] : NULL;
		crm_update_booking_meta($this->avail_id, "notes", $notes);
		/*if(isset($this->booking_info['notes'])){
			crm_update_booking_meta($this->avail_id, "notes", $this->booking_info['notes']);
		}else{
			crm_update_booking_meta($this->avail_id, "notes");
		}*/
	}

	function product_name(){
		//$name = get_the_title( $this->product_id );
		return $this->variation;
	}
	
	function tasks(){
		echo "<h4>Tasks:</h4>";
		/*?><input type="checkbox" name="mc" value="1" <?php if($mailchimp){echo "checked='checked'";}?>><i class="fal fa-check-square"></i>Send week before email
		<br/><input type="checkbox" name="fb" value="1" <?php if($feedback){echo "checked='checked'";}?> >Send feedback email
		<br/><input type="checkbox" name="edit_notes" value="1">Edit notes: <small>This must be selected to save changes to notes</small>
		<?php*/
		?>Send week before email
		<br/>Send feedback email
		<br/>Notes:
		<?php
	}

	function order_url(){
		$order_url = get_site_url()."/wp-admin/post.php?post=".$this->order_id."&action=edit";
		return $order_url;
	}


	//Functions not currently in use
	/*function get_booking_details(){
		return $this->info;
	}

	function get_items(){
		return $this->items;
	}

	

	function get_booking_customer(){
		return $this->customer;
	}
	function get_booking_order(){
		return $this->order;
	}
	
	
	function set_booking_date($date){
		//needs to record change in db to be useful
		$this->booking_date = $date;
	}
	

	
	function get_upcoming_bookings($start = NULL, $end = NULL){
		if(!$start){$start = current_time('Y-m-01');}
		if(!$end){$end = current_time('Y-m-t');}
		//return $start.$end;
		



		//$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC', 'date_paid' => $start."...".$end,) );
		$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC', 'book_date' => $start."...".$end,) );
		$orders = $query->get_orders();
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			//$order_id = $order->ID; <-- debug log error
			$order_url = $site."/wp-admin/post.php?post=".$order_id."&action=edit";
			$crm_order = wc_get_order($order_id);
			$items = $crm_order->get_items();
			foreach ( $items as $item_id => $item_data ) {
				//create array of categories product is in
				global $post;
				$post_id = $item_data['product_id'];
				$terms = wp_get_post_terms( $post_id, 'product_cat' );
				$customer = get_post_meta($order_id, '_customer_user', true);
				foreach ( $terms as $term ) $cat = $term->slug;
				
				if ($cat=='parties'){
					if($item_data['book_date']){
						$booking = $wpdb->get_var("SELECT book_ref FROM {$wpdb->prefix}crm_bookings WHERE book_item_id = '$item_id'");
						if(!$booking){
							$booking_date = date("g:ia, l jS M", strtotime($item_data['book_date']));
							echo "Order <a href='".$order_url."'>".$order_id."</a>: ".$item_data['name'].": ".$booking_date;//$item_data['birthday_name']." ".$item_data['book_date'];$item_data['product_id'] ;
							$book_ref = date("Y-m-d-ga", strtotime($item_data['book_date']));
							?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
							<input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
							<input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
							<input type="hidden" name="product_id" value="<?php echo $item_data['variation_id']; ?>">
							<input type="hidden" name="book_ref" value="<?php echo $book_ref; ?>"> 
							<input type="hidden" name="customer" value="<?php echo $customer; ?>"> 
							<input type="hidden" name="action" value="add_new_booking">
							<input type="submit" value="Confirm Order">
							</form> 
							<?php
						}else{}
					} else {
						echo "Order <a href='".$order_url."'>".$order_id."</a>: ".$item_data['name']."<br/>";
					}
				}
			}
		}
	}

	*/
}