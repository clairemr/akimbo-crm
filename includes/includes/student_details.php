<?php
global $wpdb;
$today = current_time('Y-m-d H:ia');
$site = get_site_url(); 
$page = "akimbo-crm";
$tab = "details";
echo "<br/>";
crm_dropdown_selector("students", $page, $tab);
crm_dropdown_selector("users", $page, $tab);
echo "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=new'>Add new student</a><br/>";

$student_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_students" );
echo "<br/>Total students: ".$student_count;
$user_count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );
echo "<br/>Total users: ".$user_count."<br/>";
echo "<br/><hr><br/>";
if(isset($_GET['student'])){
	$student_id = $_GET['student'];
	if($student_id == "new"){
		echo "<h3>Add New Student</h3>";
		if(isset($_GET['class'])){//send back to manage classes page
			$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$_GET['class'];
		}else{
			$student_id = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1 ") + 1;
			$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$student_id;
		}
		update_student_details_form(0, $url, 1);
	}else{
		$student = new Akimbo_Crm_Student($_GET['student']);
		//var_dump($student->class_attendance(0, 0));
		$info = $student->get_student_info();
		$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$student_id;
		echo "<h3>".$info->student_firstname." ".$info->student_lastname."</h3>";
		echo "Contact number: ".$student->contact_phone();
		update_student_details_form($student->get_id(), $url, 1);
	}

	echo "<br/><hr><br/>";

	if(isset($student)){
		$student_classes = $student->get_classes();
		if(!$student_classes){echo "No classes found. Delete student?";
			$end = 0;
			$start = 0;
			crm_simple_delete_button("crm_students", "student_id", $student_id, "/wp-admin/admin.php?page=akimbo-crm&tab=details");
		}else {
			echo "<h2>Classes Attended</h2>";
			echo "<table>";
			echo "<tr><td>Date</td><td>Class</td><td>Attended</td><td>Order Item</td></tr>";
			foreach($student_classes as $student_class){
				echo "<tr><td>".date("g:ia, l jS M Y", strtotime($student_class->session_date));
				echo "</td><td><a href='".$site."/wp-admin/admin.php?page=akimbo-crm2&class=".$student_class->class_list_id."'>".$student_class->class_title."</a></td><td>".$student_class->attended;
				echo "</td><td><a href='".crm_order_link_from_item_id($student_class->ord_id)."'>".$student_class->ord_id."</a></td><td>";
				$redirect = "/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$student_id;
				crm_simple_delete_button("crm_attendance", "attendance_id", $student_class->attendance_id, $redirect);
				echo "<i>does not return pass</i></td></tr>";
			}
			echo "</table>";
		}

				$mailchimp = $student->update_mailchimp();//updates mailchimp and returns values
		echo "<h2>Mailchimp integration: ";
		if($mailchimp['subscribed'] == false){
			echo "<small>User not subscribed</small></h2>";
			echo "CRM start: ".date("g:ia, l jS M Y", strtotime($student->first_class()->session_date))."<br/>";
			echo "CRM most recent: ".date("g:ia, l jS M Y", strtotime($student->last_class()->session_date))."<br/>";
		}else{
			echo "<small>".$mailchimp['email']."</small></h2>";
			echo "Mailchimp start: ".date("l jS M Y", strtotime($mailchimp['MCstart']))."<br/>";
			echo "Mailchimp most recent: ".date("l jS M Y", strtotime($mailchimp['MCend']))."<br/>";
		}	
		
	}	
}//end student details

/**
*
* Sort by user
*
*/

if(isset($_GET['user'])){
	$user_id = $_GET['user'];
	$user_info = get_userdata($user_id);
	echo '<h3>User: ' . $user_info->display_name."</h3>";
	echo 'Username: ' . $user_info->user_login . "<br/>Name: ". $user_info->first_name." ".$user_info->last_name;
	echo "<br/>Email: ".$user_info->user_email;
	$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = '$user_id' ");
	if(!$students){echo "No students found";} else{
		foreach ($students as $student){echo "<h3>".$student->student_firstname." ".$student->student_lastname." <a href='".$site."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$student->student_id."'>View</a></h3>";}
	}
	echo "<a href='".get_site_url()."/wp-admin/user-edit.php?user_id=".$user_id."'><button>View Full User Profile</button>";
}