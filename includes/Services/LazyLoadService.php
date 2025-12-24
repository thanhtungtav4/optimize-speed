<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class LazyLoadService implements ServiceInterface
{
    private $options;

    public function register()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        $this->options = get_option('optimize_speed_settings', []);
        if (!empty($this->options['lazyload_iframes'])) {
            add_filter('the_content', [$this, 'lazyload_iframes'], 100);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        }
    }

    public function boot()
    {
    }

    public function enqueue_assets()
    {
        wp_enqueue_script('os-lazyload', OPTIMIZE_SPEED_URL . 'assets/js/lazyload.js', [], '1.0', true);
        wp_enqueue_style('os-lazyload', OPTIMIZE_SPEED_URL . 'assets/css/lazyload.css', [], '1.0');
    }

    public function lazyload_iframes($content)
    {
        if (is_admin() || is_feed()) {
            return $content;
        }

        // Match YouTube regex (standard and short URLs)
        $youtube_regex = '#<iframe[^>]+src=["\'](?:https?:)?//(?:www\.)?(?:youtube\.com/embed/|youtu\.be/)([\w-]+)(?:\?[^"\']*)?["\'][^>]*></iframe>#i';

        $content = preg_replace_callback($youtube_regex, function ($matches) {
            $video_id = $matches[1];
            $original_iframe = $matches[0];

            // Get original src
            preg_match('/src=["\'](.*?)["\']/', $original_iframe, $src_match);
            $src = isset($src_match[1]) ? $src_match[1] : '';

            // Add autoplay to src when clicked
            if (strpos($src, '?') !== false) {
                $src .= '&autoplay=1';
            } else {
                $src .= '?autoplay=1';
            }

            // Thumbnail URL
            $thumb = "https://i.ytimg.com/vi/{$video_id}/sddefault.jpg";

            // Facade HTML
            $html = '<div class="os-lazy-iframe" data-src="' . esc_url($src) . '" style="background-image: url(' . esc_url($thumb) . ');">';
            $html .= '<div class="os-play-btn"></div>';
            $html .= '</div>';

            return $html;
        }, $content);

        // Match Vimeo regex
        $vimeo_regex = '#<iframe[^>]+src=["\'](?:https?:)?//player\.vimeo\.com/video/(\d+)(?:\?[^"\']*)?["\'][^>]*></iframe>#i';

        $content = preg_replace_callback($vimeo_regex, function ($matches) {
            $video_id = $matches[1];
            $original_iframe = $matches[0];

            // Get original src
            preg_match('/src=["\'](.*?)["\']/', $original_iframe, $src_match);
            $src = isset($src_match[1]) ? $src_match[1] : '';

            if (strpos($src, '?') !== false) {
                $src .= '&autoplay=1';
            } else {
                $src .= '?autoplay=1';
            }

            // Vimeo thumbnail requires API or heuristic. For simplicity we use a placeholder or generic vimeo color if we can't fetch.
            // But we can try to fetch data-thumbnail if available, or just leave it blank/black.
            // A better approach without API call on render: use a generic play button over black/gray bg.
            // Or use an endpoint if possible. For now, we will use a muted background.

            $html = '<div class="os-lazy-iframe" data-src="' . esc_url($src) . '" style="background-color: #000;">';
            $html .= '<div class="os-play-btn"></div>';
            $html .= '</div>';

            // Note: Vimeo thumbnails are harder to get statically without an API call. 
            // We could use JS to fetch it on client side, but that adds complexity.
            // For now, static black background is acceptable and better than loading iframe.

            return $html;
        }, $content);

        return $content;
    }
}
