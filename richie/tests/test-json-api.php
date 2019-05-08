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

}
