jQuery(document).ready(function($) {  
    if (typeof jQuery === 'undefined') {  
        console.error('jQuery is not loaded');  
        return;  
    }  

    $('#shift-form').on('submit', function(e) {  
        e.preventDefault();  
        var formData = $(this).serialize();  

        $.ajax({  
            url: ssl_ajax.ajax_url,  
            type: 'POST',  
            data: formData,  
            success: function(response) {  
                $('#shift-response').html(response.success ? '✅ ' + response.data : '❌ ' + response.data);  
            },  
            error: function(xhr, status, error) {  
                $('#shift-response').html('❌ Server error: ' + error);  
            }  
        });  
    });  
});  