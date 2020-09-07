<?php

/**
 * Payroll line object
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.0
 *
 * @package    Akimbo_Crm
 * @subpackage Akimbo_Crm/payroll
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */

class Akimbo_Crm_Payroll{

	public $start_date;
	public $end_date;
	private $payroll_items;

	function __construct($start_date, $end_date){
		global $wpdb;
		$this->start_date = $start_date;
		$this->end_date = $end_date;
		$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$start_date' AND session_date <= '$end_date' ORDER BY session_date ASC");
		//session_date, trainers, duration
		//$bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_availability LEFT JOIN {$wpdb->prefix}crm_booking_meta ON {$wpdb->prefix}crm_availability.avail_id = {$wpdb->prefix}crm_booking_meta.avail_id WHERE {$wpdb->prefix}crm_booking_meta.meta_key = 'trainers' AND {$wpdb->prefix}crm_availability.availability = false AND {$wpdb->prefix}crm_availability.session_date >= '$start_date' AND {$wpdb->prefix}crm_availability.session_date <= '$end_date' ORDER BY {$wpdb->prefix}crm_availability.session_date ASC");
		$shifts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_roster WHERE start_time >= '$start_date' AND start_time <= '$end_date' ORDER BY start_time ASC");
		$this->payroll_items = array_merge($classes, $shifts);

	}

	function get_items(){
		return $this->payroll_items;
	}

	function display_items(){
		global $wpdb;
		$userid = get_current_user_id();
		$payroll_trainers = akimbo_crm_trainer_names();
		if($this->payroll_items != NULL){
			echo "<table width='80%'><tr bgcolor = '#33ccff'><th width='40%'><strong>Class</strong></th><th width='30%'><strong>Trainer</strong></th><th>Duration</th></tr>";
			
			foreach($this->payroll_items as $item){
				if(isset($item->shift_type)){
					echo "<tr><td align='right'>".ucwords($item->shift_type)." , ".date('g:ia l jS M', strtotime($item->start_time))."</td><td>".$payroll_trainers[$item->trainer_id]."</td><td>".$item->duration." mins</td></tr>";
				}
				$line_trainers = (isset($item->trainers)) ? unserialize($item->trainers) : unserialize($item->trainer_id);
				if(is_array($line_trainers)){
					$i = 1;	
					foreach($line_trainers as $trainer){
						if(!current_user_can('manage_options') && $trainer != $userid){//don't show
						}else{
							if($trainer >= 1){//don't show empty slots
								echo "<tr><td align='right'>";
								$duration = $item->duration;
								$start = date('g:ia l jS M', strtotime($item->session_date));
								if(isset($item->class_title)){
									echo akimbo_crm_class_permalink($item->list_id, $item->class_title);
								}else{
									echo "Booking ".$item->avail_id;
									$duration = ($i == 1) ? $duration + 60 : $duration + 30;//add set up. 60mins senior, 30 mins junior
									$start = ($i == 1) ? date('g:ia l jS M', strtotime('-30mins', strtotime($item->session_date))) : date('h:ia l jS M', strtotime('-15mins', strtotime($item->session_date)));
								}
								echo ", ".$start."</td><td>".$payroll_trainers[$trainer]."</td><td>".$duration." mins</td></tr>";
							}
							$i++;
						}
					}
				}
				
			}
			echo "</table>";
		}
	}

}