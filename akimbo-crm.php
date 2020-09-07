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

/**
 * Checks if the WooCommerce plugin is activated. If not, don't allow plugin to be activated
 *
 * @since 1.0.0
 */
function akimbo_crm_activate_check_woocommerce() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	  include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}
	if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) {
	  deactivate_plugins( plugin_basename( __FILE__ ) );// Deactivate the plugin.
	  // Throw an error in the WordPress admin console.
	  $error_message = '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size: 13px;line-height: 1.5;color:#444;">' . esc_html__( 'This plugin requires ', 'akimbo-crm' ) . '<a href="' . esc_url( 'https://wordpress.org/plugins/akimbo-crm/' ) . '">WooCommerce</a>' . esc_html__( ' plugin to be active.', 'akimbo-crm' ) . '</p>';
	  die( $error_message ); 
	}
  }
register_activation_hook( __FILE__, 'akimbo_crm_activate_check_woocommerce' );
register_activation_hook( __FILE__, 'akimbo_crm_create_db_tables' );
global $akimbo_crm_db_version;
$akimbo_crm_db_version = '2.1';
//https://www.quora.com/How-do-premium-WordPress-plugins-validate-a-user-licence-from-their-end

function akimbo_crm_create_db_tables(){
	/*
	* Finish checking which tables are actually used in plugin before creating them all automatically
	*/
	global $wpdb;		
	global $akimbo_crm_db_version;
	$installed_ver = get_option( "akimbo_crm_db_version" );
	if($installed_ver != $akimbo_crm_db_version){
		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		 /**
		  * Attendance Table
		  */
		$table_name = $wpdb->prefix . "crm_attendance"; 
		$sql = "CREATE TABLE $table_name(
			attendance_id int(11) NOT NULL AUTO_INCREMENT,
			class_list_id int(6) NOT NULL,
			student_id int(5) NOT NULL,
			user_id int(5) NOT NULL,
			student_name tinytext NOT NULL,
			ord_id int(6) NOT NULL,
			enrolled tinyint(1) NOT NULL,
			attended tinyint(1) NOT NULL
			PRIMARY KEY  (student_id)
			) $charset_collate;";
		dbDelta( $sql );

		/*
		 * Booking Availability
		 */
		$table_name = $wpdb->prefix . "crm_availability"; 
		$sql = "CREATE TABLE $table_name (
		avail_id int(11) NOT NULL AUTO_INCREMENT,
		prod_id tinytext,
		session_date datetime DEFAULT '0000-00-00 00:00:00',
		duration int(11),
		availability tinyint(1),
		availabilities text DEFAULT NULL
		PRIMARY KEY  (avail_id)
		) $charset_collate;";
		dbDelta( $sql );
		
		/**
		 * List of classes and bookings
		 */
		$table_name = $wpdb->prefix . "crm_class_list"; 
		$sql = "CREATE TABLE $table_name(
			list_id int(5) NOT NULL,
			age_slug tinytext NOT NULL,
			prod_id text NOT NULL,
			class_id int(11) NOT NULL,
			class_title tinytext NOT NULL,
			session_date datetime NOT NULL,
			location tinytext NOT NULL,
			duration int(4) NOT NULL,
			trainers text NOT NULL,
			semester_slug tinytext NOT NULL
			PRIMARY KEY  (list_id)
		) $charset_collate;";
		dbDelta( $sql );

		/*
		* Roster table
		*/
		$table_name = $wpdb->prefix . "crm_roster"; 
		$sql = "CREATE TABLE $table_name (
		roster_id int(11) NOT NULL AUTO_INCREMENT,
		start_time datetime DEFAULT '0000-00-00 00:00:00',
		trainer_id int(11),
		duration int(11),
		shift_type tinytext,
		location tinytext,
		PRIMARY KEY  (roster_id)
		) $charset_collate;";
		dbDelta( $sql );
		
		/**
		 * Semesters
		 */
		$table_name = $wpdb->prefix . "crm_semesters"; 
		$sql = "CREATE TABLE $table_name (
			semester_id int(11) NOT NULL,
			semester_slug tinytext NOT NULL,
			semester_start date NOT NULL,
			semester_end date NOT NULL
			PRIMARY KEY  (semester_id)
		) $charset_collate;";
		dbDelta( $sql );

		/*
		 * Student functionality
		 */
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
		
		/** Update db version & success message */
		update_option( 'akimbo_crm_db_version', $akimbo_crm_db_version );
		$update_message = "Plugin successfully updated";
	}
}

