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