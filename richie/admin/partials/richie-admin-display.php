<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie
 * @subpackage Richie/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<?php
$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'settings';
?>

<div class="wrap richie-settings">
    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=settings' ) ?>" class="nav-tab <?php echo $active_tab == 'settings' || '' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'richie') ?></a>
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=sources' ) ?>" class="nav-tab <?php echo $active_tab == 'sources' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('News sources', 'richie') ?></a>
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=adslots' ) ?>" class="nav-tab <?php echo $active_tab == 'adslots' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Ad slots', 'richie') ?></a>
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=assets' ) ?>" class="nav-tab <?php echo $active_tab == 'assets' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('News assets', 'richie') ?></a>
    </h2>

    <?php if ( $active_tab === 'settings' ) : ?>
        <form method="post" name="richie-options" action="options.php">

            <?php
                settings_fields($this->settings_option_name);
                do_settings_sections($this->settings_option_name);
            ?>

            <?php submit_button(esc_html__('Save all changes', 'richie'), 'primary','submit', TRUE); ?>
        </form>

    <?php elseif ($active_tab === 'sources') : ?>
        <h3><?php _e('News sources', 'richie') ?></h3>
        <?php echo $this->source_list() ?>
        <h3><?php _e( 'Add new feed source', 'richie' ) ?></h3>
        <hr>
        <form method="post" name="richie-source-form" action="options.php">
            <?php
                settings_fields($this->sources_option_name);
                do_settings_sections($this->sources_option_name);
                submit_button(esc_html__('Add source', 'richie'), 'primary','submit', TRUE);
            ?>
        </form>
    <?php elseif ( $active_tab === 'adslots' ) : ?>
        <h3><?php _e('Ad slots', 'richie') ?></h3>
        <?php echo $this->adslot_list() ?>
        <hr>
        <form method="post" name="richie-adslots-form" action="options.php">
            <?php
                settings_fields($this->adslots_option_name);
                do_settings_sections($this->adslots_option_name);
                submit_button(esc_html__('Add ad slot', 'richie'), 'primary','submit', TRUE);
            ?>
        </form>
    <?php elseif ($active_tab === 'assets') : ?>
    <form method="post" name="richie-assets-feed" action="options.php">
    <?php
        settings_fields($this->assets_option_name);
        do_settings_sections($this->assets_option_name);
    ?>

    <?php submit_button(esc_html__('Save all changes', 'richie'), 'primary','submit', TRUE); ?>
    </form>
    <?php endif; ?>
</div>