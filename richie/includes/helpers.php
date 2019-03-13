<?php

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
function richie_create_maggio_rewrite_rules($flush = false) {
    add_rewrite_tag('%maggio_redirect%', '([0-9a-fA-F-]+)');
    add_rewrite_rule('^maggio-redirect/([0-9a-fA-F-]+)/?$', 'index.php?maggio_redirect=$matches[1]', 'top');

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
 * @param  array $array {
 *      @type string $key
 *      @type string $value
 * }
 *
 * @return void
 */
function richie_key_value_sort( $array ) {
    usort($array, function($a, $b) {
        if ( $a['key'] === $b['key'] ) {
            return strcmp( $a['value'], $b['value'] );
        }
        return strcmp($a['key'], $b['key']);
    });

    return $array;
}


/**
 * build sorted query string
 *
 * @param  array $params {
 *      @type string $key
 *      @type string $value
 * }
 *
 * @return string Query string key=value&key2=value2&... sorted by key and value
 */
function richie_build_query ( $params ) {
    $sorted = richie_key_value_sort( $params );
    $mapper = function( $param ) {
        return $param['key'] . '=' . $param['value'];
    };
    $pairs = array_map ( $mapper, $sorted );
    return implode( '&', $pairs);
}

/**
 * Generate maggio authentication url signature hash
 *
 * @since 1.0.0
 *
 * @param  string   $secret         Authentication secret
 * @param  string   $issue_id       Issue UUIDv4 id
 * @param  int      $timestamp      Unix timestamp
 * @param  string   $query_string   Optional. Query string to be included in hash
 *
 * @return string   $hash           Calculated signature hash to be included in signin url
 */
function richie_generate_signature_hash( $secret, $issue_id, $timestamp, $query_string = '') {
    if ( !isset( $secret ) ) {
        return new WP_Error( 'secret', __('Missing secret') );
    }

    if ( !wp_is_uuid( $issue_id ) ) {
        return new WP_Error( 'uuid', __('Invalid issue uuid') );
    }

    if ( !is_int( $timestamp ) ) {
        return new WP_Error( 'timestamp', __('Invalid timestamp, it must be an integer') );
    }


    $signature_data = $issue_id . "\n" . $timestamp . "\n" . $query_string;
    $hash = hash_hmac('sha256', $signature_data, $secret); // returns hex data

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
    if (!is_user_logged_in()) {
        return false;
    }

    if(function_exists('pmpro_hasMembershipLevel') && $required_pmpro_level > 0) {
        $membership_level = pmpro_getMembershipLevelForUser();
        if (
            empty( $membership_level ) ||
            $membership_level->ID != $required_pmpro_level
        ) {
            // pmpro installed, required level configured and user doesn't have that level -> no access
            return false;
        }
    }

    return true;
}

/**
 * Modify original wordpress function with custom query (adding LIMIT 1).
 * This should make function to perform faster.
 * @see https://core.trac.wordpress.org/ticket/41281
*/
function richie_attachment_url_to_postid( $url ) {
    global $wpdb;

    $dir  = wp_get_upload_dir();
    $path = $url;

    $site_url   = parse_url( $dir['url'] );
    $image_path = parse_url( $path );

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


function richie_make_link_absolute($url) {
    if ( substr( $url, 0, 4 ) === 'http' ) {
        return $url;
    } elseif (substr( $url, 0, 2 ) === '//') {
        return 'https://' . $url;
    } else {
        return get_site_url(null, $url);
    }
}

function richie_get_image_id($image_url) {
	global $wpdb;
    $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url ));
    if (!empty($attachment)) {
        return $attachment[0];
    }
    return false;
}

function richie_is_image_url($image_url) {
    if ( !isset( $image_url ) ) {
        return false;
    }
    $allowed_extensions = array('png', 'jpg', 'gif');
    $path = wp_parse_url($image_url, PHP_URL_PATH);
    if ( $path ) {
        $filetype = wp_check_filetype($path);
        $extension = $filetype['ext'];
        if( in_array( $extension, $allowed_extensions ) ) {
            return true;
        }
    }
    return false;
}
}