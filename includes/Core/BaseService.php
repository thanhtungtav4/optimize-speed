<?php

namespace OptimizeSpeed\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base service with common functionality
 * All services should extend this class
 */
abstract class BaseService implements ServiceInterface
{
    /**
     * Option name for plugin settings
     */
    const OPTION_NAME = 'optimize_speed_settings';

    /**
     * Cached options to avoid multiple database queries
     * @var array|null
     */
    protected static $options_cache = null;

    /**
     * Get plugin options with caching
     * 
     * @return array
     */
    protected function get_options(): array
    {
        if (self::$options_cache === null) {
            self::$options_cache = get_option(self::OPTION_NAME, []);
        }
        return self::$options_cache;
    }

    /**
     * Get a specific option value
     * 
     * @param string $key Option key
     * @param mixed $default Default value if not set
     * @return mixed
     */
    protected function get_option(string $key, $default = null)
    {
        $options = $this->get_options();
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Check if an option is enabled (truthy)
     * 
     * @param string $key Option key
     * @return bool
     */
    protected function is_enabled(string $key): bool
    {
        return !empty($this->get_option($key));
    }

    /**
     * Clear options cache (useful after saving settings)
     */
    public static function clear_options_cache(): void
    {
        self::$options_cache = null;
    }

    /**
     * Check if current request is frontend
     * 
     * @return bool
     */
    protected function is_frontend(): bool
    {
        return !is_admin() && !is_feed() && !wp_doing_ajax() && !wp_doing_cron();
    }

    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    protected function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Check if Elementor is active
     * 
     * @return bool
     */
    protected function is_elementor_active(): bool
    {
        return defined('ELEMENTOR_VERSION');
    }

    /**
     * Safe file write using WordPress filesystem
     * 
     * @param string $file Full path to file
     * @param string $content Content to write
     * @return bool
     */
    protected function write_file(string $file, string $content): bool
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!WP_Filesystem()) {
            return false;
        }

        $dir = dirname($file);
        if (!$wp_filesystem->is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $wp_filesystem->put_contents($file, $content, FS_CHMOD_FILE);
    }

    /**
     * Safe file read using WordPress filesystem
     * 
     * @param string $file Full path to file
     * @return string|false
     */
    protected function read_file(string $file)
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!WP_Filesystem()) {
            return false;
        }

        if (!$wp_filesystem->exists($file)) {
            return false;
        }

        return $wp_filesystem->get_contents($file);
    }
}
