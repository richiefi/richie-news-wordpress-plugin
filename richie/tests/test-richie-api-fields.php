<?php
/**
 * Class Test_Richie_API_Fields
 *
 * @package Richie
 */

/**
 * Tests for Richie API field alignment with documentation.
 */
class Test_Richie_API_Fields extends WP_UnitTestCase {

	private $richie_public;

	public function setUp(): void {
		parent::setUp();
		$this->richie_public = new Richie_Public( 'richie', '1.0.0' );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that section articles use collection_header_title instead of list_group_title.
	 */
	public function test_section_article_uses_collection_header_title() {
		$post_id = $this->factory->post->create();

		$article = array(
			'id'                 => strval( $post_id ),
			'original_post'      => get_post( $post_id ),
			'last_updated'       => current_time( 'c' ),
			'article_attributes' => array(
				'list_layout_style'       => 'big',
				'collection_header_title' => 'Featured',
			),
		);

		$result = $this->richie_public->get_section_article( $article );

		// Should use collection_header_title, not list_group_title.
		$this->assertArrayHasKey( 'collection_header_title', $result );
		$this->assertArrayNotHasKey( 'list_group_title', $result );
		$this->assertEquals( 'Featured', $result['collection_header_title'] );
	}

	/**
	 * Test that search endpoint returns section object.
	 */
	public function test_search_returns_section_object() {
		$this->factory->post->create(
			array(
				'post_title'  => 'Searchable Test Post',
				'post_status' => 'publish',
			)
		);

		// Mock request with search term.
		$request = new WP_REST_Request( 'GET', '/richie/v1/search' );
		$request->set_param( 'q', 'Searchable' );

		$result = $this->richie_public->search_route_handler( $request );

		$this->assertArrayHasKey( 'section', $result );
		$this->assertArrayHasKey( 'name', $result['section'] );
		$this->assertArrayHasKey( 'articles', $result );
	}

	/**
	 * Test that search articles use layout field instead of list_layout_style.
	 */
	public function test_search_articles_use_layout_field() {
		update_option( 'richie', array( 'search_list_layout_style' => 'small' ) );

		$this->factory->post->create(
			array(
				'post_title'  => 'Layout Test Post',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/richie/v1/search' );
		$request->set_param( 'q', 'Layout' );

		$result = $this->richie_public->search_route_handler( $request );

		$this->assertNotEmpty( $result['articles'], 'Search should return at least one article' );

		$article = (array) $result['articles'][0];

		// Should use 'layout', not 'list_layout_style'.
		$this->assertArrayHasKey( 'layout', $article );
		$this->assertArrayNotHasKey( 'list_layout_style', $article );
		$this->assertEquals( 'small', $article['layout'] );
	}
}
