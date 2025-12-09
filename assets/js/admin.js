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

    // --- Script Manager Actions ---

    // --- Script Manager Rules Engine Logic ---

    // 1. Add New Rule
    $('#add-rule-btn').on('click', function () {
        var tbody = $('#rules-tbody');
        var newIndex = new Date().getTime();

        // Build Select Options from osData
        var postTypesOpts = '<option value="">Select Post Type</option>';
        if (window.osData && window.osData.post_types) {
            window.osData.post_types.forEach(function (pt) {
                postTypesOpts += '<option value="' + pt.slug + '">' + pt.label + '</option>';
            });
        }

        var templatesOpts = '<option value="default">Default Template</option>';
        if (window.osData && window.osData.templates) {
            window.osData.templates.forEach(function (tpl) {
                templatesOpts += '<option value="' + tpl.file + '">' + tpl.name + '</option>';
            });
        }

        var rowHtml = `
            <tr class="rule-row">
                <td>
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][target]" class="rule-target-select" style="width:100%">
                        <option value="global">Global (All Pages)</option>
                        <option value="homepage">Homepage</option>
                        <option value="custom">Specific Page ID</option>
                        <option value="post_type">Specific Post Type</option>
                        <option value="page_template">Specific Page Template</option>
                    </select>
                    
                    <!-- ID Input -->
                    <input type="number" 
                           name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_num]" 
                           value="" 
                           placeholder="Page ID" 
                           style="width:100%; margin-top:5px; display:none;"
                           class="target-id-input target-input-custom">
                    
                    <!-- Post Type Select -->
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_type]" 
                            class="target-input-post_type" 
                            style="width:100%; margin-top:5px; display:none;">
                        ${postTypesOpts}
                    </select>

                    <!-- Template Select -->
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_tpl]" 
                            class="target-input-page_template" 
                            style="width:100%; margin-top:5px; display:none;">
                        ${templatesOpts}
                    </select>

                    <!-- Hidden Final ID -->
                    <input type="hidden" class="final-custom-id" name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id]" value="">
                </td>
                <td>
                    <input type="text" name="optimize_speed_settings[script_manager_rules][${newIndex}][handle]" value="" style="width:100%" placeholder="e.g. jquery, contact-form-7">
                    <label style="display:block; margin-top:4px; font-size:12px;">
                        <input type="checkbox" name="optimize_speed_settings[script_manager_rules][${newIndex}][is_regex]" value="1"> 
                        Regex Match
                    </label>
                </td>
                <td>
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][type]" style="width:100%">
                        <option value="js">JavaScript (JS)</option>
                        <option value="css">CSS Style</option>
                    </select>
                </td>
                <td>
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][strategy]" class="rule-strategy-select" style="width:100%">
                        <option value="async">Async</option>
                        <option value="defer">Defer</option>
                        <option value="delay">Delay (Interaction)</option>
                        <option value="preload">Preload (Head)</option>
                        <option value="disable">Disable (Dequeue)</option>
                    </select>
                    <label class="crossorigin-opt" style="display:none; margin-top:4px; font-size:12px;">
                        <input type="checkbox" name="optimize_speed_settings[script_manager_rules][${newIndex}][crossorigin]" value="1">
                        Crossorigin
                    </label>
                </td>
                <td>
                    <button type="button" class="button remove-rule-btn">Remove</button>
                </td>
            </tr>
        `;

        tbody.append(rowHtml);
    });

    // 2. Remove Rule
    $(document).on('click', '.remove-rule-btn', function () {
        if (confirm('Are you sure?')) {
            $(this).closest('tr').remove();
        }
    });

    // 3. Toggle Target Inputs
    $(document).on('change', '.rule-target-select', function () {
        var val = $(this).val();
        var cell = $(this).closest('td');

        // Hide all specifics first
        cell.find('.target-input-custom, .target-input-post_type, .target-input-page_template').hide();

        // Show relevant
        if (val === 'custom') {
            cell.find('.target-input-custom').show();
        } else if (val === 'post_type') {
            cell.find('.target-input-post_type').show();
        } else if (val === 'page_template') {
            cell.find('.target-input-page_template').show();
        }
    });

    // 4. Toggle Strategy Options (Crossorigin)
    $(document).on('change', '.rule-strategy-select', function () {
        var val = $(this).val();
        var cell = $(this).closest('td');
        if (val === 'preload') {
            cell.find('.crossorigin-opt').show();
        } else {
            cell.find('.crossorigin-opt').hide();
        }
    });

    // 5. Sync Inputs to Hidden Field
    $(document).on('input change', '.target-input-custom, .target-input-post_type, .target-input-page_template', function () {
        var val = $(this).val();
        $(this).closest('td').find('.final-custom-id').val(val);
    });

    // --- Visual Asset Scanner ---

    $('#scan-target-type').on('change', function () {
        if ($(this).val() === 'id') {
            $('#scan-target-id').show();
        } else {
            $('#scan-target-id').hide();
        }
    });

    $('#start-scan-btn').on('click', function () {
        var type = $('#scan-target-type').val();
        var id = $('#scan-target-id').val();
        var url = '/'; // Default homepage

        if (type === 'id') {
            if (!id) {
                alert('Please enter a Page ID');
                return;
            }
            url = '/?p=' + id;
        }

        var scanUrl = url + (url.indexOf('?') !== -1 ? '&' : '?') + 'os_scan_assets=1';

        $('#scan-spinner').addClass('is-active');
        $('#start-scan-btn').prop('disabled', true);
        $('#scan-results').hide();
        $('#scan-results-body').empty();

        $.get(scanUrl)
            .done(function (resp) {
                $('#scan-spinner').removeClass('is-active');
                $('#start-scan-btn').prop('disabled', false);

                if (resp && resp.success && resp.data) {
                    $('#scan-results').show();
                    $('#scan-url-display').text('Scan Target: ' + url);
                    var assets = resp.data;

                    // Helper render
                    var renderRow = function (item, type) {
                        return `
                            <tr>
                                <td><strong>${item.handle}</strong></td>
                                <td>${type.toUpperCase()}</td>
                                <td><div style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${item.src}">${item.src}</div></td>
                                <td>
                                    <button type="button" class="button button-small add-scanned-rule" data-handle="${item.handle}" data-type="${type}" data-strategy="async">Async</button>
                                    <button type="button" class="button button-small add-scanned-rule" data-handle="${item.handle}" data-type="${type}" data-strategy="defer">Defer</button>
                                    <button type="button" class="button button-small add-scanned-rule" data-handle="${item.handle}" data-type="${type}" data-strategy="delay">Delay</button>
                                    <button type="button" class="button button-small add-scanned-rule" data-handle="${item.handle}" data-type="${type}" data-strategy="disable" style="color:#a00">Disable</button>
                                </td>
                            </tr>
                         `;
                    };

                    if (assets.js) assets.js.forEach(item => $('#scan-results-body').append(renderRow(item, 'js')));
                    if (assets.css) assets.css.forEach(item => $('#scan-results-body').append(renderRow(item, 'css')));

                    if (assets.js.length === 0 && assets.css.length === 0) {
                        $('#scan-results-body').html('<tr><td colspan="4">No assets found or scan failed.</td></tr>');
                    }

                } else {
                    alert('Scan failed. Please check if the page exists.');
                }
            })
            .fail(function () {
                $('#scan-spinner').removeClass('is-active');
                $('#start-scan-btn').prop('disabled', false);
                alert('Scan request failed.');
            });
    });

    $('#clear-scan-btn').on('click', function () {
        $('#scan-results').hide();
        $('#scan-results-body').empty();
    });

    // Add Scanned Rule to Table
    $(document).on('click', '.add-scanned-rule', function () {
        var handle = $(this).data('handle');
        var type = $(this).data('type');
        var strategy = $(this).data('strategy');
        var targetType = $('#scan-target-type').val();
        var targetId = $('#scan-target-id').val();

        // Auto-scroll to rules table
        $('html, body').animate({
            scrollTop: $("#rules-table").offset().top - 100
        }, 500);

        // Add row via existing logic (simulate click or duplicate logic)
        // We duplicates logic slightly for speed (or refactor add-rule-btn to function, but manual append is fine)
        var tbody = $('#rules-tbody');
        var newIndex = new Date().getTime();

        var targetSelect = 'global';
        var customIdVal = '';
        var customDisplay = 'none';

        if (targetType === 'id') {
            targetSelect = 'custom';
            customIdVal = targetId;
            customDisplay = 'block';
        } else if (targetType === 'homepage') {
            targetSelect = 'homepage';
        }

        var rowHtml = `
            <tr class="rule-row" style="background:#f0fafe">
                <td>
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][target]" style="width:100%">
                        <option value="global" ${targetSelect === 'global' ? 'selected' : ''}>Global (All Pages)</option>
                        <option value="homepage" ${targetSelect === 'homepage' ? 'selected' : ''}>Homepage</option>
                        <option value="custom" ${targetSelect === 'custom' ? 'selected' : ''}>Specific Page ID</option>
                    </select>
                    <input type="number" 
                           name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id]" 
                           value="${customIdVal}" 
                           placeholder="Page ID" 
                           style="width:100%; margin-top:5px; display:${customDisplay};"
                           class="target-id-input">
                </td>
                <td>
                    <input type="text" name="optimize_speed_settings[script_manager_rules][${newIndex}][handle]" value="${handle}" style="width:100%">
                </td>
                <td>
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][type]" style="width:100%">
                        <option value="js" ${type === 'js' ? 'selected' : ''}>JavaScript (JS)</option>
                        <option value="css" ${type === 'css' ? 'selected' : ''}>CSS Style</option>
                    </select>
                </td>
                <td>
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][strategy]" style="width:100%">
                        <option value="async" ${strategy === 'async' ? 'selected' : ''}>Async</option>
                        <option value="defer" ${strategy === 'defer' ? 'selected' : ''}>Defer</option>
                        <option value="delay" ${strategy === 'delay' ? 'selected' : ''}>Delay (Interaction)</option>
                        <option value="disable" ${strategy === 'disable' ? 'selected' : ''}>Disable (Dequeue)</option>
                    </select>
                </td>
                <td>
                    <button type="button" class="button remove-rule-btn">Remove</button>
                </td>
            </tr>
        `;

        tbody.prepend(rowHtml); // Prepend so user sees it top
    });

    console.log('Optimize Speed Admin JS Setup Complete');
});
