<?php 

/**
 *
 * Akimbo CRM admin area content and functions
 * 
 */

/*************
Reference list
**************
dashboard
 * crm_add_dashboard_widgets()
 * crm_dashboard_widget_function()
akimbo-crm
 * akimbo_crm_admin_home_page()

*/

add_action( 'wp_dashboard_setup', 'crm_add_dashboard_widgets' );

/**
 * Add a widget to the dashboard. Hooked into the 'wp_dashboard_setup' action above.
 */
function crm_add_dashboard_widgets() {
	wp_add_dashboard_widget(
     'crm_dashboard_widget',         // Widget slug.
     'Akimbo CRM',         // Title.
     'crm_dashboard_widget_function' // Display function.
    );	
}

/**
 * Output the contents of the Dashboard Widget.
 */
function crm_dashboard_widget_function() {
	echo "<h2>Upcoming Classes</h2>";
	$date = current_time('Y-m-d');
	crm_class_list(); 
	if(current_user_can('manage_options')){
		echo "<hr><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2'><button>Manage Classes</button></a>";
		echo " <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4'><button>Manage Bookings</button></a>";
		echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details'><button>Student Info</button></a>";
		echo " <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm3&tab=payroll'><button>Payroll</button></a>";
	}
}

function akimbo_crm_admin_home_page(){
	echo "<h1>Circus Akimbo Staff Portal</h1>";
	echo "<h2>Upcoming Classes</h2>";
	global $wpdb;
	$today = current_time('Y-m-d');

	crm_class_list($today);
	$path = get_site_url()."/wp-admin/admin.php?page=akimbo-crm";
	echo "<h2><a href='".$path."&tab=details'><button>Student Details</button></a> <a href='".$path."&tab=roster'><button>View Roster</button></a> <a href='".$path."&tab=availabilities'><button>Update Availabilities</button></a></h2>";

	if (current_user_can('manage_woocommerce')){
		echo "<h2><a href='".$path."2'><button>Classes</button></a> <a href='".$path."2&tab=schedule'><button>Add Class</button></a> <a href='".$path."4'><button>Bookings</button></a> <a href='".$path."3&tab=payroll'> <button>Payroll</button></a></h2>";
	}
	/*if (current_user_can('manage_woocommerce')){
		echo "<a href='https://www.circusakimbo.com.au/wp-admin/post-new.php?post_type=shop_order'><button>Add Order</button></a> 
		<button>Run payroll report</button> <button>Export sales</button> <button>View P&L</button>";
	}	*/ 
}

function akimbo_crm_unpaid_students(){
	global $wpdb;
	echo "<h2>Unpaid Students</h2>";
	echo "<i>Orders listed as 'Not Available for Use' don't have weeks or sessions available. If there are no available pricing options listed, check the student account for duplicate classes and search <a href='".get_site_url()."/wp-admin/edit.php?post_type=shop_order'>user orders</a> in case something is missing. If there's nothing relevant, create a new order and email it to the client. If it's been a while, send an explanatory email first so they know why they're getting an invoice out of the blue!<br/>If Circus or Claire is showing as the managing user, <a href=''>View Student</a> and update the user to update the database <br></i>";

	$unpaid_students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id WHERE ord_id = '' ORDER BY {$wpdb->prefix}crm_class_list.session_date DESC");
	foreach($unpaid_students as $unpaid_student){
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
		echo "<br/><b>".$unpaid_student->student_name."</b> ".$unpaid_student->class_title.", ".date("g:ia, l jS M Y", strtotime($unpaid_student->session_date));
		?><input type="hidden" name="att_id" value="<?php echo $unpaid_student->attendance_id;?>">
		<?php crm_select_available_user_orders($unpaid_student->user_id);?>
		<input type="hidden" name="student_id" value="<?php echo $unpaid_student->student_id;?>">
		<input type="hidden" name="class_id" value="<?php echo $unpaid_student->class_list_id;?>">
		<input type="hidden" name="referral_url" value="/wp-admin/admin.php?page=akimbo-crm3&tab=payments">
		<input type="hidden" name="action" value="admin_assign_order_id">
		<input type='submit' value='Update'>  <i><a href="<?php echo get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$unpaid_student->student_id; ?>">View Student</a></i>
		</form><?php
		
		$user = $unpaid_student->user_id;
		$user_info = get_userdata($user);
		echo "<i>User: ". $user_info->first_name." ".$user_info->last_name . ", ".$user_info->user_email."</i>";
	}
}

function akimbo_crm_manage_payroll(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	echo "<h4>Payslip period: ".date("l jS M", strtotime($crm_date['week_start']))." - ".date("l jS M", strtotime($crm_date['week_end']));
	echo "<br/>Payment date: ".date("l jS M", strtotime('thursday next week', strtotime($date)))."</h4>";
	crm_date_selector("akimbo-crm3", "payroll");

	$payroll = new Akimbo_Crm_Payroll($crm_date['week_start'], $crm_date['week_end']);
	$payroll->display_items();
	echo "<br/><hr>";
	if(current_user_can('manage_options')){
		//var_dump($payroll->get_items());
		export_payroll_csv_file($payroll->get_items());
		echo "<br/><br/><a href='https://quickbooks.intuit.com/au/quickbooks-login/' target='_blank'><button>Log In to Quickbooks</button></a>";
	}
}

function akimbo_crm_roster(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	echo "<h4>Week Starting: ".date("D jS M, Y", strtotime($crm_date['week_start']))."</h4>";
	crm_date_selector("akimbo-crm", "roster");

	$payroll = new Akimbo_Crm_Payroll($crm_date['week_start'], $crm_date['week_end']);
	$payroll->display_items();
	echo "<br/><hr><br/>Can't make a rostered shift? You can try to swap it in the trainer <a href='https://www.facebook.com/groups/863632663663310/'>Facebook group</a> or contact another staff member directly.";
	echo "test";
	if(current_user_can('manage_options')){crm_roster_edit_button();}
}

function akimbo_crm_enrolment_issues(){
	global $wpdb;
	global $post;
	echo "<h2>Fix enrolment issues</h2>";

	if(isset($_GET['item_id'])){crm_update_weeks_or_sessions($_GET['item_id']);}

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
				echo "</td><td><a href='".$site."/wp-admin/post.php?post=".$class->ID."&action=edit'>".$class->ID."</a></td>";
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
	
	
	crm_date_selector("akimbo-crm3", "business");
	
	
	echo "<table width='80%''><tr bgcolor = '#33ccff'><th>Classes</th><th>Trainers</th><th>Students</th><th>In</th><th>Out</th><th>Total</th></tr>";
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
		
	echo "<tr><td>".date("g:ia D jS M", strtotime($class->session_date)).": <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$class->list_id."'>".$class->class_title."</a></td><td>";
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
	echo "<table width='80%''><tr bgcolor = '#33ccff'><th>Classes</th><th>Trainers</th><th>Original Order</th><th>Extra students</th><th>Out</th><th>Total</th></tr>";
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
	echo "<table width='80%''><tr bgcolor = '#33ccff'><th>Category</th><th>In</th><th>Out</th><th>Total</th></tr>";
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
<table width='80%''><tr bgcolor = '#33ccff'><th>Class</th><th>Students</th><th>Classes</th><th>In</th><th>Out</th><th>Total</th></tr>
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

<table border='1'>Quarterly info</td></tr>
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