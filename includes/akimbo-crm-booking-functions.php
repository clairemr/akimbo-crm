<?php
//if admin post isn't working, double check I've added the admin_post action....
/**
 *
 * Akimbo CRM booking functions
 * 
 */

//crm_available_booking_dates_dropdown($product_id = NULL)//select, name = session_date


add_action('woocommerce_before_add_to_cart_button','CRM_add_custom_fields');
add_filter('woocommerce_add_cart_item_data', 'CRM_add_item_data',10,3);
add_filter('woocommerce_get_item_data', 'CRM_add_item_meta', 10, 2);
add_action('woocommerce_checkout_create_order_line_item', 'CRM_add_custom_order_line_item_meta', 10, 4);
add_action( 'wp_head', 'crm_hide_booking_quantity' );//hide quantity on booking page
add_action( 'admin_post_add_new_booking', 'crm_add_new_booking' );//Add new orders to booking table
add_action( 'admin_post_crm_booking_checklist', 'crm_update_booking_checklist' );//update checklist and trainers on Manage Bookings page
add_action( 'admin_post_crm_archive_booking', 'crm_archive_booking' );//remove booking from Manage Bookings page and mark as completed in database
add_action( 'admin_post_crm_add_booking_no_order', 'crm_add_booking_no_order');
add_action( 'admin_post_crm_add_booking_availability_process', 'crm_add_booking_availability_process' );
add_shortcode('bookingDateDropdown', 'crm_available_booking_dates_shortcode'); //[bookingDateDropdown type='adults']

add_action( 'admin_post_crm_update_book_trainer_availability', 'crm_update_book_trainer_availability_process' );
add_action( 'admin_post_crm_update_booking_trainers', 'crm_update_booking_trainers' );
add_action( 'admin_post_crm_update_booking_meta_process', 'crm_update_booking_meta_process' );



//crm_update_booking_trainers
/**
 *
 * Akimbo CRM custom booking functions
 * 
 */

/*************
Reference list
**************
crm_available_booking_dates_dropdown($product_id): echo dropdown of all available dates

*/
add_action( 'admin_post_crm_update_book_date', 'crm_update_book_date' );

function crm_update_book_date(){
	//$avail_id, $order_id = NULL
	global $wpdb;
	$table = $wpdb->prefix.'woocommerce_order_itemmeta';
	$where = array(
		'meta_key' => "book_date",
		);
	$data = array(
		'order_item_id' => $_POST['item_id'],
		'meta_key' => "book_date",
		'meta_value' => $_POST['book_date'],
		);
	$result = $wpdb->update($table, $data, $where);
	$date = $_POST['book_date'];
	$avail_id = $wpdb->get_var("SELECT avail_id FROM {$wpdb->prefix}crm_availability WHERE session_date = '$date'");
	if($result){
		$table = $wpdb->prefix.'crm_availability';
		$where = array('avail_id' => $avail_id);
		$data = array('availability' => 0);
		$result2 = $wpdb->update( $table, $data, $where);
	}

	$message = ($result) ? "success" : "failure";
	$url = get_site_url().$_POST['url']."&message=".$message;
	wp_redirect( $url ); 
	exit;
}

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
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=booking_details&booking=".$_POST['avail_id'];	
	wp_redirect( $url ); 
	exit;
}


