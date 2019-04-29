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
            'member_only_pmpro_level' => null,
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
}