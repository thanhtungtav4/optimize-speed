<?php
namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

class ScriptDelayService implements ServiceInterface
{
    private $options;
    private $delay_keywords = [];

    public function register()
    {
        add_action('wp', [$this, 'init']);
    }

    public function init()
    {
        $this->options = get_option('optimize_speed_settings', []);
        if (empty($this->options['delay_javascript']) || is_admin() || is_feed()) {
            return;
        }

        $keywords = isset($this->options['delay_javascript_keywords']) ? $this->options['delay_javascript_keywords'] : '';
        if ($keywords) {
            $this->delay_keywords = array_filter(array_map('trim', explode("\n", $keywords)));
        }

        if (empty($this->delay_keywords)) {
            return;
        }

        // Hook into output buffering to catch scripts
        add_action('template_redirect', [$this, 'start_buffering'], 1);
    }

    public function boot()
    {
    }

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

        // Inject Delay JS Handler in footer
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

        // Check against keywords
        $should_delay = false;
        foreach ($this->delay_keywords as $keyword) {
            if (stripos($full_tag, $keyword) !== false) {
                $should_delay = true;
                break;
            }
        }

        if (!$should_delay) {
            return $full_tag;
        }

        // Delay this script
        // Change type to "os/delay" and preserve src/content

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
        // Minified JS handler
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
                    
                    // Copy attributes
                    Array.from(s.attributes).forEach(function(attr) {
                        if(attr.name !== "type" && attr.name !== "data-os-src" && attr.name !== "data-os-type") {
                            n.setAttribute(attr.name, attr.value);
                        }
                    });
                    
                    // Restore type
                    var originalType = s.getAttribute("data-os-type");
                    if(originalType) n.type = originalType;
                    else n.removeAttribute("type"); // default JS
                    
                    // Restore src or content
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
