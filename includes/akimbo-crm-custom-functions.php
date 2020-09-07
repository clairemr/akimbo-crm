<?php 

/**
 *
 * Akimbo CRM custom functions and shortcodes
 * 
 */

/*************
Reference list
**************

crm_date_selector($page, $tab): date dropdown that adds date value to url e.g. payroll, roster page
crm_date_setter_month($date)
crm_date_setter_week($date)

crm_get_user_email_list($user_list): get user emails from an array of user ids


akimbo_crm_redirect(): use at the end of admin posts to direct back to the right page. Values home, classes, bookings, business or custom urls may be passed


crm_weeks_remaining($item_id): returns weeks, weeks_used, qty, total and remaining for a given item id

*/


add_action( 'admin_post_update_mailchimp_child', 'akimbo_crm_update_mailchimp_child' );


add_shortcode('termDates', 'akimbo_term_dates'); //[termDates]
add_shortcode('nextSemester', 'akimbo_next_semester'); //[nextSemester]


function akimbo_crm_account_permalinks($permalink = NULL, $id = NULL, $text = NULL, $format = "link"){//classes, orders, students
	$url = get_permalink( get_option('woocommerce_myaccount_page_id') );
	switch($permalink){
		case "students":
			$url .= "/students/";
	    	$args = array(
				'student_id' => $id,
			);
	    break;
	    default: 
	    	$args = NULL;
	}
	if ($args != NULL) {
		foreach($args as $key => $value){$url .= "&".$key."=".$value;}
	}

	if($text != NULL){
		$display = ($format == "button") ? "<button>".$text."</button>" : $text;
		$url = "<a href='".$url."'>".$display."</a>";
	}
	
	return $url;
}

function akimbo_crm_class_permalink($class_id = NULL, $text = NULL){
	$format = ($text != NULL) ? "display" : "link";
	$args = ($class_id != NULL) ? array("class" => $class_id) : NULL;
	$url = akimbo_crm_permalinks("classes", $format, $text, $args);

	return $url;
}

function akimbo_crm_student_permalink($student_id = NULL, $text = NULL, $admin = true){
	if($admin != true){
		$url = get_permalink( get_option('woocommerce_myaccount_page_id') )."/students/";
		if($student_id != NULL){
			$url .= "?student_id=".$student_id;
		}
		if($text != NULL){
			$url = "<a href='".$url."'>".$text."</a>";
		}
	}else{
		$format = ($text != NULL) ? "display" : "link";
		$args = ($student_id != NULL) ? array("student" => $student_id) : NULL;
		$url = akimbo_crm_permalinks("students", $format, $text, $args);
	}
	

	return $url;
}

