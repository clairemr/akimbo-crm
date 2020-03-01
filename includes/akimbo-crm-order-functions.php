<?php

/**
 *
 * Akimbo CRM order functions and shortcuts
 * 
 */

/*************
Reference list
**************

order_received_text()//print order message, set on options page

crm_add_content_empty_cart()//add message when people visit an empty cart
enrolment_issues_weeks_used_update()
crm_casual_or_enrolment($product_id): returns casual, enrolment or party for a given product id. Defaults to NULL
crm_product_get_age_slug($product_id)//Get age slug for a given product ID. Defaults to private

*/

add_filter('woocommerce_thankyou_order_received_text', 'order_received_text');
add_filter( 'wc_empty_cart_message', 'crm_add_content_empty_cart' );
add_filter('woocommerce_create_account_default_checked', '__return_true');//allow guest checkout but default to true
//add_filter( 'woocommerce_billing_fields', 'crm_unrequire_wc_phone_field' ); //throws an error for products requiring shipping
add_action( 'admin_post_enrolment_issues_weeks_used_update', 'enrolment_issues_weeks_used_update' );

function order_received_text() {
	get_option('akimbo_crm_order_message');
}
 
function crm_add_content_empty_cart() {
$shop_url= esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) );
echo "<p>Your cart is currently empty! Visit our <a href='".$shop_url."'>online store</a> to purchase class passes, workshops and circus gear</p>";//wc_empty_cart_message
}

//throws an error "Please enter an address to continue", https://stackoverflow.com/questions/46671968/woocommerce-error-when-removing-billing-fields-please-enter-an-address-to-cont?rq=1
function crm_unrequire_wc_phone_field( $fields ) {
    /*$fields['billing_address_1']['required'] = false;
	$fields['billing_address_2']['required'] = false;
	$fields['billing_city']['required'] = false;
	$fields['billing_postcode']['required'] = false;*/
    unset($fields['billing_company']);
	unset($fields['billing_country']);

    return $fields;
}

function crm_calculate_passes_used($item_id){
	global $wpdb;
	$passes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = '$item_id'");
	return $passes;
}

function crm_update_weeks_or_sessions($item_id){
	echo "Item ".$item_id.": ";
	$sessions = wc_get_order_item_meta($item_id, "pa_sessions");
	if($sessions >=1 ){echo $sessions." sessions<br/>";}
	$sessions_used = wc_get_order_item_meta($item_id, "sessions_used");
	if($sessions_used >=1 ){echo $sessions_used." sessions used<br/>";}
	$weeks = wc_get_order_item_meta($item_id, "weeks");
	if($weeks >=1 ){echo $weeks." weeks<br/>";}
	$weeks_used = wc_get_order_item_meta($item_id, "weeks_used");
	if($weeks_used >=1 ){echo $weeks_used." weeks used<br/>";}
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<table>
	<tr><td><input type='number' name="meta_data" style='max-width: 3em;'>
	</td><td><select name="meta_key">
	<option	value="pa_sessions">Sessions</option><!-- change to pa_sessions for live site -->
	<option	value="sessions_used">Sessions Used</option>
	<option	value="weeks">Weeks</option>
	<option	value="weeks_used">Weeks Used</option>
	</select>
	<input type="hidden" name="item_id" value=" <?php echo $_GET['item_id']; ?>">
	<input type="hidden" name="url" value="/wp-admin/post.php?post=<?php echo $_GET['order']; ?>&action=edit">
	<input type="hidden" name="action" value="crm_update_order_meta">
	</td><td><input type='submit' value='Update'>
	</form></td></tr>
	</table>
	<br/><hr><br/><?php
}

 add_action( 'woocommerce_admin_order_item_headers', 'cak_crm_admin_order_items_headers' );
 add_action( 'woocommerce_admin_order_item_values', 'cak_crm_admin_order_item_values', 10, 3);
 add_action( 'admin_post_crm_update_order_meta', 'crm_update_order_meta');

//Add students list to admin order page
add_action('woocommerce_admin_order_data_after_shipping_address', 'crm_admin_order_display_student_list', 10, 3);

function crm_admin_order_display_student_list ($user_id){
	$url_id = $user_id->get_user_id();//order object
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".$url_id;
	echo "<a href='".$url."'>View User Students</a>";
}
 
function crm_update_order_meta (){
	$item_id = $_POST['item_id'];
	$meta_key = $_POST['meta_key'];
	$meta_data = $_POST['meta_data'];
	$result = wc_update_order_item_meta($item_id, $meta_key, $meta_data);
	if($result){
		//echo "Success: ".$item_id.$meta_key.$meta_data.$url;
		$url = get_site_url().$_POST['url'];
		wp_redirect( $url ); 
		exit;
	}else {
		wc_delete_order_item_meta( $item_id, $meta_key ); //update wasn't working if there was already data, seems to work if meta_key gets deleted first. 16/09/19 change
		$result = wc_update_order_item_meta($item_id, $meta_key, $meta_data);
		if($result){
			//echo "Success: ".$item_id.$meta_key.$meta_data.$url;
			$url = get_site_url().$_POST['url'];
			wp_redirect( $url ); 
			exit;
		}else{
			echo "Still not working";
			echo $item_id."<br/>".$meta_key."<br/>".$meta_data."<br/>".$url."<br/>Something isn't right. Please let an administrator know what happened, with links and information on how they can repeat the issue.";	
		}
		
	}
 }
 
 //build mailchimp API functions into CRM kids enrol button, update most recent class
 
 
 
 
