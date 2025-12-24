<?php

namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Hardening Service
 * Provides various WordPress security enhancements
 */
class SecurityHardeningService implements ServiceInterface
{
    const OPTION_NAME = 'optimize_speed_settings';
    const LOGIN_ATTEMPTS_OPTION = 'os_login_attempts';
    const LOCKOUT_DURATION = 900; // 15 minutes
    const MAX_ATTEMPTS = 5;

    private $options = [];

    public function register()
    {
        // Disable File Editing must be done early
        $options = get_option(self::OPTION_NAME, []);
        if (!empty($options['disable_file_editing'])) {
            $this->disable_file_editing();
        }

        add_action('init', [$this, 'init']);
    }

    public function boot()
    {
        $this->options = get_option(self::OPTION_NAME, []);
    }

    public function init()
    {
        $this->options = get_option(self::OPTION_NAME, []);

        // Security Headers
        if (!empty($this->options['security_headers'])) {
            add_action('send_headers', [$this, 'add_security_headers']);
        }

        // Limit Login Attempts
        if (!empty($this->options['limit_login_attempts'])) {
            add_filter('authenticate', [$this, 'check_login_attempts'], 30, 3);
            add_action('wp_login_failed', [$this, 'record_failed_login']);
            add_action('wp_login', [$this, 'clear_login_attempts'], 10, 2);
        }

        // Note: Disable File Editing is handled in register() for proper timing

        // Block PHP in Uploads
        if (!empty($this->options['block_php_uploads'])) {
            add_action('admin_init', [$this, 'create_htaccess_uploads']);
        }

        // Disable XML-RPC
        if (!empty($this->options['disable_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', [$this, 'remove_x_pingback']);
        }

        // Hide WP Version (additional places)
        if (!empty($this->options['hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }
    }

    /**
     * Add Security Headers
     */
    public function add_security_headers()
    {
        if (is_admin()) {
            return;
        }

        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');

        // X-Frame-Options - Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (Feature Policy)
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

        // Optional: Strict Transport Security (HSTS)
        if (!empty($this->options['enable_hsts']) && is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Check login attempts before authentication
     */
    public function check_login_attempts($user, $username, $password)
    {
        if (empty($username)) {
            return $user;
        }

        $ip = $this->get_client_ip();
        $attempts = $this->get_login_attempts($ip);

        if ($attempts['count'] >= self::MAX_ATTEMPTS) {
            $time_remaining = $attempts['lockout_until'] - time();

            if ($time_remaining > 0) {
                $minutes = ceil($time_remaining / 60);
                return new \WP_Error(
                    'too_many_attempts',
                    sprintf(
                        __('Too many failed login attempts. Please try again in %d minutes.', 'optimize-speed'),
                        $minutes
                    )
                );
            } else {
                // Lockout expired, reset
                $this->clear_login_attempts_by_ip($ip);
            }
        }

        return $user;
    }

    /**
     * Record failed login attempt
     */
    public function record_failed_login($username)
    {
        $ip = $this->get_client_ip();
        $attempts = $this->get_login_attempts($ip);

        $attempts['count']++;
        $attempts['last_attempt'] = time();

        if ($attempts['count'] >= self::MAX_ATTEMPTS) {
            $attempts['lockout_until'] = time() + self::LOCKOUT_DURATION;
        }

        $this->save_login_attempts($ip, $attempts);

        // Log failed attempt
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[Optimize Speed Security] Failed login attempt for user "%s" from IP %s (attempt %d/%d)',
                $username,
                $ip,
                $attempts['count'],
                self::MAX_ATTEMPTS
            ));
        }
    }

    /**
     * Clear login attempts on successful login
     */
    public function clear_login_attempts($user_login, $user)
    {
        $ip = $this->get_client_ip();
        $this->clear_login_attempts_by_ip($ip);
    }

    /**
     * Get login attempts for IP
     */
    private function get_login_attempts($ip)
    {
        $all_attempts = get_option(self::LOGIN_ATTEMPTS_OPTION, []);

        if (isset($all_attempts[$ip])) {
            return $all_attempts[$ip];
        }

        return [
            'count' => 0,
            'last_attempt' => 0,
            'lockout_until' => 0
        ];
    }

    /**
     * Save login attempts for IP
     */
    private function save_login_attempts($ip, $attempts)
    {
        $all_attempts = get_option(self::LOGIN_ATTEMPTS_OPTION, []);
        $all_attempts[$ip] = $attempts;

        // Clean up old entries (older than 24 hours)
        foreach ($all_attempts as $stored_ip => $data) {
            if ($data['last_attempt'] < (time() - 86400)) {
                unset($all_attempts[$stored_ip]);
            }
        }

        update_option(self::LOGIN_ATTEMPTS_OPTION, $all_attempts, false);
    }

    /**
     * Clear login attempts by IP
     */
    private function clear_login_attempts_by_ip($ip)
    {
        $all_attempts = get_option(self::LOGIN_ATTEMPTS_OPTION, []);

        if (isset($all_attempts[$ip])) {
            unset($all_attempts[$ip]);
            update_option(self::LOGIN_ATTEMPTS_OPTION, $all_attempts, false);
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Disable file editing in WordPress admin
     */
    private function disable_file_editing()
    {
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
    }

    /**
     * Create .htaccess in uploads to block PHP execution
     */
    public function create_htaccess_uploads()
    {
        $upload_dir = wp_upload_dir();
        $htaccess_file = $upload_dir['basedir'] . '/.htaccess';

        // Only create if doesn't exist
        if (file_exists($htaccess_file)) {
            return;
        }

        $htaccess_content = "# Block PHP execution in uploads folder
# Generated by Optimize Speed Plugin

<Files *.php>
    Order Allow,Deny
    Deny from all
</Files>

<Files *.phtml>
    Order Allow,Deny
    Deny from all
</Files>

<Files *.php3>
    Order Allow,Deny
    Deny from all
</Files>

<Files *.php4>
    Order Allow,Deny
    Deny from all
</Files>

<Files *.php5>
    Order Allow,Deny
    Deny from all
</Files>

<Files *.php7>
    Order Allow,Deny
    Deny from all
</Files>

<Files *.phps>
    Order Allow,Deny
    Deny from all
</Files>
";

        // Use WordPress filesystem
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (WP_Filesystem()) {
            $wp_filesystem->put_contents($htaccess_file, $htaccess_content, FS_CHMOD_FILE);
        }
    }

    /**
     * Remove X-Pingback header
     */
    public function remove_x_pingback($headers)
    {
        unset($headers['X-Pingback']);
        return $headers;
    }
}
