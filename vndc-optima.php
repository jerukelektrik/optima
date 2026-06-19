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

        // Register sitemap rewrite rules and flush
        if ( function_exists( 'vndc_optima_sitemap_rewrite_rule' ) ) {
            vndc_optima_sitemap_rewrite_rule();
        }
        flush_rewrite_rules();

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

    // Flush rewrite rules on deactivation to clean up sitemap.xml
    flush_rewrite_rules();
}

/* ==========================================================================
   9. SETTINGS & OPTIONS CONSOLE (VNDC BRANDED)
   ========================================================================== */

function vndc_optima_get_settings() {
    $defaults = array(
        'classic_editor'  => 1,
        'post_duplicator' => 1,
        'whatsapp_button' => 1,
        'whatsapp_number' => '',
        'whatsapp_message'=> 'Halo Setiawan Spooring, saya ingin bertanya tentang layanan Anda...',
        'seo_module'      => 1,
    );
    $settings = get_option( 'vndc_optima_settings', $defaults );
    return wp_parse_args( $settings, $defaults );
}

add_action( 'admin_menu', 'vndc_optima_add_admin_menu' );
function vndc_optima_add_admin_menu() {
    add_menu_page(
        'VNDC Optima Settings',
        'VNDC Optima',
        'manage_options',
        'vndc-optima',
        'vndc_optima_settings_page_html',
        'dashicons-performance',
        90
    );
}

