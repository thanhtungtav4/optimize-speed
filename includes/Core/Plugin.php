<?php
namespace OptimizeSpeed\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    /**
     * @var ServiceInterface[]
     */
    private $services = [];

    /**
     * Run the plugin.
     */
    public function run()
    {
        foreach ($this->services as $service) {
            $service->register();
        }

        add_action('plugins_loaded', [$this, 'boot_services']);
    }

    /**
     * Boot all services.
     */
    public function boot_services()
    {
        foreach ($this->services as $service) {
            $service->boot();
        }
    }

    /**
     * Register a service.
     *
     * @param ServiceInterface $service
     * @return self
     */
    public function register_service(ServiceInterface $service)
    {
        $this->services[] = $service;
        return $this;
    }
}
