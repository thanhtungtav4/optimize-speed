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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_partytown_core']);
        add_action('wp_head', [$this, 'print_partytown_config'], 1);
        add_action('wp_body_open', [$this, 'print_partytown_atom'], 1);
        add_action('wp_head', [$this, 'print_all_partytown_scripts'], 5);
        add_action('admin_init', [$this, 'check_and_install_assets']);
    }

    public function boot()
    {
        $this->options = get_option(AdminService::OPTION_NAME, []);
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
        if (empty($this->options['enable_partytown']))
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
        if (empty($this->options['enable_partytown']))
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
        if (empty($this->options['enable_partytown']))
            return;
        echo '<script type="text/partytown">/* Partytown ready */</script>';
    }

    private function get_forward_events()
    {
        $fwd = ['dataLayer.push'];

        // Support both old field names (with _id) and new field names (without _id)
        if (!empty($this->options['gtm']) || !empty($this->options['gtm_id']))
            $fwd[] = 'dataLayer.push';
        if (!empty($this->options['gtag']) || !empty($this->options['ga4_id']))
            $fwd[] = 'gtag';
        if (!empty($this->options['fbpixel']) || !empty($this->options['fb_pixel_id']))
            $fwd[] = 'fbq';
        if (!empty($this->options['matomo']) || !empty($this->options['matomo_site_id']))
            $fwd[] = '_paq.push';
        if (!empty($this->options['clarity']) || !empty($this->options['clarity_id']))
            $fwd[] = 'clarity';
        if (!empty($this->options['tiktok']) || !empty($this->options['tiktok_pixel_id'])) {
            $fwd[] = 'ttq.load';
            $fwd[] = 'ttq.track';
            $fwd[] = 'ttq.page';
            $fwd[] = 'ttq.instance';
        }

        return apply_filters('optimize_speed_partytown_forward_events', array_unique($fwd));
    }

    public function print_all_partytown_scripts()
    {
        // Check if any tracking ID is configured
        $has_tracking = !empty($this->options['gtm']) || !empty($this->options['gtag']) ||
            !empty($this->options['fbpixel']) || !empty($this->options['tiktok']) ||
            !empty($this->options['clarity']) || !empty($this->options['matomo']);

        if (!$has_tracking) {
            return; // No tracking configured
        }

        // External scripts that need to be loaded (with type="text/partytown")
        $external_scripts = [];

        // Google Tag Manager
        $gtm_id = $this->options['gtm'] ?? $this->options['gtm_id'] ?? '';
        if (!empty($gtm_id)) {
            $gtm_id = esc_js($gtm_id);
            $external_scripts[] = "<script type=\"text/partytown\" src=\"https://www.googletagmanager.com/gtm.js?id={$gtm_id}\"></script>";
        }

        // Google Analytics 4 (gtag.js)
        $ga4_id = $this->options['gtag'] ?? $this->options['ga4_id'] ?? '';
        if (!empty($ga4_id)) {
            $ga4_id = esc_js($ga4_id);
            $external_scripts[] = "<script type=\"text/partytown\" src=\"https://www.googletagmanager.com/gtag/js?id={$ga4_id}\"></script>";
        }

        // Facebook Pixel
        $fb_id = $this->options['fbpixel'] ?? $this->options['fb_pixel_id'] ?? '';
        if (!empty($fb_id)) {
            $external_scripts[] = "<script type=\"text/partytown\" src=\"https://connect.facebook.net/en_US/fbevents.js\"></script>";
        }

        // TikTok Pixel
        $tiktok_id = $this->options['tiktok'] ?? $this->options['tiktok_pixel_id'] ?? '';
        if (!empty($tiktok_id)) {
            $external_scripts[] = "<script type=\"text/partytown\" src=\"https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=C6RGGFS2I7N4QUB43OL0&lib={$tiktok_id}\"></script>";
        }

        // Microsoft Clarity
        $clarity_id = $this->options['clarity'] ?? $this->options['clarity_id'] ?? '';
        if (!empty($clarity_id)) {
            $clarity_id = esc_js($clarity_id);
            $external_scripts[] = "<script type=\"text/partytown\" src=\"https://www.clarity.ms/tag/{$clarity_id}\"></script>";
        }

        // Output external scripts
        if (!empty($external_scripts)) {
            echo implode(PHP_EOL, $external_scripts) . PHP_EOL;
        }

        // Inline initialization scripts
        $output = '<script type="text/partytown">' . PHP_EOL;

        // GTM DataLayer
        if (!empty($gtm_id)) {
            $output .= "window.dataLayer = window.dataLayer || []; dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});" . PHP_EOL;
        }

        // GA4 init
        if (!empty($ga4_id)) {
            $output .= "window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '{$ga4_id}');" . PHP_EOL;
        }

        // Facebook Pixel init
        if (!empty($fb_id)) {
            $fb_id = esc_js($fb_id);
            $output .= "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js'); fbq('init', '{$fb_id}'); fbq('track', 'PageView');" . PHP_EOL;
        }

        // TikTok Pixel init
        if (!empty($tiktok_id)) {
            $tiktok_id = esc_js($tiktok_id);
            $output .= "!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};};}(window,document,'ttq'); ttq.load('{$tiktok_id}'); ttq.page();" . PHP_EOL;
        }

        // Matomo
        $matomo_id = $this->options['matomo'] ?? $this->options['matomo_site_id'] ?? '';
        $matomo_url = $this->options['matomo_url'] ?? '';
        if (!empty($matomo_id) && !empty($matomo_url)) {
            $matomo_id = intval($matomo_id);
            $matomo_url = esc_url_raw(rtrim($matomo_url, '/'));
            $output .= "var _paq = window._paq = window._paq || []; _paq.push(['trackPageView']); _paq.push(['enableLinkTracking']); _paq.push(['setTrackerUrl', '{$matomo_url}/matomo.php']); _paq.push(['setSiteId', '{$matomo_id}']);" . PHP_EOL;
        }

        // Microsoft Clarity init
        if (!empty($clarity_id)) {
            $output .= "(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};})(window,document,'clarity','script','{$clarity_id}');" . PHP_EOL;
        }

        $output .= '</script>';

        // Only output if we have actual content
        if (strlen($output) > 100) {
            echo $output . PHP_EOL;
        }
    }

    private function print_fallback_scripts()
    {
        $scripts = [];

        // Google Tag Manager
        $gtm_id = $this->options['gtm'] ?? $this->options['gtm_id'] ?? '';
        if (!empty($gtm_id)) {
            $gtm_id = esc_attr($gtm_id);
            $scripts[] = "<script async src='https://www.googletagmanager.com/gtm.js?id={$gtm_id}'></script>";
            $scripts[] = "<script>window.dataLayer=window.dataLayer||[];dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});</script>";
        }

        // Google Analytics 4
        $ga4_id = $this->options['gtag'] ?? $this->options['ga4_id'] ?? '';
        if (!empty($ga4_id)) {
            $ga4_id_attr = esc_attr($ga4_id);
            $ga4_id_js = esc_js($ga4_id);
            $scripts[] = "<script async src='https://www.googletagmanager.com/gtag/js?id={$ga4_id_attr}'></script>";
            $scripts[] = "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$ga4_id_js}');</script>";
        }

        // Facebook Pixel
        $fb_id = $this->options['fbpixel'] ?? $this->options['fb_pixel_id'] ?? '';
        if (!empty($fb_id)) {
            $fb_id = esc_js($fb_id);
            $scripts[] = "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$fb_id}');fbq('track','PageView');</script>";
        }

        // TikTok Pixel
        $tiktok_id = $this->options['tiktok'] ?? $this->options['tiktok_pixel_id'] ?? '';
        if (!empty($tiktok_id)) {
            $tiktok_id = esc_js($tiktok_id);
            $scripts[] = "<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};};}(window,document,'ttq');ttq.load('{$tiktok_id}');ttq.page();</script>";
        }

        // Microsoft Clarity
        $clarity_id = $this->options['clarity'] ?? $this->options['clarity_id'] ?? '';
        if (!empty($clarity_id)) {
            $clarity_id = esc_js($clarity_id);
            $scripts[] = "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{$clarity_id}');</script>";
        }

        // Matomo
        $matomo_id = $this->options['matomo'] ?? $this->options['matomo_site_id'] ?? '';
        $matomo_url = $this->options['matomo_url'] ?? '';
        if (!empty($matomo_id) && !empty($matomo_url)) {
            $matomo_id = intval($matomo_id);
            $matomo_url = esc_url(rtrim($matomo_url, '/'));
            $scripts[] = "<script>var _paq=window._paq=window._paq||[];_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);(function(){var u='{$matomo_url}/';_paq.push(['setTrackerUrl',u+'matomo.php']);_paq.push(['setSiteId','{$matomo_id}']);var d=document,g=d.createElement('script'),s=d.getElementsByTagName('script')[0];g.async=true;g.src=u+'matomo.js';s.parentNode.insertBefore(g,s);})();</script>";
        }

        if (!empty($scripts)) {
            echo implode(PHP_EOL, $scripts) . PHP_EOL;
        }
    }
}
