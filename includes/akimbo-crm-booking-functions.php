<?php

/***************************************************************************************************
 *
 * Add booking dropdown option to all products marked as private bookings in Akimbo CRM
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
		$book_title = (array_key_exists('birthday_name', $values)) ? "Party: ".$values['birthday_name'] : "Party";
		crm_create_booking($item, $values['book_date'], $book_title);
	}	
}

/****************************************************************************************************
 * 
 * Backend scheduling functions
 * 
 **************************************************************************************************/




add_action( 'admin_post_crm_add_booking_availability_process', 'crm_add_booking_availability_process' );


add_action( 'admin_post_crm_update_book_trainer_availability', 'crm_update_book_trainer_availability_process' );
add_action( 'admin_post_crm_update_booking_trainers', 'crm_update_booking_trainers' );
add_action( 'admin_post_crm_update_booking_meta_process', 'crm_update_booking_meta_process' );

 //crm_available_booking_dates_dropdown($product_id): echo dropdown of all available dates



//crm_available_booking_dates_dropdown($product_id = NULL)//select, name = session_date

//crm_update_booking_trainers


function crm_reset_availability($book_date){
	//given a book_date, set availability to 1
	global $wpdb;
	/**
	 * Delete from availability table
	 */
	$table = $wpdb->prefix.'crm_availability';
	$data = array("availability" => 1);
	$session_date = date("Y-m-d H:i:s", strtotime($book_date));
	$where = array("session_date" => $session_date);
	$result = $wpdb->update( $table, $data, $where);
	
	return $result;
}
//add_action( 'admin_post_crm_update_book_date', 'crm_update_book_date' );//I think this is outdated?
function crm_update_book_date($order_id, $product_id = NULL, $book_date = NULL, $url = NULL){//works with get or variables
	//get product_id & order (id) & book_date
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
	if(isset($_GET['book_date']) || $book_date != NULL){	//run extra function to reset availability	
		$book_date = (isset($_GET['book_date'])) ? $_GET['book_date'] : $book_date;
		echo "Current booking date: ".date("g:ia, l jS M", strtotime($book_date))."<br/>";
		echo "<input type='hidden' name='reset' value='".$book_date."'>";
		$submit = "Change Booking Date";
	}else{
		$submit = "Set Booking Date";
	}
	$order_id = (isset($_GET['order'])) ? $_GET['order'] : $order_id;
	$product_id = (isset($_GET['product_id'])) ? $_GET['product_id'] : $product_id;
	$name = get_post_meta($order_id, '_birthday_name', true);
	$value = "Booking: ";
	if(isset($name)){
		$value .= $name."'s party";
	}
	echo "Booking Title: <input type='text' name='book_title' value='". $value."'>";
	crm_available_booking_dates_dropdown($product_id);//book_date
	?>
	<input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
	<input type="hidden" name="action" value="add_new_booking">
	<input type="submit" value="<?php echo $submit; ?>">
	</form><?php
}

add_action( 'admin_post_add_new_booking', 'crm_update_book_date_action' );

function crm_update_book_date_action(){
	//post order_id, book_date & book_title(optional)
	$order = wc_get_order($_POST['order_id']);
	foreach( $order->get_items() as $item ){
		$type = crm_product_meta_type($item->get_product_id());
		if($type == "booking"){
			$book_title = (isset($_POST['book_title'])) ? sanitize_text_field( $_POST['book_title'] ) : NULL;
			$result = crm_create_booking($item, $_POST['book_date'], $book_title, $_POST['order_id']);
			if(isset($_POST['reset'])){
				crm_reset_availability($_POST['reset']);
				//delete previous from class list table
				global $wpdb;
				$table = $wpdb->prefix."crm_class_list";
				$where = array(
					"session_date" => $_POST['reset'],
					"class_id" => $_POST['order_id'],
				);
				$wpdb->delete( $table, $where);
			}
		}
	}
	
	$url = (isset($_POST['url'])) ? $_POST['url'] : crm_admin_order_link($_POST['order_id']);
	$message = ($result) ? "success" : "failure";
	$url = $url."&message=".$message;
	wp_redirect( $url ); 
	exit;
}

