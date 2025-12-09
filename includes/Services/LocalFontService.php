<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

class LocalFontService implements ServiceInterface
{
    private $options;
    private $fonts_dir;
    private $fonts_url;

    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'localize_google_fonts'], 999);
    }

    public function boot()
    {
        $this->options = get_option('optimize_speed_settings', []);
        $upload = wp_get_upload_dir();
        $this->fonts_dir = $upload['basedir'] . '/optimize-speed-fonts';
        $this->fonts_url = $upload['baseurl'] . '/optimize-speed-fonts';
    }

    public function localize_google_fonts()
    {
        if (empty($this->options['local_google_fonts']) || is_admin()) {
            return;
        }

        global $wp_styles;

        if (!isset($wp_styles->queue))
            return;

        $google_font_handles = [];

        foreach ($wp_styles->queue as $handle) {
            $src = $wp_styles->registered[$handle]->src;
            if (strpos($src, 'fonts.googleapis.com') !== false || strpos($src, 'fonts.gstatic.com') !== false) {
                $google_font_handles[$handle] = $src;
            }
        }

        if (empty($google_font_handles))
            return;

        // Process found fonts
        foreach ($google_font_handles as $handle => $src) {
            $local_url = $this->process_font_stylesheet($handle, $src);
            if ($local_url) {
                // Dequeue original and enqueue local
                wp_dequeue_style($handle);
                wp_deregister_style($handle); // Ensure it's gone

                wp_enqueue_style($handle . '-local', $local_url, [], null); // null version to avoid cache busting query string if handled in filename
            }
        }
    }

    private function process_font_stylesheet($handle, $url)
    {
        // Create hash of URL to identify unique font request
        $hash = md5($url);
        $local_css_file = $this->fonts_dir . '/' . $hash . '.css';
        $local_css_url = $this->fonts_url . '/' . $hash . '.css';

        // Ensure directory exists
        if (!file_exists($this->fonts_dir)) {
            wp_mkdir_p($this->fonts_dir);
        }

        // If cached exists and is not empty, return it
        if (file_exists($local_css_file) && filesize($local_css_file) > 0) {
            return $local_css_url;
        }

        // Fetch CSS from Google
        // We need to set User Agent to get woff2 format (modern browsers)
        $response = wp_remote_get($url, [
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36'
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $css = wp_remote_retrieve_body($response);
        if (empty($css))
            return false;

        // Parse and download font files
        $css = preg_replace_callback('/url\((.*?)\)/', function ($matches) {
            $font_url = trim($matches[1], '\'"');
            return 'url(' . $this->download_font_file($font_url) . ')';
        }, $css);

        // Save local CSS
        file_put_contents($local_css_file, $css);

        return $local_css_url;
    }

    private function download_font_file($url)
    {
        // Basic validation
        if (strpos($url, 'fonts.gstatic.com') === false) {
            return $url;
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));
        $local_file = $this->fonts_dir . '/' . $filename;
        $local_url = $this->fonts_url . '/' . $filename;

        if (!file_exists($local_file)) {
            $content = file_get_contents($url); // Simpler than wp_remote_get for binary files often
            if ($content) {
                file_put_contents($local_file, $content);
            }
        }

        return $local_url;
    }
}
