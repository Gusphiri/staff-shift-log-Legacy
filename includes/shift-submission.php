<?php 

// Shortcode: [staff_shift_form]  

add_shortcode('staff_shift_form', function() {  
    // Restrict to logged-in users  
    if (!is_user_logged_in()) {  
        return '<p>You must be logged in to submit a shift.</p>';  
    }  

    ob_start(); ?>  
    <form id="shift-form" method="post">  
        <input type="text" name="staff_name" placeholder="Your Name" required>  
        <input type="date" name="shift_date" required>  
        <select name="shift_type" required>  
            <option value="morning">Morning (6am-12pm)</option>  
            <option value="afternoon">Afternoon (12pm-6pm)</option>  
        </select>  
        <input type="hidden" name="action" value="submit_shift">  
        <?php wp_nonce_field('shift_form_nonce', 'shift_nonce'); ?>  
        <input type="submit" value="Submit">  
    </form>  
    <div id="shift-response"></div>  
    <?php return ob_get_clean();  
});  

// AJAX Handler (only for logged-in users)  
add_action('wp_ajax_submit_shift', 'ssl_handle_shift_submission');  
function ssl_handle_shift_submission() {  
    // Check nonce  
    if (!wp_verify_nonce($_POST['shift_nonce'], 'shift_form_nonce')) {  
        wp_send_json_error('Security check failed. Please try again.');  
    }  

    // Validate data  
    $staff_name = sanitize_text_field($_POST['staff_name']);  
    $shift_date = sanitize_text_field($_POST['shift_date']);  
    $shift_type = sanitize_text_field($_POST['shift_type']);  

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shift_date) || !in_array($shift_type, ['morning', 'afternoon'])) {  
        wp_send_json_error('Invalid shift data.');  
    }  

    global $wpdb;  
    $table_name = $wpdb->prefix . 'staff_shifts';  

    $data = array(  
        'staff_name' => $staff_name,  
        'shift_date' => $shift_date,  
        'shift_type' => $shift_type,  
    );  

    // Insert with error handling  
    if ($wpdb->insert($table_name, $data) === false) {  
        wp_send_json_error('Failed to save shift.');  
    } else {  
        wp_send_json_success('Shift submitted successfully!');  
    }  
}  
