=== Richie News Platform ===
Tags: rest, feed, api
Requires at least: 4.0
Tested up to: 6.9
Stable tag: 1.7.3
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
`article` endpoint returns details for specific article, including rendered html content
`assets` endpoint returns configured assets (in plugin settings)

Rendering is done using a template system. Theme may provide an template for this content by placing `richie-news-article.php?`
inside `<theme_path>/richie` folder. It should return full html page starting from doctype.
