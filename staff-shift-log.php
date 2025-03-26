<?php  
/*
 * Plugin Name:       Staff Shift Log  
 * Plugin URI:        https://github.dev/Gusphiri/staff-shift-log-Legacy
 * Description:       Log and manage staff shift requests. 
 * Version:           1.0
 * Requires at least: 5.8+
 * Requires PHP:      7.4+ 
 * Requires MySQL:    5.7+
 * Author:            Augustin Phiri
 * Author URI:        https://augustinphiri.co.za/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://augustinphiri.co.za/the-plugs/
 * Text Domain:       staff-shift-log-Legacy
 * Domain Path:       /languages
 */

// Load dependencies  
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';  
require_once plugin_dir_path(__FILE__) . 'includes/shift-submission.php';  
require_once plugin_dir_path(__FILE__) . 'includes/calendar-display.php';  

// Load CSS/JS  
add_action('wp_enqueue_scripts', 'ssl_enqueue_scripts');  
function ssl_enqueue_scripts() {  
    wp_enqueue_style('ssl-styles', plugins_url('assets/styles.css', __FILE__));  
    wp_enqueue_script('ssl-scripts', plugins_url('assets/scripts.js', __FILE__), array('jquery'), null, true);  
    wp_localize_script('ssl-scripts', 'ssl_ajax', array('ajax_url' => admin_url('admin-ajax.php')));  
}  

// Create custom table on plugin activation  
register_activation_hook(__FILE__, 'ssl_create_shift_table');  
function ssl_create_shift_table() {  
    global $wpdb;  
    $table_name = $wpdb->prefix . 'staff_shifts';  
    $charset_collate = $wpdb->get_charset_collate();  

    $sql = "CREATE TABLE $table_name (  
        id mediumint(9) NOT NULL AUTO_INCREMENT,  
        staff_name varchar(100) NOT NULL,  
        shift_date date NOT NULL,  
        shift_type varchar(50) NOT NULL,  
        status varchar(20) DEFAULT 'pending',  
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP,  
        PRIMARY KEY (id)  
    ) $charset_collate;";  

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';  
    dbDelta($sql);  

    // Verify table creation  
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {  
        error_log('Staff Shift Log: Failed to create table ' . $table_name);  
    }  
}  

// Drop table on deactivation  
register_deactivation_hook(__FILE__, 'ssl_drop_shift_table');  
function ssl_drop_shift_table() {  
    global $wpdb;  
    $table_name = $wpdb->prefix . 'staff_shifts';  
    $wpdb->query("DROP TABLE IF EXISTS $table_name");  
}  

// Load FullCalendar  
add_action('wp_enqueue_scripts', 'ssl_load_fullcalendar');  
function ssl_load_fullcalendar() {  
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array(), null, true);  
    wp_enqueue_style('ssl-calendar-styles', plugins_url('assets/calendar.css', __FILE__));  
}  