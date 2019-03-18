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
    }


    private function render_template($slug, $name, $post_id) {
        global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID, $wp_styles, $wp_scripts, $wp_filter;
        require_once plugin_dir_path( __FILE__ ) . 'class-richie-template-loader.php';
        $richie_template_loader = new Richie_Template_Loader;
        $wp_query = new WP_Query(array(
            'p' => $post_id
        ));

        // add pmpro filter which overrides access and always returns true
        // this way it won't filter the content and always returns full content
        add_filter( 'pmpro_has_membership_access_filter', '__return_true', 20, 4 );

        ob_start();
        $richie_template_loader
            ->get_template_part($slug, $name);

        $rendered_content = ob_get_clean();
        wp_reset_query();
        wp_reset_postdata();

        return $rendered_content;
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
            $article->image_url = richie_make_link_absolute($thumbnail);
        }

        $article->id = $post->guid;
        $article->title = $post->post_title;
        $article->summary = $post->post_excerpt;
        if ( $category ) {
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

        //$article->debug_content_url = $content_url;

        $transient_key = 'richie_' . $hash;
        $rendered_content = get_transient($transient_key);

        if ( empty($rendered_content) ) {
            $response = wp_remote_get(
                $content_url,
                array ( 'sslverify' => false, 'timeout' => 15 )
            );

            if ( is_array( $response ) && ! is_wp_error( $response ) ) {
                $rendered_content = $response['body'];
                set_transient($transient_key, $rendered_content, 10);
                $article->from_cache = false;
            } else {
                $rendered_content = __('Failed to get content', 'richie');
                if ( is_wp_error( $response ) ) {
                    $article->content_error = $response->get_error_message();
                }
            }
        } else {
            $article->from_cache = true;
        }

        // render locally to get assets
        $this->render_template('richie-news', 'article', $post_id);
        // $wp_scripts and $wp_styles globals should now been set
        $article_assets = get_article_assets();

        // find local article assets (shortcodes etc)
        $local_assets = array_udiff($article_assets, $this->assets, function($a, $b) {
            return strcmp($a->remote_url, $b->remote_url);
        });

        $urls = array_unique(wp_extract_urls($rendered_content));
        // preg_match_all('/((href|src)=[\'"](.+?)[\'"])|([\'"](https?:\/\/.+?)[\'" ])/', $rendered_content, $matches);
        // $urls = array_unique(array_merge($matches[3], $matches[5]));
        $absolute_urls = array_map(function($url) {
            return richie_make_link_absolute($url);
        }, $urls);

        // replace asset urls with localname
        foreach ( $this->assets as $asset ) {
            $rendered_content = str_replace($asset->remote_url, ltrim( $asset->local_name, '/' ), $rendered_content);
            $rendered_content = str_replace(wp_make_link_relative($asset->remote_url), ltrim( $asset->local_name, '/' ), $rendered_content);

        }

        // replace local assets
        foreach ( $local_assets as $asset ) {
            $rendered_content = str_replace($asset->remote_url, ltrim( $asset->local_name, '/' ), $rendered_content);
            $rendered_content = str_replace(wp_make_link_relative($asset->remote_url), ltrim( $asset->local_name, '/' ), $rendered_content);
        }

        // preg_match_all('/((href|src)=[\'"](.+?)[\'"])|([\'"](https?:\/\/.+?)[\'" ])/', $rendered_content, $matches);
        // $extracted_urls = array_unique(array_merge($matches[3], $matches[5]));

        $disable_url_handling = false;

        if( isset( $_GET['disable_asset_parsing'] ) ) {
            $disable_url_handling = $_GET['disable_asset_parsing'] === '1';
        }

        $article_photos = array();

        if ( ! $disable_url_handling ) {
            $image_urls = [];
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_convert_encoding($rendered_content, 'HTML-ENTITIES', 'UTF-8'));
            // Get all the images
            $images = $dom->getElementsByTagName('img');

            // Loop the images
            foreach ($images as $image) {
                $image_urls[] = $image->getAttribute('src');
                $image->removeAttribute('srcset');
            }

            // Save the HTML
            $rendered_content = $dom->saveHTML();

            $galleries = get_post_galleries($post_id, false);

            if ( !empty( $galleries ) ) {
                foreach( $galleries as $gallery ) {
                    $photos_array = [];
                    $ids = explode(',', $gallery['ids']);
                    foreach ( $ids as $attachment_id ) {
                        $attachment = get_post($attachment_id);
                        $attachment_url = wp_get_attachment_url($attachment->ID);
                        $local_name = remove_query_arg( 'ver', wp_make_link_relative($attachment_url));
                        $local_name = ltrim($local_name, '/');
                        $rendered_content = str_replace($attachment_url, $local_name, $rendered_content);
                        $photos_array[] = array(
                            'caption' => $attachment->post_excerpt,
                            'local_name' => $local_name,
                            'remote_url' => richie_make_link_absolute($attachment_url)
                        );

                        // remove from general image array, since we have already handled this url
                        $index = array_search($attachment_url, $image_urls);
                        if($index !== FALSE){
                            unset($image_urls[$index]);
                        }

                    }
                    $article_photos[] = $photos_array;
                }
            }

            if ( $image_urls ) {

                $attachent_cache = [];
                $photos_array = [];
                foreach ( array_unique($image_urls) as $url) {
                    // remove size from the url, expects '-1000x230' format
                    $base_url = preg_replace('/(.+)(-\d+x\d+)(.+)/', '$1$3', $url);

                    $local_name = remove_query_arg( 'ver', wp_make_link_relative($url));
                    if ( empty($local_name) ) {
                        continue;
                    }
                    $local_name = ltrim($local_name, '/');

                    $remote_url = richie_make_link_absolute($url);

                    $attachment_id = false;
                    // if( isset( $attachment_cache[$base_url] ) ) {
                    //     $attachment_id = $attachment_cache[$base_url];
                    // } else {
                    //     $attachment_id = richie_get_image_id($base_url);
                    //     $attachment_cache[$base_url] = $attachment_id;
                    // }
                    if ( $attachment_id ) {
                        $attachment = get_post($attachment_id);
                        $attachment_url = wp_get_attachment_url($attachment->ID);
                        $rendered_content = str_replace($url, $local_name, $rendered_content);
                        $photos_array[] = array(
                            'caption' => $attachment->post_excerpt,
                            'local_name' => $local_name,
                            'remote_url' => $remote_url
                        );
                    } else {
                        $rendered_content = str_replace($url, $local_name, $rendered_content);
                        $photos_array[] = array(
                            'local_name' => $local_name,
                            'remote_url' => $remote_url
                        );
                    }
                }

                $article_photos[] = $photos_array;
            }
        }

        $article->content_html_document = $rendered_content;
        $article->assets = array_values($local_assets);
        $article->photos = $article_photos;
        return $article;
    }
}