<?php
/**
 * Class Test_Richie_Request_Init
 *
 * @package Richie
 */

/**
 * Request init hook test suite.
 */
class Test_Richie_Request_Init extends WP_UnitTestCase {
    private $request_init_count = 0;

    private $original_get = array();

    private $original_server = array();

    public function setUp(): void {
        parent::setUp();

        $this->request_init_count = 0;
        $this->original_get       = $_GET;
        $this->original_server    = $_SERVER;

        add_action( 'richie_request_init', array( $this, 'on_request_init' ) );
    }

    public function tearDown(): void {
        remove_action( 'richie_request_init', array( $this, 'on_request_init' ) );

        $_GET    = $this->original_get;
        $_SERVER = $this->original_server;

        parent::tearDown();
    }

    public function on_request_init() {
        $this->request_init_count++;
    }

    public function throw_on_request_init() {
        throw new Exception( 'Test exception from richie_request_init' );
    }

    public function test_request_init_fires_for_pretty_richie_rest_route() {
        unset( $_GET['rest_route'] );
        $_SERVER['REQUEST_URI'] = '/wp-json/richie/v1/article/45/?token=testing';

        do_action( 'init' );

        $this->assertSame( 1, $this->request_init_count );
    }

    public function test_request_init_fires_for_rest_route_query_param() {
        $_GET['rest_route']      = '/richie/v1/article/45';
        $_SERVER['REQUEST_URI']  = '/index.php?rest_route=/richie/v1/article/45&token=testing';

        do_action( 'init' );

        $this->assertSame( 1, $this->request_init_count );
    }

    public function test_request_init_does_not_fire_for_non_richie_route() {
        $_GET['rest_route']      = '/wp/v2/posts';
        $_SERVER['REQUEST_URI']  = '/wp-json/wp/v2/posts';

        do_action( 'init' );

        $this->assertSame( 0, $this->request_init_count );
    }

    public function test_request_init_callback_exception_is_caught() {
        remove_action( 'richie_request_init', array( $this, 'on_request_init' ) );
        add_action( 'richie_request_init', array( $this, 'throw_on_request_init' ) );

        $_GET['rest_route']      = '/richie/v1/article/45';
        $_SERVER['REQUEST_URI']  = '/index.php?rest_route=/richie/v1/article/45&token=testing';

        ini_set( 'error_log', '/dev/null' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
        do_action( 'init' );
        ini_restore( 'error_log' );

        remove_action( 'richie_request_init', array( $this, 'throw_on_request_init' ) );
        add_action( 'richie_request_init', array( $this, 'on_request_init' ) );

        $this->assertSame( 0, $this->request_init_count );
    }
}
