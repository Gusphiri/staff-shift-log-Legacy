/**
 * Staff Shift Log - JavaScript
 * 
 * This file handles the AJAX submission of shift requests and form validation.
 */
jQuery(document).ready(function($) {  
    // Check if jQuery is loaded
    if (typeof jQuery === 'undefined') {  
        console.error('jQuery is not loaded');
        return;  
    }  

    // Check if the form exists
    var $form = $('#shift-form');
    if (!$form.length) {
        return; // Form not found, exit
    }

    // Form submission handler
    $form.on('submit', function(e) {  
        e.preventDefault();
        
        // Disable submit button to prevent double submission
        var $submitButton = $(this).find('button[type="submit"]');
        var originalText = $submitButton.text();
        $submitButton.prop('disabled', true).text('Submitting...');
        
        // Clear previous messages
        $('#shift-response').removeClass('success error').empty();
        
        // Validate form
        var $dateField = $('#shift_date');
        var $typeField = $('#shift_type');
        var isValid = true;
        
        // Date validation
        if (!$dateField.val()) {
            showError('Please select a date');
            isValid = false;
        }
        
        // Shift type validation
        if (!$typeField.val()) {
            showError('Please select a shift type');
            isValid = false;
        }
        
        if (!isValid) {
            $submitButton.prop('disabled', false).text(originalText);
            return;
        }
        
        // Serialize form data
        var formData = $(this).serialize();
        
        // Add the action parameter for WordPress AJAX
        formData += '&action=submit_shift';

        // Submit form via AJAX
        $.ajax({  
            url: ssl_ajax.ajax_url,  
            type: 'POST',  
            data: formData,
            timeout: 10000, // 10 second timeout
            success: function(response) {  
                if (response.success) {
                    showSuccess(response.data);
                    // Reset form after successful submission
                    $form[0].reset();
                } else {
                    showError(response.data);
                }
            },  
            error: function(xhr, status, error) {  
                var errorMessage = 'Server error';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Security check failed. Please refresh the page and try again.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                } else if (error) {
                    errorMessage = 'Error: ' + error;
                }
                
                showError(errorMessage);
            },
            complete: function() {
                // Re-enable submit button
                $submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Helper function to show success message
    function showSuccess(message) {
        $('#shift-response')
            .removeClass('error')
            .addClass('success')
            .html('<span class="dashicons dashicons-yes"></span> ' + message);
    }
    
    // Helper function to show error message
    function showError(message) {
        $('#shift-response')
            .removeClass('success')
            .addClass('error')
            .html('<span class="dashicons dashicons-no"></span> ' + message);
    }
});