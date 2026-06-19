<?php
/**
 * Plugin Name: VNDC Optima
 * Plugin URI: https://www.setiawanspooring.co.id/
 * Description: Developed by VNDC, a trusted leader in web performance and digital acceleration, this plugin leverages cutting-edge caching strategies and asset optimization techniques. Our dedicated focus on technical excellence empowers businesses with lightning-fast websites that drive higher user engagement, superior SEO rankings, and seamless digital experiences.
 * Version: 1.0.0
 * Author: VNDC Digital Agency
 * Author URI: https://www.vndc.co.id
 * License: GPL2
 * GitHub Plugin URI: jerukelektrik/optima
 * Primary Branch: main
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* ==========================================================================
   1. REMOVE HEADER BLOAT
   ========================================================================== */

// Remove emoji scripts and styles
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

// Remove general header elements
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );

/* ==========================================================================
   2. DEQUEUE UNUSED ASSETS (WOOCOMMERCE, CF7, GUTENBERG)
   ========================================================================== */

add_action( 'wp_enqueue_scripts', 'vndc_optima_dequeue_unused_assets', 9999 );
function vndc_optima_dequeue_unused_assets() {
    // 1. Dequeue Gutenberg Blocks CSS (Elementor is main builder)
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'wc-blocks-style' );

    // 2. Conditionally dequeue WooCommerce assets on non-shop pages
    if ( function_exists( 'is_woocommerce' ) ) {
        // Do not dequeue WooCommerce assets on the front page to prevent header/menu layout issues
        if ( ! is_front_page() && ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
            // Styles
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
            wp_dequeue_style( 'woocommerce-general' );
            wp_dequeue_style( 'woocommerce_frontend_styles' );
            wp_dequeue_style( 'wc-blocks-vendors-style' );
            wp_dequeue_style( 'wc-blocks-style' );
            
            // Scripts
            wp_dequeue_script( 'wc-add-to-cart' );
            wp_dequeue_script( 'woocommerce' );
            wp_dequeue_script( 'wc-cart-fragments' );
            wp_dequeue_script( 'jquery-blockui' );
            wp_dequeue_script( 'js-cookie' );
        }
    }

    // 3. Dequeue Contact Form 7 on pages without forms
    global $post;
    $has_form = false;
    if ( is_a( $post, 'WP_Post' ) ) {
        if ( has_shortcode( $post->post_content, 'contact-form-7' ) || strpos( $post->post_content, 'wpcf7' ) !== false ) {
            $has_form = true;
        } else {
            // Check Elementor metadata to see if CF7 is used inside an Elementor template/widget
            $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
            if ( ! empty( $elementor_data ) && is_string( $elementor_data ) ) {
                if ( strpos( $elementor_data, 'contact-form-7' ) !== false || strpos( $elementor_data, 'wpcf7' ) !== false ) {
                    $has_form = true;
                }
            }
        }
    }
    
    // Check request URL (sanitize using esc_url_raw and wp_unslash to prevent security issues)
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    if ( strpos( $request_uri, '/contact/' ) === false && strpos( $request_uri, '/hubungi/' ) === false && ! $has_form ) {
        wp_dequeue_style( 'contact-form-7' );
        wp_dequeue_script( 'contact-form-7' );
    }
}

/* ==========================================================================
   3. DEFER NON-CRITICAL JAVASCRIPT & REMOVE QUERY STRINGS
   ========================================================================== */

// Defer script tags except core jQuery
add_filter( 'script_loader_tag', 'vndc_optima_defer_scripts', 9999, 3 );
function vndc_optima_defer_scripts( $tag, $handle, $src ) {
    if ( is_admin() ) {
        return $tag;
    }

    // Core scripts and plugin assets that should not be deferred to avoid breaking themes
    // Note: 'latepoint' is removed here to allow deferring and improve performance
    $exclude_keywords = array(
        'jquery',
        'elementor',
        'revslider',
        'tp-tools',
        'waypoints',
        'case-addons',
        'counter',
        'countdown',
        'swiper',
        'recster',        // Theme scripts
        'pxl',            // Theme framework scripts
        'wow',            // Scroll animation trigger (WOW.js)
        'gsap',           // Animation engine
        'scroll-trigger', // Scroll trigger for GSAP
        'modernizr',      // Feature detection
    );

    foreach ( $exclude_keywords as $keyword ) {
        if ( strpos( $handle, $keyword ) !== false ) {
            return $tag;
        }
    }

    // Apply defer (use negative lookbehind to ensure we do not touch attributes like data-src)
    if ( strpos( $tag, 'defer' ) === false && strpos( $tag, 'async' ) === false ) {
        $tag = preg_replace( '/(?<!-)\bsrc\s*=\s*/i', 'defer src=', $tag, 1 );
    }

    return $tag;
}

