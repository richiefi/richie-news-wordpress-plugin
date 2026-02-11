<?php
$template_path = get_query_var( 'richie_block_template_path' );
$template_slug = get_query_var( 'richie_block_template_slug' );

if ( $template_slug ) {
    echo richie_render_block_template_by_slug( $template_slug );
    return;
}

if ( ! $template_path || ! file_exists( $template_path ) ) {
    return;
}

echo richie_render_block_template_document( $template_path );
