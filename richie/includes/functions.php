<?php
/**
 * Richie helper functions
 *
 * @package richie
 * @subpackage richie/includes
 */


/**
 * Sort array by key AND value
 *
 * Array is sorted by key first and if multiple equal keys, by value.
 * Note: sorting is done in place, modifying the given argument
 *
 * @since 1.0.0
 *
 * @param  array {
 *      @type string $key
 *      @type string $value
 * } $array Array to be sorted.
 *
 * @return array Sorted array
 */
function richie_key_value_sort( $array ) {
    usort(
        $array,
        function ( $a, $b ) {
            if ( $a['key'] === $b['key'] ) {
                return strcmp( $a['value'], $b['value'] );
            }
            return strcmp( $a['key'], $b['key'] );
        }
    );

    return $array;
}


/**
 * Build sorted query string
 *
 * @param  array {
 *      @type string $key
 *      @type string $value
 * } $params  key-value pairs
 *
 * @return string Query string key=value&key2=value2&... sorted by key and value
 */
function richie_build_query( $params ) {
    $sorted = richie_key_value_sort( $params );
    $mapper = function ( $param ) {
        return $param['key'] . '=' . $param['value'];
    };
    $pairs  = array_map( $mapper, $sorted );
    return implode( '&', $pairs );
}

/**
 * Modify original WordPress function with custom query (adding LIMIT 1).
 * This should make function to perform faster.
 *
 * @see https://core.trac.wordpress.org/ticket/41281
 *
 * @param string $url   Attachment url.
 * @return int post id
 */
function richie_attachment_url_to_postid( $url ) {
    global $wpdb;

    $dir  = wp_get_upload_dir();
    $path = $url;

    $site_url   = wp_parse_url( $dir['url'] );
    $image_path = wp_parse_url( $path );

    // force the protocols to match if needed
    if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
        $path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
    }

    if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
        $path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
    }

    $sql     = $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
        $path
    );
    $post_id = $wpdb->get_var( $sql );

    /**
     * Filters an attachment id found by URL.
     *
     * @since 4.2.0
     *
     * @param int|null $post_id The post_id (if any) found by the function.
     * @param string   $url     The URL being looked up.
     */
    return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
}

/**
 * Makes link absolute.
 * If starting with http(s), return as unchanged. If missing protocol, add https.
 * Otherwise prepend site url.
 *
 * @param string $url Url to modify.
 * @return string Absolute url
 */
function richie_make_link_absolute( $url ) {
    if ( substr( $url, 0, 4 ) === 'http' ) {
        return $url;
    } elseif ( substr( $url, 0, 2 ) === '//' ) {
        return 'https:' . $url;
    } else {
        return get_site_url( null, $url );
    }
}

/**
 * Build request headers for calls made by the Richie News plugin.
 *
 * @param array $headers Existing request headers.
 * @return array Headers with Richie plugin version metadata.
 */
function richie_get_server_request_headers( $headers = array() ) {
    $version = defined( 'Richie_VERSION' ) ? Richie_VERSION : 'unknown';

    return array_merge(
        array(
            'User-Agent'              => sprintf( 'Richie News/%s; WordPress/%s', $version, get_bloginfo( 'version' ) ),
            'X-Richie-Plugin'         => 'richie',
            'X-Richie-Plugin-Version' => $version,
        ),
        $headers
    );
}

function richie_make_local_name( $url ) {
    // If scheme not included, prepend it
    if ( ! preg_match( '#^http(s)?://#', $url ) ) {
        $url = set_url_scheme( $url );
    }

    // remove version string
    $url = remove_query_arg( 'ver', $url );

    // remove local host
    $url = str_replace( get_site_url(), '', $url );

    $url_parts = wp_parse_url( $url );

    // get domain
    $domain = isset( $url_parts['host'] ) ? $url_parts['host'] : '';

    // get path
    $path = isset( $url_parts['path'] ) ? rtrim( $url_parts['path'], '/' ) : '';

    // get query
    $query = isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '';

    // create url
    $local_name = ltrim( $domain . $path . $query, '/' );
    return $local_name;
}