// Load non-critical stylesheets asynchronously to eliminate render-blocking CSS
add_filter( 'style_loader_tag', 'vndc_optima_async_styles', 9999, 4 );
function vndc_optima_async_styles( $tag, $handle, $href, $media ) {
    if ( is_admin() ) {
        return $tag;
    }

    // Check if stylesheet is a Google Font
    if ( strpos( $href, 'fonts.googleapis.com' ) !== false ) {
        $noscript = '<noscript>' . $tag . '</noscript>';
        $tag = preg_replace( '/\smedia=["\']([^"\']*)["\']/i', '', $tag );
        $tag = str_replace( ' href=', ' media="print" onload="this.media=\'all\'" href=', $tag );
        $tag .= $noscript;
        return $tag;
    }

    // Stylesheets that are not critical for initial paint (icons, booking forms, newsletters, modals)
    $async_handles = array(
        'latepoint-front',
        'latepoint-main-front',
        'latepoint-vendor-front',
        'latepoint',
        'woocommerce-layout',
        'woocommerce-smallscreen',
        'woocommerce-general',
        'wc-blocks-style',
        'wc-blocks-vendors-style',
        'newsletter',
        'magnific-popup',
        'twentytwenty',
        'elementor-icons',
        'font-awesome',
        'font-awesome-4-shim',
        'bootstrap-icons',
        'caseicon',
        'icomoon',
        'animate-css',
        'zoomIn-css',
        'brands-css',
        'solid-css',
        'all-css',
        'fontawesome-css',
    );

    $async_keywords = array(
        'latepoint',
        'newsletter',
        'magnific-popup',
        'twentytwenty',
        'icon',
        'font-awesome',
        'fontawesome',
        'bootstrap-icons',
        'woocommerce',
        'wc-blocks',
    );

    $is_async = in_array( $handle, $async_handles, true );
    if ( ! $is_async ) {
        foreach ( $async_keywords as $keyword ) {
            if ( strpos( $handle, $keyword ) !== false ) {
                // Only async WooCommerce styles on non-shop pages
                if ( strpos( $handle, 'woocommerce' ) !== false || strpos( $handle, 'wc-' ) !== false ) {
                    if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
                        continue;
                    }
                }
                $is_async = true;
                break;
            }
        }
    }

    if ( $is_async ) {
        $noscript = '<noscript>' . $tag . '</noscript>';
        $tag = preg_replace( '/\smedia=["\']([^"\']*)["\']/i', '', $tag );
        $tag = str_replace( ' href=', ' media="print" onload="this.media=\'all\'" href=', $tag );
        $tag .= $noscript;
    }

    return $tag;
}

// Remove query string "?ver=x" from styles and scripts
add_filter( 'script_loader_src', 'vndc_optima_remove_query_strings', 9999 );
add_filter( 'style_loader_src', 'vndc_optima_remove_query_strings', 9999 );
function vndc_optima_remove_query_strings( $src ) {
    if ( strpos( $src, '?ver=' ) || strpos( $src, '&ver=' ) ) {
        $src = remove_query_arg( 'ver', $src );
    }
    return $src;
}

/* ==========================================================================
   4. HTML OUTPUT BUFFERING HOOKS
   ========================================================================== */

add_action( 'template_redirect', 'vndc_optima_start_html_buffer' );
function vndc_optima_start_html_buffer() {
    if ( is_admin() || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }
    ob_start( 'vndc_optima_optimize_html_output' );
}

