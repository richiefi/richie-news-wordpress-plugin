<?php
/**
 * Class Test_JSON_API
 *
 * @package Richie
 */

/**
 * JSON API test suite.
 */
class Test_JSON_API extends WP_UnitTestCase {
    protected $server;

    protected $namespaced_route = 'richie/v1';

	public function setUp(): void {
        parent::setUp();
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
        update_option( 'richie', array( 'access_token' => 'testtoken' ) );
    }

    public function tearDown(): void {
        // Clean up any test styles/scripts registered by individual tests.
        foreach ( array( 'richie-test-block-style', 'richie-test-path-backed', 'richie-test-css-deps', 'richie-test-override-css' ) as $handle ) {
            wp_dequeue_style( $handle );
            wp_deregister_style( $handle );
        }
        delete_transient( RICHIE_ASSET_CACHE_KEY );
        delete_option( 'richie_assets' );
        delete_option( 'richie' );
        parent::tearDown();
    }

	public function test_get_news_feed_without_token() {
		$request  = new WP_REST_Request( 'GET', '/richie/v1/news/set' );
		$response = $this->server->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_get_news_feed_with_invalid_token() {
        $request = new WP_REST_Request( 'GET', '/richie/v1/news/set' );
        $request->set_query_params( array( 'token' => 'wrong_token' ) );
		$response = $this->server->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_get_news_feed_with_correct_token() {
        // create article set
        $term_id = self::factory()->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );
        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );
    }

