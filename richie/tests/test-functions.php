<?php
/**
 * Class FunctionTest
 *
 * Tests plugin helper functions
 *
 * @package Richie
 */


/**
 * Helper function tests.
 */
class FunctionTest extends WP_UnitTestCase {

    public function test_make_link_absolute_no_protocol() {
        $url = richie_make_link_absolute('/testing/url/image.png');
        $this->assertEquals($url, 'http://example.org/testing/url/image.png');
    }

    public function test_make_link_absolute_includes_protocol() {
        $url = richie_make_link_absolute('https://richie.fi//image.png');
        // should not change url
        $this->assertEquals($url, 'https://richie.fi//image.png');
    }

    public function test_make_link_absolute_protocol_relative() {
        $url = richie_make_link_absolute('//richie.fi//image.png');
        // should prefix with https
        $this->assertEquals($url, 'https://richie.fi//image.png');
    }

    public function test_normalize_path_no_dots() {
        $path = richie_normalize_path('/test/path/somewhere.jpg');
        $this->assertEquals($path, '/test/path/somewhere.jpg');
    }

    public function test_normalize_path_single_dots() {
        $path = richie_normalize_path('/test/path/./somewhere.jpg');
        $this->assertEquals($path, '/test/path/somewhere.jpg');
    }

    public function test_normalize_path_double_dots() {
        $path = richie_normalize_path('/test/path/../somewhere.jpg');
        $this->assertEquals($path, '/test/somewhere.jpg');
    }

    public function test_normalize_path_no_leading_slash() {
        $path = richie_normalize_path('test/path/./../somewhere.jpg');
        $this->assertEquals($path, 'test/somewhere.jpg');
    }

    public function test_normalize_path_double_slashes() {
        $path = richie_normalize_path('/test///path/.././somewhere.jpg');
        $this->assertEquals($path, '/test/somewhere.jpg');
    }

    public function test_richie_make_local_name() {
        $test_urls = array(
            array( 'https://www.richie.fi/path/image.png', 'www.richie.fi/path/image.png' ),
            array( 'http://example.org/path/image.png', 'path/image.png' ),
            array( 'http://example.org/path/image/endingslash/', 'path/image/endingslash' ),
            array( '//www.richie.fi/protocol/relative/external.js', 'www.richie.fi/protocol/relative/external.js' ),
            array( '//protocol/relative/script.js', 'protocol/relative/script.js' ),
            array( '/absolute/url/style.css', 'absolute/url/style.css' ),
            array( 'relative/url/image.png', 'relative/url/image.png' ),
            array( 'http://example.org/path/version.css?ver=1234&test=1', 'path/version.css?test=1' )
        );

        foreach ( $test_urls as $case ) {
            $this->assertEquals( $case[1], richie_make_local_name( $case[0] ) );
        }
    }

    // --- richie_parse_srcset_best_url() tests ---

    public function test_parse_srcset_picks_highest_width() {
        $srcset = 'photo-300x200.jpg 300w, photo-800x533.jpg 800w, photo-1600x1067.jpg 1600w';
        $result = richie_parse_srcset_best_url( $srcset );
        $this->assertEquals( 'photo-1600x1067.jpg', $result );
    }

    public function test_parse_srcset_picks_highest_density() {
        $srcset = 'photo.jpg 1x, photo@2x.jpg 2x, photo@3x.jpg 3x';
        $result = richie_parse_srcset_best_url( $srcset );
        $this->assertEquals( 'photo@3x.jpg', $result );
    }

    public function test_parse_srcset_no_descriptor_returns_last() {
        $srcset = 'photo-small.jpg, photo-large.jpg';
        $result = richie_parse_srcset_best_url( $srcset );
        $this->assertEquals( 'photo-large.jpg', $result );
    }

    public function test_parse_srcset_single_entry_no_descriptor() {
        $result = richie_parse_srcset_best_url( 'photo.jpg' );
        $this->assertEquals( 'photo.jpg', $result );
    }

    public function test_parse_srcset_empty_returns_false() {
        $this->assertFalse( richie_parse_srcset_best_url( '' ) );
        $this->assertFalse( richie_parse_srcset_best_url( '   ' ) );
    }

    public function test_parse_srcset_single_width_entry() {
        $result = richie_parse_srcset_best_url( 'photo-1024x768.jpg 1024w' );
        $this->assertEquals( 'photo-1024x768.jpg', $result );
    }
}