function vndc_optima_optimize_html_output( $html ) {
    if ( empty( $html ) || ! is_string( $html ) ) {
        return $html;
    }

    // A. Apply font-display=swap to Google Fonts
    $html = vndc_optima_optimize_google_fonts( $html );

    // B. Add DNS Prefetch & Preconnect headers
    $html = vndc_optima_add_resource_hints( $html );

    // C. Add width/height dimensions, lazy-loading, and local WebP replacements
    $html = vndc_optima_optimize_images_markup( $html );

    // D. Preload LCP candidates (logo and home page banners)
    $html = vndc_optima_preload_lcp_images( $html );

    // E. Buffer-level Script Deferral & Async Stylesheet Loader (Crucial for plugins that echo scripts directly)
    $html = vndc_optima_buffer_defer_scripts( $html );
    $html = vndc_optima_buffer_async_styles( $html );

    // F. Script-safe HTML Minification
    $html = vndc_optima_minify_html( $html );

    return $html;
}

// Defer non-critical scripts at the HTML buffer level for raw echoed scripts
function vndc_optima_buffer_defer_scripts( $html ) {
    return preg_replace_callback(
        '/<script\s+([^>]+)>/i',
        function( $matches ) {
            $tag = $matches[0];
            $attrs_str = $matches[1];
            
            if ( strpos( $attrs_str, 'src=' ) === false ) {
                return $tag;
            }
            
            if ( strpos( $attrs_str, 'defer' ) !== false || strpos( $attrs_str, 'async' ) !== false ) {
                return $tag;
            }
            
            if ( preg_match( '/src=["\']([^"\']+)["\']/i', $attrs_str, $src_match ) ) {
                $src = strtolower( $src_match[1] );
                
                $exclude_keywords = array(
                    'jquery',
                    'elementor',
                    'revslider',
                    'tp-tools',
                    'waypoints',
                    'case-addons',
                    'counter',
                    'countdown',
                    'swiper',
                    'recster',
                    'pxl',
                    'wow',
                    'gsap',
                    'scroll-trigger',
                    'modernizr',
                );
                
                foreach ( $exclude_keywords as $keyword ) {
                    if ( strpos( $src, $keyword ) !== false ) {
                        return $tag;
                    }
                }
                
                $new_attrs_str = preg_replace( '/(?<!-)\bsrc\s*=\s*/i', 'defer src=', $attrs_str, 1 );
                return '<script ' . $new_attrs_str . '>';
            }
            
            return $tag;
        },
        $html
    );
}

// Convert non-critical stylesheets and Google Fonts to load asynchronously at the HTML buffer level
function vndc_optima_buffer_async_styles( $html ) {
    return preg_replace_callback(
        '/<link\s+([^>]+)>/i',
        function( $matches ) {
            $tag = $matches[0];
            $attrs_str = $matches[1];
            
            if ( strpos( $attrs_str, 'stylesheet' ) === false ) {
                return $tag;
            }
            
            if ( strpos( $attrs_str, 'onload=' ) !== false ) {
                return $tag;
            }
            
            if ( preg_match( '/href=["\']([^"\']+)["\']/i', $attrs_str, $href_match ) ) {
                $href = strtolower( $href_match[1] );
                
                $is_google_font = ( strpos( $href, 'fonts.googleapis.com' ) !== false );
                
                $async_keywords = array(
                    'latepoint',
                    'newsletter',
                    'magnific-popup',
                    'twentytwenty',
                    'icon',
                    'font-awesome',
                    'fontawesome',
                    'bootstrap-icons',
                    'woocommerce',
                    'wc-blocks',
                );
                
                $is_async = $is_google_font;
                if ( ! $is_async ) {
                    foreach ( $async_keywords as $keyword ) {
                        if ( strpos( $href, $keyword ) !== false ) {
                            if ( strpos( $href, 'woocommerce' ) !== false || strpos( $href, 'wc-' ) !== false ) {
                                if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
                                    continue;
                                }
                            }
                            $is_async = true;
                            break;
                        }
                    }
                }
                
                if ( $is_async ) {
                    $new_attrs_str = preg_replace( '/\smedia=["\']([^"\']*)["\']/i', '', $attrs_str );
                    $new_attrs_str = str_replace( ' href=', ' media="print" onload="this.media=\'all\'" href=', $new_attrs_str );
                    return '<link ' . $new_attrs_str . '><noscript>' . $tag . '</noscript>';
                }
            }
            
            return $tag;
        },
        $html
    );
}

