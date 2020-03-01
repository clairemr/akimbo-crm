<?php
global $wpdb;
$userid = get_current_user_id();
$user = get_userdata( $userid );
$role = implode(', ', $user->roles);

//Date of payments, payroll record number
$today = current_time('Y-m-d-h:ia');
echo "<h2>Week Starting:  <small>";
if(isset($_GET['date'])){
	$date = $_GET['date'];
	$week_start = date("Y-m-d-h:ia", strtotime('monday this week', strtotime($date)));
	$week_end = date("Y-m-d-h:ia", strtotime('monday next week', strtotime($date)));
	echo date("D jS M, Y", strtotime($week_start))."</small></h2>";
}else{
	$week_start = date("Y-m-d-h:ia", strtotime('monday this week', strtotime($today)));
	$week_end = date("Y-m-d-h:ia", strtotime('monday next week', strtotime($today)));
	echo date("D jS M, Y", strtotime($week_start))."</small></h2>";
}

crm_date_selector("akimbo-crm", "roster");

$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$week_start' AND session_date <= '$week_end' ORDER BY session_date ASC");
$rosters = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_roster WHERE start_time >= '$week_start' AND start_time <= '$week_end' ORDER BY start_time ASC");
echo "<table>";
if(!$classes && !$rosters){
	echo "Roster not yet set<br/><br/>";
}else{
	//Classes
	echo "<tr bgcolor = '#33ccff'><th>Class</th><th>Trainer</th><th>Start</th><th>Finish</th><th>Venue</th><th>Details</th></tr>";
	foreach ( $classes as $class ) {
		echo "<tr><td>";
		//Class Date
		echo $class->class_title.", ".date("l jS F", strtotime($class->session_date))."</td><td>";
		
		//trainers
		$tr1 = $class->trainer_id;
		$tr2 = $class->trainer2_id;
		echo $wpdb->get_var("SELECT display_name FROM {$wpdb->prefix}users WHERE ID = '$tr1'");
		if($tr2){echo " & ".$wpdb->get_var("SELECT display_name FROM {$wpdb->prefix}users WHERE ID = '$tr2'");}
		echo "</td><td>";
		
		//Times
		echo date("g:ia", strtotime($class->session_date))."</td><td>";
		$increment = "+ ".$class->duration." minutes";
		$class_end = date("g:ia", strtotime($increment, strtotime($class->session_date)));
		echo $class_end."</td><td>";
		
		//Details
		echo $class->location."</td><td>";
		echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$class->list_id."'><i>View Class</i></a>";
		echo "</td></tr>";
	}
	
	//Rostered shifts & bookings
	echo "<tr bgcolor = '#33ccff'><th>Rostered Shifts</th><th>Trainer</th><th>Start</th><th>Finish</th><th>Venue</th><th>Details</th></tr>";
	foreach ( $rosters as $roster ) {
		$increment = "+ ".$roster->duration." minutes";
		$trainer = $roster->trainer_id;
		if(isset($_GET['shift'])){
			if($_GET['shift'] == $roster->roster_id){
				?><tr><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
				<td><select name="type"><option value="<?php echo $roster->shift_type; ?> "><?php echo ucfirst($roster->shift_type);?></option><option>----</option>
				<option value="party">Party</option><option value="setup">Setup</option><option value="admin">Admin</option></select>
				</td><td><select name="trainer"><option value="<?php echo $trainer; ?>"><?php echo $wpdb->get_var("SELECT display_name FROM {$wpdb->prefix}users WHERE ID = '$trainer'"); ?></option>
				<option>----</option>
				<?php crm_trainer_dropdown_list(); ?></select>
				</td><td> Start:<input name="start_time" type="datetime-local">
				</td><td><input name="duration" type="number" value="<?php echo $roster->duration;?>"> mins
				</td><td><input name="location" type="text" value="<?php echo $roster->location;?>">
				<input type="hidden" name="roster_id" value="<?php echo $roster->roster_id; ?>">
				<input type="hidden" name="redirect" value="/wp-admin/admin.php?page=akimbo-crm&tab=roster">
				<input type="hidden" name="action" value="crm_add_to_timesheet">
				</td><td><input type='submit' value='Update'>
				<?php echo crm_simple_delete_button( "crm_roster", "roster_id", $roster->roster_id, "/wp-admin/admin.php?page=akimbo-crm&tab=roster");//$table, $data_id, $data, $redirect?>
				</td></tr>
				</form><?php
			}
		}
		echo "<tr><td>".ucfirst($roster->shift_type).", ".date("l jS F", strtotime($roster->start_time))."</td><td>";
		echo $wpdb->get_var("SELECT display_name FROM {$wpdb->prefix}users WHERE ID = '$trainer'")."</td><td>";
		echo date("g:ia", strtotime($roster->start_time))."</td><td>";
		echo date("g:ia", strtotime($increment, strtotime($roster->start_time)))."</td><td>";
		echo $roster->location."</td><td>";
		if ($role == "administrator") {
			echo "<i><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=roster&date=".date("Y-m-d", strtotime($roster->start_time))."&shift=".$roster->roster_id."'>Edit shift</a></i>";
		}
		echo "</td></tr>";
	}
}	

