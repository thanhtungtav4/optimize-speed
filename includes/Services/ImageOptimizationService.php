<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;
use Imagick;
use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

if (!defined('ABSPATH')) {
    exit;
}

class ImageOptimizationService implements ServiceInterface
{
    private static $best_size_cache = [];
    private static $url_to_id_cache = [];
    private static $generated_files = [];
    private static $is_first_image = true;
    private $weak_server_mode = false;

    public function register()
    {
        add_filter('wp_generate_attachment_metadata', [$this, 'generate_optimized'], 10, 2);
        add_filter('wp_get_attachment_image', [$this, 'render_picture'], 10, 4);
        add_filter('the_content', [$this, 'process_content'], 99);
        add_filter('wp_content_img_tag', [$this, 'replace_single_img'], 10, 3);
        add_filter('render_block', [$this, 'process_block_content'], 10, 2);
        add_action('delete_attachment', [$this, 'cleanup']);

        // AJAX
        add_action('wp_ajax_modern_opti_queue', [$this, 'ajax_get_queue']);
        add_action('wp_ajax_modern_opti_regen', [$this, 'ajax_regenerate_single']);
        add_action('wp_ajax_modern_opti_cleanup_avif', [$this, 'ajax_cleanup_bad_avif']);

        // Reset first image flag on loop start
        add_action('loop_start', function () {
            self::$is_first_image = true;
        });
    }

    public function boot()
    {
        // Late initialization if needed
    }

