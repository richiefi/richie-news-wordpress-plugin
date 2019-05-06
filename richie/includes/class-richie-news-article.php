<?php
/**
 * Generates article for the Richie news feed.
 *
 * @link       https://www.richie.fi
 * @since      1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 */

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

        if ( richie_is_pmpro_active() ) {
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

    public function get_pmpro_levels( $my_post ) {
        if ( richie_is_pmpro_active() ) {
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

        $article->id      = $my_post->guid;
        $article->title   = $my_post->post_title;
        $article->summary = $my_post->post_excerpt;
        if ( $category ) {
            $article->kicker = $category[0]->name;
        }

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

        $urls = array_unique( wp_extract_urls( $rendered_content ) );

        $absolute_urls = array_map(
            function( $url ) {
                return richie_make_link_absolute( $url );
            },
            $urls
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

        $image_urls = [];
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( mb_convert_encoding( $rendered_content, 'HTML-ENTITIES', 'UTF-8' ) );
        // Get all the images.
        $images = $dom->getElementsByTagName( 'img' );

        // Loop the images.
        foreach ( $images as $image ) {
            $image_urls[] = $image->getAttribute( 'src' );
            $image->removeAttribute( 'srcset' );
        }

        // Remove duplicate urls.
        $image_urls = array_unique( $image_urls );

        // Save the HTML.
        $rendered_content = $dom->saveHTML();

        if ( $thumbnail_id ) {
            $thumbnail          = wp_get_attachment_image_url( $thumbnail_id, 'full' );
            $remote_url         = richie_make_link_absolute( $thumbnail );
            $article->image_url = $this->append_wpp_shadow( $remote_url );

            $all_sizes = get_intermediate_image_sizes();
            $all_sizes[] = 'full'; // Append full size also.
            foreach ( $all_sizes as $size ) {
                $thumbnail_url = null;
                if ( 'full' === $size ) {
                    // We already have full url.
                    $thumbnail_url = $thumbnail;
                } else {
                    $thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, $size );
                }
                if ( false !== strpos( $rendered_content, $thumbnail_url ) ) {
                    $local_name       = wp_make_link_relative( $thumbnail );
                    $local_name       = ltrim( $local_name, '/' );
                    $rendered_content = str_replace( $thumbnail_url, $local_name, $rendered_content );
                    $main_gallery[]   = array(
                        'caption'    => get_the_post_thumbnail_caption( $my_post ),
                        'local_name' => $local_name,
                        'remote_url' => $this->append_wpp_shadow( $remote_url ),
                    );
                    // Remove from general image array, since we have already handled this url.
                    $index = array_search( $thumbnail_url, $image_urls );
                    if ( false !== $index ) {
                        unset( $image_urls[ $index ] );
                    }
                }
            }
        }

        if ( ! $disable_url_handling ) {
            // Find first gallery and append it to photos array.
            $gallery = get_post_gallery( $my_post, false );
            if ( false !== $gallery ) {
                $ids = explode( ',', $gallery['ids'] );
                foreach ( $ids as $attachment_id ) {
                    $attachment = get_post( $attachment_id );
                    if ( null === $attachment ) {
                        continue;
                    }
                    $attachment_url = wp_get_attachment_url( $attachment->ID );
                    $local_name     = remove_query_arg( 'ver', wp_make_link_relative( $attachment_url ) );
                    $local_name     = ltrim( $local_name, '/' );
                    if ( false !== strpos( $rendered_content, richie_make_link_absolute( $attachment_url ) ) ) {
                        $rendered_content = str_replace( richie_make_link_absolute( $attachment_url ), $local_name, $rendered_content );
                    } else {
                        $rendered_content = str_replace( $attachment_url, $local_name, $rendered_content );
                    }
                    $main_gallery[] = array(
                        'caption'    => $attachment->post_excerpt,
                        'local_name' => $local_name,
                        'remote_url' => $this->append_wpp_shadow( richie_make_link_absolute( $attachment_url ) ),
                    );

                    // Remove from general image array, since we have already handled this url.
                    $index = array_search( $attachment_url, $image_urls );
                    if ( false !== $index ) {
                        unset( $image_urls[ $index ] );
                    }
                }
            }

            // Remove possible duplicate entries from main gallery.
            $unique = array();

            foreach ( $main_gallery as $item ) {
                // Duplicate items will replace the key in unique array.
                $unique[ $item['local_name'] ] = $item;
            }

            $article_photos[] = array_values( $unique );

            if ( $image_urls ) {
                $attachent_cache = [];
                $photos_array = [];
                foreach ( array_unique( $image_urls ) as $url ) {
                    // Remove size from the url, expects '-1000x230' format.
                    $base_url = preg_replace( '/(.+)(-\d+x\d+)(.+)/', '$1$3', $url );

                    $local_name = remove_query_arg( 'ver', wp_make_link_relative( $url ) );
                    if ( empty( $local_name ) ) {
                        continue;
                    }
                    $local_name = ltrim( $local_name, '/' );

                    if ( isset( $unique[ $local_name ] ) ) {
                        continue; // Already have image in gallery array.
                    }

                    $remote_url = richie_make_link_absolute( $url );

                    $attachment_id = false;
                    // if( isset( $attachment_cache[$base_url] ) ) {
                    //     $attachment_id = $attachment_cache[$base_url];
                    // } else {
                    //     $attachment_id = richie_get_image_id($base_url);
                    //     $attachment_cache[$base_url] = $attachment_id;
                    // }
                    if ( $attachment_id ) {
                        $attachment       = get_post( $attachment_id );
                        $attachment_url   = wp_get_attachment_url( $attachment->ID );
                        $rendered_content = str_replace( $url, $local_name, $rendered_content );
                        $photos_array[]   = array(
                            'caption'    => $attachment->post_excerpt,
                            'local_name' => $local_name,
                            'remote_url' => $remote_url,
                        );
                    } else {
                        $rendered_content = str_replace( $url, $local_name, $rendered_content );
                        $local_assets[]   = array(
                            'local_name' => $local_name,
                            'remote_url' => $this->append_wpp_shadow( $remote_url ),
                        );
                    }
                }
                if ( ! empty( $photos_array ) ) {
                    $article_photos[] = $photos_array;
                }
            }
        }

        $article->content_html_document = $rendered_content;
        $article->assets                = array_values( $local_assets );
        $article->photos                = $article_photos;

        return $article;
    }
}
