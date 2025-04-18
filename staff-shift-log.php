<?php  

/*
 * Plugin Name:       Staff Shift Log  
 * Plugin URI:        https://augustinphiri.co.za/the-plugs/#ap-slp-plugs
 * Description:       Log and manage staff shift requests with admin approval. New updates include staff submission on dashboard and admin approval.
 * Version:           1.5
 * Requires at least: 5.8+
 * Requires PHP:      7.4+ 
 * Requires MySQL:    5.7+
 * Author:            Augustin Phiri
 * Author URI:        https://augustinphiri.co.za/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://augustinphiri.co.za/the-plugs/
 * Text Domain:       staff-shift-log
 * Domain Path:       /languages
 */


// Load dependencies  
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';  
require_once plugin_dir_path(__FILE__) . 'includes/shift-submission.php';  
require_once plugin_dir_path(__FILE__) . 'includes/calendar-display.php';  

// Load text domain for translations  
add_action('plugins_loaded', 'ssl_load_textdomain');  
function ssl_load_textdomain() {  
    load_plugin_textdomain('staff-shift-log', false, dirname(plugin_basename(__FILE__)) . '/languages/');  
}  

// Add dashboard widget for staff shift form
add_action('wp_dashboard_setup', 'ssl_add_dashboard_widget');
function ssl_add_dashboard_widget() {
    // Only show to logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    wp_add_dashboard_widget(
        'ssl_shift_form_widget',
        __('Staff Shift Form', 'staff-shift-log'),
        'ssl_dashboard_widget_content'
    );
}

// Add admin bar menu for quick access
add_action('admin_bar_menu', 'ssl_add_admin_bar_menu', 100);
function ssl_add_admin_bar_menu($admin_bar) {
    // Only show to logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    // Get the page ID where the shortcode is used
    $pages = get_pages();
    $form_page_id = null;
    
    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, 'staff_shift_form')) {
            $form_page_id = $page->ID;
            break;
        }
    }
    
    if ($form_page_id) {
        $form_page_url = get_permalink($form_page_id);
        
        // Add main menu item
        $admin_bar->add_menu(array(
            'id'    => 'ssl-shift-menu',
            'title' => __('Staff Shifts', 'staff-shift-log'),
            'href'  => $form_page_url,
            'meta'  => array(
                'title' => __('Staff Shift Management', 'staff-shift-log'),
            ),
        ));
        
        // Add submenu items
        $admin_bar->add_menu(array(
            'id'     => 'ssl-submit-shift',
            'parent' => 'ssl-shift-menu',
            'title'  => __('Submit Shift Request', 'staff-shift-log'),
            'href'   => $form_page_url,
        ));
        
        // Check if calendar shortcode exists on any page
        $calendar_page_id = null;
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'staff_shift_calendar')) {
                $calendar_page_id = $page->ID;
                break;
            }
        }
        
        if ($calendar_page_id) {
            $calendar_page_url = get_permalink($calendar_page_id);
            
            $admin_bar->add_menu(array(
                'id'     => 'ssl-view-calendar',
                'parent' => 'ssl-shift-menu',
                'title'  => __('View Calendar', 'staff-shift-log'),
                'href'   => $calendar_page_url,
            ));
        }
        
        // Add admin page link for administrators
        if (current_user_can('manage_options')) {
            // Check for pending shift requests
            global $wpdb;
            $requests_table = $wpdb->prefix . 'shift_requests';
            $pending_count = 0;
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table) {
                $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $requests_table WHERE status = 'pending'");
            }
            
            $admin_title = __('Manage Shifts', 'staff-shift-log');
            if ($pending_count > 0) {
                $admin_title .= ' <span class="ssl-pending-count">' . $pending_count . '</span>';
            }
            
            $admin_bar->add_menu(array(
                'id'     => 'ssl-admin-page',
                'parent' => 'ssl-shift-menu',
                'title'  => $admin_title,
                'href'   => admin_url('admin.php?page=staff-shift-log'),
            ));
        }
    }
}

// Add CSS for the admin bar menu
add_action('admin_head', 'ssl_admin_bar_css');
function ssl_admin_bar_css() {
    ?>
    <style>
        #wp-admin-bar-ssl-shift-menu .ssl-pending-count {
            background-color: #d63638;
            color: #fff;
            border-radius: 50%;
            display: inline-block;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.4;
            margin-left: 5px;
            min-width: 18px;
            padding: 0 5px;
            text-align: center;
        }
    </style>
    <?php
}