/* ==========================================================================
   5. GOOGLE FONTS & RESOURCE HINTS
   ========================================================================== */

function vndc_optima_optimize_google_fonts( $html ) {
    // Inject display=swap parameter to all Google Font link hrefs, matching original opening/closing quotes
    return preg_replace_callback(
        '/(href=(["\'])(?:https?:)?\/\/fonts\.googleapis\.com\/[^"\']+)\2/i',
        function( $matches ) {
            $url = $matches[1];
            $quote = $matches[2];
            $decoded_url = html_entity_decode( $url );
            if ( strpos( $decoded_url, 'display=' ) === false ) {
                $sep = ( strpos( $decoded_url, '?' ) === false ) ? '?' : '&';
                $url .= $sep . 'display=swap';
            }
            return $url . $quote;
        },
        $html
    );
}

function vndc_optima_add_resource_hints( $html ) {
    $hints = '
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://fonts.gstatic.com">
<link rel="dns-prefetch" href="https://www.googletagmanager.com">
<link rel="dns-prefetch" href="https://www.google-analytics.com">
<link rel="dns-prefetch" href="https://connect.facebook.net">
';
    // Insert resource hints immediately after <head>
    return preg_replace( '/<head>/i', "<head>\n" . trim( $hints ), $html, 1 );
}

/* ==========================================================================
   6. IMAGE OPTIMIZATIONS (DIMENSIONS, LAZY, WEBP, PRELOAD LCP)
   ========================================================================== */

function vndc_optima_optimize_images_markup( $html ) {
    return preg_replace_callback(
        '/<img\s+([^>]+)>/i',
        function( $matches ) {
            $img_attribs_str = $matches[1];
            
            // Parse existing attributes
            $attrs = vndc_optima_parse_attributes( $img_attribs_str );
            
            // Extract src
            if ( ! isset( $attrs['src'] ) || empty( $attrs['src'] ) ) {
                return $matches[0];
            }
            $src = $attrs['src'];
            
            // Detect if image is LCP candidate
            $is_lcp = ( 
                strpos( $src, 'logo-setiawan-' ) !== false || 
                strpos( $src, 'bg-slide-home' ) !== false || 
                strpos( $src, 'home-slide-' ) !== false ||
                strpos( $src, 'heo1-' ) !== false
            );
            
            if ( $is_lcp ) {
                // Remove lazy loading from LCP images to load them as fast as possible
                unset( $attrs['loading'] );
                $attrs['fetchpriority'] = 'high';
            } else {
                // Ensure lazy loading
                if ( ! isset( $attrs['loading'] ) ) {
                    $attrs['loading'] = 'lazy';
                }
            }
            
            // WebP Image Conversion (Local files only)
            if ( strpos( $src, '/wp-content/uploads/' ) !== false && ! preg_match( '/\.(webp|svg)$/i', $src ) ) {
                $webp_src = vndc_optima_get_webp_equivalent( $src );
                if ( $webp_src ) {
                    $attrs['src'] = $webp_src;
                    if ( isset( $attrs['srcset'] ) ) {
                        $srcset_parts = explode( ',', $attrs['srcset'] );
                        $new_srcset_parts = array();
                        foreach ( $srcset_parts as $part ) {
                            $part = trim( $part );
                            if ( empty( $part ) ) {
                                continue;
                            }
                            $subparts = preg_split( '/\s+/', $part );
                            if ( ! empty( $subparts[0] ) ) {
                                $sub_url = $subparts[0];
                                $webp_sub = vndc_optima_get_webp_equivalent( $sub_url );
                                if ( $webp_sub ) {
                                    $subparts[0] = $webp_sub;
                                }
                            }
                            $new_srcset_parts[] = implode( ' ', $subparts );
                        }
                        if ( ! empty( $new_srcset_parts ) ) {
                            $attrs['srcset'] = implode( ', ', $new_srcset_parts );
                        }
                    }
                }
            }
            
            // Inject missing width & height attributes
            if ( ! isset( $attrs['width'] ) || ! isset( $attrs['height'] ) ) {
                $dimensions = vndc_optima_get_local_image_dimensions( $src );
                if ( $dimensions ) {
                    if ( ! isset( $attrs['width'] ) ) {
                        $attrs['width'] = $dimensions['width'];
                    }
                    if ( ! isset( $attrs['height'] ) ) {
                        $attrs['height'] = $dimensions['height'];
                    }
                }
            }
            
            // Reconstruct the <img> tag
            $new_attrs = array();
            foreach ( $attrs as $key => $val ) {
                if ( $val === true ) {
                    $new_attrs[] = esc_attr( $key );
                } else {
                    $new_attrs[] = esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
                }
            }
            
            return '<img ' . implode( ' ', $new_attrs ) . ' />';
        },
        $html
    );
}

