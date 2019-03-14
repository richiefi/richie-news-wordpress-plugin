<?php

class Richie_Article {

    // values for metered_paywall
    const METERED_PAYWALL_NO_ACCESS_VALUE = 'no_access';
    const METERED_PAYWALL_METERED_VALUE = 'metered';
    const METERED_PAYWALL_FREE_VALUE = 'free';

    private $news_options;
    private $assets;

    function __construct($richie_options, $assets = []) {
        $this->news_options = $richie_options;
        $this->assets = $assets;

        function start_wp_head_buffer() {
            ob_start();
        }
        add_action('wp_head','start_wp_head_buffer',0);

        function end_wp_head_buffer() {
            global $richie_cached_head;
            if (empty($richie_cached_head)) {
                $richie_cached_head = ob_get_clean();
            } else {
                ob_end_clean();
            }
            remove_action('wp_head', 'start_wp_head_buffer');
            remove_action('wp_head', 'end_wp_head_buffer');
        }
        add_action('wp_head','end_wp_head_buffer', PHP_INT_MAX); //PHP_INT_MAX will ensure this action is called after all other actions that can modify head
        add_action('wp_head', array($this, 'cached_head'), PHP_INT_MAX);


        function start_wp_footer_buffer() {
            ob_start();
        }
        add_action('wp_footer','start_wp_footer_buffer', 0);

        function end_wp_footer_buffer() {
            global $richie_cached_footer;
            if (empty($richie_cached_footer)) {
                $richie_cached_footer = ob_get_clean();
            } else {
                ob_end_clean();
            }
            remove_action('wp_footer', 'start_wp_footer_buffer');
            remove_action('wp_footer', 'end_wp_footer_buffer');
        }
        add_action('wp_footer','end_wp_footer_buffer', PHP_INT_MAX); //PHP_INT_MAX will ensure this action is called after all other actions that can modify head
        add_action('wp_footer', array($this, 'cached_footer'), PHP_INT_MAX);


        function get_assets() {
            global $wp_styles, $wp_scripts, $feed_assets;
            $feed_assets = array();
            foreach ( $wp_styles->queue as $handle) {
                array_push($feed_assets, $wp_styles->registered[$handle]->src);
            }
            foreach ( $wp_scripts->queue as $handle) {
                array_push($feed_assets, $wp_scripts->registered[$handle]->src);
            }
        }
        add_action( 'wp_enqueue_scripts', 'get_assets', PHP_INT_MAX);
    }

    public function cached_head() {
        global $richie_cached_head;
        echo $richie_cached_head;
    }

    public function cached_footer() {
        global $richie_cached_footer;
        echo $richie_cached_footer;
    }


    public function richie_pmpro_has_membership_access_filter( $hasaccess, $mypost, $myuser, $post_membership_levels ) {
        return true;
    }