function get_all_bookings_for_date_range($start = NULL, $format = "return"){
	global $wpdb;
	/*$query = "
        SELECT *
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        
        AND order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = 'book_date'
    ";
	if($start != NULL){$query .= "AND order_item_meta.meta_value >= '$start'";}
	if($end != NULL){$query .= "AND order_item_meta.meta_value <= '$end'";}
	$bookings = $wpdb->get_results($query);*/
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

/**
 *
 * Add custom fields to booking product page
 * https://wisdmlabs.com/blog/add-custom-data-woocommerce-order-2/ 
 * 
 */
function CRM_add_custom_fields(){
	global $post;
	$products = array(152,192,1470,2968);//152,192 test site. Eventually pull dynamically
	$post_id = $post->ID;
	if (in_array($post->ID, $products)){//Only show on bookable products
		global $product;
		ob_start();
		?><div class="CRM-custom-fields">
			Your preferred date: 
			<?php //date dropdown
			$select = crm_available_booking_dates_dropdown($post->ID);
			echo $select;//end select options
			?>
			<br/><br/>Guest of Honour: <input type="text" name="birthday_name">
			<!-- <br/><input type="checkbox" name="custom_invite"> Please send me a free customised invitation! -->
		</div><div class="clear"></div>
		<?php $content = ob_get_contents();
		ob_end_flush();
		return $content;
		echo $content;
	}
}	

/**
 *
 * Add custom data to cart
 * 
 */
function CRM_add_item_data($cart_item_data, $product_id, $variation_id){
	if(isset($_REQUEST['birthday_name'])){
		$cart_item_data['birthday_name'] = sanitize_text_field($_REQUEST['birthday_name']);
	}
	if(isset($_REQUEST['book_date'])){
		$cart_item_data['book_date'] = sanitize_text_field($_REQUEST['book_date']);
		//$_REQUEST['book_date'];//(see if removing the sanitize_text_field keeps time accurate). Undone 24/6, seemed to create problems
	}
	/*if(isset($_REQUEST['custom_invite'])){
		$cart_item_data['custom_invite'] = sanitize_text_field($_REQUEST['custom_invite']);
	}*/
	return $cart_item_data;
}	

/**
 *
 * Display information as Meta on Cart page
 * 
 */
function CRM_add_item_meta($item_data, $cart_item){
	if(array_key_exists('birthday_name', $cart_item)){
		$birthday_name = $cart_item['birthday_name'];
		$item_data[0] = array(
			'key' => 'Name',
			'value' => $birthday_name);
	}
	if(array_key_exists('book_date', $cart_item)){
		$book_date = $cart_item['book_date'];
		$cart_book_date = date("g:i, l jS M", strtotime($book_date));
		$item_data[1] = array(
			'key' => 'Booking date',
			'value' => $cart_book_date);//eventually update cart display date e.g. $cart_book_date = date("g:ia, l jS M Y", strtotime($book_date));//date("g:ia, l jS M Y", strtotime($book_date))
	}
	/*if(array_key_exists('custom_invite', $cart_item)){
		$custom_invite = $cart_item['custom_invite'];
		$item_data[2] = array(
			'key' => 'Custom invite?',
			'value' => 'Yes please!');
	}*/
	return $item_data;
}

/**
 *
 * Save item meta to database
 * 
 */
function CRM_add_custom_order_line_item_meta($item, $cart_item_key, $values, $order){
	if(array_key_exists('birthday_name', $values)){
		$item->add_meta_data('_birthday_name', $values['birthday_name']);
	}
	if(array_key_exists('book_date', $values)){
		$item->add_meta_data('_book_date', $values['book_date']);
		global $wpdb;
		//$book_date = $values['book_date'];
		//$data_id = $wpdb->get_var("SELECT avail_id FROM {$wpdb->prefix}crm_availability WHERE book_date = '$book_date'");
		
		$table = $wpdb->prefix.'crm_availability';//delete available slot from calendar table
		$data = $availability = 0;
		$where = array( 
			'session_date' => $values['book_date'],
		);
		$wpdb->update( $table, $data, $where);
	}
	/*if(array_key_exists('custom_invite', $values)){
		$item->add_meta_data('_custom_invite', $values['custom_invite']);
	}*/
	
}

/**
 *
 * Hide quantity button for parties
 * https://www.cloudways.com/blog/hide-product-quantity-field-from-woocommerce-product-pages/
 * 
 */
function crm_hide_booking_quantity() {
	if(is_product()){
		global $post;
		if ($post->ID == '1470' || $post->ID == '2968'){
			?><style type="text/css">.quantity, .buttons_added { width:0; height:0; display: none; visibility: hidden; }</style><?php
		}
	}
}

function crm_available_booking_dates_shortcode($atts){//$page = null
	extract(shortcode_atts(array('type' => ''), $atts));
	$content = crm_available_booking_dates_dropdown($type);
	return $content;
}

function crm_available_booking_dates_dropdown($product_id = 0, $start=NULL){
	$availability = crm_check_booking_availability($product_id,  $start);
	$select = "<br/><select name= 'session'>";
	if(isset($availability)){
		foreach($availability as $avail){
			$select.= "<option value=' ".$date = date("Y-m-d H:i", strtotime($avail->session_date))."'>".date("g:ia, l jS M Y", strtotime($avail->session_date))."</option>"; 
		}
	}else{
		$select.= "<option>No available dates</option>";
	}
	$select.= "</select>";
	echo $select;//end select options
}



//function crm_check_booking_availability($product_id = NULL, $start = NULL, $end = NULL){
function crm_check_booking_availability($product_id = NULL, $start = NULL){
	global $wpdb;
	if($start == NULL){
		$date = current_time('Y-m-d H:ia');
		$crm_date = crm_date_setter_month($date);
		$start = $crm_date['start'];
	}
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

function crm_add_booking_no_order(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_availability';
	$data = array('availability' => 0,);
	$where = array('avail_id' => $_POST['id']);
	$result = $wpdb->update($table, $data, $where);
	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=bookings" ); 
	exit;
}

function crm_add_booking_availability(){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<br/>Select product: <select name= 'product_id'><?php
		$posts = get_posts(array('post_type'=>'product', 'numberposts' => 100,'orderby'=> 'post_title','order' => 'ASC', ));
        
		foreach($posts as $key=>$post){
			//$post_id = $post->ID;
			//$type = crm_casual_or_enrolment($post->ID);
	        //if($type == 'booking'){
	        	echo "<option value='".$post->ID."'>".$post->post_title."</option>";
	        //}
		}?> </select> 
	Start: <input type="time" name="start_time"><input type="date" name="start_date"> End: <input type="date" name="end_date">
	<input type="hidden" name="action" value="crm_add_booking_availability_process">
	<br/><input type="submit" value="Add Slot"> <!--<input type="submit" value="Save and add new">-->
	</form><?php
}

function crm_add_booking_availability_process(){//not yet working
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
	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=calendar" ); 
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

	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=calendar" ); 
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

	wp_redirect( get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=booking_details&order=".$_POST['order_id'] ); 
	exit;
}*/

/* Archive booking
 * Remove from list in Manage Bookings and mark as completed in database
 * 
 */
function crm_archive_booking(){//values set on manage_bookings.php
	global $wpdb;
	$table = $wpdb->prefix.'crm_bookings';
	$where = array('book_ref' => $_POST['book_ref'],);
	$archive=$_POST['archive'];
	if($archive){$data = array('book_completed' => $_POST['archive']);
		$wpdb->update( $table, $data, $where);
	}
	
	// redirect to bookings page
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm4";
	wp_redirect( $url ); 
	exit;	
}

/**
 *
 * Add new booking
 * Adds new orders to the bookings table in database and add book_ref to order_itemmeta table
 * 
 */
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
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm4";
	wp_redirect( $url ); 
	exit;	
}

/**
 *
 * Update booking checklist on Manage Bookings page
 * Updates trainers and notes, as well as the invitation, week before email, contact trainers and send feedback checkboxes 
 * 
 */
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
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&booking=".$_POST['book_ref'];
	wp_redirect( $url ); 
	exit;	
}