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
                ['lazyload_iframes', 'Lazy Load Iframes/Videos', 'Replace YouTube/Vimeo with thumbnail facade'],
                ['local_google_fonts', 'Local Google Fonts', 'Download and serve Google Fonts locally'],
                ['preload_resources', 'Preload Resources', 'One URL per line'],

                // Security & API
                ['disable_rest_api', 'Disable REST API', 'Restrict to logged-in users'],

                // Assets & Styles
                ['remove_query_strings', 'Remove Query Strings', 'Remove ?ver= from CSS/JS'],
                ['disable_self_pingbacks', 'Disable Self Pingbacks', 'Stop pinging own posts'],
                ['disable_gravatars', 'Disable Gravatars', 'Remove Gravatar requests'],

                // Comments
                ['disable_comments', 'Disable Comments', 'Completely disable system'],

                // Gutenberg
                ['disable_global_styles', 'Disable Global Styles', 'Remove huge inline CSS & SVGs'],
                ['disable_duotone_svg', 'Disable Duotone SVG', 'Remove Gutenberg filters'],

                // Page Builders
                ['optimize_elementor', 'Smart Elementor Assets', '⚠️ Don\'t use with Elementor Headers'],

                // WooCommerce
                ['disable_wc_cart_fragments', 'Disable WC Cart Fragments', 'Stop AJAX cart refresh'],
                ['remove_wc_scripts_non_wc_pages', 'Remove WC Scripts on Non-WC Pages', 'Blog, pages, etc.'],

                // Security
                ['disable_password_strength_meter', 'Disable Password Strength Meter', 'Frontend forms'],
                // Analytics
                ['gtm', 'Google Tag Manager', 'Container ID (GTM-XXXX)'],
                ['gtag', 'Google Analytics 4', 'Measurement ID (G-XXXX)'],
                ['fbpixel', 'Facebook Pixel', 'Pixel ID'],
                ['tiktok', 'TikTok Pixel', 'Pixel ID'],
                ['clarity', 'Microsoft Clarity', 'Project ID'],
                ['matomo', 'Matomo Site ID', 'Site ID (e.g. 1)'],
                ['matomo_url', 'Matomo URL', 'Full URL (e.g. https://matomo.yoursite.com/)'],
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
            'ajaxurl' => admin_url('admin-ajax.php'),
            'siteUrl' => home_url()
        ]);

        // Enqueue admin CSS
        wp_enqueue_style(
            'optimize-speed-admin',
            plugins_url('assets/css/admin.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );
    }

    public function admin_styles()
    {
        // Styles moved to assets/css/admin.css
    }
}