function akimbo_crm_permalinks($permalink, $format = "link", $text = NULL, $args = NULL){
	switch($permalink){
	    case "home": 
	    	$page = "akimbo-crm";
	    	$text = ($text != NULL) ? $text : "Home";
	    break;
	    case "classes":
			$page = "akimbo-crm";
			$tab = "classes";
	    	$text = ($text != NULL) ? $text : "Manage Classes"; 
	    break;
	    case "payroll":  
	    	$page = "akimbo-crm3";
	    	$tab = "payroll";
	    	$text = ($text != NULL) ? $text : "Payroll"; 
	    break;
	    case "bookings"://manage availabilities
			$page = "akimbo-crm2";
			$tab = "bookings";
	    	$text = ($text != NULL) ? $text : "Manage Bookings"; 
	    break;
	    case "students":  
	    	$page = "akimbo-crm";
	    	$tab = "details";
	    	$text = ($text != NULL) ? $text : "Student Info"; 
		break;
		case "add student":  
	    	$page = "akimbo-crm";
			$tab = "details";
			$args = array("student" => "new");
			$text = ($text != NULL) ? $text : "Add New Student"; 
		break;
		case "payments":
			$page = "akimbo-crm2";
			$tab = "payments";
			$text = ($text != NULL) ? $text : "Late Payments"; 
		break;
		case "scheduling":  
	    	$page = "akimbo-crm2";
	    	$text = ($text != NULL) ? $text : "Scheduling"; 
		break;
		case "staff":  
			$page = "akimbo-crm";
			$tab = "availabilities";
	    	$text = ($text != NULL) ? $text : "Staff Portal"; 
		break;
		case "troubleshooting":  
			$page = "akimbo-crm2";
			$tab = "enrolment";
	    	$text = ($text != NULL) ? $text : "Enrolment Troubleshooting"; 
		break;
		case "statistics":  
			$page = "akimbo-crm3";
			$tab = "statistics";
	    	$text = ($text != NULL) ? $text : "Student Statistics"; 
	    break;
	    default: 
	    	$page = 'akimbo-crm';
	    	$text = ($text != NULL) ? $text : "Home";
	}
	$url = get_site_url()."/wp-admin/admin.php?page=".$page;
	if(isset($tab)){
	 	$url .= "&tab=".$tab;
	}
	if ($args != NULL) {
		foreach($args as $key => $value){$url .= "&".$key."=".$value;}
	}
	if($format == "link"){
		return $url;
	}elseif($format == "array"){//use to update date selector
		return array($page, $tab);
	}else{
		$display = ($format == "button") ? "<button>".$text."</button>" : $text;
		$result = "<a href='".$url."'>".$display."</a>";
		echo $result;
	}
}

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

//crm_dropdown_selector("students", "account", NULL, $user_id);
function crm_dropdown_selector($type, $page, $tab = NULL, $user_id = NULL){//, $value = "View"
	$action = ($page == "account") ? get_option('woocommerce_myaccount_page_id') : "admin.php";
	?><form action="<?php echo $action; ?>" method="get"><?php
	if($page != "account"){echo "<input type='hidden' name='page' value='".$page."' />";}
	if($tab != NULL){echo "<input type='hidden' name='tab' value='".$tab."' />";}
	switch ($type) {
	    case "students":  	
	    	echo "Student: ";
	    	echo crm_student_dropdown("student", NULL, $user_id); 
	    break;
	    case "users": 
	    	echo "User: ";
	    	akimbo_user_dropdown("user"); 
	    break;
	}
	?><input type="submit" value="View"></form><?php
}


function crm_simple_delete_button($table, $data_id, $data, $redirect, $display = NULL){// "crm_class_list'", "list_id", "123", "/wp-admin/admin.php?page=akimbo-crm2"
	$display = ($display != NULL) ? $display : "Delete";
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="table" value="<?php echo $table; ?>">
	<input type="hidden" name="data_id" value="<?php echo $data_id; ?>">
	<input type="hidden" name="data" value="<?php echo $data; ?>">
	<input type="hidden" name="redirect" value="<?php echo $redirect; ?>">
	<input type="hidden" name="action" value="crm_simple_delete_button_process">
	<input type="submit" value="<?php echo $display; ?>">
	</form> <?php
}

add_action( 'admin_post_crm_simple_delete_button_process', 'crm_simple_delete_button_process' );

function crm_simple_delete_button_process(){
	global $wpdb;
	$table = $wpdb->prefix.$_POST['table'];
	$data_id = $_POST['data_id'];
	$data = $_POST['data'];	
	$result = $wpdb->delete( $table, array( $data_id => $data,) );
	$message = ($result) ? "success" : "failure";
	$url = $_POST['redirect']."&message=".$message;
	wp_redirect( $url ); 
	exit;	
}

function crm_date_selector($page = akimbo-crm, $tab=NULL, $display = "Change Date"){
	?><form action="admin.php" method="get">
	<input type="hidden" name="page" value="<?php echo $page; ?>" /><?php 
	if(isset($tab)){ ?> <input type="hidden" name="tab" value="<?php echo $tab; ?>" /> <?php } ?>
	<input type="date" name="date"><input type="submit" value="<?php echo $display; ?>"></form><br/><?php
 }
