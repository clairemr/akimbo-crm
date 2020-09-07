<?php

/**
 * Staff member object
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.0
 *
 * @package    Akimbo_Crm
 * @subpackage Akimbo_Crm/staff
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */


//class Akimbo_Crm_Staff extends Akimbo_Crm_User{
class Akimbo_Crm_Staff{

	public $staff_id;
	private $trainer_meta;
	private $level;
	private $type;

	function __construct($staff_id){
		global $wpdb;
		$this->staff_id = $staff_id;
		//parent::__construct( $staff_id );
		$this->user_data = get_userdata($staff_id);
		$this->level = $this->trainer_meta['employee_level'][0];
		$this->type = $this->trainer_meta['employee_type'][0];
	}

	function is_staff(){
		$user_meta = get_userdata($this->staff_id);
		$user_roles = $user_meta->roles;
		$staff_roles = array('administrator', 'author');
		$roles = array_intersect($user_roles,$staff_roles);
		$result = ( count( $roles) >= 1 ) ? true : false;
		return $result;
	}

	function get_meta(){
		$trainer_meta = get_user_meta($this->staff_id);
		$rerun = false;
		if(!isset($trainer_meta['employee_level'][0])){
			add_user_meta( $this->staff_id, "employee_level", "", true);
			$rerun = true;
		}
		if(!isset($trainer_meta['employee_type'][0])){
			add_user_meta( $this->staff_id, "employee_type", "", true);
			$rerun = true;
		}
		if(!isset($trainer_meta['employee_threshold'][0])){
			add_user_meta( $this->staff_id, "employee_threshold", "", true);
			$rerun = true;
		}
		if(!isset($trainer_meta['employee_tfn'][0])){
			add_user_meta( $this->staff_id, "employee_tfn", "", true);
			$rerun = true;
		}
		if(!isset($trainer_meta['employee_keypay_id'][0])){
			add_user_meta( $this->staff_id, "employee_keypay_id", "", true);
			$rerun = true;
		}
		if(!isset($trainer_meta['wwccheck'][0])){
			add_user_meta( $this->staff_id, "wwccheck", "", true);
			$rerun = true;
		}
		//add check to re-run get_user_meta function if needed
		$this->trainer_meta = ($rerun) ? get_user_meta($this->staff_id) : $trainer_meta;
		return $this->trainer_meta;
	}

	function payrate($day_of_week){//weekday or weekend
		global $wpdb;
		$rate = $wpdb->get_var("SELECT rate FROM {$wpdb->prefix}crm_award_rates WHERE level = '$this->level'");
		if($type == "casual"){
			$weekday_rate = $rate * 1.25;
			$weekend_rate = $rate * 1.3;
		} else{
			$weekday_rate = $rate;
			$weekend_rate = $rate;
		}

		$result = ($day_of_week == "weekday") ? $weekday_rate : $weekend_rate;
		return $result;
	}

}