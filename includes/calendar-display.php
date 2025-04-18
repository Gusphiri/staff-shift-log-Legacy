<?php 

/**
 * Staff Shift Log - Calendar Display
 * 
 * This file handles the calendar display and AJAX endpoints for shift data.
 */

/**
 * AJAX endpoint for retrieving shifts
 */
add_action('wp_ajax_get_shifts', 'ssl_get_shifts');  
function ssl_get_shifts() {  
    if (!is_user_logged_in()) {  
        wp_send_json_error('You must be logged in to view shifts');  
        return;  
    }  

    global $wpdb;  
    $shifts_table = $wpdb->prefix . 'staff_shifts';  
    $requests_table = $wpdb->prefix . 'shift_requests';  

    // Check if tables exist
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    if (!$shifts_table_exists || !$requests_table_exists) {
        // Tables don't exist, try to create them
        ssl_create_shift_table();
        
        // Check again after creation attempt
        $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
        $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
        
        if (!$shifts_table_exists || !$requests_table_exists) {
            $error_message = 'Database tables could not be created. Please contact an administrator.';
            if (!empty($wpdb->last_error)) {
                $error_message .= ' Error: ' . $wpdb->last_error;
            }
            wp_send_json_error($error_message);
            return;
        }
    }

    // Get shifts based on user role  
    if (current_user_can('manage_options')) {  
        // Admin sees all shifts  
        $shifts = $wpdb->get_results("SELECT s.*, u.display_name FROM $shifts_table s JOIN {$wpdb->prefix}users u ON s.staff_id = u.ID");  
        $requests = $wpdb->get_results("SELECT r.*, u.display_name FROM $requests_table r JOIN {$wpdb->prefix}users u ON r.staff_id = u.ID");  
    } else {  
        // Regular users see only their shifts  
        $user_id = get_current_user_id();  
        $shifts = $wpdb->get_results($wpdb->prepare("SELECT s.*, u.display_name FROM $shifts_table s JOIN {$wpdb->prefix}users u ON s.staff_id = u.ID WHERE s.staff_id = %d", $user_id));  
        $requests = $wpdb->get_results($wpdb->prepare("SELECT r.*, u.display_name FROM $requests_table r JOIN {$wpdb->prefix}users u ON r.staff_id = u.ID WHERE r.staff_id = %d", $user_id));  
    }  

    if ($wpdb->last_error) {  
        wp_send_json_error('Database error: ' . $wpdb->last_error);  
        return;  
    }  

    $events = array();  

    // Add approved shifts  
    if (is_array($shifts)) {
        foreach ($shifts as $shift) {  
            $events[] = array(  
                'title' => $shift->display_name . ' - ' . ucfirst($shift->shift_type),  
                'start' => $shift->shift_date,  
                'color' => '#28a745',  
                'textColor' => '#fff'  
            );  
        }
    }

    // Add pending requests  
    if (is_array($requests)) {
        foreach ($requests as $request) {  
            $events[] = array(  
                'title' => $request->display_name . ' - ' . ucfirst($request->shift_type) . ' (Pending)',  
                'start' => $request->shift_date,  
                'color' => '#ffc107',  
                'textColor' => '#000'  
            );  
        }
    }

    wp_send_json_success($events);  
}  

/**
 * Shortcode for displaying the shift calendar
 */
add_shortcode('staff_shift_calendar', 'ssl_shift_calendar');  
function ssl_shift_calendar() {  
    if (!is_user_logged_in()) {  
        return 'Please log in to view the shift calendar.';  
    }  

    // Enqueue FullCalendar - use the same version as in the main plugin file
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array('jquery'), '6.1.10', true);  
    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css', array(), '6.1.10');  
    
    // Enqueue our calendar styles
    wp_enqueue_style('ssl-calendar-styles', plugins_url('assets/calendar.css', dirname(__FILE__)), array(), '1.0.0');

    ob_start();  
    ?>  
    <div id="calendar"></div>  
    <script>  
    document.addEventListener('DOMContentLoaded', function() {  
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) {
            console.error('Calendar element not found');
            return;
        }
        
        var calendar = new FullCalendar.Calendar(calendarEl, {  
            initialView: 'dayGridMonth',  
            headerToolbar: {  
                left: 'prev,next today',  
                center: 'title',  
                right: 'dayGridMonth,timeGridWeek,timeGridDay'  
            },  
            events: function(info, successCallback, failureCallback) {  
                jQuery.ajax({  
                    url: ssl_ajax.ajax_url,  // Use the localized ajax_url
                    type: 'POST',  
                    data: {  
                        action: 'get_shifts'  
                    },  
                    success: function(response) {  
                        if (response.success) {  
                            successCallback(response.data);
                        } else {  
                            console.error('Calendar error:', response.data);
                            failureCallback(response.data);  
                        }  
                    },  
                    error: function(xhr, status, error) {  
                        console.error('AJAX error:', status, error);
                        failureCallback('Error retrieving shifts: ' + error);  
                    }  
                });  
            },  
            eventDidMount: function(info) {  
                // Check if jQuery tooltip is available
                if (jQuery.fn.tooltip) {
                    jQuery(info.el).tooltip({  
                        title: info.event.title,  
                        placement: 'top',  
                        trigger: 'hover',  
                        container: 'body'  
                    });  
                }
            }  
        });  
        calendar.render();  
    });  
    </script>  
    <?php  
    return ob_get_clean();  
}