/**
 * Should replace above function, works with permalinks
 */
function crm_date_selector_permalinks($permalink, $display = "Change Date"){//update permalink function
	?><form action="admin.php" method="get">
	<input type="hidden" name="page" value="<?php echo $page; ?>" /><?php 
	if(isset($tab)){ ?> <input type="hidden" name="tab" value="<?php echo $tab; ?>" /> <?php } ?>
	<input type="date" name="date"><input type="submit" value="<?php echo $display; ?>"></form><br/><?php
 }

function crm_date_setter_month($date){
	$date_setter['start'] = date('Y-m-01', strtotime($date));
	$date_setter['start_time'] = date('00:00 Y-m-01', strtotime($date));
	$date_setter['end'] = date('Y-m-t', strtotime($date));
	$date_setter['end_time'] = date('23:59 Y-m-t', strtotime($date));
	$date_setter['month'] = date("F", strtotime($date));
	$date_setter['year'] = date("Y", strtotime($date));
	$date_setter['number_of_days'] = date('t', strtotime($date));
	$date_setter['first_day'] = date('l', strtotime($date_setter['start']));
	
	$mnth = date('m', strtotime($date));
	if($mnth >= 12){
		$year = date('Y', strtotime($date)) + 1;
		$format = $year.'-01-01';
		$date_setter['next_month'] = date($format, strtotime($date));
	}else{
		$mth = date('m', strtotime($date)) + 1;
		$format = 'Y-'.$mth.'-01';
		$date_setter['next_month'] = date($format, strtotime($date));
	}

	if($mnth <= 1){
		$year = date('Y', strtotime($date)) - 1;
		$format = $year.'-12-01';
		$date_setter['previous_month'] = date($format, strtotime($date));
	}else{
		$mth = date('m', strtotime($date)) - 1;
		$format = 'Y-'.$mth.'-01';
		$date_setter['previous_month'] = date($format, strtotime($date));
	}
 	

	return $date_setter;
 }

function crm_date_setter_week($date){
 	$date_setter['week_start'] = date("Y-m-d 0:00", strtotime('monday this week', strtotime($date)));
	$date_setter['week_end'] = date("Y-m-d 23:59", strtotime('sunday this week', strtotime($date)));
	$date_setter['last_week_start'] = date("Y-m-d 0:00", strtotime('monday last week', strtotime($date)));
	$date_setter['last_week_end'] = date("Y-m-d 23:59", strtotime('sunday last week', strtotime($date)));
	$date_setter['next_week_start'] = date("Y-m-d 0:00", strtotime('monday next week', strtotime($date)));
	$date_setter['next_week_end'] = date("Y-m-d 23:59", strtotime('sunday next week', strtotime($date)));
	$date_setter['start'] = $date;
	$date_setter['end'] = date("Y-m-d", strtotime('+7 days', strtotime($date)));
 	
	return $date_setter;
 }

