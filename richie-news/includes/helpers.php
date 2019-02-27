<?php

function richie_create_maggio_rewrite_rules($flush = False) {
    add_rewrite_tag('%maggio_redirect%', '([0-9a-fA-F-]+)');
    add_rewrite_rule('^maggio-redirect/([0-9a-fA-F-]+)/?$', 'index.php?maggio_redirect=$matches[1]', 'top');

    if ( $flush ) {
        flush_rewrite_rules();
    }
}

function richie_key_value_sort( $array ) {
    usort($array, function($a, $b) {
        if ( $a['key'] === $b['key'] ) {
            return strcmp( $a['value'], $b['value'] );
        }
        return strcmp($a['key'], $b['key']);
    });

    return $array;
}

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
 */
function richie_generate_signature_hash( $secret, $issue_id, $timestamp, $auth_params = null) {
    if ( !isset( $secret ) ) {
        return new WP_Error( 'secret', __('Missing secret') );
    }

    if ( !wp_is_uuid( $issue_id ) ) {
        return new WP_Error( 'uuid', __('Invalid issue uuid') );
    }

    if ( !is_int( $timestamp ) ) {
        return new WP_Error( 'timestamp', __('Invalid timestamp, it must be an integer') );
    }

    $params = '';

    if ( !empty( $auth_params ) ) {
        // sort by keys and values and create query string
        $params = richie_build_query( $auth_params );
    }

    $signature_data = $issue_id . "\n" . $timestamp . "\n" . $params;
    $hash = hash_hmac('sha256', $signature_data, $secret); // returns hex data

    return $hash;
}


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