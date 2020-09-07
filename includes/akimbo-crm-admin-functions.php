<?php // Akimbo CRM admin area display functions
/**
 * Add a widget to the dashboard. Hooked into the 'wp_dashboard_setup' action.
 */
function crm_add_dashboard_widgets() {
	if(current_user_can('upload_files')){//only visible to author level and above
		wp_add_dashboard_widget(
			'crm_dashboard_widget',         // Widget slug.
			'Akimbo CRM',         // Title.
			'crm_dashboard_widget_function' // Display function.
		);	
	}
}
add_action( 'wp_dashboard_setup', 'crm_add_dashboard_widgets' );

/**
 * Output the contents of the Dashboard Widget.
 */
function crm_dashboard_widget_function() {
	echo "<h2>Upcoming Classes</h2>";
	$date = current_time('Y-m-d');
	crm_class_list(); 
	akimbo_crm_permalinks("students", "button");
	if(current_user_can('manage_options')){
		akimbo_crm_permalinks("classes", "button");
		akimbo_crm_permalinks("bookings","button");
		akimbo_crm_permalinks("payroll", "button");
	}
}

/***********************************************************************************************
 * 
 * Main admin area. All staff (author level or higher) can view
 * 
 ***********************************************************************************************/
/**
 * Plugin Home Page
 */
function akimbo_crm_admin_home_page(){
	echo "<h1>Akimbo CRM</h1>";
	echo "<h2>Upcoming Classes</h2>";
	global $wpdb;
	$today = current_time('Y-m-d');
	crm_class_list($today);
	akimbo_crm_permalinks("add student", "button");
	if (current_user_can('manage_woocommerce')){
		akimbo_crm_permalinks("scheduling", "button");
		akimbo_crm_permalinks("payroll", "button");
		akimbo_crm_permalinks("bookings", "button");
	}
}

/**
 * Class details page
 */
function akimbo_crm_manage_classes(){
	if(isset($_GET['class'])){
		global $wpdb;
		$class_type = crm_check_class_type($_GET['class']);
		if($class_type == "booking"){
			crm_display_booking_info($_GET['class']);//in booking functions
		}else{
			akimbo_crm_manage_classes_details($_GET['class']);
			crm_class_list();//show next 10 classes
		}
		
	} else{
		$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');
		echo "<h2>".crm_date_selector_header("classes", $date, "week")."</h2>";
		$crm_date = crm_date_setter_week($date);
		crm_class_list($crm_date['week_start'], $crm_date['week_end']);
	}
	echo "<br/><hr><br/>";
	apply_filters('akimbo_crm_manage_classes_date_selector', crm_date_selector_permalinks("classes"));
}

/**
 * Students tab. 
 * Shows student and user details if id provided, or duplicate students function if not
 */
function akimbo_crm_student_details(){
	crm_dropdown_selector("students", "akimbo-crm", "details");
	crm_dropdown_selector("users", "akimbo-crm", "details");
	akimbo_crm_permalinks("add student", "text");
	echo "<br/><hr><br/>";
	$url = (isset($_GET['class'])) ? akimbo_crm_permalinks("classes", "link", NULL, array("class" => $_GET['class'], )) : akimbo_crm_permalinks("students");
	if(isset($_GET['student'])){//Student Details
		$student_id = $_GET['student'];
		if(is_numeric($student_id)){
			$student = new Akimbo_Crm_Student($_GET['student']);
			update_student_details_form($student_id, $url, 1);
			echo "<br/><hr><br/>";
			akimbo_crm_display_student_classes($student);
			$student->display_mailchimp(true);
		}else{//e.g. student == "new"
			update_student_details_form(0, $url, 1);
		}
	}

	if(isset($_GET['user'])){//User Details
		$user = new Akimbo_Crm_User($_GET['user']);
		akimbo_crm_admin_user_info($user);
	}

	if(!isset($_GET['student']) && !isset($_GET['user']) && current_user_can('manage_options')){
		crm_find_duplicate_students($url);//only visible to admin
	}
}