function crm_date_selector_header($page, $date = NULL, $period = "month"){
	$date = ($date != NULL) ? $date : current_time('Y-m-d');
	if($period == "month"){
		$crm_date = crm_date_setter_month($date);
		$previous = $crm_date['previous_month'];
		$next = $crm_date['next_month'];
		$title = $crm_date['month'].", ".$crm_date['year'];
	}elseif($period == "semester"){
		$current_semester = akimbo_term_dates('return', $date);
		$previous = $current_semester['previous_start'];
		$next = $current_semester['next_start'];
		$title = $current_semester['slug'];
	}else{
		$crm_date = crm_date_setter_week($date);
		$previous = date("l jS M", strtotime($crm_date['last_week_start']));//remove timestamp
		$next = date("l jS M", strtotime($crm_date['next_week_start']));
		$title = "Week Starting: ".date("l jS M", strtotime($crm_date['week_start']));
	}
	
	$header = "<h2>";
	if($previous != NULL){
		$header .= "<a href='".akimbo_crm_permalinks($page, "link", NULL, array("date" => $previous))."'>";
		$header .= "<input type='submit' value='<'></a> ";
	}
	$header .= $title;
	if($next != NULL){
		$header .= " <a href='".akimbo_crm_permalinks($page, "link", NULL, array("date" => $next))."'>";
		$header .= "<input type='submit' value='>'></a>";
	}
	$header .= "</h2>";
	return "$header";
}

 function akimbo_term_dates($format = 'echo', $date = NULL){
	global $wpdb;
	if($date == NULL){$date = current_time('Y-m-d-h:ia');}
	$semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_end >= '$date' ORDER BY semester_end ASC LIMIT 1");//get current term
	$curTerm = $semester->semester_slug;
	$term = substr($curTerm, 1, 1);
	$start_date = date("l jS F", strtotime($semester->semester_start));
	$end_date = date("l jS F", strtotime($semester->semester_end));
	if($format == 'return'){
		$previous_id = $semester->semester_id - 1;
		$previous_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_id = '$previous_id'");//get previous term
		$next_id = $semester->semester_id + 1;
		$next_semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_id = '$next_id'");//get next term
		$content = array(
			'slug' => $curTerm, 
			'start' => $semester->semester_start, 
			'end' => $semester->semester_end,
			'previous_start' => NULL,
			'next_start' => NULL,
		);
		if($previous_semester){
			$content['previous'] = $previous_semester->semester_slug;
			$content['previous_start'] = $previous_semester->semester_start;
		}
		if($next_semester){
			$content['next'] = $next_semester->semester_slug;
			$content['next_start'] = $next_semester->semester_start;
		}
	}else{
		$content = "Term ".$term.": ".$start_date." - ".$end_date;
	}
	return $content;
}

/*function akimbo_previous_semester($current_slug){
	global $wpdb;
	$semester_id = $wpdb->get_var("SELECT semester_id FROM {$wpdb->prefix}crm_semesters WHERE semester_slug = '$current_slug'");//get previous term
	$previous_id = $semester_id - 1;
	if($previous_id >= 1){
		$semester = $wpdb->get_var("SELECT semester_slug FROM {$wpdb->prefix}crm_semesters WHERE semester_id = '$previous_id'");//get previous term
	}else{$semester = "No earlier semesters";}
	
	return $semester;
}*/

function akimbo_next_semester(){
	global $wpdb;
	$today = current_time('Y-m-d-h:ia');
	$semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_start >= '$today' ORDER BY semester_start ASC LIMIT 1");//get next term
	$curTerm = $semester->semester_slug;
	$term = substr($curTerm, 1, 1);
	$start_date = date("l jS F", strtotime($semester->semester_start));
	$end_date = date("l jS F", strtotime($semester->semester_end));
	$content = "Term ".$term.", ".date("Y", strtotime($semester->semester_start)).": ".$start_date." - ".$end_date;
	
	return $content;
}



/*function crm_simple_update_button($table, $data, $where, $content){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
	echo $content;
	?><input type="hidden" name="action" value="crm_simple_update_table"><input type="submit"></form><?php
}

add_action( 'admin_post_crm_simple_update_table', 'crm_simple_update_table' );

function crm_simple_update_table($table, $data, $where, $redirect){//'crm_students', array('list_id' => $_POST['id']),
	$table = $wpdb->prefix.$_POST['table'];
	$result = $wpdb->update( $table, $data, $_POST['where']);
	$message = ($result) ? "success" : "failure";
	$url = get_site_url().$redirect."&message=".$message;
	wp_redirect( $url ); 
	exit;
}*/



//Mailchimp integration



/**
 *
 * Crm Admin Functions
 * //Potentially should be a class
 */

/*************
Reference list
**************
crm_add_dashboard_widgets()//register dashboard widget
crm_dashboard_widget_function()//dashboard widget output


*/










