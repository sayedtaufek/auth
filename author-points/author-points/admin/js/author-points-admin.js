/**
 * Author Points Admin JavaScript - Optimized Version
 * 
 * Improvements:
 * - Added debounce for button clicks
 * - Improved error handling
 * - Added form validation
 * - Optimized DOM manipulation
 */
(function($) {
    'use strict';
    
    // Debounce function to prevent multiple rapid clicks
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    // Show notification message
    function showMessage(type, message) {
        const $notice = $('#notification_message');
        
        // Only manipulate DOM if necessary
        if (!$notice.hasClass('notice-' + type)) {
            $notice.removeClass('notice-success notice-error').addClass('notice-' + type);
        }
        
        $notice.html('<p>' + message + '</p>').show();
    }
    
    // Validate post ID
    function validatePostId(postId) {
        if (!postId || isNaN(parseInt(postId, 10))) {
            showMessage('error', 'الرجاء إدخال رقم مقال صحيح');
            return false;
        }
        return true;
    }
    
    // Send push notification
    function sendPushNotification() {
        const postId = $('#post_id').val().trim();
        
        // Validate input
        if (!validatePostId(postId)) {
            return;
        }
        
        // Disable button to prevent multiple submissions
        const $button = $('#send_push');
        $button.prop('disabled', true);
        
        // Show loading indicator
        showMessage('success', 'جاري إرسال الطلب...');
        
        // Send AJAX request
        $.ajax({
            url: authorPoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'send_push_notification',
                nonce: authorPoints.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data);
                    
                    // Use requestAnimationFrame for smoother UI updates
                    requestAnimationFrame(function() {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    });
                } else {
                    showMessage('error', response.data || 'حدث خطأ غير معروف');
                }
            },
            error: function(xhr) {
                let errorMessage = 'حدث خطأ أثناء إرسال الطلب';
                
                // Try to get more specific error message
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                
                showMessage('error', errorMessage);
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false);
            }
        });
    }
    
    // Initialize when document is ready
    $(function() {
        // Use event delegation for better performance
        $(document).on('click', '#send_push', debounce(sendPushNotification, 300));
        
        // Add input validation on keyup
        $('#post_id').on('keyup', function(e) {
            // If Enter key is pressed, trigger send button
            if (e.keyCode === 13) {
                $('#send_push').trigger('click');
                return;
            }
            
            // Clear error message when user starts typing
            if ($('#notification_message').hasClass('notice-error')) {
                $('#notification_message').hide();
            }
        });
    });
    
})(jQuery);
