<?php //Akimbo CRM custom functions and shortcodes

/**
 * Delete a database row where the primary key is given
 */
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
	$result = $wpdb->delete( $table, array( $_POST['data_id'] => $_POST['data'],) );
	$message = ($result) ? "success" : "failure";
	$url = $_POST['redirect']."&message=".$message;
	wp_redirect( $url ); 
	exit;	
}

/**
 * Display dropdown of students or users. Uses GET to send information
 */
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

/***********************************************************************************************
 * 
 * Url & Permalink Functions
 * 
 ***********************************************************************************************/
function crm_nav_tab($page, $tab, $title, $active_tab){
	$url = "<a href='?page=".$page."&tab=".$tab."' ";
	$url .= $active_tab == $tab ? "class='nav-tab nav-tab-active'" : "class='nav-tab'";
	$url .= "'>".$title."</a>";
	return $url;
}

/**
 * Permalinks for front end
 */
function akimbo_crm_account_permalinks($permalink = NULL, $id = NULL, $text = NULL, $format = "link"){//classes, orders, students
	$url = get_permalink( get_option('woocommerce_myaccount_page_id') );
	switch($permalink){
		case "students":
			$url .= "/students/?";
	    	$args = array(
				'student' => $id,
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

/**
 * Simple permalink function for classes
 */
function akimbo_crm_class_permalink($class_id = NULL, $text = NULL){
	$format = ($text != NULL) ? "display" : "link";
	$args = ($class_id != NULL) ? array("class" => $class_id) : NULL;
	$url = akimbo_crm_permalinks("classes", $format, $text, $args);

	return $url;
}

/**
 * Simple permalink function for students
 */
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

/**
 * Permalinks for all pages of Akimbo CRM admin
 */
function akimbo_crm_permalinks($permalink, $format = "link", $text = NULL, $args = NULL){
	switch($permalink){
	    case "home": 
	    	$page = "akimbo-crm";
	    break;
	    case "classes":
			$page = "akimbo-crm";
			$tab = "classes";
	    break;
	    case "payroll":  
	    	$page = "akimbo-crm3";
	    	$tab = "payroll";
	    break;
	    case "bookings"://manage availabilities
			$page = "akimbo-crm2";
			$tab = "bookings"; 
	    break;
	    case "students":  
	    	$page = "akimbo-crm";
	    	$tab = "details";
		break;
		case "add student":  
	    	$page = "akimbo-crm";
			$tab = "details";
			$args = array("student" => "new");
		break;
		case "payments":
			$page = "akimbo-crm2";
			$tab = "payments";
		break;
		case "scheduling":  
	    	$page = "akimbo-crm2";
		break;
		case "staff":  
			$page = "akimbo-crm";
			$tab = "availabilities"; 
		break;
		case "troubleshooting":  
			$page = "akimbo-crm2";
			$tab = "enrolment"; 
		break;
		case "statistics":  
			$page = "akimbo-crm3";
			$tab = "statistics";
	    break;
	    default: 
	    	$page = 'akimbo-crm';
	}
	$url = get_site_url()."/wp-admin/admin.php?page=".$page;
	if(isset($tab)){
	 	$url .= "&tab=".$tab;
	}
	if ($args != NULL) {
		foreach($args as $key => $value){$url .= "&".$key."=".$value;}
	}	
	if($text != NULL || $format == "button"){
		$text = ($text == NULL) ? ucfirst($permalink) : $text;
		$url = ($format == "button") ? "<a href='".$url."'><button>".$text."</button></a>" : "<a href='".$url."'>".$text."</a>";
	}
	
	return $url;
}

/***********************************************************************************************
 * 
 * Date Functions
 * 
 ***********************************************************************************************/
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
	
	$header = NULL;
	if($previous != NULL){
		$header .= "<a href='".akimbo_crm_permalinks($page, "link", NULL, array("date" => $previous))."'>";
		$header .= "<input type='submit' value='<'></a> ";
	}
	$header .= $title;
	if($next != NULL){
		$header .= " <a href='".akimbo_crm_permalinks($page, "link", NULL, array("date" => $next))."'>";
		$header .= "<input type='submit' value='>'></a>";
	}
	
	return $header;
}

/**
 * Update date to scroll through data
 * Replace crm_date_selector, works with permalinks
 */
function crm_date_selector_permalinks($permalink, $display = "Change Date"){//update permalink function
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="permalink" value="<?php echo $permalink; ?>">
	<input type="hidden" name="action" value="crm_date_permalink_process">
	<input type="date" name="date"><input type="submit" value="<?php echo $display; ?>"></form><br/><?php
}

add_action( 'admin_post_crm_date_permalink_process', 'crm_date_permalink_process' );

function crm_date_permalink_process(){
	$args = array("date" => $_POST['date']);
	$url = akimbo_crm_permalinks($_POST['permalink'], "link", NULL, $args);
	wp_redirect( $url ); 
	exit;	
}

/**
 * Set start, end and important dates for months
 */
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

 /**
 * Set start, end and important dates for weeks
 */
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

/**
 * Return or echo semester information
 */
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

add_shortcode('termDates', 'akimbo_term_dates'); //[termDates]

/**
 * Display next semester information. Used as shortcode
 */
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

add_shortcode('nextSemester', 'akimbo_next_semester'); //[nextSemester]