/**
 * Get image id for image url
 *
 * @param string $image_url Url to the image.
 * @return int
 */
function richie_get_image_id( $image_url ) {
	global $wpdb;
    $attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s;", $image_url ) );
    if ( ! empty( $attachment ) ) {
        return $attachment[0];
    }
    return false;
}

/**
 * Detect if url points to an image based on MIME type or extension.
 *
 * @param string $image_url Url to the image.
 * @return boolean
 */
function richie_is_image_url( $image_url ) {
    if ( ! isset( $image_url ) ) {
        return false;
    }

    $path = wp_parse_url( $image_url, PHP_URL_PATH );

    if ( $path ) {
        $filetype = wp_check_filetype( $path );

        // MIME type check is most reliable (covers webp, avif, svg etc).
        if ( ! empty( $filetype['type'] ) && 0 === strpos( $filetype['type'], 'image/' ) ) {
            return true;
        }

        // Fallback: explicit list for formats WordPress may not map via MIME.
        if ( in_array( $filetype['ext'], array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg' ), true ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Convert a local filesystem path under ABSPATH to a site URL.
 *
 * @param string $local_path Absolute filesystem path.
 * @return string|false URL on success, false when path is outside ABSPATH.
 */
function richie_local_path_to_url( $local_path ) {
    $normalized      = wp_normalize_path( $local_path );
    $normalized_base = trailingslashit( wp_normalize_path( ABSPATH ) );

    if ( 0 !== strpos( $normalized, $normalized_base ) ) {
        return false;
    }

    $relative = ltrim( substr( $normalized, strlen( $normalized_base ) ), '/' );

    if ( '' === $relative ) {
        return false;
    }

    return get_site_url( null, '/' . $relative );
}

/**
 * Return the best available URL for a registered WP dependency.
 *
 * Gutenberg block styles sometimes register with src=false and store the
 * local filesystem path in extra['path'] instead of a URL.
 *
 * @param _WP_Dependency $dependency Registered script or style.
 * @return string|false URL string, or false when not determinable.
 */
function richie_get_registered_asset_url( $dependency ) {
    if ( ! empty( $dependency->src ) ) {
        return $dependency->src;
    }

    if ( ! empty( $dependency->extra['path'] ) ) {
        return richie_local_path_to_url( $dependency->extra['path'] );
    }

    return false;
}

/**
 * Collect Richie_App_Asset objects for the given script and style handles.
 *
 * Only handles that are registered and whose URL ends in .js/.css (and is not
 * a wp-admin asset) are included.
 *
 * @param string[] $script_handles Script handles to collect.
 * @param string[] $style_handles  Style handles to collect.
 * @param string   $local_prefix   Prefix for local_name (e.g. 'app-assets/').
 * @return Richie_App_Asset[]
 */
function richie_collect_registered_assets( $script_handles, $style_handles, $local_prefix ) {
    require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-app-asset.php';

    global $wp_scripts, $wp_styles;

    $assets = array();

    foreach ( $script_handles as $handle ) {
        if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
            continue;
        }
        $script     = $wp_scripts->registered[ $handle ];
        $remote_url = richie_get_registered_asset_url( $script );

        if ( is_string( $remote_url ) && '.js' === substr( $remote_url, -3 ) && false === strpos( $remote_url, 'wp-admin' ) ) {
            $assets[] = new Richie_App_Asset( $script, $local_prefix );
        }
    }

    foreach ( $style_handles as $handle ) {
        if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
            continue;
        }
        $style      = $wp_styles->registered[ $handle ];
        $remote_url = richie_get_registered_asset_url( $style );

        if ( ! is_string( $remote_url ) || false !== strpos( $remote_url, 'wp-admin' ) ) {
            continue;
        }

        if ( '.css' === substr( $remote_url, -4 ) || false !== strpos( $remote_url, '.css?' ) ) {
            // For path-backed styles ($style->src is false/empty), Richie_App_Asset constructor
            // reads $dependency->src directly and would produce a bogus asset. Override src
            // with the resolved URL before constructing the asset, then restore it.
            $original_src = $style->src;
            if ( empty( $style->src ) ) {
                $style->src = $remote_url;
            }
            $asset      = new Richie_App_Asset( $style, $local_prefix );
            $style->src = $original_src;
            // Skip degenerate assets where the URL had no path component.
            if ( $asset->local_name !== $local_prefix && '' !== $asset->local_name ) {
                $assets[] = $asset;
            }
        }
    }

    return $assets;
}

/**
 * Get assets from script and style handles already emitted during the current request.
 *
 * Uses ->done (handles already output) instead of do_items() which has side
 * effects and misses previously emitted handles.
 *
 * @param string $local_prefix Prefix for local_name (e.g. 'app-assets/').
 * @return Richie_App_Asset[]
 */
function richie_get_emitted_assets( $local_prefix = 'app-assets/' ) {
    global $wp_scripts, $wp_styles;

    $script_handles = isset( $wp_scripts->done ) ? $wp_scripts->done : array();
    $style_handles  = isset( $wp_styles->done ) ? $wp_styles->done : array();

    return richie_collect_registered_assets( $script_handles, $style_handles, $local_prefix );
}

/**
 * Get assets from global $wp_scripts and $wp_styles (article-level assets).
 *
 * @return Richie_App_Asset[]
 */
function richie_get_article_assets() {
    return richie_get_emitted_assets( '' );
}

/**
 * Check whether a URL is same-origin (matches the current site host, or is root-relative).
 *
 * @param string $url URL to check.
 * @return bool
 */
function richie_is_same_origin_url( $url ) {
    if ( ! is_string( $url ) || '' === $url ) {
        return false;
    }

    $parsed = wp_parse_url( $url );

    if ( false === $parsed ) {
        return false;
    }

    // Root-relative or relative URL — same origin by definition.
    if ( empty( $parsed['host'] ) ) {
        return true;
    }

    return wp_parse_url( get_site_url(), PHP_URL_HOST ) === $parsed['host'];
}

/**
 * Resolve a URL that may be relative to the given base URL into an absolute URL.
 *
 * @param string $base_url     Absolute URL of the document containing the reference.
 * @param string $relative_url URL (may be relative, root-relative, absolute, etc.).
 * @return string Absolute URL, or empty string for unsupported schemes.
 */
function richie_resolve_url( $base_url, $relative_url ) {
    $relative_url = trim( $relative_url );

    if ( '' === $relative_url ) {
        return '';
    }

    // Non-http schemes that are not useful to include as assets.
    if ( preg_match( '#^(?:data:|javascript:|mailto:|tel:|about:)#i', $relative_url ) ) {
        return '';
    }

    // Already absolute.
    if ( preg_match( '#^https?://#i', $relative_url ) ) {
        return $relative_url;
    }

    // Protocol-relative.
    if ( 0 === strpos( $relative_url, '//' ) ) {
        return set_url_scheme( $relative_url );
    }

    // Root-relative.
    if ( 0 === strpos( $relative_url, '/' ) ) {
        return get_site_url( null, $relative_url );
    }

    // True relative: resolve against the directory of the base URL.
    $base_parts = wp_parse_url( $base_url );

    if ( false === $base_parts || empty( $base_parts['host'] ) ) {
        // Can't resolve without a proper base; fall back to site root.
        return get_site_url( null, '/' . $relative_url );
    }

    $base_path = isset( $base_parts['path'] ) ? $base_parts['path'] : '/';
    $base_dir  = trailingslashit( dirname( $base_path ) );
    $path      = richie_normalize_path( $base_dir . $relative_url );

    $scheme = isset( $base_parts['scheme'] ) ? $base_parts['scheme'] : 'https';
    $host   = $base_parts['host'];
    $port   = isset( $base_parts['port'] ) ? ':' . $base_parts['port'] : '';

    return $scheme . '://' . $host . $port . '/' . ltrim( $path, '/' );
}

/**
 * Return true if the URL's file extension is a recognised static web asset type.
 *
 * Used to guard asset-discovery paths against accidentally including non-asset
 * files (e.g. /wp-config.php) that happen to exist on disk.
 *
 * @param string $url URL to check.
 * @return bool
 */
function richie_is_allowed_asset_url( $url ) {
    static $allowed = array( 'css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico' );
    $ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
    return in_array( $ext, $allowed, true );
}

/**
 * Convert a same-origin URL to a local filesystem path.
 *
 * @param string $url URL to resolve.
 * @return string|false Absolute filesystem path when it exists, false otherwise.
 */
function richie_url_to_local_path( $url ) {
    if ( ! richie_is_same_origin_url( $url ) ) {
        return false;
    }

    $path = wp_parse_url( $url, PHP_URL_PATH );

    if ( ! is_string( $path ) || '' === $path ) {
        return false;
    }

    // On subdirectory installs (e.g. example.com/news), WordPress is mounted at a
    // path prefix. Strip that prefix before mapping to the filesystem so that
    // /news/wp-content/... resolves to <ABSPATH>/wp-content/... correctly.
    $site_path = wp_parse_url( home_url(), PHP_URL_PATH );
    if ( is_string( $site_path ) && '' !== $site_path && '/' !== $site_path ) {
        $site_path = trailingslashit( $site_path );
        if ( 0 === strpos( $path, $site_path ) ) {
            $path = '/' . substr( $path, strlen( $site_path ) );
        }
    }

    $local_path = ABSPATH . ltrim( $path, '/' );

    return file_exists( $local_path ) ? $local_path : false;
}

/**
 * Parse a CSS document and return all URLs it references.
 *
 * Covers @import rules and url() tokens (background images, fonts, etc.).
 * All URLs are resolved to absolute form using $base_url.
 *
 * @param string $css      CSS content.
 * @param string $base_url Absolute URL of the CSS file, for resolving relative references.
 * @return string[] Unique list of absolute URLs.
 */
function richie_parse_css_urls( $css, $base_url ) {
    $urls = array();

    if ( '' === trim( $css ) ) {
        return $urls;
    }

    // @import "path" or @import url("path") or @import url(path).
    if ( preg_match_all( '/@import\s+(?:url\(\s*)?[\'"]?([^\'"\)\s;]+)[\'"]?\s*\)?/i', $css, $matches ) ) {
        foreach ( $matches[1] as $raw ) {
            $resolved = richie_resolve_url( $base_url, $raw );
            if ( '' !== $resolved ) {
                $urls[] = $resolved;
            }
        }
    }

    // url("path"), url('path'), url(path) — catches @font-face src, background-image, etc.
    if ( preg_match_all( '/url\(\s*[\'"]?([^\'"\)\s]+)[\'"]?\s*\)/i', $css, $matches ) ) {
        foreach ( $matches[1] as $raw ) {
            $resolved = richie_resolve_url( $base_url, $raw );
            if ( '' !== $resolved ) {
                $urls[] = $resolved;
            }
        }
    }

    return array_values( array_unique( $urls ) );
}

/**
 * Build a relative link (path + query) from an absolute or protocol-relative URL.
 *
 * Unlike wp_make_link_relative() this preserves the query string, which is
 * needed to match versioned asset URLs like style.css?ver=1.2 in HTML content.
 *
 * @param string $url Absolute or protocol-relative URL.
 * @return string Relative URL (path + optional query string).
 */
function richie_make_link_relative_with_query( $url ) {
    $path = wp_parse_url( $url, PHP_URL_PATH );

    if ( ! is_string( $path ) || '' === $path ) {
        return wp_make_link_relative( $url );
    }

    $query = wp_parse_url( $url, PHP_URL_QUERY );

    return $path . ( is_string( $query ) && '' !== $query ? '?' . $query : '' );
}

/**
 * Discover CSS sub-resource dependencies for the given list of app assets.
 *
 * Reads each .css asset from the local filesystem and parses it for @import
 * rules and url() references (fonts, background images, etc.). Processes
 * nested imports recursively (BFS) up to same-origin files that exist on disk.
 *
 * The Richie app does not crawl CSS/JS for sub-resources, so all dependencies
 * must be explicitly listed in the asset feed.
 *
 * @param Richie_App_Asset[] $assets       Already-collected asset list.
 * @param string             $local_prefix Local-name prefix (e.g. 'app-assets/').
 * @return Richie_App_Asset[] New dependency assets (does not include original $assets).
 */
function richie_discover_css_dependencies( $assets, $local_prefix ) {
    require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-app-asset.php';

    $queue = array(); // CSS URLs to process.
    $seen  = array(); // Prevent infinite loops.
    $found = array(); // local_name => Richie_App_Asset.

    // Seed the queue with CSS assets.
    foreach ( $assets as $asset ) {
        if ( isset( $asset->remote_url ) && false !== strpos( $asset->remote_url, '.css' ) ) {
            $queue[] = $asset->remote_url;
        }
    }

    while ( ! empty( $queue ) ) {
        $css_url = array_shift( $queue );
        // Normalise key: strip version query arg so the same file isn't processed twice.
        $cache_key = remove_query_arg( 'ver', $css_url );

        if ( isset( $seen[ $cache_key ] ) ) {
            continue;
        }

        $seen[ $cache_key ] = true;
        $local_path         = richie_url_to_local_path( $css_url );

        if ( false === $local_path || ! is_readable( $local_path ) ) {
            continue;
        }

        $css = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( false === $css ) {
            continue;
        }

        foreach ( richie_parse_css_urls( $css, $css_url ) as $dep_url ) {
            if ( ! richie_is_same_origin_url( $dep_url ) ) {
                continue;
            }

            // Only include files that actually exist on disk.
            if ( false === richie_url_to_local_path( $dep_url ) ) {
                continue;
            }

            // Only include recognised static asset types.
            if ( ! richie_is_allowed_asset_url( $dep_url ) ) {
                continue;
            }

            $dep                             = new stdClass();
            $dep->src                        = $dep_url;
            $dep->ver                        = null;
            $dep_asset                       = new Richie_App_Asset( $dep, $local_prefix );
            $found[ $dep_asset->local_name ] = $dep_asset;

            // Queue CSS files for recursive processing.
            if ( false !== strpos( $dep_url, '.css' ) ) {
                $queue[] = $dep_url;
            }
        }
    }

    return array_values( $found );
}


/**
 * Normalize path
 */
function richie_normalize_path( $path ) {
    $normalized_path = wp_normalize_path( $path );
    $path_segments   = explode( '/', $normalized_path );
    $stack           = array();
    foreach ( $path_segments as $seg ) {
        if ( $seg === '..' ) {
            // Ignore this and remove last one
            array_pop( $stack );
            continue;
        }

        if ( $seg === '.' ) {
            // Ignore this segment
            continue;
        }

        $stack[] = $seg;
    }

    return implode( '/', $stack );
}

function richie_encode_url_path( $url ) {
    $encoded = preg_replace_callback(
        '#://([^/]+)/([^?]+)#',
        function ( $match ) {
            return '://' . $match[1] . '/' . join( '/', array_map( 'rawurlencode', explode( '/', $match[2] ) ) );
        },
        $url
    );

    return $encoded;
}

function richie_force_url_scheme( $url ) {
    // this should handle also protocol relative urls
    $parsed_url = wp_parse_url( $url );

    if ( $parsed_url !== false ) {
        if ( empty( $parsed_url['scheme'] ) ) {
            if ( isset( $parsed_url['host'] ) ) {
                // absolute url without protocol, set it based on site protocol
                $url = set_url_scheme( $url );
            }
        }
    }
    return $url;
}

/**
 * Parse a srcset attribute value and return the URL of the best (largest) candidate.
 *
 * Handles width descriptors (300w), pixel-density descriptors (2x), and bare URLs.
 * Returns false if the string is empty or no valid entry is found.
 *
 * @param string $srcset_value The srcset attribute value.
 * @return string|false Best candidate URL, or false on failure.
 */
function richie_parse_srcset_best_url( $srcset_value ) {
    $srcset_value = trim( $srcset_value );
    if ( '' === $srcset_value ) {
        return false;
    }

    // Split on commas. Per the HTML spec, URLs in srcset must percent-encode commas
    // (%2C), so plain comma splitting is safe. Each entry is trimmed; empty entries
    // (e.g. from a trailing comma) are discarded.
    $entries = array_values( array_filter( array_map( 'trim', explode( ',', $srcset_value ) ) ) );
    if ( empty( $entries ) ) {
        return false;
    }

    $best_url     = false;
    $best_width   = -1;
    $best_density = -1.0;
    $has_width    = false;
    $has_density  = false;
    $last_url     = false;

    foreach ( $entries as $entry ) {
        $parts = preg_split( '/\s+/', trim( $entry ), 2 );
        if ( empty( $parts[0] ) ) {
            continue;
        }
        $url        = $parts[0];
        $descriptor = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        $last_url   = $url;

        if ( preg_match( '/^(\d+)w$/i', $descriptor, $m ) ) {
            $has_width = true;
            if ( (int) $m[1] > $best_width ) {
                $best_width = (int) $m[1];
                $best_url   = $url;
            }
        } elseif ( preg_match( '/^([\d.]+)x$/i', $descriptor, $m ) ) {
            $has_density = true;
            if ( (float) $m[1] > $best_density ) {
                $best_density = (float) $m[1];
                $best_url     = $url;
            }
        }
    }

    // If we found width or density descriptors, return the winner.
    if ( $has_width || $has_density ) {
        return $best_url;
    }

    // No descriptors — return the last entry (largest is conventionally last).
    return $last_url;
}

/**
 * Resolve the best available image URL for an <img> src.
 *
 * Strategy (in priority order):
 * 1. If same-origin: try attachment lookup to get the full-size URL from the media library.
 * 2. If a srcset value is provided: parse it and pick the largest candidate.
 * 3. Fall back to the original $src_url.
 *
 * Results of attachment lookups are memoized in $attachment_cache (keyed by base URL
 * with size suffix stripped, value is attachment ID or 0 if not found).
 *
 * @param string $src_url         The current image src URL.
 * @param string $srcset_value    Optional srcset attribute value for fallback.
 * @param array  $attachment_cache By-reference cache array to avoid duplicate DB queries.
 * @return string Best URL to use.
 */
function richie_resolve_best_image_url( $src_url, $srcset_value = '', &$attachment_cache = array() ) {
    if ( '' === $src_url ) {
        // No src — try srcset directly.
        $from_srcset = richie_parse_srcset_best_url( $srcset_value );
        return $from_srcset ? $from_srcset : '';
    }

    $site_host = wp_parse_url( get_site_url(), PHP_URL_HOST );
    $src_host  = wp_parse_url( $src_url, PHP_URL_HOST );

    // Only attempt attachment lookup for same-origin URLs.
    if ( $src_host && $src_host !== $site_host ) {
        // External URL — try srcset fallback, otherwise keep src.
        $from_srcset = richie_parse_srcset_best_url( $srcset_value );
        return $from_srcset ? $from_srcset : $src_url;
    }

    // Strip size suffix (e.g. -300x200 before the extension) for the lookup key.
    $base_url  = preg_replace( '/(-\d+x\d+)(\.[a-z0-9]+)$/i', '$2', $src_url );
    $cache_key = $base_url;

    if ( ! array_key_exists( $cache_key, $attachment_cache ) ) {
        $attachment_cache[ $cache_key ] = (int) richie_attachment_url_to_postid( $base_url );
    }

    $attachment_id = $attachment_cache[ $cache_key ];

    if ( $attachment_id ) {
        $full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
        if ( $full_url ) {
            return richie_make_link_absolute( $full_url );
        }
    }

    // No attachment found — try srcset.
    $from_srcset = richie_parse_srcset_best_url( $srcset_value );
    return $from_srcset ? $from_srcset : $src_url;
}

/**
 * Build template file names for HTML templates.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return array
 */
function richie_get_html_template_names( $slug, $name = null ) {
    return richie_get_template_names( $slug, $name, '.html' );
}

/**
 * Validate that a resolved template path is within one of the allowed base directories.
 *
 * @param string $candidate Resolved template path.
 * @param array  $allowed_dirs Allowed base directories.
 * @return bool
 */
function richie_is_valid_template_path( $candidate, $allowed_dirs ) {
    $real = realpath( $candidate );
    if ( false === $real ) {
        return false;
    }
    foreach ( $allowed_dirs as $dir ) {
        $real_dir = realpath( $dir );
        if ( false !== $real_dir && 0 === strpos( $real, $real_dir . DIRECTORY_SEPARATOR ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Locate a template file in the given directories.
 *
 * @param array $template_names Filenames to look for, in priority order.
 * @param array $paths          Directories to search, in priority order.
 * @return string|null Absolute path to the first match, or null.
 */
function richie_locate_template_file( $template_names, $paths ) {
    foreach ( $template_names as $template_name ) {
        foreach ( $paths as $path ) {
            $candidate = $path . ltrim( $template_name, '/' );
            if ( file_exists( $candidate ) && richie_is_valid_template_path( $candidate, $paths ) ) {
                return $candidate;
            }
        }
    }

    return null;
}

/**
 * Get the standard theme directories for Richie templates.
 *
 * @return array
 */
function richie_get_theme_template_dirs() {
    return array(
        trailingslashit( get_stylesheet_directory() ) . 'richie/',
        trailingslashit( get_template_directory() ) . 'richie/',
    );
}

/**
 * Build template file names for a given extension.
 *
 * @param string $slug      Template slug.
 * @param string $name      Template variation name.
 * @param string $extension File extension including dot (e.g. '.html').
 * @return array
 */
function richie_get_template_names( $slug, $name = null, $extension = '.html' ) {
    $templates = array();
    if ( isset( $name ) && '' !== $name ) {
        $templates[] = $slug . '-' . $name . $extension;
    }
    $templates[] = $slug . $extension;

    return $templates;
}

/**
 * Locate a HTML template file in theme or plugin paths.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return string|null
 */
function richie_locate_html_template( $slug, $name = null ) {
    $templates = richie_get_template_names( $slug, $name, '.html' );
    $paths     = richie_get_theme_template_dirs();
    $paths[]   = trailingslashit( Richie_PLUGIN_DIR ) . 'templates/';
    $paths     = apply_filters( 'richie_html_template_paths', $paths, $slug, $name );

    return richie_locate_template_file( $templates, $paths );
}

/**
 * Locate a HTML template file in the active theme or parent theme only.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return string|null
 */
function richie_locate_theme_html_template( $slug, $name = null ) {
    return richie_locate_template_file(
        richie_get_template_names( $slug, $name, '.html' ),
        richie_get_theme_template_dirs()
    );
}

/**
 * Locate a PHP template file in the active theme or parent theme only.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return string|null
 */
function richie_locate_theme_php_template( $slug, $name = null ) {
    return richie_locate_template_file(
        richie_get_template_names( $slug, $name, '.php' ),
        richie_get_theme_template_dirs()
    );
}

/**
 * Render a block HTML template into a full HTML document string.
 *
 * @param string $template_path Absolute path to the HTML template.
 * @return string
 */
function richie_render_block_template_document( $template_path ) {
    $template_contents = file_get_contents( $template_path );

    if ( false === $template_contents ) {
        return '';
    }

    return richie_render_block_template_document_from_content( $template_contents );
}

/**
 * Render block template content into a full HTML document string.
 *
 * @param string $template_contents Block template markup.
 * @return string
 */
function richie_render_block_template_document_from_content( $template_contents ) {
    if ( empty( $template_contents ) ) {
        return '';
    }

    $rendered = do_blocks( $template_contents );

    ob_start();
    wp_head();
    $head_assets = ob_get_clean();

    ob_start();
    wp_footer();
    $footer_assets = ob_get_clean();

    $has_head = stripos( $rendered, '<head' ) !== false;
    $has_body = stripos( $rendered, '<body' ) !== false;

    if ( $has_head && $has_body ) {
        // Template is a full document — inject assets into existing tags.
        $rendered = preg_replace( '/<\/head>/i', $head_assets . "\n</head>", $rendered, 1 );
        $rendered = preg_replace( '/<\/body>/i', $footer_assets . "\n</body>", $rendered, 1 );
    } else {
        // Template is block markup only — wrap in a full document.
        $rendered = "<!doctype html>\n" .
            "<html>\n" .
            "<head>\n" . $head_assets . "\n</head>\n" .
            "<body>\n" . $rendered . "\n" . $footer_assets . "\n</body>\n" .
            "</html>\n";
    }

    return $rendered;
}

/**
 * Check whether block template rendering is enabled.
 *
 * @param array|null $options Plugin options array. If null, reads from database.
 * @return bool
 */
function richie_use_block_template( $options = null ) {
    if ( null === $options ) {
        $options = get_option( 'richie' );
    }
    return is_array( $options ) && ! empty( $options['use_block_template'] );
}

/**
 * Resolve which template should be used for rendering, without actually rendering.
 *
 * Returns a descriptor array with 'type' and additional keys depending on the type:
 *   - { type: 'block_path', path: string }  — HTML block template file to render with do_blocks().
 *   - { type: 'block_slug', slug: string }   — Site Editor template to render by slug.
 *   - { type: 'php', slug: string, name: string } — PHP template to load via Richie_Template_Loader.
 *
 * @param string     $slug    Template slug (e.g. 'richie-news').
 * @param string     $name    Template variation name (e.g. 'article').
 * @param array|null $options Plugin options array. If null, reads from database.
 * @return array Template descriptor.
 */
function richie_resolve_template( $slug, $name, $options = null ) {
    // 0. Explicit block template request — skip theme overrides.
    if ( 'block' === $name && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
        $block_slug = richie_get_block_template_slug();
        if ( richie_get_block_template_by_slug( $block_slug ) ) {
            return array(
				'type' => 'block_slug',
				'slug' => $block_slug,
            );
        }
    }

    // 1. Theme HTML template override.
    $theme_html = richie_locate_theme_html_template( $slug, $name );
    if ( $theme_html ) {
        return array(
			'type' => 'block_path',
			'path' => $theme_html,
        );
    }

    // 2. Theme PHP template override.
    $theme_php = richie_locate_theme_php_template( $slug, $name );
    if ( $theme_php ) {
        return array(
			'type' => 'php',
			'slug' => $slug,
			'name' => $name,
        );
    }

    // 3. Site Editor block template (block themes only).
    if ( richie_use_block_template( $options ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
        $block_slug = richie_get_block_template_slug();
        if ( richie_get_block_template_by_slug( $block_slug ) ) {
            return array(
				'type' => 'block_slug',
				'slug' => $block_slug,
            );
        }
    }

    // 4. Plugin HTML template fallback.
    $plugin_html = richie_locate_html_template( $slug, $name );
    if ( $plugin_html ) {
        return array(
			'type' => 'block_path',
			'path' => $plugin_html,
        );
    }

    // 5. Legacy PHP template fallback.
    return array(
		'type' => 'php',
		'slug' => $slug,
		'name' => $name,
    );
}

/**
 * Get the block template slug used for Richie articles.
 *
 * @return string
 */
function richie_get_block_template_slug() {
    return 'richie-article';
}

/**
 * Find a block template by slug, preferring custom templates.
 *
 * @param string $slug Template slug.
 * @return WP_Block_Template|null
 */
function richie_get_block_template_by_slug( $slug ) {
    if ( ! function_exists( 'get_block_templates' ) ) {
        return null;
    }

    $templates = get_block_templates(
        array(
            'slug__in' => array( $slug ),
        ),
        'wp_template'
    );

    if ( empty( $templates ) ) {
        return null;
    }

    $priority = array(
        'custom' => 0,
        'theme'  => 1,
        'plugin' => 2,
    );

    usort(
        $templates,
        function ( $a, $b ) use ( $priority ) {
            $a_priority = isset( $priority[ $a->source ] ) ? $priority[ $a->source ] : 99;
            $b_priority = isset( $priority[ $b->source ] ) ? $priority[ $b->source ] : 99;
            return $a_priority <=> $b_priority;
        }
    );

    return $templates[0];
}

/**
 * Render a block template by slug into a full HTML document.
 *
 * @param string $slug Template slug.
 * @return string
 */
function richie_render_block_template_by_slug( $slug ) {
    $template = richie_get_block_template_by_slug( $slug );
    if ( ! $template || empty( $template->content ) ) {
        return '';
    }

    return richie_render_block_template_document_from_content( $template->content );
}
