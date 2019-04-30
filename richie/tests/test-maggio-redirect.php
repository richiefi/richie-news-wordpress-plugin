<?php
/**
 * Class MaggioRedirectTest
 *
 * Tests maggio signin redirection
 *
 * @package Richie
 */

/**
 * Maggio redirect tests.
 */
class MaggioRedirectTest extends WP_UnitTestCase {
    protected $host_name = 'https://test.hostname';

	public function setUp() {
        parent::setUp();
        update_option(
            'richie',
            array(
                'maggio_hostname' => $this->host_name,
                'maggio_secret' => 'test-secret',
                'maggio_required_pmpro_level' => 1,
            )
        );
    }

    public function tearDown() {
        delete_option('richie');
        parent::tearDown();
    }

    public function test_redirection() {

        $maggio_service = $this->getMockBuilder( Richie_Maggio_Service::class )
        ->setConstructorArgs( [ $this->hostname ] )
        ->setMethods( [ 'is_issue_free' ] )
        ->getMock();

        // fake free issue
        $maggio_service->method( 'is_issue_free' )
        ->willReturn( true );

        $richie_public = $this->getMockBuilder( Richie_Public::class )
        ->setConstructorArgs( array( 'richie', 'version' ) )
        ->setMethods( [ 'do_redirect', 'get_maggio_service' ] )
        ->getMock();

        $richie_public->method( 'get_maggio_service' )
        ->willReturn( $maggio_service );

        $richie_public->expects( $this->once() )
            ->method( 'do_redirect' )
            ->with( $this->stringContains( 'https://test.hostname/_signin/' ) );

        $uuid    = wp_generate_uuid4();
        $wp_mock = new StdClass();

        $wp_mock->query_vars = array(
            'maggio_redirect' => $uuid,
        );

        $richie_public->maggio_redirect_request( $wp_mock );

    }
}