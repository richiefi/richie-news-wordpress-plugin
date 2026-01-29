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
- PHP 5.6+ compatibility required
- WordPress 4.6+ minimum
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
