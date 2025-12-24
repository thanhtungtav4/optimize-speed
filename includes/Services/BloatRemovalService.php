<?php

namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BloatRemovalService implements ServiceInterface
{
    private $options;

    const HEARTBEAT_INTERVAL = 60; // seconds

    public function register()
    {
        add_action('init', [$this, 'boot']);
    }

    public function boot()
    {
        $this->options = get_option('optimize_speed_settings', []);

        if (!empty($this->options['disable_emojis']))
            $this->disable_emojis();
        if (!empty($this->options['disable_embeds']))
            $this->disable_embeds();
        if (!empty($this->options['disable_xmlrpc']))
            add_filter('xmlrpc_enabled', '__return_false');

        // Heartbeat
        if (!empty($this->options['disable_heartbeat'])) {
            add_action('init', [$this, 'disable_heartbeat'], 1);
        } elseif (!empty($this->options['limit_heartbeat'])) {
            add_filter('heartbeat_settings', [$this, 'limit_heartbeat']);
        }

        if (!empty($this->options['disable_jquery_migrate']))
            add_action('wp_default_scripts', [$this, 'disable_jquery_migrate']);
        if (!empty($this->options['disable_widget_blocks']))
            remove_action('init', 'wp_widgets_init', 1); // This might be too aggressive, usually remove_theme_support('widgets-block-editor')
        if (!empty($this->options['disable_widget_blocks']))
            add_action('after_setup_theme', function () {
                remove_theme_support('widgets-block-editor');
            });

        if (!empty($this->options['remove_meta_generator']))
            remove_action('wp_head', 'wp_generator');
        if (!empty($this->options['remove_wlwmanifest']))
            remove_action('wp_head', 'wlwmanifest_link');
        if (!empty($this->options['remove_shortlink'])) {
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('template_redirect', 'wp_shortlink_header', 11);
        }

        if (!empty($this->options['remove_rss_feed_links'])) {
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
        }

        if (!empty($this->options['disable_rss_feeds']))
            $this->disable_rss_feeds();
        if (!empty($this->options['remove_rss_generator'])) {
            add_filter('the_generator', '__return_empty_string');
        }

        if (!empty($this->options['disable_post_revisions']))
            add_filter('wp_revisions_to_keep', '__return_zero');
        if (!empty($this->options['disable_app_passwords']))
            add_filter('wp_is_application_passwords_available', '__return_false');

        if (!empty($this->options['disable_rest_api'])) {
            add_filter('rest_authentication_errors', [$this, 'disable_rest_api_logic']);
        }

        if (!empty($this->options['remove_rsd_link']))
            remove_action('wp_head', 'rsd_link');
        if (!empty($this->options['remove_rest_api_link'])) {
            remove_action('wp_head', 'rest_output_link_wp_head', 10);
            remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
            remove_action('template_redirect', 'rest_output_link_header', 11, 0);
        }
        if (!empty($this->options['remove_query_strings'])) {
            add_filter('style_loader_src', [$this, 'remove_query_strings'], 10, 2);
            add_filter('script_loader_src', [$this, 'remove_query_strings'], 10, 2);
        }
        if (!empty($this->options['disable_self_pingbacks']))
            add_action('pre_ping', [$this, 'disable_self_pingbacks']);
        if (!empty($this->options['remove_dashicons']))
            add_action('wp_enqueue_scripts', [$this, 'remove_dashicons'], 100);
        if (!empty($this->options['disable_gravatars']))
            add_filter('get_avatar', '__return_false');
        if (!empty($this->options['disable_comments'])) {
            add_action('admin_init', [$this, 'disable_comments_admin']);
            add_filter('comments_open', '__return_false', 20, 2);
            add_filter('pings_open', '__return_false', 20, 2);
            add_filter('comments_array', '__return_empty_array', 10, 2);
            add_action('admin_menu', function () {
                remove_menu_page('edit-comments.php');
            });
        }

        if (!empty($this->options['disable_global_styles'])) {
            remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
            remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
        }

        if (!empty($this->options['disable_block_library'])) {
            add_action('wp_enqueue_scripts', function () {
                wp_dequeue_style('wp-block-library');
                wp_dequeue_style('wp-block-library-theme');
                wp_dequeue_style('wc-block-style'); // WooCommerce blocks
            }, 100);
        }

        if (!empty($this->options['optimize_elementor'])) {
            add_action('wp_enqueue_scripts', [$this, 'optimize_elementor_assets'], 100);
        }

        // New optimizations
        if (!empty($this->options['disable_dns_prefetch'])) {
            remove_action('wp_head', 'wp_resource_hints', 2);
        }

        if (!empty($this->options['remove_jquery'])) {
            add_action('wp_enqueue_scripts', [$this, 'remove_jquery'], 100);
        }

        if (!empty($this->options['disable_google_fonts'])) {
            add_action('wp_enqueue_scripts', [$this, 'disable_google_fonts'], 100);
        }

        if (!empty($this->options['defer_javascript'])) {
            add_action('wp_enqueue_scripts', [$this, 'defer_scripts_modern'], 999);
            add_filter('script_loader_tag', [$this, 'defer_scripts_legacy'], 10, 2);
        }

        if (!empty($this->options['disable_wc_cart_fragments'])) {
            add_action('wp_enqueue_scripts', [$this, 'disable_wc_cart_fragments'], 100);
        }

        if (!empty($this->options['remove_wc_scripts_non_wc_pages'])) {
            add_action('wp_enqueue_scripts', [$this, 'remove_wc_scripts_non_wc_pages'], 100);
        }

        if (!empty($this->options['disable_password_strength_meter'])) {
            add_action('wp_print_scripts', [$this, 'disable_password_strength_meter'], 100);
        }

        if (!empty($this->options['limit_post_revisions_number']) && is_numeric($this->options['limit_post_revisions_number'])) {
            add_filter('wp_revisions_to_keep', function () {
                return intval($this->options['limit_post_revisions_number']);
            });
        }

        if (!empty($this->options['remove_recent_comments_style'])) {
            add_action('widgets_init', function () {
                global $wp_widget_factory;
                if (isset($wp_widget_factory->widgets['WP_Widget_Recent_Comments'])) {
                    remove_action('wp_head', [$wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style']);
                }
            });
        }

        if (!empty($this->options['disable_duotone_svg'])) {
            remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
        }

        if (!empty($this->options['remove_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }

        if (!empty($this->options['disable_canonical'])) {
            remove_action('wp_head', 'rel_canonical');
        }
    }

    public function optimize_elementor_assets()
    {
        if (!class_exists('\Elementor\Plugin'))
            return;

        $post_id = get_the_ID();

        // If it's not a singular post/page, or if it IS built with Elementor, do nothing (let assets load)
        if (!is_singular() || \Elementor\Plugin::$instance->db->is_built_with_elementor($post_id)) {
            return;
        }

        // Dequeue Elementor assets
        wp_dequeue_style('elementor-frontend');
        wp_dequeue_script('elementor-frontend');
        wp_dequeue_style('elementor-icons');
        wp_dequeue_style('elementor-global');
        // Note: This might break Header/Footer if built with Elementor Pro but applied to non-Elementor pages.
        // Users should be warned.
    }

    public function remove_query_strings($src)
    {
        if (strpos($src, '?ver=') !== false)
            $src = remove_query_arg('ver', $src);
        return $src;
    }

    public function disable_self_pingbacks(&$links)
    {
        $home = get_option('home');
        foreach ($links as $l => $link)
            if (0 === strpos($link, $home))
                unset($links[$l]);
    }

    public function remove_dashicons()
    {
        if (!is_admin_bar_showing() && !is_customize_preview()) {
            wp_dequeue_style('dashicons');
        }
    }

    public function disable_comments_admin()
    {
        $post_types = get_post_types();
        foreach ($post_types as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }
    }

    public function disable_jquery_migrate($scripts)
    {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, ['jquery-migrate']);
            }
        }
    }

    public function disable_heartbeat()
    {
        wp_deregister_script('heartbeat');
    }

    public function disable_rss_feeds()
    {
        add_action('do_feed', [$this, 'disable_feed'], 1);
        add_action('do_feed_rdf', [$this, 'disable_feed'], 1);
        add_action('do_feed_rss', [$this, 'disable_feed'], 1);
        add_action('do_feed_rss2', [$this, 'disable_feed'], 1);
        add_action('do_feed_atom', [$this, 'disable_feed'], 1);
        add_action('do_feed_rss2_comments', [$this, 'disable_feed'], 1);
        add_action('do_feed_atom_comments', [$this, 'disable_feed'], 1);
    }

    public function disable_feed()
    {
        wp_die(__('No feed available, please visit the <a href="' . esc_url(home_url('/')) . '">homepage</a>!'));
    }

    public function disable_rest_api_logic($result)
    {
        if (!empty($result)) {
            return $result;
        }
        if (!is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'You are not currently logged in.', ['status' => 401]);
        }
        return $result;
    }


    private function disable_emojis()
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', function ($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, ['wpemoji']);
            }
            return [];
        });
        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ('dns-prefetch' === $relation_type) {
                $emoji_svg_url = 'https://s.w.org/images/core/emoji/';
                foreach ($urls as $key => $url) {
                    if (strpos($url, $emoji_svg_url) !== false) {
                        unset($urls[$key]);
                    }
                }
            }
            return $urls;
        }, 10, 2);
    }

    private function disable_embeds()
    {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        add_filter('embed_oembed_discover', '__return_false');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('tiny_mce_plugins', function ($plugins) {
            return array_diff($plugins, ['wpembed']);
        });
        add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (false !== strpos($rewrite, 'embed=true')) {
                    unset($rules[$rule]);
                }
            }
            return $rules;
        });
    }

    public function limit_heartbeat($settings)
    {
        $settings['interval'] = self::HEARTBEAT_INTERVAL;
        return $settings;
    }

    // ==================== NEW OPTIMIZATION METHODS ====================

    public function remove_jquery()
    {
        if (!is_admin()) {
            wp_deregister_script('jquery');
            wp_deregister_script('jquery-core');
            wp_deregister_script('jquery-migrate');
        }
    }

    public function disable_google_fonts()
    {
        // Remove Google Fonts from wp_enqueue_scripts
        global $wp_styles;
        if (!isset($wp_styles->registered)) {
            return;
        }

        foreach ($wp_styles->registered as $handle => $style) {
            if (isset($style->src) && (strpos($style->src, 'fonts.googleapis.com') !== false || strpos($style->src, 'fonts.gstatic.com') !== false)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }

    public function defer_scripts_modern()
    {
        // Use WP 6.3+ Script Strategy if available
        if (!function_exists('wp_script_add_data'))
            return;

        global $wp_scripts;
        if (empty($wp_scripts->queue))
            return;

        $exclude = ['optimize-speed', 'partytown', 'jquery', 'jquery-core', 'jquery-migrate'];

        foreach ($wp_scripts->queue as $handle) {
            // Check exclusion list
            $is_excluded = false;
            foreach ($exclude as $ex) {
                if (strpos($handle, $ex) !== false) {
                    $is_excluded = true;
                    break;
                }
            }
            if ($is_excluded)
                continue;

            // Apply defer strategy
            wp_script_add_data($handle, 'strategy', 'defer');
        }
    }

    public function defer_scripts_legacy($tag, $handle)
    {
        // If we are on WP 6.3+, usually wp_script_add_data handles it, so we might not need this.
        // But if someone manually enqueues without registration, or for compatibility:
        if (function_exists('wp_script_add_data') && version_compare(get_bloginfo('version'), '6.3', '>=')) {
            return $tag;
        }

        // Don't defer jQuery or admin scripts
        if (is_admin() || strpos($handle, 'jquery') !== false) {
            return $tag;
        }

        // Exclude scripts that shouldn't be deferred
        $exclude = ['optimize-speed', 'partytown'];
        foreach ($exclude as $excluded) {
            if (strpos($handle, $excluded) !== false) {
                return $tag;
            }
        }

        // Add defer attribute
        if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
            $tag = str_replace(' src', ' defer src', $tag);
        }

        return $tag;
    }

    public function disable_wc_cart_fragments()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        wp_dequeue_script('wc-cart-fragments');
        wp_deregister_script('wc-cart-fragments');

        // Alternative: Increase interval instead of disabling
        // add_filter('woocommerce_cart_fragment_refresh_interval', function() {
        //     return 86400; // 24 hours
        // });
    }

    public function remove_wc_scripts_non_wc_pages()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Only load on WooCommerce pages
        if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
            return;
        }

        // Dequeue WooCommerce scripts
        wp_dequeue_style('woocommerce-general');
        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
        wp_dequeue_style('woocommerce_frontend_styles');
        wp_dequeue_style('woocommerce_fancybox_styles');
        wp_dequeue_style('woocommerce_chosen_styles');
        wp_dequeue_style('woocommerce_prettyPhoto_css');

        wp_dequeue_script('wc_price_slider');
        wp_dequeue_script('wc-single-product');
        wp_dequeue_script('wc-add-to-cart');
        wp_dequeue_script('wc-checkout');
        wp_dequeue_script('wc-add-to-cart-variation');
        wp_dequeue_script('wc-single-product');
        wp_dequeue_script('wc-cart');
        wp_dequeue_script('wc-chosen');
        wp_dequeue_script('woocommerce');
        wp_dequeue_script('prettyPhoto');
        wp_dequeue_script('prettyPhoto-init');
        wp_dequeue_script('jquery-blockui');
        wp_dequeue_script('jquery-placeholder');
        wp_dequeue_script('fancybox');
        wp_dequeue_script('jqueryui');
    }

    public function disable_password_strength_meter()
    {
        if (!is_admin()) {
            wp_dequeue_script('zxcvbn-async');
            wp_deregister_script('zxcvbn-async');
            wp_dequeue_script('password-strength-meter');
            wp_deregister_script('password-strength-meter');
        }
    }
}
