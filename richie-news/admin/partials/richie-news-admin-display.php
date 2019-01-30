<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie_News
 * @subpackage Richie_News/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<div class="wrap">
    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <form method="post" name="richie-news-options" action="options.php">
        <?php
            //Grab all options
            $options = get_option($this->plugin_name);
            $metered_pmpro_level = $options['metered_pmpro_level'];
            $member_only_pmpro_level = $options['member_only_pmpro_level'];
        ?>
        <?php
            settings_fields($this->plugin_name);
            do_settings_sections($this->plugin_name);
        ?>
        <fieldset>
            <h3><?php _e( 'Paywall levels', $this->plugin_name ); ?></h3>
            <?php $pmpro_levels = pmpro_getAllLevels(); ?>
            <label>
                <?php esc_attr_e( 'Metered level', $this->plugin_name ); ?>
                <select name="<?php echo $this->plugin_name; ?>[metered-level]" id="<?php echo $this->plugin_name; ?>-metered-level">
                    <option value="0"><?php esc_attr_e('Not used', $this->plugin_name );?></option>
                    <?php
                        foreach ( $pmpro_levels as $level ) {
                            $selected = selected( $metered_pmpro_level, $level->id, FALSE);
                            echo "<option value='{$level->id}' {$selected}>{$level->name}</option>";
                        }
                    ?>
                </select>
                <span class="description"><?php esc_attr_e('PMPro level for metered content (metered)', $this->plugin_name); ?></span>
            </label>
            <br>
            <label>
                <?php esc_attr_e( 'Member only level', $this->plugin_name ); ?>
                <select name="<?php echo $this->plugin_name; ?>[member-only-level]" id="<?php echo $this->plugin_name; ?>-member-only-level">
                    <option value="0"><?php esc_attr_e('Not used', $this->plugin_name );?></option>
                    <?php
                        foreach ( $pmpro_levels as $level ) {
                            $selected = selected( $member_only_pmpro_level, $level->id, FALSE);
                            echo "<option value='{$level->id}' {$selected}>{$level->name}</option>";
                        }
                    ?>
                </select>
                <span class="description"><?php esc_attr_e('PMPro level for member only content (no_access)', $this->plugin_name); ?></span>
            </label>
        </fieldset>
        <?php submit_button('Save all changes', 'primary','submit', TRUE); ?>
    </form>
</div>