function crm_create_booking($item, $book_date, $book_title = "Private Booking", $order_id=NULL){
	global $wpdb;
	$result = false;
	/**
	 * Add meta data to item
	 */
	//Check whether another item has that book date. If so, do not continue
	//$item->add_meta_data('_book_date', $book_date);//not currently working on order page
	//wc_add_order_item_meta( $item->get_id(), '_book_date', $book_date );
	wc_update_order_item_meta( $item->get_id(), '_book_date', $book_date );

	/**
	 * Add booking to class list table
	 */
	//check if it's already been added
	$table = $wpdb->prefix.'crm_class_list';
	$added_class = $wpdb->get_var("SELECT class_id FROM {$wpdb->prefix}crm_class_list WHERE session_date = '$book_date'");
	if(!isset($added_class)){
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
	

	/**
	 * Delete from availability table
	 */
	$table = $wpdb->prefix.'crm_availability';
	$data = array("availability" => 0);
	$session_date = date("Y-m-d H:i:s", strtotime($book_date));
	$where = array("session_date" => $session_date);
	$result = $wpdb->update( $table, $data, $where);
	
	return $result;
}

function crm_delete_booking_button($class_id, $book_date, $item_id = NULL){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
	<input type="hidden" name="book_date" value="<?php echo $book_date; ?>">
	<?php if($item_id != NULL){ ?>
		<input type="hidden" name="item_id" value="<?php echo $item_id; ?>"><?php
	}?>
	<input type="hidden" name="action" value="crm_delete_booking">
	<br/><input type="submit" value="Delete Booking">
	</form><?php
}
add_action( 'admin_post_crm_delete_booking', 'crm_delete_booking_action' );

function crm_delete_booking_action($book_date = NULL, $class_id = NULL, $item_id = NULL, $url = NULL){
	global $wpdb;
	$book_date = (isset($_POST['book_date'])) ? $_POST['book_date'] : $book_date;
	$class_id = (isset($_POST['class_id'])) ? $_POST['class_id'] : $class_id;
	//Delete Availability
	crm_reset_availability($book_date);
	//Delete Item Meta
	if($item_id != NULL){wc_delete_order_item_meta( $item_id, "_book_date", $book_date);}
	//Delete from Class List table
	$table = $wpdb->prefix."crm_class_list";
	$where = array("list_id" => $class_id,);
	$result = $wpdb->delete( $table, $where);
	$message = ($result) ? "success" : "failure";
	$post_url = (isset($_POST['url'])) ? $_POST['url'] : $url;
	$url = ($post_url != NULL) ? $post_url : akimbo_crm_permalinks("classes", "link", NULL, array('message' => $message, 'class' => $class_id));
	wp_redirect(akimbo_crm_class_permalink()); 
	exit;
}

add_action('wp_trash_post', 'crm_delete_booking_restore_availability');
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
			$class_id = $wpdb->get_var("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE class_id = $post_id ");
			crm_delete_booking_action($item_data['book_date'], $class_id, $item_id, $url);
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
	//$end = ($end != NULL) ? $end : current_time('Y-m-t');//ternary operator
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






function crm_display_booking_info($booking_id){
	$booking = new Akimbo_Crm_Booking($booking_id);
	/**
	 * Match Order
	 */
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

	/**
	 * Display booking info
	 */
	$booking_info = $booking->get_booking_info();
	echo "<br/><table width='80%' style='border-collapse: collapse;'><tr bgcolor = '#33ccff'><th><h2>";
	echo $booking_info->class_title." ".date("g:ia, l jS M", strtotime($booking_info->session_date));
	echo "</h2></th></tr><tr><td align='center'>";
	crm_update_trainer_dropdown("class", $booking_id, unserialize($booking_info->trainers));
	echo "</td></tr>";
	if($order){
		echo "<tr><td>";
		$user_id = get_post_meta($order->get_id(), '_customer_user', true);
		echo "<h4>Customer: ";
		echo ($user_id >= 1) ? crm_user_name_from_id($user_id) : "Not set";
		echo "<br/><small>Guest of Honour: ".$booking_info->guest_of_honour."</small></h4>";
		$items = $order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			echo $item_data['name']."<br/>";
		}
		/**
		 * Show add ons
		 */
		echo "</td></tr><tr><th>Add Ons</th></tr><tr><td>"; 
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
		echo "</td></tr>";
	}
	echo "</table>";

	if($order){
		echo "<h4>Edit Booking</h4>";
		crm_update_book_date($order->get_id(), $booking_info->product_id, $booking_info->session_date, akimbo_crm_class_permalink($booking_info->list_id));	
		crm_delete_booking_button($booking_info->list_id, $booking_info->session_date, $booking_info->item_id);
		echo "<i>Does not delete associated order</i>";
	}else{
		crm_delete_booking_button($booking_info->list_id, $booking_info->session_date);	
	}
}

