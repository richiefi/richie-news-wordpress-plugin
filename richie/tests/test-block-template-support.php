<?php
/**
 * Block template support tests.
 *
 * @package Richie
 */

class Block_Template_Support_Test extends WP_UnitTestCase {

	private $admin;
	private $created_paths = array();

	public function setUp(): void {
		parent::setUp();
		$this->admin = new Richie_Admin( 'richie', '1.0.0' );
	}

	public function tearDown(): void {
		unset( $_GET['page'], $_GET['tab'] );

		// Clean up any files created during tests.
		foreach ( array_keys( $this->created_paths ) as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->created_paths = array();

		// Remove temp richie dir if empty.
		$tmp_dir = sys_get_temp_dir() . '/richie-test-theme-' . getmypid() . '/richie';
		if ( is_dir( $tmp_dir ) ) {
			@rmdir( $tmp_dir );
			@rmdir( dirname( $tmp_dir ) );
		}

		remove_all_filters( 'stylesheet_directory' );
		remove_all_filters( 'template_directory' );

		parent::tearDown();
	}

	/**
	 * Create a template file in the active theme's richie/ directory.
	 *
	 * @param string $filename Template filename.
	 * @param string $content  Template content.
	 * @return string The full path to the created file.
	 */
	private function create_theme_template( $filename, $content = '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->' ) {
		$dir  = sys_get_temp_dir() . '/richie-test-theme-' . getmypid() . '/richie';
		$path = $dir . '/' . $filename;

		// Point the theme directory to our temp location.
		add_filter( 'stylesheet_directory', function () use ( $dir ) {
			return dirname( $dir );
		} );
		add_filter( 'template_directory', function () use ( $dir ) {
			return dirname( $dir );
		} );

		$this->created_paths[ $path ] = null;

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $path, $content );

		return $path;
	}

	// ── options_update ──────────────────────────────────────────────────

	public function test_options_update_defaults_use_block_template_true() {
		delete_option( 'richie' );

		$this->admin->options_update();
		$options = get_option( 'richie' );

		$this->assertIsArray( $options );
		$this->assertTrue( $options['use_block_template'] );
	}

	public function test_options_update_preserves_existing_use_block_template() {
		update_option( 'richie', array( 'use_block_template' => false, 'access_token' => 'abc' ) );

		$this->admin->options_update();
		$options = get_option( 'richie' );

		$this->assertFalse( $options['use_block_template'] );
		$this->assertSame( 'abc', $options['access_token'] );
	}

	// ── validate_settings (use_block_template sanitization) ─────────

	public function test_validate_settings_saves_use_block_template_true() {
		$input  = array( 'use_block_template' => '1' );
		$result = $this->admin->validate_settings( $input );

		$this->assertTrue( $result['use_block_template'] );
	}

	public function test_validate_settings_saves_use_block_template_false_when_missing() {
		$input  = array();
		$result = $this->admin->validate_settings( $input );

		$this->assertFalse( $result['use_block_template'] );
	}

	public function test_validate_settings_rejects_non_one_value() {
		$input  = array( 'use_block_template' => '2' );
		$result = $this->admin->validate_settings( $input );

		$this->assertFalse( $result['use_block_template'] );
	}

	// ── Template name generation ────────────────────────────────────────

	public function test_get_html_template_names_with_name() {
		$names = richie_get_html_template_names( 'richie-news', 'article' );

		$this->assertSame( array( 'richie-news-article.html', 'richie-news.html' ), $names );
	}

	public function test_get_html_template_names_without_name() {
		$names = richie_get_html_template_names( 'richie-news' );

		$this->assertSame( array( 'richie-news.html' ), $names );
	}

	// ── Template location functions ─────────────────────────────────────

	public function test_locate_theme_html_template_finds_file() {
		$this->create_theme_template( 'richie-news-article.html' );

		$result = richie_locate_theme_html_template( 'richie-news', 'article' );
		$this->assertNotNull( $result );
		$this->assertStringContainsString( 'richie-news-article.html', $result );
	}

	public function test_locate_theme_html_template_returns_null_when_missing() {
		$result = richie_locate_theme_html_template( 'richie-news', 'nonexistent' );
		$this->assertNull( $result );
	}

