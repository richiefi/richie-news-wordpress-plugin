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
}