function ssl_dashboard_widget_content() {
    // Get the page ID where the shortcode is used
    $pages = get_pages();
    $form_page_id = null;
    
    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, 'staff_shift_form')) {
            $form_page_id = $page->ID;
            break;
        }
    }
    
    if ($form_page_id) {
        $form_page_url = get_permalink($form_page_id);
        
        // Get user's upcoming shifts and recent requests
        global $wpdb;
        $user_id = get_current_user_id();
        $shifts_table = $wpdb->prefix . 'staff_shifts';
        $requests_table = $wpdb->prefix . 'shift_requests';
        
        $upcoming_shifts = array();
        $recent_requests = array();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table) {
            $upcoming_shifts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $shifts_table 
                    WHERE staff_id = %d 
                    AND shift_date >= CURDATE() 
                    ORDER BY shift_date ASC 
                    LIMIT 3",
                    $user_id
                )
            );
        }
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table) {
            $recent_requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $requests_table 
                    WHERE staff_id = %d 
                    ORDER BY submitted_at DESC 
                    LIMIT 3",
                    $user_id
                )
            );
        }
        
        ?>
        <div class="ssl-dashboard-widget">
            <p><?php _e('Submit your shift requests or view your schedule.', 'staff-shift-log'); ?></p>
            <div class="ssl-dashboard-buttons">
                <a href="<?php echo esc_url($form_page_url); ?>" class="button button-primary">
                    <?php _e('Submit Shift Request', 'staff-shift-log'); ?>
                </a>
                
                <?php
                // Check if calendar shortcode exists on any page
                $calendar_page_id = null;
                foreach ($pages as $page) {
                    if (has_shortcode($page->post_content, 'staff_shift_calendar')) {
                        $calendar_page_id = $page->ID;
                        break;
                    }
                }
                
                if ($calendar_page_id) {
                    $calendar_page_url = get_permalink($calendar_page_id);
                    ?>
                    <a href="<?php echo esc_url($calendar_page_url); ?>" class="button">
                        <?php _e('View Calendar', 'staff-shift-log'); ?>
                    </a>
                    <?php
                }
                ?>
            </div>
            
            <?php if (!empty($upcoming_shifts)): ?>
            <div class="ssl-upcoming-shifts">
                <h3><?php _e('Your Upcoming Shifts', 'staff-shift-log'); ?></h3>
                <ul>
                    <?php foreach ($upcoming_shifts as $shift): ?>
                    <li>
                        <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($shift->shift_date))); ?></strong>
                        - <?php echo esc_html($shift->shift_type); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($recent_requests)): ?>
            <div class="ssl-recent-requests">
                <h3><?php _e('Your Recent Requests', 'staff-shift-log'); ?></h3>
                <ul>
                    <?php foreach ($recent_requests as $request): ?>
                    <li>
                        <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request->shift_date))); ?></strong>
                        - <?php echo esc_html($request->shift_type); ?>
                        <span class="ssl-status ssl-status-<?php echo esc_attr($request->status); ?>">
                            <?php echo esc_html(ucfirst($request->status)); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <style>
            .ssl-dashboard-widget {
                padding: 10px 0;
            }
            .ssl-dashboard-buttons {
                margin: 15px 0;
            }
            .ssl-dashboard-buttons .button {
                margin-right: 10px;
            }
            .ssl-upcoming-shifts, .ssl-recent-requests {
                margin-top: 20px;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
            .ssl-upcoming-shifts h3, .ssl-recent-requests h3 {
                margin-top: 0;
                font-size: 14px;
            }
            .ssl-upcoming-shifts ul, .ssl-recent-requests ul {
                margin: 0;
                padding: 0;
            }
            .ssl-upcoming-shifts li, .ssl-recent-requests li {
                margin-bottom: 8px;
                list-style: none;
            }
            .ssl-status {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                margin-left: 5px;
            }
            .ssl-status-pending {
                background-color: #f0b849;
                color: #fff;
            }
            .ssl-status-approved {
                background-color: #46b450;
                color: #fff;
            }
            .ssl-status-rejected {
                background-color: #dc3232;
                color: #fff;
            }
        </style>
        <?php
    } else {
        ?>
        <div class="ssl-dashboard-widget">
            <p><?php _e('The shift form page has not been set up yet. Please contact your administrator.', 'staff-shift-log'); ?></p>
        </div>
        <?php
    }
}

