<?php

/***************************************************************************************************
 *
 * Add booking dropdown option to all products marked as private bookings in Akimbo CRM (is_booking)
 * 
 ***************************************************************************************************/
add_shortcode('bookingDateDropdown', 'crm_available_booking_dates_shortcode');
add_action( 'wp_head', 'crm_hide_booking_quantity' );//hide quantity on booking page
add_action('woocommerce_before_add_to_cart_button','crm_add_custom_booking_fields');
add_filter('woocommerce_add_cart_item_data', 'crm_add_booking_item_data',10,3);
add_filter('woocommerce_get_item_data', 'crm_add_booking_item_meta', 10, 2);
add_action('woocommerce_checkout_create_order_line_item', 'crm_add_booking_order_line_item_meta', 10, 4);

/**
 * Shortcode to display available booking dates. Used on party page
 */
function crm_available_booking_dates_shortcode($atts){//$page = null
	extract(shortcode_atts(array('product' => ''), $atts));
	$content = crm_available_booking_dates_dropdown($product);
	return $content;
}

/**
 * Display booking availability as <select>. field name = "book_date"
 */
function crm_available_booking_dates_dropdown($product_id = 0, $start=NULL){
	$availability = crm_check_booking_availability($product_id,  $start);
	$select = "<br/><select name= 'book_date'>";
	if(isset($availability)){
		foreach($availability as $avail){
			$select.= "<option value=' ".$date = date("Y-m-d H:i", strtotime($avail->session_date))."'>".date("g:ia, l jS M Y", strtotime($avail->session_date))."</option>"; 
		}
	}else{
		$select.= "<option>No available dates</option>";
	}
	$select.= "</select>";
	echo $select;
}

/**
 * Hide quantity button for private bookings
 */
function crm_hide_booking_quantity() {
	if(is_product()){
		global $post;
		$is_booking = get_post_meta($post->ID, 'is_booking', true );	
		if($is_booking){
			?><style type="text/css">.quantity, .buttons_added { width:0; height:0; display: none; visibility: hidden; }</style><?php
		}
	}
}

/**
 * Display custom fields on booking products
 */
function crm_add_custom_booking_fields(){
	global $post;
	$is_booking = get_post_meta($post->ID, 'is_booking', true );	
	if($is_booking){
		ob_start();
		?><div class="CRM-custom-date">
			Your preferred date: <?php echo crm_available_booking_dates_dropdown($post->ID);?>
		</div>
		<div class="CRM-custom_guest">
			<br/>Guest of Honour: <input type="text" name="birthday_name"><br/><br/>
		</div><div class="clear"></div>
		<?php $content = ob_get_contents();
		ob_end_flush();
		return $content;
	}
}	

/**
 * Add custom data to cart 
 */
function crm_add_booking_item_data($cart_item_data, $product_id, $variation_id){
	if(isset($_REQUEST['birthday_name'])){
		$cart_item_data['birthday_name'] = sanitize_text_field($_REQUEST['birthday_name']);
	}
	if(isset($_REQUEST['book_date'])){
		$cart_item_data['book_date'] = sanitize_text_field($_REQUEST['book_date']);
	}
	return $cart_item_data;
}	

/**
 * Display information as meta on cart page
 */
function crm_add_booking_item_meta($item_data, $cart_item){
	if(array_key_exists('birthday_name', $cart_item)){
		$birthday_name = $cart_item['birthday_name'];
		$item_data[0] = array(
			'key' => 'Name',
			'value' => $birthday_name);
	}
	if(array_key_exists('book_date', $cart_item)){
		$book_date = $cart_item['book_date'];
		$cart_book_date = date("g:ia, l jS M", strtotime($book_date));
		$item_data[1] = array(
			'key' => 'Booking date',
			'value' => $cart_book_date);
	}
	return $item_data;
}

/**
 * Save item meta to database, add booking to class list table and update slot in availability table
 */
