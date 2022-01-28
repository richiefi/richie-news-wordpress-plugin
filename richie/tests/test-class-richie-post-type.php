<?php
/**
 * Class Test_Richie_Post_Type
 *
 * Tests Richie_Post_Type class
 *
 * @package Richie
 */


/**
 * Helper function tests.
 */
class Test_Richie_Post_Type extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
        $args = array(
            'labels'   => array(
                'name' => 'Featured Posts',
            ),
            'supports' => array( 'title' ),
        );
        register_post_type('mb_featured_post', $args);
        register_post_type('not_support_post', $args);
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-post-type.php';
    }

    public function test_returns_available_post_types_array() {
        $types = Richie_Post_Type::available_post_types();
        $this->assertEquals( $types, array( 'post', 'mb_featured_post' ) );
    }

    public function test_returns_available_post_types_object() {
        $types = Richie_Post_Type::available_post_types( 'object' );
        $expected = array(
            array( 'value' => 'post', 'title' => 'Posts' ),
            array( 'value' => 'mb_featured_post', 'title' => 'Featured Posts' ),
        );

        $this->assertEquals( $expected, $types );
    }

    public function test_validate_post_post() {
        $post = $this->factory->post->create_and_get(
            array(
                'post_type'  => 'post',
                'post_title' => 'My Title',
            )
        );

        $is_valid = Richie_Post_Type::validate_post( $post );
        $this->assertTrue( $is_valid );
    }

    public function test_validate_post_featured_post() {
        $target_post = $this->factory->post->create_and_get();
        $post = $this->factory->post->create_and_get(
            array(
                'post_type'  => 'mb_featured_post',
                'post_title' => 'My Title',
            )
        );
        update_post_meta( $post->ID, 'featured_post_url', get_permalink( $target_post ) );
        $is_valid = Richie_Post_Type::validate_post( $post );
        $this->assertTrue( $is_valid );
    }

    public function test_validate_post_featured_post_external_url() {
        $post = $this->factory->post->create_and_get(
            array(
                'post_type'  => 'mb_featured_post',
                'post_title' => 'My Title',
            )
        );
        update_post_meta( $post->ID, 'featured_post_url', 'https://www.richie.fi' );
        $is_valid = Richie_Post_Type::validate_post( $post );
        $this->assertEquals( $is_valid, 'https://www.richie.fi' );
    }

    public function test_check_featured_post_field_support_custom_type() {
        $post_type = new Richie_Post_Type( 'mb_featured_post' );
        $this->assertFalse( $post_type->supports_property( 'date' ) ); // Should hide dates for featured post
        $this->assertTrue( $post_type->supports_property( 'unknown_field' ) ); // Returns true as default (for supported post types)
    }

    public function test_check_featured_post_field_support_post_type() {
        $post_type = new Richie_Post_Type( 'post' );
        $this->assertTrue( $post_type->supports_property( 'date' ) ); // Post should support all fields
        $this->assertTrue( $post_type->supports_property( 'unknown_field' ) ); // Returns true as default (for supported post types)
    }

    public function test_check_featured_post_field_support_unknown_post_type() {
        $post_type = new Richie_Post_Type( 'custom_post_type' );
        $this->assertFalse( $post_type->supports_property( 'date' ) ); // Always false for unsupported post type
        $this->assertFalse( $post_type->supports_property( 'unknown_field' ) ); // Always false for unsupported post type
    }

    public function test_is_supported_post_type() {
        $not_supported = new Richie_Post_Type( 'not_supported' );
        $supported = new Richie_Post_Type( 'mb_featured_post' );
        $this->assertTrue( $supported->is_supported_post_type() );
        $this->assertFalse( $not_supported->is_supported_post_type() );
    }
}