    public function generate_article($my_post) {
        global $wpdb;

        $post = $my_post;
        $hash = md5(serialize($my_post));
        $article = new stdClass();
        $article->hash = $hash;

        // get metadata
        $post_id = $post->ID;
        $user_data = get_userdata($post->post_author);
        $category = get_the_category($post_id);

        $thumbnail = get_the_post_thumbnail_url($post_id, 'full');

        if ( $thumbnail ) {
            if ( substr( $thumbnail, 0, 4 ) === 'http' ) {
                $article->image_url = $thumbnail;
            } else {
                $article->image_url = get_site_url(null, $thumbnail);
            }
        }

        $article->id = $post->guid;
        $article->title = $post->post_title;
        $article->summary = $post->post_excerpt;
        if ($category) {
            $article->kicker = $category[0]->name;
        }

        $date = (new DateTime($post->post_date_gmt))->format('c');
        $updated_date = (new DateTime($post->post_modified_gmt))->format('c');
        $article->date = $date;
        if ($date != $updated_date) {
            $article->updated_date = $updated_date;
        }

        $article->share_link_url = get_permalink($post_id);


        $metered_id = $this->news_options['metered_pmpro_level'];
        $member_only_id = $this->news_options['member_only_pmpro_level'];

        // get paywall type
        $sqlQuery = "SELECT mp.membership_id as id FROM $wpdb->pmpro_memberships_pages mp WHERE mp.page_id = '" . $post->ID . "'";
        $post_membership_levels = $wpdb->get_results($sqlQuery);
        $levels = array_column($post_membership_levels, 'id');

        $is_premium = in_array($member_only_id, $levels);
        $is_metered = in_array($metered_id, $levels);

        if ( $is_metered ) {
            $article->metered_paywall = self::METERED_PAYWALL_METERED_VALUE;
        } else if ( $is_premium  ) {
            $article->metered_paywall = self::METERED_PAYWALL_NO_ACCESS_VALUE;
        } else {
            $article->metered_paywall = self::METERED_PAYWALL_FREE_VALUE;
        }

        $content_url = add_query_arg( array(
            'richie_news' => 1,
            'token' =>  $this->news_options['access_token']
        ), get_permalink($post_id));

        $photos = array();
        $assets = array();

        $transient_key = 'richie_' . $hash;
        $rendered_content = get_transient($transient_key);


        if ( empty($rendered_content) ) {
            $response = wp_remote_get($content_url);
            //$response = wp_remote_get(str_replace('localhost:8000', '192.168.0.4:8000', $content_url), array ( 'sslverify' => false));

            if ( is_array( $response ) && ! is_wp_error( $response ) ) {
                $rendered_content = $response['body'];
                set_transient($transient_key, $rendered_content, 10);
                $article->from_cache = false;
            } else {
                $rendered_content = 'Failed to get content';
                if ( is_wp_error( $response ) ) {
                    $article->content_error = $response->get_error_message();
                }
            }
        } else {
            $article->from_cache = true;
        }

        preg_match_all('/((href|src)=[\'"](.+?)[\'"])|([\'"](https?:\/\/.+?)[\'" ])/', $rendered_content, $matches);
        $asset_urls = array_column($this->assets, 'remote_url');
        $asset_names = array_column($this->assets, 'local_name');

        $found_urls = array_unique(array_merge($matches[3], $matches[5]));
        $urls = array_diff($found_urls, $asset_urls);

        // replace asset urls with localname
        foreach ( $this->assets as $asset ) {
            $rendered_content = str_replace($asset->remote_url, ltrim( $asset->local_name, '/' ), $rendered_content);
        }


        $disable_url_handling = false;

        if( isset( $_GET['disable_asset_parsing'] ) ) {
            $disable_url_handling = $_GET['disable_asset_parsing'] === '1';
        }

        if ( ! $disable_url_handling ) {
            if ( $urls ) {
                // only parse urls with following extensions
                $allowed_extensions = array('png', 'jpg', 'gif', 'js', 'css');
                $filtered_urls = array();

                foreach( $urls as $u ) {
                    // wordpress includes some script tags inside cdata and it messes the extract urls function
                    // ignore specific row in urls, since it is wrongly matched
                    if ( strpos( $u, 'admin-ajax.php' ) ) {
                        continue;
                    }
                    $path = wp_parse_url($u, PHP_URL_PATH);
                    if ( $path ) {
                        $filetype = pathinfo($path);
                        if ( isset($filetype['extension'] ) ) {
                            $extension = $filetype['extension'];
                            if( in_array( $extension, $allowed_extensions ) ) {
                                array_push($filtered_urls, $u);
                            }
                        }
                    }

                }
                $attachent_cache = [];
                foreach ( $filtered_urls as $url) {
                    $base_url = preg_replace('/(.+)(-\d+x\d+)(.+)/', '$1$3', $url);

                    $local_name = remove_query_arg( 'ver', wp_make_link_relative($url));
                    if ( empty($local_name) ) {
                        continue;
                    }
                    $local_name = ltrim($local_name, '/');
                    if (in_array($asset_urls, $local_name)) {
                        continue;
                    }
                    $remote_url = richie_make_link_absolute($url);

                    $attachment_id = false;
                    if( isset( $attachment_cache[$base_url] ) ) {
                        $attachment_id = $attachment_cache[$base_url];
                    } else {
                        $attachment_id = richie_get_image_id($base_url);
                        $attachment_cache[$base_url] = $attachment_id;
                    }
                    if ( richie_is_image_url($url) ) {
                        $attachment = null; //get_post($attachment_id);
                        if ( $attachment ) {
                            $attachment_url = wp_get_attachment_url($attachment->ID);
                            if ( wp_attachment_is_image($attachment) ) {
                                $rendered_content = str_replace($url, $local_name, $rendered_content);
                                $photos[] = array(
                                    'caption' => $attachment->post_excerpt,
                                    'local_name' => $local_name,
                                    'remote_url' => $remote_url
                                );
                            } else {
                                $rendered_content = str_replace($url, $local_name, $rendered_content);
                                array_push($assets, array(
                                    'local_name' => $local_name,
                                    'remote_url' => $remote_url
                                ));
                            }
                        } else {
                            $rendered_content = str_replace($url, $local_name, $rendered_content);
                            array_push($photos, array(
                                'local_name' => $local_name,
                                'remote_url' => $remote_url
                            ));
                        }
                    } else {
                        // not an attachment
                        $rendered_content = str_replace($url, $local_name, $rendered_content);
                        if ( !in_array($local_name, $asset_names) ) {
                            array_push($assets, array(
                                'local_name' => $local_name,
                                'remote_url' => $remote_url
                            ));
                        }
                    }
                }
            }
        }

        $article->content_html_document = $rendered_content;

        $article->assets = $assets;
        $article->photos = array(array_values($photos));
        return $article;
    }
}