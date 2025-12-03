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
    }

    public function boot()
    {
        // No late initialization needed
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

        // Partytown Section
        add_settings_section('partytown_section', 'Partytown Settings', null, 'optimize-speed');

        $partytown_fields = [
            ['enable_partytown', 'Enable Partytown', 'checkbox'],
            ['ga4_id', 'Google Analytics 4 ID (G-XXXXXXXXXX)', 'text'],
            ['fb_pixel_id', 'Facebook Pixel ID', 'text'],
            ['tiktok_pixel_id', 'TikTok Pixel ID', 'text'],
            ['linkedin_partner_id', 'LinkedIn Partner ID', 'text'],
            ['pinterest_tag_id', 'Pinterest Tag ID', 'text'],
            ['matomo_url', 'Matomo URL', 'url'],
            ['matomo_site_id', 'Matomo Site ID', 'number'],
            ['clarity_id', 'Microsoft Clarity ID', 'text'],
            ['hotjar_id', 'Hotjar Site ID', 'number'],
        ];

        foreach ($partytown_fields as $field) {
            add_settings_field(
                $field[0],
                $field[1],
                [$this, 'render_field'],
                'optimize-speed',
                'partytown_section',
                ['id' => $field[0], 'type' => $field[2]]
            );
        }
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

        // Include the template
        include plugin_dir_path(dirname(__DIR__, 2)) . 'templates/admin-page.php';
    }

    public function admin_assets($hook)
    {
        if ($hook !== 'settings_page_optimize-speed') {
            return;
        }
        echo '<style>
            .opti-status{padding:12px 16px;border-left:4px solid;margin:20px 0;font-size:14px;background:#fff;border-radius:4px}
            .opti-ok{border-color:#00a32a;background:#f0fff4}
            .opti-warn{border-color:#ffb900;background:#fff8e5}
            .opti-error{border-color:#d63638;background:#ffebee}
            .card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        </style>';
    }
}
