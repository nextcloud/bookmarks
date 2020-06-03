# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.1] - 2020-06-03

## Fixed

- Fixed getChildren SQL query

## [3.1.0] - 2020-06-03

## New
1d0d91689cdab27f26c168d7ffb2201ac69c2ef9 Bookmarklet: Add folder picker
906de9ce56792638ff03ba10f6afc964acf999bf Add option to copy public link as RSS feed
e86437c93bd9e733731bb2a650c9dd4ffa216639 Add option to open selection in new tabs
45da16e9d795c1aca474b88b1141e1ae7d440a5d Allow bulk selection actions for folders

## Changed
e7eb042d7c5f14feba284884689327822ba4fa37 Import into currently open folder

## Fixed
622103eb0949eb347335b73f97ae71ab5e33fef0 Fix tag filtering
e391da95c3049cf5bd637235075c8df8d82282f4 Filter by tag: Include tags from shared folders
04cb5e01c63f0898e16ae34be4d925e00a96e820 Fix shared folder icon
d17c7cb96d36b1e6a80289864f3ada4a7eff8756 Fix shares
ba523ff9bffc90bfa554acd2c675a93067844cde Fix: Display a loading indicator while moving items
88d32dd23bb9cd7cb72d3c6d44c820cd3d7a3cf5 Fix Bookmark deletion by selection: Remove only bookmark entry not whole bookmark
abe7eaf039a52074477b336eb6bd49b3698a41f8 Fix search when sorting alphabetically
fdbe69ebc888132a4bb88f834c25d5f492b0fa10 Docs: Document /export API endpoint

## [3.0.13] - 2020-05-11

## Changed
- Fix export
- Really drop libgmp dependency

## [3.0.12] - 2020-05-08

## Changed
 - Fix bookmark deletion
 - Fix v3 migration: Correctly account for duplicates in the root folder

## [3.0.11] - 2020-05-07

## Changed
 - Fix Auth error for users of certain setups


## [3.0.10] - 2020-05-05

## Changed
 - Fix Auth error when creating bookmarks without selecting a folder


## [3.0.9] - 2020-05-05

## Changed
 - Change column type for tree index to allow **alot** of bookmarks.

## [3.0.8] - 2020-05-04

## Changed
 - Fix Authorization for newBookmark API endpoint
 - Try to fix Authentication for some setups
 - Remove orphaned items from bookmarks tree
 - Remove superfluous shared folders from bookmarks tree
 - Fix share creation to avoid sharing a folder with oneself

## [3.0.7] - 2020-05-03

## Changed
 - Fix delete-all-bookmarks API endpoint

## [3.0.6] - 2020-05-03

## Changed
 - Fix bookmarks by folders API endpoint
 - Fix v3 db migration to account for db inconsistencies
 - Fix client-side usage of 'finally'
 - Fix getFolders API endpoint

## [3.0.5] - 2020-04-30

## Changed
 - Fix: Remove uninstall repair step which would also run on disabling the app
- Fix bookmarklet

## [3.0.4] - 2020-04-30

## Changed
 - Fix: Delete shares when deleting a shared folder
 
## [3.0.3] - 2020-04-30

## Changed
 - Fix hash endpoint

## [3.0.2] - 2020-04-30

## Changed
 - Fix sharee search for installations without mod_rewrite

## [3.0.1] - 2020-04-30

## Changed
 - Fix hash endpoint

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

[3.1.0]: https://github.com/nextcloud/bookmarks/compare/v3.0.13...v3.1.0
[3.0.13]: https://github.com/nextcloud/bookmarks/compare/v3.0.12...v3.0.13
[3.0.12]: https://github.com/nextcloud/bookmarks/compare/v3.0.11...v3.0.12
[3.0.11]: https://github.com/nextcloud/bookmarks/compare/v3.0.10...v3.0.11
[3.0.10]: https://github.com/nextcloud/bookmarks/compare/v3.0.9...v3.0.10
[3.0.9]: https://github.com/nextcloud/bookmarks/compare/v3.0.8...v3.0.9
[3.0.8]: https://github.com/nextcloud/bookmarks/compare/v3.0.7...v3.0.8
[3.0.7]: https://github.com/nextcloud/bookmarks/compare/v3.0.6...v3.0.7
[3.0.6]: https://github.com/nextcloud/bookmarks/compare/v3.0.5...v3.0.6
[3.0.5]: https://github.com/nextcloud/bookmarks/compare/v3.0.4...v3.0.5
[3.0.4]: https://github.com/nextcloud/bookmarks/compare/v3.0.3...v3.0.4
[3.0.3]: https://github.com/nextcloud/bookmarks/compare/v3.0.2...v3.0.3
[3.0.2]: https://github.com/nextcloud/bookmarks/compare/v3.0.1...v3.0.2
[3.0.1]: https://github.com/nextcloud/bookmarks/compare/v3.0.0...v3.0.1
[3.0.0]: https://github.com/nextcloud/bookmarks/compare/v2.3.4...v3.0.0
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