class AkimboCRM {
	/**
	*Constructor. Called when plugin is initialised
	*/
	function __construct(){
		
		add_action('admin_menu', array(&$this, 'akimbo_crm_admin_menu'));
		$this->includes();
		add_action('admin_init', array(&$this, 'akimbo_crm_register_settings'));
		add_action( 'plugins_loaded', array(&$this, 'akimbo_crm_update_db_check') );
		add_action('admin_enqueue_scripts', array(&$this, 'akimbo_crm_enqueue_styles') );
	}

	/**
	* Admin style sheet
	*/
	function akimbo_crm_enqueue_styles(){
		$current_screen = get_current_screen();
		//if ( $current_screen->parent_base != 'akimbo-crm') {//not currently working
		if ( strpos($current_screen->base, 'akimbo-crm') === false) {
			return;
		} else {
			wp_enqueue_style( 'admin-style', plugins_url( 'css/admin-style.css', __FILE__ ) ); 
		}	
		
	}

	/**
	 * Check db version and update if needed
	 */
	function akimbo_crm_update_db_check() {
		global $akimbo_crm_db_version;
		if ( get_site_option( 'akimbo_crm_db_version' ) != $akimbo_crm_db_version ) {
			akimbo_crm_create_db_tables();
		}
	}

	/**
	 * Add plugin to wordpress menu
	 */
	function akimbo_crm_admin_menu(){//Title, admin panel label, user capabilities, slug, function callback
		if (current_user_can('upload_files')){
			$parent_slug = "akimbo-crm";
			add_menu_page( 'Akimbo CRM', 'Akimbo CRM', 'upload_files', $parent_slug, array(&$this, 'akimbo_crm_init' ), 'dashicons-book-alt', '2');
			add_submenu_page( $parent_slug, 'Scheduling', 'Scheduling', 'upload_files', 'akimbo-crm2', array(&$this, 'manage_classes' ), 1);
			add_submenu_page( $parent_slug, 'Business Admin', 'Business', 'manage_options', 'akimbo-crm3', array(&$this, 'manage_orders' ), 3);
			add_submenu_page( $parent_slug, 'Settings', 'Settings', 'manage_options', 'crm-options', array(&$this, 'akimbo_crm_options_page' ), 4);
		}	
	}
	
	/**
	 * Plugin home page, accesible to author level users
	 */
	function akimbo_crm_init(){
		if (current_user_can('upload_files')){
			global $wpdb;
			$site = get_site_url();
			$today = current_time('Y-m-d');
			echo "<h2 class='nav-tab-wrapper'>";
				$page = "akimbo-crm";
				$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'home';
				echo crm_nav_tab($page, "home", "Home", $active_tab);
				echo crm_nav_tab($page, "classes", "Classes", $active_tab);
				echo crm_nav_tab($page, "details", "Student Details", $active_tab);
				echo crm_nav_tab($page, "availabilities", "Staff Portal", $active_tab);
			echo "</h2>";
			if(isset($_GET['message'])){
				$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Updates successful!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
				echo apply_filters('akimbo_crm_update_notice', $message);
			}
			switch ($active_tab) {
				case "home": echo apply_filters('akimbo_crm_admin_home', akimbo_crm_admin_home_page());
				break;
				case "classes": echo apply_filters('akimbo_crm_admin_manage_classes', akimbo_crm_manage_classes());
				break;
				case "details": echo apply_filters('akimbo_crm_student_details', akimbo_crm_student_details());
				break;
				case "availabilities": apply_filters('akimbo_crm_admin_availabilities', akimbo_crm_update_trainer_availabilities()); 
				echo apply_filters('akimbo_crm_admin_home_staff_details', akimbo_crm_staff_details());	
				break;
				default:
			}
		}else{
			wp_die( __("Sorry, you don't have permission to view this page. Please contact an admin if you think you should have access to this page") );
		}		
	}
	
	/**
	 * Schedule page, accessible by administrator level only
	 */
	function manage_classes(){
		if (current_user_can('manage_options')){
			global $wpdb;
			$today = current_time('Y-m-d');
			$site = get_site_url();
			echo "<h2 class='nav-tab-wrapper'>";
			$page = "akimbo-crm2";
			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'schedule'; 
			echo crm_nav_tab($page, "schedule", "Scheduling", $active_tab);
			echo crm_nav_tab($page, "enrolment", "Enrolment Troubleshooting", $active_tab);
			echo crm_nav_tab($page, "payments", "Late Payments", $active_tab);
			echo crm_nav_tab($page, "bookings", "Manage Availability", $active_tab);
			echo "</h2>";
			if(isset($_GET['message'])){
				$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Updates successful!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
				echo apply_filters('akimbo_crm_update_notice', $message);
			}
			switch ($active_tab) {
				case "schedule": echo apply_filters('akimbo_crm_admin_manage_classes_schedule', akimbo_crm_manage_schedules());	
				break;
				case "enrolment": echo apply_filters('akimbo_crm_admin_manage_classes_enrolments', akimbo_crm_enrolment_issues());
				break;
				case "payments": apply_filters('akimbo_crm_business details_payments', akimbo_crm_unpaid_students());
				break;
				case "bookings": apply_filters('akimbo_crm_admin_manage_booking_schedules', akimbo_crm_manage_booking_schedules());
				break;
				default:
			}
		}else{
			wp_die( __("Sorry, you don't have permission to edit schedules. Please contact an admin if you think you should have access to this page") );
		}		
		
	}