function vndc_optima_parse_attributes( $attrs_str ) {
    $attrs = array();
    // Match attribute name, double/single-quoted value, or unquoted value
    $pattern = '/\b([a-zA-Z0-9_\-:.]+)(?:\s*=\s*(?:["\']([^"\']*)["\']|([^>\s]+)))?/i';
    if ( preg_match_all( $pattern, $attrs_str, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $name = strtolower( $match[1] );
            $value = '';
            if ( isset( $match[2] ) && $match[2] !== '' ) {
                $value = $match[2];
            } elseif ( isset( $match[3] ) && $match[3] !== '' ) {
                $value = $match[3];
            } elseif ( isset( $match[0] ) && strpos( $match[0], '=' ) !== false ) {
                $value = '';
            } else {
                $value = true;
            }
            $attrs[ $name ] = $value;
        }
    }
    return $attrs;
}

function vndc_optima_get_webp_equivalent( $url ) {
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];
    $base_dir = $upload_dir['basedir'];

    $url_parts = wp_parse_url( $url );
    $base_url_parts = wp_parse_url( $base_url );

    $url_path = isset( $url_parts['path'] ) ? $url_parts['path'] : '';
    $base_path = isset( $base_url_parts['path'] ) ? $base_url_parts['path'] : '';

    if ( empty( $url_path ) || empty( $base_path ) ) {
        return false;
    }

    // Check if the host matches the local uploads directory host (for absolute URLs)
    $url_host = isset( $url_parts['host'] ) ? strtolower( $url_parts['host'] ) : '';
    $base_host = isset( $base_url_parts['host'] ) ? strtolower( $base_url_parts['host'] ) : '';

    if ( $url_host !== '' ) {
        $norm_url_host = preg_replace( '/^www\./i', '', $url_host );
        $norm_base_host = preg_replace( '/^www\./i', '', $base_host );
        if ( $norm_url_host !== $norm_base_host ) {
            return false;
        }
    }

    if ( strpos( $url_path, $base_path ) === 0 ) {
        $rel_path = substr( $url_path, strlen( $base_path ) );
        $file_path = $base_dir . $rel_path;

        // Strip any query parameters from path
        $file_path = preg_replace( '/\?.*$/', '', $file_path );
        $webp_file_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );

        $cache_key = 'vndc_webp_' . md5( $webp_file_path );
        $exists = get_transient( $cache_key );

        if ( false === $exists ) {
            $exists = file_exists( $webp_file_path ) ? 'yes' : 'no';
            if ( 'no' === $exists ) {
                // Try to generate the WebP file on-the-fly in a non-blocking rate-limited way
                $convert_lock_key = 'vndc_lock_' . md5( $webp_file_path );
                if ( ! get_transient( $convert_lock_key ) ) {
                    set_transient( $convert_lock_key, 'yes', HOUR_IN_SECONDS ); // 1 hour rate limit per image
                    $converted = vndc_optima_convert_to_webp( $file_path );
                    if ( $converted ) {
                        $exists = 'yes';
                    }
                }
            }
            set_transient( $cache_key, $exists, DAY_IN_SECONDS );
        }

        if ( 'yes' === $exists ) {
            $new_url_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $url_path );
            
            // Reconstruct the full optimized WebP URL dynamically matching the input format
            $new_url = '';
            if ( isset( $url_parts['scheme'] ) ) {
                $new_url .= $url_parts['scheme'] . '://';
            } elseif ( strpos( $url, '//' ) === 0 ) {
                $new_url .= '//';
            }
            if ( isset( $url_parts['host'] ) ) {
                $new_url .= $url_parts['host'];
            }
            if ( isset( $url_parts['port'] ) ) {
                $new_url .= ':' . $url_parts['port'];
            }
            $new_url .= $new_url_path;
            if ( isset( $url_parts['query'] ) ) {
                $new_url .= '?' . $url_parts['query'];
            }
            return $new_url;
        }
    }
    return false;
}

