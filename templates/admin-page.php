<?php
if (!defined('ABSPATH'))
    exit;

$options = get_option('optimize_speed_settings', []);
?>

<div class="wrap optimize-speed-wrapper">
    <h1>⚡ Optimize Speed Settings</h1>
    <p class="description">Comprehensive performance optimization for your WordPress site.</p>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper os-main-nav">
        <a href="#bloat-removal" class="nav-tab nav-tab-active">🧹 Bloat Removal</a>
        <a href="#performance" class="nav-tab">🚀 Performance</a>
        <a href="#script-manager" class="nav-tab">📜 Script Manager</a>
        <a href="#partytown" class="nav-tab">🎭 Partytown</a>
        <a href="#database" class="nav-tab">🗄️ Database</a>
        <a href="#images" class="nav-tab">🖼️ Images</a>
    </nav>

    <!-- Tab Content -->
    <!-- Tab Content -->
    <form method="post" action="options.php" class="optimize-form">
        <?php settings_fields('optimize_speed_group'); ?>

        <div class="tab-content">
            <!-- Bloat Removal Tab -->
            <div id="bloat-removal" class="tab-pane active">
                <div class="settings-section">
                    <h2>Bloat Removal Options</h2>
                    <p class="description">Enable the optimizations you need. Each option removes unnecessary WordPress
                        features.</p>

                    <div class="bloat-removal-grid">
                        <?php
                        $config = OptimizeSpeed\Services\AdminService::get_settings_config();
                        foreach ($config['bloat_removal'] as $field) {
                            [$key, $label, $description] = $field;
                            $value = isset($options[$key]) ? $options[$key] : 0;
                            $has_warning = in_array($key, ['optimize_elementor', 'remove_jquery']);
                            $is_number_input = ($key === 'limit_post_revisions_number');

                            if ($is_number_input) {
                                // Special number input field
                                ?>
                                <div class="option-card option-card-number">
                                    <span class="option-content">
                                        <span class="option-title"><?php echo esc_html($label); ?></span>
                                        <span class="option-desc"><?php echo esc_html($description); ?></span>
                                        <input type="number" name="optimize_speed_settings[<?php echo esc_attr($key); ?>]"
                                            value="<?php echo esc_attr($value ?: 5); ?>" min="1" max="50" placeholder="5"
                                            class="small-text" style="margin-top: 8px;">
                                    </span>
                                </div>
                                <?php
                            } else {
                                // Regular checkbox field
                                ?>
                                <label class="option-card">
                                    <input type="checkbox" name="optimize_speed_settings[<?php echo esc_attr($key); ?>]"
                                        value="1" <?php checked(1, $value); ?>>
                                    <span class="option-content">
                                        <span class="option-title"><?php echo esc_html($label); ?></span>
                                        <?php if ($description): ?>
                                            <span class="option-desc"><?php echo esc_html($description); ?></span>
                                        <?php endif; ?>
                                        <?php if ($has_warning): ?>
                                            <span class="option-warning">⚠️
                                                <?php echo $key === 'remove_jquery' ? 'May break themes/plugins using jQuery' : "Don't enable if using Elementor Header/Footer"; ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="settings-section" style="margin-top: 20px;">
                    <h2>🔒 Security</h2>
                    <p class="description">Protect your WordPress login from brute-force attacks.</p>

                    <div class="bloat-removal-grid">
                        <div class="option-card" style="grid-column: span 2;">
                            <span class="option-content">
                                <span class="option-title">Custom Login URL</span>
                                <span class="option-desc">Hide wp-login.php and wp-admin with a custom URL. Leave empty
                                    to disable.</span>
                                <div style="margin-top: 10px; display: flex; gap: 10px; align-items: center;">
                                    <code
                                        style="background: #f0f0f1; padding: 8px 12px; border-radius: 4px;"><?php echo esc_url(home_url('/')); ?></code>
                                    <input type="text" name="optimize_speed_settings[custom_login_slug]"
                                        value="<?php echo esc_attr(isset($options['custom_login_slug']) ? $options['custom_login_slug'] : ''); ?>"
                                        placeholder="my-secret-login" class="regular-text" pattern="[a-z0-9-]+"
                                        style="max-width: 200px;">
                                </div>
                                <?php if (!empty($options['custom_login_slug'])): ?>
                                    <div
                                        style="margin-top: 10px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                                        ✅ <strong>Active:</strong> Your login URL is now
                                        <a href="<?php echo esc_url(home_url($options['custom_login_slug'])); ?>"
                                            target="_blank">
                                            <?php echo esc_url(home_url($options['custom_login_slug'])); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <p style="margin-top: 8px; color: #d63638; font-size: 12px;">
                                    ⚠️ <strong>Warning:</strong> Remember your new login URL! If you forget it, disable
                                    the plugin via FTP.
                                </p>
                            </span>
                        </div>
                    </div>
                </div>

                <?php submit_button('Save Settings', 'primary', 'submit_bloat'); ?>
            </div>

            <!-- Partytown Tab -->
            <div id="partytown" class="tab-pane">
                <div class="settings-section">
                    <h2>Partytown Integrations</h2>
                    <p class="description">Configure third-party script tracking IDs to load via Partytown (off main
                        thread).</p>

                    <div class="integration-grid">
                        <?php
                        $integrations = [
                            'gtm' => ['Google Tag Manager', 'GTM-XXXXXXX'],
                            'gtag' => ['Google Analytics', 'G-XXXXXXXXXX'],
                            'fbpixel' => ['Facebook Pixel', '1234567890'],
                            'matomo' => ['Matomo Site ID', '1'],
                            'tiktok' => ['TikTok Pixel', 'XXXXXXXXXXXXX'],
                            'clarity' => ['Microsoft Clarity', 'xxxxxxxxxx']
                        ];
                        foreach ($integrations as $key => $info) {
                            [$label, $placeholder] = $info;
                            $value = isset($options[$key]) ? $options[$key] : '';
                            ?>
                            <div class="integration-field">
                                <label for="<?php echo esc_attr($key); ?>">
                                    <strong><?php echo esc_html($label); ?></strong>
                                </label>
                                <input type="text" id="<?php echo esc_attr($key); ?>"
                                    name="optimize_speed_settings[<?php echo esc_attr($key); ?>]"
                                    value="<?php echo esc_attr($value); ?>"
                                    placeholder="<?php echo esc_attr($placeholder); ?>" class="regular-text">
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>

                <?php submit_button('Save Settings'); ?>
            </div>

            <!-- Database Optimization Tab -->
            <div id="database" class="tab-pane">
                <div class="settings-section">
                    <h2>Database Optimization Tools</h2>
                    <p class="description">Clean up your database to reduce size and improve performance.</p>

                    <div class="db-tools-grid">
                        <button type="button" class="db-tool-card" data-action="transients">
                            <span class="dashicons dashicons-update"></span>
                            <strong>Clean Expired Transients</strong>
                            <small>Remove expired cached data</small>
                        </button>

                        <button type="button" class="db-tool-card" data-action="all_transients">
                            <span class="dashicons dashicons-trash"></span>
                            <strong>Clean All Transients</strong>
                            <small>Remove all transient cache</small>
                        </button>

                        <button type="button" class="db-tool-card" data-action="revisions">
                            <span class="dashicons dashicons-backup"></span>
                            <strong>Clean Post Revisions</strong>
                            <small>Remove old post revisions</small>
                        </button>

                        <button type="button" class="db-tool-card" data-action="auto_drafts">
                            <span class="dashicons dashicons-media-document"></span>
                            <strong>Clean Auto Drafts</strong>
                            <small>Remove auto-saved drafts</small>
                        </button>

                        <button type="button" class="db-tool-card" data-action="trash_spam">
                            <span class="dashicons dashicons-dismiss"></span>
                            <strong>Clean Trash & Spam</strong>
                            <small>Empty trash and remove spam</small>
                        </button>

                        <button type="button" class="db-tool-card primary" data-action="optimize_tables">
                            <span class="dashicons dashicons-performance"></span>
                            <strong>Optimize Tables</strong>
                            <small>Optimize database tables</small>
                        </button>
                    </div>

                    <div id="db-optimization-result"></div>
                </div>
            </div>

            <!-- Script Manager Tab -->
            <div id="script-manager" class="tab-pane">
                <div class="settings-section">
                    <h2>Global Script Settings</h2>
                    <p class="description">Control how JavaScript is loaded globally across your site.</p>
                    <div class="bloat-removal-grid">
                        <label class="option-card">
                            <input type="checkbox" name="optimize_speed_settings[defer_javascript]" value="1" <?php checked(1, isset($options['defer_javascript']) ? $options['defer_javascript'] : 0); ?>>
                            <span class="option-content">
                                <span class="option-title">Defer JavaScript</span>
                                <span class="option-desc">Add defer attribute to scripts globally.</span>
                            </span>
                        </label>
                        <label class="option-card">
                            <input type="checkbox" name="optimize_speed_settings[delay_javascript]" value="1" <?php checked(1, isset($options['delay_javascript']) ? $options['delay_javascript'] : 0); ?>>
                            <span class="option-content">
                                <span class="option-title">Delay JavaScript Execution</span>
                                <span class="option-desc">Delay until user interaction (Click, Scroll, Move).</span>
                            </span>
                        </label>

                        <div class="option-card" style="grid-column: span 2;">
                            <span class="option-content">
                                <span class="option-title">Delay JS Keywords</span>
                                <span class="option-desc">One per line. Handle or filename (e.g.,
                                    <code>jquery-migrate</code>, <code>slider.js</code>).</span>
                                <textarea name="optimize_speed_settings[delay_javascript_keywords]" rows="5"
                                    class="large-text code"
                                    style="margin-top:10px;"><?php echo esc_textarea(isset($options['delay_javascript_keywords']) ? $options['delay_javascript_keywords'] : ''); ?></textarea>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Visual Asset Scanner</h2>
                    <p class="description">Select a page to scan for loaded scripts and styles, then configure them
                        visually.</p>

                    <?php
                    // Get pages for dropdown (cached for 1 hour)
                    $pages = get_transient('os_scanner_pages');
                    if (false === $pages) {
                        $pages = get_pages(['number' => 50, 'sort_column' => 'menu_order,post_title']);
                        set_transient('os_scanner_pages', $pages, HOUR_IN_SECONDS);
                    }

                    // Get recent posts (cached for 1 hour)
                    $recent_posts = get_transient('os_scanner_posts');
                    if (false === $recent_posts) {
                        $recent_posts = get_posts([
                            'post_type' => 'post',
                            'numberposts' => 20,
                            'post_status' => 'publish'
                        ]);
                        set_transient('os_scanner_posts', $recent_posts, HOUR_IN_SECONDS);
                    }

                    // Get WooCommerce products if exists (cached for 1 hour)
                    $products = get_transient('os_scanner_products');
                    if (false === $products) {
                        $products = [];
                        if (post_type_exists('product')) {
                            $products = get_posts([
                                'post_type' => 'product',
                                'numberposts' => 20,
                                'post_status' => 'publish'
                            ]);
                        }
                        set_transient('os_scanner_products', $products, HOUR_IN_SECONDS);
                    }

                    // --- Pre-fetch Post Types for Dropdown & JS Data ---
                    $post_types = get_post_types(['public' => true], 'objects');
                    $exclude_types = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block'];

                    $pt_js = [];
                    $sample_urls = [];

                    foreach ($post_types as $pt) {
                        if (!in_array($pt->name, $exclude_types)) {
                            $pt_js[] = ['slug' => $pt->name, 'label' => $pt->label];

                            // Get Latest Post of this Type
                            $latest_posts = get_posts([
                                'post_type' => $pt->name,
                                'numberposts' => 1,
                                'post_status' => 'publish',
                            ]);

                            $single_url = '';
                            if (!empty($latest_posts)) {
                                $single_url = get_permalink($latest_posts[0]->ID);
                            }

                            // Get Archive URL (if registered)
                            $archive_url = '';
                            if ($pt->has_archive) {
                                $archive_url = get_post_type_archive_link($pt->name);
                            }

                            $sample_urls[$pt->name] = [
                                'single' => $single_url,
                                'archive' => $archive_url
                            ];
                        }
                    }

                    // Fetch page templates
                    $templates = get_page_templates(null, 'page');
                    if (empty($templates)) {
                        $templates = array_flip(get_page_templates());
                    }
                    $tpl_js = [];
                    foreach ($templates as $tname => $tfile) {
                        $tpl_js[] = ['file' => $tfile, 'name' => $tname];
                    }
                    ?>

                    <div
                        style="display:flex; gap:10px; align-items:center; margin-bottom:15px; background:#fff; padding:15px; border:1px solid #ccd0d4; border-radius:4px; flex-wrap:wrap;">
                        <select id="scan-target-type">
                            <option value="homepage">🏠 Homepage</option>
                            <option value="page">📄 Select Page</option>
                            <option value="post">📝 Select Post</option>
                            <?php if (!empty($products)): ?>
                                <option value="product">🛒 Select Product</option>
                            <?php endif; ?>
                            <option value="post_type">📁 Latest of Post Type...</option>
                            <option value="specific_post_type">🗂️ Post of Type...</option>
                            <option value="archive">📚 Archive of Type...</option>
                            <option value="url">🔗 Custom URL</option>
                            <option value="id">🔢 Specific Page ID</option>
                        </select>

                        <!-- Page Select Dropdown -->
                        <select id="scan-target-page" style="display:none; min-width:200px;">
                            <option value="">-- Select Page --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr(get_permalink($page->ID)); ?>" data-id="<?php echo esc_attr($page->ID); ?>">
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Post Select Dropdown -->
                        <select id="scan-target-post" style="display:none; min-width:200px;">
                            <option value="">-- Select Post --</option>
                            <?php foreach ($recent_posts as $post): ?>
                                <option value="<?php echo esc_attr(get_permalink($post->ID)); ?>" data-id="<?php echo esc_attr($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Product Select Dropdown -->
                        <?php if (!empty($products)): ?>
                            <select id="scan-target-product" style="display:none; min-width:200px;">
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo esc_attr(get_permalink($prod->ID)); ?>" data-id="<?php echo esc_attr($prod->ID); ?>">
                                        <?php echo esc_html($prod->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <!-- Manual ID Input -->
                        <input type="number" id="scan-target-id" placeholder="Page ID (e.g., 123)"
                            style="display:none; width:100px;">

                        <!-- Manual URL Input -->
                        <input type="url" id="scan-target-url" placeholder="https://..."
                            style="display:none; width:300px;">

                        <!-- Post Type Select -->
                        <select id="scan-target-post-type" style="display:none;">
                            <option value="">Select Type</option>
                            <?php foreach ($pt_js as $pt): ?>
                                <option value="<?php echo esc_attr($pt['slug']); ?>"><?php echo esc_html($pt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Specific Post Select -->
                        <select id="scan-target-specific-post" style="display:none; min-width:200px;">
                            <option value="">-- Select Item --</option>
                        </select>

                        <button type="button" class="button button-primary" id="start-scan-btn">🔍 Scan Assets</button>
                        <span class="spinner" id="scan-spinner"></span>
                    </div>

                    <div id="scan-results" style="display:none;">
                        <h3>Scanned Assets <span id="scan-url-display"
                                style="font-weight:normal; font-size:12px; color:#666;"></span></h3>
                        
                        <!-- Filter & Grouping Controls -->
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px; align-items:center;">
                            <input type="text" id="scan-filter" placeholder="Filter assets (e.g. jquery, slider)..." style="width:300px;">
                            
                            <div class="scan-view-controls">
                                <button type="button" class="button button-secondary active" data-view="list" id="view-list-btn">📝 List View</button>
                                <button type="button" class="button button-secondary" data-view="group" id="view-group-btn">📂 Group by Source</button>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped" id="scan-table">
                            <thead>
                                <tr>
                                    <th width="3%"><input type="checkbox" id="scan-select-all"></th>
                                    <th width="20%">Asset Handle</th>
                                    <th width="8%">Size</th>
                                    <th width="5%">Type</th>
                                    <th width="10%">Source</th>
                                    <th width="15%">Scope</th>
                                    <th width="12%">Strategy</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="scan-results-body">
                                <!-- Results populated by JS -->
                            </tbody>
                        </table>
                        <p style="margin-top:10px; text-align:right;">
                            <button type="button" class="button" id="add-selected-btn" style="margin-right:10px;">➕ Add
                                Selected Rules</button>
                            <button type="button" class="button" id="clear-scan-btn">Clear Results</button>
                        </p>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Script Rules Engine</h2>
                    <p class="description">Define specific rules for plugins or pages. Overrides global settings.</p>

                    <!-- New Tabbed Interface for Rules -->
                    <div class="os-rules-tabs nav-tab-wrapper" style="margin-bottom: 15px;">
                        <a href="#rules-global" class="nav-tab nav-tab-active">Global Rules</a>
                        <a href="#rules-homepage" class="nav-tab">Homepage Rules</a>
                        <a href="#rules-specific" class="nav-tab">Page/Post Specific</a>
                    </div>

                    <?php
                    // Helper function to render rule row (since we now have multiple loops)
                    if (!function_exists('os_render_rule_row')) {
                        function os_render_rule_row($i, $rule, $post_types, $templates, $exclude_types)
                        {
                            $rule_target = isset($rule['target']) ? $rule['target'] : 'global';
                            $custom_id = isset($rule['custom_id']) ? $rule['custom_id'] : '';
                            $show_custom = ($rule_target === 'custom');
                            $show_post_type = ($rule_target === 'post_type');
                            $show_template = ($rule_target === 'page_template');
                            $handle = isset($rule['handle']) ? $rule['handle'] : '';
                            ?>
                            <tr class="rule-row info-row">
                                <!-- Hidden Index / Metadata -->
                                <input type="hidden" name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][target]" class="rule-target-val" value="<?php echo esc_attr($rule_target); ?>">
                                
                                <td width="30%">
                                    <strong><?php echo esc_html($handle); ?></strong>
                                    <?php if(!empty($rule['is_regex'])): ?>
                                        <span class="os-badge-small">Regex</span>
                                    <?php endif; ?>
                                    
                                    <!-- Inputs for saving (hidden mostly) -->
                                    <input type="hidden" name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][handle]" value="<?php echo esc_attr($handle); ?>">
                                    <input type="hidden" name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][is_regex]" value="<?php echo isset($rule['is_regex']) ? $rule['is_regex'] : 0; ?>">

                                    <!-- Target Info Display -->
                                    <div class="target-display" style="margin-top:5px; font-size:12px; color:#666;">
                                        <?php if($rule_target === 'global'): ?>
                                            Global
                                        <?php elseif($rule_target === 'homepage'): ?>
                                            Homepage Only
                                        <?php elseif($rule_target === 'custom'): ?>
                                            ID: <?php echo esc_html($custom_id); ?>
                                        <?php elseif($rule_target === 'post_type'): ?>
                                            Type: <?php echo esc_html($custom_id); ?>
                                        <?php elseif($rule_target === 'page_template'): ?>
                                            Tpl: <?php echo esc_html($custom_id); ?>
                                        <?php endif; ?>
                                        
                                        <!-- Keep hidden inputs for target data -->
                                        <input type="number" 
                                            name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][custom_id_num]" 
                                            value="<?php echo ($show_custom ? esc_attr($custom_id) : ''); ?>" 
                                            style="display:none;" class="target-id-input">
                                        
                                        <input type="hidden" class="final-custom-id"
                                            name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][custom_id]"
                                            value="<?php echo esc_attr($custom_id); ?>">

                                        <!-- Post Type Select Hidden Clone (simplification) -->
                                        <input type="hidden" name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][custom_id_type]" value="<?php echo $show_post_type ? esc_attr($custom_id) : ''; ?>">
                                        <input type="hidden" name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][custom_id_tpl]" value="<?php echo $show_template ? esc_attr($custom_id) : ''; ?>">
                                    </div>
                                </td>

                                <td width="15%">
                                    <select name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][type]" style="width:100%">
                                        <option value="js" <?php selected(isset($rule['type']) ? $rule['type'] : 'js', 'js'); ?>>JS</option>
                                        <option value="css" <?php selected(isset($rule['type']) ? $rule['type'] : 'js', 'css'); ?>>CSS</option>
                                    </select>
                                </td>

                                <td width="35%">
                                    <select name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][strategy]" class="rule-strategy-select" style="width:100%">
                                        <option value="async" <?php selected(isset($rule['strategy']) ? $rule['strategy'] : 'async', 'async'); ?> title="Parallel load">Async</option>
                                        <option value="defer" <?php selected(isset($rule['strategy']) ? $rule['strategy'] : 'async', 'defer'); ?> title="After parsing">Defer</option>
                                        <option value="delay" <?php selected(isset($rule['strategy']) ? $rule['strategy'] : 'async', 'delay'); ?> title="On interaction">Delay</option>
                                        <option value="preload" <?php selected(isset($rule['strategy']) ? $rule['strategy'] : 'async', 'preload'); ?> title="High priority">Preload</option>
                                        <option value="disable" <?php selected(isset($rule['strategy']) ? $rule['strategy'] : 'async', 'disable'); ?> title="Do not load">Disable</option>
                                    </select>
                                    
                                    <label class="crossorigin-opt" style="display:<?php echo (isset($rule['strategy']) && $rule['strategy'] === 'preload') ? 'block' : 'none'; ?>; margin-top:4px; font-size:11px;">
                                        <input type="checkbox" name="optimize_speed_settings[script_manager_rules][<?php echo $i; ?>][crossorigin]" value="1" <?php checked(isset($rule['crossorigin']) ? $rule['crossorigin'] : 0, 1); ?>>
                                        Crossorigin
                                    </label>
                                </td>

                                <td width="10%" style="text-align:right;">
                                    <button type="button" class="button remove-rule-btn" style="color:#a00;"><span class="dashicons dashicons-trash" style="margin-top:3px;"></span></button>
                                </td>
                            </tr>
                            <?php
                        }
                    }

                    // Prepare Groups
                    $rules_global = [];
                    $rules_homepage = [];
                    $rules_specific = [];

                    $rules = isset($options['script_manager_rules']) ? $options['script_manager_rules'] : [];
                    if (!is_array($rules)) $rules = [];

                    foreach ($rules as $i => $rule) {
                        $t = isset($rule['target']) ? $rule['target'] : 'global';
                        $rule['_index'] = $i; // Keep original index for saving
                        
                        if ($t === 'global') $rules_global[] = $rule;
                        elseif ($t === 'homepage') $rules_homepage[] = $rule;
                        else $rules_specific[] = $rule; 
                    }
                    ?>
                    
                    <div id="rules-containers">
                        <?php foreach(['global' => $rules_global, 'homepage' => $rules_homepage, 'specific' => $rules_specific] as $key => $group_rules): ?>
                        <div id="rules-<?php echo $key; ?>" class="rules-tab-content" style="<?php echo $key === 'global' ? '' : 'display:none;'; ?>">
                            <table class="wp-list-table widefat fixed striped rules-table-v2" data-tab="<?php echo $key; ?>">
                                <thead>
                                    <tr>
                                        <th>Asset Info</th>
                                        <th>Type</th>
                                        <th>Strategy</th>
                                        <th style="text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="rules-tbody-target">
                                    <?php if(empty($group_rules)): ?>
                                        <tr class="no-rules-row"><td colspan="4" style="text-align:center; padding:20px; color:#666;">No rules found in this section.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($group_rules as $rule) {
                                            os_render_rule_row($rule['_index'], $rule, $post_types, $templates, $exclude_types);
                                        } ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <!-- Add Manual Rule Button for this section? Maybe too complex. Let's keep scanner as primary add method. -->
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hidden Template for JS Adding Row -->
                    <!-- JS needs to replicate the structure above. -->
                </div>

                <?php submit_button('Save Settings', 'primary', 'submit_scripts'); ?>
            </div>

            <!-- Performance Tab -->
            <div id="performance" class="tab-pane">
                <div class="settings-section">
                    <h2>Advanced Performance</h2>
                    <p class="description">Boost your Core Web Vitals with these advanced optimizations.</p>

                    <div class="bloat-removal-grid">
                        <?php
                        $config = OptimizeSpeed\Services\AdminService::get_settings_config();
                        // Filter for performance keys
                        $perf_keys = [
                            'lazyload_iframes',
                            'local_google_fonts',
                            'preload_resources',
                            'disable_dns_prefetch'
                        ];

                        foreach ($config['bloat_removal'] as $field) {
                            [$key, $label, $description] = $field;
                            if (!in_array($key, $perf_keys))
                                continue;

                            $value = isset($options[$key]) ? $options[$key] : '';

                            if ($key === 'preload_resources') {
                                ?>
                                <div class="option-card" style="grid-column: span 2;">
                                    <span class="option-content">
                                        <span class="option-title"><?php echo esc_html($label); ?></span>
                                        <span class="option-desc"><?php echo esc_html($description); ?></span>
                                        <textarea name="optimize_speed_settings[<?php echo esc_attr($key); ?>]" rows="5"
                                            class="large-text code"
                                            style="margin-top:10px;"><?php echo esc_textarea($value); ?></textarea>
                                    </span>
                                </div>
                                <?php
                            } else {
                                ?>
                                <label class="option-card">
                                    <input type="checkbox" name="optimize_speed_settings[<?php echo esc_attr($key); ?>]"
                                        value="1" <?php checked(1, $value); ?>>
                                    <span class="option-content">
                                        <span class="option-title"><?php echo esc_html($label); ?></span>
                                        <span class="option-desc"><?php echo esc_html($description); ?></span>
                                    </span>
                                </label>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

                <?php submit_button('Save Settings', 'primary', 'submit_perf'); ?>
            </div>

            <!-- Image Optimization Tab -->
            <div id="images" class="tab-pane">
                <div class="settings-section">
                    <h2>Image Optimization Status</h2>
                    <p><strong>Native Image Optimization</strong> is active.</p>
                    <p><strong>Features:</strong></p>
                    <ul>
                        <li>Native Lazy Loading (loading="lazy")</li>
                        <li>LCP Optimization (fetchpriority="high")</li>
                        <li>Auto WebP/AVIF generation</li>
                    </ul>

                    <hr style="margin: 20px 0;">

                    <h3>Configuration</h3>
                    <div class="bloat-removal-grid" style="grid-template-columns: 1fr;">
                        <?php
                        $current_mode = isset($options['image_opt_mode']) ? $options['image_opt_mode'] : 'native';
                        ?>
                        <label class="option-card" style="cursor: pointer;">
                            <input type="radio" name="optimize_speed_settings[image_opt_mode]" value="native" <?php checked('native', $current_mode); ?>>
                            <span class="option-content">
                                <span class="option-title">Mode 1: Native (Recommended)</span>
                                <span class="option-desc">Uses browser-native lazy loading and modern formats
                                    (WebP/AVIF). Best for SEO and Core Web Vitals. No JavaScript overhead.</span>
                            </span>
                        </label>

                        <label class="option-card" style="cursor: pointer;">
                            <input type="radio" name="optimize_speed_settings[image_opt_mode]" value="lqip" <?php checked('lqip', $current_mode); ?>>
                            <span class="option-content">
                                <span class="option-title">Mode 2: LQIP (Low Quality Placeholder)</span>
                                <span class="option-desc">Displays a blurred placeholder initially and lazily loads the
                                    full image using JavaScript. Provides a smoother visual experience.</span>
                            </span>
                        </label>
                    </div>
                    <?php submit_button('Save Settings', 'primary', 'submit_db'); ?>

                    <hr style="margin: 20px 0;">

                    <h3>Regenerate Images</h3>
                    <p>Total Images (JPEG/PNG): <strong id="total-count">loading...</strong></p>

                    <p>
                        <strong>AVIF Status:</strong>
                        <?php
                        $avif_support = false;
                        if (class_exists('Imagick')) {
                            $formats = Imagick::queryFormats();
                            if (in_array('AVIF', $formats) && in_array('HEIF', $formats)) {
                                $avif_support = true;
                            }
                        }
                        if ($avif_support): ?>
                            <span style="color:#00a32a">✅ Active</span>
                        <?php else: ?>
                            <span style="color:#d63638">❌ Not Supported</span>
                        <?php endif; ?>
                        &nbsp;
                        <a href="<?php echo wp_nonce_url(add_query_arg('reset_avif_test', '1'), 'reset_avif_test'); ?>"
                            class="button button-small">Test Again</a>
                        &nbsp;
                        <button id="cleanup-avif-btn" type="button" class="button button-small"
                            style="background:#ff9800;color:#fff;border-color:#f57c00">
                            🗑️ Cleanup Bad AVIF
                        </button>
                    </p>

                    <p>
                        <button id="start-btn" type="button" class="button button-primary button-large">Start
                            Regenerate</button>
                        <button id="pause-btn" type="button" class="button button-secondary"
                            style="display:none">Pause</button>
                        <button id="resume-btn" type="button" class="button button-secondary"
                            style="display:none">Resume</button>
                    </p>

                    <div id="progress-container" style="margin:25px 0;display:none">
                        <div style="background:#ddd;height:40px;border-radius:8px;position:relative;overflow:hidden">
                            <div id="progress-bar" style="background:#2271b1;width:0%;height:100%"></div>
                            <div style="position:absolute;top:10px;left:15px;color:#fff;font-weight:bold">
                                <span id="processed">0</span> / <span id="total">0</span> (<span id="percent">0</span>%)
                            </div>
                        </div>
                    </div>

                    <div id="log"
                        style="background:#1d2327;color:#0f0;padding:15px;height:200px;overflow-y:auto;font-family:monospace;border:1px solid #333;border-radius:4px;font-size:13px">
                        Ready...
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Image regeneration script
    (function () {
        const NONCE_QUEUE = '<?php echo wp_create_nonce('modern_opti_queue'); ?>';
        const NONCE_REGEN = '<?php echo wp_create_nonce('modern_opti_regen'); ?>';
        const NONCE_CLEANUP = '<?php echo wp_create_nonce('modern_opti_cleanup_avif'); ?>';
        const AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';

        let running = false, paused = false, processed = 0, total = 0, queue = [];
        let retries = {};
        const MAX_RETRIES = 2;

        function log(m, c = '#0f0') {
            const l = document.getElementById('log');
            const d = document.createElement('div');
            d.textContent = '[' + new Date().toLocaleTimeString() + '] ' + m;
            d.style.color = c;
            l.appendChild(d);
            l.scrollTop = l.scrollHeight;
        }

        function update() {
            const p = total > 0 ? Math.round((processed / total) * 100) : 0;
            document.getElementById('progress-bar').style.width = p + '%';
            document.getElementById('processed').textContent = processed;
            document.getElementById('percent').textContent = p;
        }

        function processBatch() {
            if (!running || paused || queue.length === 0) {
                if (queue.length === 0 && processed > 0 && running) finish();
                return;
            }
            const batch = queue.splice(0, 1);
            let done = 0;

            batch.forEach(id => {
                fetch(AJAX_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ action: 'modern_opti_regen', id: id, _wpnonce: NONCE_REGEN })
                })
                    .then(r => r.json())
                    .then(d => {
                        done++;
                        if (d && d.success) {
                            processed++;
                            log('✓ Completed ID ' + id, '#0f0');
                            delete retries[id];
                        } else {
                            const msg = (d && d.data ? d.data : 'Unknown error');
                            log('✗ Error ID ' + id + ': ' + msg, '#f55');
                            retries[id] = (retries[id] || 0) + 1;
                            if (retries[id] < MAX_RETRIES) { queue.push(id); } else { processed++; }
                        }
                        update();
                        if (done === batch.length) setTimeout(processBatch, 500);
                    }).catch(err => {
                        done++;
                        log('✗ Network Error ID ' + id, '#fa0');
                        retries[id] = (retries[id] || 0) + 1;
                        if (retries[id] < MAX_RETRIES) { queue.push(id); } else { processed++; }
                        if (done === batch.length) setTimeout(processBatch, 1500);
                    });
            });
        }

        function start() {
            log('Loading image list...');
            fetch(AJAX_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ action: 'modern_opti_queue', _wpnonce: NONCE_QUEUE })
            }).then(r => r.json())
                .then(d => {
                    if (d && d.success) {
                        queue = Array.isArray(d.data.ids) ? d.data.ids : [];
                        total = queue.length;
                        document.getElementById('total-count').textContent = total;
                        document.getElementById('total').textContent = total;
                        document.getElementById('progress-container').style.display = 'block';
                        document.getElementById('start-btn').style.display = 'none';
                        document.getElementById('pause-btn').style.display = 'inline-block';
                        running = true;
                        log('Starting process for ' + total + ' images...');
                        processBatch();
                    } else {
                        log('Error fetching image list!', '#f55');
                    }
                });
        }

        function finish() {
            running = false;
            document.getElementById('pause-btn').style.display = 'none';
            document.getElementById('resume-btn').style.display = 'none';
            document.getElementById('start-btn').style.display = 'inline-block';
            log('ALL DONE!', 'lime');
        }

        document.getElementById('start-btn').onclick = start;
        document.getElementById('pause-btn').onclick = () => {
            paused = true;
            document.getElementById('pause-btn').style.display = 'none';
            document.getElementById('resume-btn').style.display = 'inline-block';
            log('PAUSED', 'orange');
        };
        document.getElementById('resume-btn').onclick = () => {
            paused = false;
            document.getElementById('resume-btn').style.display = 'none';
            document.getElementById('pause-btn').style.display = 'inline-block';
            log('RESUMING...', '#0f0');
            processBatch();
        };

        // Initial count
        fetch(AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ action: 'modern_opti_queue', _wpnonce: NONCE_QUEUE })
        }).then(r => r.json()).then(d => {
            document.getElementById('total-count').textContent = (d && d.success) ? (Array.isArray(d.data.ids) ? d.data.ids.length : 0) : '0';
        });

        document.getElementById('cleanup-avif-btn').onclick = function () {
            if (!confirm('Delete all bad AVIF files (< 100 bytes)?')) return;
            const btn = this; btn.disabled = true;
            btn.textContent = '⏳ Scanning...';
            fetch(AJAX_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ action: 'modern_opti_cleanup_avif', _wpnonce: NONCE_CLEANUP })
            }).then(r => r.json())
                .then(d => {
                    btn.disabled = false; btn.textContent = '🗑️ Cleanup Bad AVIF';
                    if (d && d.success) { alert('Deleted: ' + d.data.deleted + ' files'); }
                    else { alert('Error!'); }
                });
        };
    })();
</script>