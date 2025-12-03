<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class AdminService implements ServiceInterface
{
    const OPTION_GROUP = 'optimize_speed_group';
    const OPTION_NAME = 'optimize_speed_settings';

    public function register()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'settings_init']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_head', [$this, 'admin_styles']);
    }

    public function boot()
    {
    }

    /**
     * Get all settings configuration
     */
    public static function get_settings_config()
    {
        return [
            'bloat_removal' => [
                // WordPress Core
                ['disable_emojis', 'Disable Emojis', 'Removes extra JS/CSS'],
                ['disable_embeds', 'Disable Embeds', 'Removes oEmbed JS/Routes'],
                ['disable_xmlrpc', 'Disable XML-RPC', 'Security & Performance'],
                ['disable_jquery_migrate', 'Disable jQuery Migrate', 'Removes compatibility layer'],
                ['remove_jquery', 'Remove jQuery Completely', '⚠️ May break themes/plugins'],
                ['disable_widget_blocks', 'Disable Widget Block Editor', 'Use classic widgets'],
                
                // Meta Tags & Links
                ['remove_meta_generator', 'Remove Meta Generator Tag', 'Hide WP version'],
                ['remove_wp_version', 'Remove WP Version', 'From all sources'],
                ['remove_wlwmanifest', 'Remove WLW Manifest Link', 'Windows Live Writer'],
                ['remove_shortlink', 'Remove Shortlink Header', 'Link rel shortlink'],
                ['remove_rsd_link', 'Remove RSD Link', 'EditURI for external tools'],
                ['remove_rest_api_link', 'Remove REST API Link', 'Link tag in header'],
                ['disable_canonical', 'Disable Canonical URL', 'Remove rel canonical'],
                
                // RSS & Feeds
                ['remove_rss_feed_links', 'Remove RSS Feed Links', 'Feed discovery links'],
                ['disable_rss_feeds', 'Disable All RSS Feeds', 'Redirect to homepage'],
                ['remove_rss_generator', 'Remove RSS Generator Tag', 'Hide WP version'],
                
                // Database & Revisions
                ['disable_post_revisions', 'Disable Post Revisions', 'Stop saving revisions'],
                ['limit_post_revisions_number', 'Limit Post Revisions', 'Input number: 3-10 recommended'],
                ['disable_app_passwords', 'Disable Application Passwords', 'WP 5.6+ feature'],
                
                // Performance
                ['limit_heartbeat', 'Limit Heartbeat', '60s interval'],
                ['disable_heartbeat', 'Disable Heartbeat API', 'Completely disable'],
                ['disable_dns_prefetch', 'Disable DNS Prefetch', 'Remove resource hints'],
                ['defer_javascript', 'Defer JavaScript', 'Add defer to scripts'],
                
                // Security & API
                ['disable_rest_api', 'Disable REST API', 'Restrict to logged-in users'],
                
                // Assets & Styles
                ['remove_query_strings', 'Remove Query Strings', 'Remove ?ver= from CSS/JS'],
                ['disable_self_pingbacks', 'Disable Self Pingbacks', 'Stop pinging own posts'],
                ['remove_dashicons', 'Remove Dashicons', 'Disable on frontend'],
                ['disable_gravatars', 'Disable Gravatars', 'Remove Gravatar requests'],
                ['disable_google_fonts', 'Disable Google Fonts', 'Block all Google Fonts'],
                ['remove_recent_comments_style', 'Remove Recent Comments Style', 'Widget CSS'],
                
                // Comments
                ['disable_comments', 'Disable Comments', 'Completely disable system'],
                
                // Gutenberg
                ['disable_global_styles', 'Disable Global Styles', 'Remove huge inline CSS & SVGs'],
                ['disable_block_library', 'Disable Block CSS', 'If not using Gutenberg'],
                ['disable_duotone_svg', 'Disable Duotone SVG', 'Remove Gutenberg filters'],
                
                // Page Builders
                ['optimize_elementor', 'Smart Elementor Assets', '⚠️ Don\'t use with Elementor Headers'],
                
                // WooCommerce
                ['disable_wc_cart_fragments', 'Disable WC Cart Fragments', 'Stop AJAX cart refresh'],
                ['remove_wc_scripts_non_wc_pages', 'Remove WC Scripts on Non-WC Pages', 'Blog, pages, etc.'],
                
                // Security
                ['disable_password_strength_meter', 'Disable Password Strength Meter', 'Frontend forms'],
            ]
        ];
    }

    public function add_settings_page()
    {
        add_options_page(
            'Optimize Speed',
            'Optimize Speed',
            'manage_options',
            'optimize-speed',
            [$this, 'render_page']
        );
    }

    public function settings_init()
    {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME);
    }

    public function checkbox_field($args)
    {
        $options = get_option(self::OPTION_NAME, []);
        $key = $args['label_for'];
        $description = $args['label'] ?? '';
        $value = isset($options[$key]) ? $options[$key] : 0;

        // Special handling for Elementor warning
        $has_warning = ($key === 'optimize_elementor');

        echo '<div class="bloat-removal-grid" style="display: contents;">';
        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[' . esc_attr($key) . ']" value="1" ' . checked(1, $value, false) . '>';
        echo '<span class="option-text">';
        echo '<span class="option-title">' . esc_html($args['label']) . '</span>';
        if ($description) {
            echo '<span class="option-desc">' . esc_html($description) . '</span>';
        }
        if ($has_warning) {
            echo '<span class="option-warning">⚠️ Do not enable if you use Elementor for Header/Footer on non-Elementor pages</span>';
        }
        echo '</span>';
        echo '</label>';
        echo '</div>';
    }

    public function text_field($args)
    {
        $options = get_option(self::OPTION_NAME, []);
        $key = $args['label_for'];
        $value = isset($options[$key]) ? $options[$key] : '';

        echo '<input type="text" name="' . self::OPTION_NAME . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function render_field($args)
    {
        $options = get_option(self::OPTION_NAME, []);
        $id = $args['id'];
        $type = $args['type'];
        $value = isset($options[$id]) ? $options[$id] : '';

        if ($type === 'checkbox') {
            echo '<input type="checkbox" name="' . self::OPTION_NAME . '[' . $id . ']" value="1" ' . checked(1, $value, false) . '>';
        } else {
            echo '<input type="' . $type . '" name="' . self::OPTION_NAME . '[' . $id . ']" value="' . esc_attr($value) . '" class="regular-text">';
        }
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Clear opcache for this template to force reload
        $template_path = OPTIMIZE_SPEED_DIR . 'templates/admin-page.php';
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($template_path, true);
        }

        // Include the template
        include $template_path;
    }

    public function admin_assets($hook)
    {
        if ($hook !== 'settings_page_optimize-speed') {
            return;
        }

        // Enqueue admin JavaScript
        wp_enqueue_script(
            'optimize-speed-admin',
            plugins_url('assets/js/admin.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.4', // Increment version to bust cache
            true
        );

        // Localize script with nonce
        wp_localize_script('optimize-speed-admin', 'optimizeSpeedAdmin', [
            'nonce' => wp_create_nonce('optimize_speed_admin_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    public function admin_styles()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'settings_page_optimize-speed') {
            return;
        }

        // Output tab-based CSS
        echo '<style>
            /* Wrapper - Full Width */
            .optimize-speed-wrapper {
                max-width: 100%;
                margin: 20px 20px 20px 0;
            }
            
            .optimize-speed-wrapper h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            /* Tab Navigation */
            .nav-tab-wrapper {
                border-bottom: 1px solid #c3c4c7;
                margin: 20px 0 0 0;
                padding: 0;
            }
            
            .nav-tab {
                font-size: 15px;
                padding: 12px 20px;
                font-weight: 500;
            }
            
            /* Tab Content */
            .tab-content {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-top: none;
            }
            
            .tab-pane {
                display: none;
                padding: 30px;
            }
            
            .tab-pane.active {
                display: block;
            }
            
            .settings-section h2 {
                margin-top: 0;
                font-size: 20px;
                font-weight: 600;
            }
            
            /* Bloat Removal Grid */
            .bloat-removal-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 15px;
                margin: 25px 0;
            }
            
            .option-card {
                display: flex;
                align-items: flex-start;
                padding: 15px;
                background: #f9f9f9;
                border: 2px solid #dcdcde;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .option-card:hover {
                background: #f0f6fc;
                border-color: #2271b1;
                box-shadow: 0 2px 8px rgba(34, 113, 177, 0.15);
                transform: translateY(-1px);
            }
            
            .option-card input[type="checkbox"] {
                margin: 4px 12px 0 0;
                flex-shrink: 0;
                width: 18px;
                height: 18px;
            }
            
            .option-content {
                flex: 1;
            }
            
            .option-title {
                display: block;
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 4px;
                font-size: 14px;
            }
            
            .option-desc {
                display: block;
                font-size: 12px;
                color: #646970;
                line-height: 1.4;
            }
            
            .option-warning {
                display: block;
                margin-top: 8px;
                padding: 6px 10px;
                background: #fcf0f1;
                border-left: 3px solid #d63638;
                font-size: 11px;
                color: #d63638;
                border-radius: 0 3px 3px 0;
            }
            
            /* Number Input Card */
            .option-card-number {
                cursor: default;
                border-color: #8c8f94;
            }
            
            .option-card-number:hover {
                background: #f9f9f9;
                border-color: #8c8f94;
                box-shadow: none;
                transform: none;
            }
            
            .option-card-number input[type="number"] {
                width: 80px;
                padding: 5px 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
                font-size: 14px;
            }
            
            .option-card-number input[type="number"]:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            
            /* Integration Grid */
            .integration-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin: 25px 0;
            }
            
            .integration-field label {
                display: block;
                margin-bottom: 8px;
                font-size: 14px;
            }
            
            .integration-field input[type="text"] {
                width: 100%;
                padding: 10px;
                font-size: 14px;
            }
            
            /* Database Tools Grid */
            .db-tools-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 15px;
                margin: 25px 0;
            }
            
            .db-tool-card {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 25px 20px;
                background: #fff;
                border: 2px solid #dcdcde;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .db-tool-card:hover {
                border-color: #2271b1;
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.15);
                transform: translateY(-2px);
            }
            
            .db-tool-card.primary {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
            }
            
            .db-tool-card.primary:hover {
                background: #135e96;
                border-color: #135e96;
            }
            
            .db-tool-card .dashicons {
                width: 40px;
                height: 40px;
                font-size: 40px;
                margin-bottom: 12px;
                color: #2271b1;
            }
            
            .db-tool-card.primary .dashicons {
                color: #fff;
            }
            
            .db-tool-card strong {
                display: block;
                font-size: 15px;
                margin-bottom: 6px;
            }
            
            .db-tool-card.primary strong {
                color: #fff;
            }
            
            .db-tool-card small {
                font-size: 12px;
                color: #646970;
            }
            
            .db-tool-card.primary small {
                color: rgba(255,255,255,0.9);
            }
            
            #db-optimization-result {
                margin-top: 20px;
                border-radius: 4px;
            }
            
            /* Form Styling */
            .optimize-form {
                max-width: 100%;
            }
            
            .optimize-form .submit {
                padding: 0;
                margin-top: 30px;
            }
            
            /* Responsive */
            @media (max-width: 782px) {
                .bloat-removal-grid,
                .integration-grid,
                .db-tools-grid {
                    grid-template-columns: 1fr;
                }
                
                .tab-pane {
                    padding: 20px 15px;
                }
            }
        </style>';
    }
}