function crm_add_booking_order_line_item_meta($item, $cart_item_key, $values, $order){
	if(array_key_exists('birthday_name', $values)){
		$item->add_meta_data('_birthday_name', $values['birthday_name']);
		$birthday_name = $values['birthday_name'];
	}
	if(array_key_exists('book_date', $values)){
		crm_create_booking($item, $values['book_date']);
	}	
}

/****************************************************************************************************
 * 
 * Create & Display Booking Info
 * 
 **************************************************************************************************/
function crm_create_booking($item, $book_date, $order_id=NULL){
	global $wpdb;
	$result = false;
	wc_update_order_item_meta( $item->get_id(), '_book_date', $book_date );//Add meta data to item
	$result = crm_reset_availability($book_date, 0);//update crm_availability
	//Add booking to class list table
	$table = $wpdb->prefix.'crm_class_list';
	$added_class = $wpdb->get_var("SELECT class_id FROM {$wpdb->prefix}crm_class_list WHERE session_date = '$book_date'");
	if(!isset($added_class)){
		$book_title = get_option('akimbo_crm_booking_title');
		$duration = get_post_meta($item->get_product_id(), 'duration', true );
		$duration = ($duration <= 1) ? get_post_meta($item->get_variation_id(), 'duration', true ) : 1;//Duration may be set on post or variation. Check for both and use default value of 1 so it's added to db either way
		$prod_id = serialize(array($item->get_product_id(),$item->get_variation_id()));//$item->get_variation_id()
		$data = array(
			'age_slug' => "private",
			'location' => "Circus Akimbo - Hornsby",
			'session_date' => $book_date,
			'duration' => $duration,
			'prod_id' => $prod_id,
			'class_title' => $book_title,
		);
		$data['class_id'] = ($order_id != NULL) ? $order_id : 0;
		$result = $wpdb->insert($table, $data);	
	}elseif($added_class <= 1 && $order_id != NULL){
		$data = array('class_id' => $order_id);
		$where = array('session_date' => $book_date);
		$result = $wpdb->update($table, $data, $where);
	}
	return $result;
}

function crm_display_booking_info($booking_id){
	$booking = new Akimbo_Crm_Booking($booking_id);
	//Match Order
	$matched_orders = $booking->match_order();
	$order = $booking->get_booking_order();
	if($matched_orders == false){
		echo "No matched order! ";
	}else{
		if(count($matched_orders) >= 2){
			echo "<h2>Error, ".count($booking->match_order())." matched orders!</h2>";
			foreach($matched_orders as $matched_order){
				echo crm_admin_order_link($matched_order->get_id(), "Order ID: ".$matched_order->get_id())."<br/>";
			}
		}else{
			echo "Order ID: ";
			echo crm_admin_order_link($order->get_id(), $order->get_id());
		}
	}
	//Display booking info
	$booking_info = $booking->get_booking_info();
	echo "<br/><table><tr><th>".$booking_info->class_title." ".date("g:ia, l jS M", strtotime($booking_info->session_date));
	echo "</th></tr><tr><td align='center'>";
	crm_update_trainer_dropdown("class", $booking_id, unserialize($booking_info->trainers));
	if($order){
		echo "</td></tr><tr><td>";
		$user_id = get_post_meta($order->get_id(), '_customer_user', true);
		echo "<h4>Customer: ";
		echo ($user_id >= 1) ? crm_user_name_from_id($user_id) : "Not set";
		echo "<br/><small>Guest of Honour: ".$booking_info->guest_of_honour."</small></h4>";
		$items = $order->get_items();//show all order items
		foreach ( $items as $item_id => $item_data ) {echo $item_data['name']."<br/>";}
		echo "</td></tr><tr><th>Add Ons</th></tr><tr><td>"; //Show add ons
		echo "<i>To associate additional products with this order, simply add meta '_parent_order' with the value ".$order->get_id()." to any new orders</i>";
		$add_ons = crm_return_orders_by_meta("_parent_order", $order->get_id());
		if($add_ons){
			echo "</td></tr><tr><td>"; 
			foreach($add_ons as $added_order){
				echo crm_admin_order_link($added_order->get_id(), "Order ".$added_order->get_id()).":<br/>";
				$items = $added_order->get_items();
				foreach ( $items as $item_id => $item_data ) {
					echo $item_data['name']."<br/>";
				}
			}
			echo "</td></tr>";
		}
	}
	echo "</td></tr></table>";
	if($order){
		echo "<h4>Edit Booking</h4>";
		crm_update_book_date($order->get_id(), $booking_info->product_id, $booking_info->session_date, akimbo_crm_class_permalink($booking_info->list_id));	
		crm_delete_booking_button($booking_info->class_id, $booking_info->session_date, $booking_info->item_id);
		echo "<i>Does not delete associated order</i>";
	}else{
		crm_delete_booking_button($booking_info->class_id, $booking_info->session_date);	
	}
}

