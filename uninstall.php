<?php
/**
 * Uninstall Staff Shift Log
 *
 * Deletes all plugin data when the plugin is uninstalled.
 *
 * @package Staff_Shift_Log
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('staff_shift_log_version');

// Drop custom tables
global $wpdb;
$shifts_table = $wpdb->prefix . 'staff_shifts';
$requests_table = $wpdb->prefix . 'shift_requests';

// Check if tables exist before dropping
$shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") === $shifts_table;
$requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") === $requests_table;

if ($shifts_table_exists) {
    $wpdb->query("DROP TABLE IF EXISTS $shifts_table");
}

if ($requests_table_exists) {
    $wpdb->query("DROP TABLE IF EXISTS $requests_table");
}

// Clear any cached data
wp_cache_flush(); 