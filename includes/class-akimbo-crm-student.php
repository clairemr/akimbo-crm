<?php

/**
 * The booking functionality of the plugin.
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.0
 *
 * @package    Akimbo_Crm
 * @subpackage Akimbo_Crm/student
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */

class Akimbo_Crm_Student{

	public $student_id;
	private $student_info;
	private $user_info;
	private $classes;
	private $age;

	function __construct($student_id){
		$this->student_id = $student_id;
		global $wpdb;
		$this->student_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_students WHERE student_id = $student_id");
		$user_id = $this->student_info->user_id;
		$this->user_info = get_userdata($user_id);
		$this->classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id WHERE student_id = $student_id ORDER BY session_date");
		$this->age = $wpdb->get_var("SELECT age_slug FROM {$wpdb->prefix}crm_class_list LEFT JOIN {$wpdb->prefix}crm_attendance ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id WHERE student_id = '$student_id' AND age_slug != 'private' ORDER BY session_date DESC LIMIT 1");//desc = most recent, accounts for students getting older
		/*if($student->last_class()->session_date == "current_semester"){
			$status = "current";
		}*/
	}

	function get_product_id(){
		return $this->$student_id;
	}

	function get_student_info(){
		return $this->student_info;
	}

	function contact_number(){
		global $wpdb;
		$phone = get_user_meta( $this->student_info->user_id, 'billing_phone', true );
		return $phone;
	}

	function first_name(){
		return $this->student_info->student_firstname;
	}

	function full_name(){
		$full_name = $this->student_info->student_firstname." ".$this->student_info->student_lastname;
		return $full_name;
	}

	function get_age(){
		$age = ($this->age != NULL) ? $this->age : "adult";
		return $this->age;
	}
	
	function get_dob($format = NULL){//'Y-m-d'
		$dob = $this->student_info->student_dob;
		$dob = ($format == NULL) ? $dob : date($format, strtotime($dob));
		return $dob;
	}

	function get_id(){
		return $this->student_id;
	}

	function get_user_id(){
		return $this->student_info->user_id;
	}

	function get_classes($format = "array", $date = 'all'){
		$classes = array();
		$today = current_time('Y-m-d');
		if($format == "object"){
			foreach($this->get_classes() as $student_class){
				if($date == "future" && $student_class->session_date <= $today){
				}else{
					$id = $student_class->class_list_id;
					$classes[] = array($student_class, new Akimbo_Crm_Class($id));
				}
			}
		}else{
			$classes = $this->classes;
		}
		return $classes;
	}

	function get_class_count(){
		return count($this->get_classes);
	}

	function get_upcoming_classes(){
		$today = current_time('Y-m-d');
		$i=0;
		$upcoming_classes = array();
		foreach($this->classes as $class){
			if($class->session_date >= $today){
				$upcoming_classes[$i] = $class;
			}
			$i++;
		}
		return $upcoming_classes;
	}

	function class_attendance($variation_id = NULL, $semester = NULL){
		$attended_classes = array();
		foreach($this->classes as $class){
			$attended_classes[] = $class->list_id;
		}

		if($variation_id != NULL){
			$class_ids = array();
			foreach($this->classes as $class){
				if($class->class_id == $variation_id){$class_ids[] = $class->list_id;}
			}
			$attended_classes = (!empty($class_ids)) ? array_intersect($attended_classes, $class_ids): array();
		}
		if($semester != NULL){
			$semester_classes = array();
			foreach($this->classes as $class){
				if($class->semester_slug == $semester){$semester_classes[] = $class->list_id;}
			}
			$attended_classes = (!empty($semester_classes)) ? array_intersect($attended_classes, $semester_classes) : array();
		}
		return $attended_classes;
	}

	function attending_this_semester($current_semester_slug){
		$result = ($this->class_attendance(NULL, $current_semester_slug) != NULL) ? true :false;
		return $result;
	}

	function new_or_returning(){
		$semester = NULL;
		$result = "new";
		foreach($this->classes as $class){
			//if student has attended for multiple semesters
			if($semester != NULL && $class->semester_slug != $semester){
				$result = "returning";
				break;
			}
			$semester = $class->semester_slug;
		}
		return $result;
	}

	//is_new() //check whether first & last class semester slugs are the same

	function first_class(){
		$classes = $this->classes;
		if(count($classes) >= 1){
			$first_class = $classes[0];
		}else{
			$first_class = NULL;
		}
		return $first_class;  
	}

