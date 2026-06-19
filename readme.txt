=== VNDC Optima ===
Contributors: Antigravity
Tags: speed, performance, pagespeed, elementor, woocommerce, cache, webp, lcp, cls
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later

== Description ==

VNDC Optima is an advanced performance optimization suite engineered to maximize Core Web Vitals, accelerate page load times, and deliver an exceptional user experience for https://www.setiawanspooring.co.id/. It resolves critical PageSpeed Insights issues by implementing automatic, lightweight, and robust optimization techniques.

Developed by VNDC, a trusted leader in web performance and digital acceleration, this plugin leverages cutting-edge caching strategies and asset optimization techniques. Our dedicated focus on technical excellence empowers businesses with lightning-fast websites that drive higher user engagement, superior SEO rankings, and seamless digital experiences.

== Features ==

* **Eliminates Render-Blocking Resources**: Defers non-critical JS scripts while keeping core jQuery intact to prevent breaking theme functionality.
* **Reduces Unused CSS/JS**: Selectively dequeues WooCommerce blocks, WooCommerce core scripts, and Contact Form 7 scripts on pages where they aren't utilized (e.g. homepage). Dequeues unused Gutenberg library styles.
* **Eliminates Text Flash (FOIT)**: Detects Google Fonts references and forces `font-display: swap` parameter, making sure texts remain visible during webfont load.
* **Speeds up Connection Handshake**: Injects DNS-prefetch and Preconnect tags to fonts.googleapis.com, fonts.gstatic.com, googletagmanager.com, etc.
* **Solves CLS (Cumulative Layout Shift)**: Automatically crawls local image files loaded without explicit dimensions and injects their correct `width` and `height` properties in the output HTML.
* **Preloads LCP Candidates**: Preloads the site logo and the slider hero images on the homepage.
* **Enforces Lazy Loading**: Enforces `loading="lazy"` on all below-the-fold image assets, while ensuring above-the-fold images load synchronously with `fetchpriority="high"`.
* **Swaps WebP Assets**: Detects if an uploaded JPG/PNG image has an equivalent WebP file in the uploads directory and swaps the URL to load the lighter WebP asset automatically.
* **Safe HTML Minifier**: Minifies the output HTML markup to save bytes.
* **Server-Level Optimization**: Adds Gzip compression and browser caching rules automatically to the site's `.htaccess` file upon activation.

== Installation ==

1. Zip the `vndc-optima` folder.
2. Go to WordPress Dashboard > Plugins > Add New > Upload Plugin.
3. Choose the zip file and click Install Now.
4. Activate the plugin.
