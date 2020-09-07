<?php //Akimbo CRM staff management functions and shortcuts
/***********************************************************************************************
 * 
 * Admin area
 * 
 ***********************************************************************************************/
function akimbo_crm_update_staff_meta_form($staff_id){
	$staff = new Akimbo_Crm_Staff($staff_id);
	if($staff->is_staff()){//show employee details
		$trainer_meta = $staff->get_meta();
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

add_action( 'admin_post_crm_update_staff_meta', 'akimbo_crm_update_staff_meta_process' );

/**
 * Update user meta table with additional information about staff members
 */
function akimbo_crm_update_staff_meta_process(){
	global $wpdb;
	$user_id = $_POST['staff_id'];
	update_user_meta( $user_id, 'employee_type', $_POST['type']);
	update_user_meta( $user_id, 'employee_level', $_POST['level']);
	update_user_meta( $user_id, 'employee_threshold', $_POST['threshold']);
	update_user_meta( $user_id, 'employee_tfn', $_POST['tfn']);
	update_user_meta( $user_id, 'employee_keypay_id', $_POST['kpid']);
	update_user_meta( $user_id, 'wwccheck', $_POST['wwcc']);
	$url = akimbo_crm_permalinks("staff")."&message=success";
	wp_redirect( $url ); 
	exit;
}


/***********************************************************************************************
 * 
 * Rostering Functions
 * 
 ***********************************************************************************************/

function akimbo_crm_trainer_names(){//used in payroll class
	global $wpdb;
	$args = array('role__in' => array('shop_manager', 'author', 'administrator'),);
	$users = get_users( $args );
	foreach($users as $user){
		$trainers[$user->id] = $user->display_name;
	}
	return $trainers;
}

 /**
 * Return <select> field of all trainers
 */
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

/**
 * Use select field to update trainers for a given class_id
 */
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
	?><input type="hidden" name="class_type" value="class">
	<input type="hidden" name="url" value="<?php echo akimbo_crm_class_permalink($id); ?>">
	<input type="hidden" name="id" value="<?php echo $id; ?>">
	<input type="hidden" name="action" value="update_trainers">
	<input type='submit' value='Update Trainers'>
	</form><?php
}

/**
 * Update trainers in class_list table
 */
function crm_update_trainers(){
	global $wpdb;
	$trainers = array();
	if(isset($_POST['tr1'])){$trainers[] = $_POST['tr1'];}
	if(isset($_POST['tr2'])){$trainers[] = $_POST['tr2'];}
	$table = $wpdb->prefix.'crm_class_list';
	$where = array('list_id' => $_POST['id']);
	$data = array('trainers' => serialize($trainers));
	$result = $wpdb->update( $table, $data, $where);
	$message = ($result) ? "success" : "failure";
	$url = $_POST['url']."&message=".$message;
	wp_redirect( $url ); 
	exit;
}

add_action( 'admin_post_update_trainers', 'crm_update_trainers' );

/**
 * Update availability form, used for bookings
 */
function akimbo_crm_update_trainer_availabilities($user_id = NULL){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_month($date);
	$header = crm_date_selector_header("staff", $date, $period = "month");
	echo apply_filters('akimbo_crm_manage_availabilities_header', $header);
	crm_date_selector_permalinks("staff");
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

/**
 * Update availability form process
 */
function crm_update_book_trainer_availability_process(){
	global $wpdb;
	$table = $wpdb->prefix.'availability';
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
	$path = akimbo_crm_permalinks("staff");
	$url = (isset($_POST['date'])) ? $path."&date=".$_POST['date'] : $path;
	wp_redirect( $url );
	exit;
}

/***********************************************************************************************
 * 
 * Payroll Functions
 * 
 ***********************************************************************************************/

 /**
 * Display Payroll information
 */
function akimbo_crm_manage_payroll(){
	global $wpdb;
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	echo "<h4>Payslip period: ".date("l jS M", strtotime($crm_date['last_week_start']))." - ".date("l jS M", strtotime($crm_date['last_week_end']));
	$pay_day = strtolower(get_option("akimbo_crm_pay_day"))." this week";
	echo "<br/>Payment date: ".date("l jS M", strtotime($pay_day, strtotime($date)))."</h4>";
	crm_date_selector_permalinks("payroll");
	$payroll = new Akimbo_Crm_Payroll($crm_date['last_week_start'], $crm_date['last_week_end']);
	$payroll->display_items();
	echo "<br/><hr>";
	if(current_user_can('manage_options')){
		export_payroll_csv_file($payroll->get_items());
		echo "<br/><br/><a href='https://quickbooks.intuit.com/au/quickbooks-login/' target='_blank'><button>Log In to Quickbooks</button></a>";
	}
}
/**
 * Create csv file to export payroll information. COntains custom code specific to Circus Akimbo
 */
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
			if(is_array($line_trainers)){
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
		}
	}else{
		$trainers = "NO ITEMS";
	}

    fclose($outstream); 
    echo "<br/><a href='".$path['url'].$filename."'>Download Shifts</a>";  //make a link to the file so the user can download.
}

/**
 * Possibly outdated, not currently in use
 */
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
		$result = $wpdb->update( $table, $data, $where);
	}else{
		$result = $wpdb->insert($table, $data);
	}
	$message = ($result) ? "&message=success" : "failure";
	$url = (isset($_POST['redirect'])) ? $_POST['redirect'] : akimbo_crm_permalinks("staff");
	wp_redirect( $url ); 
	exit;	
}

add_action( 'admin_post_crm_add_to_timesheet', 'crm_add_to_timesheet' );