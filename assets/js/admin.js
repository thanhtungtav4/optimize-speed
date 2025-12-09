jQuery(document).ready(function ($) {
    console.log('Optimize Speed Admin JS Loaded');
    console.log('Nav tabs found:', $('.nav-tab').length);
    console.log('Tab panes found:', $('.tab-pane').length);

    // Track unsaved changes
    var unsavedChanges = false;
    $('form :input').on('change', function () {
        unsavedChanges = true;
    });

    // Tab switching functionality
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();

        /* 
        Single form implementation allows switching tabs without data loss.
        Unsaved changes check is no longer needed for tab switching.
        */


        console.log('Tab clicked:', $(this).attr('href'));
        var target = $(this).attr('href');

        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update active pane
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');

        console.log('Tab switched to:', target);

        // Update URL hash without scrolling
        if (history.replaceState) {
            history.replaceState(null, null, target);
        }
    });

    // Restore active tab from hash on page load
    if (window.location.hash) {
        var hash = window.location.hash;
        console.log('Hash detected:', hash);
        var $targetTab = $('.nav-tab[href="' + hash + '"]');
        if ($targetTab.length) {
            setTimeout(function () {
                $targetTab.click();
            }, 100);
        }
    }

    // Database tools - handle all db tool card clicks
    $(document).on('click', '.db-tool-card', function () {
        console.log('DB tool clicked');
        var btn = $(this);
        var action = btn.data('action');
        var originalHtml = btn.html();
        var resultDiv = $('#db-optimization-result');

        btn.html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span><strong>Processing...</strong>').prop('disabled', true);
        resultDiv.hide().removeClass('notice-success notice-error');

        $.post(ajaxurl, {
            action: 'optimize_speed_db_cleanup',
            cleanup_type: action,
            nonce: optimizeSpeedAdmin.nonce
        }, function (response) {
            btn.html(originalHtml).prop('disabled', false);
            if (response.success) {
                resultDiv.addClass('notice notice-success').html('<p><strong>✓ Success:</strong> ' + response.data.message + '</p>').fadeIn();
            } else {
                resultDiv.addClass('notice notice-error').html('<p><strong>✗ Error:</b> ' + (response.data.message || 'Unknown error') + '</p>').fadeIn();
            }

            setTimeout(function () {
                resultDiv.fadeOut();
            }, 5000);
        }).fail(function () {
            btn.html(originalHtml).prop('disabled', false);
            resultDiv.addClass('notice notice-error').html('<p><strong>✗ Network Error:</strong> Please try again.</p>').fadeIn();
        });
    });

    // Add spinning animation CSS
    if (!document.getElementById('optimize-speed-animations')) {
        $('<style id="optimize-speed-animations">@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');
    }

    console.log('Optimize Speed Admin JS Setup Complete');
});
