=== Richie ===
Contributors: (this should be a list of wordpress.org userid's)
Donate link: https://www.richie.fi
Tags: rest, feed, api, shortcode
Requires at least: 4.0
Tested up to: 5.1
License: Copyright Richie Oy

This plugin provides backend feeds and digital paper support for Richie Platform.

== Description ==

Richie Platform plugin provides following features:
- Shortcode for including list of Maggio issues
- Redirection url which constructs signin url and provides access to the actual magazine
- JSON feeds to be used in Richie Platform
- Supports paywall features (currently assuming PMPro plugin installed)

== Installation ==

1. Upload `richie` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in Wordpress.
3. Configure required settings under 'Settings -> Richie'.

== Changelog ==
= 1.1.1 (09.04.2019) =
* Fix issue with images

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

== Configuration ==

= General =

1. `Access token` - Random string to be used as authentication for Richie feeds.
    NOTE: with token, full content of the article can be accessed despite of pmpro configuration.
2. `Paywall` - Map PMPro levels to levels used in news feed.
    - `Metered` - Level which marks articles to be readable by anyone, but amount of article reads is limited
    - `Premium` - Level which marks articles to be access only by users having this levels

3. `Maggio settings`
    1. `Maggio organization` - Organization which includes maggio products.
    2. `Maggio hostname` - Full hostname to the Maggio HTML5 server, can be https://<client>.ap.richiefi.net or configured cname
    3. `Maggio secret` - Provided secret which is used to calculate signature hash for signin urls
    4. `Required membership level` - If set, user must have that level to access Maggio issues

= News sources =

- Sources provides articles to the article sets. Article sets must be created first using separate UI.
- Article set may contain multiple source items.
- Source includes filters like category and number of posts, which is used to determine, which articles are included.
- Sources can be reordered with drag&drop.
- Sources have "draft" and "published" versions. Modifying sources requires publish to make changes "live".

= Assets =

JSON array of assets required in articles. These will be available offline.

== Shortcode ==

Plugin provides `[maggio]` shortcode, which may be used to show grid of available issues.
Shortcode supports two attributes:
  - `product` (required): Maggio product id
  - `organization` (optional): Maggio organization, default value can be set in settings.
  - `number_of_issues` (optional): Amount of issues to be shown. If omitted, shows all issues.

Example:
```
[maggio id="main" number_of_issues="10]
```

Short code renders a template, which the theme may overwrite.
This can be done by placing `richie-maggio-index.php` inside `<theme_path>/richie` folder.
A basic template as a base can be found inside plugin's templates folder.

== News feed ==

Plugin provides following rest apis:
`/wp-json/richie/v1/news/<article_set_name>?token=<configured_token>`
`/wp-json/richie/v1/article/<article_id>?token=<configured_token>`
`/wp-json/richie/v1/assets`

`news` endpoint returns an array of articles for the specific article set, using configured sources.
`article` endpoint returns details for specific article, including rendered html content
`assets` endpoint returns configured assets (in plugin settings)

Rendering is done using a template system. Theme may provide an template for this content by placing `richie-news-article.php?`
inside `<theme_path>/richie` folder. It should return full html page starting from doctype.