// Convert a single image file (JPG/PNG) to WebP format using GD or Imagick
function vndc_optima_convert_to_webp( $file_path ) {
    if ( ! file_exists( $file_path ) ) {
        return false;
    }
    
    if ( preg_match( '/\.(webp|gif|svg)$/i', $file_path ) ) {
        return false;
    }
    
    $webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
    if ( file_exists( $webp_path ) ) {
        return true;
    }
    
    $has_gd = function_exists( 'imagewebp' );
    $has_imagick = class_exists( 'Imagick' );
    
    if ( ! $has_gd && ! $has_imagick ) {
        return false;
    }
    
    if ( $has_gd ) {
        $info = @getimagesize( $file_path );
        if ( $info ) {
            $mime = $info['mime'];
            $image = null;
            
            if ( $mime === 'image/jpeg' && function_exists( 'imagecreatefromjpeg' ) ) {
                $image = @imagecreatefromjpeg( $file_path );
            } elseif ( $mime === 'image/png' && function_exists( 'imagecreatefrompng' ) ) {
                $image = @imagecreatefrompng( $file_path );
                if ( $image ) {
                    imagepalettetotruecolor( $image );
                    imagealphasave( $image, true );
                }
            }
            
            if ( $image ) {
                $saved = @imagewebp( $image, $webp_path, 82 ); // 82 quality recommended by Google PageSpeed
                imagedestroy( $image );
                if ( $saved ) {
                    return true;
                }
            }
        }
    }
    
    if ( $has_imagick ) {
        try {
            $im = new Imagick( $file_path );
            $im->setImageFormat( 'webp' );
            $im->setImageCompressionQuality( 82 );
            $saved = $im->writeImage( $webp_path );
            $im->clear();
            $im->destroy();
            if ( $saved ) {
                return true;
            }
        } catch ( Throwable $e ) {
            return false;
        }
    }
    
    return false;
}

// Convert newly uploaded images to WebP format automatically on metadata generation
add_filter( 'wp_generate_attachment_metadata', 'vndc_optima_generate_webp_on_upload', 10, 2 );
function vndc_optima_generate_webp_on_upload( $metadata, $attachment_id ) {
    $file = get_attached_file( $attachment_id );
    if ( ! $file || ! file_exists( $file ) ) {
        return $metadata;
    }
    
    // Convert main image
    vndc_optima_convert_to_webp( $file );
    
    // Convert all resized sizes (thumbnails, medium, large, WooCommerce sizes, etc.)
    if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
        $path_info = pathinfo( $file );
        $dir = $path_info['dirname'];
        foreach ( $metadata['sizes'] as $size => $size_info ) {
            if ( isset( $size_info['file'] ) ) {
                $size_file = $dir . '/' . $size_info['file'];
                vndc_optima_convert_to_webp( $size_file );
            }
        }
    }
    
    return $metadata;
}

