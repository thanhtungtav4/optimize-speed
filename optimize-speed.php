<?php
/**
 * Plugin Name: Optimize Speed
 * Description: All-in-one speed optimization: Partytown integration and Native Image Optimization (WebP/AVIF/Lazy Load).
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: optimize-speed
 */

if (!defined('ABSPATH')) {
    exit;
}

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

use OptimizeSpeed\Core\Plugin;
use OptimizeSpeed\Services\AdminService;
use OptimizeSpeed\Services\PartytownService;
use OptimizeSpeed\Services\ImageOptimizationService;

function optimize_speed_init()
{
    $plugin = new Plugin();

    // Register Services
    $plugin->register_service(new AdminService());
    $plugin->register_service(new PartytownService());
    $plugin->register_service(new ImageOptimizationService());

    // Run Plugin
    $plugin->run();
}

optimize_speed_init();
