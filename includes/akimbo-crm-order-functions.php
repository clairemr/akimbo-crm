<?php  //Akimbo CRM order functions and shortcuts

/***********************************************************************************************
 * 
 * Display Functions
 * 
 ***********************************************************************************************/
//Add order message, updated from Akimbo CRM settings page
function order_received_text() {
	get_option('akimbo_crm_order_message');
}

add_filter('woocommerce_thankyou_order_received_text', 'order_received_text');

//Add user students link to admin order page
function crm_admin_order_display_student_list ($order){
	echo akimbo_crm_permalinks("students", "link", "View User Students", array("user" => $order->get_user_id(), ));
}

add_action('woocommerce_admin_order_data_after_shipping_address', 'crm_admin_order_display_student_list', 10, 1);//add user students link

/**
 * Adds 'Item Name' column header to Orders table immediately before 'Total' column.
 */
function akimbo_crm_add_order_items_column_header( $columns ) {
    $new_columns = array();
    foreach ( $columns as $column_name => $column_info ) {
        $new_columns[ $column_name ] = $column_info;
        if ( 'order_status' === $column_name ) {
            $new_columns['order_items'] = __( 'Items', 'my-textdomain' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'akimbo_crm_add_order_items_column_header', 20 );

/**
 * Adds 'Items' column content to Orders table immediately before 'Total' column.
 */
function akimbo_crm_add_order_items_column_content( $column ) {
    global $post;
    if ( 'order_items' === $column ) {			
		$crm_order = wc_get_order($post->ID);
		$items = $crm_order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			echo $item_data['name']." ";
		}
    }
}
add_action( 'manage_shop_order_posts_custom_column', 'akimbo_crm_add_order_items_column_content' );

/*
 * Adds Weeks/Sessions header to admin order page
 */
function cak_crm_admin_order_items_headers($order){
  ?><th class="line_sessions sortable" data-sort="3">Passes</th><?php
}
add_action( 'woocommerce_admin_order_item_headers', 'cak_crm_admin_order_items_headers' );

/**
 * Adds Weeks/Sessions info to admin order page
 */
function cak_crm_admin_order_item_values( $product, $item, $item_id ) {
	global $wpdb;
	$values = NULL;
  	$order = crm_order_info_from_item_id($item_id, "id");
	if (!$order) {//refund, avoid error message
	}else{
		$product_id = $item['product_id'];
  		$is_bookable = get_post_meta($product_id, "is_bookable", true);//use instead of wc_get_order_item_meta
		if(!$is_bookable){
			$values .= "Not bookable";
		}else{
			$class_type = crm_product_meta_type($product_id);
			switch ($class_type) {
			    case "casual":
			        $sessions = wc_get_order_item_meta($item_id, "pa_sessions");
					if($sessions <= 0){
						wc_update_order_item_meta($item_id, "pa_sessions", 1);//sessions default to 1 unless set otherwise
						$text = "Set";
					} else {
						$values .= $sessions." ";
						$text = "Reset";
					}
					$values .= akimbo_crm_permalinks("troubleshooting", "link", $text, array("order" => $item['order_id'], "item_id" => $item_id,));
			        break;
			    case "enrolment":
			        $class_id = ($item['variation_id'] >= 1) ? $item['variation_id'] : $product->get_id();
			        $weeks = wc_get_order_item_meta($item_id, "weeks");
					$semester = ucwords(wc_get_order_item_meta($item_id, "semester"));
					if($semester == NULL){$semester = ucwords(wc_get_order_item_meta($item_id, "pa_semester"));}//some use pa_semester
			        if($semester == NULL){// || !isset($weeks)
					if($weeks == NULL){
						$values .= "1. ";
						wc_update_order_item_meta($item_id, "weeks", 1);
					}else{
						$values .= $weeks.". ";
					}
					$text = "Reset";
			        }else{
			        	$classes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$class_id' AND semester_slug = '$semester'");
			        	$values .= $weeks."/".$classes.". ";
			        	if($weeks <= 0 && $classes >= 1 && $item->get_total() >= 1){
							wc_update_order_item_meta($item_id, "weeks", $classes);//use update, not add, so it won't enter additional rows
							$text = "Update";
						}else{
							$text = "Reset";
						}
				    	$values .= akimbo_crm_permalinks("troubleshooting", "link", $text, array("order" => $item['order_id'], "item_id" => $item_id,));

						if($weeks >= 1 && $classes >= 1 && $weeks != $classes){
							$price = $product->get_price();
							if($price != NULL){
								$new_price = ($price/$classes)*$weeks;
								$GST = $new_price/11;
								$subtotal = ($GST)*10;
								$values .= "<br/>Cost/GST/Total: <br/>$".round($subtotal, 2)."/$".round($GST, 2)."/$".$new_price;
							}
						}
			        }		
			        break;
					case "booking":
						$args = array(
							"order" => $item['order_id'],
							"product_id" => $item->get_product_id(),
						);
						$book_date = wc_get_order_item_meta($item_id, "_book_date");
						if($book_date){
							$values .= date("g:ia D jS M", strtotime($book_date)).". ";
							$text= "Reset";
							$args["book_date"] = date("Y-m-d-H:i", strtotime($book_date));
						}else{
							$text = "Set Date";
						}	
				    	$values .= akimbo_crm_permalinks("troubleshooting", "link", $text, $args);
			        break;
			    default:
			}
		}
	}
	
	echo "<td class='sessions'>".$values."</td>";
}

add_action( 'woocommerce_admin_order_item_values', 'cak_crm_admin_order_item_values', 10, 3);

/*
* Simple function to update weeks, weeks_used, sessions or sessions_used
* Used at top of troubleshooting page, linked from the Set/Reset link in the order page
*/
function crm_update_weeks_or_sessions($item_id){
	$display = "Item ".$item_id.": ";
	$display .= (wc_get_order_item_meta($item_id, "pa_sessions") >=1 ) ? wc_get_order_item_meta($item_id, "pa_sessions")." sessions<br/>" : "";
	$display .= (wc_get_order_item_meta($item_id, "sessions_used") >=1 ) ? wc_get_order_item_meta($item_id, "sessions_used")." sessions used<br/>" : "";
	$display .= (wc_get_order_item_meta($item_id, "weeks") >=1 ) ? wc_get_order_item_meta($item_id, "weeks")." weeks<br/>" : "";
	$display .= (wc_get_order_item_meta($item_id, "weeks_used") >=1 ) ? wc_get_order_item_meta($item_id, "weeks_used")." weeks used<br/>" : "";
	echo $display;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><table>
	<tr><td><input type='number' name="meta_data" style='max-width: 3em;' value='1'></td><td><select name="meta_key">
	<option	value="pa_sessions">Sessions</option><option value="sessions_used">Sessions Used</option>
	<option	value="weeks">Weeks</option><option	value="weeks_used">Weeks Used</option></select>
	<input type="hidden" name="item_id" value=" <?php echo $_GET['item_id']; ?>">
	<input type="hidden" name="action" value="crm_update_order_meta">
	</td><td><input type='submit' value='Update'><input type='submit' name='submit' value='Delete'>
	</form></td></tr></table><br/><hr><br/><?php
}

add_action( 'admin_post_crm_update_order_meta', 'crm_update_order_meta');//process for crm_update_weeks_or_sessions()

/***********************************************************************************************
 * 
 * Order Search Functions
 * 
 ***********************************************************************************************/
/**
 * Get order object, link or ID from item ID
 * Replaces crm_admin_order_link_from_item_id, crm_order_id_from_item_id and crm_order_object_from_item_id
 */
function crm_order_info_from_item_id($item_id, $format = "object", $text = NULL){
	global $wpdb;
	$order_id = $wpdb->get_var("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = $item_id");
	$result = false;
	if(( $order_id >= 1)){
		if($format == "object"){
			$result = wc_get_order( $order_id );
		}elseif($format == "url"){
			$result = ($text == NULL) ? crm_admin_order_link($order_id) : crm_admin_order_link($order_id, $text);
		}else{
			$result = $order_id;
		}
	}
	return $result;
}

function crm_admin_order_link($order_id, $text = NULL){
	$url = get_site_url()."/wp-admin/post.php?post=".$order_id."&action=edit";
	$result = ($text != NULL) ? "<a href='".$url."'>".$text."</a>" : $url;
	return $result;
}

/**
 * Replaces crm_casual_or_enrolment in 2.2
 * Returns booking, casual or enrolment
 */
function crm_product_meta_type($product_id){
	$is_booking = get_post_meta($product_id, 'is_booking', true );
	$is_casual = get_post_meta($product_id, 'is_casual', true );
	$is_bookable = get_post_meta($product_id, 'is_bookable', true );
	if($is_booking && !$is_casual){
		$result = "booking";
	}elseif($is_casual){
		$result = "casual";
	}elseif($is_bookable){//bookable, but not casual or private booking
		$result = "enrolment";
	}else{
		$result = false;
	}
	return $result;
}

/**
 * Designed to work with the above function, crm_product_meta_type
 */
function crm_check_class_type($class_id){
	global $wpdb;
	$class_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = '$class_id'");
	if($class_info){
		$products = unserialize($class_info->prod_id);
		return crm_product_meta_type($products[0]);
	}
}

/**
 * Used to get select or array of casual, enrolment or booking products. Uses product meta data
 */
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
			}elseif($format == "ids"){
				$result[] = $post->ID;
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

/**
 * Get product posts by a given meta key and value
 * Replaces akimbo_crm_get_product_ids_by_age in 2.1.
 */
function akimbo_crm_return_posts_by_meta($key, $value, $format = "object"){
	$result = array();
	$args = array ( 
		'post_type'  => 'product',
		'posts_per_page'  => -1,
		'meta_query' => array( 
			array( 
			 'key' => $key, 
			 'value' => $value,
			), 
		  ),
		); 
	$query = new WP_Query( $args );
	if ( $query->have_posts() ){
		$result = ($format == "id") ? wp_list_pluck( $query->posts, "ID") : $query->posts;	
	}
	return $result;
}

/*
* Replaces get_all_orders_items_from_a_product_variation and get_all_orders_from_a_product_id. 
* Use '_variation_id' and '_product_id'
*/
function crm_return_orders_by_meta($key, $value, $date = NULL){
	$matched_orders = array();
	$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC') );
	$orders = $query->get_orders();//get all orders
	foreach ( $orders as $order ) {
		$items = $order->get_items();
		foreach ( $items as $item) {
			$metadata = $item->get_meta($key, true);
			if($metadata){
				$matched_orders[] = $order;
			}
			/*if(is_array($value)){//e.g. array of product ids
				if($metadata && in_array($metadata, $value)){
					$matched_orders[] = $order;
				}
			}else{
				if($date != NULL){
					$metadata = date('Y-m-d H:i', strtotime($item->get_meta($key, true)));
					$value = date('Y-m-d H:i', strtotime($value));
				}
				if($metadata && $value == NULL){//if has meta key, regardless of value
					$matched_orders[] = $order;
				}elseif($metadata && $metadata == $value){//if has meta key, and value matches 
					$matched_orders[] = $order;
				}
			}*/
			
		}
	}
	if(count($matched_orders) <= 0){$matched_orders = false;}
	return $matched_orders;
}

//Above function not currently working in matched orders. Delete this one when it's no longer needed
/*
function get_all_orders_items_from_a_product_variation( $variation_id ){// returns array of order items ids
    global $wpdb;
    $item_ids_arr = $wpdb->get_col( $wpdb->prepare( "
        SELECT `order_item_id` 
        FROM {$wpdb->prefix}woocommerce_order_itemmeta 
        WHERE meta_key LIKE '_variation_id' 
        AND meta_value = %s
    ", $variation_id ) );
    return $item_ids_arr; 
}*/

/***********************************************************************************************
 * 
 * Order functions for passes
 * 
 ***********************************************************************************************/
/**
 * Get expiry date, or if not set, add to item meta
 */
function crm_get_or_set_expiry($order, $item_id){
	$exp_date = wc_get_order_item_meta( $item_id, 'expiry_date', true);
	if(!$exp_date){
		$year = date("Y", strtotime($order->get_date_paid())) + 1;
		$format = $year."-m-d-h:ia";
		$exp_date = date($format, strtotime($order->get_date_paid()));
		wc_update_order_item_meta($item_id, 'expiry_date', $exp_date);
	}
	return $exp_date;	
}

/**
 * Check passes used against crm_class_list
 */
function crm_calculate_passes_used($item_id, $compare = NULL, $pass_type = NULL){
	global $wpdb;
	$passes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = '$item_id'");
	$pass_type = ($pass_type == NULL) ? "weeks" : "sessions";
	if($compare != $passes){
		$meta_key = $pass_type."_used";
		wc_update_order_item_meta($item_id, $meta_key, $passes);
	}
	return $passes;
}

 /**
 * Get pass info for a given item ID
 * //replaces casual_return_available_sessions and crm_weeks_remaining. Used with $user->get_available_user_orders
 */
function crm_get_item_available_passes($item_id, $order = NULL){
	global $wpdb;
	/**
	 * Get order info
	 */
	if($order == NULL){
		$order = crm_order_info_from_item_id($item_id);
	}
	$item_info['available'] = false;
	if($order){
		$order_id = $order->get_id();
		$item_info['order_id'] = $order->get_id();
		$item_info['user_id'] = $order->get_user_id();
		$statuses = ['completed','processing'];
		if(in_array($order->get_status(), $statuses)  && get_post_type($order_id) != "shop_subscription"){//don't show unpaid orders or parent subscriptions
			/**
			 * Check item availability and set info if available
			 */
			
			$item_info['available'] = false;
			$item_data = new WC_Order_Item_Product($item_id);
			if(isset($item_data['pa_sessions']) || isset($item_data['sessions'])){
				$passes = (isset($item_data['sessions'])) ? $item_data['sessions'] : $item_data['pa_sessions'];
				$pass_type = "sessions";
			}elseif(isset($item_data['weeks'])){
				$passes = $item_data['weeks'];
				$pass_type = "weeks";
			}
			if(isset($passes)){
				$meta_key = $pass_type."_used";
				$used = (isset($item_data[$meta_key])) ? $item_data[$meta_key] : 0;
				$item_info['used'] = crm_calculate_passes_used($item_id, $used, $pass_type);
				$item_info['qty'] = $item_data->get_quantity(); // ? $item_data['_qty'];
				$item_info['passes'] = $passes*$item_info['qty'];
				$item_info['remaining'] = $item_info['passes'] - $item_info['used'];
				$item_info['expiry'] = crm_get_or_set_expiry($order, $item_id);
				$available = ($item_info['remaining'] >= 1 && $item_info['expiry'] >= current_time('Y-m-d-h:ia')) ? true : false;
				
				if($available){		
					/**
					 * Return available order information
					 */
					$item_info['available'] = true;
					$item_info['item_id'] = $item_id;
					$item_info['name'] = $item_data['name'];
					$item_info['order_id'] = $order_id;
					$item_info['pass_type'] = $pass_type;
					$item_info['product_id'] = $item_data['product_id'];
					$item_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-order/".$order_id."/'>View Order</a>";	
					$item_info['type'] = (get_post_type($order_id) == "renewal") ? "subscription" : "order";
					$item_info['subscription'] = wcs_order_contains_subscription( $order, "renewal" );
					
					//add in subscription function to avoid having to pass subscription object
					/*$subscription_info['parent'] = $parent;
					$subscription_info['active'] = true;
					$subscription_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-subscription/".$subscription_info['id']."/'>View Subscription</a>";
					$subscription_info['next'] = $subscription->get_date( 'next_payment' );*/
				}
			}else{//not a bookable item
			}
		}
	}
	
	return $item_info;
}



function crm_update_order_meta (){
	$item_id = $_POST['item_id'];
	$meta_key = $_POST['meta_key'];
	$meta_data = $_POST['meta_data'];
	if($_POST['submit'] == "Delete"){
		$result = wc_delete_order_item_meta($item_id, $meta_key);
	}else{
		$result = wc_update_order_item_meta($item_id, $meta_key, $meta_data);
	}
	
	if(!$result){
		wc_delete_order_item_meta( $item_id, $meta_key ); //update wasn't working if data existed, works if meta_key is deleted 1st
		$result = wc_update_order_item_meta($item_id, $meta_key, $meta_data);
	}
	if($result){
		$url = (isset($_POST['url'])) ? $_POST['url'] : crm_order_info_from_item_id($item_id, "url");
		wp_redirect( $url ); 
		exit;
	}
}



/***************************************************************************************************************************************
*
* Functions to update/archive
*
****************************************************************************************************************************************/

function crm_display_enrolment_issues(){
	global $wpdb;
	echo "Display all Youth/Junior Circus orders where weeks != weeks_used OR weeks/weeks_used not set";

	//$products = //function get all bookable products
	$products = crm_get_posts_by_type("enrolment", "ids");
	//$products = array('1473', '1479');
	$classes = crm_return_orders_by_meta('_product_id', $products);

	if($classes){
		echo "<table width='80%''><tr bgcolor = '#33ccff'><th>Youth/Junior Circus</th><th>Order ID</th><th>Weeks</th><th>Weeks Used</th><th>CRM count</th><th>Update</th><th></th></tr>";
		foreach ($classes as $class){
			$weeks = wc_get_order_item_meta($class->order_item_id, "weeks");
			$used = wc_get_order_item_meta($class->order_item_id, "weeks_used");
			$qty = wc_get_order_item_meta($class->order_item_id, "_qty");
			$total_weeks = $weeks*$qty;
			if($total_weeks != $used || $weeks < 1){
				$order_id = $class->ID;
				//var_dump($class);
				$qty = wc_get_order_item_meta($class->order_item_id, "_qty");
				echo "<tr><td>".$class->order_item_name;
				echo "</td><td><a href='".get_site_url()."/wp-admin/post.php?post=".$class->ID."&action=edit'>".$class->ID."</a></td>";
				?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
				<td><input type="text" name="weeks" value="<?php echo $weeks; ?>"><?php if ($qty >= 2) { echo " x".$qty;} ?></text></td>
				<td><input type="text" name="weeks_used" value="<?php echo $used; ?>"></text></td>
				<td><?php 
				echo crm_calculate_passes_used($class->order_item_id);
				//echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $order_id "); 
				?></td>
				<input type="hidden" name="item_id" value="<?php echo $class->order_item_id;?>">
				<input type="hidden" name="action" value="enrolment_issues_weeks_used_update">
				<td><input type='submit' value='Update meta'></form></td><td><?php
				$args = array("user" => get_post_meta($order_id, '_customer_user', true), );
				echo akimbo_crm_permalinks("students", "link", "View Students", $args);
				echo "</td></tr>";
			}
		}
		echo "</table>";	
	}else{echo "<h2>No issues found!<h2>";}
}

add_action( 'admin_post_enrolment_issues_weeks_used_update', 'enrolment_issues_weeks_used_update' );

/**
 * Admin post action for crm_display_enrolment_issues()
 */
function enrolment_issues_weeks_used_update(){
	$item_id = $_POST['item_id'];
	$weeks = wc_get_order_item_meta($item_id, "weeks");
	$used = wc_get_order_item_meta($item_id, "weeks_used");
	
	$meta_key = "weeks";
	$meta_value = $_POST['weeks'];
	if(!isset($weeks)){
		wc_add_order_item_meta($item_id, $meta_key, $meta_value);
	} else {wc_update_order_item_meta($item_id, $meta_key, $meta_value);}
	
	$meta_key = "weeks_used";
	$meta_value = $_POST['weeks_used'];
	if(!isset($used)){
		wc_add_order_item_meta($item_id, $meta_key, $meta_value);
	} else {wc_update_order_item_meta($item_id, $meta_key, $meta_value);}
	wp_redirect(akimbo_crm_permalinks("enrolment")); 
	exit;	
}