function vndc_optima_get_local_image_dimensions( $url ) {
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];
    $base_dir = $upload_dir['basedir'];

    $url_parts = wp_parse_url( $url );
    $base_url_parts = wp_parse_url( $base_url );

    $url_path = isset( $url_parts['path'] ) ? $url_parts['path'] : '';
    $base_path = isset( $base_url_parts['path'] ) ? $base_url_parts['path'] : '';

    if ( empty( $url_path ) || empty( $base_path ) ) {
        return false;
    }

    // Check if the host matches the local uploads directory host (for absolute URLs)
    $url_host = isset( $url_parts['host'] ) ? strtolower( $url_parts['host'] ) : '';
    $base_host = isset( $base_url_parts['host'] ) ? strtolower( $base_url_parts['host'] ) : '';

    if ( $url_host !== '' ) {
        $norm_url_host = preg_replace( '/^www\./i', '', $url_host );
        $norm_base_host = preg_replace( '/^www\./i', '', $base_host );
        if ( $norm_url_host !== $norm_base_host ) {
            return false;
        }
    }

    if ( strpos( $url_path, $base_path ) === 0 ) {
        $rel_path = substr( $url_path, strlen( $base_path ) );
        $file_path = $base_dir . $rel_path;

        // Strip any query parameters from path
        $file_path = preg_replace( '/\?.*$/', '', $file_path );

        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $cache_key = 'vndc_dim_' . md5( $file_path );
        $dimensions = get_transient( $cache_key );

        if ( false === $dimensions ) {
            $size = @getimagesize( $file_path );
            if ( $size ) {
                $dimensions = array(
                    'width'  => $size[0],
                    'height' => $size[1]
                );
                set_transient( $cache_key, $dimensions, WEEK_IN_SECONDS );
            } else {
                $dimensions = 'none';
                set_transient( $cache_key, $dimensions, DAY_IN_SECONDS );
            }
        }

        if ( is_array( $dimensions ) ) {
            return $dimensions;
        }
    }
    return false;
}

function vndc_optima_preload_lcp_images( $html ) {
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];

    // Dynamic resolution of the image preloads (supports dynamic WebP equivalents)
    $logo_url = $base_url . '/2025/12/logo-setiawan-.png';
    $logo_webp = vndc_optima_get_webp_equivalent( $logo_url );
    $logo_preload_url = esc_url( $logo_webp ? $logo_webp : $logo_url );
    $logo_type = $logo_webp ? 'image/webp' : 'image/png';

    if ( ! is_front_page() ) {
        // Just preload logo on inner pages
        $logo_preload = '<link rel="preload" as="image" href="' . $logo_preload_url . '" type="' . $logo_type . '">';
        return preg_replace( '/<\/head>/i', $logo_preload . "\n</head>", $html, 1 );
    }

    $slide1_url = $base_url . '/2026/01/bg-slide-home-1.webp';
    $slide1_webp = vndc_optima_get_webp_equivalent( $slide1_url );
    $slide1_preload_url = esc_url( $slide1_webp ? $slide1_webp : $slide1_url );

    $slide2_url = $base_url . '/2026/01/home-slide-2.webp';
    $slide2_webp = vndc_optima_get_webp_equivalent( $slide2_url );
    $slide2_preload_url = esc_url( $slide2_webp ? $slide2_webp : $slide2_url );

    $hero1_url = $base_url . '/2026/06/heo1-1.png';
    $hero1_webp = vndc_optima_get_webp_equivalent( $hero1_url );
    $hero1_preload_url = esc_url( $hero1_webp ? $hero1_webp : $hero1_url );
    $hero1_type = $hero1_webp ? 'image/webp' : 'image/png';

    $preloads = '
<link rel="preload" as="image" href="' . $logo_preload_url . '" type="' . $logo_type . '">
<link rel="preload" as="image" href="' . $slide1_preload_url . '" type="image/webp">
<link rel="preload" as="image" href="' . $slide2_preload_url . '" type="image/webp">
<link rel="preload" as="image" href="' . $hero1_preload_url . '" type="' . $hero1_type . '">
';
    return preg_replace( '/<\/head>/i', trim( $preloads ) . "\n</head>", $html, 1 );
}

/* ==========================================================================
   7. SAFE HTML MINIFICATION
   ========================================================================== */

function vndc_optima_minify_html( $html ) {
    // Only remove HTML comments (excluding conditional and structural script comments)
    // This is 100% safe, reducing bytes (like Elementor comments) while preserving all spaces, newlines, and spacing layout.
    return preg_replace( '/<!--(?!\[if|<!\[)[\s\S]*?-->/', '', $html );
}

/* ==========================================================================
   8. HTACCESS OPTIMIZATIONS (COMPRESSION & EXPIRES HEADERS)
   ========================================================================== */

