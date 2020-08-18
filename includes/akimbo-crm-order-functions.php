<?php

/**
 *
 * Akimbo CRM order functions and shortcuts
 * 
 */

/*************
Reference list
**************

Sections:
 - Admin order display functions
 - Front end display functions
 - Functions to upgrade/archive
 - General order functions


crm_casual_or_enrolment($product_id): returns casual, enrolment or party for a given product id. Defaults to NULL
crm_product_get_age_slug($product_id)//Get age slug for a given product ID. Defaults to private
get_all_orders_items_from_a_product_variation( $variation_id )
get_all_orders_from_a_product_id( $product_id ){

*/

/*
* Admin order display functions
*

crm_update_weeks_or_sessions($item_id)//admin update form, currently on enrolment issues page

*/

/**
 * New in 2.1
 * wp_list_pluck is my new favourite thing
 */

function akimbo_crm_get_product_ids_by_age($age_slug){
	$args = array ( 
		'post_type'  => 'product',
		'posts_per_page'  => -1,
		'meta_query' => array( 
			array( 
			 'key' => 'age_slug', 
			 'value' => $age_slug,
			), 
		  ),
		); 
	$query = new WP_Query( $args );
	if ( $query->have_posts() ){
		$products = wp_list_pluck( $query->posts, "ID");
	}
	return $products;
}




add_filter( 'manage_edit-shop_order_columns', 'akimbo_crm_add_order_items_column_header', 20 );//add items column header
add_action( 'manage_shop_order_posts_custom_column', 'akimbo_crm_add_order_items_column_content' );//Add items column to order posts page
add_action( 'admin_post_crm_update_order_meta', 'crm_update_order_meta');//process for crm_update_weeks_or_sessions()
add_action( 'woocommerce_admin_order_item_headers', 'cak_crm_admin_order_items_headers' );//adds Weeks/Sessions header to admin order page
add_action( 'woocommerce_admin_order_item_values', 'cak_crm_admin_order_item_values', 10, 3);//adds Weeks/Sessions info to admin order page
add_action('woocommerce_admin_order_data_after_shipping_address', 'crm_admin_order_display_student_list', 10, 3);//add user students link

/*
* Functions to upgrade/archive
*

crm_display_enrolment_issues()//displays on enrolment issues page. No longer accurate with changed ways of prod ids & is_bookable

*/
add_action( 'admin_post_enrolment_issues_weeks_used_update', 'enrolment_issues_weeks_used_update' );


/*****************************************************************************************************************************************
* Front end display functions
*****************************************************************************************************************************************/
add_filter('woocommerce_thankyou_order_received_text', 'order_received_text');//Add order message, updated from Akimbo CRM settings page
add_filter( 'wc_empty_cart_message', 'crm_add_content_empty_cart' );//add message when people visit an empty cart
add_filter('woocommerce_create_account_default_checked', '__return_true');//allow guest checkout but default to true

function order_received_text() {
	get_option('akimbo_crm_order_message');
}
 
function crm_add_content_empty_cart() {
$shop_url= esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) );
echo "<p>Your cart is currently empty! Visit our <a href='".$shop_url."'>online store</a> to purchase class passes, workshops and circus gear</p>";//wc_empty_cart_message
}

/*
* Order functions
*

crm_admin_order_link($order_id, $text = NULL)//link to admin order page from order_id. $text sets display or plain url
function crm_admin_order_link_from_item_id($item_id, $text = NULL)//link to admin order page from item_id. $text sets display or plain url
crm_get_or_set_expiry($order, $item_id)//gets expiry date, or sets default 1 year expiry if not set
crm_calculate_passes_used($item_id, $compare = NULL, $pass_type = "weeks") //return count from attendance table for given item id
crm_get_order_available_passes($order, $product_ids = NULL) //return array of item info for a given order, with $item_info['available'] set to true/false. For single item only. Product ID check not currently working. This is the main check, used in user class. Check qty calculates correctly
get_all_orders_items_from_a_product_variation( $variation_id ){// returns array of order items ids
get_all_orders_from_a_product_id( $product_id, $ids = NULL ){//return $ids (if true) or array of results
crm_product_get_age_slug($product_id){//based on product category, Private lessons, Adult Classes, Kids Classes or Playgroup

crm_product_meta_type($product_id)//replaces crm_casual_or_enrolment in 2.2 and returns booking, casual or enrolment
*/