function cak_crm_admin_order_items_headers($order){
  ?><th class="line_sessions sortable" data-sort="3">Weeks/Sessions</th><?php
}

function cak_crm_admin_order_item_values( $product, $item, $item_id ) {
  //Get what you need from $product, $item or $item_id
  global $wpdb;
  echo "<td class='sessions'>";
	$product_id = $item['product_id'];
	$class_type = crm_casual_or_enrolment($product_id);  
	$url = "/wp-admin/post.php?post=".$item['order_id']."&action=edit";

	switch ($class_type) {
	    case "casual":
	        $sessions = wc_get_order_item_meta($item_id, "pa_sessions");//change to pa_sessions for live site
		if($sessions <= 0){
			wc_update_order_item_meta($item_id, "pa_sessions", 1);//sessions default to 1 unless set otherwise
			echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=enrolment&order=".$item['order_id']."&item_id=".$item_id."'>Set</a>";
		} else {
			echo $sessions." <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=enrolment&order=".$item['order_id']."&item_id=".$item_id."'>Reset</a>";
		}
	        break;
	    case "enrolment":
	        $class_id = $item['variation_id'];
		$classes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$class_id'");
		$weeks = wc_get_order_item_meta($item_id, "weeks");
		//echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm3&tab=enrolment&order=".$item['order_id']."&item_id=".$item_id."'>Set</a>";
		//echo "<input type='number' value='".$weeks."' style='max-width: 3em;'>/".$classes."<button>Update</button>";
		echo $weeks."/".$classes;
		if($weeks <= 0 && $classes >= 1){//use classes check to only add weeks for enrolments entered into CRM
			wc_update_order_item_meta($item_id, "weeks", $classes);//use update instead of add so it won't keep adding additional rows
			echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=enrolment&order=".$item['order_id']."&item_id=".$item_id."'><button>Update</button></a>";//refresh window
		}else{
			echo " <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=enrolment&order=".$item['order_id']."&item_id=".$item_id."'>Reset</a>";
		}
	        break;
	    case "party":
	    	$book_date = wc_get_order_item_meta($item_id, "_book_date");
	    	$book_date = date("ga D jS M", strtotime($book_date));
	    	if($book_date){
	    		echo $book_date." <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&order=".$item['order_id']."&item_id=".$item_id."'>Reset</a>";
	    	}else{
	        	echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&order=".$item['order_id']."&item_id=".$item_id."'>Set date</a>";
	        }
	        break;
	    default:
	        
	}

	echo "</td>";
}


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
add_filter( 'manage_edit-shop_order_columns', 'akimbo_crm_add_order_items_column_header', 20 );

/**
 * Adds 'Items' column content to 'Orders' page immediately before 'Total' column.
 *
 * @param string[] $column name of column being displayed
 */
function akimbo_crm_add_order_items_column_content( $column ) {
    global $post;

    if ( 'order_items' === $column ) {

        $order    = wc_get_order( $post->ID );
		//echo "Items from order ".$post->ID;
		
		$crm_order = wc_get_order($post->ID);
		$items = $crm_order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			echo $item_data['name']." ";
		}
    }
}
add_action( 'manage_shop_order_posts_custom_column', 'akimbo_crm_add_order_items_column_content' );

function crm_order_link_from_item_id($item_id){
	global $wpdb;
	$order_id = $wpdb->get_var("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = $item_id");
	$url = get_site_url()."/wp-admin/post.php?post=".$order_id."&action=edit";
	return $url;
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

function get_all_orders_items_from_a_product_variation( $variation_id ){

    global $wpdb;

    // Getting all Order Items with that variation ID
    $item_ids_arr = $wpdb->get_col( $wpdb->prepare( "
        SELECT `order_item_id` 
        FROM {$wpdb->prefix}woocommerce_order_itemmeta 
        WHERE meta_key LIKE '_variation_id' 
        AND meta_value = %s
    ", $variation_id ) );

    return $item_ids_arr; // return the array of orders items ids

}

function get_all_orders_from_a_product_id( $product_id ){

    global $wpdb;
	//$results = $wpdb->get_col("SELECT order_items.order_id //use this version to just get the order IDs
	
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

    return $results;
	
	////AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' ) //<-- add in to sort by order status

}

/*
*
* Get age slug for a given product ID
*
*/
function crm_product_get_age_slug($product_id){
	global $wpdb;
	if ( has_term( 'Adult Classes', 'product_cat', $product_id ) ){
		$age_slug = "adult";
	}elseif( has_term( 'Kids Classes', 'product_cat', $product_id ) ){
		$age_slug = "kids";
	}elseif( has_term( 'Playgroup', 'product_cat', $product_id ) ){
		$age_slug = "playgroup";
	}else{
		$age_slug = "private";
	}

	return $age_slug;
}

/**
 *
 * For a given product ID, return class_type casual, enrolment or party. Based on product tags
 * 
 */
function crm_casual_or_enrolment($product_id){
	global $wpdb;
	if ( has_term( 'casual', 'product_tag', $product_id ) ){
		$class_type = "casual";
	}elseif( has_term( 'enrolment', 'product_tag', $product_id ) ){
		$class_type = "enrolment";
	}elseif( has_term( 'party', 'product_tag', $product_id ) ){
		$class_type = "party";
	}else{
		$class_type = NULL;
	}

	return $class_type;
}