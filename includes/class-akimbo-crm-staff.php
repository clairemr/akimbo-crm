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
		parent::__construct( $staff_id );
		$this->trainer_meta = get_user_meta($trainer);
		$this->level = $this->trainer_meta['employee_level'][0];
		$this->type = $this->trainer_meta['employee_type'][0];
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