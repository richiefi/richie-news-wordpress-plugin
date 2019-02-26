<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-maggio-issue.php';

class Richie_Maggio_Service {
    public $public;
    public $url;
    private $request_cache_key;
    private $minimum_cache_time;

    function __construct($index_url, $client)  {
        $this->client = $client;
        $this->url = $index_url;
        $this->request_cache_key = md5( 'remote_request|' . $this->url );
        $this->minimum_cache_time = MINUTE_IN_SECONDS;
    }

    public function get_issues( $product, $number_of_issues = -1 ) {
        $request = get_transient($this->request_cache_key);

        if ( $request === false ) {
            $request = wp_remote_get( $this->url );
            if ( is_wp_error( $request ) ) {
                // Cache failures for a short time, will speed up page rendering in the event of remote failure.
                set_transient( $this->request_cache_key, $request, 10 );
                return false;
            }
            // success, cache
            set_transient( $this->request_cache_key, $request, $this->minimum_cache_time );
        }

        if ( is_wp_error( $request ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $request );
        $data = json_decode( $body );

        $issues = array();
        $product_id = $this->client . '.magg.io/' . $product;
        if( !isset($data->issues->{$product_id})) {
            return false;
        }
        $product_issues = $data->issues->{$product_id};

        if( !empty( $product_issues ) ) {
            foreach( $product_issues as $issue_data ) {
                $issue = new Richie_Maggio_Issue($product, $issue_data);
                array_push($issues, $issue);
            }
        }

        usort( $issues, function( $a, $b ) {
            return $b->date - $a->date;
        } );

        if ( $number_of_issues >= 0 ) {
            return array_slice($issues, 0, $number_of_issues);
        }

        return $issues;
    }

}