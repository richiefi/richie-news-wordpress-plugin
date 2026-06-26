# Changelog


## 3.1.0 (2026-06-26)

* feat: send plugin version with Richie requests

## 3.0.0 (2026-04-10)

### ⚠ BREAKING CHANGES

* PHP 7.4 is no longer supported. PHP 7.4 reached EOL in
December 2022 and the codebase already uses PHP 8.0+ features.

* fix: add flush_cache query param to bust asset transient
* fix: clear inline script/style extra data after get_assets() wp_head() run
* feat: discover inline <style> sub-resources as article assets
* feat!: drop PHP 7.4 support, require PHP 8.0+
* fix: improve image URL detection and add asset discovery helpers
* feat: resolve best image size from srcset and WP attachment lookup
* fix: resolve PHP 8.4 deprecation errors in tests
* fix: rework article image discovery for lazyload, inline styles and srcset
* fix: use emitted asset handles and add CSS dependency discovery in asset feed

## 2.1.0 (2026-03-04)

* feat(richie): add request init hook for REST integrations

## 2.0.0 (2026-02-13)

* feat: cli tool for testing article output
* feat: delete article collections
* fix: don't show unpublished notification if no data
* feat: new feed source editor
* fix: prevent crash if template rendering fails
* fix: rendered html missing css/js if caches are cold
* feat: show preview of the feed
* feat: support block layout templates (html)
* feat: support layout editor in wordpress

## 1.8.0 (2026-02-03)

* feat: access control section in sections
* fix: colorpicker causing js error
* fix: error message if there are no issues
* fix(tests): tests were using removed v3 api