    public function test_get_news_feed_items_with_missing_guid() {
        $term_id = self::factory()->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        $sources = array();

        $sources[1] = array(
            'id'                => 1,
            'name'              => 'test source',
            'number_of_posts'   => 5,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        $posts = self::factory()->post->create_many( 3 );
        $first = $posts[0];

        global $wpdb;
        $wpdb->update( $wpdb->posts, array( 'guid' => '' ), array( 'ID' => $first ) );
        clean_post_cache( $first );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['articles'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 2 ); // Should not include item with empty guid.
        $id_list = array_column( $articles, 'publisher_id' );
        $this->assertEquals( $id_list, array_slice( $posts, 1 ) );

        $this->assertEquals( $response->data['errors'][0]['description'], 'Missing guid' );
        $this->assertEquals( $response->data['errors'][0]['post_id'], $first );
    }

    public function test_get_news_feed_items_without_duplicates() {
        $term_id = self::factory()->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        $sources = array();

        $sources[1] = array(
            'id'                => 1,
            'name'              => 'test source',
            'number_of_posts'   => 2,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small_group_item',
            'list_group_title'  => 'group title',
            'allow_duplicates'  => 0,
        );

        $sources[2] = array(
            'id'                => 2,
            'name'              => 'test source 2',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        $posts   = self::factory()->post->create_many( 10 );
        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['articles'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 5 );
        $id_list = array_column( $articles, 'publisher_id' );
        $this->assertEquals( $id_list, array_slice( $posts, 0, 5 ) );
    }

    public function test_group_items_include_collection_header_title() {
        $term_id = self::factory()->term->create(
            array(
                'name'     => 'Test set',
                'taxonomy' => 'richie_article_set',
                'slug'     => 'test-set',
            )
        );

        $sources = array();

        $sources[1] = array(
            'id'                => 1,
            'name'              => 'group source',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small_group_item',
            'list_group_title'  => 'group title',
            'allow_duplicates'  => 0,
        );

        $sources[2] = array(
            'id'                => 2,
            'name'              => 'non-group source',
            'number_of_posts'   => 2,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        self::factory()->post->create_many( 10 );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['articles'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( 5, count( $articles ) );

        $group_articles = array_slice( $articles, 0, 3 );
        foreach ( $group_articles as $article ) {
            $this->assertArrayHasKey( 'collection_header_title', $article );
            $this->assertEquals( 'group title', $article['collection_header_title'] );
        }
    }

    public function test_get_v1_news_feed() {
        $term_id = self::factory()->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        $sources = array();

        $sources[1] = array(
            'id'                => 1,
            'name'              => 'test source',
            'number_of_posts'   => 2,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small_group_item',
            'list_group_title'  => 'group title',
            'allow_duplicates'  => 0,
        );

        $sources[2] = array(
            'id'                => 2,
            'name'              => 'test source 2',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        $posts   = self::factory()->post->create_many( 10 );
        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['articles'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 5 );
        $this->assertEquals( $response->data['section']['name'], 'Test set' );
        $id_list = array_column( $articles, 'publisher_id' );
        $this->assertEquals( $id_list, array_slice( $posts, 0, 5 ) );
    }

    public function test_get_news_feed_items_with_duplicates() {
        $term_id = self::factory()->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        $sources = array();

        $sources[1] = array(
            'id'                => 1,
            'name'              => 'test source',
            'number_of_posts'   => 2,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
        );

        $sources[2] = array(
            'id'                => 2,
            'name'              => 'test source 2',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small_group_item',
            'list_group_title'  => 'group title',
            'allow_duplicates'  => true,
        );

        $sources[3] = array(
            'id'                => 3,
            'name'              => 'test source 3',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        $posts = self::factory()->post->create_many( 10 );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['articles'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 8 );
        $id_list = array_column( $articles, 'publisher_id' );
        // expects posts (indexes) [ 0, 1, 0, 1, 2, 2, 3, 4]
        // first two in order, then three same order, allowing duplicates,
        // last three shouldn't contain duplicates from first section,
        // second section shouldn't matter
        $expected_post_ids = array( $posts[0], $posts[1], $posts[0], $posts[1], $posts[2], $posts[2], $posts[3], $posts[4] );
        $this->assertEquals( $expected_post_ids, $id_list );
    }

    public function test_get_single_article_with_images() {
        $id            = self::factory()->post->create( array( 'post_content' => '<img src="//external.url/testing/image.jpg"/>' ) );
        $attachment_id = self::factory()->attachment->create_object(
            'richie.png',
            $id,
            array(
                'post_mime_type' => 'image/png',
				'post_type'      => 'attachment',
                'post_excerpt'   => 'caption',
            )
        );

        $post = get_post( $id );
        set_post_thumbnail( $post, $attachment_id );

        $request = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );

        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;
        $this->assertEquals( $article->publisher_id, $id );
        $this->assertEquals( $article->title, $post->post_title );
        $this->assertEquals( $article->photos[0][0]->local_name, 'wp-content/uploads/richie.png' );
        $this->assertEquals( $article->photos[0][0]->remote_url, 'http://example.org/wp-content/uploads/richie.png' );
        $this->assertEquals( $article->photos[0][0]->caption, 'caption' );
        $this->assertEquals( $article->photos[0][1]->local_name, 'external.url/testing/image.jpg' );
        $this->assertEquals( $article->photos[0][1]->remote_url, 'https://external.url/testing/image.jpg' );
        $this->assertStringContainsString( 'src="external.url/testing/image.jpg"', $article->content_html_document );
    }

    public function test_get_single_v1_article_with_images() {
        $id            = self::factory()->post->create( array( 'post_content' => '<img src="//external.url/testing/image.jpg"/>' ) );
        $attachment_id = self::factory()->attachment->create_object(
            'richie.png',
            $id,
            array(
                'post_mime_type' => 'image/png',
				'post_type'      => 'attachment',
                'post_excerpt'   => 'caption',
            )
        );

        $post = get_post( $id );
        set_post_thumbnail( $post, $attachment_id );

        $request = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );

        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;

        $this->assertEquals( $article->title, $post->post_title );
        $this->assertEquals( $article->photos[0][0]->local_name, 'wp-content/uploads/richie.png' );
        $this->assertEquals( $article->photos[0][0]->remote_url, 'http://example.org/wp-content/uploads/richie.png' );
        $this->assertEquals( $article->photos[0][0]->caption, 'caption' );
        $this->assertEquals( $article->photos[0][0]->scale_to_device_dimensions, true );
        $this->assertEquals( $article->photos[0][1]->local_name, 'external.url/testing/image.jpg' );
        $this->assertEquals( $article->photos[0][1]->remote_url, 'https://external.url/testing/image.jpg' );
        $this->assertEquals( $article->photos[0][1]->scale_to_device_dimensions, true );
        $this->assertStringContainsString( 'src="external.url/testing/image.jpg"', $article->content_html_document );
    }

    public function test_get_news_feed_items_with_tags() {
        $term_id = self::factory()->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        self::factory()->tag->create_and_get( array( 'slug' => 'tag1' ) );
        self::factory()->tag->create_and_get( array( 'slug' => 'tag2' ) );
        self::factory()->tag->create_and_get( array( 'slug' => 'tag3' ) );

        $sources = array();

        $sources[1] = array(
            'id'                => 1,
            'name'              => 'test source',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small_group_item',
            'list_group_title'  => 'group title',
            'allow_duplicates'  => 0,
            'tags'              => array( 'tag1', 'tag2' ),
        );

        $sources[2] = array(
            'id'                => 2,
            'name'              => 'test source 2',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
            'tags'              => array( 'tag3' ),
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        $posts     = self::factory()->post->create_many( 10 ); // create few posts without tags
        $both_tags = self::factory()->post->create(
            array(
				'post_date'  => '2020-01-01',
				'tags_input' => array(
					'tag1',
					'tag2',
				),
            )
        );
        $tag_1     = self::factory()->post->create(
            array(
				'post_date'  => '2020-01-02',
				'tags_input' => array( 'tag1' ),
            )
        );
        $tag_2     = self::factory()->post->create(
            array(
				'post_date'  => '2020-01-03',
				'tags_input' => array( 'tag2' ),
            )
        );
        $tag_2_2   = self::factory()->post->create(
            array(
				'post_date'  => '2020-01-04',
				'tags_input' => array( 'tag2' ),
            )
        );
        $tag_3     = self::factory()->post->create(
            array(
				'post_date'  => '2020-01-05',
				'tags_input' => array( 'tag3' ),
            )
        );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['articles'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( 4, count( $articles ) );
        $id_list = array_column( $articles, 'publisher_id' );
        // Should include 3 posts from first source (including tag1 or tag2) and one from second (having tag3)
        // tag_2_2 should not be in returned list
        $this->assertEquals( $id_list, array( $both_tags, $tag_1, $tag_2, $tag_3 ) );
    }

    public function test_get_assets_list_with_combined_custom() {
        update_option( 'richie_assets', json_decode( '[{"local_name": "app-assets/test/test2/script.js", "remote_url": "http://example.org/test/test2/script.js"}]' ) );
        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );

        $assets = $response->data['app_assets'];
        $last   = array_pop( $assets );
        $this->assertEquals( $last->local_name, 'app-assets/test/test2/script.js' );
        $this->assertEquals( $last->remote_url, 'http://example.org/test/test2/script.js' );
    }

    public function test_get_assets_list_with_combined_and_overriding_custom() {
        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );
        $assets   = $response->data['app_assets'];
        $original = array_shift( $assets );
        // update original item with new overridden remote
        update_option( 'richie_assets', json_decode( '[{"local_name": "' . $original->local_name . '", "remote_url": "http://another.org/some/script.js?ver=1.5"}]' ) );
        delete_transient( RICHIE_ASSET_CACHE_KEY ); // clear cache

        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );

        $assets = $response->data['app_assets'];
        $first  = array_shift( $assets );
        $this->assertEquals( $first->local_name, $original->local_name );
        $this->assertEquals( $first->remote_url, 'http://another.org/some/script.js?ver=1.5' );
    }

    // --- New tests for asset discovery fixes ---


    /**
     * Test that webp images are discovered (richie_is_image_url was too restrictive).
     */
    public function test_webp_image_is_discovered() {
        $id = self::factory()->post->create(
            array( 'post_content' => '<img src="/wp-content/uploads/photo.webp" />' )
        );

        $request = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;
        $this->assertNotEmpty( $article->photos, 'Expected photos array to be non-empty' );
        $local_names = array_map(
            function ( $photo ) {
                return $photo->local_name; },
            $article->photos[0]
        );
        $this->assertContains( 'wp-content/uploads/photo.webp', $local_names );
        $this->assertStringContainsString( 'src="wp-content/uploads/photo.webp"', $article->content_html_document );
    }

    /**
     * Test that lazyloaded images (data-src) are discovered and src gets a fallback value.
     */
    public function test_lazyload_data_src_image_discovered() {
        $id = self::factory()->post->create(
            array(
                'post_content' => '<img class="lazyload" src="" data-src="/wp-content/uploads/lazy.jpg" />',
            )
        );

        $request = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;
        $this->assertNotEmpty( $article->photos, 'Expected photos array to be non-empty' );
        $local_names = array_map(
            function ( $photo ) {
                return $photo->local_name; },
            $article->photos[0]
        );
        $this->assertContains( 'wp-content/uploads/lazy.jpg', $local_names );
        // src should be filled in from data-src so article renders in app.
        $this->assertStringContainsString( 'src="wp-content/uploads/lazy.jpg"', $article->content_html_document );
        // data-src should also be rewritten to local name.
        $this->assertStringContainsString( 'data-src="wp-content/uploads/lazy.jpg"', $article->content_html_document );
    }

    /**
     * Test that an emitted style is rewritten to use app-assets/ prefix in article HTML.
     * This ensures the do_items() vs ->done fix works.
     */
    public function test_emitted_style_rewritten_in_article() {
        $id = self::factory()->post->create( array( 'post_content' => 'content' ) );

        wp_enqueue_style(
            'richie-test-block-style',
            '/wp-includes/blocks/button/style.min.css',
            array(),
            '6.7.5'
        );

        delete_transient( RICHIE_ASSET_CACHE_KEY );

        $request = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;
        // The href should use the app-assets/ local name, not the raw path with version.
        $this->assertStringContainsString( 'href="app-assets/wp-includes/blocks/button/style.min.css"', $article->content_html_document );
        $this->assertStringNotContainsString( 'href="/wp-includes/blocks/button/style.min.css?ver=6.7.5"', $article->content_html_document );

        wp_dequeue_style( 'richie-test-block-style' );
        wp_deregister_style( 'richie-test-block-style' );
    }

    /**
     * Test that CSS dependency sub-resources (fonts, @import, url()) appear in asset feed.
     * This is the main fix: the Richie app does not crawl CSS, so the plugin must.
     */
    public function test_asset_feed_discovers_css_font_and_import_dependencies() {
        $uploads  = wp_get_upload_dir();
        $base_dir = trailingslashit( $uploads['basedir'] ) . 'richie-css-deps-test/';
        $base_url = trailingslashit( $uploads['baseurl'] ) . 'richie-css-deps-test/';

        wp_mkdir_p( $base_dir );

        // main.css imports extra.css and references a background image.
        file_put_contents( $base_dir . 'main.css', "@import url('extra.css');\n.hero { background: url('bg.png'); }\n" );
        // extra.css references a font via @font-face.
        file_put_contents( $base_dir . 'extra.css', "@font-face { font-family: Test; src: url('font.woff2') format('woff2'); }\n" );
        // Create the actual dependency files so local path check passes.
        file_put_contents( $base_dir . 'bg.png', 'fake-png' );
        file_put_contents( $base_dir . 'font.woff2', 'fake-font' );

        wp_enqueue_style( 'richie-test-css-deps', $base_url . 'main.css', array(), null );

        delete_transient( RICHIE_ASSET_CACHE_KEY );

        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $local_names = array_column( (array) $response->data['app_assets'], 'local_name' );

        $uploads_rel = str_replace( get_site_url() . '/', '', $base_url );

        $this->assertContains( 'app-assets/' . $uploads_rel . 'extra.css', $local_names, 'extra.css imported via @import should be in asset feed' );
        $this->assertContains( 'app-assets/' . $uploads_rel . 'bg.png', $local_names, 'bg.png referenced via url() should be in asset feed' );
        $this->assertContains( 'app-assets/' . $uploads_rel . 'font.woff2', $local_names, 'font.woff2 from @font-face should be in asset feed' );

        // Clean up.
        wp_dequeue_style( 'richie-test-css-deps' );
        wp_deregister_style( 'richie-test-css-deps' );
        array_map( 'unlink', glob( $base_dir . '*' ) );
        rmdir( $base_dir );
    }

    /**
     * Test that path-backed styles (src=false, extra['path'] set) appear in the asset feed.
     * Gutenberg block styles often use this pattern.
     */
    public function test_path_backed_style_in_asset_feed() {
        $style_relative_path = '/wp-includes/blocks/search/style.css';
        $style_path          = ABSPATH . ltrim( $style_relative_path, '/' );

        // Skip if the file doesn't exist in this WP installation.
        if ( ! file_exists( $style_path ) ) {
            $this->markTestSkipped( 'WP core search block style not found.' );
        }

        wp_register_style( 'richie-test-path-backed', false, array(), false );
        wp_style_add_data( 'richie-test-path-backed', 'path', $style_path );
        wp_enqueue_style( 'richie-test-path-backed' );

        delete_transient( RICHIE_ASSET_CACHE_KEY );

        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $local_names = array_column( (array) $response->data['app_assets'], 'local_name' );
        $this->assertContains( 'app-assets' . $style_relative_path, $local_names );

        $remote_urls  = array_column( (array) $response->data['app_assets'], 'remote_url' );
        $expected_url = get_site_url( null, $style_relative_path );
        $this->assertContains( $expected_url, $remote_urls );

        wp_dequeue_style( 'richie-test-path-backed' );
        wp_deregister_style( 'richie-test-path-backed' );
    }

    /**
     * Test that manual richie_assets option entries override auto-discovered CSS dependencies.
     */
    public function test_custom_assets_override_discovered_css_dependencies() {
        $uploads  = wp_get_upload_dir();
        $base_dir = trailingslashit( $uploads['basedir'] ) . 'richie-css-override-test/';
        $base_url = trailingslashit( $uploads['baseurl'] ) . 'richie-css-override-test/';

        wp_mkdir_p( $base_dir );

        file_put_contents( $base_dir . 'main.css', ".hero { background: url('bg.png'); }\n" );
        file_put_contents( $base_dir . 'bg.png', 'fake-png' );

        wp_enqueue_style( 'richie-test-override-css', $base_url . 'main.css', array(), null );

        $uploads_rel  = str_replace( get_site_url() . '/', '', $base_url );
        $override_url = 'https://cdn.example.com/custom/bg.png';
        $local_name   = 'app-assets/' . $uploads_rel . 'bg.png';

        update_option( 'richie_assets', json_decode( '[{"local_name": "' . $local_name . '", "remote_url": "' . $override_url . '"}]' ) );
        delete_transient( RICHIE_ASSET_CACHE_KEY );

        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $assets_map = array();
        foreach ( $response->data['app_assets'] as $asset ) {
            $assets_map[ $asset->local_name ] = $asset->remote_url;
        }

        $this->assertArrayHasKey( $local_name, $assets_map, 'bg.png should appear in asset feed' );
        $this->assertEquals( $override_url, $assets_map[ $local_name ], 'Manual override should win over auto-discovered URL' );

        // Clean up.
        wp_dequeue_style( 'richie-test-override-css' );
        wp_deregister_style( 'richie-test-override-css' );
        delete_option( 'richie_assets' );
        array_map( 'unlink', glob( $base_dir . '*' ) );
        rmdir( $base_dir );
    }

    /**
     * On a cold-cache request, get_assets() runs wp_head() to collect emitted handles,
     * then clears extra['data'] from all registered scripts to prevent duplication.
     * This also strips any wp_localize_script() / wp_add_inline_script() payloads that
     * were attached during wp_enqueue_scripts — those hooks do not re-fire when
     * render_template() later calls wp_head(), so the localized data is lost.
     *
     * Currently failing: the inline script payload is absent from content_html_document
     * on cold-cache requests.
     */
    public function test_wp_localize_script_payload_present_on_cold_cache_article_request() {
        delete_transient( RICHIE_ASSET_CACHE_KEY );

        // Enqueue a script and localize it — simulating what a theme/plugin does on
        // wp_enqueue_scripts. In the test environment this fires before the request.
        wp_enqueue_script( 'richie-test-localized', '/wp-includes/js/jquery/jquery.min.js', array(), '3.7', false );
        wp_localize_script( 'richie-test-localized', 'RichieTestConfig', array( 'key' => 'expected-value' ) );

        $id = self::factory()->post->create( array( 'post_content' => 'content' ) );

        $request = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;

        // The localized config object must appear in the rendered HTML.
        $this->assertStringContainsString(
            'expected-value',
            $article->content_html_document,
            'wp_localize_script payload must be present in article HTML on cold-cache requests'
        );

        wp_dequeue_script( 'richie-test-localized' );
        wp_deregister_script( 'richie-test-localized' );
    }
}
