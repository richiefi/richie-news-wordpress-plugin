# WordPress Plugins Development Guide

This repository contains two WordPress plugins for the Richie news platform.

## Plugins

### richie (Main Plugin)
News feed plugin that provides REST API endpoints for article delivery.

**Location:** `/richie/`

### richie-editions-wp (Submodule)
E-paper/editions integration plugin. This is a **git submodule** from a separate repository.

**Location:** `/richie-editions-wp/`

## Architecture

Both plugins follow the WordPress Plugin Boilerplate pattern:

```
plugin/
├── plugin-name.php          # Entry point, plugin metadata
├── includes/                # Core classes
│   ├── class-*-loader.php   # Hook registration
│   └── class-*.php          # Business logic
├── admin/                   # Admin-facing (wp-admin)
├── public/                  # Frontend-facing
├── templates/               # Theme-overridable templates
└── languages/               # Translations
```

## Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- PHP 8.0+ compatibility required
- WordPress 6.0+ minimum
- Run `composer phpcs` to check standards

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | `Plugin_Name_Class_Name` | `Richie_News_Article` |
| Functions | `plugin_name_function()` | `richie_get_sources()` |
| Hooks | `plugin_name_hook_name` | `richie_editions_has_access` |
| Options | `plugin_name_option` | `richie_news_sources` |

## Key Classes

### richie
- `Richie` - Main plugin class
- `Richie_Loader` - Hook orchestration
- `Richie_Public` - REST API routes
- `Richie_Admin` - Settings pages
- `Richie_News_Article` - Article data model

### richie-editions-wp
- `Richie_Editions_Wp` - Main plugin class
- `Richie_Editions_Service` - Remote API client
- `Richie_Editions_Issue` - Issue data model

## Testing

Tests require a database, so use Podman Compose:

```bash
# Start test environment
podman compose -f docker-compose.phpunit.yml up -d

# Run tests
podman compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit_8_0 phpunit
```

Test files are in each plugin's `tests/` directory (`richie/tests/`, `richie-editions-wp/tests/`).

## Best Practices

### Hooks
- Use `add_action()` and `add_filter()` via the Loader class
- Prefix all hooks with plugin name to avoid conflicts

### Database
- Use `$wpdb` for custom queries
- Prefer WordPress options API for settings
- Use transients for caching remote data

### Security
- Sanitize all input: `sanitize_text_field()`, `absint()`, etc.
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Use nonces for form submissions
- Check capabilities before admin actions

### REST API
- Register routes with `register_rest_route()`
- Use permission callbacks
- Return `WP_REST_Response` or `WP_Error`

### Internationalization
- Wrap strings in `__()` or `_e()`
- Use plugin text domain: `richie` or `richie-editions-wp`

## Working with the Submodule

```bash
# Update submodule
git submodule update --remote richie-editions-wp

# Initialize after clone
git submodule init
git submodule update
```

## News API Documentation

API documentation for the Richie news feed format is at https://richie.dev/docs/news/

Key pages:
- [Feed Structure](https://richie.dev/docs/news/feed-structure) - Overview of feed format, tabs, caching
- [Section Feeds](https://richie.dev/docs/news/section-feeds) - Section feed endpoints
- [Article Feeds](https://richie.dev/docs/news/article-feeds) - Article feed format
- [App Assets](https://richie.dev/docs/news/app-assets) - Asset delivery
- [Display Ads](https://richie.dev/docs/news/display-ads) - Ad integration
- [JavaScript API](https://richie.dev/docs/news/js-api) - JS bridge API

When working on REST API endpoints or feed generation, fetch relevant docs for reference.

### Related rest apis in this plugin

- `/wp-json/richie/v1/news/<section>?token=abcd` - Section feed
- `/wp-json/richie/v1/article/<id>?token=abcd` - Single article data
- `/wp-json/richie/v1/assets` - App assets
- `/wp-json/richie/v1/search?q=<search_string>&token=abcd` - Search articles

## Article Asset Discovery

The Richie app renders articles offline — `content_html_document` is the document root. Every asset referenced in the HTML (images, fonts, CSS, JS) must be available to the app before rendering. Assets can be included either in the global asset feed (`/wp-json/richie/v1/assets`) or in the article-level `photos`/`assets` fields. Missing assets cause rendering failures with no network fallback.

### Key behaviours

- **`photos`** — images referenced in article HTML. Each entry must have a corresponding reference in the HTML; unused photo entries waste bandwidth.
- **`assets`** — scripts, stylesheets, fonts, and other non-image files.
- **`content_html_document`** is the offline document root. All asset `local_name` values are paths relative to it.
- **`srcset` is stripped** from `<img>` — the best candidate is resolved (WP attachment lookup → srcset parsing) and written to `src`. `<source>` inside `<picture>` gets the best candidate written back as a single-entry `srcset`.
- **Inline `<style>` blocks** are scanned for `url()` references. Same-origin, on-disk assets are added to `assets` and the url() tokens are rewritten to local names.
- **`get_post_galleries()`** only detects native WordPress `[gallery]` shortcodes. Third-party gallery plugins (Modula, etc.) are not detected — their images go through the general image discovery path.

### Third-party plugin compatibility

When a third-party plugin's output causes asset discovery issues, **make a general fix** rather than plugin-specific hacks. For example:

- If a plugin renders images at a non-standard size with the full-size URL in a custom attribute, fix the general attribute scanning logic.
- If a plugin uses a custom gallery block, rely on the DOM-level image scan rather than adding special-case detection for that plugin.
- Plugin-specific code paths become maintenance burden and break when plugins are updated.

## Developing

When developing new feature, keep session diary in `PROGRESS.md` file in the root of the feature's directory.

## Commit Messages

- Use **Conventional Commits** only for changes that should appear in changelog/release notes.
- For work-in-progress, internal refactors, or non-changelog commits, use a plain descriptive commit message.