// Check if tables exist and create them if needed
add_action('plugins_loaded', 'ssl_ensure_tables_exist');
function ssl_ensure_tables_exist() {
    global $wpdb;
    $shifts_table = $wpdb->prefix . 'staff_shifts';
    $requests_table = $wpdb->prefix . 'shift_requests';
    
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    if (!$shifts_table_exists || !$requests_table_exists) {
        // Tables don't exist, try to create them
        ssl_create_shift_table();
        
        // Check again after creation attempt
        $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
        $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
        
        if (!$shifts_table_exists || !$requests_table_exists) {
            // Add admin notice
            add_action('admin_notices', 'ssl_admin_notice_tables_missing');
        }
    }
}

// Admin notice for missing tables
function ssl_admin_notice_tables_missing() {
    global $wpdb;
    $shifts_table = $wpdb->prefix . 'staff_shifts';
    $requests_table = $wpdb->prefix . 'shift_requests';
    
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    $error_details = '';
    if (!empty($wpdb->last_error)) {
        $error_details = '<p><strong>Database Error:</strong> ' . esc_html($wpdb->last_error) . '</p>';
    }
    
    $table_status = '<p><strong>Table Status:</strong></p>';
    $table_status .= '<ul>';
    $table_status .= '<li>Staff Shifts Table: ' . ($shifts_table_exists ? 'Exists' : 'Missing') . '</li>';
    $table_status .= '<li>Shift Requests Table: ' . ($requests_table_exists ? 'Exists' : 'Missing') . '</li>';
    $table_status .= '</ul>';
    
    ?>
    <div class="error">
        <p><?php _e('Staff Shift Log: Database tables could not be created. Please check your database permissions or contact your administrator.', 'staff-shift-log'); ?></p>
        <p><?php _e('You can try deactivating and reactivating the plugin to trigger the table creation again.', 'staff-shift-log'); ?></p>
        <?php echo $error_details; ?>
        <?php echo $table_status; ?>
        <p><strong>Troubleshooting Steps:</strong></p>
        <ol>
            <li><?php _e('Deactivate and reactivate the plugin', 'staff-shift-log'); ?></li>
            <li><?php _e('Check if your database user has CREATE TABLE permissions', 'staff-shift-log'); ?></li>
            <li><?php _e('Check your WordPress debug log for additional errors', 'staff-shift-log'); ?></li>
            <li><?php _e('If using a caching plugin, clear the cache', 'staff-shift-log'); ?></li>
        </ol>
    </div>
    <?php
}

// Load CSS/JS  
add_action('wp_enqueue_scripts', 'ssl_enqueue_scripts');  
function ssl_enqueue_scripts() {  
    wp_enqueue_style('ssl-styles', plugins_url('assets/styles.css', __FILE__));  
    wp_enqueue_script('ssl-scripts', plugins_url('assets/scripts.js', __FILE__), array('jquery'), null, true);  
    wp_localize_script('ssl-scripts', 'ssl_ajax', array('ajax_url' => admin_url('admin-ajax.php')));  
}  