echo "</table>";

if ($role == "administrator") {//Admin view	
	echo "<table><tr>";	

	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<td><select name="type"><option value="party">Party</option><option value="setup">Setup</option><option value="admin">Admin</option><option value="workshop">Workshop</option></select>
	</td><td><select name="trainer"><option value="0">No trainer</option><?php crm_trainer_dropdown_list(); ?></select>
	</td><td> Start:<input name="start_time" type="datetime-local">
	</td><td><input name="duration" type="number" value="15"> mins
	</td><td><input name="location" type="text" value="Circus Akimbo - Hornsby">
	<input type="hidden" name="action" value="crm_add_to_timesheet">
	</td><td><button>Add To Roster</button></td></tr></table>
	</form></table>
	<form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	Roster edit: <select name="trainer"><?php crm_trainer_dropdown_list(); ?></select>
	Week starting <input type="text" name="date" placeholder="e.g. 2019-04-29">
	<input type="hidden" name="action" value="crm_email_trainer_roster_edit">
	<input type="submit" value="Send roster update email">
	</form>
<?php
}
echo "<br/>Can't make a rostered shift? You can try to swap it in the trainer <a href='https://www.facebook.com/groups/863632663663310/'>Facebook group</a>";

?><!-- Select by week -->
<h2>Look up a different week</h2>
<form action="admin.php" method="get">
<input type="hidden" name="page" value="akimbo-crm" />
<input type="hidden" name="tab" value="roster" />
<select name="date"><?php 
echo "<option value='".date("Y-m-d-ha", strtotime('+ 2 weeks monday', strtotime($today)))."'>".date("D jS M, Y", strtotime('+ 2 weeks monday', strtotime($today)))." - ".date("D jS M, Y", strtotime('+ 2 weeks sunday', strtotime($today)))."</option>"; 
echo "<option value='".date("Y-m-d-ha", strtotime('monday next week', strtotime($today)))."'>".date("D jS M, Y", strtotime('monday next week', strtotime($today)))." - ".date("D jS M, Y", strtotime('sunday next week', strtotime($today)))."</option>"; 
echo "<option value='".date("Y-m-d-ha", strtotime('monday this week', strtotime($today)))."'>".date("D jS M, Y", strtotime('monday this week', strtotime($today)))." - ".date("D jS M, Y", strtotime('sunday this week', strtotime($today)))."</option>"; 
echo "<option value='".date("Y-m-d-ha", strtotime('monday last week', strtotime($today)))."'>".date("D jS M, Y", strtotime('monday last week', strtotime($today)))." - ".date("D jS M, Y", strtotime('sunday last week', strtotime($today)))."</option>"; 
echo "<option value='".date("Y-m-d-ha", strtotime('- 2 weeks monday', strtotime($today)))."'>".date("D jS M, Y", strtotime('- 2 weeks monday', strtotime($today)))." - ".date("D jS M, Y", strtotime('- 2 weeks sunday', strtotime($today)))."</option>"; 
?></select><input type="submit" value="Update"></form><br/><?php

