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
    }

    public function boot()
    {
        $this->options = get_option(AdminService::OPTION_NAME, []);
    }

    public function enqueue_partytown_core()
    {
        if (empty($this->options['enable_partytown']))
            return;

        wp_enqueue_script(
            'partytown',
            site_url('/~partytown/partytown.js'),
            [],
            '0.10.2',
            false
        );
    }

    public function print_partytown_config()
    {
        if (empty($this->options['enable_partytown']))
            return;

        $forward = $this->get_forward_events();

        echo '<script>
            window.partytown = {
                lib: "/~partytown/",
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

        if (!empty($this->options['ga4_id']))
            $fwd[] = 'gtag';
        if (!empty($this->options['fb_pixel_id']))
            $fwd[] = 'fbq';
        if (!empty($this->options['matomo_site_id']))
            $fwd[] = '_paq.push';
        if (!empty($this->options['hotjar_id']))
            $fwd[] = 'hj';
        if (!empty($this->options['clarity_id']))
            $fwd[] = 'clarity';
        if (!empty($this->options['tiktok_pixel_id'])) {
            $fwd[] = 'ttq.load';
            $fwd[] = 'ttq.track';
            $fwd[] = 'ttq.page';
            $fwd[] = 'ttq.instance';
        }
        if (!empty($this->options['linkedin_partner_id']))
            $fwd[] = 'lintrk';
        if (!empty($this->options['pinterest_tag_id'])) {
            $fwd[] = 'pintrk.load';
            $fwd[] = 'pintrk.track';
            $fwd[] = 'pintrk.page';
        }

        return array_unique($fwd);
    }

    public function print_all_partytown_scripts()
    {
        if (empty($this->options['enable_partytown'])) {
            $this->print_fallback_scripts();
            return;
        }

        $output = '<script type="text/partytown">' . PHP_EOL;

        // GA4
        if (!empty($this->options['ga4_id'])) {
            $id = esc_js($this->options['ga4_id']);
            $output .= "window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '{$id}');" . PHP_EOL;
        }

        // Facebook Pixel
        if (!empty($this->options['fb_pixel_id'])) {
            $id = esc_js($this->options['fb_pixel_id']);
            $output .= "fbq('init', '{$id}'); fbq('track', 'PageView');" . PHP_EOL;
        }

        // TikTok Pixel
        if (!empty($this->options['tiktok_pixel_id'])) {
            $id = esc_js($this->options['tiktok_pixel_id']);
            $output .= "ttq.load('{$id}'); ttq.page();" . PHP_EOL;
        }

        // LinkedIn Insight Tag
        if (!empty($this->options['linkedin_partner_id'])) {
            $id = esc_js($this->options['linkedin_partner_id']);
            $output .= "_linkedin_partner_id = '{$id}'; window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || []; window._linkedin_data_partner_ids.push(_linkedin_partner_id);" . PHP_EOL;
        }

        // Pinterest Tag
        if (!empty($this->options['pinterest_tag_id'])) {
            $id = esc_js($this->options['pinterest_tag_id']);
            $output .= "pintrk('load', '{$id}'); pintrk('page');" . PHP_EOL;
        }

        // Matomo
        if (!empty($this->options['matomo_url']) && !empty($this->options['matomo_site_id'])) {
            $url = esc_url($this->options['matomo_url']);
            $site_id = intval($this->options['matomo_site_id']);
            $output .= "var _paq = window._paq = window._paq || []; _paq.push(['trackPageView']); _paq.push(['enableLinkTracking']); _paq.push(['setTrackerUrl', '{$url}matomo.php']); _paq.push(['setSiteId', '{$site_id}']);" . PHP_EOL;
        }

        // Microsoft Clarity
        if (!empty($this->options['clarity_id'])) {
            $id = esc_js($this->options['clarity_id']);
            $output .= "(function(c,l,a,r,i){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};var t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;var y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{$id}');" . PHP_EOL;
        }

        // Hotjar
        if (!empty($this->options['hotjar_id'])) {
            $id = intval($this->options['hotjar_id']);
            $output .= "(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:{$id},hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');" . PHP_EOL;
        }

        $output .= '</script>';

        if (strlen($output) > 100) {
            echo $output;
        }
    }

    private function print_fallback_scripts()
    {
        $scripts = [];

        if (!empty($this->options['ga4_id'])) {
            $scripts[] = "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_attr($this->options['ga4_id']) . "'></script>";
            $scripts[] = "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js($this->options['ga4_id']) . "');</script>";
        }

        if (!empty($this->options['fb_pixel_id'])) {
            $scripts[] = "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . esc_js($this->options['fb_pixel_id']) . "');fbq('track','PageView');</script>";
        }

        if (!empty($scripts)) {
            echo implode(PHP_EOL, $scripts);
        }
    }
}
