jQuery(document).ready(function ($) {
    console.log('Optimize Speed Admin JS Loaded');
    console.log('Nav tabs found:', $('.nav-tab').length);
    console.log('Tab panes found:', $('.tab-pane').length);

    // Global Scan State
    var currentRawAssets = {};
    var currentFilter = '';
    var currentViewMode = 'list';
    var currentScanContext = {
        isSingular: false,
        type: '',     // page, post, product, post_type, etc.
        postType: '', // actual WP post type slug
        id: '',
        label: ''     // readable label for specific item
    };

    // Track unsaved changes
    var unsavedChanges = false;
    $('form :input').on('change', function () {
        unsavedChanges = true;
    });

    // Tab switching functionality
    // Tab switching functionality
    // Tab switching functionality
    // Main Tabs
    $('.os-main-nav.nav-tab-wrapper > .nav-tab').on('click', function (e) {
        e.preventDefault();

        console.log('Main Tab clicked:', $(this).attr('href'));
        var target = $(this).attr('href');

        // Update active tab (scoped to main tabs)
        $('.os-main-nav.nav-tab-wrapper > .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update active pane
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');

        // Update URL hash without scrolling
        if (history.replaceState) {
            history.replaceState(null, null, target);
        }
    });

    // Sub Tabs (Rules Engine)
    $('.os-rules-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();

        var target = $(this).attr('href'); // #rules-global, #rules-homepage

        // Update active tab (scoped to rules tabs)
        $('.os-rules-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update content - simple toggle since they are just divs in the same container
        $('.rules-tab-content').hide();
        $(target).show();
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

    // 1. Rule Creator Logic: Toggle Target Details
    $('#new-rule-target').on('change', function () {
        var val = $(this).val();
        $('#new-rule-target-details').children().hide();
        var detailsDiv = $('#new-rule-target-details');

        if (val === 'custom') {
            $('#new-rule-custom-id').show();
        } else if (val === 'post_type') {
            $('#new-rule-post-type').show();
        } else if (val === 'page_template') {
            $('#new-rule-page-template').show();
        }
    });

    // 2. Rule Creator Logic: Toggle Crossorigin
    $('#new-rule-strategy').on('change', function () {
        if ($(this).val() === 'preload') {
            $('#new-rule-crossorigin-wrapper').show();
        } else {
            $('#new-rule-crossorigin-wrapper').hide();
        }
    });

    // 3. Add Rule Action (Top Button) - REFACTORED FOR TABS
    $('#add-rule-btn-top').on('click', function () {
        var newIndex = new Date().getTime();

        // Gather values from Top Creator
        var targetStart = $('#new-rule-target').val();
        var handle = $('#new-rule-handle').val();
        var isRegex = $('#new-rule-regex').is(':checked') ? 1 : 0;
        var type = $('#new-rule-type').val();
        var strategy = $('#new-rule-strategy').val();
        var isCrossorigin = $('#new-rule-crossorigin').is(':checked') ? 1 : 0;

        // Custom ID logic
        var customId = '';
        if (targetStart === 'custom') {
            customId = $('#new-rule-custom-id').val();
        } else if (targetStart === 'post_type') {
            customId = $('#new-rule-post-type').val();
        } else if (targetStart === 'page_template') {
            customId = $('#new-rule-page-template').val();
        }

        if (!handle) {
            alert('Please enter a handle.');
            return;
        }

        // Determine destination tab
        var destTab = 'specific';
        if (targetStart === 'global') destTab = 'global';
        else if (targetStart === 'homepage') destTab = 'homepage';

        var tbody = $('#rules-' + destTab + ' .rules-tbody-target');

        // Build Target Display
        var targetDisplay = 'Global';
        if (targetStart === 'homepage') targetDisplay = 'Homepage Only';
        else if (targetStart === 'custom') targetDisplay = 'ID: ' + escapeHtml(customId);
        else if (targetStart === 'post_type') targetDisplay = 'Type: ' + escapeHtml(customId);
        else if (targetStart === 'page_template') targetDisplay = 'Tpl: ' + escapeHtml(customId);

        var regexBadge = isRegex ? '<span class="os-badge-small">Regex</span>' : '';

        // Visibility vars for hidden inputs (to match PHP structure mostly just for saving)
        var showCustom = (targetStart === 'custom');

        var typeJsSel = (type === 'js') ? 'selected' : '';
        var typeCssSel = (type === 'css') ? 'selected' : '';

        var stratAsync = (strategy === 'async') ? 'selected' : '';
        var stratDefer = (strategy === 'defer') ? 'selected' : '';
        var stratDelay = (strategy === 'delay') ? 'selected' : '';
        var stratPreload = (strategy === 'preload') ? 'selected' : '';
        var stratDisable = (strategy === 'disable') ? 'selected' : '';

        var crossStyle = (strategy === 'preload') ? 'block' : 'none';
        var crossChecked = (isCrossorigin) ? 'checked' : '';

        var rowHtml = `
            <tr class="rule-row info-row">
                <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][target]" value="${targetStart}">
                
                <td width="30%">
                    <strong>${escapeHtml(handle)}</strong>
                    ${regexBadge}
                    <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][handle]" value="${escapeHtml(handle)}">
                    <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][is_regex]" value="${isRegex}">

                    <div class="target-display" style="margin-top:5px; font-size:12px; color:#666;">
                        ${targetDisplay}
                        <input type="number" 
                            name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_num]" 
                            value="${(targetStart === 'custom' ? customId : '')}" 
                            style="display:none;" class="target-id-input">
                        <input type="hidden" class="final-custom-id"
                            name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id]"
                            value="${customId}">
                         <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_type]" value="${targetStart === 'post_type' ? customId : ''}">
                         <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_tpl]" value="${targetStart === 'page_template' ? customId : ''}">
                    </div>
                </td>

                <td width="15%">
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][type]" style="width:100%">
                        <option value="js" ${typeJsSel}>JS</option>
                        <option value="css" ${typeCssSel}>CSS</option>
                    </select>
                </td>

                <td width="35%">
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][strategy]" class="rule-strategy-select" style="width:100%">
                        <option value="async" ${stratAsync} title="Load in parallel, execute immediately when ready">Async</option>
                        <option value="defer" ${stratDefer} title="Load in parallel, execute after HTML parsing">Defer</option>
                        <option value="delay" ${stratDelay} title="Delay loading until user interaction (click/scroll)">Delay</option>
                        <option value="preload" ${stratPreload} title="Preload with high priority for critical assets">Preload</option>
                        <option value="disable" ${stratDisable} title="Completely disable this asset on the target">Disable</option>
                    </select>
                    <label class="crossorigin-opt" style="display:${crossStyle}; margin-top:4px; font-size:11px;">
                        <input type="checkbox" name="optimize_speed_settings[script_manager_rules][${newIndex}][crossorigin]" value="1" ${crossChecked}>
                        Crossorigin
                    </label>
                </td>

                <td width="10%" style="text-align:right;">
                    <button type="button" class="button remove-rule-btn" style="color:#a00;"><span class="dashicons dashicons-trash" style="margin-top:3px;"></span></button>
                </td>
            </tr>
        `;

        // Pass 0: Remove empty placeholder if exists
        tbody.find('.no-rules-row').remove();
        tbody.prepend(rowHtml);

        // Switch to that tab so user sees it
        $('.nav-tab[href="#rules-' + destTab + '"]').click();

        // Reset Inputs
        $('#new-rule-handle').val('');
        $('#new-rule-custom-id').val('');
        $('#new-rule-regex').prop('checked', false);
    });

    // 4. Remove Rule
    $(document).on('click', '.remove-rule-btn', function () {
        if (confirm('Are you sure?')) {
            $(this).closest('tr').remove();
        }
    });

    // 5. Toggle Advanced Options
    $(document).on('click', '.advanced-opts-toggle', function () {
        $(this).next('.advanced-opts').toggle();
    });

    // 6. Toggle Target Inputs (Row level)
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

    // 4. Toggle Strategy Options (Crossorigin) and Show Description
    var strategyDescriptions = {
        'async': '⚡ Load in parallel, execute immediately when ready (may run before DOM ready)',
        'defer': '📋 Load in parallel, execute after HTML parsing complete (safer than async)',
        'delay': '⏳ Delay loading until user interaction - best for 3rd party scripts',
        'preload': '🚀 Preload with high priority - use for critical above-the-fold assets',
        'disable': '🚫 Completely disable this asset on the target page(s)'
    };

    $(document).on('change', '.rule-strategy-select', function () {
        var val = $(this).val();
        var cell = $(this).closest('td');

        // Show/hide crossorigin option
        if (val === 'preload') {
            cell.find('.crossorigin-opt').show();
        } else {
            cell.find('.crossorigin-opt').hide();
        }

        // Update strategy description
        var desc = strategyDescriptions[val] || '';
        cell.find('.strategy-description').text(desc);
    });

    // 5. Sync Inputs to Hidden Field
    $(document).on('input change', '.target-input-custom, .target-input-post_type, .target-input-page_template', function () {
        var val = $(this).val();
        $(this).closest('td').find('.final-custom-id').val(val);
    });

    // --- Visual Asset Scanner ---

    // Helper function to build a scan result row
    function buildScanRow(item, type) {
        var handle = item.handle || 'N/A';
        var src = item.src || 'N/A';
        var typeLabel = type === 'js' ? '<span class="os-badge js">JS</span>' : '<span class="os-badge css">CSS</span>';
        var size = item.size_formatted ? item.size_formatted : '-';

        // Determine source badge color
        var sourceLabel = item.source_name || 'Unknown';

        // Scope Column Logic
        var scopeHtml = '';
        if (currentScanContext.isSingular) {
            var ptLabel = currentScanContext.postType.charAt(0).toUpperCase() + currentScanContext.postType.slice(1);
            scopeHtml = `
                <select class="scan-scope-select" style="max-width:100%;">
                    <option value="specific">Current Item (ID: ${currentScanContext.id})</option>
                    <option value="post_type">All ${ptLabel}s</option>
                    <option value="global">Global</option>
                </select>
            `;
        } else if (currentScanContext.type === 'post_type') {
            var ptLabel = currentScanContext.postType.charAt(0).toUpperCase() + currentScanContext.postType.slice(1);
            scopeHtml = `
                <select class="scan-scope-select" style="max-width:100%;">
                    <option value="post_type">All ${ptLabel}s</option>
                    <option value="global">Global</option>
                </select>
            `;
        } else if (currentScanContext.type === 'homepage') {
            scopeHtml = '<span class="os-badge">Homepage</span>';
        } else if (currentScanContext.type === 'archive') {
            scopeHtml = '<span class="os-badge">Archive</span>';
        } else {
            scopeHtml = '<span class="os-badge">Global</span>';
        }

        return `
            <tr>
                <td><input type="checkbox" class="scan-item-checkbox" value="${item.handle}"></td>
                <td>
                    <strong>${item.handle}</strong><br>
                    <small style="color:#666; word-break:break-all;">${item.src}</small>
                </td>
                <td>${size}</td>
                <td>${typeLabel}</td>
                <td>${sourceLabel}</td>
                <td>${scopeHtml}</td>
                <td>
                    <select class="scan-strategy-select">
                        <option value="disable">Disable</option>
                        <option value="async">Async</option>
                        <option value="defer">Defer</option>
                    </select>
                </td>
                <td>
                    <button type="button" class="button button-small add-scanned-rule" 
                        data-handle="${item.handle}" data-type="${type}">Add Rule</button>
                </td>
            </tr>
        `;
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 1. Toggle Scanner Inputs
    // 3. Filter & Group Events
    $('#scan-filter').on('keyup', function () {
        currentFilter = $(this).val();
        renderTable();
    });

    $('#view-list-btn').on('click', function () {
        currentViewMode = 'list';
        $('.scan-view-controls button').removeClass('active');
        $(this).addClass('active');
        renderTable();
    });

    $('#view-group-btn').on('click', function () {
        currentViewMode = 'group';
        $('.scan-view-controls button').removeClass('active');
        $(this).addClass('active');
        renderTable();
    });

    $('#scan-target-type').on('change', function () {
        var val = $(this).val();
        // Hide all secondary inputs
        $('#scan-target-id, #scan-target-post-type, #scan-target-url, #scan-target-page, #scan-target-post, #scan-target-product').hide();

        if (val === 'id') {
            $('#scan-target-id').show();
        } else if (val === 'post_type' || val === 'archive') {
            $('#scan-target-post-type').show();
        } else if (val === 'specific_post_type') {
            $('#scan-target-post-type').show();
            $('#scan-target-specific-post').show();
        } else if (val === 'url') {
            $('#scan-target-url').show();
        } else if (val === 'page') {
            $('#scan-target-page').show();
        } else if (val === 'post') {
            $('#scan-target-post').show();
        } else if (val === 'product') {
            $('#scan-target-product').show();
        }
    });

    // 2. Fetch specific posts when Post Type changes (if specific mode selected)
    $('#scan-target-post-type').on('change', function () {
        var pt = $(this).val();
        var mode = $('#scan-target-type').val();

        if (mode === 'specific_post_type' && pt) {
            var select = $('#scan-target-specific-post');
            select.html('<option>Loading...</option>').prop('disabled', true);

            $.post(optimizeSpeedAdmin.ajaxurl, {
                action: 'os_get_posts_by_type',
                post_type: pt,
                nonce: optimizeSpeedAdmin.nonce
            }, function (response) {
                select.prop('disabled', false).empty();
                if (response.success && response.data.length) {
                    select.append('<option value="">-- Select Item --</option>');
                    response.data.forEach(function (post) {
                        select.append('<option value="' + post.permalink + '">' + post.title + ' (ID: ' + post.id + ')</option>');
                    });
                } else {
                    select.append('<option value="">No posts found</option>');
                }
            });
        }
    });

    $('#start-scan-btn').on('click', function () {
        var btn = $(this);
        var type = $('#scan-target-type').val();
        var spinner = $('#scan-spinner');
        var resultsDiv = $('#scan-results');
        var tbody = $('#scan-results-body');
        var urlDisplay = $('#scan-url-display');

        var targetUrl = '';

        // Reset Context
        currentScanContext = { isSingular: false, type: type, postType: '', id: '', label: '' };

        // Determine Target URL
        if (type === 'homepage') {
            targetUrl = '/';
            currentScanContext.label = 'Homepage';
        } else if (type === 'page') {
            targetUrl = $('#scan-target-page').val();
            if (!targetUrl) { alert('Please select a page'); return; }
            currentScanContext.isSingular = true;
            currentScanContext.postType = 'page';
            currentScanContext.id = $('#scan-target-page').find(':selected').data('id');
            currentScanContext.label = $('#scan-target-page').find(':selected').text().trim();
        } else if (type === 'post') {
            targetUrl = $('#scan-target-post').val();
            if (!targetUrl) { alert('Please select a post'); return; }
            currentScanContext.isSingular = true;
            currentScanContext.postType = 'post';
            currentScanContext.id = $('#scan-target-post').find(':selected').data('id');
        } else if (type === 'product') {
            targetUrl = $('#scan-target-product').val();
            if (!targetUrl) { alert('Please select a product'); return; }
            currentScanContext.isSingular = true;
            currentScanContext.postType = 'product';
            currentScanContext.id = $('#scan-target-product').find(':selected').data('id');
        } else if (type === 'id') {
            var id = $('#scan-target-id').val();
            if (!id) { alert('Please enter a Page ID'); return; }
            targetUrl = '/?p=' + id;
            currentScanContext.isSingular = true;
            currentScanContext.postType = 'page'; // Assumption, or we need to fetch it.
            currentScanContext.id = id;
        } else if (type === 'url') {
            targetUrl = $('#scan-target-url').val();
            if (!targetUrl) { alert('Please enter a URL'); return; }
            // URL might be singular but we don't know ID/Type easily. Treat as custom URL (global/specific context limited).
        } else if (type === 'post_type') {
            var pt = $('#scan-target-post-type').val();
            if (!pt) { alert('Please select a Post Type'); return; }
            currentScanContext.postType = pt;

            if (window.osData && window.osData.sample_urls && window.osData.sample_urls[pt] && window.osData.sample_urls[pt].single) {
                targetUrl = window.osData.sample_urls[pt].single;
                // This is a "sample" single, but user selected "Latest of Type".
                // We should probably treat this as a generic context where they mostly want to apply to Post Type.
                // But wait, if they scan "Latest Product", they might see assets specific to THAT product.
                // Current UI says "Latest of Post Type...".
                // We'll mark it as singular so they CAN choose "Specific" (if we could get ID) or "Type".
                // But getting ID for "latest" is tricky without fetching. 
                // Let's rely on returned data? No, scanner result doesn't return ID.
                // Let's Just treat as Post Type context mainly.
                currentScanContext.isSingular = false; // It's a "proxy" scan
            } else {
                alert('No published posts found for this type to scan.');
                return;
            }
        } else if (type === 'specific_post_type') {
            targetUrl = $('#scan-target-specific-post').val(); // This is URL
            if (!targetUrl) { alert('Please select a specific item to scan'); return; }

            // Extract ID from text like "Title (ID: 123)"
            var text = $('#scan-target-specific-post option:selected').text();
            var matches = text.match(/\(ID: (\d+)\)/);
            if (matches && matches[1]) {
                currentScanContext.isSingular = true;
                currentScanContext.id = matches[1];
                currentScanContext.postType = $('#scan-target-post-type').val();
            }
        }

        // Build full URL if needed
        if (targetUrl.indexOf('http') !== 0) {
            var baseUrl = (optimizeSpeedAdmin && optimizeSpeedAdmin.siteUrl) ? optimizeSpeedAdmin.siteUrl : window.location.origin;
            if (baseUrl.endsWith('/') && targetUrl.startsWith('/')) {
                baseUrl = baseUrl.slice(0, -1);
            }
            targetUrl = baseUrl + targetUrl;
        }

        btn.prop('disabled', true);
        spinner.addClass('is-active');
        resultsDiv.hide();
        tbody.html('<tr><td colspan="6" style="text-align:center;padding:20px;">🔍 Scanning assets...</td></tr>');
        urlDisplay.text('Scanning: ' + targetUrl);

        // Use AJAX endpoint for reliable scanning
        $.ajax({
            url: optimizeSpeedAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'os_scan_assets',
                nonce: optimizeSpeedAdmin.nonce,
                url: targetUrl
            },
            success: function (response) {
                btn.prop('disabled', false);
                spinner.removeClass('is-active');
                tbody.empty();

                if (response.success) {
                    // STORE DATA GLOBAL
                    currentRawAssets = response.data;
                    renderTable(); // Initial Render

                    resultsDiv.fadeIn();
                } else {
                    var msg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    alert('Scan failed: ' + msg);
                }
            },
            error: function (xhr, status, error) {
                btn.prop('disabled', false);
                spinner.removeClass('is-active');
                tbody.empty();
                console.error('AJAX error:', error);
                alert('Scan request failed: ' + error);
            }
        });
    });

    $('#clear-scan-btn').on('click', function () {
        $('#scan-results').hide();
        $('#scan-results-body').empty();
    });

    // Add Scanned Rule to Table - REFACTORED
    $(document).on('click', '.add-scanned-rule', function () {
        var btn = $(this);
        var row = btn.closest('tr');
        var handle = btn.data('handle');
        var type = btn.data('type');
        var strategy = row.find('.scan-strategy-select').val() || 'disable';

        // Scope Logic
        var scope = row.find('.scan-scope-select').val();
        // If no select (Global/Homepage), infer from context
        if (!scope) {
            if (currentScanContext.type === 'homepage') scope = 'homepage';
            else scope = 'global';
        }

        var destTab = 'global';
        var targetStart = 'global';
        var customIdVal = '';
        var targetDisplay = 'Global';

        if (scope === 'specific') {
            destTab = 'specific';
            targetStart = 'custom';
            customIdVal = currentScanContext.id;
            targetDisplay = 'ID: ' + escapeHtml(customIdVal);
        } else if (scope === 'post_type') {
            destTab = 'specific';
            targetStart = 'post_type';
            customIdVal = currentScanContext.postType;
            targetDisplay = 'Type: ' + escapeHtml(customIdVal);
        } else if (scope === 'homepage') {
            destTab = 'homepage';
            targetStart = 'homepage';
            targetDisplay = 'Homepage Only';
        } else if (scope === 'global') {
            destTab = 'global';
            targetStart = 'global';
            targetDisplay = 'Global';
        }

        var tbody = $('#rules-' + destTab + ' .rules-tbody-target');
        var newIndex = new Date().getTime();

        // Scroll to tab container
        var $tabs = $(".os-rules-tabs");
        if ($tabs.length) {
            $('html, body').animate({
                scrollTop: $tabs.offset().top - 100
            }, 500);
        } else {
            // Fallback if tabs not found
            var $table = $(".rules-table-v2").first();
            if ($table.length) {
                $('html, body').animate({
                    scrollTop: $table.offset().top - 100
                }, 500);
            }
        }

        // Switch Tab
        $('.nav-tab[href="#rules-' + destTab + '"]').click();

        var typeJsSel = (type === 'js') ? 'selected' : '';
        var typeCssSel = (type === 'css') ? 'selected' : '';

        var stratAsync = (strategy === 'async') ? 'selected' : '';
        var stratDefer = (strategy === 'defer') ? 'selected' : '';
        var stratDelay = (strategy === 'delay') ? 'selected' : '';
        var stratPreload = (strategy === 'preload') ? 'selected' : '';
        var stratDisable = (strategy === 'disable') ? 'selected' : '';

        var crossStyle = (strategy === 'preload') ? 'block' : 'none';

        var rowHtml = `
            <tr class="rule-row info-row" style="background:#eafce3">
                 <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][target]" value="${targetStart}">
                
                <td width="30%">
                    <strong>${escapeHtml(handle)}</strong>
                    <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][handle]" value="${escapeHtml(handle)}">
                    
                    <div class="target-display" style="margin-top:5px; font-size:12px; color:#666;">
                        ${targetDisplay}
                        <input type="number" 
                            name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_num]" 
                            value="${customIdVal}" 
                            style="display:none;" class="target-id-input">
                        <input type="hidden" class="final-custom-id"
                            name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id]"
                            value="${customIdVal}">
                         <input type="hidden" name="optimize_speed_settings[script_manager_rules][${newIndex}][custom_id_type]" value="${targetStart === 'post_type' ? customIdVal : ''}">
                    </div>
                </td>
                <td width="15%">
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][type]" style="width:100%">
                         <option value="js" ${typeJsSel}>JS</option>
                        <option value="css" ${typeCssSel}>CSS</option>
                    </select>
                </td>
                <td width="35%">
                    <select name="optimize_speed_settings[script_manager_rules][${newIndex}][strategy]" class="rule-strategy-select" style="width:100%">
                        <option value="async" ${stratAsync}>Async</option>
                        <option value="defer" ${stratDefer}>Defer</option>
                        <option value="delay" ${stratDelay}>Delay</option>
                        <option value="preload" ${stratPreload}>Preload</option>
                        <option value="disable" ${stratDisable}>Disable</option>
                    </select>
                     <label class="crossorigin-opt" style="display:${crossStyle}; margin-top:4px; font-size:11px;">
                        <input type="checkbox" name="optimize_speed_settings[script_manager_rules][${newIndex}][crossorigin]" value="1">
                        Crossorigin
                    </label>
                </td>
                <td width="10%" style="text-align:right;">
                    <button type="button" class="button remove-rule-btn" style="color:#a00;"><span class="dashicons dashicons-trash"></span></button>
                </td>
            </tr>
        `;

        tbody.find('.no-rules-row').remove();
        tbody.prepend(rowHtml);

        // Mark as added
        btn.prop('disabled', true).text('Added ✓');
    });

    // Select All Checkbox
    $('#scan-select-all').on('change', function () {
        var checked = $(this).prop('checked');
        $('.scan-item-check').prop('checked', checked);
    });

    // --- RENDER TABLE LOGIC ---

    // Main Render Function
    function renderTable() {
        var tbody = $('#scan-results-body');
        tbody.empty();

        if (!currentRawAssets || !currentRawAssets.js && !currentRawAssets.css) {
            tbody.html('<tr><td colspan="6" style="text-align:center;">No assets found or scan not run.</td></tr>');
            return;
        }

        // Combine JS and CSS for easier filtering/sorting
        var allAssets = [];
        if (currentRawAssets.js) {
            currentRawAssets.js.forEach(function (a) { a.type = 'js'; allAssets.push(a); });
        }
        if (currentRawAssets.css) {
            currentRawAssets.css.forEach(function (a) { a.type = 'css'; allAssets.push(a); });
        }

        // Filter
        var filtered = allAssets;
        if (currentFilter && currentFilter.trim() !== '') {
            var term = currentFilter.toLowerCase();
            filtered = allAssets.filter(function (item) {
                return (item.handle && item.handle.toLowerCase().includes(term)) ||
                    (item.src && item.src.toLowerCase().includes(term));
            });
        }

        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="7" style="text-align:center;">No assets match your filter.</td></tr>');
            return;
        }

        // View Mode: Group or List
        if (currentViewMode === 'group') {
            renderGroups(filtered, tbody);
        } else {
            // Default List
            filtered.forEach(function (item) {
                tbody.append(buildScanRow(item, item.type));
            });
        }
    }

    // Render Grouped View
    function renderGroups(assets, tbody) {
        // Group by Source
        var groups = {};
        assets.forEach(function (item) {
            var src = item.source || 'other';
            if (!groups[src]) groups[src] = [];
            groups[src].push(item);
        });

        // Order: Plugin, Theme, Core, Other
        var order = ['plugin', 'theme', 'core', 'other'];

        order.forEach(function (key) {
            if (groups[key] && groups[key].length) {
                var label = key.charAt(0).toUpperCase() + key.slice(1);
                var icon = '🔌';
                if (key === 'theme') icon = '🎨';
                if (key === 'core') icon = 'WordPress';

                // Group Header
                tbody.append(`<tr class="scan-group-header" style="background:#f0f0f1; font-weight:bold;"><td colspan="7" style="padding:10px;">${icon} ${label} (${groups[key].length})</td></tr>`);

                groups[key].forEach(function (item) {
                    tbody.append(buildScanRow(item, item.type));
                });
            }
        });
    }

    console.log('Optimize Speed Admin JS Setup Complete');
});
