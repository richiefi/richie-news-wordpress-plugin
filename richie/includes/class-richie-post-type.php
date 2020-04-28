<?php
/**
 * Support for custom post
 *
 * @since      1.4.0
 * @package    Richie
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Post_Type {
    const CUSTOM_POST_TYPE_FEATURES = array(
        'mb_featured_post' => array(
            'hide_date' => true,
        ),
    );

    /**
     * Post type name
     *
     * @var [string]
     */
    public $post_type;

    /**
     * Instance of post type
     *
     * @param [string] $post_type Supported post type.
     */
    public function __construct( $post_type ) {
        $this->post_type = $post_type;
    }

    /**
     * Check if
     *
     * @param [string] $feature Feature to be checked.
     * @return boolean
     */
    public function check_feature( $feature ) {
        if ( ! $this->is_supported_post_type() ) {
            return false;
        }

        if ( isset( self::CUSTOM_POST_TYPE_FEATURES[ $this->post_type ] ) ) {
            $post_type_features = self::CUSTOM_POST_TYPE_FEATURES[ $this->post_type ];
            if ( isset( $post_type_features[ $feature ] ) ) {
                return $post_type_features[ $feature ];
            }
        }
        return false;
    }

    public function is_supported_post_type() {
        $valid_post_types = self::available_post_types();
        if ( in_array( $this->post_type, $valid_post_types ) ) {
            return true;
        }
        return false;
    }

    public function get_post( $original_post ) {
        if ( 'mb_featured_post' === $this->post_type ) {
            $target_url  = get_post_meta( $original_post->ID, 'featured_post_url', true );
            $target_post = url_to_postid( $target_url );
            if ( 0 === $target_post ) {
                return new stdClass(); // Return empty object.
            }
            $post = get_post( $target_post );
        } else {
            $post = get_post( $original_post );
        }
        return $post;
    }

    /**
     * Validate post type (is supported post type, passes post type specific checks).
     *
     * @param [WP_Post] $post WP Post object.
     * @return boolean
     */
    public static function validate_post( $post ) {
        $post_type = new Richie_Post_Type( $post->post_type );
        if ( ! $post_type->is_supported_post_type() ) {
            return false;
        }
        if ( 'post' !== $post->post_type ) {
            // Custom post type.
            if ( 'mb_featured_post' === $post->post_type ) {
                $target_url = get_post_meta( $post->ID, 'featured_post_url', true );
                if ( false === $target_url ) {
                    return false;
                }

                $target_id = url_to_postid( $target_url );

                if ( 0 === $target_id ) {
                    // Target url not internal wordpress page.
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get instance of given post type
     *
     * @param [WP_Post] $post WP Post object.
     * @return Richie_Post_Type
     */
    public static function get_post_type( $post ) {
        return new Richie_Post_Type( $post->post_type );
    }

    /**
     * Get list of supported post types
     *
     * @param string $return_type Return'object' or 'array'.
     * @return object|array
     */
    public static function available_post_types( $return_type = 'array' ) {
        $available_types = get_post_types( array(), 'objects' );
        $supported_types = array(
            array( 'value' => 'post', 'title' => $available_types['post']->label ),
        );

        if ( isset( $available_types['mb_featured_post'] ) ) {
            $type = $available_types['mb_featured_post'];
            $supported_types[] = array( 'value' => $type->name, 'title' => $type->label );
        }

        if ( 'object' === $return_type ) {
            return $supported_types;
        }

        return array_column( $supported_types, 'value' );
    }

}
