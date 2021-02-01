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
		$this->server = $wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );
        update_option( 'richie', array( 'access_token' => 'testtoken' ) );
    }

    public function tearDown() {
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
        $term_id = $this->factory->term->create(
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
        $term_id = $this->factory->term->create(
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

        $posts = $this->factory->post->create_many( 3 );
        $first = $posts[0];

        global $wpdb;
        $wpdb->update( $wpdb->posts, array( 'guid' => '' ), array( 'ID' => $first ) );
        clean_post_cache( $first );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['article_ids'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 2 ); // Should not include item with empty guid.
        $id_list = array_column( $articles, 'fetch_id' );
        $this->assertEquals( $id_list, array_slice( $posts, 1 ) );

        $this->assertEquals( $response->data['errors'][0]['description'], 'Missing guid' );
        $this->assertEquals( $response->data['errors'][0]['post_id'], $first );
    }

    public function test_get_news_feed_items_without_duplicates() {
        $term_id = $this->factory->term->create(
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

        $posts   = $this->factory->post->create_many( 10 );
        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['article_ids'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 5 );
        $id_list = array_column( $articles, 'fetch_id' );
        $this->assertEquals( $id_list, array_slice( $posts, 0, 5 ) );
    }

    public function test_get_news_feed_items_with_duplicates() {
        $term_id = $this->factory->term->create(
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

        $posts = $this->factory->post->create_many( 10 );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['article_ids'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 8 );
        $id_list = array_column( $articles, 'fetch_id' );
        // expects posts (indexes) [ 0, 1, 0, 1, 2, 2, 3, 4]
        // first two in order, then three same order, allowing duplicates,
        // last three shouldn't contain duplicates from first section,
        // second section shouldn't matter
        $expected_post_ids = array( $posts[0], $posts[1], $posts[0], $posts[1], $posts[2], $posts[2], $posts[3], $posts[4] );
        $this->assertEquals( $expected_post_ids, $id_list );
    }

    public function test_get_news_feed_items_with_herald_modules() {
        $term_id = $this->factory->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        define( 'HERALD_THEME_VERSION', '1.0.0' ); // required for theme support test
        $herald_post_id = 5;

        $sources = array();

        $sources[1] = array(
            'id'                           => 1,
            'name'                         => 'test module',
            'number_of_posts'              => 2,
            'order_by'                     => 'date',
            'order_direction'              => 'DESC',
            'article_set'                  => $term_id,
            'list_layout_style'            => 'big',
            'allow_duplicates'             => 0,
            'disable_summary'              => 1,
            'herald_featured_post_id'      => $herald_post_id,
            'herald_featured_module_title' => 'module title',
        );

        $post_ids = $this->factory->post->create_many( 10 );

        // replicate herald theme meta data, include few modules, last one is active
        // and matches given title
        $fake_meta = array(
            'sections' => array(
                array(
                    'modules' => array(
                        array(
                            'type'   => 'text',
                            'active' => 1,
                        ),
                        array(
                            'type'   => 'posts',
                            'active' => 0,
                            'title'  => 'module title',
                            'manual' => array(
                                $post_ids[1],
                                $post_ids[2],
                            ),
                        ),
                        array(
                            'type'   => 'posts',
                            'active' => 1,
                            'title'  => 'module title not matching',
                            'manual' => array(
                                $post_ids[1],
                                $post_ids[2],
                            ),
                        ),
                        array(
                            'type'   => 'posts',
                            'active' => 1,
                            'title'  => 'module title',
                            'manual' => array(
                                $post_ids[3], // this and
                                $post_ids[4], // this should be picked
                                $post_ids[5],
                            ),
                        ),
                    ),
                ),
            ),
        );
        add_option( 'richienews_sources', array( 'published' => $sources ) );
        add_post_meta( $herald_post_id, '_herald_meta', $fake_meta );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['article_ids'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( count( $articles ), 2 );
        $id_list = array_column( $articles, 'fetch_id' );
        // it should have second posts module posts, since first one isn't active
        $this->assertEquals( $id_list, array_slice( $post_ids, 3, 2 ) );
    }

    public function test_get_single_article_with_images() {
        $id            = $this->factory()->post->create( array( 'post_content' => '<img src="//external.url/testing/image.jpg"/>' ) );
        $attachment_id = $this->factory->attachment->create_object(
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
        $this->assertEquals( $article->id, $id );
        $this->assertEquals( $article->title, $post->post_title );
        $this->assertEquals( $article->photos[0][0]->local_name, 'wp-content/uploads/richie.png' );
        $this->assertEquals( $article->photos[0][0]->remote_url, 'http://example.org/wp-content/uploads/richie.png' );
        $this->assertEquals( $article->photos[0][0]->caption, 'caption' );
        $this->assertEquals( $article->photos[0][1]->local_name, 'external.url/testing/image.jpg' );
        $this->assertEquals( $article->photos[0][1]->remote_url, 'https://external.url/testing/image.jpg' );
        $this->assertContains( 'src="external.url/testing/image.jpg"', $article->content_html_document );

    }

    public function test_get_news_feed_items_with_tags() {
        $term_id = $this->factory->term->create(
            array(
				'name'     => 'Test set',
				'taxonomy' => 'richie_article_set',
				'slug'     => 'test-set',
            )
        );

        $this->factory->tag->create_and_get( array( 'slug' => 'tag1' ) );
        $this->factory->tag->create_and_get( array( 'slug' => 'tag2' ) );
        $this->factory->tag->create_and_get( array( 'slug' => 'tag3' ) );

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
            'id'                => 1,
            'name'              => 'test source 2',
            'number_of_posts'   => 3,
            'order_by'          => 'date',
            'order_direction'   => 'ASC',
            'article_set'       => $term_id,
            'list_layout_style' => 'small',
            'tags'              => array( 'tag3' ),
        );

        add_option( 'richienews_sources', array( 'published' => $sources ) );

        $posts     = $this->factory->post->create_many( 10 ); // create few posts without tags
        $both_tags = $this->factory->post->create(
            array(
				'post_date'  => '2020-01-01',
				'tags_input' => array(
					'tag1',
					'tag2',
				),
            )
        );
        $tag_1     = $this->factory->post->create(
            array(
				'post_date'  => '2020-01-02',
				'tags_input' => array( 'tag1' ),
            )
        );
        $tag_2     = $this->factory->post->create(
            array(
				'post_date'  => '2020-01-03',
				'tags_input' => array( 'tag2' ),
            )
        );
        $tag_2_2   = $this->factory->post->create(
            array(
				'post_date'  => '2020-01-04',
				'tags_input' => array( 'tag2' ),
            )
        );
        $tag_3     = $this->factory->post->create(
            array(
				'post_date'  => '2020-01-05',
				'tags_input' => array( 'tag3' ),
            )
        );

        $request = new WP_REST_Request( 'GET', '/richie/v1/news/test-set' );
        $request->set_query_params( array( 'token' => 'testtoken' ) );
        $response = $this->server->dispatch( $request );
        $articles = $response->data['article_ids'];

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( 4, count( $articles ) );
        $id_list = array_column( $articles, 'fetch_id' );
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
        delete_transient(RICHIE_ASSET_CACHE_KEY); // clear cache

        $request  = new WP_REST_Request( 'GET', '/richie/v1/assets' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 200, $response->get_status() );

        $assets = $response->data['app_assets'];
        $first  = array_shift( $assets );
        $this->assertEquals( $first->local_name, $original->local_name );
        $this->assertEquals( $first->remote_url, 'http://another.org/some/script.js?ver=1.5' );
    }

}
