<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ResourceHintService implements ServiceInterface
{
    private $options;

    public function register()
    {
        add_action('wp_head', [$this, 'add_resource_hints'], 0);
    }

    public function boot()
    {
        $this->options = get_option('optimize_speed_settings', []);
    }

    public function add_resource_hints()
    {
        if (empty($this->options['preload_resources'])) {
            return;
        }

        $urls = array_filter(array_map('trim', explode("\n", $this->options['preload_resources'])));

        foreach ($urls as $url) {
            if (empty($url))
                continue;

            $as = 'image'; // default
            if (preg_match('/\.css$/', $url))
                $as = 'style';
            if (preg_match('/\.js$/', $url))
                $as = 'script';
            if (preg_match('/\.(woff2?|ttf|otf|eot)$/', $url))
                $as = 'font';

            $crossorigin = ($as === 'font') ? 'crossorigin' : '';

            echo '<link rel="preload" href="' . esc_url($url) . '" as="' . esc_attr($as) . '" ' . $crossorigin . '>' . "\n";
        }
    }
}
