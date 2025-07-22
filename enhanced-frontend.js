
jQuery(document).ready(function($) {
    // Copy coupon code functionality
    $('.enhanced-copy-code').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var code = button.data('code');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function() {
                button.text('✅').addClass('copied');
                setTimeout(function() {
                    button.text('📋').removeClass('copied');
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(code, button);
            });
        } else {
            fallbackCopyTextToClipboard(code, button);
        }
    });
    
    // Fallback copy function
    function fallbackCopyTextToClipboard(text, button) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            button.text('✅').addClass('copied');
            setTimeout(function() {
                button.text('📋').removeClass('copied');
            }, 2000);
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }
        
        document.body.removeChild(textArea);
    }
    
    // Auto-apply coupon from URL parameter
    var urlParams = new URLSearchParams(window.location.search);
    var couponCode = urlParams.get('coupon');
    var autoApply = urlParams.get('auto_apply');
    
    if (couponCode && autoApply === '1') {
        // Store in session storage for checkout page
        sessionStorage.setItem('gr8r_enhanced_auto_coupon', couponCode);
        
        // Show notification
        showNotification('Coupon ' + couponCode + ' will be automatically applied at checkout!', 'success');
    }
    
    // Apply stored coupon on checkout page
    if ($('body').hasClass('woocommerce-checkout')) {
        var storedCoupon = sessionStorage.getItem('gr8r_enhanced_auto_coupon');
        if (storedCoupon) {
            // Apply coupon
            $('input[name="coupon_code"]').val(storedCoupon);
            $('button[name="apply_coupon"]').click();
            
            // Clear from storage
            sessionStorage.removeItem('gr8r_enhanced_auto_coupon');
        }
    }
    
    // Enhanced coupon card animations
    $('.gr8r-enhanced-coupon-card').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateY(20px)'
        }).delay(index * 100).animate({
            'opacity': '1',
            'transform': 'translateY(0)'
        }, 600);
    });
    
    // Filter coupons by status
    $('.enhanced-coupons-stats .stat').on('click', function() {
        var filter = $(this).hasClass('active') ? 'active' : 
                    $(this).hasClass('used') ? 'used' : 'expired';
        
        $('.gr8r-enhanced-coupon-card').hide();
        $('.gr8r-enhanced-coupon-card.' + filter).show();
        
        // Update active filter
        $('.enhanced-coupons-stats .stat').removeClass('active-filter');
        $(this).addClass('active-filter');
    });
    
    // Show all coupons when clicking header
    $('.enhanced-coupons-header h3').on('click', function() {
        $('.gr8r-enhanced-coupon-card').show();
        $('.enhanced-coupons-stats .stat').removeClass('active-filter');
    });
    
    // Session booking with coupon generation
    $('.enhanced-booking-actions a').on('click', function(e) {
        e.preventDefault();
        var link = $(this);
        var originalText = link.text();
        
        // Show loading state
        link.text('🔄 Generating coupon...').addClass('loading');
        
        // Simulate API call for coupon generation
        setTimeout(function() {
            // Redirect to actual booking page
            window.location.href = link.attr('href');
        }, 1000);
    });
    
    // Notification system
    function showNotification(message, type) {
        var notification = $('<div class="gr8r-enhanced-notification ' + type + '">' + message + '</div>');
        $('body').append(notification);
        
        notification.fadeIn(300).delay(3000).fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    // Enhanced search functionality
    if ($('.enhanced-coupon-search').length) {
        $('.enhanced-coupon-search').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            
            $('.gr8r-enhanced-coupon-card').each(function() {
                var couponCode = $(this).find('.enhanced-coupon-code strong').text().toLowerCase();
                var sessionName = $(this).find('.enhanced-session-info span').text().toLowerCase();
                
                if (couponCode.includes(searchTerm) || sessionName.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }
    
    // Responsive mobile menu for coupon actions
    if ($(window).width() < 768) {
        $('.enhanced-coupon-actions').addClass('mobile-actions');
        
        $('.enhanced-coupon-actions a').on('click', function(e) {
            if ($(this).hasClass('enhanced-btn-secondary')) {
                e.preventDefault();
                var menu = $(this).closest('.enhanced-coupon-actions');
                menu.toggleClass('show-mobile-menu');
            }
        });
    }
    
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 800);
        }
    });
});