function vndc_optima_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Save Settings Action
    if ( isset( $_POST['vndc_optima_save_settings'] ) ) {
        check_admin_referer( 'vndc_optima_settings_save', 'vndc_optima_settings_nonce' );
        
        $settings = array(
            'classic_editor'  => isset( $_POST['classic_editor'] ) ? 1 : 0,
            'post_duplicator' => isset( $_POST['post_duplicator'] ) ? 1 : 0,
            'whatsapp_button' => isset( $_POST['whatsapp_button'] ) ? 1 : 0,
            'whatsapp_number' => sanitize_text_field( $_POST['whatsapp_number'] ),
            'whatsapp_message'=> sanitize_textarea_field( $_POST['whatsapp_message'] ),
            'seo_module'      => isset( $_POST['seo_module'] ) ? 1 : 0,
        );
        
        update_option( 'vndc_optima_settings', $settings );
        
        // Re-register sitemap rewrite rules and flush
        vndc_optima_sitemap_rewrite_rule();
        flush_rewrite_rules();
        
        echo '<div class="notice notice-success is-dismissible"><p><strong>VNDC Optima:</strong> Settings saved and rewrite rules flushed successfully!</p></div>';
    }
    
    // Purge Cache Action
    if ( isset( $_POST['vndc_optima_purge_cache'] ) ) {
        check_admin_referer( 'vndc_optima_cache_purge', 'vndc_optima_purge_nonce' );
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vndc_%' OR option_name LIKE '_transient_timeout_vndc_%'" );
        echo '<div class="notice notice-success is-dismissible"><p><strong>VNDC Optima:</strong> Optimization cache & WebP conversion locks purged successfully!</p></div>';
    }
    
    $settings = vndc_optima_get_settings();
    
    // Statistics for status widget
    global $wpdb;
    $cached_webp_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_vndc_webp_%'" );
    $gd_status = extension_loaded('gd') ? '<span style="color: #2ec4b6; font-weight: bold;">Active</span>' : '<span style="color: #e71d36; font-weight: bold;">Inactive</span>';
    $imagick_status = extension_loaded('imagick') ? '<span style="color: #2ec4b6; font-weight: bold;">Active</span>' : '<span style="color: #e71d36; font-weight: bold;">Inactive</span>';
    
    ?>
    <div class="wrap vndc-settings-wrap">
        <style>
            .vndc-settings-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; max-width: 1200px; margin-top: 20px; }
            .vndc-header { background: linear-gradient(135deg, #1d1b3a 0%, #0d0c1d 100%); color: #fff; padding: 25px 30px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; }
            .vndc-header h1 { color: #fff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
            .vndc-header p { margin: 5px 0 0 0; color: #a5a2d6; font-size: 14px; }
            .vndc-brand { font-weight: bold; background: linear-gradient(to right, #00f2fe, #4facfe); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            
            .vndc-layout { display: flex; gap: 20px; flex-wrap: wrap; }
            .vndc-col-main { flex: 2; min-width: 320px; }
            .vndc-col-sidebar { flex: 1; min-width: 300px; }
            
            .vndc-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-top: 4px solid #4facfe; }
            .vndc-card.sidebar-card { border-top-color: #2ec4b6; }
            .vndc-card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 12px; color: #1d1b3a; font-size: 18px; font-weight: 600; }
            
            /* Toggle Switch */
            .vndc-option-row { display: flex; align-items: flex-start; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f6f6f6; }
            .vndc-option-row:last-child { border-bottom: none; }
            .vndc-option-info { flex: 1; padding-right: 20px; }
            .vndc-option-info label { font-weight: 600; color: #2d3748; font-size: 15px; display: block; margin-bottom: 4px; }
            .vndc-option-info p { margin: 0; color: #718096; font-size: 13px; line-height: 1.4; }
            
            .vndc-switch { position: relative; display: inline-block; width: 48px; height: 24px; }
            .vndc-switch input { opacity: 0; width: 0; height: 0; }
            .vndc-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e0; transition: .3s; border-radius: 24px; }
            .vndc-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; }
            input:checked + .vndc-slider { background-color: #4facfe; }
            input:checked + .vndc-slider:before { transform: translateX(24px); }
            
            /* Inputs */
            .vndc-sub-fields { background: #f7fafc; padding: 15px; border-radius: 6px; margin-top: 10px; border-left: 3px solid #cbd5e0; }
            .vndc-field-group { margin-bottom: 12px; }
            .vndc-field-group:last-child { margin-bottom: 0; }
            .vndc-field-group label { display: block; font-weight: 600; color: #4a5568; font-size: 13px; margin-bottom: 4px; }
            .vndc-field-group input[type="text"], .vndc-field-group textarea { width: 100%; border: 1px solid #cbd5e0; border-radius: 4px; padding: 8px; font-size: 13px; }
            
            /* Buttons */
            .button.vndc-btn-save { background: #4facfe; border-color: #4facfe; color: #fff; font-weight: 600; padding: 6px 20px; height: auto; border-radius: 4px; transition: background 0.2s; text-shadow: none; box-shadow: none; }
            .button.vndc-btn-save:hover { background: #00f2fe; border-color: #00f2fe; color: #fff; }
            .button.vndc-btn-purge { background: #e71d36; border-color: #e71d36; color: #fff; text-shadow: none; border-radius: 4px; }
            .button.vndc-btn-purge:hover { background: #ff4d6d; border-color: #ff4d6d; color: #fff; }
            
            /* Table stats */
            .vndc-stats-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .vndc-stats-table td { padding: 10px 0; border-bottom: 1px solid #eee; font-size: 13px; }
            .vndc-stats-table td:last-child { text-align: right; font-weight: 600; color: #2d3748; }
            .vndc-stats-table tr:last-child td { border-bottom: none; }
        </style>

        <div class="vndc-header">
            <div>
                <h1>VNDC Optima <span class="vndc-brand">Control Panel</span></h1>
                <p>Manage performance acceleration, consolidated features, and lightweight SEO tools.</p>
            </div>
            <div>
                <span style="background: rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">v1.0.0</span>
            </div>
        </div>

        <div class="vndc-layout">
            <div class="vndc-col-main">
                <form method="post" action="">
                    <?php wp_nonce_field( 'vndc_optima_settings_save', 'vndc_optima_settings_nonce' ); ?>
                    
                    <div class="vndc-card">
                        <h2>🔄 Feature Consolidation</h2>
                        
                        <!-- Classic Editor Toggle -->
                        <div class="vndc-option-row">
                            <div class="vndc-option-info">
                                <label for="classic_editor">Classic Editor</label>
                                <p>Replaces the default Gutenberg block editor with the classic editor. (Safe to deactivate the standalone "Classic Editor" plugin)</p>
                            </div>
                            <div>
                                <label class="vndc-switch">
                                    <input type="checkbox" name="classic_editor" id="classic_editor" value="1" <?php checked( $settings['classic_editor'], 1 ); ?> />
                                    <span class="vndc-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Post Duplicator Toggle -->
                        <div class="vndc-option-row">
                            <div class="vndc-option-info">
                                <label for="post_duplicator">Post & Page Duplicator</label>
                                <p>Adds a "Duplicate" link to the quick actions in posts, pages, and WooCommerce products. (Safe to deactivate the standalone "Duplicate Page" plugin)</p>
                            </div>
                            <div>
                                <label class="vndc-switch">
                                    <input type="checkbox" name="post_duplicator" id="post_duplicator" value="1" <?php checked( $settings['post_duplicator'], 1 ); ?> />
                                    <span class="vndc-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- WhatsApp Floating Button -->
                        <div class="vndc-option-row" style="border-bottom: none;">
                            <div class="vndc-option-info">
                                <label for="whatsapp_button">WhatsApp Floating Button</label>
                                <p>Injects an ultra-lightweight floating WhatsApp button in the corner of your site. (Safe to deactivate the standalone "Click to Chat" plugin)</p>
                                
                                <div class="vndc-sub-fields" id="wa-config-fields">
                                    <div class="vndc-field-group">
                                        <label for="whatsapp_number">WhatsApp Phone Number</label>
                                        <input type="text" name="whatsapp_number" id="whatsapp_number" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" placeholder="e.g. 628123456789 (Use country code, no spaces)" />
                                    </div>
                                    <div class="vndc-field-group">
                                        <label for="whatsapp_message">Default Chat Greeting Message</label>
                                        <textarea name="whatsapp_message" id="whatsapp_message" rows="2" placeholder="Greeting message loaded automatically in chat..."><?php echo esc_textarea( $settings['whatsapp_message'] ); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="vndc-switch">
                                    <input type="checkbox" name="whatsapp_button" id="whatsapp_button" value="1" <?php checked( $settings['whatsapp_button'], 1 ); ?> />
                                    <span class="vndc-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="vndc-card" style="border-top-color: #a5a2d6;">
                        <h2>🚀 Lightweight SEO Engine</h2>
                        
                        <div class="vndc-option-row" style="border-bottom: none;">
                            <div class="vndc-option-info">
                                <label for="seo_module">Enable Core SEO Tools</label>
                                <p>Activates XML Sitemap generation, meta title/description custom inputs, WooCommerce JSON-LD Schema, and automatic 301 redirects on slug changes. (Allows safe deactivation of "Rank Math SEO")</p>
                                <ul style="margin: 8px 0 0 15px; padding: 0; list-style-type: disc; font-size: 12px; color: #718096;">
                                    <li>Sitemap location: <a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank">setiawanspooring.co.id/sitemap.xml</a></li>
                                    <li>WooCommerce JSON-LD integration injects stars, stock, and price to Google search results automatically.</li>
                                    <li>URL Redirect tracking prevents 404 broken links on slug changes.</li>
                                </ul>
                            </div>
                            <div>
                                <label class="vndc-switch">
                                    <input type="checkbox" name="seo_module" id="seo_module" value="1" <?php checked( $settings['seo_module'], 1 ); ?> />
                                    <span class="vndc-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <p style="margin-top: 20px;">
                        <input type="submit" name="vndc_optima_save_settings" class="button vndc-btn-save" value="Save Settings" />
                    </p>
                </form>
            </div>
            
            <div class="vndc-col-sidebar">
                <div class="vndc-card sidebar-card">
                    <h2>📊 System Status</h2>
                    <table class="vndc-stats-table">
                        <tr>
                            <td>GD Library</td>
                            <td><?php echo $gd_status; ?></td>
                        </tr>
                        <tr>
                            <td>ImageMagick</td>
                            <td><?php echo $imagick_status; ?></td>
                        </tr>
                        <tr>
                            <td>Cached WebP Images</td>
                            <td><?php echo (int) $cached_webp_count; ?> items</td>
                        </tr>
                        <tr>
                            <td>HTML Output Cache</td>
                            <td><span style="color: #2ec4b6; font-weight: bold;">Active</span></td>
                        </tr>
                        <tr>
                            <td>LCP Preloader Cache</td>
                            <td><span style="color: #2ec4b6; font-weight: bold;">Active</span></td>
                        </tr>
                    </table>
                    
                    <form method="post" action="" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px; text-align: center;">
                        <?php wp_nonce_field( 'vndc_optima_cache_purge', 'vndc_optima_purge_nonce' ); ?>
                        <p style="font-size: 11px; color: #718096; text-align: left; margin-bottom: 10px;">Purging cache drops all image converter locks and transient caches to force a fresh scan of LCP and other assets.</p>
                        <input type="submit" name="vndc_optima_purge_cache" class="button vndc-btn-purge" value="Purge Cache & Re-Scan" />
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/* ==========================================================================
   10. CONSOLIDATED FEATURES LOGIC
   ========================================================================== */

add_action( 'init', 'vndc_optima_init_consolidated_features' );
function vndc_optima_init_consolidated_features() {
    $settings = vndc_optima_get_settings();
    
    // 1. Classic Editor
    if ( ! empty( $settings['classic_editor'] ) ) {
        add_filter( 'use_block_editor_for_post', '__return_false', 10 );
        add_filter( 'use_block_editor_for_post_type', '__return_false', 10 );
        add_action( 'wp_enqueue_scripts', 'vndc_optima_disable_gutenberg_styles', 9999 );
    }
    
    // 2. Post Duplicator
    if ( ! empty( $settings['post_duplicator'] ) ) {
        add_filter( 'post_row_actions', 'vndc_optima_duplicate_post_link', 10, 2 );
        add_filter( 'page_row_actions', 'vndc_optima_duplicate_post_link', 10, 2 );
    }
}

function vndc_optima_disable_gutenberg_styles() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'wc-blocks-style' );
}

// Post Duplicator Link & Handler
function vndc_optima_duplicate_post_link( $actions, $post ) {
    if ( current_user_can( 'edit_posts' ) ) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url( admin_url( 'admin-post.php?action=vndc_duplicate_post_as_draft&post=' . $post->ID ), 'vndc_duplicate_post_' . $post->ID ) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
    }
    return $actions;
}

add_action( 'admin_post_vndc_duplicate_post_as_draft', 'vndc_optima_duplicate_post_as_draft' );
function vndc_optima_duplicate_post_as_draft() {
    if ( empty( $_GET['post'] ) ) {
        wp_die( 'No post to duplicate has been supplied!' );
    }
    $post_id = (int) $_GET['post'];
    check_admin_referer( 'vndc_duplicate_post_' . $post_id );

    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_die( 'Post creation failed, could not find original post: ' . $post_id );
    }

    $current_user = wp_get_current_user();
    $new_post_author = $current_user->ID;

    $args = array(
        'post_author'    => $new_post_author,
        'post_content'   => $post->post_content,
        'post_title'     => $post->post_title . ' (Copy)',
        'post_excerpt'   => $post->post_excerpt,
        'post_status'    => 'draft',
        'post_pingpass'  => $post->post_pingpass,
        'post_parent'    => $post->post_parent,
        'post_password'  => $post->post_password,
        'post_type'      => $post->post_type,
        'to_ping'        => $post->to_ping,
        'pinged'         => $post->pinged,
        'post_content_filtered' => $post->post_content_filtered,
    );

    $new_post_id = wp_insert_post( $args );

    // Copy taxonomies
    $taxonomies = get_object_taxonomies( $post->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
        wp_set_object_terms( $new_post_id, $post_terms, $taxonomy );
    }

    // Copy post meta
    $post_meta_infos = get_post_meta( $post_id );
    if ( ! empty( $post_meta_infos ) ) {
        foreach ( $post_meta_infos as $meta_key => $meta_values ) {
            foreach ( $meta_values as $meta_value ) {
                $meta_value = maybe_unserialize( $meta_value );
                add_post_meta( $new_post_id, $meta_key, $meta_value );
            }
        }
    }

    wp_safe_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
    exit;
}

// WhatsApp Floating Button Footer Injector
add_action( 'wp_footer', 'vndc_optima_whatsapp_button_html' );
function vndc_optima_whatsapp_button_html() {
    $settings = vndc_optima_get_settings();
    
    if ( empty( $settings['whatsapp_button'] ) || empty( $settings['whatsapp_number'] ) ) {
        return;
    }
    
    $number = sanitize_text_field( $settings['whatsapp_number'] );
    $message = sanitize_text_field( $settings['whatsapp_message'] );
    
    $clean_number = preg_replace( '/[^0-9]/', '', $number );
    if ( strpos( $clean_number, '0' ) === 0 ) {
        $clean_number = '62' . substr( $clean_number, 1 );
    }
    
    $wa_url = 'https://wa.me/' . $clean_number;
    if ( ! empty( $message ) ) {
        $wa_url .= '?text=' . urlencode( $message );
    }
    
    ?>
    <!-- VNDC WhatsApp Floating Button -->
    <a href="<?php echo esc_url( $wa_url ); ?>" class="vndc-wa-float" target="_blank" rel="noopener noreferrer" aria-label="Chat via WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="30" height="30" fill="#fff"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L3 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
    </a>
    <style>
        .vndc-wa-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 30px;
            right: 30px;
            background-color: #25d366;
            color: #FFF;
            border-radius: 50px;
            text-align: center;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .vndc-wa-float:hover {
            transform: scale(1.1);
            background-color: #128c7e;
            box-shadow: 0px 6px 20px rgba(0, 0, 0, 0.3);
        }
        @media screen and (max-width: 767px) {
            .vndc-wa-float {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }
        }
    </style>
    <?php
}

/* ==========================================================================
   11. LIGHTWEIGHT SEO ENGINE
   ========================================================================== */

// 1. Meta Box Registration
add_action( 'add_meta_boxes', 'vndc_optima_seo_register_meta_box' );
function vndc_optima_seo_register_meta_box() {
    $settings = vndc_optima_get_settings();
    if ( ! empty( $settings['seo_module'] ) ) {
        $post_types = array( 'post', 'page', 'product' );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'vndc-seo-settings',
                'VNDC SEO Settings',
                'vndc_optima_seo_meta_box_html',
                $post_type,
                'normal',
                'high'
            );
        }
    }
}

function vndc_optima_seo_meta_box_html( $post ) {
    $title = get_post_meta( $post->ID, '_vndc_seo_title', true );
    $desc = get_post_meta( $post->ID, '_vndc_seo_desc', true );
    wp_nonce_field( 'vndc_seo_save_meta', 'vndc_seo_meta_nonce' );
    ?>
    <div class="vndc-seo-meta-box-container">
        <style>
            .vndc-seo-meta-box-container p { margin-bottom: 15px; }
            .vndc-seo-meta-box-container label { display: block; font-weight: 600; margin-bottom: 5px; color: #1d1b3a; }
            .vndc-seo-meta-box-container input[type="text"],
            .vndc-seo-meta-box-container textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 13px; }
            .vndc-seo-meta-box-container .char-count { font-size: 11px; color: #718096; margin-top: 3px; display: block; text-align: right; }
        </style>
        <p>
            <label for="vndc_seo_title">SEO Meta Title</label>
            <input type="text" id="vndc_seo_title" name="vndc_seo_title" value="<?php echo esc_attr( $title ); ?>" placeholder="Leave blank to use default page title..." />
            <span class="char-count"><span id="vndc_seo_title_count">0</span> characters (Recommended: 50-60)</span>
        </p>
        <p>
            <label for="vndc_seo_desc">SEO Meta Description</label>
            <textarea id="vndc_seo_desc" name="vndc_seo_desc" rows="3" placeholder="Leave blank to generate dynamic snippet from content..."><?php echo esc_textarea( $desc ); ?></textarea>
            <span class="char-count"><span id="vndc_seo_desc_count">0</span> characters (Recommended: 120-160)</span>
        </p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var titleInput = document.getElementById('vndc_seo_title');
                var descInput = document.getElementById('vndc_seo_desc');
                var titleCount = document.getElementById('vndc_seo_title_count');
                var descCount = document.getElementById('vndc_seo_desc_count');
                
                function updateCount() {
                    if(titleInput && titleCount) titleCount.textContent = titleInput.value.length;
                    if(descInput && descCount) descCount.textContent = descInput.value.length;
                }
                
                if(titleInput) titleInput.addEventListener('input', updateCount);
                if(descInput) descInput.addEventListener('input', updateCount);
                updateCount();
            });
        </script>
    </div>
    <?php
}

add_action( 'save_post', 'vndc_optima_seo_save_meta' );
function vndc_optima_seo_save_meta( $post_id ) {
    if ( ! isset( $_POST['vndc_seo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['vndc_seo_meta_nonce'], 'vndc_seo_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    if ( isset( $_POST['vndc_seo_title'] ) ) {
        update_post_meta( $post_id, '_vndc_seo_title', sanitize_text_field( $_POST['vndc_seo_title'] ) );
    }
    if ( isset( $_POST['vndc_seo_desc'] ) ) {
        update_post_meta( $post_id, '_vndc_seo_desc', sanitize_textarea_field( $_POST['vndc_seo_desc'] ) );
    }
}

// 2. Custom Title Tag Filter
add_filter( 'document_title_parts', 'vndc_optima_seo_custom_title', 999 );
function vndc_optima_seo_custom_title( $title_parts ) {
    $settings = vndc_optima_get_settings();
    if ( empty( $settings['seo_module'] ) ) {
        return $title_parts;
    }
    
    if ( is_singular() ) {
        $custom_title = get_post_meta( get_the_ID(), '_vndc_seo_title', true );
        if ( ! empty( $custom_title ) ) {
            $title_parts['title'] = $custom_title;
        }
    }
    return $title_parts;
}

// 3. Inject Meta Tags in Header
add_action( 'wp_head', 'vndc_optima_seo_meta_tags', 1 );
function vndc_optima_seo_meta_tags() {
    $settings = vndc_optima_get_settings();
    if ( empty( $settings['seo_module'] ) ) {
        return;
    }
    
    $desc = '';
    $title = '';
    $url = '';
    $image = '';
    
    if ( is_singular() ) {
        $post_id = get_the_ID();
        $desc = get_post_meta( $post_id, '_vndc_seo_desc', true );
        if ( empty( $desc ) ) {
            $post = get_post( $post_id );
            if ( ! empty( $post->post_excerpt ) ) {
                $desc = $post->post_excerpt;
            } else {
                $desc = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
                $desc = mb_strimwidth( $desc, 0, 160, '...' );
            }
        }
        
        $custom_title = get_post_meta( $post_id, '_vndc_seo_title', true );
        $title = ! empty( $custom_title ) ? $custom_title : get_the_title();
        $url = get_permalink();
        $image_id = get_post_thumbnail_id( $post_id );
        if ( $image_id ) {
            $image = wp_get_attachment_image_url( $image_id, 'large' );
        }
    } elseif ( is_front_page() || is_home() ) {
        $title = get_bloginfo( 'name' ) . ' - ' . get_bloginfo( 'description' );
        $desc = get_bloginfo( 'description' );
        $url = home_url( '/' );
    }
    
    $desc = sanitize_text_field( $desc );
    $desc = str_replace( array( "\r", "\n", '"' ), '', $desc );
    
    if ( ! empty( $desc ) ) {
        echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
    }
    
    // Inject Open Graph tags for social/WhatsApp
    if ( is_singular() ) {
        echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
        if ( ! empty( $desc ) ) {
            echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
        if ( ! empty( $image ) ) {
            echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url( $image ) . '" />' . "\n";
        }
    }
}

// 4. WooCommerce Structured Data (JSON-LD Product Schema)
add_action( 'wp_head', 'vndc_optima_seo_woocommerce_schema', 10 );
function vndc_optima_seo_woocommerce_schema() {
    $settings = vndc_optima_get_settings();
    if ( empty( $settings['seo_module'] ) ) {
        return;
    }
    
    if ( ! is_product() || ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) {
        return;
    }
    
    $currency = get_woocommerce_currency();
    $price = $product->get_price();
    $availability = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    
    $schema = array(
        '@context' => 'https://schema.org/',
        '@type' => 'Product',
        'name' => $product->get_name(),
        'image' => wp_get_attachment_image_url( $product->get_image_id(), 'full' ),
        'description' => wp_strip_all_tags( $product->get_description() ? $product->get_description() : $product->get_short_description() ),
        'sku' => $product->get_sku(),
        'offers' => array(
            '@type' => 'Offer',
            'url' => get_permalink( $product->get_id() ),
            'priceCurrency' => $currency,
            'price' => $price ? $price : '0',
            'priceValidUntil' => date( 'Y-12-31', strtotime( '+1 year' ) ),
            'availability' => $availability,
            'itemCondition' => 'https://schema.org/NewCondition'
        )
    );
    
    $schema['brand'] = array(
        '@type' => 'Brand',
        'name' => get_bloginfo( 'name' )
    );
    
    echo '<script type="application/ld+json">' . json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}

// 5. Dynamic XML Sitemap Generator
add_action( 'init', 'vndc_optima_sitemap_rewrite_rule' );
function vndc_optima_sitemap_rewrite_rule() {
    add_rewrite_rule( '^sitemap\.xml$', 'index.php?vndc_sitemap=1', 'top' );
}

add_filter( 'query_vars', 'vndc_optima_sitemap_query_vars' );
function vndc_optima_sitemap_query_vars( $vars ) {
    $vars[] = 'vndc_sitemap';
    return $vars;
}

add_action( 'template_redirect', 'vndc_optima_sitemap_generator' );
function vndc_optima_sitemap_generator() {
    if ( get_query_var( 'vndc_sitemap' ) ) {
        $settings = vndc_optima_get_settings();
        if ( empty( $settings['seo_module'] ) ) {
            return;
        }
        
        header( 'Content-Type: application/xml; charset=utf-8' );
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Front page
        echo "  <url>\n";
        echo "    <loc>" . esc_url( home_url( '/' ) ) . "</loc>\n";
        echo "    <changefreq>daily</changefreq>\n";
        echo "    <priority>1.0</priority>\n";
        echo "  </url>\n";
        
        // Custom query for posts, pages, and products
        $query = new WP_Query( array(
            'post_type' => array( 'post', 'page', 'product' ),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ) );
        
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                if ( $post_id == get_option( 'page_on_front' ) ) {
                    continue;
                }
                
                $url = get_permalink( $post_id );
                $post_type = get_post_type( $post_id );
                $priority = '0.6';
                if ( $post_type === 'product' ) {
                    $priority = '0.8';
                } elseif ( $post_type === 'page' ) {
                    $priority = '0.7';
                }
                
                $post_date = get_the_modified_date( 'c', $post_id );
                
                echo "  <url>\n";
                echo "    <loc>" . esc_url( $url ) . "</loc>\n";
                if ( $post_date ) {
                    echo "    <lastmod>" . esc_html( $post_date ) . "</lastmod>\n";
                }
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>" . esc_html( $priority ) . "</priority>\n";
                echo "  </url>\n";
            }
        }
        
        echo '</urlset>' . "\n";
        exit;
    }
}

// 6. Slug Change Detector & Auto-Redirect 301
add_action( 'post_updated', 'vndc_optima_detect_slug_change', 10, 3 );
function vndc_optima_detect_slug_change( $post_id, $post_after, $post_before ) {
    $settings = vndc_optima_get_settings();
    if ( empty( $settings['seo_module'] ) ) {
        return;
    }
    
    if ( ! in_array( $post_after->post_type, array( 'post', 'page', 'product' ) ) ) {
        return;
    }
    
    if ( $post_after->post_status !== 'publish' || $post_before->post_status !== 'publish' ) {
        return;
    }
    
    if ( $post_after->post_name !== $post_before->post_name ) {
        $old_slugs = get_post_meta( $post_id, '_vndc_old_slugs', true );
        if ( ! is_array( $old_slugs ) ) {
            $old_slugs = array();
        }
        
        if ( ! in_array( $post_before->post_name, $old_slugs ) ) {
            $old_slugs[] = $post_before->post_name;
            update_post_meta( $post_id, '_vndc_old_slugs', $old_slugs );
        }
    }
}

add_action( 'template_redirect', 'vndc_optima_handle_old_slug_redirect' );
function vndc_optima_handle_old_slug_redirect() {
    $settings = vndc_optima_get_settings();
    if ( empty( $settings['seo_module'] ) ) {
        return;
    }
    
    if ( is_404() ) {
        global $wpdb;
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = wp_parse_url( $request_uri, PHP_URL_PATH );
        if ( ! $path ) {
            return;
        }
        
        $slug = basename( $path );
        if ( empty( $slug ) ) {
            return;
        }
        
        // Query the post meta directly
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_vndc_old_slugs' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like( $slug ) . '%'
        ) );
        
        if ( $results ) {
            foreach ( $results as $row ) {
                $old_slugs = get_post_meta( $row->post_id, '_vndc_old_slugs', true );
                if ( is_array( $old_slugs ) && in_array( $slug, $old_slugs ) ) {
                    wp_redirect( get_permalink( $row->post_id ), 301 );
                    exit;
                }
            }
        }
    }
}