// Load FullCalendar  
add_action('wp_enqueue_scripts', 'ssl_load_fullcalendar');  
function ssl_load_fullcalendar() {  
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array('jquery'), '6.1.10', true);  
    wp_enqueue_style('ssl-calendar-styles', plugins_url('assets/calendar.css', __FILE__));  
    
    // Localize the AJAX URL for the calendar
    wp_localize_script('fullcalendar', 'ssl_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}  

// Create custom tables on plugin activation  
register_activation_hook(__FILE__, 'ssl_create_shift_table');  
function ssl_create_shift_table() {  
    global $wpdb;  
    $charset_collate = $wpdb->get_charset_collate();  

    $shifts_table = $wpdb->prefix . 'staff_shifts';  
    $requests_table = $wpdb->prefix . 'shift_requests';  

    // Check if tables already exist
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    if ($shifts_table_exists && $requests_table_exists) {
        // Tables already exist, no need to create them
        return;
    }

    // Try without foreign key constraints (more compatible)
    $sql_shifts = "CREATE TABLE $shifts_table (  
        id mediumint(9) NOT NULL AUTO_INCREMENT,  
        staff_id mediumint(9) NOT NULL,  
        shift_date date NOT NULL,  
        shift_type varchar(50) NOT NULL,  
        status varchar(20) DEFAULT 'approved',  
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP,  
        PRIMARY KEY (id)  
    ) $charset_collate;";  

    $sql_requests = "CREATE TABLE $requests_table (  
        id mediumint(9) NOT NULL AUTO_INCREMENT,  
        staff_id mediumint(9) NOT NULL,  
        shift_date date NOT NULL,  
        shift_type varchar(50) NOT NULL,  
        status varchar(20) DEFAULT 'pending',  
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP,  
        PRIMARY KEY (id)  
    ) $charset_collate;";  

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';  
    
    // Try to create tables with dbDelta
    dbDelta($sql_shifts);  
    dbDelta($sql_requests);  

    // Check if tables were created successfully
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    if ($shifts_table_exists && $requests_table_exists) {
        // Tables created successfully
        return;
    }
    
    // If tables weren't created, try direct SQL queries
    if (!$shifts_table_exists) {
        $result = $wpdb->query($sql_shifts);
        if ($result === false) {
            error_log('Staff Shift Log: Failed to create shifts table. Error: ' . $wpdb->last_error);
        }
    }
    
    if (!$requests_table_exists) {
        $result = $wpdb->query($sql_requests);
        if ($result === false) {
            error_log('Staff Shift Log: Failed to create requests table. Error: ' . $wpdb->last_error);
        }
    }
    
    // Check again if tables exist
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    if (!$shifts_table_exists || !$requests_table_exists) {  
        error_log('Staff Shift Log: Failed to create tables. Shifts table exists: ' . ($shifts_table_exists ? 'yes' : 'no') . ', Requests table exists: ' . ($requests_table_exists ? 'yes' : 'no'));
    } else {
        error_log('Staff Shift Log: Tables created successfully');
    }
}  

// Drop tables on deactivation  
register_deactivation_hook(__FILE__, 'ssl_drop_shift_table');  
function ssl_drop_shift_table() {  
    global $wpdb;  
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}shift_requests");  
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}staff_shifts");  
}

// Add AJAX handler for refreshing admin bar count
add_action('wp_ajax_ssl_refresh_admin_bar_count', 'ssl_refresh_admin_bar_count');
function ssl_refresh_admin_bar_count() {
    // Check if user is logged in and has admin capabilities
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'shift_requests';
    $pending_count = 0;
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table) {
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $requests_table WHERE status = 'pending'");
    }
    
    wp_send_json_success(array('count' => $pending_count));
}

// Add JavaScript to refresh the count periodically
add_action('admin_footer', 'ssl_admin_bar_count_script');
function ssl_admin_bar_count_script() {
    // Only add the script for logged-in users with admin capabilities
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Function to update the count
        function updatePendingCount() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ssl_refresh_admin_bar_count'
                },
                success: function(response) {
                    if (response.success) {
                        var count = response.data.count;
                        var $countElement = $('#wp-admin-bar-ssl-admin-page .ssl-pending-count');
                        
                        if (count > 0) {
                            if ($countElement.length) {
                                $countElement.text(count);
                            } else {
                                $('#wp-admin-bar-ssl-admin-page .ab-item').append(' <span class="ssl-pending-count">' + count + '</span>');
                            }
                        } else {
                            $countElement.remove();
                        }
                    }
                }
            });
        }
        
        // Update count every 30 seconds
        setInterval(updatePendingCount, 30000);
    });
    </script>
    <?php
}

