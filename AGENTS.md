# AGENTS.md

Notes for AI coding agents working in this repo. Keep changes minimal, prefer editing existing files over creating new ones, and don't create planning/summary markdown documents unless asked.

## What this is

Nextcloud Bookmarks — a server-side Nextcloud app (PHP 8.1+) with a Vue 2 / Vuex frontend. Installs into a Nextcloud instance as `apps/bookmarks`. Published on the Nextcloud App Store; primary repo is `nextcloud/bookmarks` on GitHub.

App ID: `bookmarks`. Namespace: `OCA\Bookmarks\`. Version is tracked in `appinfo/info.xml`, `package.json`, and `Makefile` — keep them in sync when bumping.

## Layout

- `lib/` — PHP backend (PSR-4: `OCA\Bookmarks\` → `lib/`)
  - `Controller/` — HTTP controllers; public APIs in `BookmarkController`, `FoldersController`, `TagsController`; the `Internal*` variants are for the Vue frontend
  - `Db/` — Entities and `QBMapper`s. `TreeMapper.php` is the heart of the data model (see below)
  - `Service/` — Business logic. `FolderService`, `BookmarkService`, `Authorizer`, `TreeCacheManager` are the most-touched
  - `Migration/` — Schema and repair steps
  - `BackgroundJobs/`, `Activity/`, `Search/`, `Dashboard/`, `Reference/`, `ContextChat/`, `Flow/`, `Hooks/` — Nextcloud integration points
- `src/` — Vue frontend (entry `main.js`, store under `store/`, router in `router.js`)
- `tests/` — PHPUnit tests (integration tests against a real Nextcloud + DB; see Testing)
- `templates/` — Server-rendered shell templates
- `docs/` — Sphinx docs published to docs.nextcloud.com (`*.rst`)
- `l10n/` — Auto-generated translations from Transifex; do not hand-edit
- `appinfo/` — Nextcloud app manifest, routes, DI wiring
- `js/` — Webpack output; do not edit, regenerate with `npm run build`

## Data model — read before touching `Db/` or sharing logic

Three tables drive everything:

- `bookmarks_folders` (Folder), `bookmarks` (Bookmark), `bookmarks_shared_folders` (SharedFolder — a sharee's view of a shared folder)
- `bookmarks_tree` — the polymorphic tree. One row per placement: `(id, type, parent_folder, index, soft_deleted_at)`. `type` is `folder`, `bookmark`, or `share`. For `share` rows, `id` is the SharedFolder's id, not the Share's; the same SharedFolder also exists as a row in `bookmarks_shared_folders`.
- `bookmarks_shares` (Share, the grant) joined to SharedFolders via `bookmarks_shared_to_shares`

`soft_deleted_at` on `bookmarks_tree` is the trash bin. Soft delete cascades to all descendant tree rows (folders, bookmarks, shares). When a sharer trashes a folder, the sharee must not see it anywhere — including their own trash — because they can't restore someone else's folder. The filter for that lives at read time, not write time: see `TreeMapper::joinOriginalFolderNotSoftDeleted` and the share-recursive arm of `BookmarkMapper::_generateCTE`.

`BookmarkMapper::_generateCTE` is a recursive CTE that walks a user's tree. It has two backend shapes:
- MySQL: one CTE with three union arms
- Postgres / sqlite: three nested CTEs (`inner_folder_tree` → `second_folder_tree` → `folder_tree`)

Both backends reuse the same `$recursiveCase` / `$recursiveCaseShares` builders, so a filter added there applies to both. New positional parameters added to those builders are picked up automatically by the `array_merge(...->getParameters())` lines below.

## Build, lint, test

```
make dev-setup           # composer install + npm ci
npm run dev              # build frontend (development)
npm run build            # build frontend (production)
npm run watch            # rebuild on change
npm run lint[:fix]       # ESLint over src/
npm run stylelint[:fix]  # Stylelint over src/

composer run lint        # php -l over lib/
composer run cs:check    # php-cs-fixer dry run
composer run cs:fix      # php-cs-fixer apply
composer run psalm       # static analysis (baseline: psalm-baseline.xml)
composer run test:unit   # phpunit -c tests/phpunit.xml
```

PHP target: 8.1 (platform pinned in `composer.json`). Node: 24.x, npm: 11.x.

## Testing — the gotcha

`tests/bootstrap.php` requires `../../../lib/base.php` and loads the bookmarks app from a Nextcloud server install. The suite does NOT run from a standalone clone — it expects this repo to live at `<nextcloud>/apps/bookmarks` with a configured DB. `before_install.sh` shows the CI setup (clones nextcloud/server, copies the app in, sets up mysql / pgsql / oracle).

If you can't run the suite, say so explicitly rather than claiming it passes. `php -l` and `composer run psalm` work standalone and catch a lot.

## Conventions

- Commits follow conventional-commit style with a scope, e.g. `fix(Activity/Provider): ...`, `feat(FolderService): ...`. Tag commits are `v16.2.1` shape.
- The `master` branch is default; PRs target `main`. Don't push to either without being asked.
- Don't hand-edit `l10n/*` — Transifex generates those (`fix(l10n): Update translations from Transifex` commits).
- Don't edit `js/` — it's the webpack build output.
- Authorization for HTTP endpoints goes through `Service\Authorizer`; permission checks live there, not scattered through controllers.
- Tree mutation must go through `TreeMapper` so `TreeCacheManager` invalidations and the soft-delete cascade stay consistent. Don't write directly to `bookmarks_tree` from controllers/services.
- Soft-delete vs hard-delete: `softDeleteEntry` / `softUndeleteEntry` for trash flow, `deleteEntry` / `deleteShare` for permanent removal. `removeFolderTangibles` cleans up shares + public folders on hard delete.
- The bookmarks app is maintained in free time by a single primary maintainer (see info.xml). Keep PRs focused; one logical change per PR.

## When extending shared-folder behaviour

Most sharing bugs come from one of two oversights:
1. Writing logic that only touches the sharer's tree, forgetting the sharee's SharedFolder tree row(s) — or vice-versa.
2. Mutating tree state on the write path (soft-delete cascades) when the same condition can be expressed once as a read-time filter, which avoids clobbering independent state the other side may have set.

Prefer read-time filters when the source of truth is unambiguous (e.g. the original folder's `soft_deleted_at` is authoritative for "does this share even exist right now").