function crm_admin_order_link($order_id, $text = NULL, $return = false){
	$url = get_site_url()."/wp-admin/post.php?post=".$order_id."&action=edit";
	if($text != NULL){
		if($return == false){
			echo "<a href='".$url."'>".$text."</a>";
		}else{
			return "<a href='".$url."'>".$text."</a>";
		}
	}else{
		return $url;
	}	
}

function crm_admin_order_link_from_item_id($item_id, $text = NULL){
	$order_id = crm_order_id_from_item_id($item_id);
	$url = get_site_url()."/wp-admin/post.php?post=".$order_id."&action=edit";
	if($text != NULL){
		echo "<a href='".$url."'>".$text."</a>";
	}else{
		return $url;
	}	
}

function crm_order_id_from_item_id($item_id){
	global $wpdb;
	$order_id = $wpdb->get_var("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = $item_id");
	return $order_id;
}

function crm_order_object_from_item_id($item_id){
	$order_id = crm_order_id_from_item_id($item_id);
	$order = ( $order_id >= 1) ? wc_get_order( $order_id ) : false;
	return $order;
}

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

function crm_calculate_passes_used($item_id, $compare = NULL, $pass_type = "weeks"){
	global $wpdb;
	$passes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = '$item_id'");
	return $passes;
}

function crm_get_order_available_passes($order, $product_ids = NULL){
	global $wpdb;
	$items = $order->get_items();
	foreach($items as $item){
		$item_info = crm_get_item_available_passes($item->get_id());
	}
	
	return $item_info;
}



