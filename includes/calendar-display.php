<?php 
// AJAX endpoint for shifts  
add_action('wp_ajax_get_shifts', 'ssl_get_shifts');  
function ssl_get_shifts() {  
    if (!is_user_logged_in()) {  
        wp_send_json_error('You must be logged in to view shifts.');  
    }  

    global $wpdb;  
    $table_name = $wpdb->prefix . 'staff_shifts';  
    $shifts = $wpdb->get_results("SELECT * FROM $table_name");  
    wp_send_json_success($shifts);  
}  

// Shortcode: [staff_shift_calendar]  
add_shortcode('staff_shift_calendar', function() {  
    if (!is_user_logged_in()) {  
        return '<p>You must be logged in to view the shift calendar.</p>';  
    }  

    global $wpdb;  
    $table_name = $wpdb->prefix . 'staff_shifts';  
    $shifts = $wpdb->get_results("SELECT * FROM $table_name");  

    ob_start(); ?>  
    <div id="shift-calendar"></div>  
    <?php if (empty($shifts)) : ?>  
        <p>No shifts scheduled yet.</p>  
    <?php endif; ?>  
    <script>  
    document.addEventListener('DOMContentLoaded', function() {  
        var calendarEl = document.getElementById('shift-calendar');  
        var calendar = new FullCalendar.Calendar(calendarEl, {  
            initialView: 'dayGridMonth',  
            events: function(fetchInfo, successCallback, failureCallback) {  
                jQuery.get(ssl_ajax.ajax_url, { action: 'get_shifts' }, function(response) {  
                    if (response.success) {  
                        successCallback(response.data.map(function(shift) {  
                            return {  
                                title: shift.staff_name + ' (' + shift.shift_type + ')',  
                                start: shift.shift_date,  
                                color: shift.shift_type === 'morning' ? '#FFD700' : '#87CEEB'  
                            };  
                        }));  
                    } else {  
                        failureCallback(response.data);  
                    }  
                }).fail(function() {  
                    failureCallback('Failed to load shifts');  
                });  
            }  
        });  
        calendar.render();  
    });  
    </script>  
    <?php return ob_get_clean();  
});  