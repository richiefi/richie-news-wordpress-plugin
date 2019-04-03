<?php
/**
 * Render form fields
 */
class Richie_Admin_Components {
    /**
     * Render json editor for asset feed
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
    public function asset_editor( array $args ) {
        if ( ! isset( $args['option_name'] ) ) {
            return;
        }

        $option_name = $args['option_name'];

        $assets = get_option( $option_name );
        if ( empty( $assets ) ) {
            $assets = [];
        }
        ?>
        <p>
            Accepts valid json. Example:
        </p>
        <pre>
    [
        {
            "remote_url": "https://example.com/path/to/asset.css",
            "local_name": "app-assets/asset.css"
        }
    ]
        </pre>
        <script>
            var assetUrl = "<?php echo esc_url( get_rest_url( null, '/richie/v1/assets' ) ); ?>";
        </script>
        <button id="generate-assets" type="button">Generate base list (overrides current content)</button>
        <textarea id="code_editor_page_js" rows="10" name="<?php echo esc_attr( $option_name ); ?>[data]" class="widefat textarea"><?php echo esc_html( wp_unslash( wp_json_encode( $assets, JSON_PRETTY_PRINT ) ) ); ?></textarea>
        <?php
    }

    public function adslot_ad_data_editor( array $args ) {
        if ( ! isset( $args['option_name'] ) ) {
            return;
        }

        $option_name          = $args['option_name'];
        $example_alternatives = array(
            'alternatives' => array(
                array(
                    'page_id'   => '',
                    'format_id' => '',
                    'min_width' => '',
                ),
            ),
        );
        ?>

        <textarea id="code_editor_page_js" rows="10" name="<?php echo esc_attr( $option_name ); ?>[ad_data]" class="textarea"><?php echo esc_html( wp_unslash( wp_json_encode( $example_alternatives, JSON_PRETTY_PRINT ) ) ); ?></textarea>
        <div style="font-size: 11px">
        <p>
            Accepts valid json array. Example:
        </p>
        <pre>
    {
        "alternatives": [
            {
                "page_id": 898073,
                "format_id": 62863,
                "min_width": 451
            },
            {
                "page_id": 898075,
                "format_id": 62863,
                "max_width": 450
            }
        ]
    }
        </pre>
        </div>
        <?php
    }

    public function input_field( array $args  ) {
        $option_name = $args['option_name'];
        $id          = $args['id'];
        $type        = isset( $args['type'] ) ? $args['type'] : 'text';
        $name        = $option_name . '[' . $id . ']';
        $value       = isset( $args['value'] ) ? $args['value'] : '';
        $class_name  = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        printf( '<input class="%s" type="%s" name="%s" value="%s">', esc_attr( $class_name ), esc_attr( $type ), esc_attr( $name ), esc_attr( $value ) );

        if ( isset( $args['description'] ) ) {
            printf( '<br><span class="description">%s</span>', esc_html( $args['description'] ) );
        }
    }

    public function checkbox_render( array $args ) {
        $current = isset( $args['current'] ) ? $args['current'] : '';
        $value = isset ( $args['value'] ) ? $args['value'] : '1';
        $checked = checked( $current, $value, false );
        $namespace = isset($args['namespace']) ? $args['namespace'] : $this->plugin_name;
        $name = $namespace . '[' . $args['id'] . ']';
        print "<input type='checkbox' name='$name' value='$value' $checked>";

        if ( isset( $args['description'] ) ) {
            printf('<span class="description">%s</span>', esc_html__( $args['description'], $this->plugin_name ));
        }
    }

    public function pmpro_level_render( array $args ) {
        $options = get_option( $this->plugin_name );
        $id = $args['id'];
        $current_level = isset($options{$id}) ? $options{$id} : '';
        $pmpro_levels = pmpro_getAllLevels();
        $name = $this->plugin_name . '[' . $args['id'] . ']';

        ?>
        <select name="<?php echo $name ?>" id="<?php echo $this->plugin_name; ?>-<?php echo $id; ?>">
            <option value="0"><?php esc_attr_e('Not used', $this->plugin_name );?></option>
            <?php
                foreach ( $pmpro_levels as $level ) {
                    $selected = selected( $current_level, $level->id, FALSE);
                    echo "<option value='{$level->id}' {$selected}>{$level->name}</option>";
                }
            ?>
        </select>
        <?php
    }


    public function source_name_render() {
        ?>
        <input class="regular-text" type='text' name='<?php echo $this->sources_option_name; ?>[source_name]'>
        <?php
    }

    public function article_set( array $args ) {

        $option_name = $args['option_name'];

        wp_dropdown_categories(
            array(
                'taxonomy'   => 'richie_article_set',
                'hide_empty' => false,
                'id'         => $option_name . '-article_set',
                'name'       => $option_name . '[article_set]',
            )
        );
        ?>
        <p>
            <a href="edit-tags.php?taxonomy=richie_article_set">Edit Richie Article Sets</a>
        </p>
        <?php
    }

    public function adprovider( array $args ) {
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . ']';

        $ad_providers = array( 'smart' );
        ?>
            <select name="<?php echo esc_attr( $name ); ?>">
                <?php foreach ( $ad_providers as $provider ) : ?>
                    <option value="<?php echo esc_attr( $provider ); ?>"><?php echo esc_attr( $provider ); ?></option>
                <?php endforeach; ?>
            </select>
        <?php
        if ( isset( $args['description'] ) ) {
            printf( '<br><span class="description">%s</span>', esc_html( $args['description'] ) );
        }
    }

    public function category_list_render() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-category-walker.php';

        $custom_walker = new Richie_Walker_Category_Checklist(null, $this->sources_option_name.'[source_categories][]');
        ?>
        <ul>
        <?php wp_category_checklist( 0, 0, false, false, $custom_walker ); ?>
        </ul>
        <?php
    }

    public function number_of_posts_render() {
        ?>
            <input class="small-text" type='text' name='<?php echo $this->sources_option_name ; ?>[number_of_posts]'>
            <span class="description"><?php esc_attr_e( 'Amount of posts included in the feed', $this->plugin_name ); ?></span>
        <?php
    }

    public function order_by_render() {
        $metakeys = [];
        // check support for event views plugin
        if ( function_exists( 'ev_get_meta_key' ) ) {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = ev_get_meta_key();
            $metakeys[] = array(
                'key' => ev_get_meta_key(),
                'orderby' => 'meta_value_num',
                'title' => 'Post views'
            );
        }

        ?>
            <select name='<?php echo $this->sources_option_name ; ?>[order_by]' id='<?php echo $this->sources_option_name ; ?>-order-by'>
                <option selected="selected" value="date">Post date</option>
                <option value="modified">Post modified time</option>
                <option value="title">Post title</option>
                <option value="author">Post author</option>
                <option value="id">Post ID</option>
                <?php foreach( $metakeys as $metakey ): ?>
                    <option value="metakey:<?php esc_attr_e($metakey['key']) ?>:<?php esc_attr_e($metakey['orderby']) ?>"><?php esc_attr_e($metakey['title']) ?></option>
                <?php endforeach; ?>
                <?php
                    if ( class_exists( 'WPP_query' ) ) {
                        echo '<option value="popular:last24hours">Popular posts (24 hours)</option>';
                        echo '<option value="popular:last7days">Popular posts (week)</option>';
                        echo '<option value="popular:last30days">Popular posts (month)</option>';
                    }
                ?>
            </select>
        <?php
    }

    public function order_direction_render() {
        ?>
            <select name='<?php echo $this->sources_option_name ; ?>[order_direction]' id='<?php echo $this->sources_option_name ; ?>-order-direction'>
                <option selected="selected" value="DESC">DESC</option>
                <option value="ASC">ASC</option>
            </select>
        <?php
    }

    public function list_layout_style_render() {
        ?>
        <select name='<?php echo $this->sources_option_name ; ?>[list_layout_style]' id='<?php echo $this->sources_option_name ; ?>-list_layout_style' required>
            <?php foreach( $this->available_layout_names as $layout_name ): ?>
                <option value='<?php echo $layout_name ?>'><?php echo $layout_name ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }


    public function list_group_title_render() {
        ?>
        <input class="regular-text" type='text' name='<?php echo $this->sources_option_name; ?>[list_group_title]'>
        <span class="description"><?php esc_attr_e( 'Header to display before the story, useful on the first
small_group_item of a group', $this->plugin_name ); ?>></span>
        <?php
    }

    public function max_age_render() {
        $available_options = array(
            '1 day',
            '3 days',
            '1 week',
            '2 weeks',
            '1 month',
            '3 months',
            '6 months',
            '1 year',
            'All time'
        )
        ?>
        <fieldset>
            <?php foreach( $available_options as $opt ): ?>
            <div>
                <label>
                    <input type='radio' name='<?php echo $this->sources_option_name; ?>[max_age]' value='<?php echo $opt; ?>' <?php checked('All time', $opt) ?>>
                    <span class="description"><?php _e($opt, $this->plugin_name) ?></span>
                </label>
            </div>
            <?php endforeach; ?>
            <span class="description"><?php esc_attr_e( 'Include posts that are not older than specific time range', $this->plugin_name ); ?>></span>
        </fieldset>
        <?php
    }


}
