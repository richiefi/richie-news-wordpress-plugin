<?php
/**
 * HTML Components for Richie settings
 *
 * @link       https://www.richie.fi
 * @since 1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 */

/**
 * HTML Components for Richie settings
 *
 * @since 1.1.0
 * @package    Richie
 * @subpackage Richie/includes
 * @author     Markku Uusitupa <markku@richie.fi>
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

    /**
     * Render json editor for ad slot data
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
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

    /**
     * Render input field
     *
     * @param array $args  Rendering options.
     *
     * Rendering options (in args):
     *  string type: Input type.
     *  string value: Input value.
     *  string class: Input class name.
     *  string description: Description text after the element.
     *
     * @return void
     */
    public function input_field( array $args ) {
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

    /**
     * Render checkbox
     *
     * @param array $args  Rendering options.
     *
     * Rendering options (in args):
     *  string value: Input value (defaults to 1).
     *  boolean checked: If true, checkbox is checked initially.
     *  string description: Description text after the element.
     *
     * @return void
     */
    public function checkbox( array $args ) {
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . ']';
        $checked     = isset( $args['checked'] ) && true === $args['checked'] ? 'checked' : '';
        $value       = isset( $args['value'] ) ? $args['value'] : '1';

        printf( '<input type="checkbox" name="%s" value="%s" %s>', esc_attr( $name ), esc_attr( $value ), esc_attr( $checked ) );

        if ( isset( $args['description'] ) ) {
            printf( '<span class="description">%s</span>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render select box for pmpro levels
     *
     * @param array $args  Rendering options.
     *
     * Rendering options (in args):
     *  string value: Current level.
     *
     * @return void
     */
    public function pmpro_level( array $args ) {
        if ( false === richie_is_pmpro_active() ) {
            return;
        }

        $option_name   = $args['option_name'];
        $id            = $args['id'];
        $name          = $option_name . '[' . $id . ']';
        $current_level = isset( $args['value'] ) ? $args['value'] : '';
        $pmpro_levels  = pmpro_getAllLevels();

        ?>
        <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
            <option value="0"><?php esc_attr_e( 'Not used', 'richie' ); ?></option>
            <?php
            foreach ( $pmpro_levels as $level ) {
                $selected = selected( $current_level, $level->id, false );
                printf( "<option value='%s' %s>%s</option>", esc_attr( $level->id ), esc_attr( $selected ), esc_attr( $level->name ) );
            }
            ?>
        </select>
        <?php
    }

    /**
     * Render select box for article sets
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
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
            <a href="edit-tags.php?taxonomy=richie_article_set"><?php esc_html_e( 'Edit Article sets', 'richie' ); ?></a>
        </p>
        <?php
    }

    /**
     * Render checkbox list for available categories.
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
    public function category_list( array $args ) {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-category-walker.php';
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . '][]';

        $custom_walker = new Richie_Walker_Category_Checklist( null, $name );
        ?>
        <ul>
        <?php wp_category_checklist( 0, 0, false, false, $custom_walker ); ?>
        </ul>
        <?php
    }

    /**
     * Render select box for supported article ordering
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
    public function order_by( array $args ) {
        $metakeys    = [];
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . ']';

        // Check support for event views plugin.
        if ( function_exists( 'ev_get_meta_key' ) ) {
            $args['orderby']  = 'meta_value_num';
            $args['meta_key'] = ev_get_meta_key();
            $metakeys[]       = array(
                'key'     => ev_get_meta_key(),
                'orderby' => 'meta_value_num',
                'title'   => __( 'Post views', 'richie' ),
            );
        }

        ?>
            <select name='<?php echo esc_attr( $option_name ); ?>[order_by]' id='<?php echo esc_attr( $option_name ); ?>-order-by'>
                <option selected="selected" value="date"><?php esc_html_e( 'Post date', 'richie' ); ?></option>
                <option value="modified"><?php esc_html_e( 'Post modified time', 'richie' ); ?></option>
                <option value="title"><?php esc_html_e( 'Post title', 'richie' ); ?></option>
                <option value="author"><?php esc_html_e( 'Post author', 'richie' ); ?></option>
                <option value="id"><?php esc_html_e( 'Post ID', 'richie' ); ?></option>
                <?php foreach ( $metakeys as $metakey ) : ?>
                    <option value="metakey:<?php echo esc_attr( $metakey['key'] ); ?>:<?php echo esc_attr( $metakey['orderby'] ); ?>"><?php echo esc_html( $metakey['title'] ); ?></option>
                <?php endforeach; ?>
                <?php
                if ( class_exists( 'WPP_query' ) ) {
                    printf( '<option value="popular:last24hours">%s</option>', esc_html__( 'Popular posts (24 hours)', 'richie' ) );
                    printf( '<option value="popular:last7days">%s</option>', esc_html__( 'Popular posts (week)', 'richie' ) );
                    printf( '<option value="popular:last30days">%s</option>', esc_html__( 'Popular posts (month)', 'richie' ) );
                }
                ?>
            </select>
        <?php
    }

    /**
     * Render select box for order direction
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
    public function order_direction( array $args ) {
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . ']';

        ?>
            <select name='<?php echo esc_attr( $option_name ); ?>[order_direction]' id='<?php echo esc_attr( $option_name ); ?>-order-direction'>
                <option selected="selected" value="DESC"><?php esc_html_e( 'DESC', 'richie' ); ?></option>
                <option value="ASC"><?php esc_html_e( 'ASC', 'richie' ); ?></option>
            </select>
        <?php
    }

    /**
     * Render select box for given options
     *
     * @param array $args  Rendering options.
     *   string[] options Array of options.
     * @return void
     */
    public function select_field( array $args ) {
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . ']';
        $options     = $args['options'];
        $required    = isset( $args['required'] ) && true === $args['required'] ? 'required' : '';
        $selected    = isset( $args['selected'] ) ? $args['selected'] : null;

        ?>
        <select name='<?php echo esc_attr( $name ); ?>' id='<?php echo esc_attr( $id ); ?>' <?php echo esc_attr( $required ); ?>>
            <?php foreach ( $options as $opt ) : ?>
                <?php
                if ( isset( $opt['value'] ) ) {
                    $value = $opt['value'];
                    $title = isset( $opt['title'] ) ? $opt['title'] : $opt['value'];
                } else {
                    $value = $opt;
                    $title = $opt;
                }
                ?>
                <option value='<?php echo esc_attr( $value ); ?>' <?php selected( $selected, $value, true ); ?>><?php echo esc_attr( $title ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        if ( isset( $args['description'] ) ) {
            printf( '<div><span class="description">%s</span></div>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render radio button list for age options.
     *
     * @param array $args  Rendering options.
     *
     * @return void
     */
    public function max_age( array $args ) {
        $id          = $args['id'];
        $option_name = $args['option_name'];
        $name        = $option_name . '[' . $id . ']';

        $available_options = array(
            array( 'value' => '1 day', 'title' => sprintf( '%d %s', 1, _n( 'day', 'days', 1, 'richie' ) ) ),
            array( 'value' => '3 days', 'title' => sprintf( '%d %s', 3, _n( 'day', 'days', 3, 'richie' ) ) ),
            array( 'value' => '1 week', 'title' => sprintf( '%d %s', 1, _n( 'week', 'weeks', 1, 'richie' ) ) ),
            array( 'value' => '2 weeks', 'title' => sprintf( '%d %s', 2, _n( 'week', 'weeks', 2, 'richie' ) ) ),
            array( 'value' => '1 month', 'title' => sprintf( '%d %s', 1, _n( 'month', 'months', 1, 'richie' ) ) ),
            array( 'value' => '3 months', 'title' => sprintf( '%d %s', 3, _n( 'month', 'months', 3, 'richie' ) ) ),
            array( 'value' => '6 months', 'title' => sprintf( '%d %s', 6, _n( 'month', 'months', 6, 'richie' ) ) ),
            array( 'value' => '1 year', 'title' => __( '1 year', 'richie' ) ),
            array( 'value' => 'All time', 'title' => __( 'All time', 'richie' ) ),
        )
        ?>
        <fieldset>
            <?php foreach ( $available_options as $opt ) : ?>
            <div>
                <label>
                    <input type='radio' name='<?php echo esc_attr( $name ); ?>' value='<?php echo esc_attr( $opt['value'] ); ?>' <?php checked( 'All time', $opt['value'] ); ?>>
                    <span class="description"><?php echo esc_html( $opt['title'] ); ?></span>
                </label>
            </div>
            <?php endforeach; ?>
            <span class="description"><?php esc_attr_e( 'Include posts that are not older than specific time range', 'richie' ); ?>></span>
        </fieldset>
        <?php
    }
}
