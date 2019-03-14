<?php

class Richie_App_Asset {
    public $local_name;
    public $remote_url;

    function __construct($dependency) {
        $remote_url = $dependency->src;
        if ( substr( $remote_url, 0, 4 ) !== "http" ) {
            $remote_url = get_site_url(null, $remote_url);
        }
        $this->local_name = 'app-assets/' . ltrim(wp_make_link_relative($remote_url), '/');
        $this->remote_url = add_query_arg( 'ver', $dependency->ver, $remote_url );
    }
}