<?php
/**
 * Generates article for the Richie news feed.
 *
 * @link       https://www.richie.fi
 * @since      1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 */

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-photo-asset.php';


/**
 * Generates article for the Richie news feed.
 *
 * @since      1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Article {

    // Values for metered_paywall.
    const METERED_PAYWALL_NO_ACCESS_VALUE = 'no_access';
    const METERED_PAYWALL_METERED_VALUE   = 'metered';
    const METERED_PAYWALL_FREE_VALUE      = 'free';

    /**
     * Richie options
     *
     * @var array
     */
    private $news_options;

    /**
     * Available assets in the asset feed
     *
     * @var array
     */
    private $assets;

    /**
     * Create instance of richie news article generator
     *
     * @param array $richie_options Richie plugin options.
     * @param array $assets Available asset items.
     */
    public function __construct( $richie_options, $assets = [] ) {
        $this->news_options = $richie_options;
        $this->assets       = $assets;
    }

    public function render_template( $slug, $name, $post_obj ) {
        global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $user_ID, $wp_styles, $wp_scripts, $wp_filter;
        require_once plugin_dir_path( __FILE__ ) . 'class-richie-template-loader.php';
        $richie_template_loader = new Richie_Template_Loader();

        $wp_query = new WP_Query( //phpcs:ignore
            array(
                'p' => $post_obj->ID,
            )
        );

        // Override global '$post'. This is required for rendering to work, templates may use global '$post'.
        $post = $post_obj; //phpcs:ignore
        $wp_query->setup_postdata( $post_obj );

        if ( $this->is_pmpro_active() ) {
            // Add pmpro filter which overrides access and always returns true.
            // This way it won't filter the content and always returns full content.
            add_filter( 'pmpro_has_membership_access_filter', '__return_true', 20, 4 );
        }

        ob_start();
        $richie_template_loader
            ->get_template_part( $slug, $name );

        $rendered_content = ob_get_clean();
        wp_reset_query(); // Reset wp query to original and resets post data also.

        return $rendered_content;
    }

    private function append_wpp_shadow( $url ) {
        if ( isset( $_GET['wpp_shadow'] ) ) {
            return add_query_arg( 'wpp_shadow', sanitize_text_field( wp_unslash( $_GET['wpp_shadow'] ) ), $url );
        } else {
            return $url;
        }
    }

    /**
     * Check if pmpro plugin is active.
     */
    public function is_pmpro_active() {
        return richie_is_pmpro_active();
    }

    /**
     * Get available pmpro plugin levels.
     *
     * @param [WP_POST] $my_post WP post object.
     * @return array
     */
    public function get_pmpro_levels( $my_post ) {
        if ( $this->is_pmpro_active() ) {
            global $wpdb;
            $post_membership_levels = $wpdb->get_results( $wpdb->prepare( "SELECT mp.membership_id as id FROM $wpdb->pmpro_memberships_pages mp WHERE mp.page_id = %d", $my_post->ID ) );
            $levels                 = array_column( $post_membership_levels, 'id' );

            return $levels;
        }
        return [];
    }

    public function get_article_assets() {
        // Expects global $wp_scripts and $wp_styles.
        return richie_get_article_assets();
    }

    /**
     * Parse html content and return all img tag source urls
     *
     * @param string $content
     * @return array
     */
    public function get_article_images( $content, $include_links = false ) {
        $image_urls = [];
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->substituteEntities = false;
        libxml_use_internal_errors( true );
        $dom->loadHTML( $content );
        // Get all the images.
        $images = $dom->getElementsByTagName( 'img' );
        // Loop the images.
        foreach ( $images as $image ) {
            $image_urls[] = richie_make_link_absolute($image->getAttribute( 'src' ));
            $image->removeAttribute( 'srcset' );
        }

        if ( true === $include_links ) {
            $links = $dom->getElementsByTagName( 'a' );

            foreach ( $links as $link ) {
                $href = $link->getAttribute( 'href' );
                if( richie_is_image_url( $href ) ) {
                    $image_urls[] = richie_make_link_absolute( $href );
                }
            }
        }

        // Remove duplicate urls.
        $image_urls = array_unique( $image_urls );
        $html = $dom->saveHTML($dom->documentElement);
        return array(
            'images' => $image_urls,
            'content' => $html
        );
    }

    /**
     * Create an asset array from image urls. Optionally tries to link them to an attachment.
     * Finding attachment by url is really slow, so it is disabled as default.
     * It also replaces urls found in passed content string with generated local_name.
     *
     * @param array $image_urls
     * @param string $rendered_content Passed as reference, modifies content.
     * @param boolean $resolve_attachment
     * @return array
     */
    public function generate_photos_array( $image_urls, &$rendered_content, $resolve_attachment = false ) {
        if ( empty( $image_urls ) ) {
            return [];
        }

        $attachment_cache = [];
        $results          = [];

        foreach ( array_unique( $image_urls ) as $url ) {
            $photo_asset = new Richie_Photo_Asset( $url, false);
            $results[] = $photo_asset;

            $encoded_url = richie_encode_url_path( $url );
            $rendered_content = str_replace( $url, $photo_asset->local_name, $rendered_content );
            $rendered_content = str_replace( $encoded_url, $photo_asset->local_name, $rendered_content );
        }

        return $results;
    }

    public function generate_article( $my_post ) {
        if ( empty( $my_post ) ) {
            return new stdClass(); // Return empty object.
        }

        $hash          = md5( wp_json_encode( $my_post ) );
        $article       = new stdClass();
        $article->hash = $hash;

        // Get metadata.
        $post_id   = $my_post->ID;
        $user_data = get_userdata( $my_post->post_author );
        $category  = get_the_category( $post_id );

        $article->id      = strval($post_id);
        $article->title   = $my_post->post_title;
        $article->summary = $my_post->post_excerpt;
        if ( $category ) {
            $article->kicker = $category[0]->name;
        }
        $revisions = wp_get_post_revisions( $my_post );
        $published_rev = array_pop($revisions);

        $article->analytics_data = array(
            'wp_post_id' => $my_post->ID,
            'original_title' => isset( $published_rev->post_title ) ? $published_rev->post_title : $my_post->post_title
        );

        $date          = new DateTime( $my_post->post_date_gmt );
        $updated_date  = new DateTime( $my_post->post_modified_gmt );
        $article->date = $date->format( 'c' );

        $diff = $updated_date->getTimestamp() - $date->getTimestamp();

        // Include updated_date if its at least 5 minutes after creation date.
        if ( $diff >= 5 * MINUTE_IN_SECONDS ) {
            $article->updated_date = $updated_date->format( 'c' );
        }

        $article->share_link_url = get_permalink( $post_id );

        $metered_id = $this->news_options['metered_pmpro_level'];
        $member_only_id = $this->news_options['member_only_pmpro_level'];

        // Get paywall type.
        $levels = $this->get_pmpro_levels( $my_post );

        $is_premium = in_array( $member_only_id, $levels );
        $is_metered = in_array( $metered_id, $levels );

        if ( $is_metered ) {
            $article->metered_paywall = self::METERED_PAYWALL_METERED_VALUE;
        } elseif ( $is_premium ) {
            $article->metered_paywall = self::METERED_PAYWALL_NO_ACCESS_VALUE;
        } else {
            $article->metered_paywall = self::METERED_PAYWALL_FREE_VALUE;
        }

        $content_url = add_query_arg(
            array(
                'richie_news' => 1,
                'token'       => $this->news_options['access_token'],
            ),
            get_permalink( $post_id )
        );

        $content_url = $this->append_wpp_shadow( $content_url );

        $disable_url_handling = false;

        if ( isset( $_GET['disable_asset_parsing'] ) ) {
            $disable_url_handling = $_GET['disable_asset_parsing'] === '1';
        }

        $use_local_render = true;
        if ( isset( $_GET['use_local_render'] ) ) {
            $use_local_render = $_GET['use_local_render'] === '1';
        }


        //$article->debug_content_url = $content_url;

        if ( ! $use_local_render ) {
            $transient_key    = 'richie_' . $hash;
            $rendered_content = get_transient( $transient_key );

            if ( empty( $rendered_content ) ) {
                $response = wp_remote_get(
                    $content_url,
                    array(
                        'sslverify' => false,
                        'timeout'   => 15,
                    )
                );

                if ( is_array( $response ) && ! is_wp_error( $response ) ) {
                    $rendered_content = $response['body'];
                    set_transient( $transient_key, $rendered_content, 10 );
                    $article->from_cache = false;
                } else {
                    $rendered_content = __( 'Failed to get content', 'richie' );
                    if ( is_wp_error( $response ) ) {
                        $article->content_error = $response->get_error_message();
                    }
                }
            } else {
                $article->from_cache = true;
            }
        }

        // Render locally to get assets.
        $local_rendered_content = $this->render_template( 'richie-news', 'article', $my_post );

        if ( $use_local_render ) {
            $rendered_content = $local_rendered_content;
        }
        // $wp_scripts and $wp_styles globals should now been set
        $article_assets = $this->get_article_assets();

        // Find local article assets (shortcodes etc).
        $local_assets = array_udiff(
            $article_assets,
            $this->assets,
            function( $a, $b ) {
                return strcmp( $a->remote_url, $b->remote_url );
            }
        );

        // Replace asset urls with localname.
        foreach ( $this->assets as $asset ) {
            $local_name       = ltrim( $asset->local_name, '/' );
            $rendered_content = str_replace( $asset->remote_url, $local_name, $rendered_content );
            $regex = '/(?<!app-assets)' . preg_quote( wp_make_link_relative( $asset->remote_url ), '/' ) . '/';
            $rendered_content = preg_replace( $regex, $local_name, $rendered_content );
        }

        // Replace local assets.
        foreach ( $local_assets as $asset ) {
            $local_name       = ltrim( $asset->local_name, '/' );
            $rendered_content = str_replace( $asset->remote_url, $local_name, $rendered_content );
            $regex = '/(?<!app-assets)' . preg_quote( wp_make_link_relative( $asset->remote_url ), '/' ) . '/';
            $rendered_content = preg_replace( $regex, $local_name, $rendered_content );
        }

        $article_photos = array();

        $main_gallery = [];
        $thumbnail_id = get_post_thumbnail_id( $my_post );

        $rendered_article_images = $this->get_article_images( $rendered_content );
        $image_urls   = $rendered_article_images['images'];


        // Save the HTML with img srcset removed.
        $rendered_content = $rendered_article_images['content'];

        $all_sizes          = get_intermediate_image_sizes();
        $all_sizes[]        = 'full'; // Append full size also.
        $all_gallery_images = [];

        if ( $thumbnail_id ) {
            $thumbnail          = wp_get_attachment_image_url( $thumbnail_id, 'full' );
            $remote_url         = richie_make_link_absolute( $thumbnail );
            $article->image_url = $this->append_wpp_shadow( $remote_url );

            foreach ( $all_sizes as $size ) {
                $thumbnail_url = null;
                if ( 'full' === $size ) {
                    // We already have full url.
                    $thumbnail_url = $thumbnail;
                } else {
                    $thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, $size );
                }
                if ( false !== strpos( $rendered_content, $thumbnail_url ) ) {
                    $photo_asset = new Richie_Photo_Asset( $remote_url, false );
                    $photo_asset->caption = get_the_post_thumbnail_caption( $my_post );

                    $rendered_content = str_replace( $thumbnail_url, $photo_asset->local_name, $rendered_content );
                    $main_gallery[]   = $photo_asset;
                    $all_gallery_images[] = $thumbnail_url;

                    // Remove from general image array, since we have already handled this url.
                    $index = array_search( $thumbnail_url, $image_urls );
                    if ( false !== $index ) {
                        unset( $image_urls[ $index ] );
                    }
                }
            }
        }

        if ( ! $disable_url_handling ) {
            // Find galleries in post and append it to photos array.
            $galleries = get_post_galleries( $my_post, false );

            foreach ( $galleries as $gallery ) {
                $gallery_photos = [];

                if ( false !== $gallery ) {
                    $ids = explode( ',', $gallery['ids'] );
                    foreach ( $ids as $attachment_id ) {
                        $attachment = get_post( $attachment_id );
                        if ( null === $attachment ) {
                            continue;
                        }
                        $attachment_url = wp_get_attachment_url( $attachment->ID );

                        $photo_asset = new Richie_Photo_Asset( $attachment_url );
                        $photo_asset->caption = $attachment->post_excerpt;

                        $local_name = $photo_asset->local_name;
                        $absolute_url = richie_make_link_absolute( $attachment_url );

                        if ( false !== strpos( $rendered_content, $absolute_url ) ) {
                            $rendered_content = str_replace( $absolute_url, $local_name, $rendered_content );
                        } else {
                            $rendered_content = str_replace( $attachment_url, $local_name, $rendered_content );
                        }


                        $gallery_photos [] = $photo_asset;

                        $all_gallery_images[] = $attachment_url;

                        // get all variants and filter from main gallery
                        foreach ( $all_sizes as $size ) {
                            $img = wp_get_attachment_image_src($attachment_id, $size);

                            if ( false !== $img ) {
                                // Remove from general image array, since this is in wordpress gallery.
                                $url = $img[0];
                                $index = array_search( $url, $image_urls );
                                if ( false !== $index ) {
                                    unset( $image_urls[ $index ] );
                                }
                            }
                        }
                    }
                }

                if ( ! empty( $gallery_photos ) ) {
                    $article_photos[] = $gallery_photos;
                }
            }
        }


        if ( $image_urls ) {
            $img_list = $this->generate_photos_array($image_urls, $rendered_content, false);
            $main_gallery = array_merge( $main_gallery, $img_list );
        }

        // Remove possible duplicate entries from main gallery.
        $unique = array();

        foreach ( $main_gallery as $item ) {
            // Duplicate items will replace the key in unique array.
            $unique[ $item->local_name ] = $item;
        }

        // prepend main gallery
        if ( !empty( $unique ) ) {
            array_unshift( $article_photos, array_values( $unique ) );
        }

        // Find images not in post content and add them to assets
        $other_images = array_diff( array_diff( $rendered_article_images[ 'images' ], $image_urls ), array_unique( $all_gallery_images ) );

        if ( !empty( $other_images ) ) {
            $arr = $this->generate_photos_array( $other_images, $rendered_content, false);
            $local_assets = array_merge( $local_assets, $arr );
        }


        $article->content_html_document = $rendered_content;
        $article->assets                = array_values( $local_assets );
        $article->photos                = $article_photos;

        return $article;
    }
}
