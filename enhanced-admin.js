
jQuery(document).ready(function($) {
    // Enhanced admin functionality
    
    // Confirmation dialogs for dangerous actions
    $('.gr8r-enhanced-quick-action.delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // Copy coupon code to clipboard
    $('.gr8r-enhanced-copy-coupon').on('click', function(e) {
        e.preventDefault();
        var code = $(this).data('code');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function() {
                alert('Coupon code copied to clipboard!');
            }).catch(function() {
                fallbackCopyTextToClipboard(code);
            });
        } else {
            fallbackCopyTextToClipboard(code);
        }
    });
    
    // Fallback copy function
    function fallbackCopyTextToClipboard(text) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('Coupon code copied to clipboard!');
        } catch (err) {
            alert('Unable to copy to clipboard. Please copy manually: ' + text);
        }
        
        document.body.removeChild(textArea);
    }
    
    // Enhanced table filtering
    $('.gr8r-enhanced-filter-select').on('change', function() {
        var filterValue = $(this).val();
        var filterColumn = $(this).data('column');
        
        $('.gr8r-enhanced-admin-table tbody tr').each(function() {
            var cellValue = $(this).find('td').eq(filterColumn).text().toLowerCase();
            var filterLower = filterValue.toLowerCase();
            
            if (filterValue === '' || cellValue.indexOf(filterLower) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Update visible count
        updateVisibleCount();
    });
    
    // Enhanced search functionality
    $('.gr8r-enhanced-admin-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.gr8r-enhanced-admin-table tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            
            if (searchTerm === '' || rowText.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        updateVisibleCount();
    });
    
    // Update visible row count
    function updateVisibleCount() {
        var totalRows = $('.gr8r-enhanced-admin-table tbody tr').length;
        var visibleRows = $('.gr8r-enhanced-admin-table tbody tr:visible').length;
        
        if ($('.enhanced-results-count').length === 0) {
            $('.gr8r-enhanced-admin-table').before('<div class="enhanced-results-count"></div>');
        }
        
        $('.enhanced-results-count').text('Showing ' + visibleRows + ' of ' + totalRows + ' results');
    }
    
    // AJAX form submission for quick actions
    $('.gr8r-enhanced-quick-action').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var action = button.data('action');
        var itemId = button.data('item-id');
        var originalText = button.text();
        
        // Show loading state
        button.text('Loading...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gr8r_enhanced_quick_action',
                quick_action: action,
                item_id: itemId,
                nonce: gr8r_enhanced_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove row or update status
                    if (action === 'delete') {
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            updateVisibleCount();
                        });
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + response.data);
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Enhanced modal functionality
    $('.gr8r-enhanced-modal-trigger').on('click', function(e) {
        e.preventDefault();
        var modalId = $(this).data('modal');
        $('#' + modalId).show();
    });
    
    $('.gr8r-enhanced-modal-close').on('click', function() {
        $(this).closest('.gr8r-enhanced-modal').hide();
    });
    
    // Close modal when clicking outside
    $('.gr8r-enhanced-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Enhanced form validation
    $('.gr8r-enhanced-admin-actions form').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Check required fields
        form.find('input[required], select[required], textarea[required]').each(function() {
            if ($(this).val() === '') {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Check numeric fields
        form.find('input[type="number"]').each(function() {
            var value = $(this).val();
            if (value !== '' && (isNaN(value) || value < 0)) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly.');
        }
    });
    
    // Auto-refresh stats every 30 seconds
    if ($('.gr8r-enhanced-stats-boxes').length) {
        setInterval(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gr8r_enhanced_refresh_stats',
                    nonce: gr8r_enhanced_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.gr8r-enhanced-stats-boxes').html(response.data.html);
                    }
                }
            });
        }, 30000);
    }
    
    // Enhanced bulk actions
    $('.gr8r-enhanced-bulk-action').on('click', function(e) {
        e.preventDefault();
        var action = $(this).data('action');
        var checkedItems = $('.gr8r-enhanced-admin-table input[type="checkbox"]:checked');
        
        if (checkedItems.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        
        if (!confirm('Are you sure you want to perform this action on ' + checkedItems.length + ' items?')) {
            return;
        }
        
        var itemIds = [];
        checkedItems.each(function() {
            itemIds.push($(this).val());
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gr8r_enhanced_bulk_action',
                bulk_action: action,
                item_ids: itemIds,
                nonce: gr8r_enhanced_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Select all checkbox functionality
    $('.gr8r-enhanced-select-all').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.gr8r-enhanced-admin-table input[type="checkbox"]').prop('checked', isChecked);
    });
    
    // Auto-save form data
    $('.gr8r-enhanced-auto-save').on('input', function() {
        var field = $(this);
        var value = field.val();
        var fieldName = field.attr('name');
        
        localStorage.setItem('gr8r_enhanced_' + fieldName, value);
    });
    
    // Restore auto-saved data
    $('.gr8r-enhanced-auto-save').each(function() {
        var field = $(this);
        var fieldName = field.attr('name');
        var savedValue = localStorage.getItem('gr8r_enhanced_' + fieldName);
        
        if (savedValue) {
            field.val(savedValue);
        }
    });
    
    // Clear auto-saved data on successful form submission
    $('.gr8r-enhanced-admin-actions form').on('submit', function() {
        $(this).find('.gr8r-enhanced-auto-save').each(function() {
            var fieldName = $(this).attr('name');
            localStorage.removeItem('gr8r_enhanced_' + fieldName);
        });
    });
    
    // Initialize tooltips
    if ($.fn.tooltip) {
        $('[data-tooltip]').tooltip({
            position: { my: "left+15 center", at: "right center" }
        });
    }
});