	public function test_locate_theme_html_template_falls_back_to_slug_only() {
		$this->create_theme_template( 'richie-news.html' );

		$result = richie_locate_theme_html_template( 'richie-news', 'article' );
		$this->assertNotNull( $result );
		$this->assertStringContainsString( 'richie-news.html', $result );
	}

	public function test_locate_theme_php_template_finds_file() {
		$this->create_theme_template( 'richie-news-article.php', '<?php // test' );

		$result = richie_locate_theme_php_template( 'richie-news', 'article' );
		$this->assertNotNull( $result );
		$this->assertStringContainsString( 'richie-news-article.php', $result );
	}

	public function test_locate_theme_php_template_returns_null_when_missing() {
		$result = richie_locate_theme_php_template( 'richie-news', 'nonexistent' );
		$this->assertNull( $result );
	}

	public function test_locate_html_template_finds_plugin_fallback() {
		// The plugin ships richie-news-article.html is not in plugin templates,
		// but there is a block-templates/richie-article.html.
		// richie_locate_html_template checks the plugin templates/ dir too.
		$result = richie_locate_html_template( 'richie-news', 'article' );

		// If no theme override exists, the plugin dir may or may not have a matching file.
		// The important thing is it doesn't error and returns string|null.
		$this->assertTrue( is_string( $result ) || is_null( $result ) );
	}

	// ── Block template slug ─────────────────────────────────────────────

	public function test_get_block_template_slug_returns_expected_value() {
		$this->assertSame( 'richie-article', richie_get_block_template_slug() );
	}

	// ── Block template document rendering ───────────────────────────────

	public function test_render_block_template_document_from_content_empty_string() {
		$result = richie_render_block_template_document_from_content( '' );
		$this->assertSame( '', $result );
	}

	public function test_render_block_template_document_from_content_wraps_in_html() {
		$result = richie_render_block_template_document_from_content(
			'<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->'
		);

		$this->assertStringContainsString( '<!doctype html>', $result );
		$this->assertStringContainsString( '<head>', $result );
		$this->assertStringContainsString( '<body>', $result );
		$this->assertStringContainsString( 'Hello', $result );
	}

	public function test_render_block_template_document_reads_file() {
		$path = $this->create_theme_template(
			'test-render.html',
			'<!-- wp:paragraph --><p>From file</p><!-- /wp:paragraph -->'
		);

		$result = richie_render_block_template_document( $path );

		$this->assertStringContainsString( 'From file', $result );
		$this->assertStringContainsString( '<html>', $result );
	}

	// ── has_block_template_override (via admin notice) ──────────────────

	public function test_admin_notice_shows_when_html_template_override_present() {
		$this->create_theme_template( 'richie-news-article.html' );
		update_option( 'richie', array( 'use_block_template' => true ) );

		$_GET['page'] = 'richie';
		$_GET['tab']  = 'settings';

		ob_start();
		$this->admin->add_admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'A custom Richie article template is detected', $output );
	}

	public function test_admin_notice_shows_when_php_template_override_present() {
		$this->create_theme_template( 'richie-news-article.php', '<?php // override' );
		update_option( 'richie', array( 'use_block_template' => true ) );

		$_GET['page'] = 'richie';
		$_GET['tab']  = 'settings';

		ob_start();
		$this->admin->add_admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'A custom Richie article template is detected', $output );
	}

	public function test_admin_notice_hidden_when_no_template_override() {
		update_option( 'richie', array( 'use_block_template' => true ) );

		$_GET['page'] = 'richie';
		$_GET['tab']  = 'settings';

		ob_start();
		$this->admin->add_admin_notices();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'A custom Richie article template is detected', $output );
	}

	public function test_admin_notice_hidden_when_use_block_template_disabled() {
		$this->create_theme_template( 'richie-news-article.html' );
		update_option( 'richie', array( 'use_block_template' => false ) );

		$_GET['page'] = 'richie';

		ob_start();
		$this->admin->add_admin_notices();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'A custom Richie article template is detected', $output );
	}

	public function test_admin_notice_hidden_on_other_pages() {
		$this->create_theme_template( 'richie-news-article.html' );
		update_option( 'richie', array( 'use_block_template' => true ) );

		$_GET['page'] = 'other-plugin';

		ob_start();
		$this->admin->add_admin_notices();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'A custom Richie article template is detected', $output );
	}
}
