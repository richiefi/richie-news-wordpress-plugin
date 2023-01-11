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
    protected $hostname = 'https://test.hostname';

	public function setUp() {
        parent::setUp();
        update_option(
            'richie',
            array(
                'maggio_hostname' => $this->hostname,
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

    public function test_redirection_on_error_external_referer() {
        $richie_public = $this->getMockBuilder( Richie_Public::class )
        ->setConstructorArgs( array( 'richie', 'version' ) )
        ->setMethods( [ 'do_redirect' ] )
        ->getMock();

        // fake referer.
        $referer = 'https://test.hostname/test/referrer';
        $_REQUEST['_wp_http_referer'] = $referer;

        $richie_public->expects( $this->once() )
            ->method( 'do_redirect' )
            ->with( get_home_url() ); // Should redirect to home url.

        add_filter(
            'allowed_redirect_hosts',
            function ( $content )  {
                $content[] = 'test.hostname';
                return $content;
            }
        );

        $richie_public->redirect_to_referer();

    }

    public function test_redirection_on_error_internal_referer() {
        $richie_public = $this->getMockBuilder( Richie_Public::class )
        ->setConstructorArgs( array( 'richie', 'version' ) )
        ->setMethods( [ 'do_redirect' ] )
        ->getMock();

        // fake referer.
        $referer = get_home_url() . '/test/referer';
        $_REQUEST['_wp_http_referer'] = $referer;

        $richie_public->expects( $this->once() )
            ->method( 'do_redirect' )
            ->with( $referer ); // Should redirect to referer.

        add_filter(
            'allowed_redirect_hosts',
            function ( $content )  {
                $content[] = 'test.hostname';
                return $content;
            }
        );

        $richie_public->redirect_to_referer();

    }

    public function test_redirection_with_params() {

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
            ->with(
                $this->logicalAnd(
                    $this->stringContains( 'https://test.hostname/_signin/' ),
                    $this->stringContains( '&page=1' ),
                    $this->stringContains( '&q=search%20term' ),
                    $this->logicalNot( $this->stringContains( 'should_not_be_included' ) )
                )
            );

        $uuid    = wp_generate_uuid4();
        $wp_mock = new StdClass();

        $wp_mock->query_vars = array(
            'maggio_redirect' => $uuid,
            'page'            => 1,
            'search'          => 'search%20term',
            'unknown'         => 'should_not_be_included',
        );

        $richie_public->maggio_redirect_request( $wp_mock );

    }
}