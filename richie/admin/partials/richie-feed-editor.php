<?php
/**
 * Feed Editor Template
 *
 * This file provides the mount point for the React feed editor.
 *
 * @link       https://www.richie.fi
 * @since      2.0.0
 *
 * @package    Richie
 * @subpackage Richie/admin/partials
 */
?>

<div class="wrap richie-feed-editor-page">
	<h1><?php echo esc_html( get_admin_page_title() ); ?> - <?php esc_html_e( 'Feed Editor', 'richie' ); ?></h1>

	<div id="feed-editor-root"></div>
</div>
