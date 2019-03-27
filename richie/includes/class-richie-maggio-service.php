<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-maggio-issue.php';

/**
 * Uses wordpress transient for caching request
 *
 * Constructor takes url, minimum cache time and optionally maximum cache time as arguments.
 * Cache time values are in seconds. If cached request is newer than minimum time, new request isn't done at all.
 * This can be used to limit poll interval for the remote url. Maximum value is set to transient, it automatically
 * removes the cache after the time is passed, forcing new request to be made.
 *
 * @since 1.0.0
 * @package    Richie
 * @subpackage Richie/includes
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Cached_Request {

    /**
     * @since 1.0.0
     * @access private
     * @var string $url Remote request url
     */
    private $url;

    /**
     * @since 1.0.0
     * @access private
     * @var string $cache_key Key for transient
     */
    private $cache_key;

    /**
     * @since 1.0.0
     * @access private
     * @var int $minimum_cache_time Always return cached response during that time. In seconds.
     */
    private $minimum_cache_time;

    /**
     * @since 1.0.0
     * @access private
     * @var int $maximum_cache_time Invalidates cache after this time, forcing new request to be made.
     */
    private $maximum_cache_time;


    /**
     * Create instance of Cached_Request
     *
     * @param  string   $url
     * @param  int      $minimum_cache_time
     * @param  int      $maximum_cache_time
     *
     */
    function __construct( $url, $minimum_cache_time, $maximum_cache_time = 0 ) {
        if ( empty( $url ) ) {
            throw new Exception('Missing url argument');
        }
        $this->url = $url;
        $this->cache_key = md5( 'remote_request|' . $this->url );
        $this->minimum_cache_time = $minimum_cache_time;
        $this->maximum_cache_time = $maximum_cache_time;
    }

    /**
     * Get remote url response
     *
     * Return cached response if possible.
     *
     * @since 1.0.0
     * @access public
     * @return WP_Error|array The response or WP_Error on failure.
     */
    public function get_response() {
        $cache = get_transient($this->cache_key);

        if ( $this->should_return_cache($cache) ) {
            // we have cached request and minimum_cache_time has not passed, return it
            return $cache['response'];
        }

        $headers = [];

        $etag = $this->get_etag( $cache );
        if ( $etag ) {
            $headers['If-None-Match'] = $etag;
        } else {
            $headers['If-Modified-Since'] = date( 'D, d M Y H:i:s', $cache['timestamp'] );
        }

        $response = wp_remote_get( $this->url , array(
            'headers' => $headers
        ));

        if ( is_wp_error( $response ) ) {
            return $cache['response']; // return cached response if any
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 304) {
            // nothing changed, return cached response
            $this->set_cached_request($cache['response'], $this->maximum_cache_time);
            return $cache['response'];
        }

        if ( $response_code === 200) {
            // we have changed data, cache and return it
            $this->set_cached_request( $response, $this->maximum_cache_time );
        } else {
            // cache invalid responses for a short time
            $this->set_cached_request( $response, 10 );
        }

        return $response;
    }

    /**
     * Check if cached response should be returned
     *
     * @since 1.0.0
     * @param array $cache Array from transient
     * @return boolean
     */
    private function should_return_cache( $cache ) {
        $max_age = $this->get_max_age( $cache );

        if ( $max_age === false ) {
            $max_age = 0;
        }

        $cache_time = max($max_age, $this->minimum_cache_time);
        if ( isset( $cache, $cache['timestamp'] ) && ( time() - $cache['timestamp'] <= $cache_time ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get etag from the cached response
     *
     * @since 1.0.0
     * @param array $cache Array from transient
     * @return string|boolean Returns etag or false if not found
     */
    private function get_etag( $cache ) {
        if ( isset( $cache, $cache['response'] ) ) {
            return wp_remote_retrieve_header( $cache['response'], 'etag' );
        }

        return false;
    }

    private function parse_max_age( $cache_control ) {
        $max_age = explode("max-age=", $cache_control);
        if ( count( $max_age ) > 0) {
            $max_age = explode(',', $max_age[1]);
            $max_age = trim($max_age[0]);
            $max_age = intval($max_age);
            return $max_age;
        }
        return false;
    }
    /**
     * Get max-age from the cached response
     *
     * @since 1.0.0
     * @param array $cache Array from transient
     * @return int|boolean Returns max-age or false if not found
     */
    private function get_max_age( $cache ) {
        if ( isset( $cache, $cache['response'] ) ) {
            $cache_control = wp_remote_retrieve_header( $cache['response'], 'cache-control' );
            if ( isset( $cache_control ) ) {
                $max_age = $this->parse_max_age($cache_control);
                return $max_age;
            }
        }

        return false;
    }

    /**
     * Cache response in transient
     *
     * @since 1.0.0
     * @param array    $response   Response from wp_remote_get
     * @param int               $cache_time Optional. Delete cache after given time in seconds.
     */
    private function set_cached_request( $response, $cache_time = 0) {
        $cache = array (
            'timestamp' => time(),
            'response' => $response
        );

        set_transient($this->cache_key, $cache, $cache_time);
    }
}
/**
 * Fetch issues by using cached request
 *
 * @since 1.0.0
 * @package    Richie
 * @subpackage Richie/includes
 * @author     Markku Uusitupa <markku@richie.fi>
*/
class Richie_Maggio_Service {
    private $cached_request;

    function __construct( $host_name )  {
        $minimum_cache_time = MINUTE_IN_SECONDS;
        $maximum_cache_time = 0; // no cache
        $index_url = $host_name . '/_data/index.json';
        $this->cached_request = new Richie_Cached_Request( $index_url, $minimum_cache_time, $maximum_cache_time );
    }

    private function get_cached_response() {
        $response = $this->cached_request->get_response();

        if ( $response === false) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );

        $data = json_decode( $body );

        if ( empty( $data ) ) {
            return false;
        }

        return $data;
    }

    public function is_issue_free( $issue_uuid ) {
        if ( empty( $issue_uuid ) || !wp_is_uuid($issue_uuid) ) {
            return false;
        }

        $data = $this->get_cached_response();

        if ( $data === false ) {
            return false;
        }

        $found = false;
        foreach (  $data->issues as $org => $issues) {
            foreach ( $issues as $issue ) {
                if ( $issue->uuid === $issue_uuid ) {
                    return $issue->isFree === true;
                }
            }
        }

        return false;
    }

    public function get_issues( $organization, $product, $number_of_issues = -1 ) {
        if ( empty( $organization ) || empty( $product ) ) {
            return false;
        }

        $data = $this->get_cached_response();

        if ( $data === false ) {
            return false;
        }

        $issues = array();
        $product_id = $organization . '.magg.io/' . $product;
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