register_activation_hook( __FILE__, 'vndc_optima_activate_htaccess_optimizations' );

// Clear all transient caches and pre-convert LCP images to WebP on activation to avoid page speed lag for the first visitor
register_activation_hook( __FILE__, 'vndc_optima_clear_transients_on_activation' );
function vndc_optima_clear_transients_on_activation() {
    try {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vndc_%' OR option_name LIKE '_transient_timeout_vndc_%'" );

        // Pre-convert critical LCP image files to WebP instantly on activation to avoid page speed lag for the first crawler/visitor!
        $upload_dir = wp_upload_dir();
        if ( isset( $upload_dir['basedir'] ) ) {
            $base_dir = $upload_dir['basedir'];
            $lcp_images = array(
                '/2025/12/logo-setiawan-.png',
                '/2026/01/bg-slide-home-1.webp',
                '/2026/01/home-slide-2.webp',
                '/2026/06/heo1-1.png',
                '/2026/01/line-box-action-1.png',
                '/2026/01/bg-button-box.png',
            );
            foreach ( $lcp_images as $img_rel ) {
                $file_path = $base_dir . $img_rel;
                if ( preg_match( '/\.(jpg|jpeg|png)$/i', $file_path ) ) {
                    vndc_optima_convert_to_webp( $file_path );
                }
            }
        }
    } catch ( Throwable $e ) {
        // Safely catch all throwables on activation to ensure activation never fails
    }
}
function vndc_optima_activate_htaccess_optimizations() {
    // Find path to WordPress ABSPATH root .htaccess
    $htaccess_path = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess_path ) ) {
        // Fall back to parent directory if root is one folder up
        $htaccess_path = dirname( ABSPATH ) . '/.htaccess';
    }
    
    if ( ! file_exists( $htaccess_path ) || ! is_writable( $htaccess_path ) ) {
        return;
    }
    
    $rules = "
# BEGIN VNDC Optima Performance Optimization
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/x-javascript application/json application/xml
</IfModule>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresDefault \"access plus 1 month\"
  ExpiresByType image/jpg \"access plus 1 year\"
  ExpiresByType image/jpeg \"access plus 1 year\"
  ExpiresByType image/png \"access plus 1 year\"
  ExpiresByType image/gif \"access plus 1 year\"
  ExpiresByType image/webp \"access plus 1 year\"
  ExpiresByType image/svg+xml \"access plus 1 year\"
  ExpiresByType text/css \"access plus 1 month\"
  ExpiresByType text/javascript \"access plus 1 month\"
  ExpiresByType application/javascript \"access plus 1 month\"
  ExpiresByType application/x-javascript \"access plus 1 month\"
  ExpiresByType application/font-woff \"access plus 1 year\"
  ExpiresByType application/font-woff2 \"access plus 1 year\"
</IfModule>
# END VNDC Optima Performance Optimization
";
    
    $content = @file_get_contents( $htaccess_path );
    if ( $content === false ) {
        return;
    }
    
    if ( strpos( $content, 'VNDC Optima Performance Optimization' ) === false ) {
        $content .= "\n" . trim( $rules ) . "\n";
        @file_put_contents( $htaccess_path, $content, LOCK_EX );
    }
}

register_deactivation_hook( __FILE__, 'vndc_optima_deactivate_htaccess_optimizations' );
function vndc_optima_deactivate_htaccess_optimizations() {
    $htaccess_path = ABSPATH . '.htaccess';
    if ( ! file_exists( $htaccess_path ) ) {
        $htaccess_path = dirname( ABSPATH ) . '/.htaccess';
    }
    
    if ( ! file_exists( $htaccess_path ) || ! is_writable( $htaccess_path ) ) {
        return;
    }
    
    $content = @file_get_contents( $htaccess_path );
    if ( $content === false ) {
        return;
    }
    $pattern = '/# BEGIN VNDC Optima Performance Optimization.*?# END VNDC Optima Performance Optimization/s';
    
    if ( preg_match( $pattern, $content ) ) {
        $content = preg_replace( $pattern, '', $content );
        $content = preg_replace( '/\n{3,}/', "\n\n", $content );
        @file_put_contents( $htaccess_path, $content, LOCK_EX );
    }
}
