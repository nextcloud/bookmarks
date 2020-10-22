# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - 2020-10-22

### New

- Add Dashboard widget
- Integrate with unified search
- Add bookmarks-only search UI

### Fixed

- Don't add empty tag to bookmarks without tags
- Improve performance of bulk actions
- Improve perforamnce of API

## [3.4.3] - 2020-10-09

### Fixed

- Fix tag duplication

## [3.4.2] - 2020-10-08

### Fixed
 - UI: Fix typo in rename button
 - Speed up import and mass deletion
 - UI: Improve waiting experience
 - Fix BookmarkMapper#mapRowToEntity
 - Fix export

## [3.4.1] - 2020-09-30

### Fixed
- Remove legacy files

## [3.4.0] - 2020-09-29

### Fixed
- UI: Fix navigation
- BookmarkController: counting bookmarks should only require READ perms
- Make tags clickable again
- UI: Make whole folder card clickable
- UI: Implement "Select all" instead of "select all visible"
- Make export and re-import work as expected
- UI: Make sure the newly created bookmark is visible in the list
- UI: Fix default icon for add-actions
- Speedup bookmark queries (#1182)
- UI: Fix folder route on direct entry
- UI: Fix settings styles
- UI: Don't show tags control in navigation if there are no tags
- UI: Fix bookmark/folder creation
- UI: Close actions after clicking
- UI: BookmarksList: Make cards fixed width and add padding
- UI: Automatically add currently filtered tags to new bookmarks
- UI: Only show checkboxes when selection mode is activated
- BookmarkController: Show default image for previews
- UI: Improve no-bookmarks view
- Fix don't allow creating folder loops
- UI: Allow editing bookmark title in sidebar
- Fix: Improve DB query performance
- Fix: Fix dependency hell with symfony v5 

### New
- Implement Pageres cli previewer
- Add custom sort option
- Refactor previews system and add support for new providers
- Try to fix favicon error
- Implement Flow integration (#1138)
- UI: Fix folder creation
- Implement dead link detection
- Use material design icons
- API: Add count endpoint publicly
- Implement file archiver (#1167)

## [3.4.0-beta.1] - 2020-08-27

### Fixed
- UI: Fix navigation
- BookmarkController: counting bookmarks should only require READ perms
- Make tags clickable again
- UI: Make whole folder card clickable
- UI: Implement "Select all" instead of "select all visible"
- Make export and re-import work as expected
- UI: Make sure the newly created bookmark is visible in the list
- UI: Fix default icon for add-actions
- Speedup bookmark queries (#1182)
- UI: Fix folder route on direct entry
- UI: Fix settings styles
- UI: Don't show tags control in navigation if there are no tags
- UI: Fix bookmark/folder creation
- UI: Close actions after clicking
- UI: BookmarksList: Make cards fixed width and add padding
- UI: Automatically add currently filtered tags to new bookmarks
- UI: Only show checkboxes when selection mode is activated
- BookmarkController: Show default image for previews
- UI: Improve no-bookmarks view

### New
- Implement Pageres cli previewer
- Add custom sort option
- Refactor previews system and add support for new providers
- Try to fix favicon error
- Implement Flow integration (#1138)
- UI: Fix folder creation
- Implement dead link detection
- Use material design icons
- API: Add count endpoint publicly
- Implement file archiver (#1167)

## [3.3.4] - 2020-08-16

### Fixed
- Fix folder endpoint
- Fix undefined index error

## [3.3.3] - 2020-07-30

### Fixed

- Fix repair step
- Fix ActivityPublisher when no user is authenticated

## [3.3.2] - 2020-07-28

### Fixed

- UI: Fix Navigation
- Fix tag count
- Fix deduplication repair step

## [3.3.1] - 2020-07-23

### Fixed

- Fix info.xml

## [3.3.0] - 2020-07-23

### New
- Implement Activity app integration

### Fixed
- Add repair step for duplicate shared folders
- Fix untagged search (on postgres)
- UI: Fix rename Tag
- Build: Don't ship source maps
- Repair steps: Add debug output
- Bookmarks: Additionally always sort by ID to make ordering stable
- Fix deletion of bookmarks that are not in tree
- Update dependencies

## [3.2.5] - 2020-07-17

### Fixed
 - UI: Open URLs in new tabs
 - UI: Fix infinite scroll
 - Update documentation of API responses
 - Fix changing bookmark's folders
 - Fix tags API

## [3.2.4] - 2020-06-29

### Fixed
- API: Fix PUT bookmark requests

## [3.2.2] - 2020-06-28

### New
- In grid view the entire bookmark behaves like a link
- Custom sort option setting for displaying the original order.
E.g. in case of a browser sync as source.

### Fixed
- Horizontal breadcrumb text alignment
- Checkbox size in grid view
- Menu toggle position
- Deletion of tags
- API: Don't send folder IDs that the client cannot access

## [3.2.1] - 2020-06-16

### Fixed
Fix service worker

## [3.2.0] - 2020-06-14

### Fixed
Bookmarklet: Fix description input

### New
Installable as Progressive web app

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

## v2.3.0

 - NEW: UI: Implement bulk editing
 - NEW: translations from transifex
 - FIX: overflow on bookmarks list
 - FIX: sorting
 - FIX: Fix humanized duration: Use stem language only

## v2.2.0

- NEW: Use routes history mode instead of hash URLs
- FIX: Sort folders alphabetically
- FIX: Allow canceling page fetches
- FIX: Import
- UI: Fixgrid view, descriptions in list view & bread crumbs

## v2.1.1

- FIX: Fix build script

## v2.1.0

- NEW: Rewrite UI
- NEW: Allow limiting the number of bookmarks per user
- NEW: Allow disabling web requests to bookmarked web pages

## v2.0.3

- NEW: Properly specify dependencies in app manifest (allows conditional support for nc 15 again)

Supported are NC 15 and 16, provided you are using PHP v7.1 and have gmp, intl and mbstring php extensions installed

## v2.0.2

- NEW: Drop support for nextcloud 15

## v2.0.1

- fix composer lock file

## v2.0.0

- NEW: gmp, intl, mbstring are now required
- NEW: Drop support for nextcloud 14 and php 7.0
- FIX: Switch URL normalizer to adhere strictly to WHATWG URL spec

## v1.1.2

- Revert breaking changes of v1.0.8

## v1.1.1

- FIX import from web UI

## v1.1.0

- NEW translations
- NEW: API endpoint to import into a specific folder

## v1.0.8

- NEW: gmp, intl, mbstring are now required
- NEW translations
- FIX: Switch URL normalizer to adhere strictly to WHATWG URL spec
- FIX: Update dependencies
- FIX: Run previews job in small batches instead of all at once

## v1.0.6

- FIX: Set timeout for submitting tags
- NEW: Create favicon
- FIX: Bump interactjs from 1.3.4 to 1.4.10
- FIX: UrlNormalizer: Don't enforce normalization
- NEW: updated from transifex
- FIX: Truncate tags in grid view
- FIX: Remove background colors
- FIX folder collapse css
- FIX: Speed up findBookmarks SQL query

[3.4.3]: https://github.com/nextcloud/bookmarks/compare/v3.4.2...v3.4.3
[3.4.2]: https://github.com/nextcloud/bookmarks/compare/v3.4.1...v3.4.2
[3.4.1]: https://github.com/nextcloud/bookmarks/compare/v3.4.0...v3.4.1
[3.4.0]: https://github.com/nextcloud/bookmarks/compare/v3.3.4...v3.4.0
[3.4.0-beta.1]: https://github.com/nextcloud/bookmarks/compare/v3.3.4...v3.4.0-beta.1
[3.3.3]: https://github.com/nextcloud/bookmarks/compare/v3.3.2...v3.3.3
[3.3.2]: https://github.com/nextcloud/bookmarks/compare/v3.3.1...v3.3.2
[3.3.1]: https://github.com/nextcloud/bookmarks/compare/v3.3.0...v3.3.1
[3.3.0]: https://github.com/nextcloud/bookmarks/compare/v3.2.5...v3.3.0
[3.2.5]: https://github.com/nextcloud/bookmarks/compare/v3.2.4...v3.2.5
[3.2.4]: https://github.com/nextcloud/bookmarks/compare/v3.2.2...v3.2.4
[3.2.2]: https://github.com/nextcloud/bookmarks/compare/v3.2.1...v3.2.2
[3.2.1]: https://github.com/nextcloud/bookmarks/compare/v3.2.0...v3.2.1
[3.2.0]: https://github.com/nextcloud/bookmarks/compare/v3.1.1...v3.2.0
[3.1.1]: https://github.com/nextcloud/bookmarks/compare/v3.1.0...v3.1.1
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
