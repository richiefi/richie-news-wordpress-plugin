<?php
/**
 * Richie helper functions
 *
 * @package richie
 * @subpackage richie/includes
 */


/**
 * Sort array by key AND value
 *
 * Array is sorted by key first and if multiple equal keys, by value.
 * Note: sorting is done in place, modifying the given argument
 *
 * @since 1.0.0
 *
 * @param  array {
 *      @type string $key
 *      @type string $value
 * } $array Array to be sorted.
 *
 * @return array Sorted array
 */
function richie_key_value_sort( $array ) {
    usort(
        $array,
        function( $a, $b ) {
            if ( $a['key'] === $b['key'] ) {
                return strcmp( $a['value'], $b['value'] );
            }
            return strcmp( $a['key'], $b['key'] );
        }
    );

    return $array;
}


/**
 * Build sorted query string
 *
 * @param  array {
 *      @type string $key
 *      @type string $value
 * } $params  key-value pairs
 *
 * @return string Query string key=value&key2=value2&... sorted by key and value
 */
function richie_build_query( $params ) {
    $sorted = richie_key_value_sort( $params );
    $mapper = function( $param ) {
        return $param['key'] . '=' . $param['value'];
    };
    $pairs  = array_map( $mapper, $sorted );
    return implode( '&', $pairs );
}

/**
 * Modify original WordPress function with custom query (adding LIMIT 1).
 * This should make function to perform faster.
 *
 * @see https://core.trac.wordpress.org/ticket/41281
 *
 * @param string $url   Attachment url.
 * @return int post id
 */
function richie_attachment_url_to_postid( $url ) {
    global $wpdb;

    $dir  = wp_get_upload_dir();
    $path = $url;

    $site_url   = wp_parse_url( $dir['url'] );
    $image_path = wp_parse_url( $path );

    //force the protocols to match if needed
    if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
        $path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
    }

    if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
        $path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
    }

    $sql     = $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
        $path
    );
    $post_id = $wpdb->get_var( $sql );

    /**
     * Filters an attachment id found by URL.
     *
     * @since 4.2.0
     *
     * @param int|null $post_id The post_id (if any) found by the function.
     * @param string   $url     The URL being looked up.
     */
    return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
}

/**
 * Makes link absolute.
 * If starting with http(s), return as unchanged. If missing protocol, add https.
 * Otherwise prepend site url.
 *
 * @param string $url Url to modify.
 * @return string Absolute url
 */
function richie_make_link_absolute( $url ) {
    if ( substr( $url, 0, 4 ) === 'http' ) {
        return $url;
    } elseif ( substr( $url, 0, 2 ) === '//' ) {
        return 'https:' . $url;
    } else {
        return get_site_url( null, $url );
    }
}

function richie_make_local_name( $url ) {
    // If scheme not included, prepend it
    if (!preg_match('#^http(s)?://#', $url)) {
        $url = set_url_scheme( $url );
    }

    // remove version string
    $url = remove_query_arg( 'ver', $url );

    // remove local host
    $url = str_replace( get_site_url(), '', $url );

    $url_parts = wp_parse_url($url);

    // get domain
    $domain = isset( $url_parts['host'] ) ? $url_parts['host'] : '';

    // get path
    $path = isset( $url_parts['path'] ) ? rtrim( $url_parts['path'], '/' ) : '';

    // get query
    $query = isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '';

    // create url
    $local_name = ltrim( $domain . $path . $query, '/' );
    return $local_name;
}

/**
 * Get image id for image url
 *
 * @param string $image_url Url to the image.
 * @return int
 */
function richie_get_image_id( $image_url ) {
	global $wpdb;
    $attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s;", $image_url ) );
    if ( ! empty( $attachment ) ) {
        return $attachment[0];
    }
    return false;
}

/**
 * Detect if url points to an image based on extension
 *
 * @param string $image_url Url to the image.
 * @return boolean
 */