/**
 * Staff Details Tab
 */
function akimbo_crm_staff_details(){
	global $wpdb;
	$args = array('role__in' => array('shop_manager', 'author', 'administrator'),'orderby' => 'display_name', 'order' => 'ASC',);
	$users = get_users( $args );
	$message = "<br/><hr><br/>Can't make a rostered shift? You can try to swap it in the trainer <a href='https://www.facebook.com/groups/863632663663310/'>Facebook group</a> or contact another staff member directly.<br/><hr><br/>";
	echo apply_filters('akimbo_crm_manage_roster_message', $message);
	if(current_user_can('manage_options')){
		crm_roster_update();//add shifts
		add_filter( 'akimbo_crm_staff_details_roster_edit', 'akimbo_crm_staff_details_roster_email');
		echo "<br/><hr><br/>";
	}
	echo "<table width='80%'><tr><th>ID</th><th>Name</th><th>Email</th>";
	if(current_user_can('manage_options')){echo "<th></th>";}
	echo "</tr>";
	foreach ($users as $user){ 
		echo "<tr><td>".$user->ID."</td><td>".$user->display_name."</td><td>".esc_html( $user->user_email );
		if(current_user_can('manage_options')){//allow editing
			echo "<td><a href='".akimbo_crm_permalinks("staff")."&staff_id=".$user->ID."'>Employee Details</a>";
		}
		echo "</td></tr>";
		$emails[] = esc_html( $user->user_email );
		if(isset($_GET['staff_id']) && $user->ID == $_GET['staff_id']){
			akimbo_crm_update_staff_meta_form($_GET['staff_id']);
		}
	}
	echo "</table><br/><b>Emails: </b>";
	foreach ($emails as $email){echo $email.", ";}
}

/***********************************************************************************************
 * 
 * Scheduling Functions. Only admin level can view
 * 
 ***********************************************************************************************/

function akimbo_crm_enrolment_issues(){//delete from final plugin
	global $wpdb;
	if(isset($_GET['product_id'])){//used for adding book_date
		echo "<h2>Set Booking Date</h2>";
		crm_update_book_date($_GET['order']);
	}elseif(isset($_GET['item_id'])){
		echo "<h2>Fix enrolment issues</h2>";
		crm_update_weeks_or_sessions($_GET['item_id']);
	}else{
		crm_display_enrolment_issues();
	}	
}

function akimbo_crm_unpaid_students(){
	global $wpdb;
	echo "<h2>Unpaid Students</h2>";
	$unpaid_students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id WHERE ord_id = '' ORDER BY {$wpdb->prefix}crm_class_list.session_date DESC");
	foreach($unpaid_students as $unpaid_student){
		$student = new Akimbo_Crm_Student($unpaid_student->student_id);
		echo "<p><b>".$unpaid_student->student_name."</b> ".$unpaid_student->class_title.", ".date("g:ia, l jS M Y", strtotime($unpaid_student->session_date)).". ";
		echo $student->student_admin_link("View Student");
		akimbo_crm_update_unpaid_classes($student->get_user_id(), $unpaid_student->attendance_id, $unpaid_student->age_slug);
		$user_info = get_userdata($student->get_user_id());
		echo "<i>User: ". $user_info->first_name." ".$user_info->last_name . ", ".$user_info->user_email."</i></p>";
		
	}
}

