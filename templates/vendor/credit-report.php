<div id="gr8r-vendor-report">Loading...</div>
<script>
jQuery(function($) {
    $.get(ajaxurl, { action: 'gr8r_vendor_report' }, function(response) {
        $('#gr8r-vendor-report').html(response);
    });
});
</script>
