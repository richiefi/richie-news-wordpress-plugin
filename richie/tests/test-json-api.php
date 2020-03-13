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

	public function setUp() {
        parent::setUp();
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init' );
        update_option('richie', array('access_token' => 'testtoken') );
    }

    public function tearDown() {
        delete_option('richie');
        parent::tearDown();
    }

	public function test_get_news_feed_without_token() {
		$request  = new WP_REST_Request( 'GET', '/richie/v1/news/set' );
		$response = $this->server->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_get_news_feed_with_invalid_token() {
        $request  = new WP_REST_Request( 'GET', '/richie/v1/news/set' );
        $request->set_query_params( array( 'token' => 'wrong_token' ) );
		$response = $this->server->dispatch( $request );
        $this->assertEquals( 401, $response->get_status() );
    }

    public function test_get_news_feed_with_correct_token() {
        // create article set
        $term_id = $this->factory->term->create([
            'name'     => 'Test set',
            'taxonomy' => 'richie_article_set',
            'slug'     => 'test-set'
        ]);
        $request  = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );
    }

    public function test_get_news_feed_items_with_missing_guid() {
        $term_id = $this->factory->term->create([
            'name'     => 'Test set',
            'taxonomy' => 'richie_article_set',
            'slug'     => 'test-set',
        ]);

        $sources = [];

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

        $posts = $this->factory->post->create_many( 3 );
        $first = $posts[0];

        global $wpdb;
        $wpdb->update( $wpdb->posts, array( 'guid' => '' ), array( 'ID' => $first ) );
        clean_post_cache( $first );

        $request  = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['article_ids'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 2 ); // Should not include item with empty guid.
        $id_list = array_column( $articles, 'fetch_id' );
        $this->assertEquals( $id_list, [ 5, 6 ] );

        $this->assertEquals( $response->data['errors'][0]['description'], 'Missing guid' );
        $this->assertEquals( $response->data['errors'][0]['post_id'], $first );
    }

    public function test_get_single_article_with_images() {
        $id = $this->factory()->post->create( array( 'post_content' => '<img src="//external.url/testing/image.jpg"/>' ));
        $attachment_id = $this->factory->attachment->create_object(
            'richie.png',
            $id,
            array(
                'post_mime_type' => 'image/png',
				'post_type'      => 'attachment',
                'post_excerpt' => 'caption'
            )
        );

        $post = get_post($id);
        set_post_thumbnail( $post, $attachment_id );

        $request  = new WP_REST_Request( 'GET', '/richie/v1/article/' . $id );
        $request->set_query_params( array( 'token' => 'testtoken' ) );

        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );
        $article = $response->data;
        $this->assertEquals( $article->id, $id );
        $this->assertEquals( $article->title, $post->post_title );
        $this->assertEquals( $article->photos[0][0]->local_name, 'wp-content/uploads/richie.png');
        $this->assertEquals( $article->photos[0][0]->remote_url, 'http://example.org/wp-content/uploads/richie.png');
        $this->assertEquals( $article->photos[0][0]->caption, 'caption');
        $this->assertEquals( $article->photos[0][1]->local_name, 'external.url/testing/image.jpg');
        $this->assertEquals( $article->photos[0][1]->remote_url, 'https://external.url/testing/image.jpg');
        $this->assertContains( 'src="external.url/testing/image.jpg"', $article->content_html_document );

    }

    public function test_get_assets_list_with_combined_custom() {
        update_option( 'richie_assets', json_decode('[{"local_name": "app-assets/test/test2/script.js", "remote_url": "http://example.org/test/test2/script.js"}]') );
        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );

        $assets = $response->data['app_assets'];
        $last = array_pop($assets);
        $this->assertEquals( $last->local_name, 'app-assets/test/test2/script.js' );
        $this->assertEquals( $last->remote_url, 'http://example.org/test/test2/script.js' );
    }

    public function test_get_assets_list_with_combined_and_overriding_custom() {
        update_option( 'richie_assets', json_decode('[{"local_name": "app-assets/wp-includes/js/jquery/jquery.js", "remote_url": "http://another.org/wp-includes/js/jquery/jquery2.js?ver=1.12.4-wp"}]') );
        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );

        $assets = $response->data['app_assets'];
        $first = array_shift($assets); // jquery is first in the array
        $this->assertEquals( $first->local_name, 'app-assets/wp-includes/js/jquery/jquery.js' );
        $this->assertEquals( $first->remote_url, 'http://another.org/wp-includes/js/jquery/jquery2.js?ver=1.12.4-wp' );
    }

}
