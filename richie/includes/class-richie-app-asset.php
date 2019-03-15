<?php

class Richie_App_Asset {
    public $local_name;
    public $remote_url;

    function __construct($dependency, $local_prefix = 'app-assets/') {
        $remote_url = $dependency->src;
        if ( substr( $remote_url, 0, 4 ) !== "http" ) {
            $remote_url = get_site_url(null, $remote_url);
        }
        $this->local_name = $local_prefix . ltrim(wp_make_link_relative($remote_url), '/');
        $this->remote_url = add_query_arg( 'ver', $dependency->ver, $remote_url );
    }

    public function __toString() {
        return json_encode(array(
            'local_name' => $this->local_name,
            'remote_url' => $this->remote_url
        ));
    }
}