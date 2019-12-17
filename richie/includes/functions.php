<?php
/**
 * Richie helper functions
 *
 * @package richie
 * @subpackage richie/includes
 */

/**
 * Create rewrite rule for maggio redirects
 *
 * Optionally flush rewrite rules (NOTE: that is an expensive operation, only do when absolute required)
 *
 * @since 1.0.0
 * @param boolean $flush Optional. Flushes rewrite rules if set. Default false.
 *
 * @return void
 */
function richie_create_maggio_rewrite_rules( $flush = false ) {
    add_rewrite_tag( '%maggio_redirect%', '([0-9a-fA-F-]+)' );
    add_rewrite_rule( '^maggio-redirect/([0-9a-fA-F-]+)/?$', 'index.php?maggio_redirect=$matches[1]', 'top' );

    if ( $flush ) {
        flush_rewrite_rules();
    }
}

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
        function( $a, $b ) {
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
    $mapper = function( $param ) {
        return $param['key'] . '=' . $param['value'];
    };
    $pairs  = array_map( $mapper, $sorted );
    return implode( '&', $pairs );
}

/**
 * Generate maggio authentication url signature hash
 *
 * @since 1.0.0
 *
 * @param  string $secret         Authentication secret.
 * @param  string $issue_id       Issue UUIDv4 id.
 * @param  int    $timestamp      Unix timestamp.
 * @param  string $query_string   Optional. Query string to be included in hash.
 *
 * @return string   $hash           Calculated signature hash to be included in signin url
 */
function richie_generate_signature_hash( $secret, $issue_id, $timestamp, $query_string = '' ) {
    if ( ! isset( $secret ) ) {
        return new WP_Error( 'secret', __( 'Missing secret', 'richie') );
    }

    if ( ! wp_is_uuid( $issue_id ) ) {
        return new WP_Error( 'uuid', __( 'Invalid issue uuid', 'richie' ) );
    }

    if ( ! is_int( $timestamp ) ) {
        return new WP_Error( 'timestamp', __( 'Invalid timestamp, it must be an integer', 'richie' ) );
    }

    $signature_data = $issue_id . "\n" . $timestamp . "\n" . $query_string;
    $hash           = hash_hmac( 'sha256', $signature_data, $secret ); // Returns hex data.

    return $hash;
}


/**
 * Check if user has maggio access
 *
 * Compares user's membership level to the required. Also expects that current user is authenticated.
 *
 * @since 1.0.0
 *
 * @global $current_user
 *
 * @param  int $required_pmpro_level Optional. If positive integer, compare that to the user's membership level. Default 0.
 *
 * @return boolean
 */
function richie_has_maggio_access( $required_pmpro_level = 0 ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    if ( function_exists( 'pmpro_hasMembershipLevel' ) && $required_pmpro_level > 0 ) {
        $membership_level = pmpro_getMembershipLevelForUser();
        if (
            empty( $membership_level ) ||
            $membership_level->ID != $required_pmpro_level
        ) {
            // PMPro installed, required level configured and user doesn't have that level -> no access.
            return false;
        }
    }

    return true;
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

    //force the protocols to match if needed
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
 * Detect if url points to an image based on extension
 *
 * @param string $image_url Url to the image.
 * @return boolean
 */
function richie_is_image_url( $image_url ) {
    if ( ! isset( $image_url ) ) {
        return false;
    }
    $allowed_extensions = array( 'png', 'jpg', 'gif' );
    $path = wp_parse_url( $image_url, PHP_URL_PATH );
    if ( $path ) {
        $filetype = wp_check_filetype( $path );
        $extension = $filetype['ext'];
        if ( in_array( $extension, $allowed_extensions ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Get assets from global $wp_scripts and $wp_styles
 *
 * @return array
 */
function richie_get_article_assets() {
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-app-asset.php';

    // Get all scripts.
    global $wp_scripts, $wp_styles;
    $article_assets = array();
    foreach ( $wp_scripts->do_items() as $script_name ) {
        $script = $wp_scripts->registered[ $script_name ];
        $remote_url = $script->src;
        if ( ( substr( $remote_url, -3 ) === '.js' ) && ! strpos( $remote_url, 'wp-admin' ) ) {
            $article_assets[] = new Richie_App_Asset( $script, RICHIE_ARTICLE_ASSET_URL_PREFIX );
        }
    }
    // Print all loaded Styles (CSS).
    foreach ( $wp_styles->do_items() as $style_name ) {
        $style = $wp_styles->registered[ $style_name ];
        $remote_url = $style->src;
        if ( ( substr( $remote_url, -4 ) === '.css' ) && ! strpos( $remote_url, 'wp-admin' ) ) {
            $article_assets[] = new Richie_App_Asset( $style, RICHIE_ARTICLE_ASSET_URL_PREFIX );
        }
    }

    return $article_assets;
}

/**
 * Check if paid-memberships-pro plugin is active.
 *
 * @return boolean
 */
function richie_is_pmpro_active() {
    $available_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    if ( in_array( 'paid-memberships-pro/paid-memberships-pro.php',  $available_plugins ) ) {
        return true;
    }

    return false;
}


/**
 * Normalize path
 */
function richie_normalize_path( $path ) {
    $normalized_path = wp_normalize_path( $path );
    $path_segments = explode ('/', $normalized_path );
    $stack = array();
    foreach ( $path_segments as $seg ) {
        if ( $seg === '..' ) {
            // Ignore this and remove last one
            array_pop($stack);
            continue;
        }

        if ( $seg === '.' ) {
            // Ignore this segment
            continue;
        }

        $stack[] = $seg;
    }

    return implode('/', $stack);
}

function richie_encode_url_path( $url ) {
    $encoded = preg_replace_callback('#://([^/]+)/([^?]+)#', function ($match) {
        return '://' . $match[1] . '/' . join('/', array_map('rawurlencode', explode('/', $match[2])));
    }, $url);

    return $encoded;
}

