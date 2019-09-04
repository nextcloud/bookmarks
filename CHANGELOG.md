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