function richie_is_image_url( $image_url ) {
    if ( ! isset( $image_url ) ) {
        return false;
    }
    $allowed_extensions = array( 'png', 'jpg', 'gif' );
    $path = wp_parse_url( $image_url, PHP_URL_PATH );
    if ( $path ) {
        $filetype = wp_check_filetype( $path );
        $extension = $filetype['ext'];
        if ( in_array( $extension, $allowed_extensions ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Get assets from global $wp_scripts and $wp_styles
 *
 * @return array
 */
function richie_get_article_assets() {
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-app-asset.php';

    // Get all scripts.
    global $wp_scripts, $wp_styles;
    $article_assets = array();
    foreach ( $wp_scripts->do_items() as $script_name ) {
        $script = $wp_scripts->registered[ $script_name ];
        $remote_url = $script->src;
        if ( ( substr( $remote_url, -3 ) === '.js' ) && ! strpos( $remote_url, 'wp-admin' ) ) {
            $article_assets[] = new Richie_App_Asset( $script, '' );
        }
    }
    // Print all loaded Styles (CSS).
    foreach ( $wp_styles->do_items() as $style_name ) {
        $style = $wp_styles->registered[ $style_name ];
        $remote_url = $style->src;
        if ( ( substr( $remote_url, -4 ) === '.css' ) && ! strpos( $remote_url, 'wp-admin' ) ) {
            $article_assets[] = new Richie_App_Asset( $style, '' );
        }
    }

    return $article_assets;
}


/**
 * Normalize path
 */
function richie_normalize_path( $path ) {
    $normalized_path = wp_normalize_path( $path );
    $path_segments = explode ('/', $normalized_path );
    $stack = array();
    foreach ( $path_segments as $seg ) {
        if ( $seg === '..' ) {
            // Ignore this and remove last one
            array_pop($stack);
            continue;
        }

        if ( $seg === '.' ) {
            // Ignore this segment
            continue;
        }

        $stack[] = $seg;
    }

    return implode('/', $stack);
}

function richie_encode_url_path( $url ) {
    $encoded = preg_replace_callback('#://([^/]+)/([^?]+)#', function ($match) {
        return '://' . $match[1] . '/' . join('/', array_map('rawurlencode', explode('/', $match[2])));
    }, $url);

    return $encoded;
}

function richie_force_url_scheme( $url ) {
    // this should handle also protocol relative urls
    $parsed_url = wp_parse_url( $url );

    if ( $parsed_url !== false) {
        if ( empty( $parsed_url['scheme'] ) ) {
            if ( isset( $parsed_url['host'] ) ) {
                // absolute url without protocol, set it based on site protocol
                $url = set_url_scheme( $url );
            }
        }
    }
    return $url;
}

/**
 * Build template file names for HTML templates.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return array
 */
function richie_get_html_template_names( $slug, $name = null ) {
    return richie_get_template_names( $slug, $name, '.html' );
}

/**
 * Validate that a resolved template path is within one of the allowed base directories.
 *
 * @param string $candidate Resolved template path.
 * @param array  $allowed_dirs Allowed base directories.
 * @return bool
 */
function richie_is_valid_template_path( $candidate, $allowed_dirs ) {
    $real = realpath( $candidate );
    if ( false === $real ) {
        return false;
    }
    foreach ( $allowed_dirs as $dir ) {
        $real_dir = realpath( $dir );
        if ( false !== $real_dir && 0 === strpos( $real, $real_dir . DIRECTORY_SEPARATOR ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Locate a template file in the given directories.
 *
 * @param array $template_names Filenames to look for, in priority order.
 * @param array $paths          Directories to search, in priority order.
 * @return string|null Absolute path to the first match, or null.
 */
function richie_locate_template_file( $template_names, $paths ) {
    foreach ( $template_names as $template_name ) {
        foreach ( $paths as $path ) {
            $candidate = $path . ltrim( $template_name, '/' );
            if ( file_exists( $candidate ) && richie_is_valid_template_path( $candidate, $paths ) ) {
                return $candidate;
            }
        }
    }

    return null;
}

/**
 * Get the standard theme directories for Richie templates.
 *
 * @return array
 */
function richie_get_theme_template_dirs() {
    return array(
        trailingslashit( get_stylesheet_directory() ) . 'richie/',
        trailingslashit( get_template_directory() ) . 'richie/',
    );
}

/**
 * Build template file names for a given extension.
 *
 * @param string $slug      Template slug.
 * @param string $name      Template variation name.
 * @param string $extension File extension including dot (e.g. '.html').
 * @return array
 */
function richie_get_template_names( $slug, $name = null, $extension = '.html' ) {
    $templates = array();
    if ( isset( $name ) && '' !== $name ) {
        $templates[] = $slug . '-' . $name . $extension;
    }
    $templates[] = $slug . $extension;

    return $templates;
}

/**
 * Locate a HTML template file in theme or plugin paths.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return string|null
 */
function richie_locate_html_template( $slug, $name = null ) {
    $templates  = richie_get_template_names( $slug, $name, '.html' );
    $paths      = richie_get_theme_template_dirs();
    $paths[]    = trailingslashit( Richie_PLUGIN_DIR ) . 'templates/';
    $paths      = apply_filters( 'richie_html_template_paths', $paths, $slug, $name );

    return richie_locate_template_file( $templates, $paths );
}

/**
 * Locate a HTML template file in the active theme or parent theme only.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return string|null
 */
function richie_locate_theme_html_template( $slug, $name = null ) {
    return richie_locate_template_file(
        richie_get_template_names( $slug, $name, '.html' ),
        richie_get_theme_template_dirs()
    );
}

/**
 * Locate a PHP template file in the active theme or parent theme only.
 *
 * @param string $slug Template slug.
 * @param string $name Template variation name.
 * @return string|null
 */
function richie_locate_theme_php_template( $slug, $name = null ) {
    return richie_locate_template_file(
        richie_get_template_names( $slug, $name, '.php' ),
        richie_get_theme_template_dirs()
    );
}

/**
 * Render a block HTML template into a full HTML document string.
 *
 * @param string $template_path Absolute path to the HTML template.
 * @return string
 */
function richie_render_block_template_document( $template_path ) {
    $template_contents = file_get_contents( $template_path );

    if ( $template_contents === false ) {
        return '';
    }

    return richie_render_block_template_document_from_content( $template_contents );
}

/**
 * Render block template content into a full HTML document string.
 *
 * @param string $template_contents Block template markup.
 * @return string
 */
function richie_render_block_template_document_from_content( $template_contents ) {
    if ( empty( $template_contents ) ) {
        return '';
    }

    $rendered = do_blocks( $template_contents );

    ob_start();
    wp_head();
    $head_assets = ob_get_clean();

    ob_start();
    wp_footer();
    $footer_assets = ob_get_clean();

    $has_head = stripos( $rendered, '<head' ) !== false;
    $has_body = stripos( $rendered, '<body' ) !== false;

    if ( $has_head && $has_body ) {
        // Template is a full document — inject assets into existing tags.
        $rendered = preg_replace( '/<\/head>/i', $head_assets . "\n</head>", $rendered, 1 );
        $rendered = preg_replace( '/<\/body>/i', $footer_assets . "\n</body>", $rendered, 1 );
    } else {
        // Template is block markup only — wrap in a full document.
        $rendered = "<!doctype html>\n" .
            "<html>\n" .
            "<head>\n" . $head_assets . "\n</head>\n" .
            "<body>\n" . $rendered . "\n" . $footer_assets . "\n</body>\n" .
            "</html>\n";
    }

    return $rendered;
}

/**
 * Check whether block template rendering is enabled.
 *
 * @param array|null $options Plugin options array. If null, reads from database.
 * @return bool
 */
function richie_use_block_template( $options = null ) {
    if ( null === $options ) {
        $options = get_option( 'richie' );
    }
    return is_array( $options ) && ! empty( $options['use_block_template'] );
}

/**
 * Resolve which template should be used for rendering, without actually rendering.
 *
 * Returns a descriptor array with 'type' and additional keys depending on the type:
 *   - { type: 'block_path', path: string }  — HTML block template file to render with do_blocks().
 *   - { type: 'block_slug', slug: string }   — Site Editor template to render by slug.
 *   - { type: 'php', slug: string, name: string } — PHP template to load via Richie_Template_Loader.
 *
 * @param string     $slug    Template slug (e.g. 'richie-news').
 * @param string     $name    Template variation name (e.g. 'article').
 * @param array|null $options Plugin options array. If null, reads from database.
 * @return array Template descriptor.
 */
function richie_resolve_template( $slug, $name, $options = null ) {
    // 1. Theme HTML template override.
    $theme_html = richie_locate_theme_html_template( $slug, $name );
    if ( $theme_html ) {
        return array( 'type' => 'block_path', 'path' => $theme_html );
    }

    // 2. Theme PHP template override.
    $theme_php = richie_locate_theme_php_template( $slug, $name );
    if ( $theme_php ) {
        return array( 'type' => 'php', 'slug' => $slug, 'name' => $name );
    }

    // 3. Site Editor block template (block themes only).
    if ( richie_use_block_template( $options ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
        $block_slug = richie_get_block_template_slug();
        if ( richie_get_block_template_by_slug( $block_slug ) ) {
            return array( 'type' => 'block_slug', 'slug' => $block_slug );
        }
    }

    // 4. Plugin HTML template fallback.
    $plugin_html = richie_locate_html_template( $slug, $name );
    if ( $plugin_html ) {
        return array( 'type' => 'block_path', 'path' => $plugin_html );
    }

    // 5. Legacy PHP template fallback.
    return array( 'type' => 'php', 'slug' => $slug, 'name' => $name );
}

/**
 * Get the block template slug used for Richie articles.
 *
 * @return string
 */
function richie_get_block_template_slug() {
    return 'richie-article';
}

/**
 * Find a block template by slug, preferring custom templates.
 *
 * @param string $slug Template slug.
 * @return WP_Block_Template|null
 */
function richie_get_block_template_by_slug( $slug ) {
    if ( ! function_exists( 'get_block_templates' ) ) {
        return null;
    }

    $templates = get_block_templates(
        array(
            'slug__in' => array( $slug ),
        ),
        'wp_template'
    );

    if ( empty( $templates ) ) {
        return null;
    }

    $priority = array(
        'custom' => 0,
        'theme'  => 1,
        'plugin' => 2,
    );

    usort(
        $templates,
        function( $a, $b ) use ( $priority ) {
            $a_priority = isset( $priority[ $a->source ] ) ? $priority[ $a->source ] : 99;
            $b_priority = isset( $priority[ $b->source ] ) ? $priority[ $b->source ] : 99;
            return $a_priority <=> $b_priority;
        }
    );

    return $templates[0];
}

/**
 * Render a block template by slug into a full HTML document.
 *
 * @param string $slug Template slug.
 * @return string
 */
function richie_render_block_template_by_slug( $slug ) {
    $template = richie_get_block_template_by_slug( $slug );
    if ( ! $template || empty( $template->content ) ) {
        return '';
    }

    return richie_render_block_template_document_from_content( $template->content );
}
