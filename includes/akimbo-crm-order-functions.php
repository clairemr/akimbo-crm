<?php  //Akimbo CRM order functions and shortcuts

/**
 * Add order message, updated from Akimbo CRM settings page
 */
function order_received_text() {
	get_option('akimbo_crm_order_message');
}

add_filter('woocommerce_thankyou_order_received_text', 'order_received_text');

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
			if($text != NULL){
				echo crm_admin_order_link($order_id, $text, false);
			}else{
				$result = crm_admin_order_link($order_id);
			}	
		}else{
			$result = $order_id;
		}
	}
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
	if($is_booking){
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
function crm_calculate_passes_used($item_id, $compare = NULL, $pass_type = "weeks"){
	global $wpdb;
	$passes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = '$item_id'");
	if($compare != NULL){
		if($compare != $passes){
			$meta_key = $pass_type."_used";
			wc_update_order_item_meta($item_id, $meta_key, $passes);
		}
	}
	return $passes;
}

 /**
 * Get pass info for a given item ID
 * //replaces casual_return_available_sessions. Used with $user->get_available_user_orders
 */
function crm_get_item_available_passes($item_id, $order = NULL){
	global $wpdb;
	/**
	 * Get order info
	 */
	if($order == NULL){
		$order = crm_order_info_from_item_id($item_id);
	}
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
			$remaining = $passes - $used;
			$item_info['test'] = $remaining;
			$expiry = crm_get_or_set_expiry($order, $item_id);
			$available = ($remaining >= 1 && $expiry >= current_time('Y-m-d-h:ia')) ? true : false;
			if($available){
				/**
				 * Check pass quantities are accurate
				 */
				$item_info['used'] = $used;
				$crm_passes_used = crm_calculate_passes_used($item_id);
				/*if($crm_passes_used != $item_info['used']){
					wc_update_order_item_meta($item_id, $meta_key, $crm_passes_used);
					$item_info['used'] = $crm_passes_used;
				}*/ //added to calculate passes function
				
				/**
				 * Return available order information
				 */
				$item_info['available'] = true;
				$item_info['expiry'] = $expiry;
				$item_info['item_id'] = $item_id;
				$item_info['name'] = $item_data['name'];
				$item_info['order_id'] = $order_id;
				$item_info['qty'] = $item_data->get_quantity(); // ? $item_data['_qty'] : 1;
				$item_info['passes'] = $passes*$item_info['qty'];
				$item_info['pass_type'] = $pass_type;
				$item_info['product_id'] = $item_data['product_id'];
				$item_info['remaining'] = $item_info['passes'] - $item_info['used'];
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
	return $item_info;
}

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
	</td><td><input type='submit' value='Update'>
	</form></td></tr></table><br/><hr><br/><?php
}

add_action( 'admin_post_crm_update_order_meta', 'crm_update_order_meta');//process for crm_update_weeks_or_sessions()

function crm_update_order_meta (){
	$item_id = $_POST['item_id'];
	$meta_key = $_POST['meta_key'];
	$meta_data = $_POST['meta_data'];
	$result = wc_update_order_item_meta($item_id, $meta_key, $meta_data);
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
* Admin order display functions
*
****************************************************************************************************************************************/
//Add user students link to admin order page
function crm_admin_order_display_student_list ($order){
	akimbo_crm_permalinks("students", "display", "View User Students", array("user" => $order->get_user_id(), ));
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
  ?><th class="line_sessions sortable" data-sort="3">Weeks/Sessions</th><?php
}
add_action( 'woocommerce_admin_order_item_headers', 'cak_crm_admin_order_items_headers' );

/**
 * Adds Weeks/Sessions info to admin order page
 */
function cak_crm_admin_order_item_values( $product, $item, $item_id ) {
  	global $wpdb;
  	echo "<td class='sessions'>";
  	$order = crm_order_info_from_item_id($item_id, "id");
	if (!$order) {//refund, avoid error message
	}else{
		$product_id = $item['product_id'];
  		$is_bookable = get_post_meta($product_id, "is_bookable", true);//use instead of wc_get_order_item_meta
		if(!$is_bookable){
			echo "Not bookable";
		}else{
			$class_type = crm_product_meta_type($product_id);
			switch ($class_type) {
			    case "casual":
			        $sessions = wc_get_order_item_meta($item_id, "pa_sessions");
					if($sessions <= 0){
						wc_update_order_item_meta($item_id, "pa_sessions", 1);//sessions default to 1 unless set otherwise
						$text = "Set";
					} else {
						$text = $sessions." Reset";
					}
					$args = array(
						"order" => $item['order_id'],
						"item_id" => $item_id,
					);	
					akimbo_crm_permalinks("troubleshooting", "display", $text, $args);
			        break;
			    case "enrolment":
			        $class_id = ($item['variation_id'] >= 1) ? $item['variation_id'] : $product->get_id();
			        $weeks = wc_get_order_item_meta($item_id, "weeks");
					$semester = ucwords(wc_get_order_item_meta($item_id, "semester"));
					if($semester == NULL){$semester = ucwords(wc_get_order_item_meta($item_id, "pa_semester"));}//some use pa_semester
			        if($semester == NULL){// || !isset($weeks)
			        	echo "Please update semester";
			        }else{
			        	$classes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$class_id' AND semester_slug = '$semester'");
			        	echo $weeks."/".$classes;
			        	if($weeks <= 0 && $classes >= 1 && $item->get_total() >= 1){
							wc_update_order_item_meta($item_id, "weeks", $classes);//use update, not add, so it won't enter additional rows
							$format = "button";
							$text = "Update";
						}else{
							$format = "display";
							$text = "Reset";
						}
						$args = array(
							"order" => $item['order_id'],
							"item_id" => $item_id,
						);	
				    	akimbo_crm_permalinks("troubleshooting", "display", $text, $args);

						if($weeks >= 1 && $classes >= 1 && $weeks != $classes){
							$price = $product->get_price();
							if($price != NULL){
								$new_price = ($price/$classes)*$weeks;
								$GST = $new_price/11;
								$subtotal = ($GST)*10;
								echo "<br/>Cost/GST/Total: <br/>$".round($subtotal, 2)."/$".round($GST, 2)."/$".$new_price;
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
							echo date("g:ia D jS M", strtotime($book_date)).". ";
							$text= "Reset";
							$args["book_date"] = date("Y-m-d-H:i", strtotime($book_date));
						}else{
							$text = "Set Date";
						}	
				    	akimbo_crm_permalinks("troubleshooting", "display", $text, $args);
			        break;
			    default:
			}
		}
	}
	echo "</td>";
}

add_action( 'woocommerce_admin_order_item_values', 'cak_crm_admin_order_item_values', 10, 3);

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
				akimbo_crm_permalinks("students", "display", "View Students", $args);
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