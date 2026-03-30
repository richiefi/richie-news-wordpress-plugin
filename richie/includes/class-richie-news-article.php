<?php
/**
 * Generates article for the Richie news feed.
 *
 * @link       https://www.richie.fi
 * @since      1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 */

require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-photo-asset.php';
require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-post-type.php';


/**
 * Generates article for the Richie news feed.
 *
 * @since      1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Article {

    const EXCLUDE_NONE     = 0b0001; // 1
    const EXCLUDE_METADATA = 0b0010; // 2
    const EXCLUDE_CONTENT  = 0b0100; // 4

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
     * Adds scale_to_device_dimensions: true to photo assets
     *
     * @var bool
     */
    private $scale_images;

    /**
     * API version
     *
     * @var int
     */
    private $api_version;

    /**
     * Create instance of richie news article generator
     *
     * @param array $richie_options Richie plugin options.
     * @param array $assets Available asset items.
     * @param int   $version Article version.
     */
    public function __construct( $richie_options, $assets = array(), $version = 1 ) {
        $this->news_options = $richie_options;
        $this->assets       = $assets;
        $this->scale_images = false;
        $this->api_version  = $version;
        if ( $version >= 3 ) {
            $this->scale_images = true;
        }
    }

    /**
     * Render template as string
     *
     * @param [type] $slug Template slug.
     * @param [type] $name Template variation name.
     * @param [type] $post_obj Post object.
     * @return string
     */
    public function render_template( $slug, $name, $post_obj ) {
        global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $user_ID, $wp_styles, $wp_scripts, $wp_filter;
        require_once plugin_dir_path( __FILE__ ) . 'class-richie-template-loader.php';

        $wp_query = new WP_Query( //phpcs:ignore
            array(
                'p' => $post_obj->ID,
            )
        );

        // Override global '$post'. This is required for rendering to work, templates may use global '$post'.
        $post = $post_obj; //phpcs:ignore
        $wp_query->setup_postdata( $post_obj );

        // Modify urls to have scheme.
        add_filter( 'script_loader_src', 'richie_force_url_scheme' );
        add_filter( 'style_loader_src', 'richie_force_url_scheme' );

        // Remove jquery-migrate from article output — not needed for rendered articles.
        wp_deregister_script( 'jquery-migrate' );
        wp_register_script( 'jquery-migrate', false, array(), false, false );

        $resolved         = richie_resolve_template( $slug, $name, $this->news_options );
        $rendered_content = '';

        switch ( $resolved['type'] ) {
            case 'block_path':
                $rendered_content = richie_render_block_template_document( $resolved['path'] );
                break;

            case 'block_slug':
                $rendered_content = richie_render_block_template_by_slug( $resolved['slug'] );
                break;

            case 'php':
                $richie_template_loader = new Richie_Template_Loader();
                ob_start();
                $richie_template_loader->get_template_part( $resolved['slug'], $resolved['name'] );
                $rendered_content = ob_get_clean();
                break;
        }

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

    public function get_article_assets() {
        // Expects global $wp_scripts and $wp_styles.
        return richie_get_article_assets();
    }

    /**
     * Parse html content and return dom object
     *
     * @param string $content HTML string.
     * @return object
     */
    public function parse_dom( $content ) {
        $dom                     = new IvoPetkov\HTML5DOMDocument();
        $dom->substituteEntities = false;
        $dom->preserveWhiteSpace = false;
        libxml_use_internal_errors( true );
        $dom->loadHTML( $content, IvoPetkov\HTML5DOMDocument::ALLOW_DUPLICATE_IDS );
        return $dom;
    }

    /**
     * Append mraid.js script tag to head
     *
     * @param string $content HTML string.
     */
    public function add_mraid_tag( $dom ) {
        $head = $dom->querySelector( 'head' );
        if ( ! $head ) {
            return;
        }
        $first_script = $head->getElementsByTagName( 'script' )->item( 0 );
        $mraid_tag    = $dom->createElement( 'script' );
        $mraid_tag->setAttribute( 'src', 'mraid.js' );
        if ( $first_script ) {
            // We have script in head, insert before it.
            $head->insertBefore( $mraid_tag, $first_script );
        } else {
            // No other scripts in head, append tag to head element.
            $head->appendChild( $mraid_tag );
        }
    }

    /**
     * Check whether a string looks like a URL or file path.
     *
     * Used to detect lazyload data-* attributes that hold an image URL.
     *
     * @param string $value Attribute value.
     * @return bool
     */
    private function looks_like_url( $value ) {
        if ( '' === $value ) {
            return false;
        }

        // Absolute or protocol-relative.
        if ( preg_match( '#^(?:https?:)?//#i', $value ) ) {
            return true;
        }

        // Root-relative.
        if ( '/' === $value[0] ) {
            return true;
        }

        // Relative path that includes an extension (e.g. images/photo.jpg).
        return (bool) preg_match( '#^[^?\s]+\.[a-z0-9]{2,8}(?:\?.*)?$#i', $value );
    }

    /**
     * Extract image URLs referenced via CSS url(...) tokens in a style attribute value.
     * Returns only URLs that pass richie_is_image_url().
     *
     * @param string $style_value Inline style attribute value.
     * @return string[] Absolute image URLs.
     */
    private function extract_style_image_urls( $style_value ) {
        $urls = array();

        if ( ! preg_match_all( '/url\(\s*[\'"]?([^\'"\)\s]+)[\'"]?\s*\)/i', $style_value, $matches ) ) {
            return $urls;
        }

        foreach ( $matches[1] as $raw ) {
            if ( richie_is_image_url( $raw ) ) {
                $urls[] = richie_make_link_absolute( $raw );
            }
        }

        return $urls;
    }

    /**
     * Rewrite CSS url(...) image tokens in a style attribute value using $url_map.
     *
     * @param string   $style_value Inline style attribute value.
     * @param string[] $url_map     Map of absolute URL => local name.
     * @return string Rewritten style value.
     */
    private function rewrite_style_image_urls( $style_value, $url_map ) {
        return preg_replace_callback(
            '/url\(\s*[\'"]?([^\'"\)\s]+)[\'"]?\s*\)/i',
            function ( $m ) use ( $url_map ) {
                $raw = $m[1];
                if ( ! richie_is_image_url( $raw ) ) {
                    return $m[0];
                }
                $abs = richie_make_link_absolute( $raw );
                if ( isset( $url_map[ $abs ] ) ) {
                    return 'url(' . $url_map[ $abs ] . ')';
                }
                // Rewrite to absolute even when not in map, so offline fetch is possible.
                return 'url(' . $abs . ')';
            },
            $style_value
        );
    }

    /**
     * Parse HTML content, discover all image URLs, and rewrite them to local names.
     *
     * Handles:
     * - <img src> (always)
     * - <img data-src>, <img data-full>, and other data-* attributes that look like URLs (lazyload)
     * - <img srcset> is stripped; src is filled with the first lazyload URL when src is empty
     * - inline style="background-image: url(...)" on any element
     * - any attribute on any element whose value passes richie_is_image_url()
     *
     * @param object   $dom     DOM object.
     * @param string[] $url_map Optional map of absolute URL => local name for pre-known images
     *                          (thumbnails, gallery attachments). When a URL is in the map the
     *                          attribute value is rewritten to the local name; otherwise it is
     *                          rewritten to the absolute URL so the app can still fetch it.
     * @return array { images: string[], content: string }
     */
    public function get_article_images( $dom, $url_map = array(), &$attachment_cache = array() ) {
        $image_urls = array();

        // Only scan the body — head and noscript elements are not rendered article content.
        $body = $dom->getElementsByTagName( 'body' )->item( 0 );

        if ( ! $body ) {
            return array(
                'images'  => $image_urls,
                'content' => $dom->saveHTML( $dom->documentElement ),
            );
        }

        foreach ( $body->getElementsByTagName( '*' ) as $element ) {
            // Skip contents of <noscript> and <script> — not rendered as visual HTML.
            $parent = $element->parentNode;
            while ( $parent ) {
                if ( XML_ELEMENT_NODE === $parent->nodeType ) {
                    $pname = strtolower( $parent->tagName );
                    if ( 'noscript' === $pname || 'script' === $pname ) {
                        continue 2;
                    }
                }
                $parent = $parent->parentNode;
            }

            if ( ! $element->hasAttributes() ) {
                continue;
            }

            $tag_name     = strtolower( $element->tagName );
            $is_img       = 'img' === $tag_name || 'source' === $tag_name;
            $fallback_src = ''; // Used to fill empty src from lazyload data-* attrs.
            $srcset_value = ''; // Collected from srcset / data-*srcset for best-candidate resolution.

            // Collect attributes once (iterating live NamedNodeMap while modifying it is unsafe).
            $attrs = array();
            foreach ( $element->attributes as $attr ) {
                $attrs[ $attr->name ] = $attr->value;
            }

            foreach ( $attrs as $attr_name => $attr_value ) {
                $attr_value = trim( $attr_value );

                if ( '' === $attr_value ) {
                    continue;
                }

                // Inline style: extract and rewrite image url() tokens.
                if ( 'style' === $attr_name ) {
                    $image_urls = array_merge( $image_urls, $this->extract_style_image_urls( $attr_value ) );
                    $element->setAttribute( 'style', $this->rewrite_style_image_urls( $attr_value, $url_map ) );
                    continue;
                }

                // Strip srcset-format attributes (srcset, data-srcset, data-lazy-srcset, etc.).
                // Save the value first so we can pick the best candidate URL from it.
                // Other data-* attributes (data-src, data-full, data-valign, ...) are NOT matched here
                // and continue to be handled by the generic data-* URL detection below.
                $is_srcset_attr = ( 'srcset' === $attr_name ) ||
                    ( 0 === strpos( $attr_name, 'data-' ) && preg_match( '/srcset$/i', $attr_name ) );

                if ( $is_srcset_attr ) {
                    // Keep the srcset value with the most entries for best-candidate selection.
                    if ( substr_count( $attr_value, ',' ) >= substr_count( $srcset_value, ',' ) ) {
                        $srcset_value = $attr_value;
                    }
                    $element->removeAttribute( $attr_name );
                    continue;
                }

                // For <img>/<source>: trust src unconditionally, and trust data-* when it looks like a URL.
                // For other elements: only include clearly image URLs.
                $is_img_src       = $is_img && 'src' === $attr_name;
                $is_img_data_url  = $is_img && 0 === strpos( $attr_name, 'data-' ) && $this->looks_like_url( $attr_value );
                $is_known_img_url = richie_is_image_url( $attr_value );

                if ( ! $is_img_src && ! $is_img_data_url && ! $is_known_img_url ) {
                    continue;
                }

                $abs_url      = richie_make_link_absolute( $attr_value );
                $image_urls[] = $abs_url;

                $local_value = isset( $url_map[ $abs_url ] ) ? $url_map[ $abs_url ] : $abs_url;
                $element->setAttribute( $attr_name, $local_value );

                // Track first data-* image URL as fallback for empty src.
                if ( $is_img && 'src' !== $attr_name && '' === $fallback_src ) {
                    $fallback_src = $local_value;
                }
            }

            // For <img>/<source>: fill empty src from the first lazyload data-* URL.
            if ( $is_img ) {
                $current_src = trim( $element->getAttribute( 'src' ) );
                if ( '' === $current_src && '' !== $fallback_src ) {
                    $current_src = $fallback_src;
                    $element->setAttribute( 'src', $current_src );
                }

                // Resolve best image: prefer WP media library full size, then best srcset candidate.
                // Skip if src is already a local name (i.e. already in url_map — known attachment).
                $known_local_names = array_values( $url_map );
                if ( '' !== $current_src && ! in_array( $current_src, $known_local_names, true ) ) {
                    $abs_src  = richie_make_link_absolute( $current_src );
                    $best_url = richie_resolve_best_image_url( $abs_src, $srcset_value, $attachment_cache );

                    if ( $best_url !== $abs_src && '' !== $best_url ) {
                        // A better (larger) URL was found — update src and register it.
                        $best_local = isset( $url_map[ $best_url ] ) ? $url_map[ $best_url ] : $best_url;
                        $element->setAttribute( 'src', $best_local );
                        $image_urls[] = $best_url;

                        // Also rewrite any data-* attributes that still point to the original src.
                        foreach ( $attrs as $attr_name => $attr_value ) {
                            if ( 0 === strpos( $attr_name, 'data-' ) ) {
                                $current_val = $element->getAttribute( $attr_name );
                                if ( $current_val === $abs_src || $current_val === $current_src ) {
                                    $element->setAttribute( $attr_name, $best_local );
                                }
                            }
                        }
                    }
                }
            }
        }

        $image_urls = array_values( array_unique( $image_urls ) );
        $html       = $dom->saveHTML( $dom->documentElement );

        return array(
            'images'  => $image_urls,
            'content' => $html,
        );
    }

    /**
     * Create an asset array from image urls. Optionally tries to link them to an attachment.
     * Finding attachment by url is really slow, so it is disabled as default.
     * It also replaces urls found in passed content string with generated local_name.
     *
     * @param array  $image_urls Array of image urls.
     * @param string $rendered_content Passed as reference, modifies content.
     * @return array
     */
    public function generate_photos_array( $image_urls, &$rendered_content, $scale_image = false ) {
        if ( empty( $image_urls ) ) {
            return array();
        }

        $attachment_cache = array();
        $results          = array();

        foreach ( array_unique( $image_urls ) as $url ) {
            $photo_asset = new Richie_Photo_Asset( $url, false, $scale_image );
            $results[]   = $photo_asset;

            $encoded_url      = richie_encode_url_path( $url );
            $rendered_content = str_replace( $url, $photo_asset->local_name, $rendered_content );
            $rendered_content = str_replace( $encoded_url, $photo_asset->local_name, $rendered_content );
        }

        return $results;
    }

    public function generate_article( $original_post, $exclude = self::EXCLUDE_NONE, $template_name = 'article' ) {
        if ( empty( $original_post ) ) {
            return new stdClass(); // Return empty object.
        }

        $without_content  = false;
        $without_metadata = false;

        if ( $exclude & self::EXCLUDE_CONTENT ) {
            $without_content = true;
        }

        if ( $exclude & self::EXCLUDE_METADATA ) {
            $without_metadata = true;
        }

        $article = new stdClass();

        if ( $this->api_version >= 3 ) {
            $article->publisher_id = strval( $original_post->ID );
        } else {
            $article->id = strval( $original_post->ID );
        }

        $article->title = $original_post->post_title;

        $revisions     = wp_get_post_revisions( $original_post );
        $published_rev = array_pop( $revisions );

        $article->analytics_data = array(
            'wp_post_id'     => $original_post->ID,
            'original_title' => isset( $published_rev->post_title ) ? $published_rev->post_title : $original_post->post_title,
        );

        $richie_post_type = Richie_Post_Type::get_post_type( $original_post );
        $external         = Richie_Post_Type::validate_post( $original_post );

        if ( $external && is_string( $external ) ) {
            $article->external_browser_url = $external;
            $article->share_link_url       = $external;
        }

        $my_post = $richie_post_type->get_post( $original_post );

        if ( ! isset( $my_post->ID ) ) {
            return $article; // didn't get post, return partial article (this might be because of featured post type, which points to external url)
        }

        $hash = md5( wp_json_encode( $my_post ) );

        // Get metadata.
        $post_id  = $my_post->ID;
        $category = get_the_category( $post_id );

        $article->share_link_url = get_permalink( $post_id );

        if ( ! $without_metadata ) {
            $article->hash = $hash; // TODO: Undocumented field - verify if still needed.

            if ( $richie_post_type->supports_property( 'summary' ) && ! empty( $my_post->post_excerpt ) ) {
                $article->summary = $my_post->post_excerpt;
            }

            if ( $category && $richie_post_type->supports_property( 'kicker' ) ) {
                $article->kicker = $category[0]->name;
            }

            if ( $richie_post_type->supports_property( 'date' ) ) {
                $date = new DateTime( $my_post->post_date_gmt );
            }

            if ( $richie_post_type->supports_property( 'updated_date' ) ) {
                $updated_date  = new DateTime( $my_post->post_modified_gmt );
                $article->date = $date->format( 'c' );

                $diff = $updated_date->getTimestamp() - $date->getTimestamp();

                // Include updated_date if its at least 5 minutes after creation date.
                if ( $diff >= 5 * MINUTE_IN_SECONDS ) {
                    $article->updated_date = $updated_date->format( 'c' );
                }
            }

            // Include the thumbnail if found.
            $thumbnail_id = get_post_thumbnail_id( $my_post );

            if ( $thumbnail_id ) {
                $thumbnail          = wp_get_attachment_image_url( $thumbnail_id, 'full' );
                $remote_url         = richie_make_link_absolute( $thumbnail );
                $article->image_url = $this->append_wpp_shadow( $remote_url );
            }
        }

        // Access control: add entitlements based on premium categories.
        // Only include in article endpoint (not in section feeds).
        if ( ! $without_content ) {
            $richie_options     = get_option( 'richie' );
            $premium_categories = isset( $richie_options['premium_categories'] ) ? (array) $richie_options['premium_categories'] : array();

            if ( ! empty( $premium_categories ) ) {
                $post_categories       = wp_get_post_categories( $my_post->ID );
                $matching_premium_cats = array_intersect( $post_categories, $premium_categories );

                $access_entitlements = array();
                $default_entitlement = isset( $richie_options['default_entitlement'] ) ? $richie_options['default_entitlement'] : '';

                if ( ! empty( $matching_premium_cats ) ) {
                    if ( ! empty( $default_entitlement ) ) {
                        // Use the configured default entitlement for all premium articles.
                        $access_entitlements[] = $default_entitlement;
                    } else {
                        // Fall back to category names converted to UPPER_SNAKE_CASE.
                        foreach ( $matching_premium_cats as $cat_id ) {
                            $cat = get_category( $cat_id );
                            if ( $cat ) {
                                $entitlement           = strtoupper( str_replace( array( ' ', '-' ), '_', $cat->name ) );
                                $access_entitlements[] = $entitlement;
                            }
                        }
                    }
                }

                /**
                 * Filter the access entitlements for an article.
                 *
                 * Use this filter to implement custom access control logic, such as
                 * integration with membership plugins or per-article overrides.
                 *
                 * @since 1.2.0
                 *
                 * @param string[] $access_entitlements Array of entitlement strings (e.g., ['PREMIUM']).
                 *                                      Empty array = free article.
                 * @param WP_Post  $post                The post object.
                 */
                try {
                    $filtered_entitlements = apply_filters( 'richie_article_access_entitlements', $access_entitlements, $my_post );

                    // Validate that filter returns an array.
                    if ( is_array( $filtered_entitlements ) ) {
                        // Validate all values are strings (filter may append invalid values).
                        $validated_entitlements = array_filter( $filtered_entitlements, 'is_string' );

                        if ( count( $validated_entitlements ) !== count( $filtered_entitlements ) ) {
                            // Log if some values were filtered out due to being non-string.
                            error_log( 'Warning: richie_article_access_entitlements filter returned non-string values for post ID ' . $my_post->ID );
                        }

                        $access_entitlements = $validated_entitlements;
                    } else {
                        // Log if filter returns invalid type, but don't crash.
                        error_log( 'Warning: richie_article_access_entitlements filter returned non-array type for post ID ' . $my_post->ID );
                    }
                } catch ( Exception $e ) {
                    // Catch any exceptions from filter and log without crashing.
                    error_log( 'Error in richie_article_access_entitlements filter for post ID ' . $my_post->ID . ': ' . $e->getMessage() );
                }

                if ( ! empty( $access_entitlements ) ) {
                    $article->access_entitlements = array_values( array_unique( $access_entitlements ) );
                }
            }
        }

        if ( ! $without_content ) {
            if ( is_array( $this->news_options ) ) {
                $token = $this->news_options['access_token'];
            } else {
                $token = '';
            }

            $content_args = array(
                'richie_news' => 1,
                'token'       => $token,
            );
            if ( 'article' !== $template_name ) {
                $content_args['template'] = $template_name;
            }
            $content_url = add_query_arg( $content_args, get_permalink( $post_id ) );

            $content_url = $this->append_wpp_shadow( $content_url );

            $disable_url_handling = false;

            if ( isset( $_GET['disable_asset_parsing'] ) ) {
                $disable_url_handling = $_GET['disable_asset_parsing'] === '1';
            }

            $use_local_render = true;
            if ( isset( $_GET['use_local_render'] ) ) {
                $use_local_render = $_GET['use_local_render'] === '1';
            }

            // $article->debug_content_url = $content_url;

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
                        $article->from_cache = false; // TODO: Undocumented field - verify if still needed.
                    } else {
                        $rendered_content = __( 'Failed to get content', 'richie' );
                        if ( is_wp_error( $response ) ) {
                            $article->content_error = $response->get_error_message(); // TODO: Undocumented field - verify if still needed.
                        }
                    }
                } else {
                    $article->from_cache = true; // TODO: Undocumented field - verify if still needed.
                }
            }

            // Snapshot which handles are already done before rendering, so we can find
            // exactly the handles emitted by this article's render (not accumulated ones).
            global $wp_scripts, $wp_styles;
            $done_scripts_before = isset( $wp_scripts->done ) ? $wp_scripts->done : array();
            $done_styles_before  = isset( $wp_styles->done ) ? $wp_styles->done : array();

            // Render locally to get assets.
            $local_rendered_content = $this->render_template( 'richie-news', $template_name, $my_post );

            if ( $use_local_render ) {
                $rendered_content = $local_rendered_content;
            }

            // Collect only the handles newly emitted by this article render.
            $new_script_handles = array_diff( isset( $wp_scripts->done ) ? $wp_scripts->done : array(), $done_scripts_before );
            $new_style_handles  = array_diff( isset( $wp_styles->done ) ? $wp_styles->done : array(), $done_styles_before );
            $article_assets     = richie_collect_registered_assets( $new_script_handles, $new_style_handles, '' );

            // Find local article assets (shortcodes etc).
            $local_assets = array_udiff(
                $article_assets,
                $this->assets,
                function ( $a, $b ) {
                    return strcmp( $a->remote_url, $b->remote_url );
                }
            );

            // Replace asset urls with localname.
            foreach ( $this->assets as $asset ) {
                $local_name       = ltrim( $asset->local_name, '/' );
                $rendered_content = str_replace( $asset->remote_url, $local_name, $rendered_content );
                // Also match the relative form with query string (e.g. /style.css?ver=1.0).
                $rel = richie_make_link_relative_with_query( $asset->remote_url );
                if ( '' === $rel ) {
                    continue;
                }
                $regex            = '/(?<!app-assets)' . preg_quote( $rel, '/' ) . '/';
                $rendered_content = preg_replace( $regex, $local_name, $rendered_content );
            }

            // Replace local assets.
            foreach ( $local_assets as $asset ) {
                $local_name       = ltrim( $asset->local_name, '/' );
                $rendered_content = str_replace( $asset->remote_url, $local_name, $rendered_content );
                $rel              = richie_make_link_relative_with_query( $asset->remote_url );
                if ( '' === $rel ) {
                    continue;
                }
                $regex            = '/(?<!app-assets)' . preg_quote( $rel, '/' ) . '/';
                $rendered_content = preg_replace( $regex, $local_name, $rendered_content );
            }

            // Strip any remaining ?ver= query strings from already-rewritten local paths so the
            // app can resolve them without version-aware cache busting.
            $rendered_content = preg_replace( '#((?:app-assets|wp-content|wp-includes)/[^"\']+?)\?ver=[^"\']+#', '$1', $rendered_content );

            $article_photos = array();

            $main_gallery = array();
            $thumbnail_id = get_post_thumbnail_id( $my_post );
            $all_sizes    = get_intermediate_image_sizes();
            $all_sizes[]  = 'full'; // Append full size also.

            // Build a URL map so get_article_images() can rewrite known images to local names
            // in a single DOM pass, instead of doing post-hoc str_replace on rendered HTML.
            // Map: absolute variant URL => canonical full-size Richie_Photo_Asset local_name.
            $url_map            = array(); // absolute variant URL => local_name
            $photo_asset_map    = array(); // canonical URL => Richie_Photo_Asset
            $all_gallery_images = array();
            // Pre-populate attachment cache with IDs we already know, avoiding redundant DB queries.
            $attachment_cache   = array();

            if ( $thumbnail_id ) {
                $thumbnail                         = wp_get_attachment_image_url( $thumbnail_id, 'full' );
                $canonical_url                     = richie_make_link_absolute( $thumbnail );
                $photo_asset                       = new Richie_Photo_Asset( $canonical_url, false, $this->scale_images );
                $photo_asset->caption              = get_the_post_thumbnail_caption( $my_post );
                $photo_asset_map[ $canonical_url ] = $photo_asset;

                // Pre-populate cache so get_article_images() won't re-query for the thumbnail.
                $attachment_cache[ $canonical_url ] = $thumbnail_id;

                foreach ( $all_sizes as $size ) {
                    $size_url = ( 'full' === $size ) ? $thumbnail : wp_get_attachment_image_url( $thumbnail_id, $size );
                    if ( ! empty( $size_url ) ) {
                        $abs_size_url              = richie_make_link_absolute( $size_url );
                        $url_map[ $abs_size_url ]  = $photo_asset->local_name;
                        // Cache all size variant URLs as well.
                        $attachment_cache[ $abs_size_url ] = $thumbnail_id;
                    }
                }
            }

            if ( ! $disable_url_handling ) {
                $galleries = get_post_galleries( $my_post, false );

                foreach ( $galleries as $gallery ) {
                    $gallery_photos = array();

                    if ( false !== $gallery ) {
                        $ids = explode( ',', $gallery['ids'] );
                        foreach ( $ids as $attachment_id ) {
                            $attachment = get_post( $attachment_id );
                            if ( null === $attachment ) {
                                continue;
                            }
                            $attachment_url = wp_get_attachment_url( $attachment->ID );
                            $canonical_url  = richie_make_link_absolute( $attachment_url );

                            $photo_asset                       = new Richie_Photo_Asset( $attachment_url, false, $this->scale_images );
                            $photo_asset->caption              = $attachment->post_excerpt;
                            $photo_asset_map[ $canonical_url ] = $photo_asset;
                            $gallery_photos[]                  = $photo_asset;
                            $all_gallery_images[]              = $canonical_url;

                            // Map all size variants of this attachment to its local name.
                            $url_map[ $canonical_url ]              = $photo_asset->local_name;
                            $attachment_cache[ $canonical_url ]     = (int) $attachment_id;
                            foreach ( $all_sizes as $size ) {
                                $img = wp_get_attachment_image_src( $attachment_id, $size );
                                if ( false !== $img ) {
                                    $abs_img_url                        = richie_make_link_absolute( $img[0] );
                                    $url_map[ $abs_img_url ]            = $photo_asset->local_name;
                                    $attachment_cache[ $abs_img_url ]   = (int) $attachment_id;
                                }
                            }
                        }
                    }

                    if ( ! empty( $gallery_photos ) ) {
                        $article_photos[] = $gallery_photos;
                    }
                }
            }

            $dom = $this->parse_dom( $rendered_content );
            $this->add_mraid_tag( $dom );

            // Single DOM pass: discover all image URLs and rewrite known ones to local names.
            $rendered_article_images = $this->get_article_images( $dom, $url_map, $attachment_cache );
            $image_urls              = $rendered_article_images['images'];
            $rendered_content        = $rendered_article_images['content'];

            // Separate out thumbnail and gallery URLs from the general image list.
            if ( $thumbnail_id ) {
                $thumbnail     = wp_get_attachment_image_url( $thumbnail_id, 'full' );
                $canonical_url = richie_make_link_absolute( $thumbnail );

                foreach ( $all_sizes as $size ) {
                    $size_url     = ( 'full' === $size ) ? $thumbnail : wp_get_attachment_image_url( $thumbnail_id, $size );
                    $abs_size_url = richie_make_link_absolute( $size_url );
                    $index        = array_search( $abs_size_url, $image_urls, true );

                    if ( false !== $index ) {
                        if ( isset( $photo_asset_map[ $canonical_url ] ) && empty( $main_gallery ) ) {
                            $main_gallery[] = $photo_asset_map[ $canonical_url ];
                        }
                        $all_gallery_images[] = $abs_size_url;
                        unset( $image_urls[ $index ] );
                        break; // Only need to find it once — all sizes map to the same asset.
                    }
                }
            }

            foreach ( $all_gallery_images as $gallery_url ) {
                $index = array_search( $gallery_url, $image_urls, true );
                if ( false !== $index ) {
                    unset( $image_urls[ $index ] );
                }
            }

            if ( $image_urls ) {
                $img_list     = $this->generate_photos_array( $image_urls, $rendered_content, $this->scale_images );
                $main_gallery = array_merge( $main_gallery, $img_list );
            }

            // Remove possible duplicate entries from main gallery.
            $unique = array();

            foreach ( $main_gallery as $item ) {
                // Duplicate items will replace the key in unique array.
                $unique[ $item->local_name ] = $item;
            }

            // Prepend main gallery.
            if ( ! empty( $unique ) ) {
                array_unshift( $article_photos, array_values( $unique ) );
            }

            // Find images not in post content and add them to assets.
            $other_images = array_diff( array_diff( $rendered_article_images['images'], $image_urls ), array_unique( $all_gallery_images ) );

            if ( ! empty( $other_images ) ) {
                $arr          = $this->generate_photos_array( $other_images, $rendered_content, false );
                $local_assets = array_merge( $local_assets, $arr );
            }

            $article->content_html_document = $rendered_content;
            $article->assets                = array_values( $local_assets );
            $article->photos                = $article_photos;
        }

        return $article;
    }
}
