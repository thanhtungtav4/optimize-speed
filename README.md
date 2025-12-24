# Optimize Speed WordPress Plugin

All-in-one WordPress optimization plugin: Performance, Security & Analytics.

## Features

### 🎭 Partytown Integration
- Move 3rd-party scripts to Web Workers (off main thread)
- Supported platforms:
  - Google Tag Manager (GTM)
  - Google Analytics 4 (GA4)
  - Facebook Pixel
  - TikTok Pixel
  - Microsoft Clarity
  - Matomo Analytics
- **Bundled assets** - No write permissions needed
- Automatic fallback if Partytown disabled

### 🖼️ Native Image Optimization
- Auto WebP/AVIF generation
- Native lazy loading (`loading="lazy"`)
- LCP optimization (`fetchpriority="high"`)
- Bulk image regeneration

### 🔒 Security Hardening
- **Custom Login URL** - Hide wp-login.php
- **Limit Login Attempts** - Block IP after 5 failed attempts (15min lockout)
- **Security Headers** - X-Frame-Options, X-XSS-Protection, CSP
- **Enable HSTS** - HTTP Strict Transport Security
- **Disable File Editing** - Block theme/plugin editor in admin
- **Block PHP in Uploads** - Prevent PHP execution in uploads folder
- **Disable XML-RPC** - Block XML-RPC & pingbacks

### 🚀 Performance Optimization
- Lazy Load Iframes/Videos (YouTube/Vimeo facades)
- Local Google Fonts (download & serve locally)
- Resource Preloading
- Script Manager with Defer/Delay options

### 🧹 Bloat Removal (40+ options!)
**WordPress Core:**
- Disable Emojis, Embeds, XML-RPC
- Remove jQuery / jQuery Migrate
- Remove Meta Generator & Version info

**Performance:**
- Defer JavaScript loading
- Disable DNS Prefetch
- Limit Heartbeat API

**Assets & Styles:**
- Disable Google Fonts
- Remove Dashicons on frontend
- Remove Query Strings from CSS/JS

**WooCommerce:**
- Disable Cart Fragments (AJAX)
- Remove WC scripts on non-shop pages

**Gutenberg:**
- Disable Global Styles & Block CSS
- Disable Duotone SVG filters

**Page Builders:**
- Smart Elementor asset loading

### 🗄️ Database Optimization
- Clean expired transients
- Remove post revisions
- Clear auto-drafts
- Optimize tables
- One-click cleanup tools

## Installation

1. Upload plugin to `/wp-content/plugins/optimize-speed/`
2. Activate plugin
3. Go to **Settings → Optimize Speed**
4. Configure your optimization settings

## Requirements

- WordPress 5.8+
- PHP 7.4+
- HTTPS (required for Service Workers & HSTS)

## Documentation

- **Website:** https://nttung.dev/toi-uu-toc-do-website/
- **INP Guide:** https://nttung.dev/huong-dan-toi-uu-inp/

## Changelog

### v1.0.3 (December 24, 2024)
- ✅ **Security Hardening Service** - Comprehensive security features
- ✅ **Limit Login Attempts** - Block brute-force attacks
- ✅ **Security Headers** - X-Frame-Options, X-XSS-Protection, HSTS
- ✅ **Custom Login URL** - Hide wp-login.php
- ✅ **Block PHP in Uploads** - Prevent malicious uploads
- ✅ **Disable XML-RPC** - Block pingbacks
- ✅ **BaseService Class** - Cached options, helper methods
- ✅ Code refactoring & optimization

### v1.0.2 (December 2024)
- ✅ Script Manager with Defer/Delay
- ✅ Lazy Load Iframes/Videos
- ✅ Local Google Fonts
- ✅ Resource Hints

### v1.0.1 (December 3, 2024)
- ✅ **Bundled Partytown assets** (no CDN download)
- ✅ Added GTM support
- ✅ Fixed tab navigation UI
- ✅ Works on restricted hosting

### v1.0.0
- Initial release

## Support

For issues or questions, please contact: support@nttung.dev

## License

MIT License - Feel free to use in your projects!

---

**Made with ❤️ by Dev Team**
