<?php
/**
 * Plugin Name: Akimbo CRM
 * Plugin URI: #
 * Version: 2.1
 * Author: Circus Akimbo
 * Author URI: https://circusakimbo.com.au
 * Description: A simple CRM system for WordPress and Woocommerce
 * License: GPL2
 */

class AkimboCRM {
	global $wpdb;
	global $akimbo_crm_db_version;
	$akimbo_crm_db_version = '2.1';
	register_activation_hook( __FILE__, 'akimbo_crm_create_db_tables' );

	function akimbo_crm_create_db_tables(){
		global $wpdb;
		global $akimbo_crm_db_version;
		
		$installed_ver = get_option( "akimbo_crm_db_version" );
		if($installed_ver != $akimbo_crm_db_version){
			$charset_collate = $wpdb->get_charset_collate();
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$table_name = $wpdb->prefix . "crm_students"; 
			$sql = "CREATE TABLE $table_name (
			student_id int(11) NOT NULL AUTO_INCREMENT,
			student_rel tinytext,
			student_firstname tinytext NOT NULL,
			student_lastname tinytext,
			student_dob datetime DEFAULT '0000-00-00 00:00:00',
			student_startdate datetime DEFAULT '0000-00-00 00:00:00',
			student_waiver datetime DEFAULT '0000-00-00 00:00:00',
			student_notes text,
			marketing tinytext,
			PRIMARY KEY  (student_id)
			) $charset_collate;";
			dbDelta( $sql );

			$table_name = $wpdb->prefix . "crm_roster"; 
			$sql = "CREATE TABLE $table_name (
			roster_id int(11) NOT NULL AUTO_INCREMENT,
			start_time datetime DEFAULT '0000-00-00 00:00:00',
			roster_id int(11),
			duration int(11),
			shift_type tinytext,
			location tinytext,
			PRIMARY KEY  (roster_id)
			) $charset_collate;";
			dbDelta( $sql );
			
