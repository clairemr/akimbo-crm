<?php
/**
 *
 * Akimbo CRM staff management functions and shortcuts
 * 
 */

/*************
Reference list
**************
crm_update_trainer_dropdown($type, $id, $trainers, $include = NULL)// "class"/"booking", class_id/book_ref, array of trainers
	*action: crm_update_trainers()
crm_trainer_dropdown_select($name, $current = NULL, $exclude = NULL, $include = NULL)//replaces crm_trainer_dropdown_list in 2.1 

*/

add_action( 'admin_post_crm_add_new_payslip_data', 'crm_add_new_payslip_data' );
add_action( 'admin_post_crm_email_trainer_payslip', 'crm_email_trainer_payslip' );
add_action( 'admin_post_crm_email_trainer_roster_edit', 'crm_email_trainer_roster_edit' );
add_action( 'admin_post_crm_add_to_timesheet', 'crm_add_to_timesheet' );
add_action( 'admin_post_update_trainers', 'crm_update_trainers' );
//add_action( 'admin_post_update_party_trainers', 'crm_update_party_trainers' );
add_action( 'admin_post_crm_payroll_class_invoices', 'crm_payroll_class_invoices' );
add_action( 'admin_post_crm_payroll_add_invoices', 'crm_payroll_add_invoices' );
add_action( 'admin_post_crm_payroll_invoices_paid', 'crm_payroll_invoices_paid' );
add_action( 'admin_post_crm_update_staff_meta', 'crm_update_staff_meta' );


function crm_update_trainer_dropdown($type, $id, $trainers, $include = NULL){//type,$trainers passed as array
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">Trainers: <?php
	if($trainers != NULL){
		$i=1;
		foreach($trainers as $trainer){
			$name = "tr".$i;
			crm_trainer_dropdown_select($name, $trainer, $trainers, $include);
			$i++;
		}?><input type="hidden" name="count" value="<?php echo $i; ?>"><?php	
	}else{
		crm_trainer_dropdown_select("tr1");
		crm_trainer_dropdown_select("tr2");
	}	
	if($type == "class"){
		?><input type="hidden" name="class_type" value="class">
		<input type="hidden" name="url" value="/wp-admin/admin.php?page=akimbo-crm2&class=<?php echo $id; ?>"><?php
	}elseif($type == "booking"){
		?><input type="hidden" name="class_type" value="booking"><?php 
		?><input type="hidden" name="url" value="/wp-admin/admin.php?page=akimbo-crm4&tab=booking_details&booking=<?php echo $id; ?>"><?php
	}
	?><input type="hidden" name="id" value="<?php echo $id; ?>">
	<input type="hidden" name="action" value="update_trainers">
	<input type='submit' value='Update Trainers'>
	</form><?php
}

function crm_trainer_dropdown_select($name, $current = NULL, $exclude = NULL, $include = NULL){
	global $wpdb;
	echo "<select name='".$name."''>";
		if($current != NULL){
			$name = ($current >=1) ? $wpdb->get_var("SELECT display_name FROM {$wpdb->prefix}users WHERE ID = '$current'") : "No trainer";
			echo "<option value='".$current."'>".$name."</option><option value=''>*****</option>";
		}
		echo "<option value='0'>No trainer</option>";
		$exclude = ($exclude != NULL) ? $exclude : array();
		$include = ($include != NULL) ? $include : NULL;
		$args = array('role__in' => array('shop_manager', 'author', 'administrator'),);
		$users = get_users( $args );
		foreach ($users as $user) {
			if($include != NULL && !in_array($user->ID, $include)){
			}elseif(!in_array($user->ID, $exclude)){
				echo '<option value="' .$user->ID .'">' .$user->display_name .'</option>';
			}
		}	
	echo "</select>";
}

function crm_update_trainers(){
	global $wpdb;
	$trainers = array();
	if(isset($_POST['tr1'])){$trainers[] = $_POST['tr1'];}
	if(isset($_POST['tr2'])){$trainers[] = $_POST['tr2'];}
	if($_POST['class_type'] == "class"){
		$table = $wpdb->prefix.'crm_class_list';
		$where = array('list_id' => $_POST['id']);
		$data = array('trainers' => serialize($trainers));
	}elseif($_POST['class_type'] == "booking"){
		$table = $wpdb->prefix.'crm_booking_meta';
		$where = array('meta_key' => 'trainers', 'avail_id' => $_POST['id']);
		$data = array('meta_value' => serialize($trainers));
		//Add to roster?
	}else{
		wp_redirect( get_site_url() ); 
		exit;
	}

	$result = $wpdb->update( $table, $data, $where);
	$message = ($result) ? "success" : "failure";
	$url = get_site_url().$_POST['url']."&message=".$message;
	wp_redirect( $url ); 
	exit;
}



