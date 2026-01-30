<?php
/**
 * Class Test_Richie_Access_Control
 *
 * @package Richie
 */

/**
 * Tests for Richie access control functionality.
 */
class Test_Richie_Access_Control extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Clear any existing premium categories setting.
		delete_option( 'richie' );
	}

	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'richie' );
		remove_all_filters( 'richie_article_access_entitlements' );
	}

	/**
	 * Test that free articles have no access_entitlements.
	 */
	public function test_free_article_has_no_entitlements() {
		$post_id = $this->factory->post->create();

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectNotHasProperty( 'access_entitlements', $result );
	}

	/**
	 * Test that articles in premium category include entitlements.
	 */
	public function test_premium_article_uses_category_name_as_entitlement() {
		$category_id = $this->factory->category->create( array( 'name' => 'Premium' ) );
		update_option( 'richie', array( 'premium_categories' => array( $category_id ) ) );

		$post_id = $this->factory->post->create(
			array(
				'post_category' => array( $category_id ),
			)
		);

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectHasProperty( 'access_entitlements', $result );
		$this->assertEquals( array( 'PREMIUM' ), $result->access_entitlements );
	}

	/**
	 * Test that category names are converted to UPPER_SNAKE_CASE.
	 */
	public function test_category_name_converted_to_upper_snake_case() {
		$category_id = $this->factory->category->create( array( 'name' => 'Subscriber Only' ) );
		update_option( 'richie', array( 'premium_categories' => array( $category_id ) ) );

		$post_id = $this->factory->post->create(
			array(
				'post_category' => array( $category_id ),
			)
		);

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectHasProperty( 'access_entitlements', $result );
		$this->assertEquals( array( 'SUBSCRIBER_ONLY' ), $result->access_entitlements );
	}

	/**
	 * Test that hyphenated category names are converted correctly.
	 */
	public function test_hyphenated_category_name_converted() {
		$category_id = $this->factory->category->create( array( 'name' => 'vip-access' ) );
		update_option( 'richie', array( 'premium_categories' => array( $category_id ) ) );

		$post_id = $this->factory->post->create(
			array(
				'post_category' => array( $category_id ),
			)
		);

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectHasProperty( 'access_entitlements', $result );
		$this->assertEquals( array( 'VIP_ACCESS' ), $result->access_entitlements );
	}

	/**
	 * Test that the filter can override entitlements.
	 */
	public function test_entitlements_filter_override() {
		$post_id = $this->factory->post->create();

		// Set up a premium category so the filter runs.
		$category_id = $this->factory->category->create( array( 'name' => 'Test' ) );
		update_option( 'richie', array( 'premium_categories' => array( $category_id ) ) );

		// Filter to add custom entitlement regardless of category.
		add_filter(
			'richie_article_access_entitlements',
			function ( $entitlements, $post ) {
				return array( 'CUSTOM_ACCESS' );
			},
			10,
			2
		);

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectHasProperty( 'access_entitlements', $result );
		$this->assertEquals( array( 'CUSTOM_ACCESS' ), $result->access_entitlements );
	}

	/**
	 * Test that multiple premium categories combine entitlements.
	 */
	public function test_multiple_premium_categories_combine_entitlements() {
		$cat1 = $this->factory->category->create( array( 'name' => 'Premium' ) );
		$cat2 = $this->factory->category->create( array( 'name' => 'VIP' ) );

		update_option( 'richie', array( 'premium_categories' => array( $cat1, $cat2 ) ) );

		// Post in both premium categories.
		$post_id = $this->factory->post->create(
			array(
				'post_category' => array( $cat1, $cat2 ),
			)
		);

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectHasProperty( 'access_entitlements', $result );
		$this->assertContains( 'PREMIUM', $result->access_entitlements );
		$this->assertContains( 'VIP', $result->access_entitlements );
	}

	/**
	 * Test that post in non-premium category has no entitlements.
	 */
	public function test_post_in_non_premium_category_has_no_entitlements() {
		$premium_cat     = $this->factory->category->create( array( 'name' => 'Premium' ) );
		$non_premium_cat = $this->factory->category->create( array( 'name' => 'News' ) );

		// Only premium_cat is marked as premium.
		update_option( 'richie', array( 'premium_categories' => array( $premium_cat ) ) );

		// Post only in non-premium category.
		$post_id = $this->factory->post->create(
			array(
				'post_category' => array( $non_premium_cat ),
			)
		);

		$article = new Richie_Article( array( 'access_token' => 'test' ) );
		$result  = $article->generate_article( get_post( $post_id ) );

		$this->assertObjectNotHasProperty( 'access_entitlements', $result );
	}
}
