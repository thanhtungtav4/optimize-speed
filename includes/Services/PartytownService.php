<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PartytownService implements ServiceInterface
{
    private $options;

    public function register()
    {
        // Load options early to ensure they're available for all hooks
        add_action('init', [$this, 'init_options'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_partytown_core']);
        add_action('wp_head', [$this, 'print_partytown_config'], 1);
        add_action('wp_body_open', [$this, 'print_partytown_atom'], 1);
        add_action('wp_head', [$this, 'print_all_partytown_scripts'], 5);
        add_action('admin_init', [$this, 'check_and_install_assets']);
    }

    public function init_options()
    {
        $this->options = get_option(AdminService::OPTION_NAME, []);
    }

    public function boot()
    {
        // Options are now loaded via init hook for proper timing
        if (empty($this->options)) {
            $this->options = get_option(AdminService::OPTION_NAME, []);
        }
    }

    public function check_and_install_assets()
    {
        // Partytown files are now bundled in assets/partytown/ directory
        // No need to create files dynamically - they're included in the plugin

        $partytown_dir = OPTIMIZE_SPEED_DIR . 'assets/partytown';

        // Just verify files exist (for debugging purposes)
        $required_files = ['partytown.js', 'partytown-sw.js', 'partytown-atomics.js', 'partytown-media.js'];
        $all_exist = true;

        foreach ($required_files as $file) {
            if (!file_exists($partytown_dir . '/' . $file)) {
                $all_exist = false;
                error_log("Optimize Speed: Missing Partytown file: {$file}");
            }
        }

        if (!$all_exist) {
            // Show admin notice
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p><strong>Optimize Speed:</strong> Partytown assets are missing. Please reinstall the plugin.</p></div>';
            });
        }
    }

    public function enqueue_partytown_core()
    {
        // Auto-enable if any integration has an ID set
        if (!$this->has_any_integration())
            return;

        $partytown_url = OPTIMIZE_SPEED_URL . 'assets/partytown/';

        // If local file doesn't exist, fallback to CDN or handle error?
        // For now, we assume it exists if enabled (checked in admin_init)
        // But to be safe, we can check or just use the URL.

        wp_enqueue_script(
            'partytown',
            $partytown_url . 'partytown.js',
            [],
            '0.10.2',
            false
        );
    }

    public function print_partytown_config()
    {
        // Auto-enable if any integration has an ID set
        if (!$this->has_any_integration())
            return;

        $partytown_url = OPTIMIZE_SPEED_URL . 'assets/partytown/';
        $forward = $this->get_forward_events();

        echo '<script>
            window.partytown = {
                lib: "' . esc_url($partytown_url) . '",
                forward: ' . wp_json_encode($forward) . ',
                debug: false
            };
        </script>';
    }

    public function print_partytown_atom()
    {
        // Auto-enable if any integration has an ID set
        if (!$this->has_any_integration())
            return;
        echo '<script type="text/partytown">/* Partytown ready */</script>';
    }

    /**
     * Check if any integration has an ID configured
     */
    private function has_any_integration()
    {
        // Ensure options are loaded
        if (empty($this->options)) {
            $this->options = get_option(AdminService::OPTION_NAME, []);
        }

        $config = $this->get_integrations_config();
        foreach ($config as $integration) {
            foreach ($integration['keys'] as $key) {
                if (!empty($this->options[$key])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function get_forward_events()
    {
        $fwd = ['dataLayer.push'];
        $config = $this->get_integrations_config();

        foreach ($config as $integration) {
            $has_integration = false;
            foreach ($integration['keys'] as $key) {
                if (!empty($this->options[$key])) {
                    $has_integration = true;
                    break;
                }
            }

            if ($has_integration) {
                $fwd = array_merge($fwd, $integration['forward']);
            }
        }

        return apply_filters('optimize_speed_partytown_forward_events', array_unique($fwd));
    }

    public function print_all_partytown_scripts()
    {
        $config = $this->get_integrations_config();
        $scripts = [];
        $inline_inits = [];

        foreach ($config as $integration) {
            $id = '';
            foreach ($integration['keys'] as $key) {
                if (!empty($this->options[$key])) {
                    $id = $this->options[$key];
                    break;
                }
            }

            if ($id) {
                // If extra options needed (like matomo_url), pass options
                $script_data = $integration['partytown']($id, $this->options);

                if (!empty($script_data['external'])) {
                    $scripts[] = '<script type="text/partytown" src="' . esc_url($script_data['external']) . '"></script>';
                }

                if (!empty($script_data['inline'])) {
                    $inline_inits[] = $script_data['inline'];
                }
            }
        }

        if (empty($scripts) && empty($inline_inits)) {
            return;
        }

        if (!empty($scripts)) {
            echo implode(PHP_EOL, $scripts) . PHP_EOL;
        }

        if (!empty($inline_inits)) {
            echo '<script type="text/partytown">' . PHP_EOL;
            echo implode(PHP_EOL, $inline_inits) . PHP_EOL;
            echo '</script>';
        }
    }

    private function print_fallback_scripts()
    {
        $config = $this->get_integrations_config();
        $scripts = [];

        foreach ($config as $integration) {
            $id = '';
            foreach ($integration['keys'] as $key) {
                if (!empty($this->options[$key])) {
                    $id = $this->options[$key];
                    break;
                }
            }

            if ($id) {
                // If extra options needed (like matomo_url), pass options
                $fallback_scripts = $integration['fallback']($id, $this->options);
                foreach ($fallback_scripts as $script) {
                    $scripts[] = $script;
                }
            }
        }

        if (!empty($scripts)) {
            echo implode(PHP_EOL, $scripts) . PHP_EOL;
        }
    }

    private function get_integrations_config()
    {
        return [
            'gtm' => [
                'keys' => ['gtm', 'gtm_id'],
                'forward' => ['dataLayer.push'],
                'partytown' => function ($id) {
                    return [
                        'external' => "https://www.googletagmanager.com/gtm.js?id={$id}",
                        'inline' => "window.dataLayer = window.dataLayer || []; dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});"
                    ];
                },
                'fallback' => function ($id) {
                    return [
                        "<script async src='https://www.googletagmanager.com/gtm.js?id={$id}'></script>",
                        "<script>window.dataLayer=window.dataLayer||[];dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});</script>"
                    ];
                }
            ],
            'gtag' => [
                'keys' => ['gtag', 'ga4_id'],
                'forward' => ['gtag'],
                'partytown' => function ($id) {
                    return [
                        'external' => "https://www.googletagmanager.com/gtag/js?id={$id}",
                        'inline' => "window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '{$id}');"
                    ];
                },
                'fallback' => function ($id) {
                    return [
                        "<script async src='https://www.googletagmanager.com/gtag/js?id={$id}'></script>",
                        "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>"
                    ];
                }
            ],
            'fbpixel' => [
                'keys' => ['fbpixel', 'fb_pixel_id'],
                'forward' => ['fbq'],
                'partytown' => function ($id) {
                    return [
                        'external' => "https://connect.facebook.net/en_US/fbevents.js",
                        'inline' => "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js'); fbq('init', '{$id}'); fbq('track', 'PageView');"
                    ];
                },
                'fallback' => function ($id) {
                    return [
                        "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>"
                    ];
                }
            ],
            'tiktok' => [
                'keys' => ['tiktok', 'tiktok_pixel_id'],
                'forward' => ['ttq', 'ttq.load', 'ttq.track', 'ttq.page', 'ttq.identify', 'ttq.instance', 'ttq.instances', 'ttq.debug', 'ttq.on', 'ttq.off', 'ttq.once', 'ttq.ready', 'ttq.alias', 'ttq.group', 'ttq.enableCookie', 'ttq.disableCookie'],
                'partytown' => function ($id) {
                    return [
                        'external' => "https://analytics.tiktok.com/i18n/pixel/events.js?sdkid={$id}&lib=ttq",
                        'inline' => "!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};};}(window,document,'ttq'); ttq.load('{$id}'); ttq.page();"
                    ];
                },
                'fallback' => function ($id) {
                    return [
                        "<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};};}(window,document,'ttq');ttq.load('{$id}');ttq.page();</script>"
                    ];
                }
            ],
            'clarity' => [
                'keys' => ['clarity', 'clarity_id'],
                'forward' => ['clarity'],
                'partytown' => function ($id) {
                    return [
                        'external' => "https://www.clarity.ms/tag/{$id}",
                        'inline' => "(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};})(window,document,'clarity','script','{$id}');"
                    ];
                },
                'fallback' => function ($id) {
                    return [
                        "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{$id}');</script>"
                    ];
                }
            ],
            'matomo' => [
                'keys' => ['matomo', 'matomo_site_id'],
                'forward' => ['_paq.push'],
                'extra_options' => ['matomo_url'],
                'partytown' => function ($id, $opts) {
                    $url = rtrim($opts['matomo_url'] ?? '', '/');
                    if (!$url)
                        return [];
                    return [
                        'external' => null,
                        'inline' => "var _paq = window._paq = window._paq || []; _paq.push(['trackPageView']); _paq.push(['enableLinkTracking']); _paq.push(['setTrackerUrl', '{$url}/matomo.php']); _paq.push(['setSiteId', '{$id}']);"
                    ];
                },
                'fallback' => function ($id, $opts) {
                    $url = rtrim($opts['matomo_url'] ?? '', '/');
                    if (!$url)
                        return [];
                    return [
                        "<script>var _paq=window._paq=window._paq||[];_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);(function(){var u='{$url}/';_paq.push(['setTrackerUrl',u+'matomo.php']);_paq.push(['setSiteId','{$id}']);var d=document,g=d.createElement('script'),s=d.getElementsByTagName('script')[0];g.async=true;g.src=u+'matomo.js';s.parentNode.insertBefore(g,s);})();</script>"
                    ];
                }
            ]
        ];
    }
}