function akimbo_crm_manage_payroll(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	echo "<h4>Payslip period: ".date("l jS M", strtotime($crm_date['last_week_start']))." - ".date("l jS M", strtotime($crm_date['last_week_end']));
	$pay_day = strtolower(get_option("akimbo_crm_pay_day"))." this week";
	echo "<br/>Payment date: ".date("l jS M", strtotime($pay_day, strtotime($date)))."</h4>";
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

//Removed in 2.1
/*function akimbo_crm_roster(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	echo "<h4>Week Starting: ".date("D jS M, Y", strtotime($crm_date['week_start']))."</h4>";
	crm_date_selector("akimbo-crm", "roster");

	$payroll = new Akimbo_Crm_Payroll($crm_date['week_start'], $crm_date['week_end']);
	$payroll->display_items();
	$message = "<br/><hr><br/>Can't make a rostered shift? You can try to swap it in the trainer <a href='https://www.facebook.com/groups/863632663663310/'>Facebook group</a> or contact another staff member directly.<br/><hr><br/>";
	echo apply_filters('akimbo_crm_manage_roster_message', $message);
	if(current_user_can('manage_options')){
		crm_roster_update();//add shifts
		crm_roster_edit_button();//send update email
		echo "<br/><hr><br/>";
	}

}*/


function akimbo_crm_update_trainer_availabilities($user_id = NULL){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_month($date);
	$header = "<h2><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=availabilities&date=".$crm_date['previous_month']."'><input type='submit' value='<'></a> Availabilities: ".$crm_date['month']." <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=availabilities&date=".$crm_date['next_month']."'><input type='submit' value='>'></a></h2>";
	echo apply_filters('akimbo_crm_manage_availabilities_header', $header);
	//echo "<h4>Month Starting: ".date("D jS M, Y", strtotime($crm_date['start']))."</h4>";
	crm_date_selector("akimbo-crm", 'availabilities');
	$start = $crm_date['start'];
	$end = $crm_date['end'];

	echo "<h4>Update Availabilities</h4>";
	$trainer_availabilities = $wpdb->get_results("SELECT avail_id FROM {$wpdb->prefix}crm_availability WHERE session_date >= '$start' AND session_date <= '$end' ORDER BY session_date");
	
	if(!$trainer_availabilities){
		echo "No slots found for that date";
	}else{
		$user_id = ($user_id != NULL) ? $user_id : get_current_user_id();
		$i=0;
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
		echo "<b> Y / N </b><br/>";
		foreach($trainer_availabilities as $available){
			$slot = new Akimbo_Crm_Availability($available->avail_id);
			$i++;
			echo "<input type='checkbox' name='".$i."a'' value='".$available->avail_id."' ";
			if($slot->get_availabilities() != NULL){
				if(in_array($user_id,$slot->get_availabilities())){//if user has marked themselves available
					echo "checked> <input type='checkbox' name='".$i."b'' value='".$available->avail_id."'> ";
				}else{//if user has marked themselves unavailable
					echo"> <input type='checkbox' name='".$i."b'' value='".$available->avail_id."' checked> ";
				}
			}else{//defaults to unavailable if user has not specified
				echo"> <input type='checkbox' name='".$i."b'' value='".$available->avail_id."' checked> ";
			}
			echo $slot->get_booking_date("g:ia jS M").": ";
			foreach($slot->get_products() as $product){echo get_the_title($product).", ";}
			echo "<br/>";
		}
		echo "<input type='hidden' name='count' value='".$i."'>";
		echo "<input type='hidden' name='trainer' value='".$user_id."'>";
		if(isset($_GET['date'])){echo "<input type='hidden' name='date' value='".$_GET['date']."'>";}
		echo "<input type='hidden' name='action' value='crm_update_book_trainer_availability'>";
		echo "<br/><input type='submit'></form>";
	}
}

function crm_update_book_trainer_availability_process(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_booking_meta';
	$i = $_POST['count'];
	$trainer = $_POST['trainer'];
	for($x = 1; $x <= $i; $x++){
		$value = $x."a";//value set to available
		if(isset($_POST[$value])){
			$slot = new Akimbo_Crm_Availability($_POST[$value]);
			$availabilities = ($slot->get_availabilities() != NULL) ? $slot->get_availabilities() : array();
			$where = array('avail_id' => $_POST[$value],'meta_key' => "availabilities",);
			if(!in_array($_POST['trainer'],$availabilities)){$availabilities[] = $_POST['trainer'];	}
			$data = array(
				'avail_id' => $_POST[$value],
				'meta_key' => 'availabilities',
				'meta_value' => serialize($availabilities)
			);
			$result = $wpdb->update($table, $data, $where);
		}else{//delete trainer from availability array
			$value = $x."b";
			$slot = new Akimbo_Crm_Availability($_POST[$value]);
			$availabilities = $slot->get_availabilities();
			if($availabilities != NULL){//no need to delete if array doesn't exist
				$arr = array_diff($availabilities, array($trainer));
				$data = array(
					'avail_id' => $_POST[$value],
					'meta_key' => 'availabilities',
					'meta_value' => serialize($arr)
				);
				$where = array('avail_id' => $_POST[$value],'meta_key' => "availabilities",);
				$result = $wpdb->update($table, $data, $where);
			}else{$result = "success";}
		}
	}
	$path = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=availabilities";
	$url = (isset($_POST['date'])) ? $path."&date=".$_POST['date'] : $path;
	wp_redirect( $url );
	exit;
}

function akimbo_crm_trainer_names(){
	global $wpdb;
	$args = array('role__in' => array('shop_manager', 'author', 'administrator'),);
	$users = get_users( $args );
	foreach($users as $user){
		$trainers[$user->id] = $user->display_name;
	}

	return $trainers;
}

/**
 *
 * Admin area
 * 
 */
function akimbo_crm_staff_details(){
	global $wpdb;
	$args = array('role__in' => array('shop_manager', 'author', 'administrator'),'orderby' => 'display_name', 'order' => 'ASC',);
	$users = get_users( $args );
	if(isset($_GET['message'])){ ?><div class="updated notice is-dismissible"><p>Updates successful!</p></div><?php }
	$message = "<br/><hr><br/>Can't make a rostered shift? You can try to swap it in the trainer <a href='https://www.facebook.com/groups/863632663663310/'>Facebook group</a> or contact another staff member directly.<br/><hr><br/>";
	echo apply_filters('akimbo_crm_manage_roster_message', $message);
	if(current_user_can('manage_options')){
		crm_roster_update();//add shifts
		crm_roster_edit_button();//send update email
		echo "<br/><hr><br/>";
	}
	/**
	 * Staff details
	 */
	echo "<table width='80%'><tr bgcolor = '#33ccff'><td>ID</td><td>Name</td><td>Email</td><td colspan='2'></td></tr>";
	foreach ($users as $user){ echo "<tr><td>".$user->ID."</td><td>".$user->display_name."</td><td>".esc_html( $user->user_email )."</td>";
	if(current_user_can('manage_options')){echo "<td><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=roster&staff_id=".$user->ID."'>Employee Details</a></td>";}
	echo "<td><a href='".get_site_url()."/wp-admin/user-edit.php?user_id=6'>View User</a></td></tr>"; 
		$emails[] = esc_html( $user->user_email );
		if(isset($_GET['staff_id'])){
			$staff_id = $_GET['staff_id'];
			$staff_member = $user->ID;
			if($staff_id == $staff_member){//show employee details
				$trainer_meta = get_user_meta($staff_id);
				if(!isset($trainer_meta['employee_level'][0])){add_user_meta( $staff_id, "employee_level", "", true);}
				if(!isset($trainer_meta['employee_type'][0])){add_user_meta( $staff_id, "employee_type", "", true);}
				if(!isset($trainer_meta['employee_threshold'][0])){add_user_meta( $staff_id, "employee_threshold", "", true);}
				if(!isset($trainer_meta['employee_tfn'][0])){add_user_meta( $staff_id, "employee_tfn", "", true);}
				if(!isset($trainer_meta['employee_keypay_id'][0])){add_user_meta( $staff_id, "employee_keypay_id", "", true);}
				if(!isset($trainer_meta['wwccheck'][0])){add_user_meta( $staff_id, "wwccheck", "", true);}
				?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
				<tr><td colspan='2'></td><td>User level</td><td  colspan='2'><input type='text' name='level' value='<?php echo $trainer_meta['employee_level'][0] ?>'></td></tr>
				<tr><td colspan='2'></td><td>User type</td><td  colspan='2'><input type='text' name='type' value='<?php echo $trainer_meta['employee_type'][0] ?>'></td></tr>
				<tr><td colspan='2'></td><td>Tax free threshold</td><td colspan='2'><input type='text' name='threshold' value='<?php echo $trainer_meta['employee_threshold'][0] ?>'></td></tr>
				<tr><td colspan='2'></td><td>TFN</td><td  colspan='2'><input type='text' name='tfn' value='<?php echo $trainer_meta['employee_tfn'][0] ?>'></td></tr>
				<tr><td colspan='2'></td><td>Keypay ID</td><td  colspan='2'><input type='text' name='kpid' value='<?php echo $trainer_meta['employee_keypay_id'][0] ?>'></td></tr>
				<tr><td colspan='2'></td><td>Working With Children Check</td><td  colspan='2'><input type='text' name='wwcc' value='<?php echo $trainer_meta['wwccheck'][0] ?>'></td></tr>
				<input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
				<input type="hidden" name="action" value="crm_update_staff_meta">
				<tr><td colspan='3'></td><td  colspan='2'><input type='submit' value='Update'> </td></tr>
				</form> <?php
			}
		}
	}
	echo "</table>";

	echo "<br/><b>Emails: </b>";
	foreach ($emails as $email){echo $email.", ";}
}
/**
 * End admin Area
 */




function crm_update_staff_meta(){
	global $wpdb;
	$user_id = $_POST['staff_id'];
	$type = $_POST['type'];
	update_user_meta( $user_id, 'employee_type', $type);
	$level = $_POST['level'];
	update_user_meta( $user_id, 'employee_level', $level);
	$threshold = $_POST['threshold'];
	update_user_meta( $user_id, 'employee_threshold', $threshold);
	$tfn = $_POST['tfn'];
	update_user_meta( $user_id, 'employee_tfn', $tfn);
	$kpid = $_POST['kpid'];
	update_user_meta( $user_id, 'employee_keypay_id', $kpid);
	$wwcc = $_POST['wwcc'];
	update_user_meta( $user_id, 'wwccheck', $wwcc);
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=staff&staff_id=".$user_id."&message=success";
	wp_redirect( $url ); 
	exit;
}


function crm_roster_update(){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<td><select name="type"><option value="admin">Admin</option><option value="party">Party</option><option value="setup">Setup</option><option value="workshop">Workshop</option></select>
		</td><td><?php crm_trainer_dropdown_select("trainer"); ?>
		</td><td> Start:<input name="start_time" type="datetime-local">
		</td><td><input name="duration" type="number" value="60"> mins
		</td><td><input name="location" type="text" value="Circus Akimbo - Hornsby">
		<input type="hidden" name="action" value="crm_add_to_timesheet">
		</td><td><button>Add To Roster</button></td></tr></table>
		</form></table>
	<?php
}

function crm_add_to_timesheet(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_roster';
	$data = array(
		'start_time' => $_POST['start_time'],
		'trainer_id' => $_POST['trainer'],
		'duration' => $_POST['duration'],
		'location' => $_POST['location'],
		'shift_type' => $_POST['type'],
		);
	if(isset($_POST['roster_id'])){
		$where = array('roster_id' => $_POST['roster_id'],);
		$wpdb->update( $table, $data, $where);
	}else{
		$wpdb->insert($table, $data);
	}
	
	$admin_notice = "success";
	if(isset($_POST['redirect'])){
		$url = get_site_url().$_POST['redirect'];
	} else{$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=roster";}
	wp_redirect( $url ); 
	exit;	
}

function crm_add_new_payslip_data(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_payroll';

	$data = array(
		'payrun_id' => $_POST['payrun_id'],
		'trainer' => $_POST['trainer'],
		'type' => "wage",
		'amount' => $_POST['wage'],
		);
	$wpdb->insert($table, $data);
	
	$data = array(
		'payrun_id' => $_POST['payrun_id'],
		'trainer' => $_POST['trainer'],
		'type' => "payg",
		'amount' => $_POST['payg'],
		);
	$wpdb->insert($table, $data);
	
	$data = array(
		'payrun_id' => $_POST['payrun_id'],
		'trainer' => $_POST['trainer'],
		'type' => "super",
		'amount' => $_POST['super'],
		);
	$wpdb->insert($table, $data);
	
	$admin_notice = "success";
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=payroll&date=".$_POST['date'];
	wp_redirect( $url ); 
	exit;	
}

function crm_email_trainer_payslip(){
	global $wpdb;
	//$userid = $_POST['user']; 
	//$userdata = get_userdata($userid);
        $to = $_POST['email'];
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=payroll&date=".$_POST['date'];
	
	//$to = "claire@circusakimbo.com.au";//array or comma separated list of emails
	$subject = "New payslip";
	$headers [] = 'From: Circus Akimbo <info@circusakimbo.com.au>';
	$headers [] = 'Content-Type: text/html; charset=UTF-8';
	$message="Hello!";
	$message .="<br/>Your pay has just been sent out!";
	$message .="<br/>Please visit <a href='".$url."'>Akimbo CRM</a> to view your payslip and let me know asap if there are any errors.";
	$message .="<br/>Cheers, <br/>Claire";
	$sent = wp_mail($to, $subject, $message, $headers);
	if($sent) {
		echo "Success";
		wp_redirect( $url ); 
		exit;
	}//message sent!
	else  {
		echo "Something went wrong! Please contact an administrator so we can get it working properly again.";
		echo "<br/>User: ".$user_id;
		var_dump($user_info);
		echo "<br/>Sent: ".$sent;
		echo "<br/>To: ".$to;
		echo "<br/>Subject: ".$subject;
		echo "<br/>Message: ".$message;
		echo "<br/>Headers: ".var_dump($headers);
	}
}

function crm_roster_edit_button(){//send email
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	Notify roster change: <?php crm_trainer_dropdown_select("trainer"); ?>
	Week starting <input type="text" name="date" placeholder="e.g. 2019-04-29">
	<input type="hidden" name="action" value="crm_email_trainer_roster_edit">
	<input type="submit" value="Send email"></form><?php
}

function crm_email_trainer_roster_edit(){
	global $wpdb;

        $trainer = $_POST['trainer'];
        $userdata = get_userdata($trainer);
	$to = $userdata->user_email;
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=roster&date=".$_POST['date'];
	
	$subject = "Roster edit";
	$headers [] = 'From: Circus Akimbo <info@circusakimbo.com.au>';
	$headers [] = 'Content-Type: text/html; charset=UTF-8';
	$message="Hello!";
	$message .="<br/>There's been a change to your roster.";
	$message .="<br/>Please visit <a href='".$url."'>Akimbo CRM</a> to check your roster and let me know asap if there are any issues. If you can't make a shift you can use the <a href='https://www.facebook.com/groups/863632663663310/'>Facebook group</a> to swap shifts. Please reply to this email to confirm you've seen it.";
	$message .="<br/>Cheers, <br/>Eloise";
	$sent = wp_mail($to, $subject, $message, $headers);
	if($sent) {
		echo "Success";
		wp_redirect( $url ); 
		exit;
	}//message sent!
	else  {
		echo "Something went wrong! Please contact an administrator so we can get it working properly again.";
		echo "<br/>User: ".$user_id;
		var_dump($user_info);
		echo "<br/>Sent: ".$sent;
		echo "<br/>To: ".$to;
		echo "<br/>Subject: ".$subject;
		echo "<br/>Message: ".$message;
		echo "<br/>Headers: ".var_dump($headers);
	}
}



function export_payroll_csv_file($items) {
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');
	$payment_date = date("Y-m-d", strtotime('thursday this week', strtotime($date)));
	
	header('Pragma: no-cache');//Do not cache file, otherwise updates won't work
	header('Expires: 0');
	$path = wp_upload_dir();   // or where ever you want the file to go
	$filename = "/payroll-".$payment_date.".csv";
   	$outstream = fopen($path['path'].$filename, "w");  // the file name you choose
    	$fields = array('date', 'startTime', 'endTime', 'employeeID', 'payCategoryExternalID');  // the information you want in the csv file
	fputcsv($outstream, $fields);  //creates the first line in the csv file
	
	$values = array();    // initialize the array
	if($items != NULL){
		foreach($items as $item){
			$trainers[] = $item->trainers;
			$line_trainers = (isset($item->trainers)) ? unserialize($item->trainers) : unserialize($item->meta_value);
			$i = 1;	
			foreach($line_trainers as $trainer){
				if($trainer >= 3){//exclude Claire
					$duration = $item->duration;
					$date = date("D d M Y", strtotime($item->session_date));
					$start = date("g:ia", strtotime($item->session_date));
					$day = date("D", strtotime($item->session_date));
					$type = ($day == 'Sat' || $day == 'Sun') ? '200' : '100';
					if(!isset($item->class_title)){//"Booking";
						$duration = ($i == 1) ? $duration + 60 : $duration + 30;//add set up. 60mins senior, 30 mins junior
						$start = ($i == 1) ? date("g:ia", strtotime('-30mins', strtotime($start))) : date("g:ia", strtotime('-15mins', strtotime($start)));
					}
					$increment = "+ ".$duration." minutes";
					$end = date("g:ia", strtotime($increment, strtotime($start)));
					$keypay_id = get_user_meta( $trainer, $key = 'employee_keypay_id', $single = true );
					$info = array($date, $start, $end, $keypay_id, $type);
					fputcsv($outstream, $info);
				}
				$i++;
			}
		}
	}else{
		$trainers = "NO ITEMS";
	}

    fclose($outstream); 
    echo "<br/><a href='".$path['url'].$filename."'>Download Shifts</a>";  //make a link to the file so the user can download.
}

function crm_payroll_add_invoices(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_trainer_invoices';
	$time = current_time('mysql', false);
	
	$data = array(
		'trinv_no' => $_POST['trinv_no'],
		'trinv_date' => $time,
		'trinv_trainer' => $_POST['trainer'],
		'trinv_amount' => $_POST['amount'],
		'trinv_paid' => '0',
		);
	$wpdb->insert($table, $data);
	
	$admin_notice = "success";
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm&tab=invoices";
	wp_redirect( $url ); 
	exit;
}

function crm_payroll_invoices_paid(){
	global $wpdb;
	$time = current_time('mysql', false);
	
	$table = $wpdb->prefix.'crm_trainer_invoices';
	$where = array('trinv_no' => $_POST['invoice'],);
	$data = array('trinv_paid' => $time);
	$wpdb->update( $table, $data, $where);
	
	$admin_notice = "success";
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm&tab=invoices";
	wp_redirect( $url ); 
	exit;
}

function crm_payroll_class_invoices(){
	//update crm_class_list and crm_bookings
	global $wpdb;
		if($_POST['newinvoice']){
		$inv_no = $_POST['newinvoice'];
	} else{
		$inv_no = $_POST['selectinvoice'];
	}
	
	if($_POST['class']){
		$table = $wpdb->prefix.'crm_class_list';
		if($_POST['trainer1']){
			$where = array('list_id' => $_POST['class'],'trainer_id' => $_POST['trainer1'],);
			$data = array('trainer_inv' => $inv_no);
			$wpdb->update( $table, $data, $where);
		} elseif ($_POST['trainer2']) {
			$where = array('list_id' => $_POST['class'],'trainer2_id' => $_POST['trainer2'],);
			$data = array('trainer2_inv' => $inv_no);
			$wpdb->update( $table, $data, $where);
		} else {}
	} elseif ($_POST['booking']){
		$table = $wpdb->prefix.'crm_bookings';
		if($_POST['trainer1']){
			$where = array('book_id' => $_POST['booking'],'book_trainer_id' => $_POST['trainer1'],);
			$data = array('book_trinv' => $inv_no);
			$wpdb->update( $table, $data, $where);
		} elseif ($_POST['trainer2']) {
			$where = array('book_id' => $_POST['booking'],'book_trainer2_id' => $_POST['trainer2'],);
			$data = array('book_tr2inv' => $inv_no);
			$wpdb->update( $table, $data, $where);
		} else {}
	} else{}
	$admin_notice = "success";
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm&tab=invoices";
	wp_redirect( $url ); 
	exit;
}