	function manage_orders(){
		if (current_user_can('manage_options')){
			echo "<h2>Manage Business</h2>";
			global $wpdb;
			echo "<h2 class='nav-tab-wrapper'>";
				$page = "akimbo-crm3";
				$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'statistics'; 
				echo crm_nav_tab($page, "statistics", "Student Statistics", $active_tab);
				echo crm_nav_tab($page, "business", "Business Details", $active_tab);
				echo crm_nav_tab($page, "mailchimp", "Mailchimp Integration", $active_tab);
				echo crm_nav_tab($page, "payroll", "Payroll", $active_tab);
				echo crm_nav_tab($page, "partydata", "Party Data", $active_tab);
			echo "</h2>";
			if(isset($_GET['message'])){
				$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Updates successful!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
				echo apply_filters('akimbo_crm_update_notice', $message);
			}
			switch ($active_tab) {
			    case "business": apply_filters('akimbo_crm3_business_details', akimbo_crm_business_details());//test info in akimbo crm 2.0 functions
			    break;
				case "statistics": apply_filters('akimbo_crm3_business details_statistics', akimbo_crm_student_statistics()); 	
				break;
				case "mailchimp": apply_filters('akimbo_crm3_business details_mailchimp', akimbo_crm_manage_mailchimp_integration($page, $active_tab)); 	
				break;
			    case "payroll": apply_filters('akimbo_crm3_business details_payroll', akimbo_crm_manage_payroll()); 	
				break;
				case "partydata": include 'includes/includes/party_data.php'; 
				default:
				
			}
		}else{
			wp_die( __("Sorry, you don't have permission to view statistics. Please contact an admin if you think you should have access to this page") );
		}
	}
	
	function akimbo_crm_register_settings(){
   		$args = array('type' => 'string','sanitize_callback' => 'sanitize_text_field',);
   		add_option( 'akimbo_crm_account_message', 'Welcome to your account dashboard.');
   		register_setting( 'akimbo_crm_options', 'akimbo_crm_account_message', $args );
		add_option( 'akimbo_crm_order_message', 'Thanks for ordering with us!');
		register_setting( 'akimbo_crm_options', 'akimbo_crm_new_user_message', $args );
   		add_option( 'akimbo_crm_new_user_message', 'A new account has been created for you with Akimbo CRM!');
   		register_setting( 'akimbo_crm_options', 'akimbo_crm_order_message', $args );
   		add_option( 'akimbo_crm_class_booking_window', '-24hrs');
		register_setting( 'akimbo_crm_options', 'akimbo_crm_class_booking_window', $args );
		//Booking settings
		add_option( 'akimbo_crm_booking_book_window', '-48hrs');
		register_setting( 'akimbo_crm_booking_options', 'akimbo_crm_class_booking_book_window', $args );
		add_option( 'akimbo_crm_booking_title', 'Private Booking');
		register_setting( 'akimbo_crm_booking_options', 'akimbo_crm_booking_title', $args );
		//Payroll settings
		add_option( 'akimbo_crm_pay_day', 'Thursday');
		register_setting( 'akimbo_crm_business_options', 'akimbo_crm_pay_day', $args );
		//Mailchimp settings   
		add_option( 'akimbo_crm_mailchimp_apikey', '123');
		register_setting( 'akimbo_crm_mailchimp_options', 'akimbo_crm_mailchimp_apikey', $args );
		add_option( 'akimbo_crm_mailchimp_server', 'us3');
		register_setting( 'akimbo_crm_mailchimp_options', 'akimbo_crm_mailchimp_server', $args );
		add_option( 'akimbo_crm_mailchimp_list_id', '456');
		register_setting( 'akimbo_crm_mailchimp_options', 'akimbo_crm_mailchimp_list_id', $args );
	}

