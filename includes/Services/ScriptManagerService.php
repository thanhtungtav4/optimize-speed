<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ScriptManagerService implements ServiceInterface
{
    private $options;
    private $rules = [];
    private $delay_keywords = [];
    private $page_rules = [];

    public function register()
    {
        add_action('wp', [$this, 'init']);
    }

    public function init()
    {
        if (is_admin() || is_feed()) {
            return;
        }

        $this->options = get_option('optimize_speed_settings', []);

        // 1. Gather Rules
        $this->load_rules();

        // 2. Disable Scripts/Styles (Dequeue)
        add_action('wp_enqueue_scripts', [$this, 'dequeue_assets'], 9999);
        add_action('wp_print_scripts', [$this, 'dequeue_assets'], 9999);
        add_action('wp_print_footer_scripts', [$this, 'dequeue_assets'], 9999);

        // 3. Async/Defer Attributes
        add_filter('script_loader_tag', [$this, 'filter_script_loader_tag'], 10, 3);
        add_filter('style_loader_tag', [$this, 'filter_style_loader_tag'], 10, 3);

        // 4. Global Defer (Legacy Support)
        if (!empty($this->options['defer_javascript'])) {
            add_filter('script_loader_tag', [$this, 'global_defer_js'], 20, 3);
        }

        // 5. Delay Execution
        // We only delay if either global delay is on OR there are specific delay rules
        $global_delay = !empty($this->options['delay_javascript']);
        $has_delay_rules = $this->has_active_delay_rules();

        if ($global_delay || $has_delay_rules) {
            // Load keywords from legacy/global setting
            $keywords = isset($this->options['delay_javascript_keywords']) ? $this->options['delay_javascript_keywords'] : '';
            if ($keywords) {
                $this->delay_keywords = array_filter(array_map('trim', explode("\n", $keywords)));
            }

            add_action('template_redirect', [$this, 'start_buffering'], 1);
        }

        // 6. Scan Request Interceptor
        if (isset($_GET['os_scan_assets']) && $_GET['os_scan_assets'] == '1') {
            add_action('template_redirect', [$this, 'handle_scan_request'], 9999);
        }

        // 7. Preload Injector
        add_action('wp_head', [$this, 'inject_preload_hints'], 1);
    }

    public function boot()
    {
    }

    // --- Scanner Logic ---
    public function handle_scan_request()
    {
        // Use secret key hash for authentication since nonce requires cookie session
        // and we fetch without cookies to simulate guest user
        $key = isset($_GET['os_key']) ? sanitize_text_field($_GET['os_key']) : '';
        $expected_key = substr(md5(NONCE_SALT . 'os_scan'), 0, 16);

        if ($key !== $expected_key) {
            // Fallback: allow if user is admin (with cookies)
            if (!current_user_can('manage_options')) {
                return;
            }
        }

        // Start output buffering immediately to capture all HTML
        ob_start();

        // Hook to wp_footer at very high priority to output JSON
        add_action('wp_footer', [$this, 'return_scan_results'], 999999);
    }

    public function return_scan_results()
    {
        global $wp_scripts, $wp_styles;

        $assets = [
            'js' => [],
            'css' => []
        ];

        // Gather Scripts
        if ($wp_scripts && !empty($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                if (isset($wp_scripts->registered[$handle])) {
                    $src = $wp_scripts->registered[$handle]->src;
                    $assets['js'][] = [
                        'handle' => $handle,
                        'src' => $src
                    ];
                }
            }
        }

        // Gather Styles
        if ($wp_styles && !empty($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                if (isset($wp_styles->registered[$handle])) {
                    $src = $wp_styles->registered[$handle]->src;
                    $assets['css'][] = [
                        'handle' => $handle,
                        'src' => $src
                    ];
                }
            }
        }

        // Discard ALL buffered output (HTML)
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Output clean JSON only
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $assets]);
        exit;
    }

    public function inject_preload_hints()
    {
        global $wp_scripts, $wp_styles;

        // Ensure rules are loaded
        if (empty($this->page_rules)) {
            $this->load_rules();
        }

        // JS Preload
        if (isset($this->page_rules['js'])) {
            foreach ($this->page_rules['js'] as $handle => $rule) {
                // If regex rule, valid handles are not known here, so we skip regex for preload 
                // UNLESS we iterate all registered scripts. For now, strict handle match for preload is safer/easier.
                // Actually, let's support regex if we can efficiently match. 
                // But $handle here is the KEY. If it's regex, the key is the pattern.
                // Preload logic is tricky with Regex because we need to output links for *matching* assets.

                // Simplified: Iterate ALL registered scripts, check against rules.
                foreach ($wp_scripts->registered as $registered_handle => $obj) {
                    $matched_rule = $this->get_rule_for_handle($registered_handle, 'js');
                    if ($matched_rule && $matched_rule['strategy'] === 'preload') {
                        $src = $obj->src;
                        if ($src) {
                            if (strpos($src, '//') === false && strpos($src, 'http') === false) {
                                $src = site_url($src);
                            }
                            $crossorigin = !empty($matched_rule['crossorigin']) ? ' crossorigin="anonymous"' : '';
                            echo '<link rel="preload" href="' . esc_url($src) . '" as="script"' . $crossorigin . '>' . "\n";
                        }
                    }
                }
                break; // We only need to run this loop once, not for every rule. Matches are handled inside.
            }
        }

        // CSS Preload
        if (isset($this->page_rules['css'])) {
            foreach ($wp_styles->registered as $registered_handle => $obj) {
                $matched_rule = $this->get_rule_for_handle($registered_handle, 'css');
                if ($matched_rule && $matched_rule['strategy'] === 'preload') {
                    $src = $obj->src;
                    if ($src) {
                        if (strpos($src, '//') === false && strpos($src, 'http') === false) {
                            $src = site_url($src);
                        }
                        $crossorigin = !empty($matched_rule['crossorigin']) ? ' crossorigin="anonymous"' : '';
                        echo '<link rel="preload" href="' . esc_url($src) . '" as="style"' . $crossorigin . '>' . "\n";
                    }
                }
            }
        }
    }

    private function load_rules()
    {
        $this->rules = isset($this->options['script_manager_rules']) ? $this->options['script_manager_rules'] : [];
        if (!is_array($this->rules)) {
            $this->rules = [];
        }

        $current_id = get_queried_object_id();
        $is_front = is_front_page();

        foreach ($this->rules as $rule) {
            $is_match = false;
            $target = isset($rule['target']) ? $rule['target'] : 'global';

            if ($target === 'custom' && !empty($rule['custom_id'])) {
                $target = $rule['custom_id'];
            }

            if ($target === 'global') {
                $is_match = true;
            } elseif ($target === 'homepage' && $is_front) {
                $is_match = true;
            } elseif (is_numeric($target) && (int) $target === $current_id) {
                $is_match = true;
            } elseif ($target === 'post_type' && !empty($rule['custom_id'])) {
                $type = $rule['custom_id'];
                if (is_singular($type) || is_post_type_archive($type)) {
                    $is_match = true;
                }
                if ($type === 'page' && is_page()) {
                    $is_match = true;
                }
            } elseif ($target === 'page_template' && !empty($rule['custom_id'])) {
                if (is_page_template($rule['custom_id'])) {
                    $is_match = true;
                }
            }

            if ($is_match) {
                $handle = isset($rule['handle']) ? $rule['handle'] : '';
                $type = isset($rule['type']) ? $rule['type'] : 'js';
                // Store full rule, not just strategy
                if ($handle) {
                    $this->page_rules[$type][$handle] = $rule;
                }
            }
        }
    }

    /**
     * Helper to find matching rule (Exact or Regex)
     */
    private function get_rule_for_handle($handle, $type)
    {
        if (empty($this->page_rules[$type])) {
            return null;
        }

        // 1. Exact Match
        if (isset($this->page_rules[$type][$handle]) && empty($this->page_rules[$type][$handle]['is_regex'])) {
            return $this->page_rules[$type][$handle];
        }

        // 2. Regex Match
        foreach ($this->page_rules[$type] as $rule_handle => $rule) {
            if (!empty($rule['is_regex'])) {
                // Check if pattern matches
                // Regex validation should happen on save, but here we supression errors just in case
                if (@preg_match('#' . $rule_handle . '#', $handle)) {
                    return $rule;
                }
            }
        }

        return null;
    }

    private function has_active_delay_rules()
    {
        if (isset($this->page_rules['js'])) {
            // Need to check strategy prop now
            foreach ($this->page_rules['js'] as $rule) {
                if (isset($rule['strategy']) && $rule['strategy'] === 'delay') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if a handle is protected and should not be modified/optimized.
     */
    private function is_protected_handle($handle)
    {
        // Whitelist critical scripts if Admin Bar is showing
        if (is_admin_bar_showing()) {
            $critical_handles = [
                'admin-bar',
                'wp-core-commands',
                'wp-commands',
                'wp-i18n',
                'wp-dom-ready',
                'wp-a11y',
                'dashicons',
                'common',
                'hoverIntent',
                'hoverintent-js',
                'wp-url',
                'wp-hooks',
            ];
            if (in_array($handle, $critical_handles)) {
                return true;
            }
        }
        return false;
    }

    // --- Dequeue Logic ---

    public function dequeue_assets()
    {
        // Strategy: Iterate Queue
        global $wp_scripts, $wp_styles;

        if ($wp_scripts && !empty($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                if ($this->is_protected_handle($handle)) {
                    continue;
                }

                $rule = $this->get_rule_for_handle($handle, 'js');
                if ($rule && $rule['strategy'] === 'disable') {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }

        if ($wp_styles && !empty($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                if ($this->is_protected_handle($handle)) {
                    continue;
                }

                $rule = $this->get_rule_for_handle($handle, 'css');
                if ($rule && $rule['strategy'] === 'disable') {
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                }
            }
        }
    }

    // --- Async/Defer Logic ---

    public function filter_script_loader_tag($tag, $handle, $src)
    {
        if ($this->is_protected_handle($handle)) {
            return $tag;
        }

        $rule = $this->get_rule_for_handle($handle, 'js');
        if ($rule) {
            $strategy = $rule['strategy'];
            if ($strategy === 'async') {
                return str_replace('<script ', '<script async ', $tag);
            } elseif ($strategy === 'defer') {
                return str_replace('<script ', '<script defer ', $tag);
            }
        }
        return $tag;
    }

    public function filter_style_loader_tag($tag, $handle, $src)
    {
        if ($this->is_protected_handle($handle)) {
            return $tag;
        }

        $rule = $this->get_rule_for_handle($handle, 'css');
        if ($rule) {
            $strategy = $rule['strategy'];
            if ($strategy === 'async') {
                return preg_replace(
                    "/(rel\s*=\s*['\"]stylesheet['\"])/",
                    "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
                    $tag
                );
            }
        }
        return $tag;
    }

    public function global_defer_js($tag, $handle, $src)
    {
        if ($this->is_protected_handle($handle)) {
            return $tag;
        }

        // Don't defer if already async or deferred by rule
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        // Don't defer jquery usually?
        if ($handle === 'jquery' || $handle === 'jquery-core') {
            return $tag;
        }
        return str_replace('<script ', '<script defer ', $tag);
    }

    // --- Delay Logic (Buffer) ---

    public function start_buffering()
    {
        ob_start([$this, 'process_html']);
    }

    public function process_html($html)
    {
        // Find all script tags
        $html = preg_replace_callback(
            '#<script([^>]*)>(.*?)</script>#is',
            [$this, 'replace_script'],
            $html
        );

        // Inject Delay JS Handler in footer if any scripts were delayed or if global delay is on
        // Logic check: only inject if we actually delayed something? 
        // For simplicity, if delay is active, we inject the handler.
        if (strpos($html, '</body>') !== false) {
            $handler = $this->get_delay_js_handler();
            $html = str_replace('</body>', $handler . '</body>', $html);
        }

        return $html;
    }

    public function replace_script($matches)
    {
        $attrs = $matches[1];
        $content = $matches[2];
        $full_tag = $matches[0];

        $should_delay = false;

        // 1. Check Global Keywords
        foreach ($this->delay_keywords as $keyword) {
            if ($keyword && stripos($full_tag, $keyword) !== false) {
                $should_delay = true;
                break;
            }
        }

        // 2. Check Page Rules (Src or ID matching is hard with regex, we rely on string search in tag)
        // If user set a rule "delay" for handle "my-script", 
        // but here we are parsing raw HTML. We don't know the handle easily.
        // LIMITATION: 'Delay' per handle is tricky in raw HTML unless we map handles to src.
        // Workaround: We encourage users to use Keywords for Delay.
        // OR: We try to match known src from handles? Too complex for now.
        // User prompt say "Handle or filename".

        // If the rules have a 'delay' entry, we try to match it as a keyword against the tag
        if (isset($this->page_rules['js'])) {
            foreach ($this->page_rules['js'] as $handle_or_keyword => $strategy) {
                if ($strategy === 'delay' && stripos($full_tag, $handle_or_keyword) !== false) {
                    $should_delay = true;
                    break;
                }
            }
        }

        if (!$should_delay) {
            return $full_tag;
        }

        // Apply Delay Transformation
        $new_attrs = $attrs;

        // Handle type attribute
        if (stripos($new_attrs, 'type=') !== false) {
            $new_attrs = preg_replace('/type=["\']([^"\']*)["\']/', 'type="os/delayed" data-os-type="$1"', $new_attrs);
        } else {
            $new_attrs .= ' type="os/delayed"';
        }

        // Backup Source
        if (stripos($new_attrs, 'src=') !== false) {
            $new_attrs = preg_replace('/src=["\']([^"\']*)["\']/', 'data-os-src="$1"', $new_attrs);
        }

        return "<script{$new_attrs}>{$content}</script>";
    }

    private function get_delay_js_handler()
    {
        // Same handler as before
        return '<script>
        (function() {
            var triggered = false;
            var events = ["mousemove", "keydown", "wheel", "touchmove", "touchstart"];
            
            function trigger() {
                if(triggered) return;
                triggered = true;
                
                events.forEach(function(e) { window.removeEventListener(e, trigger, {passive: true}); });
                
                var scripts = document.querySelectorAll("script[type=\'os/delayed\']");
                var loadNext = function(i) {
                    if(i >= scripts.length) return;
                    var s = scripts[i];
                    var n = document.createElement("script");
                    
                    Array.from(s.attributes).forEach(function(attr) {
                        if(attr.name !== "type" && attr.name !== "data-os-src" && attr.name !== "data-os-type") {
                            n.setAttribute(attr.name, attr.value);
                        }
                    });
                    
                    var originalType = s.getAttribute("data-os-type");
                    if(originalType) n.type = originalType;
                    else n.removeAttribute("type");
                    
                    var src = s.getAttribute("data-os-src");
                    if(src) {
                        n.src = src;
                        n.onload = function() { loadNext(i+1); };
                        n.onerror = function() { loadNext(i+1); };
                        s.parentNode.replaceChild(n, s);
                    } else {
                        n.text = s.innerHTML;
                        s.parentNode.replaceChild(n, s);
                        loadNext(i+1);
                    }
                };
                
                loadNext(0);
            }
            
            events.forEach(function(e) { window.addEventListener(e, trigger, {passive: true}); });
        })();
        </script>';
    }
}
