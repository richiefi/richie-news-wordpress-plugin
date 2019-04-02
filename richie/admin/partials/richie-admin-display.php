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

<div class="wrap">
    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=settings' ) ?>" class="nav-tab <?php echo $active_tab == 'settings' || '' ? 'nav-tab-active' : ''; ?>">Settings</a>
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=sources' ) ?>" class="nav-tab <?php echo $active_tab == 'sources' ? 'nav-tab-active' : ''; ?>">News sources</a>
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=adslots' ) ?>" class="nav-tab <?php echo $active_tab == 'adslots' ? 'nav-tab-active' : ''; ?>">Ad slots</a>
        <a href="<?php echo admin_url( 'options-general.php?page=' . $this->settings_page_slug . '&tab=assets' ) ?>" class="nav-tab <?php echo $active_tab == 'assets' ? 'nav-tab-active' : ''; ?>">News assets</a>
    </h2>

    <?php if ( $active_tab === 'settings' ) : ?>
        <form method="post" name="richie-options" action="options.php">

            <?php
                settings_fields($this->settings_option_name);
                do_settings_sections($this->settings_option_name);
            ?>

            <?php submit_button('Save all changes', 'primary','submit', TRUE); ?>
        </form>

    <?php elseif ($active_tab === 'sources') : ?>
        <h3><?php _e('News sources', 'richie') ?></h3>
        <?php echo $this->source_list() ?>
        <hr>
        <form method="post" name="richie-source-form" action="options.php">
            <?php
                settings_fields($this->sources_option_name);
                do_settings_sections($this->sources_option_name);
                submit_button('Add source', 'primary','submit', TRUE);
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
                submit_button('Add ad slot', 'primary','submit', TRUE);
            ?>
        </form>
    <?php elseif ($active_tab === 'assets') : ?>
    <form method="post" name="richie-assets-feed" action="options.php">
    <?php
        settings_fields($this->assets_option_name);
        do_settings_sections($this->assets_option_name);
    ?>

    <?php submit_button('Save all changes', 'primary','submit', TRUE); ?>
    </form>
    <?php endif; ?>
</div>