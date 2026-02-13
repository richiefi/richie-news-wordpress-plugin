<?php
$template_path = get_query_var( 'richie_block_template_path' );
$template_slug = get_query_var( 'richie_block_template_slug' );

if ( $template_slug ) {
    $slug = sanitize_title( $template_slug );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full HTML document output (contains <html>, <head>, <script> tags).
    echo richie_render_block_template_by_slug( $slug );
    return;
}

if ( ! $template_path || ! file_exists( $template_path ) ) {
    return;
}

// Validate path is within allowed directories.
$allowed_dirs = array(
    trailingslashit( get_stylesheet_directory() ) . 'richie/',
    trailingslashit( get_template_directory() ) . 'richie/',
    trailingslashit( Richie_PLUGIN_DIR ) . 'templates/',
);
if ( ! richie_is_valid_template_path( $template_path, $allowed_dirs ) ) {
    return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full HTML document output (contains <html>, <head>, <script> tags).
echo richie_render_block_template_document( $template_path );