/****************************************************************************************************
 * 
 * Backend scheduling functions
 * 
 **************************************************************************************************/
/*
//Delete functions//
crm_delete_booking_button($class_id, $book_date, $item_id = NULL)
crm_delete_booking_action//linked to button, redirects to class page
crm_delete_booking_process//used in crm_delete_booking_action & delete button
crm_delete_booking_restore_availability//runs delete process on trashed booking orders
crm_reset_availability($book_date)//given a book_date, set availability to 1 or 0

//Uses Delete & reset availability functions
crm_update_book_date($order_id, $product_id = NULL, $book_date = NULL, $url = NULL)//works with get or variables
crm_update_book_date_action()//requires order_id and book_date
*/
add_action( 'admin_post_crm_delete_booking', 'crm_delete_booking_action' );
add_action('wp_trash_post', 'crm_delete_booking_restore_availability');
add_action( 'admin_post_add_new_booking', 'crm_update_book_date_action' );









add_action( 'admin_post_crm_add_booking_availability_process', 'crm_add_booking_availability_process' );
add_action( 'admin_post_crm_update_book_trainer_availability', 'crm_update_book_trainer_availability_process' );
add_action( 'admin_post_crm_update_booking_trainers', 'crm_update_booking_trainers' );
add_action( 'admin_post_crm_update_booking_meta_process', 'crm_update_booking_meta_process' );

/**
 * Set or update book date for a given order. Works with $_GET or variables
 */
function crm_update_book_date($order_id, $product_id = NULL, $book_date = NULL, $url = NULL){
	global $wpdb;
	$order_id = (isset($_GET['order'])) ? $_GET['order'] : $order_id;
	$product_id = (isset($_GET['product_id'])) ? $_GET['product_id'] : $product_id;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
	$submit = "Set Booking Date";
	if(isset($_GET['book_date']) || $book_date != NULL){	//run extra function to reset availability	
		$book_date = (isset($_GET['book_date'])) ? $_GET['book_date'] : $book_date;
		echo "Current booking date: ".date("g:ia, l jS M", strtotime($book_date))."<br/>";
		echo "<input type='hidden' name='reset' value='".$book_date."'>";
		$submit = "Change Booking Date";
	}
	crm_available_booking_dates_dropdown($product_id);//book_date
	?><input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
	<input type="hidden" name="action" value="add_new_booking">
	<input type="submit" value="<?php echo $submit; ?>">
	</form><?php
}

/**
 * Action for crm_update_book_date(). Requires order_id and book_date
 */
