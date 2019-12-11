## v2.3.3

- FIX: Create bookmarks in the current folder

## v2.3.2

- FIX webpack build
- FIX: Create bookmarks in the current folder
- FIX: Make folder icons clickable
- FIX translations
- NEW: translations

## v2.3.1

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
