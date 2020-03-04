<?php

class Richie_App_Asset {
    public $local_name;
    public $remote_url;

    function __construct($dependency, $local_prefix = 'app-assets/') {
        $remote_url = $dependency->src;

        // this should handle also protocol relative urls
        $parsed_url = wp_parse_url( $remote_url );

        if ( $parsed_url !== false) {
            if ( empty( $parsed_url['scheme'] ) ) {
                if ( isset( $parsed_url['host'] ) ) {
                    $remote_url = set_url_scheme( $remote_url );
                } else {
                    $remote_url = get_site_url( null, $remote_url );
                }
            }
        } else {
            // failed to parse string, try sanitizing it anyway
            $remote_url = get_site_url( null, $remote_url );
        }

        $this->local_name = richie_normalize_path($local_prefix . ltrim(wp_make_link_relative($remote_url), '/'));

        if ( !empty( $dependency->ver ) ) {
            $remote_url = add_query_arg( 'ver', $dependency->ver, $remote_url );
        }

        $this->remote_url = $remote_url;
    }

    public function __toString() {
        return json_encode(array(
            'local_name' => $this->local_name,
            'remote_url' => $this->remote_url
        ));
    }
}