function crm_update_book_date_action(){
	$order = wc_get_order($_POST['order_id']);
	foreach( $order->get_items() as $item ){
		$type = crm_product_meta_type($item->get_product_id());
		if($type == "booking"){//update correct order item
			//Delete old booking before creating new booking
			if(isset($_POST['reset'])){crm_delete_booking_process($_POST['reset'], $_POST['order_id'], $item->get_id, "no");}
			$result = crm_create_booking($item, $_POST['book_date'], $_POST['order_id']);
		}
	}
	$url = (isset($_POST['url'])) ? $_POST['url'] : crm_admin_order_link($_POST['order_id']);
	$message = ($result) ? "success" : "failure";
	$url = $url."&message=".$message;
	wp_redirect( $url ); 
	exit;
}

/**
 * Button and admin post action for crm_delete_booking_process
 */
function crm_delete_booking_button($order_id, $book_date, $item_id = NULL){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
	<input type="hidden" name="book_date" value="<?php echo $book_date; ?>">
	<input type="hidden" name="url" value="<?php echo akimbo_crm_class_permalink(); ?>">
	<?php if($item_id != NULL){ ?>
		<input type="hidden" name="item_id" value="<?php echo $item_id; ?>"><?php
	}?>
	<input type="hidden" name="action" value="crm_delete_booking">
	<br/><input type="submit" value="Delete Booking">
	</form><?php
}

/**
 * Admin post & redirect for crm_delete_booking_process
 */
function crm_delete_booking_action($book_date = NULL, $order_id = 0, $item_id = NULL, $redirect = "no"){
	$book_date = (isset($_POST['book_date'])) ? $_POST['book_date'] : $book_date;
	$order_id = (isset($_POST['order_id'])) ? $_POST['order_id'] : $order_id;
	$item_id = (isset($_POST['item_id'])) ? $_POST['item_id'] : $item_id;
	$post_url = (isset($_POST['url'])) ? $_POST['url'] : $redirect;
	$result = crm_delete_booking_process($book_date, $order_id, $item_id, $post_url);
	$message = ($result) ? "success" : "failure";
	$url = ($post_url != NULL) ? $post_url : akimbo_crm_permalinks("classes", "link", NULL, array('message' => $message, 'class' => $class_id));
	wp_redirect($url); 
	exit;
}

/**
 * Delete booking process without redirect
 */
function crm_delete_booking_process($book_date = NULL, $order_id = 0, $item_id = NULL, $redirect = "no"){
	global $wpdb;
	//Reset availability and delete item meta
	crm_reset_availability($book_date);
	$result = ($item_id != NULL) ? wc_delete_order_item_meta( $item_id, "_book_date") : false;
	//Delete from Class List table
	$table = $wpdb->prefix."crm_class_list";
	$where = array("class_id" => $order_id,);//"session_date" => $book_date,
	$result = $wpdb->delete( $table, $where);
	return $result;
}

/**
 * Reset availability: 1 for available, 0 for unavailable
 */
function crm_reset_availability($book_date, $available = 1){
	global $wpdb;
	$table = $wpdb->prefix.'crm_availability';
	$session_date = date("Y-m-d H:i:s", strtotime($book_date));
	$result = $wpdb->update( $table, array("availability" => $available), array("session_date" => $session_date));
	return $result;
}

/**
 * Delete booking if order moved to trash
 */
function crm_delete_booking_restore_availability($post_id){
	global $wpdb;
	$order = wc_get_order($post_id);
	$items = $order->get_items();
	foreach ( $items as $item_id => $item_data ) {
		if($item_data['book_date']){
			$url = get_site_url()."/wp-admin/edit.php?post_type=shop_order";
			$order_id = $wpdb->get_var("SELECT class_id FROM {$wpdb->prefix}crm_class_list WHERE class_id = $post_id ");
			crm_delete_booking_action($item_data['book_date'], $order_id, $item_id, $url);
		}
	}
}

/**
 * Check availability from availability table
 */