	function akimbo_crm_options_page(){
		if (current_user_can('manage_options')){
			?><div><?php screen_icon(); ?>
			<!-- General Settings -->
			<h2>Akimbo CRM Options</h2><form method="post" action="options.php">
			<?php settings_fields( 'akimbo_crm_options' ); ?>
			<table>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_account_message">Account Message</label></th>
			<td><input type="text" id="akimbo_crm_account_message" name="akimbo_crm_account_message" value="<?php echo get_option('akimbo_crm_account_message'); ?>" size="50"/></td></tr>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_order_message">Order Message</label></th>
			<td><input type="text" id="akimbo_crm_order_message" name="akimbo_crm_order_message" value="<?php echo get_option('akimbo_crm_order_message'); ?>" size="50"/></td></tr>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_new_user_message">New User Email</label></th>
			<td><input type="text" id="akimbo_crm_new_user_message" name="akimbo_crm_new_user_message" value="<?php echo get_option('akimbo_crm_new_user_message'); ?>" size="50"/></td></tr>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_class_booking_window">Booking Window (Classes)</label></th>
			<td><input type="text" id="akimbo_crm_class_booking_window" name="akimbo_crm_class_booking_window" value="<?php echo get_option('akimbo_crm_class_booking_window'); ?>" size="50"/></td></tr>
			</table><?php  submit_button(); ?></form></div>
			<!-- Booking Settings -->
			<div><?php screen_icon(); ?>
			<h2>Booking Settings</h2><form method="post" action="options.php">
			<?php settings_fields( 'akimbo_crm_booking_options' ); ?>
			<table>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_booking_book_window">Booking Window (Bookings)</label></th>
			<td><input type="text" id="akimbo_crm_booking_book_window" name="akimbo_crm_booking_book_window" value="<?php echo get_option('akimbo_crm_booking_book_window'); ?>" size="50"/></td></tr>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_booking_title">Booking Title</label></th>
			<td><input type="text" id="akimbo_crm_booking_title" name="akimbo_crm_booking_title" value="<?php echo get_option('akimbo_crm_booking_title'); ?>" size="50"/></td></tr>
			</table><?php  submit_button(); ?></form></div>
			<!-- Payroll Settings -->
			<div><?php screen_icon(); ?>
			<h2>Payroll Settings</h2><form method="post" action="options.php">
			<?php settings_fields( 'akimbo_crm_business_options' ); ?>
			<table>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_pay_day">Pay Day:</label></th>
			<td><input type="text" id="akimbo_crm_pay_day" name="akimbo_crm_pay_day" value="<?php echo get_option('akimbo_crm_pay_day'); ?>" size="50"/></td></tr>
			</table><?php  submit_button(); ?></form></div>
			<!-- Mailchimp Settings -->
			<div><?php screen_icon(); ?>
			<h2>Mailchimp Settings</h2><form method="post" action="options.php">
			<?php settings_fields( 'akimbo_crm_mailchimp_options' ); ?>
			<table>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_mailchimp_apikey">API Key:</label></th>
			<td><input type="text" id="akimbo_crm_mailchimp_apikey" name="akimbo_crm_mailchimp_apikey" value="<?php echo get_option('akimbo_crm_mailchimp_apikey'); ?>" size="50"/></td></tr>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_mailchimp_server">Server:</label></th>
			<td><input type="text" id="akimbo_crm_mailchimp_server" name="akimbo_crm_mailchimp_server" value="<?php echo get_option('akimbo_crm_mailchimp_server'); ?>" size="50"/></td></tr>
			<tr valign="top"><th scope="row"><label for="akimbo_crm_mailchimp_list_id">List ID:</label></th>
			<td><input type="text" id="akimbo_crm_mailchimp_list_id" name="akimbo_crm_mailchimp_list_id" value="<?php echo get_option('akimbo_crm_mailchimp_list_id'); ?>" size="50"/></td></tr>
			</table><?php  submit_button(); ?></form></div><?php

		}else{
			wp_die( __("Sorry, you don't have permission to edit settings. Please contact an admin if you think you should have access to this page") );
		}
	}

	public function includes() {


		/**
		 * Core classes.
		 */
		include_once 'includes/class-akimbo-crm-availability.php';
		include_once 'includes/class-akimbo-crm-booking.php';
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
		include_once 'includes/akimbo-crm-mailchimp-functions.php';
		include_once 'includes/akimbo-crm-order-functions.php';
		include_once 'includes/akimbo-crm-scheduling-functions.php';
		include_once 'includes/akimbo-crm-staff-functions.php';
		include_once 'includes/akimbo-crm-student-functions.php';
		include_once 'includes/akimbo-crm-user-functions.php';
		

		//include_once WC_ABSPATH . 'includes/class-wc-datetime.php';
		
	}	
}

$akimboCRM = new AkimboCRM;

 



