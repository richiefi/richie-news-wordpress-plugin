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

    public function setUp(): void {
        parent::setUp();
        $this->options = array(
            'access_token' => '1234',
        );
        $this->assets = array();

    }
    public function tearDown(): void {
        parent::tearDown();
    }

    private function get_stub() {
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->onlyMethods( array( 'render_template', 'get_article_assets' ) )
        ->getMock();

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
                'post_type'     => 'post',
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
                'post_type'     => 'post',
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
        $this->markTestSkipped('There is no support for metered paywall for now.');
        global $wpdb;
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->onlyMethods( array( 'render_template', 'get_article_assets' ) )
        ->getMock();

        // Configure the stub.
        $stub->method( 'get_article_assets' )
            ->willReturn( array() );

        $stub->method( 'render_template' )
            ->willReturn( '<html><head></head><body>Test content</body></html>' );

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'post',
                'post_title'    => 'My Title',
            )
        );

        //$wpdb->pmpro_memberships_pages = 'TEST_DB';

        // add_filter( 'query', function( $query ) {
        //     if ( strpos( $query, 'TEST_DB' ) !== false ) {
        //         return 'SELECT 1 as id'; // We have set member only level to 1 in options, return that.
        //     }
        //     return $query;
        // });

        $article = $stub->generate_article( $post );
        $this->assertEquals( $article->title, 'My Title' );
        $this->assertEquals( $article->metered_paywall, 'no_access' );

    }

    public function test_article_has_correct_paths() {
        global $wp_scripts, $wp_styles;
        $general_assets = [];

        // generate general assets from scripts
        foreach ( $wp_scripts->do_items() as $script_name ) {
            $script     = $wp_scripts->registered[ $script_name ];
            $remote_url = $script->src;
            if ( ( substr( $remote_url, -3 ) === '.js' ) && ! strpos( $remote_url, 'wp-admin' ) ) {
                $general_assets[] = new Richie_App_Asset( $script );
            }
        }
        // Print all loaded Styles (CSS).
        foreach ( $wp_styles->do_items() as $style_name ) {
            $style      = $wp_styles->registered[ $style_name ];
            $remote_url = $style->src;
            if ( ( substr( $remote_url, -4 ) === '.css' ) && ! strpos( $remote_url, 'wp-admin' ) ) {
                $general_assets[] = new Richie_App_Asset( $style );
            }
        }

        $service = new Richie_Article( $this->options, $general_assets );

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
        $article = $service->generate_article( $post );
        $this->assertEquals( $article->title, 'My Title' );

        $assets = $article->assets;
        $this->assertEquals( 2, count( $assets ) ); // 2 extras after general assets

        $locals = array_column($assets, NULL, 'local_name');
        $this->assertArrayHasKey( 'www.richie.fi/js/script.js', $locals);
        $this->assertArrayHasKey( 'styles/style.css', $locals);

        $this->assertEquals( $locals['www.richie.fi/js/script.js']->remote_url, 'http://www.richie.fi/js/script.js?ver=1.1' );
        $this->assertEquals( $locals['styles/style.css']->remote_url, 'http://example.org/styles/style.css?ver=1' );

        $this->assertContains( '"www.richie.fi/js/script.js"', $article->content_html_document );
        $this->assertContains( '"styles/style.css"', $article->content_html_document);
    }

    public function test_article_handles_images() {
        global $wpdb;
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->onlyMethods( array( 'render_template', 'get_article_assets' ) )
        ->getMock();

        // Configure the stub.

        $stub->method( 'get_article_assets' )
            ->willReturn( array() );

        $template = '
            <html>
                <head>
                    <noscript><img src="http://example.org/img/not-included.jpg"></noscript>
                </head>
                <body>
                    Test content<img src="http://example.org/img/included.jpg" srcset="img/included.jpg 480w">
                    <script type="text/template"><div><img src="img/no-included2.jpg">Template</div></div></script>
                </body>
            </html>
        ';

        $stub->method( 'render_template' )
            ->willReturn( $template );

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:04:59';

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'post',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
            )
        );

        $post->post_modified     = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );

        $article = $stub->generate_article( $post );
        $photos  = $article->photos[0];

        // Url changed, srcset removed.
        $this->assertContains( '<img src="img/included.jpg">', $article->content_html_document );
        $this->assertEquals( count( $photos ), 1 ); // One image should be included.
    }

    public function test_article_inserts_mraid() {
        global $wpdb;
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->onlyMethods( array( 'render_template', 'get_article_assets' ) )
        ->getMock();

        // Configure the stub.

        $stub->method( 'get_article_assets' )
            ->willReturn( array() );

        $template = '
            <html>
                <head>
                    <script src="another.js"></script>
                </head>
                <body>
                    <div>Test</div>
                    <script src="somescript.js"></script>
                </body>
            </html>
        ';

        $expected_output = '
            <html>
                <head>
                    <script src="mraid.js"></script>
                    <script src="another.js"></script>
                </head>
                <body>
                  <div>Test</div>
                  <script src="somescript.js"></script>
                </body>
            </html>
        ';

        $stub->method( 'render_template' )
            ->willReturn( $template );

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:04:59';

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'post',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
            )
        );

        $post->post_modified     = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );

        $article = $stub->generate_article( $post );
        $this->assertXmlStringEqualsXmlString($expected_output, $article->content_html_document);
    }

    public function test_article_handles_duplicate_id() {
        global $wpdb;
        $stub = $this->getMockBuilder( Richie_Article::class )
        ->setConstructorArgs( array( $this->options, $this->assets ) )
        ->onlyMethods( array( 'render_template', 'get_article_assets' ) )
        ->getMock();

        // Configure the stub.

        $stub->method( 'get_article_assets' )
            ->willReturn( array() );

        $template = '
            <html>
                <head>
                </head>
                <body>
                    <div id="duplicate">Test</div>
                    <div id="duplicate">Test2</div>
                </body>
            </html>
        ';

        $expected_output = '
            <html>
                <head>
                    <script src="mraid.js"></script>
                </head>
                <body>
                    <div id="duplicate">Test</div>
                    <div id="duplicate">Test2</div>
                </body>
            </html>
        ';

        $stub->method( 'render_template' )
            ->willReturn( $template );

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:04:59';

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'post',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
            )
        );

        $post->post_modified     = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );

        $article = $stub->generate_article( $post );
        $this->assertXmlStringEqualsXmlString($expected_output, $article->content_html_document);
    }

    public function test_article_without_metadata() {
        $stub = $this->get_stub();

        $postdate = '2010-01-01 12:00:00';
        $updated  = '2010-01-01 12:05:00';

        $post = $this->factory->post->create_and_get(
            array(
                'post_type'     => 'post',
                'post_title'    => 'My Title',
                'post_date'     => $postdate,
                'post_date_gmt' => get_gmt_from_date( $postdate ),
            )
        );

        $post->post_modified = $updated;
        $post->post_modified_gmt = get_gmt_from_date( $updated );
        $article = $stub->generate_article( $post, Richie_Article::EXCLUDE_METADATA );
        $this->assertObjectNotHasAttribute( 'date', $article );
        $this->assertObjectNotHasAttribute( 'summary', $article );
        $this->assertObjectHasAttribute( 'title', $article );
        $this->assertObjectHasAttribute( 'content_html_document', $article );
        $this->assertObjectHasAttribute( 'assets', $article );
        $this->assertObjectHasAttribute( 'photos', $article );
    }
}