function crm_check_booking_availability($product_id = NULL, $start = NULL){//$end = NULL
	global $wpdb;
	$start = ($start == NULL ) ? current_time('Y-m-d H:ia') : $start;
	$availability = array();
	if($product_id >= 1){
		$availabilities = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_availability WHERE availability = '1' AND session_date >= '$start' ORDER BY session_date");
		foreach($availabilities as $slot){
			$products = unserialize($slot->prod_id);
			if(in_array($product_id, $products)){$availability[] = $slot;}
		}
	}else{
		$availability = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_availability WHERE availability >= '1' AND session_date >= '$start' ORDER BY session_date");
	}
	
    return $availability;
}


/**
 * Manage availability calendar. Update to remove individual booking details once class function has been updated
 */
function akimbo_crm_manage_booking_schedules(){
	/**
	 * Availability Calendar
	 */
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_month($date);
	$availability = crm_check_booking_availability(0, $crm_date['start_time'], $crm_date['end']);
	
	if (current_user_can('manage_woocommerce')){//only admin can view & edit availabilities
		echo "<table border='1' width='95%'><tr><td colspan='7' align='center'>";
		echo apply_filters('akimbo_crm_manage_availabilities_header', crm_date_selector_header("bookings", $date));
		apply_filters('akimbo_crm_manage_availabilities_before_calendar', crm_date_selector("akimbo-crm2", "calendar"));
		echo "</td></tr><tr>";
		$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
		for($x = 1; $x <= 7; $x++){
			echo "<th width = '13%'>".$days[$x-1]."</th>";
			if($days[$x-1] == $crm_date['first_day']){$start = $x;}
		}
		echo "</tr>";
		$rows = 5;
		$day_of_the_month = 1;
		for($x = 1; $x <= $rows; $x++){
			echo "<tr>";
			for($i = 1; $i <= 7; $i++){
				echo "<td>";
				if($i == $start){$count_started = 1;}
				if(isset($count_started) && $day_of_the_month <= $crm_date['number_of_days']){
					echo $day_of_the_month."<br/>";
					$select_value = date("Y-m-$day_of_the_month", strtotime($date));
					if(isset($availability)){
						foreach($availability as $avail_id){
							$avail = new Akimbo_Crm_Availability($avail_id->avail_id);
							$avail_date = $avail->get_booking_date("Y-m-j");
							if($avail_date == $select_value){
								echo "Available: ".$avail->get_booking_date("g:ia");
								crm_simple_delete_button('crm_availability', "avail_id", "$avail_id->avail_id", "/wp-admin/admin.php?page=akimbo-crm2&tab=calendar");
								$avail->book_button();
								echo "<hr>";
							}
						}
					}
					$day_of_the_month++;
				}
				echo "</td>";
			}
			echo "</tr>";
			if($x == 5 && $day_of_the_month <= $crm_date['number_of_days']){$rows = 6;}
		}
		echo "</table>";
		crm_add_booking_availability();
		echo "<br/>Availabilities: ";
		echo crm_available_booking_dates_dropdown();
	}
	echo "<hr>";
	crm_roster_edit_button();
}

function crm_add_booking_availability(){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<?php crm_get_posts_by_type("booking", "select", "Select product:"); ?>
	Start: <input type="time" name="start_time"><input type="date" name="start_date"> End: <input type="date" name="end_date">
	<input type="hidden" name="action" value="crm_add_booking_availability_process">
	<br/><input type="submit" value="Add Slot"></form><?php
}

function crm_add_booking_availability_process(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_availability';
	$time = $_POST['start_time'];
	$new_date = $_POST['start_date']." ".date("H:i", strtotime($time));
	$end_date = $_POST['end_date'];
	while($new_date <= $end_date){
		$data = array(
		'prod_id' => serialize(array($_POST['product_id'])),
		'session_date' => $new_date,
		'duration' => 150,
		'availability' => 1,
		);

		$result = $wpdb->insert($table, $data);
		$new_date = date("Y-m-d H:i", strtotime($new_date) + 604800);//add number of seconds in 7 days, g:ia time format 6:00pm
	}
	wp_redirect(akimbo_crm_permalinks("bookings")); 
	exit;
}