//function crm_get_item_available_passes($order, $items){//give array of items
function crm_get_item_available_passes($item_id, $order = NULL){
	global $wpdb;
	/**
	 * Get order info
	 */
	if($order == NULL){
		$order_id = $wpdb->get_var("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = $item_id");
		$order = wc_get_order($order_id);
	}else{
		$order_id = $order->get_id();
	}
	$item_info['user_id'] = $order->get_user_id();
	if($order->get_status() != "pending"  && get_post_type($order_id) != "shop_subscription"){//don't show unpaid orders or parent subscriptions
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
			$expiry = crm_get_or_set_expiry($order, $item_id);
			$available = ($remaining >= 1 && $expiry >= current_time('Y-m-d-h:ia')) ? true : false;
			if($available){
				/**
				 * Check pass quantities are accurate
				 */
				$item_info['used'] = $used;
				$crm_passes_used = crm_calculate_passes_used($item_id);
				if($crm_passes_used != $item_info['used']){
					wc_update_order_item_meta($item_id, $meta_key, $crm_passes_used);
					$item_info['used'] = $crm_passes_used;
				}
				
				/**
				 * Return available order information
				 */
				$item_info['available'] = true;
				$item_info['expiry'] = $expiry;
				$item_info['item_id'] = $item_id;
				$item_info['name'] = $item_data['name'];
				$item_info['order_id'] = $order_id;
				$item_info['passes'] = $passes;
				$item_info['pass_type'] = $pass_type;
				$item_info['product_id'] = $item_data['product_id'];
				$item_info['qty'] = $item_data->get_quantity(); // ? $item_data['_qty'] : 1;
				$item_info['remaining'] = ($passes * $item_info['qty']) - $item_info['used'];
				$item_info['url'] = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."view-order/".$item_info['$order_id']."/'>View Order</a>";	
				//$order_info['type'] = "casual";
				$item_info['type'] = (get_post_type($order_id) == "renewal") ? "subscription" : "order";
				
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

function get_all_orders_items_from_a_product_variation( $variation_id ){// returns array of order items ids
    global $wpdb;
    $item_ids_arr = $wpdb->get_col( $wpdb->prepare( "
        SELECT `order_item_id` 
        FROM {$wpdb->prefix}woocommerce_order_itemmeta 
        WHERE meta_key LIKE '_variation_id' 
        AND meta_value = %s
    ", $variation_id ) );
    return $item_ids_arr; 
}

function get_all_orders_from_a_product_id( $product_id, $ids = NULL ){//return $ids (if true) or array of results
    global $wpdb;
	if($ids != NULL){
		$results = $wpdb->get_col("
			SELECT order_items.order_id
	        FROM {$wpdb->prefix}woocommerce_order_items as order_items
	        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
	        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
	        WHERE posts.post_type = 'shop_order'
	        
	        AND order_items.order_item_type = 'line_item'
	        AND order_item_meta.meta_key = '_product_id'
	        AND order_item_meta.meta_value = '$product_id'
	    ");
	}else{
		$results = $wpdb->get_results("
	        SELECT *
	        FROM {$wpdb->prefix}woocommerce_order_items as order_items
	        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
	        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
	        WHERE posts.post_type = 'shop_order'
	        
	        AND order_items.order_item_type = 'line_item'
	        AND order_item_meta.meta_key = '_product_id'
	        AND order_item_meta.meta_value = '$product_id'
	    ");
		 ////AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' ) //<-- add in to sort by order status
	}
	
    return $results;
}

/*
*
* Get age slug for a given product ID
*
*/
function crm_product_get_age_slug($product_id){
	global $wpdb;
	if( has_term( 'Private Lessons', 'product_cat', $product_id ) ){
		$age_slug = "private";
	}elseif( has_term( 'Kids Classes', 'product_cat', $product_id ) ){
		$age_slug = "kids";
	}elseif( has_term( 'Playgroup', 'product_cat', $product_id ) ){
		$age_slug = "playgroup";
	}else{
		$age_slug = "adult";//( has_term( 'Adult Classes', 'product_cat', $product_id ) ), default option
	}

	return $age_slug;
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
	$products = unserialize($class_info->prod_id);
	$result = crm_product_meta_type($products[0]);
	return $result;
}





/***************************************************************************************************************************************
*
* Admin order display functions
*
****************************************************************************************************************************************/

/**
 * Adds 'Item Name' column header to 'Orders' page immediately before 'Total' column.
 *
 * @param string[] $columns
 * @return string[] $new_columns
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

/**
 * Adds 'Items' column content to 'Orders' page immediately before 'Total' column.
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

//Add students list to admin order page
function crm_admin_order_display_student_list ($user_id){
	$url_id = $user_id->get_user_id();//order object
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".$url_id;
	echo "<a href='".$url."'>View User Students</a>";
}

//Add Weeks/Sessions header to admin order page
function cak_crm_admin_order_items_headers($order){
  ?><th class="line_sessions sortable" data-sort="3">Weeks/Sessions</th><?php
}

function cak_crm_admin_order_item_values( $product, $item, $item_id ) {
  	global $wpdb;
  	echo "<td class='sessions'>";
  	$order = crm_order_id_from_item_id($item_id);
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
						$book_date = date("ga D jS M", strtotime(wc_get_order_item_meta($item_id, "_book_date")));
						echo $book_date.". ";
						$text = ($book_date) ? "Reset" : "Set Date";
						$args = array(
							"order" => $item['order_id'],
							"product_id" => $item->get_product_id(),
							"book_date" => date("Y-m-d-H:i", strtotime($book_date)),
						);	
				    	akimbo_crm_permalinks("troubleshooting", "display", $text, $args);
			        break;
			    default:
			}
		}
		
	}
	echo "</td>";
}

/*
* Simple function to update weeks, weeks_used, sessions or sessions_used
*/
function crm_update_weeks_or_sessions($item_id){//displaying on enrolment issues page
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
		$url = (isset($_POST['url'])) ? get_site_url().$_POST['url'] : crm_admin_order_link_from_item_id($item_id);
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
	$classes = array_merge(get_all_orders_from_a_product_id( '1473' ), get_all_orders_from_a_product_id( '1479' ));

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
				<td><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $order_id "); ?></td>
				<input type="hidden" name="item_id" value="<?php echo $class->order_item_id;?>">
				<input type="hidden" name="action" value="enrolment_issues_weeks_used_update">
				<td><input type='submit' value='Update meta'></form></td>


				<?php
				echo "<td><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".get_post_meta($order_id, '_customer_user', true)."'>View students</a>";
				echo "</td></tr>";
			}
		}
		echo "</table>";	
	}else{echo "<h2>No issues found!<h2>";}
}

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
	
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm3&tab=enrolment";
	wp_redirect( $url ); 
	exit;	
}