    // ====================== GENERATE OPTIMIZED FILES ======================
    public function generate_optimized($metadata, $id)
    {
        if (!wp_attachment_is_image($id))
            return $metadata;
        $file = get_attached_file($id);
        if (!$file || !file_exists($file))
            return $metadata;

        $dir = dirname($file);
        $files = [$file];
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $s) {
                $f = $dir . '/' . $s['file'];
                if (file_exists($f))
                    $files[] = $f;
            }
        }

        foreach ($files as $f) {
            if (!is_readable($f) || filesize($f) < 5000)
                continue;
            $this->create_webp($f);
            $this->create_avif($f);
        }
        return $metadata;
    }

    private function create_webp($file)
    {
        if (!is_readable($file) || filesize($file) < 5000 || !preg_match('/\.(jpe?g|png)$/i', $file))
            return;
        $out = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);

        if (file_exists($out)) {
            if (filesize($out) > 300)
                return;
            @unlink($out);
        }

        if (class_exists('Imagick')) {
            try {
                $img = new Imagick($file);
                $img->setImageFormat('webp');
                $img->setOption('webp:quality', '75');
                $img->writeImage($out);
                $img->clear();
                $img->destroy();
            } catch (Exception $e) { /* silent */
            }
        } elseif (function_exists('imagewebp')) {
            $gd = @imagecreatefromstring(@file_get_contents($file));
            if ($gd) {
                if (function_exists('imagepalettetotruecolor'))
                    @imagepalettetotruecolor($gd);
                @imagealphablending($gd, true);
                @imagesavealpha($gd, true);
                @imagewebp($gd, $out, 82);
                @imagedestroy($gd);
            }
        }
    }

    private function create_avif($file)
    {
        if (!is_readable($file) || filesize($file) < 5000 || !preg_match('/\.(jpe?g|png)$/i', $file))
            return;
        if (!$this->can_create_avif())
            return;

        $out = preg_replace('/\.(jpe?g|png)$/i', '.avif', $file);

        if (file_exists($out)) {
            if (filesize($out) > 200)
                return;
            @unlink($out);
        }

        try {
            $img = new Imagick($file);
            $img->setImageFormat('avif');
            $img->setOption('avif:lossless', 'false');
            $img->setImageCompressionQuality(40);
            $img->setOption('avif:speed', '8');

            if (method_exists($img, 'stripImage')) {
                $img->stripImage();
            } elseif (method_exists($img, 'strip')) {
                $img->strip();
            }

            $img->writeImage($out);
            $img->clear();
            $img->destroy();

            clearstatcache(true, $out);
            if (file_exists($out) && filesize($out) < 100) {
                @unlink($out);
            }
        } catch (Exception $e) {
            if (file_exists($out)) {
                @unlink($out);
            }
        }
    }

    private function can_create_avif()
    {
        if (!class_exists('Imagick'))
            return false;
        $formats = Imagick::queryFormats();
        if (!in_array('AVIF', $formats) || !in_array('HEIF', $formats))
            return false;

        $cached = get_transient('mio_avif_test');
        if ($cached !== false)
            return $cached === 'yes';

        $tmp = wp_tempnam('test.jpg');
        if (!$tmp)
            return false;

        $avif_file = $tmp . '.avif';
        $ok = false;
        try {
            $img = imagecreatetruecolor(10, 10);
            imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
            imagejpeg($img, $tmp, 80);
            imagedestroy($img);

            $im = new Imagick($tmp);
            $im->setImageFormat('avif');
            $im->writeImage($avif_file);
            if (file_exists($avif_file) && filesize($avif_file) > 100)
                $ok = true;
            $im->clear();
            $im->destroy();
        } catch (Exception $e) {
            $ok = false;
        }

        @unlink($tmp);
        @unlink($avif_file);
        set_transient('mio_avif_test', $ok ? 'yes' : 'no', WEEK_IN_SECONDS);
        return $ok;
    }

    // ====================== RENDER PICTURE ======================
    private function build_picture($id, $size)
    {
        $src = wp_get_attachment_image_src($id, $size);
        if (!$src)
            return false;
        list($url, $w, $h) = $src;
        if (strpos($url, '.svg') !== false)
            return false;

        $upload = wp_get_upload_dir();
        $file_path = str_replace($upload['baseurl'], $upload['basedir'], $url);
        if (!file_exists($file_path))
            $file_path = get_attached_file($id);
        if (!$file_path || !file_exists($file_path))
            return false;

        $original_file = get_attached_file($id);
        if (!$original_file || !file_exists($original_file))
            return false;

        $original_dir = dirname($original_file);
        $original_name = pathinfo($original_file, PATHINFO_FILENAME);

        $dir = dirname($file_path);
        $name = pathinfo($file_path, PATHINFO_FILENAME);
        $webp = $dir . '/' . $name . '.webp';
        $avif = $dir . '/' . $name . '.avif';

        $alt = get_post_meta($id, '_wp_attachment_image_alt', true) ?: '';
        $srcset = wp_get_attachment_image_srcset($id, $size);
        $sizes = wp_get_attachment_image_sizes($id, $size);

        $is_lcp = self::$is_first_image;
        if ($is_lcp)
            self::$is_first_image = false;

        $loading_attr = $is_lcp ? 'eager' : 'lazy';
        $fetch_priority = $is_lcp ? 'fetchpriority="high"' : '';

        $html = '<picture class="optimize-picture">';

        // AVIF
        $avif_url = null;
        if (file_exists($avif)) {
            $avif_url = str_replace($upload['basedir'], $upload['baseurl'], $avif);
        } else {
            $original_avif = $original_dir . '/' . $original_name . '.avif';
            if (file_exists($original_avif)) {
                $avif_url = str_replace($upload['basedir'], $upload['baseurl'], $original_avif);
            } elseif (!$this->weak_server_mode && $this->can_create_avif() && !isset(self::$generated_files[$original_avif])) {
                $this->create_avif($original_file);
                self::$generated_files[$original_avif] = true;
                if (file_exists($original_avif)) {
                    $avif_url = str_replace($upload['basedir'], $upload['baseurl'], $original_avif);
                }
            }
        }
        if ($avif_url)
            $html .= '<source srcset="' . esc_url($avif_url) . '" type="image/avif">';

        // WebP
        $webp_url = null;
        if (file_exists($webp)) {
            $webp_url = str_replace($upload['basedir'], $upload['baseurl'], $webp);
        } else {
            $original_webp = $original_dir . '/' . $original_name . '.webp';
            if (file_exists($original_webp)) {
                $webp_url = str_replace($upload['basedir'], $upload['baseurl'], $original_webp);
            } elseif (!$this->weak_server_mode && !isset(self::$generated_files[$original_webp])) {
                $this->create_webp($original_file);
                self::$generated_files[$original_webp] = true;
                if (file_exists($original_webp)) {
                    $webp_url = str_replace($upload['basedir'], $upload['baseurl'], $original_webp);
                }
            }
        }
        if ($webp_url)
            $html .= '<source srcset="' . esc_url($webp_url) . '" type="image/webp">';

        $html .= '<img src="' . esc_url($url) . '" ' . ($srcset ? 'srcset="' . esc_attr($srcset) . '"' : '') . ' ' . ($sizes ? 'sizes="' . esc_attr($sizes) . '"' : '') . ' alt="' . esc_attr($alt) . '" width="' . (int) $w . '" height="' . (int) $h . '" loading="' . $loading_attr . '" decoding="async" data-opti="1" ' . $fetch_priority . '>';
        $html .= '</picture>';
        return $html;
    }

    public function render_picture($html, $id, $size, $icon)
    {
        if (is_admin() || $icon)
            return $html;
        return $this->build_picture($id, $size) ?: $html;
    }

    public function process_content($content)
    {
        if (is_admin() || empty($content))
            return $content;
        return preg_replace_callback(
            '#(<img\b[^>]*\ssrc=["\'])([^"\']*wp-content/uploads[^"\']*)(["\'][^>]*>)#i',
            function ($m) {
                return $this->replace_single_img($m[0]) ?: $m[0];
            },
            $content
        );
    }

    public function process_block_content($block_content, $block)
    {
        if (is_admin() || empty($block_content))
            return $block_content;
        if (strpos($block_content, '<img') === false)
            return $block_content;
        return preg_replace_callback(
            '#<img\b[^>]*>#i',
            function ($m) {
                return $this->replace_single_img($m[0]) ?: $m[0];
            },
            $block_content
        );
    }

    public function replace_single_img($match, $context = null, $attachment_id = null)
    {
        $img_tag = is_array($match) ? ($match[0] ?? '') : $match;
        if (empty($img_tag))
            return $img_tag;
        if (strpos($img_tag, 'optimize-picture') !== false || strpos($img_tag, 'data-optimize-picture="1"') !== false)
            return $img_tag;
        if (strpos($img_tag, '<img') === false)
            return $img_tag;

        if (!preg_match('/src=["\'](.*?)["\']/', $img_tag, $m))
            return $img_tag;
        $url = $m[1] ?? '';
        if (empty($url) || !preg_match('/\.(jpe?g|png)$/i', $url))
            return $img_tag;

        if ($attachment_id) {
            $id = $attachment_id;
        } elseif (isset(self::$url_to_id_cache[$url])) {
            $id = self::$url_to_id_cache[$url];
        } else {
            $id = attachment_url_to_postid($url);
            self::$url_to_id_cache[$url] = $id;
        }

        if (!$id)
            return $img_tag;

        preg_match('/width=["\'](\d+)["\']/', $img_tag, $w);
        preg_match('/height=["\'](\d+)["\']/', $img_tag, $h);
        preg_match('/class=["\'][^"\']*size-([a-z0-9-]+)[^"\']*/i', $img_tag, $sz);

        $size = $sz[1] ?? 'large';
        if (!empty($w[1]) && !empty($h[1])) {
            $size = $this->best_size($id, (int) $w[1], (int) $h[1]);
        }

        $picture = $this->build_picture($id, $size);
        if (!$picture)
            return $img_tag;

        if (preg_match('/class=["\'](.*?)["\']/', $img_tag, $c)) {
            $picture = preg_replace('/<img /', '<img class="' . esc_attr($c[1]) . '" ', $picture);
        }

        return $picture;
    }

    private function best_size($id, $w, $h)
    {
        $key = "$id-$w-$h";
        if (isset(self::$best_size_cache[$key]))
            return self::$best_size_cache[$key];

        $meta = wp_get_attachment_metadata($id);
        if (empty($meta['sizes']) || $w <= 0 || $h <= 0 || !is_numeric($w) || !is_numeric($h)) {
            return self::$best_size_cache[$key] = 'large';
        }

        $target_ratio = $w / $h;
        $best = 'large';
        $score = PHP_INT_MAX;
        $candidates = ['original' => ['width' => $meta['width'], 'height' => $meta['height']]];
        foreach ($meta['sizes'] as $n => $d)
            $candidates[$n] = $d;

        foreach ($candidates as $name => $d) {
            $cw = $d['width'];
            $ch = $d['height'];
            if ($cw <= 0 || $ch <= 0)
                continue;
            $ratio_diff = abs(($cw / $ch) - $target_ratio);
            $penalty = $ratio_diff > 0.05 ? 999999 + $ratio_diff * 1000000 : 0;
            $oversize = ($cw > $w * 1.3 || $ch > $h * 1.3) ? 50000 : 0;
            $diff = abs($cw - $w) + abs($ch - $h);
            $s = $diff + $penalty + $oversize;
            if ($cw <= $w && $ch <= $h && $ratio_diff < 0.03)
                $s -= 10000;
            if ($s < $score) {
                $score = $s;
                $best = $name === 'original' ? 'full' : $name;
            }
        }
        return self::$best_size_cache[$key] = $best;
    }

    // ====================== AJAX HANDLERS ======================
    public function ajax_get_queue()
    {
        check_ajax_referer('modern_opti_queue');
        if (!current_user_can('manage_options'))
            wp_die();
        $ids = get_posts(['post_type' => 'attachment', 'post_mime_type' => ['image/jpeg', 'image/png'], 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids']);
        wp_send_json_success(['ids' => $ids]);
    }

    public function ajax_regenerate_single()
    {
        if (!check_ajax_referer('modern_opti_regen', '_wpnonce', false))
            wp_send_json_error('Invalid nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error('Unauthorized');

        $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : 0;
        if (!$id)
            wp_send_json_error('Invalid ID');

        $file = get_attached_file($id);
        if (!$file || !file_exists($file))
            wp_send_json_error('File not found');

        @set_time_limit(120);
        @ini_set('memory_limit', '256M');

        try {
            $meta = wp_get_attachment_metadata($id);
            if ($meta) {
                $this->generate_optimized($meta, $id);
                wp_send_json_success(['id' => $id]);
            } else {
                wp_send_json_error('No metadata');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
        wp_die();
    }

    public function ajax_cleanup_bad_avif()
    {
        check_ajax_referer('modern_opti_cleanup_avif');
        if (!current_user_can('manage_options'))
            wp_die();
        @set_time_limit(300);
        $upload_dir = wp_get_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $deleted = 0;
        $errors = 0;
        $total_freed = 0;
        
        if (!is_dir($base_dir)) {
            wp_send_json_error('Uploads directory not found');
        }

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                try {
                    if ($file->isFile() && strtolower($file->getExtension()) === 'avif') {
                        $path = $file->getPathname();
                        clearstatcache(true, $path);
                        $size = $file->getSize();
                        
                        // Delete if 0 bytes or very small (< 100 bytes is essentially empty for AVIF)
                        if ($size < 100) {
                            if (@unlink($path)) {
                                $deleted++;
                                $total_freed += $size;
                            } else {
                                $errors++;
                            }
                        }
                    }
                } catch (Exception $inner_e) {
                    continue;
                }
            }
        } catch (Exception $e) {
        }
        wp_send_json_success(['deleted' => $deleted, 'errors' => $errors, 'freed' => $total_freed]);
    }

    public function cleanup($id)
    {
        $file = get_attached_file($id);
        if (!$file)
            return;
        $dir = dirname($file);
        $name = pathinfo($file, PATHINFO_FILENAME);
        foreach (['.webp', '.avif'] as $ext)
            @unlink($dir . '/' . $name . $ext);
        $meta = wp_get_attachment_metadata($id);
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $s) {
                $size_name = pathinfo($dir . '/' . $s['file'], PATHINFO_FILENAME);
                foreach (['.webp', '.avif'] as $ext)
                    @unlink($dir . '/' . $size_name . $ext);
            }
        }
    }
}