//Not used? 14/1/20
/*function akimbo_crm_admin_student_details(){
	//akimbo_crm_show_all_classes();//code moved to crm-enrolment_functions
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	$header = "<h2>All classes from ".$crm_date['start']." - ".$crm_date['end']."</h2>";
	echo apply_filters('akimbo_crm_manage_business_header', $header);
	apply_filters('akimbo_crm_manage_business_date_selector', crm_date_selector("akimbo-crm3", "classes"));
	crm_class_list($crm_date['start'], $crm_date['end']); 
}*/


/**
 *
 * Plugin redirect, use at the end of admin posts to direct back to the right page. Values home, classes, bookings, business or custom urls may be passed
 * Replaced by permalinks in 2.1
 */
/*
function akimbo_crm_redirect($page){
	$site = get_site_url();
	if(isset($page)){
		switch ($page) {
			case "home":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm";
				break;
			case "classes":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm2";
				break;
			case "bookings":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm3";
				break;
			case "business":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm4";
				break;
			default:
				$url = $site.$page;//allows custom urls to be passed
		}
	}else{
		$url = $site."/wp-admin/admin.php?page=akimbo-crm";		
	}

	wp_redirect( $url );
	exit;
}*/





function crm_get_user_email_list($user_list){//user emails from user ids
	foreach($user_list as $user){
		//$user = $student_edit->user_id;
		$user_info = get_userdata($user);
		//echo "<br/>Name: ". $user_info->first_name." ".$user_info->last_name;
		//echo '<br/>User roles: ' . implode(', ', $user_info->roles) . "\n";
		//echo '<br/>User ID: ' . $user_info->ID . "\n";
		echo $user_info->user_email.", ";
	}
}









function crm_nav_tab($page, $tab, $title, $active_tab){
	$url = "<a href='?page=".$page."&tab=".$tab."' ";
	$url .= $active_tab == $tab ? "class='nav-tab nav-tab-active'" : "class='nav-tab'";
	$url .= "'>".$title."</a>";
	return $url;
}





function crm_weeks_remaining($item_id){
	global $wpdb;
	/*$weeks = wc_get_order_item_meta( $item_id, 'weeks', true );
	$weeks_used = wc_get_order_item_meta( $item_id, 'weeks_used', true );
	$qty = wc_get_order_item_meta( $item_id, '_qty', true );
	$total = $weeks * $qty;
	$remaining = $total - $weeks_used;*/
	$weeks_used = wc_get_order_item_meta( $item_id, 'weeks_used', true );
	if(!isset($weeks_used)){$weeks_used = 0;}
	$remaining['weeks'] = wc_get_order_item_meta( $item_id, 'weeks', true );
	$remaining['weeks_used'] = $weeks_used;
	$remaining['qty'] = wc_get_order_item_meta( $item_id, '_qty', true );
	$remaining['total'] = $remaining['weeks'] * $remaining['qty'];
	if($weeks_used >= 1){$remaining['remaining'] = $remaining['total'] - $weeks_used;}else{$remaining['remaining'] = $remaining['total'];}
	
	return $remaining;
}













/**
 *
 * Potentially outdated or broken
 * 
 */

/*************
Reference list
**************


*/


/*function register_crm_admin_css(){
	//get_stylesheet_directory_uri for childtheme, get_template_directeory_uri otherwise
	$src = get_stylesheet_directory_uri()."/css/admin-css.css";
	$handle = "crmAdminCss";
	wp_register_script($handle, $src);
	wp_enqueue_style($handle, $src, array(), false, false);
}*/

add_action( 'login_enqueue_scripts', 'akimbo_crm_login_logo' );

function akimbo_crm_login_logo() { ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/images/site-login-logo.png);
		height:65px;
		width:320px;
		background-size: 320px 65px;
		background-repeat: no-repeat;
        	padding-bottom: 30px;
        }
    </style>
<?php }

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
			if(is_array($value)){//e.g. array of product ids
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
			}
			
		}
	}
	if(count($matched_orders) <= 0){$matched_orders = false;}
	return $matched_orders;
}

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