<?php

namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service to hide/change WordPress login URL
 * Provides security by obscuring the default wp-login.php endpoint
 */
class HideLoginService implements ServiceInterface
{
    const OPTION_NAME = 'optimize_speed_settings';

    private $custom_slug = '';
    private $wp_login_php = false;

    public function register()
    {
        $options = get_option(self::OPTION_NAME, []);
        $this->custom_slug = isset($options['custom_login_slug']) ? sanitize_title($options['custom_login_slug']) : '';

        if (empty($this->custom_slug)) {
            return; // Feature disabled if no custom slug set
        }

        // Very early hooks for login redirection
        add_action('plugins_loaded', [$this, 'plugins_loaded'], 1);
        add_action('wp_loaded', [$this, 'wp_loaded']);
        add_action('init', [$this, 'handle_custom_login']);

        // Filter login/logout URLs
        add_filter('login_url', [$this, 'filter_login_url'], 10, 3);
        add_filter('logout_url', [$this, 'filter_logout_url'], 10, 2);
        add_filter('lostpassword_url', [$this, 'filter_lostpassword_url'], 10, 2);
        add_filter('register_url', [$this, 'filter_register_url']);

        // Site URL filter for login form
        add_filter('site_url', [$this, 'filter_site_url'], 10, 4);

        // Redirect after login/logout
        add_filter('login_redirect', [$this, 'filter_login_redirect'], 10, 3);
        add_filter('logout_redirect', [$this, 'filter_logout_redirect'], 10, 3);
    }

    public function boot()
    {
    }

    /**
     * Check if accessing wp-login.php directly
     */
    public function plugins_loaded()
    {
        global $pagenow;

        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (
            strpos(rawurldecode($request), 'wp-login.php') !== false ||
            (isset($pagenow) && $pagenow === 'wp-login.php')
        ) {
            $this->wp_login_php = true;
        }
    }

    /**
     * Block direct access to wp-login.php and unprotected wp-admin
     */
    public function wp_loaded()
    {
        global $pagenow;

        $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $request = trim($request, '/');

        // If accessing custom slug, don't block
        if ($request === $this->custom_slug) {
            return;
        }

        // Block direct wp-login.php access
        if ($this->wp_login_php) {
            // Allow logout action
            if (isset($_GET['action']) && $_GET['action'] === 'logout') {
                // Check for valid logout nonce
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'log-out')) {
                    return;
                }
            }

            // Allow POST requests with valid referer (for actual login submissions)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->is_valid_login_referer()) {
                return;
            }

            $this->redirect_404();
        }

        // Block wp-admin for non-logged-in users (redirect to 404)
        if (
            is_admin() &&
            !is_user_logged_in() &&
            !defined('DOING_AJAX') &&
            !defined('DOING_CRON') &&
            $pagenow !== 'admin-post.php'
        ) {
            $this->redirect_404();
        }
    }

    /**
     * Handle custom login slug access
     */
    public function handle_custom_login()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $request = trim($request, '/');

        // Check if accessing custom login slug
        if ($request === $this->custom_slug) {
            // Set flag to allow login
            $this->wp_login_php = false;

            // Load wp-login.php
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /**
     * Check if referer is from our custom login page
     */
    private function is_valid_login_referer()
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
        $referer = trim($referer, '/');

        return $referer === $this->custom_slug;
    }

    /**
     * Redirect to 404 page
     */
    private function redirect_404()
    {
        global $wp_query;

        status_header(404);

        if ($wp_query) {
            $wp_query->set_404();
        }

        // Try to load theme's 404 template
        $template = get_404_template();
        if ($template) {
            include $template;
            exit;
        }

        // Fallback to home redirect
        wp_safe_redirect(home_url(), 302);
        exit;
    }

    /**
     * Get the custom login URL
     */
    private function get_custom_login_url($scheme = null)
    {
        return home_url($this->custom_slug, $scheme);
    }

    /**
     * Filter login URL
     */
    public function filter_login_url($login_url, $redirect = '', $force_reauth = false)
    {
        $login_url = $this->get_custom_login_url();

        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }

        if ($force_reauth) {
            $login_url = add_query_arg('reauth', '1', $login_url);
        }

        return $login_url;
    }

    /**
     * Filter logout URL
     */
    public function filter_logout_url($logout_url, $redirect = '')
    {
        $logout_url = add_query_arg([
            'action' => 'logout',
        ], $this->get_custom_login_url());

        // Add nonce
        $logout_url = wp_nonce_url($logout_url, 'log-out');

        if (!empty($redirect)) {
            $logout_url = add_query_arg('redirect_to', urlencode($redirect), $logout_url);
        }

        return $logout_url;
    }

    /**
     * Filter lost password URL
     */
    public function filter_lostpassword_url($url, $redirect = '')
    {
        $url = add_query_arg('action', 'lostpassword', $this->get_custom_login_url());

        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }

        return $url;
    }

    /**
     * Filter register URL
     */
    public function filter_register_url($url)
    {
        return add_query_arg('action', 'register', $this->get_custom_login_url());
    }

    /**
     * Filter site_url for login form action
     */
    public function filter_site_url($url, $path, $scheme, $blog_id)
    {
        if (strpos($path, 'wp-login.php') !== false) {
            $url = str_replace('wp-login.php', $this->custom_slug, $url);
        }

        return $url;
    }

    /**
     * Filter login redirect
     */
    public function filter_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        return $redirect_to;
    }

    /**
     * Filter logout redirect
     */
    public function filter_logout_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if (empty($redirect_to)) {
            return home_url();
        }
        return $redirect_to;
    }
}
