<?php

/**
 * Add the Staff Shifts menu to the WordPress admin
 */
add_action('admin_menu', 'ssl_admin_menu');  
function ssl_admin_menu() {  
    add_menu_page(
        'Staff Shifts', 
        'Staff Shifts', 
        'manage_options', 
        'staff-shift-log', 
        'ssl_admin_page', 
        'dashicons-calendar-alt', 
        30
    );  
}  

/**
 * Display the admin page for managing staff shifts
 */
function ssl_admin_page() {  
    // Check if tables exist
    global $wpdb;
    $shifts_table = $wpdb->prefix . 'staff_shifts';
    $requests_table = $wpdb->prefix . 'shift_requests';
    
    $shifts_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") == $shifts_table;
    $requests_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") == $requests_table;
    
    if (!$shifts_table_exists || !$requests_table_exists) {
        ?>
        <div class="wrap">
            <h1><?php _e('Staff Shift Manager', 'staff-shift-log'); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('The required database tables do not exist. Please deactivate and reactivate the plugin to create them.', 'staff-shift-log'); ?></p>
            </div>
        </div>
        <?php
        return;
    }
    
    // Handle shift request actions
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        $action = sanitize_text_field($_POST['action']);
        
        if ($action === 'approve') {
            // Get the request details
            $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $requests_table WHERE id = %d", $request_id));
            
            if ($request) {
                // Insert into shifts table
                $wpdb->insert(
                    $shifts_table,
                    array(
                        'staff_id' => $request->staff_id,
                        'shift_date' => $request->shift_date,
                        'shift_type' => $request->shift_type,
                        'status' => 'approved'
                    ),
                    array('%d', '%s', '%s', '%s')
                );
                
                // Update request status
                $wpdb->update(
                    $requests_table,
                    array('status' => 'approved'),
                    array('id' => $request_id),
                    array('%s'),
                    array('%d')
                );
                
                echo '<div class="notice notice-success"><p>' . __('Shift request approved.', 'staff-shift-log') . '</p></div>';
            }
        } elseif ($action === 'reject') {
            // Update request status
            $wpdb->update(
                $requests_table,
                array('status' => 'rejected'),
                array('id' => $request_id),
                array('%s'),
                array('%d')
            );
            
            echo '<div class="notice notice-success"><p>' . __('Shift request rejected.', 'staff-shift-log') . '</p></div>';
        } elseif ($action === 'delete') {
            // Delete the request
            $wpdb->delete(
                $requests_table,
                array('id' => $request_id),
                array('%d')
            );
            
            echo '<div class="notice notice-success"><p>' . __('Shift request deleted.', 'staff-shift-log') . '</p></div>';
        }
    }
    
    // Get all shift requests
    $requests = $wpdb->get_results("SELECT * FROM $requests_table ORDER BY submitted_at DESC");
    
    // Get all approved shifts
    $shifts = $wpdb->get_results("SELECT * FROM $shifts_table ORDER BY shift_date DESC");
    
    // Get current month and year for calendar download
    $current_month = date('m');
    $current_year = date('Y');
    
    ?>
    <div class="wrap">
        <h1><?php _e('Staff Shift Manager', 'staff-shift-log'); ?></h1>
        
        <!-- Calendar Download Section -->
        <div class="ssl-calendar-download">
            <h2><?php _e('Download Calendar', 'staff-shift-log'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ssl_download_calendar">
                <?php wp_nonce_field('ssl_download_calendar', 'ssl_calendar_nonce'); ?>
                
                <select name="calendar_month" id="calendar_month">
                    <?php
                    // Generate options for the last 12 months
                    for ($i = 0; $i < 12; $i++) {
                        $month = date('m', strtotime("-$i months"));
                        $year = date('Y', strtotime("-$i months"));
                        $month_name = date_i18n('F Y', strtotime("$year-$month-01"));
                        $selected = ($month == $current_month && $year == $current_year) ? 'selected' : '';
                        echo "<option value='$year-$month' $selected>$month_name</option>";
                    }
                    ?>
                </select>
                
                <select name="calendar_format" id="calendar_format">
                    <option value="csv"><?php _e('CSV', 'staff-shift-log'); ?></option>
                    <option value="excel"><?php _e('Excel', 'staff-shift-log'); ?></option>
                    <option value="pdf"><?php _e('PDF', 'staff-shift-log'); ?></option>
                </select>
                
                <button type="submit" class="button button-primary"><?php _e('Download Calendar', 'staff-shift-log'); ?></button>
            </form>
        </div>
        
        <h2><?php _e('Shift Requests', 'staff-shift-log'); ?></h2>
        <?php if (empty($requests)): ?>
            <p><?php _e('No shift requests found.', 'staff-shift-log'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Staff', 'staff-shift-log'); ?></th>
                        <th><?php _e('Date', 'staff-shift-log'); ?></th>
                        <th><?php _e('Shift Type', 'staff-shift-log'); ?></th>
                        <th><?php _e('Status', 'staff-shift-log'); ?></th>
                        <th><?php _e('Submitted', 'staff-shift-log'); ?></th>
                        <th><?php _e('Actions', 'staff-shift-log'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php 
                                $user = get_user_by('id', $request->staff_id);
                                echo $user ? esc_html($user->display_name) : esc_html($request->staff_id);
                            ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request->shift_date))); ?></td>
                            <td><?php echo esc_html($request->shift_type); ?></td>
                            <td><?php echo esc_html(ucfirst($request->status)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($request->submitted_at))); ?></td>
                            <td>
                                <?php if ($request->status === 'pending'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="button button-small button-primary"><?php _e('Approve', 'staff-shift-log'); ?></button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="button button-small"><?php _e('Reject', 'staff-shift-log'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this request?', 'staff-shift-log'); ?>');"><?php _e('Delete', 'staff-shift-log'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2><?php _e('Approved Shifts', 'staff-shift-log'); ?></h2>
        <?php if (empty($shifts)): ?>
            <p><?php _e('No approved shifts found.', 'staff-shift-log'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Staff', 'staff-shift-log'); ?></th>
                        <th><?php _e('Date', 'staff-shift-log'); ?></th>
                        <th><?php _e('Shift Type', 'staff-shift-log'); ?></th>
                        <th><?php _e('Status', 'staff-shift-log'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><?php 
                                $user = get_user_by('id', $shift->staff_id);
                                echo $user ? esc_html($user->display_name) : esc_html($shift->staff_id);
                            ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($shift->shift_date))); ?></td>
                            <td><?php echo esc_html($shift->shift_type); ?></td>
                            <td><?php echo esc_html(ucfirst($shift->status)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
        .ssl-calendar-download {
            background: #fff;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .ssl-calendar-download select {
            margin-right: 10px;
        }
    </style>
    <?php
}