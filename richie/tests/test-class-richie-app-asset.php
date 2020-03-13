<?php
/**
 * Class Test_Richie_App_Asset
 *
 * Tests Richie_App_Asset class
 *
 * @package Richie
 */


/**
 * Helper function tests.
 */
class Test_Richie_App_Asset extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-app-asset.php';
    }
    public function test_returns_app_asset_object() {
        $dependency = new stdClass();
        $dependency->src = 'test/styles/style.css';
        $dependency->ver = '1.0.5';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( $app_asset->remote_url, 'http://example.org/test/styles/style.css?ver=1.0.5' );
        $this->assertEquals( $app_asset->local_name, 'prefix/test/styles/style.css' );
    }

    public function test_returns_app_asset_object_absolute_path() {
        $dependency = new stdClass();
        $dependency->src = 'http://example.org/test/styles/style.css';
        $dependency->ver = '1.0.5';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( $app_asset->remote_url, 'http://example.org/test/styles/style.css?ver=1.0.5' );
        $this->assertEquals( $app_asset->local_name, 'prefix/test/styles/style.css' );
    }

    public function test_returns_app_asset_object_strip_extra_slashes() {
        $dependency = new stdClass();
        $dependency->src = '///test/styles/style.css';
        $dependency->ver = '1.0.2';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( $app_asset->remote_url, 'http://example.org/test/styles/style.css?ver=1.0.2' );
        $this->assertEquals( $app_asset->local_name, 'prefix/test/styles/style.css' );
    }

    public function test_returns_app_asset_object_with_normalized_path() {
        $dependency = new stdClass();
        $dependency->src = '/test/src/../scripts/./test.js';
        $dependency->ver = '1.0.0';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( $app_asset->remote_url, 'http://example.org/test/src/../scripts/./test.js?ver=1.0.0' );
        $this->assertEquals( $app_asset->local_name, 'prefix/test/scripts/test.js' );
    }

    public function test_returns_app_asset_as_json_string() {
        $dependency = new stdClass();
        $dependency->src = 'test/styles/style.css';
        $dependency->ver = '1.0.5';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( strval( $app_asset ), '{"local_name":"prefix\/test\/styles\/style.css","remote_url":"http:\/\/example.org\/test\/styles\/style.css?ver=1.0.5"}' );
    }

    public function test_returns_app_asset_object_external_url() {
        $dependency = new stdClass();
        $dependency->src = 'http://external.org/test/styles/style.css';
        $dependency->ver = '1.0.5';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( $app_asset->remote_url, 'http://external.org/test/styles/style.css?ver=1.0.5' );
        $this->assertEquals( $app_asset->local_name, 'prefix/external.org/test/styles/style.css' );
    }

    public function test_returns_app_asset_object_external_url_without_protocl() {
        $dependency = new stdClass();
        $dependency->src = '//external.org/test/styles/style.css';
        $dependency->ver = '1.0.5';
        $app_asset = new Richie_App_Asset( $dependency, 'prefix/' );
        $this->assertEquals( $app_asset->remote_url, 'http://external.org/test/styles/style.css?ver=1.0.5' );
        $this->assertEquals( $app_asset->local_name, 'prefix/external.org/test/styles/style.css' );
    }
}
