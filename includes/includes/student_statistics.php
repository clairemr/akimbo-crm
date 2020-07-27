<?php
$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');
$current_semester = akimbo_term_dates('return', $date);
echo "<table width='90%'><tr><th>";
//http://localhost/wordpress/wp-admin/admin.php?page=akimbo-crm3
if(isset($current_semester['previous_start'])){
	echo " <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm3&tab=statistics&date=".$current_semester['previous_start']."'><input type='submit' value='<'></a></h2>";
}
echo $current_semester['slug'];
if(isset($current_semester['next_start'])){
	echo " <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm3&tab=statistics&date=".$current_semester['next_start']."'><input type='submit' value='>'></a></h2>";
}

echo "</th></tr><tr align='center'><td>";
echo "<h4>".date("D jS M, Y", strtotime($current_semester['start']))." - ".date("D jS M, Y", strtotime($current_semester['end']))."</h4>";
crm_date_selector("akimbo-crm3", "statistics");
echo "</td></tr></th></tr></table>";
echo "<hr>";

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
echo "<details>";
echo "<summary>Returning students: ".count($returning)."</summary>";
if($returning){
	foreach ($returning as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
	}
}else{
	echo "<i>No returning students</i>";
}
echo "<hr></details>";
echo "<details>";
echo "<summary>New students: ".count($new)."</summary>";
if($new){
	foreach ($new as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
	}
}else{
	echo "<i>No new students</i>";
}
echo "<hr></details>";
echo "<details>";
echo "<summary>Students not returning: ".count($not_returning)."</summary>";
if($not_returning){
	foreach ($not_returning as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
	}
}else{
	echo "<i>No students not returning</i>";
}
echo "<hr></details>";

/**
 * Move to Mailchimp page
 */

/*
echo "<table width='90%'><tr>
	<th width='33%'>Returning students: ".count($returning)."</th>
	<th width='34%'>New students: ".count($new)."</th>
	<th width='33%'>Students who haven't re-enrolled: ".count($not_returning)."</th></tr>";

echo "<td valign='top'>";
if($returning){
	foreach ($returning as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
		$emails[] = $student->contact_email();
	}
	echo "<hr><br/>Emails: ";
	//foreach($emails as $email){echo $email.", ";}
	unset($emails);
}
echo "</td><td valign='top'>";
if($new){
	foreach ($new as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
		$emails[] = $student->contact_email();
	}
	echo "<hr><br/>Emails: ";
	//foreach($emails as $email){echo $email.", ";}
	unset($emails);
}
echo "</td><td valign='top'>";

if($not_returning){
$kids_emails = array();
	foreach ($not_returning as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
		$contact = $student->contact_email();
		$emails[] = $student->contact_email();
		if($student->get_age() == 'kids' && !in_array($contact, $kids_emails)){
			$kids_emails[] = $student->contact_email();
		}
	}
	echo "<hr><br/>Emails: ";
	foreach($emails as $email){echo $email.", ";}
	echo "<hr><br/>Kids Emails: ";
	foreach($kids_emails as $email){echo $email.", ";}
	unset($emails);
}
echo "</td></tr></table>";

//echo "<br/><i>Total students: ".count($student_list);
$previous_term_students = count($returning) + count($not_returning);
$current_term_students = count($returning) + count($new);
echo "<i>Previous term students: ".$previous_term_students;
if($previous_term_students >=1){
	echo " (".count($returning)." returning, ".count($not_returning)." not returning)";
}

echo "<br/>Current term students: ".$current_term_students." (".count($new)." new, ".count($returning)." returning)</i>";
if(count($returning) >=1 && $previous_term_students){
	$retention = count($returning)/$previous_term_students;
	echo "<br/><i>Retention: ".round($retention * 100 )."%</i>";
}

//purchases up to end of semester count towards that term. From the next day it counts towards following term

		
//Total number of students
//Unique students who attended a class in T3-2018
//Unique students who attended a class in T4-2018
//Students who attended their first class in T4-2018 (NEW)
//Students who attended a class in T4-2018 and also a previous term (RE)

*/