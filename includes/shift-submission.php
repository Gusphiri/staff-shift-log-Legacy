<?php 
/**
 * Staff Shift Log - Shift Submission Form
 * 
 * This file handles the shift submission form and AJAX processing.
 */

// Shortcode: [staff_shift_form] 
add_shortcode('staff_shift_form', 'ssl_shift_form');
function ssl_shift_form() {  
    if (!is_user_logged_in()) {  
        return __('Please log in to submit shift requests.', 'staff-shift-log');  
    }  

    ob_start();  
    ?>  
    <form id="shift-form">  
        <p>  
            <label for="shift_date"><?php _e('Date:', 'staff-shift-log'); ?></label>  
            <input type="date" id="shift_date" name="shift_date" required>  
        </p>  
        <p>  
            <label for="shift_type"><?php _e('Shift Type:', 'staff-shift-log'); ?></label>  
            <select id="shift_type" name="shift_type" required>  
                <option value=""><?php _e('Select Shift Type', 'staff-shift-log'); ?></option>  
                <option value="morning"><?php _e('Morning', 'staff-shift-log'); ?></option>  
                <option value="afternoon"><?php _e('Afternoon', 'staff-shift-log'); ?></option>  
                <option value="evening"><?php _e('Evening', 'staff-shift-log'); ?></option>  
            </select>  
        </p>  
        <?php wp_nonce_field('shift_form_nonce', 'shift_nonce'); ?>  
        <p><button type="submit"><?php _e('Submit Request', 'staff-shift-log'); ?></button></p>  
        <div id="shift-response"></div>  
    </form>  
    <?php  
    return ob_get_clean();  
}  

/**
 * AJAX Handler for shift submission
 */
add_action('wp_ajax_submit_shift', 'ssl_handle_shift_submission');  
function ssl_handle_shift_submission() {  
    // Verify nonce
    if (!isset($_POST['shift_nonce']) || !wp_verify_nonce($_POST['shift_nonce'], 'shift_form_nonce')) {  
        wp_send_json_error('Invalid nonce');  
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

    $staff_id = get_current_user_id();  
    $shift_date = sanitize_text_field($_POST['shift_date']);  
    $shift_type = sanitize_text_field($_POST['shift_type']);  

    // Check if shift already exists  
    $existing_shift = $wpdb->get_var($wpdb->prepare(  
        "SELECT id FROM $shifts_table WHERE staff_id = %d AND shift_date = %s",  
        $staff_id,  
        $shift_date  
    ));  

    if ($existing_shift) {  
        wp_send_json_error('You already have a shift on this date');  
        return;  
    }  

    // Check if request already exists  
    $existing_request = $wpdb->get_var($wpdb->prepare(  
        "SELECT id FROM $requests_table WHERE staff_id = %d AND shift_date = %s",  
        $staff_id,  
        $shift_date  
    ));  

    if ($existing_request) {  
        wp_send_json_error('You already have a pending request for this date');  
        return;  
    }  

    // Insert request  
    $result = $wpdb->insert(  
        $requests_table,  
        array(  
            'staff_id' => $staff_id,  
            'shift_date' => $shift_date,  
            'shift_type' => $shift_type,  
            'status' => 'pending'  
        )  
    );  

    if ($result) {  
        // Send email notification  
        $admin_email = get_option('admin_email');  
        $user = get_userdata($staff_id);  
        $subject = 'New Shift Request';  
        $message = sprintf(  
            'A new shift request has been submitted by %s for %s (%s).',  
            $user->display_name,  
            $shift_date,  
            $shift_type  
        );  
        wp_mail($admin_email, $subject, $message);  
        wp_send_json_success('Shift request submitted successfully');  
    } else {  
        wp_send_json_error('Failed to submit shift request');  
    }  
}