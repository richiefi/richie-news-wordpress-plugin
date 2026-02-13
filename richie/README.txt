=== Richie News Platform ===
Tags: rest, feed, api
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.7.3
Requires PHP: 7.4
License: Copyright Richie Oy

This plugin provides backend feeds support for Richie News Platform.

== Description ==

Richie News Platform plugin provides JSON feeds to be used in Richie News Platform:

- News feed for article sets
- Article details feed
- Assets feed

== Installation ==

1. Upload `richie` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in Wordpress.
3. Configure required settings under 'Settings -> Richie'.

== Changelog ==
= WIP =
* support php 8.3 and wp 6.9
* consolidated api versioning to v1 only
* removed pmpro plugin support (we don't want to maintain custom plugins, we will provide own solution later)
* removed all maggio support (Richie Editions), since it's now provided by separate plugin
* removed special support for herald theme

= 1.7.3 (21.03.2022) =
* Fix external url in v1 api

= 1.7.2 (09.03.2022) =
* Fix missing data from featured post with external url

= 1.7.1 (08.03.2022) =
* Bug Fix

= 1.7.0 (07.03.2022) =
* Support external url in featured post
* Support hide_on_mobile flag in featured post

= 1.6.2 (27.01.2022) =
* Include mraid tag to content html

= 1.6.1 (14.12.2021) =
* Add required fields to v3 article api

= 1.6.0 (14.12.2021) =
* Implementation for v3 apis

= 1.5.1 (03.10.2021) =
* Include `text_left_square_thumb_right` layout style

= 1.5.0 (03.10.2021) =
* Implmented api for news search

= 1.4.7 (09.02.2021) =
* Fix potential php notice when configuring source with herald block
* Possible workaround for random failures on settings save, when using object cache

= 1.4.6 (01.02.2021) =
* Support readpeak ad configuration

= 1.4.5 (01.12.2020) =
* Limit index api call (and settings render) to plugin settings page only

= 1.4.4 (05.10.2020) =
* Fix failing html5 parser because of duplicate ids in source

= 1.4.3 (01.10.2020) =
* Fix invalid html output if script tags includes html tags (templates etc)

= 1.4.2 (25.05.2020) =
* Fix possible conflicts with other plugins and client scripts

= 1.4.1 (05.05.2020) =
* Support filtering news source items with tag list

= 1.4.0 (04.05.2020) =
* Support background color property in news sources
* Support for Mediabox Featured Post -custom type

= 1.3.1 (02.04.2020) =
* Improved herald-theme module support

= 1.3.0 (31.03.2020) =
* More protocol relative url fixes
* Fix issue with ajax requests
* Support duplicate items in feeds

= 1.2.12 (04.03.2020)
* Fix issue with protocol relative urls in assets

= 1.2.11 (25.02.2020)
* Use wp post id instead of guid as article id

= 1.2.10 (09.01.2020) =
* Include wp post id and original title to analytics_data property

= 1.2.9 (03.01.2020) =
* Fix issue with feature image included in both photos and assets array

= 1.2.8 (30.12.2019) =
* Support for google ad provider

= 1.2.7 (23.10.2019) =
* Improved removal of duplicates from photo galleries

= 1.2.6 (12.09.2019) =
* Convert asset local name to ascii characters

= 1.2.5 (11.09.2019) =
* Include img tags in body to photo gallery

= 1.2.4 (22.07.2019) =
* Resolve relative asset pack urls (paths with .. or .)
* Return generated asset list from the api (merged with custom data)

= 1.2.3 (24.06.2019) =
* Fix invalid function call

= 1.2.2 (24.06.2019) =
* Filter articles without guid

= 1.2.1 (06.05.2019) =
* Fix cronjob function call
* Fix pmpro level check

= 1.2.0 (30.04.2019) =
* Schedule maggio index update in cronjob (hourly)
* Unit test support
* Support extra query params for maggio redirection

= 1.1.2 (11.04.2019) =
* Support time limited maggio index files
* Include list group title only to the first item

= 1.1.1 (09.04.2019) =
* Fix issue with images
* Fix issue with template rendering context

= 1.1.0 (08.04.2019) =
* Fix an issue with non existing gallery images
* Feature: ad slots configuration
* Localization fixes
* Finnish localization
* Code refactoring

= 1.0.2 (29.03.2019) =
* Fix issue with updated_date in news feed

= 1.0.1 =
* Bug fixes

= 1.0 =
* Initial version

== Hooks & Filters ==

= richie_article_access_entitlements =

Filters the access entitlements for an article in the API response.

Use this filter to implement custom access control logic, such as integration with membership plugins, per-article overrides, or dynamic entitlement assignment based on custom rules. You can modify the array by appending new entitlements, removing existing ones, or completely replacing the array.

**Parameters:**

* `$access_entitlements` (string[]) - Array of entitlement strings (e.g., `['PREMIUM']`). Empty array indicates a free article. This is the existing value you can append to or modify.
* `$post` (WP_Post) - The WordPress post object for the article.

**Return:**

(string[]) Modified array of entitlement strings. All values must be strings (non-string values will be filtered out).

**Example 1: Append custom entitlement based on post meta**

```php
add_filter( 'richie_article_access_entitlements', function( $entitlements, $post ) {
    $is_subscriber_only = get_post_meta( $post->ID, 'subscriber_only', true );

    if ( $is_subscriber_only ) {
        // Append to existing entitlements
        $entitlements[] = 'SUBSCRIBER_ACCESS';
    }

    return $entitlements;
}, 10, 2 );
```

**Example 2: Override entitlements completely**

```php
add_filter( 'richie_article_access_entitlements', function( $entitlements, $post ) {
    // Force all articles to require premium access
    return array( 'PREMIUM_REQUIRED' );
}, 10, 2 );
```

**Example 3: Integration with membership plugin**

```php
add_filter( 'richie_article_access_entitlements', function( $entitlements, $post ) {
    // Check if post requires a specific membership level
    $required_level = get_post_meta( $post->ID, 'membership_level', true );

    if ( $required_level ) {
        // Convert membership level to entitlement string
        $entitlements[] = strtoupper( $required_level );
    }

    // Remove duplicates and return
    return array_values( array_unique( $entitlements ) );
}, 10, 2 );
```

**Example 4: Conditional access based on post age**

```php
add_filter( 'richie_article_access_entitlements', function( $entitlements, $post ) {
    $post_date = strtotime( $post->post_date );
    $days_old = ( time() - $post_date ) / DAY_IN_SECONDS;

    // Articles older than 30 days become free
    if ( $days_old > 30 ) {
        return array(); // Empty array = free article
    }

    return $entitlements;
}, 10, 2 );
```

**Notes:**

* The filter runs only when premium categories are configured in plugin settings.
* Returning an empty array makes the article free (no `access_entitlements` property in API response).
* The plugin automatically removes duplicates from the final array.
* The filter is applied before the entitlements are added to the article object.
* **Value validation:** Only string values are allowed in the returned array. Non-string values (objects, arrays, numbers, etc.) are automatically filtered out and logged as warnings.
* **Error handling:** If your filter throws an exception or returns a non-array value, the plugin logs the error and continues with the original entitlements. This prevents the entire article API from failing due to filter issues.

== Configuration ==

= General =

1. `Access token` - Random string to be used as authentication for Richie feeds.
    NOTE: with token, full content of the article can be accessed despite of pmpro configuration.
2. `Search list layout` - Layout style to be used in search results.

= News sources =

- Sources provides articles to the article sets. Article sets must be created first using separate UI.
- Article set may contain multiple source items.
- Source includes filters like category and number of posts, which is used to determine, which articles are included.
- Sources can be reordered with drag&drop.
- Sources have "draft" and "published" versions. Modifying sources requires publish to make changes "live".

= Assets =

JSON array of assets required in articles. These will be available offline.

== News feed ==

Plugin provides following rest apis:
`/wp-json/richie/v1/news/<article_set_name>?token=<configured_token>`
`/wp-json/richie/v1/article/<article_id>?token=<configured_token>`
`/wp-json/richie/v1/assets`
`/wp-json/richie/v1/search?q=<search_query>&token=<configured_token>`

`news` endpoint returns an array of articles for the specific article set, using configured sources.
`article` endpoint returns details for specific article, including rendered html content. Accepts optional `template` parameter to select a template variant (e.g. `&template=premium` uses `richie-news-premium.html`).
`assets` endpoint returns configured assets (in plugin settings)

== Article Templates ==

Richie renders articles as full HTML pages. The template system supports both block (HTML) and legacy PHP templates.

= Template Resolution Order =

Templates are resolved in this priority:

1. **Theme HTML template** — `<theme>/richie/richie-news-article.html`
2. **Theme PHP template** — `<theme>/richie/richie-news-article.php`
3. **Site Editor template** — The `richie-article` template from Appearance > Editor (block themes only)
4. **Plugin HTML template** — `richie/templates/richie-news.html` (plugin fallback)
5. **Plugin PHP template** — Built-in legacy template via `Richie_Template_Loader`

The first match wins. This applies to both the REST API article endpoint and the frontend `?richie_news` template loader.

= Block Templates (Recommended) =

With a block theme (e.g. Twenty Twenty-Five), Richie registers a `richie-article` template in the Site Editor. This is enabled by default via the **"Use Site Editor template"** checkbox under Richie > Settings > General.

To customize:

1. Go to **Appearance > Editor > Templates**
2. Find and edit the **Richie Article** template
3. Use standard blocks: Post Title, Post Content, Post Featured Image, Post Date, etc.

The template is rendered with `do_blocks()` and wrapped in a full HTML document including `wp_head()` and `wp_footer()` assets.

= Theme HTML Override =

Place a block markup file at:

`wp-content/themes/<your-theme>/richie/richie-news-article.html`

This takes the highest priority. The file should contain block markup (no `<html>` wrapper needed — the plugin adds it automatically).

Example:

```
<!-- wp:group {"tagName":"main"} -->
<main class="wp-block-group">
  <!-- wp:post-title {"level":1} /-->
  <!-- wp:post-content /-->
</main>
<!-- /wp:group -->
```

= Legacy PHP Override =

Place a PHP template at:

`wp-content/themes/<your-theme>/richie/richie-news-article.php`

This template should output a full HTML page starting from `<!doctype html>`. The post is available via the global `$post` variable.

= Custom Template Variant =

Both the article REST API and the frontend template loader support a `template` parameter to select a variant.

- REST API: `/wp-json/richie/v1/article/<id>?token=...&template=premium`
- Frontend: `?richie_news&token=...&template=premium`

This changes the template lookup from the default `richie-news-article.html` (or `.php`) to `richie-news-premium.html` (or `.php`) in the theme's `richie/` directory. If no matching variant is found, the normal resolution cascade applies.

= Admin Notices =

The Richie settings page shows notices when:

- Block templates are enabled but the active theme is not a block theme
- A custom theme template override is detected (which takes priority over the Site Editor template)
