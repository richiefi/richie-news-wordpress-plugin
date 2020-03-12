<?php
/**
 * Class Test_Richie_News_Article
 *
 * @package Richie
 */

/**
 * Tests for Richie News Article.
 */
class Test_Richie_News_Article extends WP_UnitTestCase {
    protected $options;
    protected $assets;

    public function setUp() {
        parent::setUp();
        $this->options = array(
            'metered_pmpro_level' => null,
            'member_only_pmpro_level' => 1,
            'access_token' => '1234',
        );
        $this->assets = array();

    }
    public function tearDown() {
        parent::tearDown();
    }

    private function get_stub() {
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->setMethods( array( 'get_pmpro_levels', 'render_template', 'get_article_assets' ) )
        ->getMock();

        // Configure the stub.
        $stub->method( 'get_pmpro_levels' )
            ->willReturn( array() );

        $stub->method( 'get_article_assets' )
            ->willReturn( array() );

        $stub->method( 'render_template' )
            ->willReturn( '<html><head></head><body>Test content</body></html>' );

        return $stub;
    }

    public function test_article_update_date_included() {
        $stub = $this->get_stub();

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:05:00';

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'article',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
            )
        );

        $post->post_modified = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );
        $article = $stub->generate_article( $post );
        $this->assertEquals( $article->title, 'My Title' );
        $this->assertEquals( $article->date, ( new DateTime( $postdate ) )->format( 'c' ) );
        $this->assertEquals( $article->updated_date, ( new DateTime( $updated ) )->format( 'c' ) );
    }

    public function test_article_no_updated_date_if_close() {
        $stub = $this->get_stub();

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:04:59';

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'article',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
            )
        );

        $post->post_modified = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );
        $article = $stub->generate_article( $post );
        $this->assertEquals( $article->title, 'My Title' );
        $this->assertEquals( $article->date, ( new DateTime( $postdate ) )->format( 'c' ) );
        $this->assertFalse( property_exists( $article, 'updated_date' ) );
    }

    public function test_article_metered_paywall() {
        global $wpdb;
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->setMethods( array( 'is_pmpro_active', 'render_template', 'get_article_assets' ) )
        ->getMock();

        // Configure the stub.
        $stub->method( 'is_pmpro_active' )
            ->willReturn( true );

        $stub->method( 'get_article_assets' )
            ->willReturn( array() );

        $stub->method( 'render_template' )
            ->willReturn( '<html><head></head><body>Test content</body></html>' );

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'article',
                'post_title'    => 'My Title',
            )
        );

        $wpdb->pmpro_memberships_pages = 'TEST_DB';

        add_filter( 'query', function( $query ) {
            if ( strpos( $query, 'TEST_DB' ) !== false ) {
                return 'SELECT 1 as id'; // We have set member only level to 1 in options, return that.
            }
            return $query;
        });

        $article = $stub->generate_article( $post );
        $this->assertEquals( $article->title, 'My Title' );
        $this->assertEquals( $article->metered_paywall, 'no_access' );

    }

    public function test_article_has_correct_paths() {
        global $post;
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->setMethods( array( 'get_pmpro_levels') )
        ->getMock();

        $stub->method( 'get_pmpro_levels' )
        ->willReturn( array() );

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:05:00';

        wp_enqueue_style( 'test-style', 'http://example.org/styles/style.css', null, 1.0, false );
        wp_enqueue_script( 'test-script', '//www.richie.fi/js/script.js', null, 1.1, true);
        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'post',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
                'post_content'  => 'content'
            )
        );

        $post->post_modified = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );
        $article = $stub->generate_article( $post );
        $this->assertEquals( $article->title, 'My Title' );

        $assets = $article->assets;
        $this->assertEquals( 8, count( $assets ) );

        $locals = array_column($assets, NULL, 'local_name');
        $this->assertArrayHasKey( 'www.richie.fi/js/script.js', $locals);
        $this->assertArrayHasKey( 'styles/style.css', $locals);

        $this->assertEquals( $locals['www.richie.fi/js/script.js']->remote_url, 'http://www.richie.fi/js/script.js?ver=1.1' );
        $this->assertEquals( $locals['styles/style.css']->remote_url, 'http://example.org/styles/style.css?ver=1' );
    }
}