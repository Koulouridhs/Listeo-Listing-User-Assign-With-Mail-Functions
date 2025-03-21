jQuery(document).ready(function($) {
    // Check all functionality
    $('#luua-check-all').on('click', function() {
        $('input[name="luua_listing_ids[]"]').prop('checked', this.checked);
    });

    // Update "Check All" checkbox state when individual checkboxes change
    $('input[name="luua_listing_ids[]"]').on('change', function() {
        $('#luua-check-all').prop('checked', $('input[name="luua_listing_ids[]"]:checked').length === $('input[name="luua_listing_ids[]"]').length);
    });

    // Smooth scroll to top after pagination click, accounting for fixed headers
    $('.luua-pagination a').on('click', function() {
        $('html, body').animate({ scrollTop: $('.luua-wrap').offset().top - 50 }, 300);
    });
});