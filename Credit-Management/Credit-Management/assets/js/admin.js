jQuery(document).ready(function($) {
    // Handle admin actions
    $('.gr8r-admin-actions form').on('submit', function(e) {
        var form = $(this);
        var button = form.find('input[type="submit"]');
        
        button.prop('disabled', true).val(button.data('processing-text'));
    });
    
    // Search functionality for tables
    $('.gr8r-admin-search').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.wp-list-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});