/**
 * Manage availability calendar. Update to remove individual booking details once class function has been updated
 */
function akimbo_crm_manage_booking_schedules(){
	echo "Class type: ";
	echo crm_check_class_type(244);
	

	if(isset($_GET['message'])){
		$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Updates successful!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
		echo apply_filters('manage_booking_details_update_notice', $message);
	}

	if(isset($_GET['order'])){
		echo "Haven't yet finished this function, but will eventually let you add booking dates to orders that don't have one set";
	}
	/**
	 * Show individual booking details
	 */
	if(isset($_GET['booking'])){
		//if(get_post_type($_GET['order']) != "shop_order"){echo "Invalid order number<br/>";}
		$booking = new Akimbo_Crm_Booking($_GET['booking']);
		echo "<h2>Booking details: ".$booking->get_booking_date()."</h2>";
		crm_update_trainer_dropdown("booking", $booking->avail_id, $booking->get_trainers(), $booking->get_availabilities());
		echo apply_filters('akimbo_crm_manage_bookings_detailed_info', $booking->get_booking_info());
		
		echo "<br/><hr><h3>";
		akimbo_crm_permalinks("bookings", "button", "Reset", $args);
		echo "</h3>";

		echo "<br/><hr><h2>Archived Functions: </h2>";
		echo apply_filters('akimbo_crm_manage_bookings_tasks', $booking->tasks());
		echo "<br/><button>Edit slot time</button><hr>";//update date in availability table, so slot doesn't reappear for booking
		//var_dump($booking);		
	}

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

	/**
	 * Booking search and roster edit
	 */
	echo "<hr>";
	crm_roster_edit_button();
	?>
	
	
	<!--<form action="admin.php" method="get">
	<input type="hidden" name="page" value="akimbo-crm2" />
	See all bookings: <select name="booking"><?php
	//get_all_bookings_for_date_range($crm_date['start_time'], "dropdown");//or replace start with $date for future bookings only
	?></select><input type="submit" value="Select"></form> 
	<form action="admin.php" method="get">or search orders: 
	<input type="hidden" name="page" value="akimbo-crm2" />
	<input type="number" name="order"> <input type="submit" value="View"></form> --><?php
	
	

}

