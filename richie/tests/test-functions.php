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
}