			/** Update db version & success message */
			add_option( 'akimbo_crm_db_version', $akimbo_crm_db_version );
			$update_message = "Plugin successfully updated";
		}
		
		

		
	}
	
	/**
	*Constructor. Called when plugin is initialised
	*/
	function __construct(){
		add_action('admin_menu', array(&$this, 'akimbo_crm_admin_menu'));
		$this->includes();
		add_action('admin_init', array(&$this, 'akimbo_crm_register_settings'));
		add_action( 'plugins_loaded', 'akimbo_crm_update_db_check' );
		
	}
	function akimbo_crm_update_db_check() {
		global $akimbo_crm_db_version;
		if ( get_site_option( 'akimbo_crm_db_version' ) != $akimbo_crm_db_version ) {
			akimbo_crm_create_db_tables();
		}
	}
	
	
	function akimbo_crm_admin_menu(){//Title, admin panel label, user capabilities, slug, function callback
			$parent_slug = "akimbo-crm";
			add_menu_page( 'Circus Akimbo Admin', 'Akimbo CRM', 'upload_files', 'akimbo-crm', array(&$this, 'akimbo_crm_init' ), 'dashicons-book-alt', '2');
			add_submenu_page( $parent_slug, 'Classes', 'Classes', 'upload_files', 'akimbo-crm2', array(&$this, 'manage_classes' ), 1);
			add_submenu_page( $parent_slug, 'Bookings & Parties', 'Bookings', 'manage_woocommerce', 'akimbo-crm4', array(&$this, 'manage_bookings' ), 2);
			add_submenu_page( $parent_slug, 'Business Admin', 'Business', 'manage_options', 'akimbo-crm3', array(&$this, 'manage_orders' ), 3);
			add_submenu_page( $parent_slug, 'Settings', 'Settings', 'manage_options', 'crm-options', array(&$this, 'akimbo_crm_options_page' ), 4);
	}
	
	function akimbo_crm_init(){
        global $wpdb;
        $site = get_site_url();
        $today = current_time('Y-m-d');
        echo "<h2 class='nav-tab-wrapper'>";
			$page = "akimbo-crm";
    		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'home';
			echo crm_nav_tab($page, "home", "Home", $active_tab);
			echo crm_nav_tab($page, "details", "Student Details", $active_tab);
			echo crm_nav_tab($page, "roster", "Roster", $active_tab);
			echo crm_nav_tab($page, "availabilities", "Availabilities", $active_tab);
		echo "</h2>";
		switch ($active_tab) {
		    case "home": echo apply_filters('akimbo_crm_admin_home', akimbo_crm_admin_home_page());
		    break;
		    case "details": echo apply_filters('akimbo_crm_student_details', akimbo_crm_student_details());
		    break;
		    case "roster": apply_filters('akimbo_crm_admin_roster', akimbo_crm_roster()); 
		    echo apply_filters('akimbo_crm_admin_home_staff_details', akimbo_crm_staff_details());
		    break;
		    case "availabilities": apply_filters('akimbo_crm_admin_availabilities', akimbo_crm_update_trainer_availabilities()); 
		    break;
		    default:
		}
	}

	function manage_bookings(){
		echo "<h2 class='nav-tab-wrapper'>";
			$page = "akimbo-crm4";
			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'bookings'; 
			echo crm_nav_tab($page, "bookings", "Manage Bookings", $active_tab);
			echo crm_nav_tab($page, "booking_details", "Booking Details", $active_tab);
			echo crm_nav_tab($page, "calendar", "Calendar", $active_tab);
			if (current_user_can('manage_woocommerce')){
				echo crm_nav_tab($page, "partydata", "Party Data", $active_tab);
			}
		echo "</h2>";
		include_once 'includes/manage_bookings.php';
	
	}
	
	function manage_classes(){
		global $wpdb;
		$today = current_time('Y-m-d');
		$site = get_site_url();
        echo "<h2 class='nav-tab-wrapper'>";
				$page = "akimbo-crm2";
				$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'classes'; 
				echo crm_nav_tab($page, "classes", "Classes", $active_tab);
				echo crm_nav_tab($page, "schedule", "Scheduling", $active_tab);
				echo crm_nav_tab($page, "enrolment", "Enrolment Troubleshooting", $active_tab);
				echo crm_nav_tab($page, "payments", "Late Payments", $active_tab);
		echo "</h2>";
		switch ($active_tab) {
		    case "classes": echo apply_filters('akimbo_crm_admin_manage_classes', akimbo_crm_manage_classes());
				 //include 'includes/manage_classes.php';
		    break;
		    case "schedule": echo apply_filters('akimbo_crm_admin_manage_classes_schedule', akimbo_crm_manage_schedules());	
		    break;
		    case "enrolment": echo apply_filters('akimbo_crm_admin_manage_classes_enrolments', akimbo_crm_enrolment_issues());
		    break;
		    case "payments": apply_filters('akimbo_crm3_business details_payments', akimbo_crm_unpaid_students());
			break;
		    default:
		}
	}

	function manage_orders(){
		if (!current_user_can('manage_options')){
			  wp_die( __('This page is for the administrator, sorry. Please contact an admin if you think you should have access to this page') );
		}else{
			echo "<h2>Manage Business</h2>";
			global $wpdb;
			echo "<h2 class='nav-tab-wrapper'>";
				$page = "akimbo-crm3";
				$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'statistics'; 
				echo crm_nav_tab($page, "statistics", "Student Statistics", $active_tab);
				echo crm_nav_tab($page, "business", "Business Details", $active_tab);
				echo crm_nav_tab($page, "payroll", "Payroll", $active_tab);
			echo "</h2>";

			switch ($active_tab) {
			    case "business": apply_filters('akimbo_crm3_business details', akimbo_crm_business_details());//test info in akimbo-crm 2.0 functions
			    break;
			    case "statistics": include 'includes/includes/student_statistics.php';  	
			    break;
			    case "payroll": apply_filters('akimbo_crm3_business details_payroll', akimbo_crm_manage_payroll()); 	
			    break;
			    default:
			}
		}
	}
	
	function akimbo_crm_register_settings(){
   		$args = array('type' => 'string','sanitize_callback' => 'sanitize_text_field',);
   		add_option( 'akimbo_crm_account_message', 'Welcome to your account dashboard.');
   		register_setting( 'akimbo_crm_options', 'akimbo_crm_account_message', $args );
   		add_option( 'akimbo_crm_order_message', 'Thanks for ordering with us!');
   		register_setting( 'akimbo_crm_options', 'akimbo_crm_order_message', $args );
   		add_option( 'akimbo_crm_class_booking_window', '-24hrs');
   		//Class products
   		register_setting( 'akimbo_crm_options', 'akimbo_crm_class_booking_window', $args );
   		add_option( 'akimbo_crm_adult_class_products', 'a:2:{i:0;i:308;i:1;i:227;}');
   		register_setting( 'akimbo_crm_product_options', 'akimbo_crm_adult_class_products', $args );
   		add_option( 'akimbo_crm_training_class_products', serialize(array(227, 308)));
   		register_setting( 'akimbo_crm_product_options', 'akimbo_crm_training_class_products', $args );
   		add_option( 'akimbo_crm_kids_class_products', serialize(array(227, 308)));
   		register_setting( 'akimbo_crm_product_options', 'akimbo_crm_kids_class_products', $args );
   		add_option( 'akimbo_crm_playgroup_class_products', serialize(array(227, 308)));
   		register_setting( 'akimbo_crm_product_options', 'akimbo_crm_playgroup_class_products', $args );
	}

	function akimbo_crm_options_page(){
		?><div><?php screen_icon(); ?>
		<h2>Options</h2><form method="post" action="options.php">
		<?php settings_fields( 'akimbo_crm_options' ); ?>
		<table>
		<tr valign="top"><th scope="row"><label for="akimbo_crm_account_message">Account Message</label></th>
		<td><input type="text" id="akimbo_crm_account_message" name="akimbo_crm_account_message" value="<?php echo get_option('akimbo_crm_account_message'); ?>" size="50"/></td></tr>
		<tr valign="top"><th scope="row"><label for="akimbo_crm_order_message">Order Message</label></th>
		<td><input type="text" id="akimbo_crm_order_message" name="akimbo_crm_order_message" value="<?php echo get_option('akimbo_crm_order_message'); ?>" size="50"/></td></tr>
		<tr valign="top"><th scope="row"><label for="akimbo_crm_class_booking_window">Booking Window</label></th>
		<td><input type="text" id="akimbo_crm_class_booking_window" name="akimbo_crm_class_booking_window" value="<?php echo get_option('akimbo_crm_class_booking_window'); ?>" size="50"/></td></tr>
		</table><?php  submit_button(); ?></form></div><?php

		?><div><?php screen_icon(); ?>
		<h2>Class Options: DO NOT UPDATE</h2><form method="post" action="options.php">
		<?php settings_fields( 'akimbo_crm_product_options' ); ?>
		<table>
		<!--------------------------------------------------------------------------------------------------------------------
			Remove input fields to avoid issues when updating other settings until I work out how to pass serialized arrays 
		---------------------------------------------------------------------------------------------------------------------->
		<tr valign="top"><th scope="row"><label for="akimbo_crm_adult_class_products">Adult Class Products</label></th>
		<td><input type="text" id="akimbo_crm_adult_class_products" name="akimbo_crm_adult_class_products" value="<?php echo get_option('akimbo_crm_adult_class_products') ?>" size="50"/><?php  
		$options = get_option('akimbo_crm_adult_class_products'); 
		foreach($options as $option){echo $option.", "; }?></td></tr>
		<tr valign="top"><th scope="row">Open Training Products<label for="akimbo_crm_training_class_products"></label></th>
		<td><input type="text" id="akimbo_crm_training_class_products" name="akimbo_crm_adult_training_products" value="<?php echo get_option('akimbo_crm_training_class_products'); ?>" size="50"/><?php 
		$options = get_option('akimbo_crm_training_class_products');
		foreach($options as $option){echo $option.", "; }?></td></tr>
		<tr valign="top"><th scope="row"><label for="akimbo_crm_kids_class_products">Kids Class Products</label></th>
		<td><input type="text" id="akimbo_crm_kids_class_products" name="akimbo_crm_kids_class_products" value="<?php echo get_option('akimbo_crm_kids_class_products'); ?>" size="50"/><?php 
		$options = get_option('akimbo_crm_kids_class_products');
		foreach($options as $option){echo $option.", "; }?></td></tr>
		<tr valign="top"><th scope="row"><label for="akimbo_crm_playgroup_class_products">Playgroup Class Products</label></th>
		<td><input type="text" id="akimbo_crm_playgroup_class_products" name="akimbo_crm_playgroup_class_products" value="<?php echo get_option('akimbo_crm_playgroup_class_products'); ?>" size="50"/><?php 
		$options = get_option('akimbo_crm_playgroup_class_products');
		foreach($options as $option){echo $option.", "; }?></td></tr>
		
		</table><?php  submit_button(); ?></form></div><?php
	}

	public function includes() {


		/**
		 * Core classes.
		 */
		include_once 'includes/class-akimbo-crm-availability.php';
		include_once 'includes/class-akimbo-crm-class.php';
		include_once 'includes/class-akimbo-crm-party.php';
		include_once 'includes/class-akimbo-crm-payroll.php';
		include_once 'includes/class-akimbo-crm-staff.php';
		include_once 'includes/class-akimbo-crm-student.php';
		include_once 'includes/class-akimbo-crm-user.php';
		
		/* 
		*
		* Functions
		*
		*/
		include_once 'includes/akimbo-crm-account-functions.php';
		include_once 'includes/akimbo-crm-admin-functions.php';
		include_once 'includes/akimbo-crm-badge-functions.php';
		include_once 'includes/akimbo-crm-booking-functions.php';
		include_once 'includes/akimbo-crm-class-functions.php';
		include_once 'includes/akimbo-crm-custom-functions.php';
		include_once 'includes/akimbo-crm-order-functions.php';
		include_once 'includes/akimbo-crm-scheduling-functions.php';
		include_once 'includes/akimbo-crm-staff-functions.php';
		include_once 'includes/akimbo-crm-student-functions.php';
		include_once 'includes/akimbo-crm-user-functions.php';
		

		//include_once WC_ABSPATH . 'includes/class-wc-datetime.php';
		
	}	
}

$akimboCRM = new AkimboCRM;

 