	function last_class(){
		$classes = $this->classes;
		if(count($classes) >= 2){
			$last_class = $classes[count($classes) - 1]; 
		}elseif(count($classes) == 1){
			$last_class = $classes[0]; 
		}else{
			$last_class = NULL; 
		}
		return $last_class; 
	}

	function user_info(){
		return $this->user_info;
	}

	function contact_email(){
		return $this->user_info->user_email;
	}
	
	function contact_phone(){
		$customer = new WC_Customer( $this->student_info->user_id );
		return $customer->get_billing_phone();
	}

	function update_mailchimp(){
		$email = $this->contact_email();
		$mailchimp = akimbo_crm_mailchimp_get_all_merge_fields($email);
		if(is_array($mailchimp)){
			if($this->first_class() != NULL){
				if($mailchimp['ENDDATE'] != $this->last_class()->session_date){//update mailchimp, CRM should always have the most recent data
					akimbo_crm_mailchimp_update_merge_field("ENDDATE", $this->last_class()->session_date, $email);
				}		
				if ($mailchimp['STARTDATE'] > 0000-00-00 && $mailchimp['STARTDATE'] < $this->first_class()->session_date){ //echo "don't update"; //Mailchimp has the oldest date
				} elseif($mailchimp['STARTDATE'] == $this->first_class()->session_date){ //echo "dates are equal";
				} else{//echo "do update";
					akimbo_crm_mailchimp_update_merge_field("STARTDATE", $this->first_class()->session_date, $email);
				}
			}
			
			if(!is_array($mailchimp)){
				$mailchimp = array(
					'MCstart' => 0,
					'MCend' => 0,
					'subscribed' => false,
				);
			}else{
				$mailchimp = array(
					'MCstart' => $mailchimp['STARTDATE'],
					'MCend' => $mailchimp['ENDDATE'],
					'subscribed' => true,
					'email' => $email,
				);
			}
		}else{
			$mailchimp = false;
		}
		
		return $mailchimp;
	}

	function display_mailchimp($admin = false){
		$mailchimp = $this->update_mailchimp();
		if($admin == true){
			$message = ($mailchimp != false) ? "Mailchimp integration: <small>".$mailchimp['email']."</small>" : '<small>User not subscribed to Mailchimp</small>';
			echo "<h2>".$message."</h2>";
		}		
		
		if(isset($this->first_class()->session_date)){
			$name = ($this->get_student_info()->student_rel == "user" && $admin == false) ? "Your" : $this->first_name()."'s";
			echo $name." most recently enrolled class: ".date("g:ia, l jS F", strtotime($this->last_class()->session_date));
			echo "<br/>".$name." first class at Circus Akimbo: ".date("g:ia, l jS F Y", strtotime($this->first_class()->session_date));
			if($mailchimp['subscribed']){
				if($mailchimp['MCstart'] > 0000-00-00 && $mailchimp['MCstart'] < $this->first_class()->session_date){
					echo " <i>(or the first class in the new system anyway! ".$name." actual first booking was ".date("l jS F Y", strtotime($mailchimp['MCstart'])).")</i>";
				}
			}else{
				echo "<br/>Did you know you aren't subscribed to our newsletter? You might be missing important information about classes! Click <a href='http://eepurl.com/KAagf'>here</a> to update your subscription.";
			}
		}else{
			if($admin == false){
				echo "Head to your <a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."'>account dashboard</a> to book your first class!";
			}
			
		}
	}

	function student_list_display($admin = false){
		if($admin == true){
			echo $this->full_name().". ".$this->student_admin_link("View")."<br/>";
		}else{
			echo $this->full_name().". ".$this->student_account_link("View")."<br/>";
		}
		
	}

	function student_admin_link($display = NULL){//text to display or link only
		if($display != NULL){
			$url = "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$this->student_id."'>".$display."</a>";
		}else{
			$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$this->student_id;
		}
		return $url;
	}

	function student_account_link($display = NULL){//text to display or link only
		//get_permalink( get_option('woocommerce_myaccount_page_id') )."/students/?student_id=".$student->student_id
		if($display != NULL){
			$url = "<a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."students/?student_id=".$this->student_id."'>".$display."</a>";
		}else{
			$url = get_permalink( get_option('woocommerce_myaccount_page_id') )."students/?student_id=".$this->student_id;
		}
		return $url;
	}

	function user_admin_link($display = NULL){//text to display or link only
		if($display != NULL){
			$url = "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".$this->student_info->user_id."'>".$display."</a>";
		}else{
			$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&user=".$this->student_info->user_id;
		}
		return $url;
	}

}