function crm_get_posts_by_type($type, $format = "select", $text = NULL){//select name is product_id
	global $wpdb;
	$result = ($format == "select") ? NULL : array();
	//'post_type'=>array('product', 'product_variation')
	$posts = get_posts(array('post_type'=>'product', 'numberposts' => 100,'orderby'=> 'post_title','order' => 'ASC', ));
	foreach($posts as $key=>$post){
		$post_type = crm_product_meta_type($post->ID);
		if($post_type == $type){
			if($format == "select"){
				$result .= "<option value='".$post->ID."'>".$post->post_title."</option>";
			}else{
				$result[] = $post;
			}
		}
	}
	if($format == "select"){
		if($text != NULL){echo $text." ";}
		echo "<select name= 'product_id'>".$result."</select>";
	}else{
		return $result;
	}
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

/**
 * Functions to fix
 */


function crm_update_booking_meta($avail_id, $meta_key = NULL, $meta_value = NULL){//$format = "unserialize"
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
	if($meta_key != NULL ){
		?><input type="hidden" name="meta_key" value="<?php echo $meta_key; ?>" ><?php echo ucfirst($meta_key).": ";
	}else{
		?><select name="meta_key"><option value="order_id">Order ID</option><option value="notes">Notes</option></select> New Value: <?php
	}
	?><input type="text" name="meta_value" <?php if($meta_value != NULL){echo "value='".$meta_value."'";} ?> >
	<input type="hidden" name="avail_id" value="<?php echo $avail_id;?>">
	<input type="hidden" name="action" value="crm_update_booking_meta_process">
	<input type="submit" value="Update"></form><?php
}

function crm_update_booking_meta_process(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_booking_meta';
	$data = array(
	'avail_id' => $_POST['avail_id'],
	'meta_key' => $_POST['meta_key'],
	'meta_value' => $_POST['meta_value'],
	);
	$result = $wpdb->insert($table, $data);
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=booking_details&booking=".$_POST['avail_id'];	
	wp_redirect( $url ); 
	exit;
}

/*function crm_update_book_trainer_availability_process(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_booking_meta';
	$i = $_POST['count'];
	$array = array(1,2,3);
	for($x = 1; $x <= $i; $x++){
		if(isset($_POST[$x])){
			$data = array(
				'avail_id' => $_POST[$x],
				'meta_key' => 'availability',
				//'meta_value' => $_POST['trainer']
				'meta_value' => serialize($array)
			);

		$result = $wpdb->insert($table, $data);
		}
		
	}

	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=calendar" ); 
	exit;
}*/






/*
*
*
*
*
*
*
*
*
********************
Potentially outdated functions
*******************
*
*
*
*
*
*
*
*
*
*
*
 /



/*function crm_update_booking_trainers(){
	global $wpdb;
	$slot = new Akimbo_Crm_Availability($_POST['avail_id']);
	$table = $wpdb->prefix.'crm_booking_meta';
	$data = array('avail_id' => $_POST['avail_id'], 'meta_key' => "senior_trainer", 'meta_value' => $_POST['senior_trainer']);
	if($slot->senior_trainer() != NULL){
		$where = array('avail_id' => $_POST['avail_id'], 'meta_key' => "senior_trainer");
		$wpdb->update( $table, $data, $where);
	}else{
		$wpdb->insert( $table, $data);
	}
	
	$data = array('avail_id' => $_POST['avail_id'], 'meta_key' => "junior_trainer", 'meta_value' => $_POST['junior_trainer']);
	if($slot->junior_trainer() != NULL){
		$where = array('avail_id' => $_POST['avail_id'], 'meta_key' => "junior_trainer");
		$wpdb->update( $table, $data, $where);
	}else{
		$wpdb->insert( $table, $data);
	}

	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=booking_details&order=".$_POST['order_id'] ); 
	exit;
}*/









/**
 * 
 * Archived functions
 * 
 */

/*
// Add new booking: Adds new orders to the bookings table in database and add book_ref to order_itemmeta table
//add_action( 'admin_post_add_new_booking', 'crm_add_new_booking' );//Add new orders to booking table
function crm_add_new_booking() {//values set on manage_bookings.php
	//add book_ref to order_itemmeta
	$item_id = $_POST['item_id'];// <- set on user.php
	$meta_key = "book_ref";
	$meta_value = $_POST['book_ref'];
	wc_add_order_item_meta($item_id, $meta_key, $meta_value);

	//update crm_bookings		
	global $wpdb;
	$table = $wpdb->prefix.'crm_bookings';
	$data = array(
		'book_ref' => $_POST['book_ref'],
		'book_order' => $_POST['order_id'],
		'book_product' => $_POST['product_id'],
		'book_item_id' => $_POST['item_id'],
		'book_customer' => $_POST['customer'],
		);
	$wpdb->insert($table, $data);
	
	// redirect to bookings page
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2";
	wp_redirect( $url ); 
	exit;	
}

//Update booking checklist on Manage Bookings page: Updates trainers and notes, as well as the invitation, week before email, contact trainers and send feedback checkboxes 
//add_action( 'admin_post_crm_booking_checklist', 'crm_update_booking_checklist' );//update checklist and trainers on Manage Bookings page
function crm_update_booking_checklist(){//values set on manage_bookings.php
	global $wpdb;
	$table = $wpdb->prefix.'crm_bookings';
	$where = array('book_ref' => $_POST['book_ref'],);
	//facebook, mailchimp, notes, edit_notes
	if($_POST['trainer1']){$data = array('book_trainer_id' => $_POST['trainer1']);
		$wpdb->update( $table, $data, $where);
	}
	if($_POST['trainer2']){$data = array('book_trainer2_id' => $_POST['trainer2']);
		$wpdb->update( $table, $data, $where);
	}
	$fb=$_POST['fb'];
	if($fb){$data = array('fb' => $_POST['fb']);
		$wpdb->update( $table, $data, $where);
	} else{$data = array('fb' => 0);$wpdb->update( $table, $data, $where);}
	$mc=$_POST['mc'];
	if($mc){$data = array('mc' => $_POST['mc']);
		$wpdb->update( $table, $data, $where);
	} else{$data = array('mc' => 0);$wpdb->update( $table, $data, $where);}
	$iv=$_POST['iv'];
	if($iv){$data = array('iv' => $_POST['iv']);
		$wpdb->update( $table, $data, $where);
	} else{$data = array('iv' => 0);$wpdb->update( $table, $data, $where);}
	$tr=$_POST['tr'];
	if($tr){$data = array('tr' => $_POST['tr']);
		$wpdb->update( $table, $data, $where);
	} else{$data = array('tr' => 0);$wpdb->update( $table, $data, $where);}
	$edit=$_POST['edit_notes'];
	if($edit){$data = array('book_notes' => $_POST['notes']);
		$wpdb->update( $table, $data, $where);
	}
	// redirect to bookings page
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&booking=".$_POST['book_ref'];
	wp_redirect( $url ); 
	exit;	
}

//Archive booking: Remove from list in Manage Bookings and mark as completed in database 
add_action( 'admin_post_crm_archive_booking', 'crm_archive_booking' );//remove booking from Manage Bookings page and mark as completed in database
function crm_archive_booking(){//values set on manage_bookings.php
	global $wpdb;
	$table = $wpdb->prefix.'crm_bookings';
	$where = array('book_ref' => $_POST['book_ref'],);
	$archive=$_POST['archive'];
	if($archive){$data = array('book_completed' => $_POST['archive']);
		$wpdb->update( $table, $data, $where);
	}
	
	// redirect to bookings page
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2";
	wp_redirect( $url ); 
	exit;	
}

function get_all_bookings_for_date_range($start = NULL, $format = "return"){
	global $wpdb;
	$query = "SELECT * FROM {$wpdb->prefix}crm_availability WHERE availability = false ";//
	if($start != NULL){$query .= " AND session_date >= '$start' ";}
	$query .= " ORDER BY session_date ASC";
	$bookings = $wpdb->get_results($query);

	if($format == "return"){
		return $bookings;
	}else{
		foreach($bookings as $booking){
			echo "<option value='".$booking->avail_id."'>".date('g:ia l jS M', strtotime($booking->session_date))."</option>";			
		}
	}
}
//add_action( 'admin_post_crm_add_booking_no_order', 'crm_add_booking_no_order');
function crm_add_booking_no_order(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_availability';
	$data = array('availability' => 0,);
	$where = array('avail_id' => $_POST['id']);
	$result = $wpdb->update($table, $data, $where);
	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=bookings" ); 
	exit;
}

*/