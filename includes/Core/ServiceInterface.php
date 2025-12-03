<?php
namespace OptimizeSpeed\Core;

if (!defined('ABSPATH')) {
    exit;
}

interface ServiceInterface {
    /**
     * Register the service.
     * Use this method to hook into WordPress actions and filters.
     */
    public function register();

    /**
     * Boot the service.
     * Use this method for any late initialization or logic that depends on other services.
     */
    public function boot();
}