// Add calendar download functionality
add_action('admin_post_ssl_download_calendar', 'ssl_download_calendar');
function ssl_download_calendar() {
    // Check if user is logged in and has admin capabilities
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'staff-shift-log'));
    }
    
    // Verify nonce
    if (!isset($_POST['ssl_calendar_nonce']) || !wp_verify_nonce($_POST['ssl_calendar_nonce'], 'ssl_download_calendar')) {
        wp_die(__('Security check failed.', 'staff-shift-log'));
    }
    
    // Get the selected month and format
    $calendar_month = isset($_POST['calendar_month']) ? sanitize_text_field($_POST['calendar_month']) : date('Y-m');
    $calendar_format = isset($_POST['calendar_format']) ? sanitize_text_field($_POST['calendar_format']) : 'csv';
    
    // Parse the month and year
    $parts = explode('-', $calendar_month);
    if (count($parts) !== 2) {
        wp_die(__('Invalid month format.', 'staff-shift-log'));
    }
    
    $year = intval($parts[0]);
    $month = intval($parts[1]);
    
    // Get the first and last day of the month
    $first_day = date('Y-m-01', strtotime("$year-$month-01"));
    $last_day = date('Y-m-t', strtotime("$year-$month-01"));
    
    // Get all shifts for the selected month
    global $wpdb;
    $shifts_table = $wpdb->prefix . 'staff_shifts';
    
    $shifts = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, u.display_name as staff_name 
        FROM $shifts_table s 
        LEFT JOIN {$wpdb->users} u ON s.staff_id = u.ID 
        WHERE s.shift_date BETWEEN %s AND %s 
        ORDER BY s.shift_date ASC, s.staff_id ASC",
        $first_day,
        $last_day
    ));
    
    // Prepare the data for export
    $export_data = array();
    
    // Add header row
    $export_data[] = array(
        __('Date', 'staff-shift-log'),
        __('Staff', 'staff-shift-log'),
        __('Shift Type', 'staff-shift-log'),
        __('Status', 'staff-shift-log')
    );
    
    // Add shift data
    foreach ($shifts as $shift) {
        $export_data[] = array(
            date_i18n(get_option('date_format'), strtotime($shift->shift_date)),
            $shift->staff_name ?: $shift->staff_id,
            $shift->shift_type,
            ucfirst($shift->status)
        );
    }
    
    // Generate the file based on the selected format
    switch ($calendar_format) {
        case 'csv':
            ssl_export_csv($export_data, "staff-shifts-$year-$month.csv");
            break;
        case 'excel':
            ssl_export_excel($export_data, "staff-shifts-$year-$month.xlsx");
            break;
        case 'pdf':
            ssl_export_pdf($export_data, "staff-shifts-$year-$month.pdf", $year, $month);
            break;
        default:
            wp_die(__('Invalid export format.', 'staff-shift-log'));
    }
    
    exit;
}

// Export data as CSV
function ssl_export_csv($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

// Export data as Excel (XLSX)
function ssl_export_excel($data, $filename) {
    // Check if PhpSpreadsheet is available
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Try to include PhpSpreadsheet if it's not already loaded
        if (file_exists(ABSPATH . 'wp-content/plugins/staff-shift-log/vendor/autoload.php')) {
            require_once ABSPATH . 'wp-content/plugins/staff-shift-log/vendor/autoload.php';
        } else {
            // If PhpSpreadsheet is not available, fall back to CSV
            ssl_export_csv($data, str_replace('.xlsx', '.csv', $filename));
            return;
        }
    }
    
    // Create a new Spreadsheet object
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add data to the sheet
    foreach ($data as $row_index => $row) {
        foreach ($row as $col_index => $value) {
            $sheet->setCellValueByColumnAndRow($col_index + 1, $row_index + 1, $value);
        }
    }
    
    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Create Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}

// Export data as PDF
function ssl_export_pdf($data, $filename, $year, $month) {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        // Try to include TCPDF if it's not already loaded
        if (file_exists(ABSPATH . 'wp-content/plugins/staff-shift-log/vendor/tecnickcom/tcpdf/tcpdf.php')) {
            require_once ABSPATH . 'wp-content/plugins/staff-shift-log/vendor/tecnickcom/tcpdf/tcpdf.php';
        } else {
            // If TCPDF is not available, fall back to CSV
            ssl_export_csv($data, str_replace('.pdf', '.csv', $filename));
            return;
        }
    }
    
    // Create a new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Staff Shift Log');
    $pdf->SetTitle(sprintf(__('Staff Shifts - %s %s', 'staff-shift-log'), date_i18n('F', strtotime("$year-$month-01")), $year));
    
    // Set default header data
    $pdf->SetHeaderData('', 0, sprintf(__('Staff Shifts - %s %s', 'staff-shift-log'), date_i18n('F', strtotime("$year-$month-01")), $year), '');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Create the table
    $html = '<table border="1" cellpadding="4">
        <tr style="background-color: #f2f2f2; font-weight: bold;">
            <th>' . $data[0][0] . '</th>
            <th>' . $data[0][1] . '</th>
            <th>' . $data[0][2] . '</th>
            <th>' . $data[0][3] . '</th>
        </tr>';
    
    // Add data rows
    for ($i = 1; $i < count($data); $i++) {
        $html .= '<tr>';
        foreach ($data[$i] as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    // Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output($filename, 'D');
}