function akimbo_crm_party_data(){
	global $wpdb;
	$today = current_time('Y-m-d-h:ia');
	//initialize CSV writing
	$path = wp_upload_dir();   // or where ever you want the file to go
	$outstream = fopen($path['path']."/partydata.csv", "w");  // the file name you choose
	$fields = array('customer', 'item-id', 'date', 'order_date', 'total');  // the information you want in the csv file
	fputcsv($outstream, $fields);  //creates the first line in the csv file
	echo "<br/><table border='1'><tr><td>Customer</td><td>Product</td><td>Date</td><td>Order Date</td><td>Subtotal</td></tr>";
	$parties = crm_return_orders_by_meta('_book_date', NULL);
	if($parties){
		foreach($parties as $order){
			$items = $order->get_items();
			foreach($items as $item){
				$item_product_id = $item['product_id'];
				echo "<tr><td>".$order->get_customer_id()."</td>";
				echo "<td>".$item['name']."</td>";
				echo "<td>".$item['_book_date']."</td>";
				echo "<td>".$party->get_date_created()."</td>";
				echo "<td>".$item['subtotal']."</td></tr>";
				fputcsv($outstream, array($order->get_customer_id(), $item['name'], $item['_book_date'], $party->get_date_completed(), $item['subtotal']));
			}
		}
	}
	echo "</table>";
	fclose($outstream); 
	echo '<a href="'.$path['url'].'/partydata.csv">Download as csv</a>';  //make a link to the file so the user can download.
}

