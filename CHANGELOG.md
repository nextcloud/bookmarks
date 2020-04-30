# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2020-04-30

### Changed
 - UI: Selection: Implement "select all" and "cancel selection"
 - Better document how the screeenly api url looks like
 - Move UI: Automatically open current folder
 - Implement bookmark counting endpoints and UI indicators
 - Fix bookmark creation: Assign folders correctly
 - Drop tables on uninstall
 - Fix import and export
 - Allow editing URLs
 - Implement a full children endpoint
 - Breadcrumbs: -AddBookmark button +Add Folder/Bookmark dropdown
 - Don't enable scraping by default
 - Drop libgmp dependency
 - Major backend refactoring

### Added
 - Implement hash caching
 - Implement private sharing and public links of folders


## [2.3.4] - 2019-12-12

### Changed
- Fix Bookmark creation outside folders
- Fix browser compatibility

## [2.3.3]

### Changed
- Fix Creating bookmarks in the current folder

## [2.3.2]

### Changed
- Fix webpack build
- Fix Creating bookmarks in the current folder
- Fix Make folder icons clickable
- Fix translations

## [2.3.1]

### Changed
- FIX: Load tags on app init

## [2.3.0]

### Added
 - UI: Implement bulk editing
 - translations from transifex
 
### Changed
 - Fix overflow on bookmarks list
 - Fix sorting
 - Fix humanized duration: Use stem language only

## [2.2.0]

### Added
- Use routes history mode instead of hash URLs

### Changed
- Fix Sorting folders alphabetically
- Fix Allow canceling page fetches
- Fix Import
- Fix grid view, descriptions in list view & bread crumbs

## [2.1.1]

### Changed
- Fix build script

## [v2.1.0]

### Added

- Rewrite UI
- Allow limiting the number of bookmarks per user
- Allow disabling web requests to bookmarked web pages

## [v2.0.3]

### Changed
- NEW: Properly specify dependencies in app manifest (allows conditional support for nc 15 again)

Supported are NC 15 and 16, provided you are using PHP v7.1 and have gmp, intl and mbstring php extensions installed

## [2.0.2]

### Changed
- Drop support for nextcloud 15

## [2.0.1]

### Changed
- fix composer lock file

## [2.0.0]

### Changed
- gmp, intl, mbstring are now required
- Drop support for nextcloud 14 and php 7.0
- Switch URL normalizer to adhere strictly to WHATWG URL spec

## [1.1.2]

### Changed

- Revert breaking changes of v1.0.8

## [1.1.1]

### Changed
- Fix import from web UI

## [1.1.0]

### Added
- NEW: API endpoint to import into a specific folder

## [1.0.8]

### Changed
- gmp, intl, mbstring are now required
- FIX: Switch URL normalizer to adhere strictly to WHATWG URL spec
- FIX: Update dependencies
- FIX: Run previews job in small batches instead of all at once

## [1.0.6]

### Changed

- FIX: Set timeout for submitting tags
- NEW: Create favicon
- FIX: Bump interactjs from 1.3.4 to 1.4.10
- FIX: UrlNormalizer: Don't enforce normalization
- NEW: updated from transifex
- FIX: Truncate tags in grid view
- FIX: Remove background colors
- FIX folder collapse css
- FIX: Speed up findBookmarks SQL query


[2.3.4]: https://github.com/nextcloud/bookmarks/compare/v2.3.4...v3.0.0
[2.3.4]: https://github.com/nextcloud/bookmarks/compare/v2.3.3...v2.3.4
[2.3.3]: https://github.com/nextcloud/bookmarks/compare/v2.3.2...v2.3.3
[2.3.2]: https://github.com/nextcloud/bookmarks/compare/v2.3.1...v2.3.2
[2.3.1]: https://github.com/nextcloud/bookmarks/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/nextcloud/bookmarks/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/nextcloud/bookmarks/compare/v2.1.1...v2.2.0
[2.1.1]: https://github.com/nextcloud/bookmarks/compare/v2.1.0...v2.1.1
[2.1.0]: https://github.com/nextcloud/bookmarks/compare/v2.0.3...v2.1.0
[2.0.3]: https://github.com/nextcloud/bookmarks/compare/v2.0.2...v2.0.3
[2.0.2]: https://github.com/nextcloud/bookmarks/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/nextcloud/bookmarks/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/nextcloud/bookmarks/compare/v1.1.2...v2.0.0
[1.1.2]: https://github.com/nextcloud/bookmarks/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/nextcloud/bookmarks/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nextcloud/bookmarks/compare/v1.0.8...v1.1.0
[1.0.8]: https://github.com/nextcloud/bookmarks/compare/v1.0.6...v1.0.8
[1.0.6]: https://github.com/nextcloud/bookmarks/compare/v1.0.5...v1.0.6
