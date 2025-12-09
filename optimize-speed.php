<?php
/**
 * Plugin Name: Optimize Speed
 * Description: All-in-one speed optimization: Partytown integration and Native Image Optimization (WebP/AVIF/Lazy Load).
 * Version: 1.0.2
 * Author: Dev Team
 * Author URI: https://nttung.dev
 * Text Domain: optimize-speed
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OPTIMIZE_SPEED_VERSION', '1.0.2');
define('OPTIMIZE_SPEED_DIR', plugin_dir_path(__FILE__));
define('OPTIMIZE_SPEED_URL', plugin_dir_url(__FILE__));
define('PARTYTOWN_VERSION', '0.10.2');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'OptimizeSpeed\\';
    $base_dir = plugin_dir_path(__FILE__) . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Use statements for services
use OptimizeSpeed\Core\Plugin;
use OptimizeSpeed\Services\AdminService;
use OptimizeSpeed\Services\PartytownService;
use OptimizeSpeed\Services\ImageOptimizationService;
use OptimizeSpeed\Services\BloatRemovalService;
use OptimizeSpeed\Services\DatabaseOptimizationService;

function optimize_speed_init()
{
    $plugin = new Plugin();

    // Register Services
    $plugin->register_service(new AdminService());
    $plugin->register_service(new PartytownService());
    $plugin->register_service(new ImageOptimizationService());
    $plugin->register_service(new BloatRemovalService());
    $plugin->register_service(new DatabaseOptimizationService());

    // New Performance Services
    $plugin->register_service(new \OptimizeSpeed\Services\LazyLoadService());
    $plugin->register_service(new \OptimizeSpeed\Services\ScriptManagerService());
    $plugin->register_service(new \OptimizeSpeed\Services\ResourceHintService());
    $plugin->register_service(new \OptimizeSpeed\Services\LocalFontService());

    // Run Plugin
    $plugin->run();
}

optimize_speed_init();