/***********************************************************************************************
 * 
 * Business & Statistics Functions. Only admin level can view
 * 
 ***********************************************************************************************/

 function akimbo_crm_student_statistics(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');
	$current_semester = akimbo_term_dates('return', $date);
	echo "<table width='90%'><tr><th>";
	echo crm_date_selector_header("statistics", $date, "semester");
	echo "</th></tr><tr align='center'><td>";
	echo "<h4>".date("D jS M, Y", strtotime($current_semester['start']))." - ".date("D jS M, Y", strtotime($current_semester['end']))."</h4>";
	crm_date_selector_permalinks("statistics");
	echo "</td></tr></th></tr></table><hr>";
	echo "<br/>Total students: ".$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_students" );
	echo "<br/>Total users: ".$wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" )."<br/><hr>";
	
	/**
	 * Get student information
	 */
	$students = $wpdb->get_col("SELECT student_id FROM {$wpdb->prefix}crm_students");
	$past_slug = (isset($current_semester['previous'])) ? $current_semester['previous'] : NULL;//akimbo_previous_semester($slug);
	$returning = array();
	$new = array();
	$not_returning = array();
	foreach($students as $student_id){
		$student = new Akimbo_Crm_Student($student_id);
		$age = $student->get_age();
		if($student->attending_this_semester($current_semester['slug']) == true){
			if($student->new_or_returning() == "returning"){
				$returning[] = $student;
			}else{
				$new[] = $student;
			}
		}elseif($student->attending_this_semester($current_semester['slug']) != true && $student->attending_this_semester($past_slug) == true){
			$not_returning[] = $student;
		}
	}
	$total = count($new) + count($returning);
	
	/**
	 * Display student information
	 */
	echo "<b>Total students: ".$total."</b>";
	echo "<details><summary>Returning students: ".count($returning)."</summary>";
	if($returning){
		foreach ($returning as $student){
			$details = $student->get_student_info();
			echo "<br/>".$details->full_name().akimbo_crm_student_permalink($details->student_id, "View");
		}
	}else{
		echo "<i>No returning students</i>";
	}
	echo "<hr></details><details><summary>New students: ".count($new)."</summary>";
	if($new){
		foreach ($new as $student){
			$details = $student->get_student_info();
			echo "<br/>".$details->full_name().akimbo_crm_student_permalink($details->student_id, "View");
		}
	}else{
		echo "<i>No new students</i>";
	}
	echo "<hr></details><details><summary>Students not returning: ".count($not_returning)."</summary>";
	if($not_returning){
		foreach ($not_returning as $student){
			$details = $student->get_student_info();
			echo "<br/>".$details->full_name().akimbo_crm_student_permalink($details->student_id, "View");
		}
	}else{
		echo "<i>No students not returning</i>";
	}
	echo "<hr></details>";
 }

 function akimbo_crm_get_students($age = NULL, $status = 'all', $semester_slug = NULL){//kids, all/current/not_returning
	global $wpdb;
	$students = $wpdb->get_col("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_firstname");
	$semester = akimbo_term_dates('return');//do this either way
	if($semester_slug == NULL){
		$semester_slug = $semester['slug'];
	}
	$past_slug = $semester['previous'];//akimbo_previous_semester($semester_slug);
	foreach($students as $student_id){
		$student = new Akimbo_Crm_Student($student_id);
		if($age != NULL){
			if($student->get_age() == $age){
				$list[] = $student;
			}
		}else{
			$list[] = $student;
		}
	}
	if($status != 'all' && $list != NULL){
		foreach($list as $listed_student){
			if($listed_student->attending_this_semester($semester_slug) == true){
				$current[] = $listed_student;
			}elseif($listed_student->attending_this_semester($past_slug) == true){
				$not_returning[] = $listed_student;
			}
		}			
	}
	if($status == 'current'){
		$list = $current;
	}elseif($status == "not_returning"){
		$list = $not_returning;
	}
	return $list;
}

 function akimbo_crm_business_details(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	//Month
	/*$crm_date = crm_date_setter_month($date);
	$start = date("Y-m-d-h:ia", strtotime($crm_date['start']));
	$end = date("Y-m-d-h:ia", strtotime($crm_date['end']));
	echo "<h2>Month Starting:  <small>".date("D jS M, Y", strtotime($start))."</small></h2>";
	$classes = crm_class_list($crm_date['start'], $crm_date['end'], 10, "return");*/
	
	//Week
	$crm_date = crm_date_setter_week($date);
	$week_start = date("Y-m-d-h:ia", strtotime($crm_date['week_start']));
	$week_end = date("Y-m-d-h:ia", strtotime($crm_date['week_end']));
	echo "<h2>Week Starting:  <small>".date("D jS M, Y", strtotime($week_start))."</small></h2>";
	
	crm_date_selector_permalinks("business");	
	
	echo "<table width='80%''><tr><th>Classes</th><th>Trainers</th><th>Students</th><th>In</th><th>Out</th><th>Total</th></tr>";
	$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$week_start' AND session_date <= '$week_end' ORDER BY session_date ASC");
	$i=0;
	$total = 0;
	$st_total = 0;
	$in_total = 0;
	$out_total = 0;
	$claire = 0;
	$adults = array('in' => 0,'out' => 0,'total' => 0);
	$youth = array('in' => 0,'out' => 0,'total' => 0);
	$junior = array('in' => 0,'out' => 0,'total' => 0);
	$playgroup = array('in' => 0,'out' => 0,'total' => 0);
	$parties = array('in' => 0,'out' => 0,'total' => 0);
	$private = array('in' => 0,'out' => 0,'total' => 0);
	$ages = array();
	foreach($classes as $class){
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE class_list_id = $class->list_id ");//students enrolled
		$trainers = 0;
		if($class->trainer_id >= 3){
			$trainers = $trainers +1;
		} else {
			if ($class->trainer_id >= 1){$claire = $claire +1;}
		}
		if($class->trainer2_id >= 3){
			$trainers = $trainers +1;
		} else {
			if ($class->trainer2_id >= 1){$claire = $claire +1;}
		}
		
		//rates
		$age = $class->age_slug;
		if(!in_array($age, $ages)){$ages[] = $age;}
		if($age == "adults"){
			$rate = 19;
			$wage = 30;
		} elseif($age == "playgroup") {
			$rate = 12;
			$wage = 30;
		}elseif($age == "private") {
			$rate = 30;
			$wage = 30;
		}else{
			$rate = 18;
			$wage = 30;
		}
		
		$trainer_wage = $trainers * $wage;
		$income = $count * $rate;
		$subtotal = $income - $trainer_wage;
		$$age[in] += $income;
		$$age[out] += $trainer_wage;
		$$age[total] += $subtotal;
		
		echo "<tr><td>".date("g:ia D jS M", strtotime($class->session_date)).": ";
		echo $class->class_admin_link($class->class_title)."</td><td>";
		echo $class->trainer_id.", ".$class->trainer2_id;
		echo "</td><td>".$count."</td><td>$";
		echo $income."</td><td>$".$trainer_wage."</td><td>$";
		echo $subtotal."</td></tr>";
		$total = $total + $subtotal;
		$st_total = $st_total + $count;
		$in_total = $in_total + $income;
		$out_total = $out_total + $trainer_wage;
		$i++;
	}

	echo "<tr><td colspan='2'>Totals</td><td>".$st_total."</td><td>$".$in_total."</td><td>$".$out_total."</td><td>$".$total."</td></tr>";
	echo "</table>";


	echo "<h2>Parties</h2>";
	echo "<table width='80%''><tr><th>Classes</th><th>Trainers</th><th>Original Order</th><th>Extra students</th><th>Out</th><th>Total</th></tr>";
	$bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_bookings WHERE book_ref >= '$week_start' AND book_ref <= '$week_end' ORDER BY book_ref ASC ");
	$book_in = 0;
	$book_out = 0;
	$book_total = 0;
	foreach ($bookings as $booking){
		$book_ref = $booking->book_ref;
		$booking_date = date("g:ia, l jS M", strtotime($book_ref));
		$product = wc_get_product($booking->book_product);
		$book_total += $product->get_price();
		echo "<tr><td>".$booking_date."</td><td>".$booking->book_trainer_id.", ".$booking->book_trainer2_id."</td><td>$".$product->get_price()."</td><td>0</td><td>$0</td><td></td></tr>";
		//echo "<br/><br/><br/>";
		//var_dump($product);
	}
	echo "</table>";

	echo "Weekly costs = rent, admin & Claire";

	echo "<br/><strong>Classes: ".$i."</strong>";
	echo "<br/><strong>Claire teaching: ".$claire."</strong>";

	$order_totals = apply_filters( 'woocommerce_reports_sales_overview_order_totals', $wpdb->get_row( "
	 
	SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts
	 
	LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
	 
	WHERE meta.meta_key = '_order_total'
	 
	AND posts.post_type = 'shop_order'

	AND post_date >= '$week_start' AND post_date <= '$week_end'
	 
	AND posts.post_status IN ( '" . implode( "','", array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) . "' )
	 
	" ) );
	//var_dump($order_totals);
	echo "<br/>Sales this week: $".$order_totals->total_sales;
	echo "<br/>Orders this week: ".$order_totals->total_orders;


	echo "<h2>Actual Usage</h2>";
	echo "<table width='80%''><tr><th>Category</th><th>In</th><th>Out</th><th>Total</th></tr>";
	sort($ages);//sort A-Z
	$sum = 0;
	foreach($ages as $display){
		echo "<tr><td>".ucfirst($display)."</td><td>$".$$display['in']."</td><td>$".$$display['out']."</td><td>$".$$display['total']."</td></tr>";
		$sum += $$display['total'];
	}
	echo "<tr><td>Parties</td><td></td><td></td><td>$".$book_total."</td></tr>";
	$sum += $book_total;
	echo "<tr><td colspan = '3'>Total</td><td>$".$sum."</td></tr>";
	echo "</table>";


	/*$order_totals = apply_filters( 'woocommerce_reports_sales_overview_order_totals', $wpdb->get_row( "
	 
	SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts
	 
	LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
	 
	WHERE meta.meta_key = '_order_total'
	 
	AND posts.post_type = 'shop_order'
	 
	AND posts.post_status IN ( '" . implode( "','", array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) . "' )
	 
	" ) );
	//var_dump($order_totals);
	echo "<br/>Total sales: $".$order_totals->total_sales;
	echo "<br/>Total orders: ".$order_totals->total_orders;



	$order_items = apply_filters( 'woocommerce_reports_top_earners_order_items', $wpdb->get_results( "

	SELECT order_item_meta_2.meta_value as product_id, SUM( order_item_meta.meta_value ) as line_total FROM {$wpdb->prefix}woocommerce_order_items as order_items

	LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id

	LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id

	LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID

	WHERE posts.post_type = 'shop_order'

	AND posts.post_status IN ( '" . implode( "','", array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) . "' )

	AND order_items.order_item_type = 'line_item'

	AND order_item_meta.meta_key = '_line_total'

	AND order_item_meta_2.meta_key = '_product_id'

	AND post_date >= '$week_start' AND post_date <= '$week_end'

	GROUP BY order_item_meta_2.meta_value

	" ));
	ksort($order_items);
	//var_dump($order_items);
	setlocale(LC_MONETARY, 'en_US.UTF-8');
	$item_total = 0;
	foreach ($order_items as $item){
		//echo $item->product_id." ".get_the_title( $item->product_id ).": ".money_format('%.2n', $item->line_total);
		echo $item->product_id." ".get_the_title( $item->product_id ).": ".$item->line_total;
		$item_total += $item->line_total;
		echo "<br/>";
	}
	echo $item_total;
	
	*/
	
	
	$classes = crm_class_list($crm_date['week_start'], $crm_date['week_end'], 10, "return");
	$ages = array();
	foreach ( $classes as $class_id ) {
		$class = new Akimbo_Crm_Class($class_id);//->list_id
		//$class_info = $class->get_class_info();
		$capacity = $class->capacity();
		$age = $class->age_slug();
		if(!in_array($age, $ages)){$ages[] = $age;}
		$$age['students'] += $capacity['count'];
		$$age['classes'] += 1;
		$$age['income'] += $class->class_income();
		$$age['cost'] += 3;
		
		/*echo "<tr><td>".$class->get_booking_date().": ".$class_info->class_title."</td><td>";
		echo $class->trainer_names()."</td><td>";
		$capacity = $class->capacity();//enrolments
		echo $capacity['count']."/".$capacity['capacity']."</td><td><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$class_id."'><i>View Class<i></a></tr>";*/
	}


	?>

Not sure quantity is calculating correctly for multiple kids & Active Kids vouchers aren't being included in total
<table width='80%''><tr><th>Class</th><th>Students</th><th>Classes</th><th>In</th><th>Out</th><th>Total</th></tr>
<?php 

foreach($ages as $product){
	$total = $$product['income'] - $$product['cost'];
	echo "<tr><td>".ucwords($product)."</td><td>".$$product['students']."</td><td>".$$product['classes']."</td><td>$".round($$product['income'], 2)."</td><td>$".round($$product['cost'], 2)."</td><td>$".round($total, 2)."</td></tr>";
	$week_profit +=$total;
}

?>



<tr><td>Adults</td><td></td><td></td><td></td><td></td><td></td></tr>
</table>
<br/>Totals don't currently include GST<br/>
- Weekly income from each type of class vs cost for each
- Total students, not including trials. Total trials separate 
- Extra bookings
new students this week: list of people to follow up. Booked Y/N
Minus total cost of running Akimbo
Profit margin for week/year/term

create function: for a given order number, calculate the price paid per session. Compare product id, and divide by number of sessions/weeks

Cashflow report. Quickbooks compared with Woocommerce<br/><br/>

<table><tr><td>Quarterly info</td></tr>
<tr><td>Term 1</td></tr>
<tr><td>Woocommerce Income</td></tr>
<tr><td>Quickbooks Invoices</td></tr>
<tr><td>Misc?</td></tr>
<tr><td>Payroll (expenses in red)</td></tr>
<tr><td>Rent</td></tr>
<tr><td>Misc</td></tr>

<tr><td>Total</td></tr>
</table>

	<?php

	echo "<br/>Weekly intake: ".$week_profit;
	echo "<br/>OTher expenses";
	
	
}