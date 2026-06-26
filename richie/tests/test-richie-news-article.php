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

        $post = self::factory()->post->create_and_get(
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

        $post = self::factory()->post->create_and_get(
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

    public function test_article_has_correct_paths() {
        global $wp_scripts, $wp_styles;
        $general_assets = [];

        // generate general assets from scripts
        foreach ( $wp_scripts->do_items() as $script_name ) {
            if ( ! isset( $wp_scripts->registered[ $script_name ] ) ) {
                continue;
            }
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
        $post = self::factory()->post->create_and_get(
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

        $this->assertStringContainsString( '"www.richie.fi/js/script.js"', $article->content_html_document );
        $this->assertStringContainsString( '"styles/style.css"', $article->content_html_document);
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

        $post = self::factory()->post->create_and_get(
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
        $this->assertStringContainsString( '<img src="img/included.jpg">', $article->content_html_document );
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

        $post = self::factory()->post->create_and_get(
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

        $post = self::factory()->post->create_and_get(
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

        $post = self::factory()->post->create_and_get(
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
        $this->assertObjectNotHasProperty( 'date', $article );
        $this->assertObjectNotHasProperty( 'summary', $article );
        $this->assertObjectHasProperty( 'title', $article );
        $this->assertObjectHasProperty( 'content_html_document', $article );
        $this->assertObjectHasProperty( 'assets', $article );
        $this->assertObjectHasProperty( 'photos', $article );
    }

    private function make_stub_with_template( $template ) {
        $stub = $this->getMockBuilder( Richie_Article::class )
            ->setConstructorArgs( array( $this->options, $this->assets ) )
            ->onlyMethods( array( 'render_template', 'get_article_assets' ) )
            ->getMock();
        $stub->method( 'get_article_assets' )->willReturn( array() );
        $stub->method( 'render_template' )->willReturn( $template );
        return $stub;
    }

    public function test_srcset_best_candidate_replaces_small_src() {
        $template = '<html><head></head><body>'
            . '<img src="http://example.org/wp-content/uploads/photo-300x200.jpg"'
            . ' srcset="http://example.org/wp-content/uploads/photo-300x200.jpg 300w,'
            . ' http://example.org/wp-content/uploads/photo-1024x768.jpg 1024w">'
            . '</body></html>';

        $stub = $this->make_stub_with_template( $template );
        $post = self::factory()->post->create_and_get();

        $article = $stub->generate_article( $post );

        // srcset must be stripped.
        $this->assertStringNotContainsString( 'srcset', $article->content_html_document );
        // Best candidate (1024w) should be used in src.
        $this->assertStringContainsString( 'photo-1024x768.jpg', $article->content_html_document );
        // Small variant should not be in src.
        $this->assertStringNotContainsString( 'photo-300x200.jpg', $article->content_html_document );
        // 1024w photo should appear in the photos array.
        $photo_local_names = array_map( function ( $p ) { return $p->local_name; }, $article->photos[0] );
        $this->assertContains( 'wp-content/uploads/photo-1024x768.jpg', $photo_local_names );
    }

    public function test_data_srcset_parsed_and_stripped() {
        $template = '<html><head></head><body>'
            . '<img src="" data-src="http://example.org/wp-content/uploads/lazy-300x200.jpg"'
            . ' data-srcset="http://example.org/wp-content/uploads/lazy-300x200.jpg 300w,'
            . ' http://example.org/wp-content/uploads/lazy-1024x768.jpg 1024w">'
            . '</body></html>';

        $stub = $this->make_stub_with_template( $template );
        $post = self::factory()->post->create_and_get();

        $article = $stub->generate_article( $post );

        // data-srcset must be stripped.
        $this->assertStringNotContainsString( 'data-srcset', $article->content_html_document );
        // Best candidate (1024w) should be in src.
        $this->assertStringContainsString( 'lazy-1024x768.jpg', $article->content_html_document );
    }

    public function test_data_lazy_srcset_stripped() {
        $template = '<html><head></head><body>'
            . '<img src="http://example.org/wp-content/uploads/img.jpg"'
            . ' data-lazy-srcset="http://example.org/wp-content/uploads/img-300.jpg 300w,'
            . ' http://example.org/wp-content/uploads/img-1024.jpg 1024w">'
            . '</body></html>';

        $stub = $this->make_stub_with_template( $template );
        $post = self::factory()->post->create_and_get();

        $article = $stub->generate_article( $post );

        $this->assertStringNotContainsString( 'data-lazy-srcset', $article->content_html_document );
    }

    public function test_modula_data_full_preserved_and_rewritten() {
        $template = '<html><head></head><body>'
            . '<img class="pic"'
            . ' src="http://example.org/wp-content/uploads/photo-300x200.jpg"'
            . ' data-full="http://example.org/wp-content/uploads/photo.jpg"'
            . ' data-valign="middle">'
            . '</body></html>';

        $stub = $this->make_stub_with_template( $template );
        $post = self::factory()->post->create_and_get();

        $article = $stub->generate_article( $post );

        // data-full must be preserved (not stripped).
        $this->assertStringContainsString( 'data-full=', $article->content_html_document );
        // data-valign must be preserved unchanged.
        $this->assertStringContainsString( 'data-valign="middle"', $article->content_html_document );
        // data-full should be rewritten to local name.
        $this->assertStringContainsString( 'data-full="wp-content/uploads/photo.jpg"', $article->content_html_document );
        // The full-size URL from data-full should appear in photos.
        $all_local_names = array();
        foreach ( $article->photos as $group ) {
            foreach ( $group as $photo ) {
                $all_local_names[] = $photo->local_name;
            }
        }
        $this->assertContains( 'wp-content/uploads/photo.jpg', $all_local_names );
    }

    public function test_attachment_lookup_resolves_full_size() {
        // Create a WP attachment (simulates a media library image).
        $post          = self::factory()->post->create_and_get();
        $attachment_id = self::factory()->attachment->create_object(
            'full-image.jpg',
            $post->ID,
            array(
                'post_mime_type' => 'image/jpeg',
                'post_type'      => 'attachment',
            )
        );

        $full_url  = wp_get_attachment_url( $attachment_id );
        // Simulate a thumbnail-sized variant URL by renaming.
        $thumb_url = str_replace( 'full-image.jpg', 'full-image-150x150.jpg', $full_url );

        $template = '<html><head></head><body>'
            . '<img src="' . esc_attr( $thumb_url ) . '">'
            . '</body></html>';

        $stub = $this->make_stub_with_template( $template );

        $article = $stub->generate_article( $post );

        // The full-size URL should appear in photos (attachment lookup should find it).
        $all_local_names = array();
        foreach ( $article->photos as $group ) {
            foreach ( $group as $photo ) {
                $all_local_names[] = $photo->local_name;
            }
        }
        // full-image.jpg is the canonical; the thumbnail variant should have been resolved to it.
        $this->assertContains( 'wp-content/uploads/full-image.jpg', $all_local_names );
    }

    public function test_inline_style_font_urls_discovered_as_assets() {
        // Create a temp font file on disk so richie_url_to_local_path() finds it.
        $font_dir  = ABSPATH . 'wp-content/themes/richie-test-inline/fonts/';
        $font_file = $font_dir . 'test-font.woff2';
        wp_mkdir_p( $font_dir );
        file_put_contents( $font_file, 'fake-woff2' );

        $template = '<html><head>'
            . '<style>@font-face { font-family: TestFont; src: url(\'/wp-content/themes/richie-test-inline/fonts/test-font.woff2\') format(\'woff2\'); }</style>'
            . '</head><body>Content</body></html>';

        $stub    = $this->make_stub_with_template( $template );
        $post    = self::factory()->post->create_and_get();
        $article = $stub->generate_article( $post );

        $asset_local_names = array_map( function ( $a ) { return $a->local_name; }, $article->assets );
        $this->assertContains( 'wp-content/themes/richie-test-inline/fonts/test-font.woff2', $asset_local_names, 'Font from inline @font-face should be in article assets' );

        // The <style> content should be rewritten to use the local name.
        $this->assertStringContainsString( 'wp-content/themes/richie-test-inline/fonts/test-font.woff2', $article->content_html_document );
        $this->assertStringNotContainsString( '/wp-content/themes/richie-test-inline/fonts/test-font.woff2', $article->content_html_document );

        // Clean up.
        unlink( $font_file );
        rmdir( $font_dir );
        rmdir( ABSPATH . 'wp-content/themes/richie-test-inline/' );
    }

    public function test_inline_style_background_url_discovered_as_asset() {
        $uploads_dir = ABSPATH . 'wp-content/uploads/';
        $bg_file     = $uploads_dir . 'richie-test-inline-bg.jpg';
        file_put_contents( $bg_file, 'fake-jpg' );

        $template = '<html><head></head><body>'
            . '<style>.hero { background-image: url(\'/wp-content/uploads/richie-test-inline-bg.jpg\'); }</style>'
            . '<div class="hero">Hi</div>'
            . '</body></html>';

        $stub    = $this->make_stub_with_template( $template );
        $post    = self::factory()->post->create_and_get();
        $article = $stub->generate_article( $post );

        $asset_local_names = array_map( function ( $a ) { return $a->local_name; }, $article->assets );
        $this->assertContains( 'wp-content/uploads/richie-test-inline-bg.jpg', $asset_local_names, 'Background image from inline <style> should be in article assets' );

        // Clean up.
        unlink( $bg_file );
    }

    public function test_inline_style_attribute_background_image_discovered_as_photo() {
        $template = '<html><head></head><body>'
            . '<div style="background-image: url(\'/wp-content/uploads/hero.jpg\')">content</div>'
            . '</body></html>';

        $stub    = $this->make_stub_with_template( $template );
        $post    = self::factory()->post->create_and_get();
        $article = $stub->generate_article( $post );

        $this->assertNotEmpty( $article->photos, 'Expected photos array to be non-empty' );
        $photo_local_names = array_map( function ( $photo ) { return $photo->local_name; }, $article->photos[0] );
        $this->assertContains( 'wp-content/uploads/hero.jpg', $photo_local_names );
        $this->assertStringContainsString( 'url(wp-content/uploads/hero.jpg)', $article->content_html_document );
    }

    public function test_inline_style_external_urls_skipped() {
        $template = '<html><head>'
            . '<style>@font-face { font-family: ExtFont; src: url(\'https://fonts.example.com/external.woff2\') format(\'woff2\'); }</style>'
            . '</head><body>Content</body></html>';

        $stub    = $this->make_stub_with_template( $template );
        $post    = self::factory()->post->create_and_get();
        $article = $stub->generate_article( $post );

        $asset_remote_urls = array_map( function ( $a ) { return $a->remote_url; }, $article->assets );
        $this->assertNotContains( 'https://fonts.example.com/external.woff2', $asset_remote_urls, 'External font URL should not appear in article assets' );
    }

    /**
     * True-relative url() paths in inline <style> blocks are resolved against the
     * site root, because content_html_document is the document root — the same as
     * the site root. So url('fonts/font.woff2') in an inline style means the font
     * lives at /fonts/font.woff2 relative to the site root, and should be discovered
     * and rewritten to that local_name.
     */
    public function test_inline_style_true_relative_url_resolved_against_site_root() {
        $tmp_dir  = ABSPATH . 'fonts/';
        $tmp_file = $tmp_dir . 'rel-font.woff2';

        wp_mkdir_p( $tmp_dir );
        file_put_contents( $tmp_file, 'fake' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        $template = '<html><head>'
            . '<style>@font-face { font-family: RelFont; src: url(\'fonts/rel-font.woff2\') format(\'woff2\'); }</style>'
            . '</head><body>Content</body></html>';

        $stub    = $this->make_stub_with_template( $template );
        $post    = self::factory()->post->create_and_get();
        $article = $stub->generate_article( $post );

        $asset_remote_urls = array_map( function ( $a ) { return $a->remote_url; }, $article->assets );
        $expected_url      = get_site_url() . '/fonts/rel-font.woff2';

        $this->assertContains( $expected_url, $asset_remote_urls, 'True-relative font URL should resolve against the site root (document root)' );

        unlink( $tmp_file );
        rmdir( $tmp_dir );
    }
}
