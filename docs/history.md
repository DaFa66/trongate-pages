# Trongate Pages — Historical Record (v1 reverse-engineering)

This document is the technical record of the original Trongate Pages feature
from Trongate v1, produced during reverse-engineering (September 2026).
It is intentionally a history document: what existed, how it worked, and what
that means for the v2 module. It is not the design document — see `design.md`.

## Where the old implementation was found

1. **Git history of `trongate/trongate-framework`** (local clone at
   `C:\phpup\www\trongate-framework`, full history including `master`/`v1`/`v2`
   branches and tags back to v1.2.x). The framework repository itself was the
   application skeleton in the v1 era, and `trongate_pages` lived at
   `modules/trongate_pages/` inside it.
2. **The `b3` test application** (`C:\phpup\www\b3`) — itself a git checkout of
   the framework at commit `4749af0` (`v1.3.3049-75-g4749af0`, May 2024 era),
   containing a working copy of the module. Used as a live reference and
   functional test environment (admin `admin`/`admin`, MariaDB database `b3`).

Neither source alone was treated as authoritative. b3's copy was compared
against the final historical version in git (see below).

## Which historical version is the most complete

The module was added to the framework on 2023-07-18 (`f7279b4`, "*** Added
Trongate_pages module to the framework!!! ***") and deleted outright on
2025-08-20 in commit `8a1d04d` — the v1.4 release ("This release represents a
significant update with potentially breaking changes. Although this could be
considered Trongate v2, it is being released as v1.4."). That commit stripped
`trongate_pages`, the `Standard_endpoints` engine class, `Api.php`, the API
explorer views, and related engine machinery as part of the modularisation
that became v2.

**The most complete version is the tree at `8a1d04d~1`** (parent of the removal
commit). It contains 36 commits of evolution across the module's ~2 year life.
b3 (`4749af0`) sits five commits before removal and differs only in small,
framework-wide cleanups that landed between b3's snapshot and the purge:

- `bfe5032` (2024-06-17): access-modifier cleanup — helpers renamed without the
  underscore prefix (`_make_url_str` → `make_url_str`, etc.) and made
  explicitly `public`; some unused methods removed.
- `d063985` (2024-07-09): added the missing `set_per_page()` method.
- `e4f7410` (2024-08-07): framework-wide form-helper refactor.
- `6f88563` (2025-03-06): `validation_helper()` → `validation()` (framework-wide).
- `028a27f` (2025-05-01): corrected `website_url` property to `webpage_url`.

Functionally, b3 is equivalent to the final version; the semantic diff is
cosmetic. All structural analysis below is valid for both.

## Evolution timeline (commit dates)

| Date | Commit | Event |
|---|---|---|
| 2023-07-18 | `f7279b4` | Added to framework (with dynamic_nav link, permissions-error view, initial JS) |
| 2023-07 → 2023-12 | ~20 commits | Visual editor build-out: dock manager, element adder, toolbar, image/text/headline/button/youtube/divider/code managers, folder explorer, modals, CSS (licence era 1.3.4047) |
| 2023-09-08 | `086f5d4` | File explorer for the media library |
| 2023-10-10 | `c9a596a` | Optional per-instance extra CSS/JS via `public/trongate_pages_extra` |
| 2023-10-18 | `83a6982` | **`INTERCEPT_404` constant introduced to the framework** so 404s try a Trongate Pages record first |
| 2024-05-17/20 | `41d6f68`…`a9fd18e` | `attempt_display()` simplification; **homepage reads content from the database** (record id 1); `default_limit` fixes |
| 2024-06-17 | `bfe5032` | Access-modifier cleanup |
| 2024-07-09 | `d063985` | `set_per_page()` added |
| 2025-08-20 | `8a1d04d` | **Deleted** in the v1.4 modularisation purge. Never ported, never split into a separate repo |

Framework dependencies that died with it in the same purge: `INTERCEPT_404`
(engine constant + dispatch behaviour), `Standard_endpoints` +
per-module `assets/api.json` endpoint descriptors, the `api_auth()` helper,
and the API explorer.

## How the original implementation worked

### Database

Single table `trongate_pages`:

| Column | Type | Notes |
|---|---|---|
| id | int(11) PK AI | record 1 is special-cased as the homepage |
| url_string | varchar(255) | slug, one flat URL segment |
| page_title | varchar(255) | |
| meta_keywords | text | mostly unused in the UI |
| meta_description | text | |
| page_body | text | raw HTML (visual editor output) |
| date_created | int(11) | Unix timestamp |
| last_updated | int(11) | Unix timestamp; 0 = never |
| published | tinyint(1) | 0 = draft (publicly 404) |
| created_by | int(11) | admin id |

Related: `trongate_administrators` (author names), `trongate_tokens` (auth),
`trongate_comments` (deleted alongside pages in `submit_delete`).

### Front-end rendering

- **Homepage**: `DEFAULT_MODULE = 'welcome'`; `Welcome::index()` delegated to
  `$this->trongate_pages->display()`. `display()` detected
  `current_url() === BASE_URL`, seeded a homepage record in dev mode if the
  table was empty (id 1, url_string `homepage`, body from the
  `default_homepage_content` view, `published = 1`, `last_updated = 0`), and
  rendered record 1.
- **Other pages**: one URL segment per page (`/about-us`). The URL never hit a
  real module, so the engine's 404 path consulted `INTERCEPT_404`
  (`trongate_pages/attempt_display`), preserved the original URL segments, and
  `attempt_display()` simply called `display()`. `display()` read the last URL
  segment, fetched the row by `url_string`, and rendered the `public` template
  with view file `display.php`:
  `flashdata()`, `validation_errors()`, `$page_body` inside
  `<div class="page-content">`, then
  `Modules::run('trongate_pages/_attempt_enable_page_edit', $data)`.
- **Drafts**: `published = 0` → real 404 publicly; in dev mode an extra
  "not published" notice was injected after the 404 content.
- **Placeholders**: `[website]` inside body/URLs was replaced with `BASE_URL`
  at render time (visitor view), left raw while editing so the editor kept
  working regardless of domain.

### Slugs

Created from the page title via `url_title()` on page creation (title-only
form). Uniqueness was code-enforced: collision appends "search friendly"
suffix words (`-information`, `-advice`, …) then falls back to a random
string. A new page title could not collide with an existing module folder name
(`title_check` validation callback) because module URLs win over page URLs.
Slugs were flat (no nesting) and could not be edited after creation in the
admin list; changing title slug is what `url_string` was keyed on.

### Administration

- Login: `trongate_administrators` module (admin/admin in b3).
- `manage()`: admin list of pages (search, pagination with per-page options,
  author, created/updated dates, published indicator, edit links). "Create New
  Webpage" modal asked only for a page title → `submit()` validated the title
  (`callback_title_check` for uniqueness + module-name conflict), inserted an
  **unpublished** record with an empty-ish body (`<h1>Title</h1>`), then
  redirected to `trongate_pages/display/<url_string>/edit` to open the editor.
- `submit_delete()`: guarded homepage (id 1) — deletion of the homepage record
  was refused with an error. Also deleted related comments.
- **Edit mode**: appending `/edit` to any page URL switched the *public* page
  into an editable state. `display()` looked for a valid admin token when the
  last segment was `edit`; if valid, the page rendered normally and
  `enable_page_edit.php` booted the visual editor on top of the live content.
  Invalid token + dev mode redirected to the admin manage screen.
- Saving happened from the editor: PUT to
  `api/update/trongate_pages/{id}` (generic `Standard_endpoints` endpoint
  declared in `assets/api.json` with `beforeHook: _pre_update`, which stamped
  `last_updated`). The editor also declared image/folder APIs in `api.json`
  (upload, delete image, create/rename/delete folders, fetch uploads, beautify
  HTML source, YouTube ID check), all protected by `api_auth()` which matched
  the current URL against the descriptor and required an admin token.
- Image uploads landed in
  `modules/trongate_pages/assets/images/uploads/` (subfolders supported) and
  were served back through the module asset trigger
  (`trongate_pages_module/...`), with `index.php` placeholders to stop
  directory listing. A permissions-error screen appeared if the folder was not
  writable.

### The visual editor (the bulk of the old code)

~6,000 lines of vanilla JavaScript across 13 files + ~1,000 lines of CSS:

`trongate_pages.js` (core bootstrap + element DOM operations),
`toolbar_manager.js` (top toolbar), `dock_manager.js` (right-side element
dock), `element_adder_manager.js` (add/duplicate/delete elements),
`headline_manager.js`, `text_manager.js`, `image_manager.js`,
`button_manager.js`, `youtube_manager.js`, `divider_manager.js`,
`code_view.js` (raw HTML editing + beautify), `folder_manager.js` (media
sub-folders), `camera_manager.js`.

Elements: headline (h1–h5), text, image, button, YouTube embed, divider, code
block. The page area was treated as one big contenteditable-ish document where
the user selected nodes and the managers mutated the DOM; modals handled image
picking/uploading, link insertion and YouTube URLs. It loaded scripts
asynchronously only in edit mode and only for authenticated admins, so normal
visitors never downloaded any editor code. No framework JS was used.

### Admin manage UI dependencies (v1 incidental)

Font Awesome 4.7 loaded from CDN by the app's public/admin templates (not by
the module); editor and admin screens used `fa-` icons throughout. The editor's
default content used FA icon markup too. The v1 admin panel used a
`themes/default_admin/{colour}` theme system with a `dynamic_nav` partial that
contained a hard-coded "Manage Articles" link to `trongate_pages/manage`.

## Essential vs incidental (assessment)

Essential (the feature):
- DB-backed pages: url_string, title, HTML body, publish state, timestamps, author.
- Clean-URL retrieval via 404 interception with original segments preserved.
- Homepage as a page record (dev-seeded).
- Publish gating (unpublished = 404), homepage deletion protection.
- Slug generation + uniqueness (+ module-name conflict avoidance).
- Admin list/create/edit/delete workflow.
- The visual drag-and-drop editor and media manager (kept per product decision,
  see `design.md` — it was the module's defining differentiator).
- `[website]` shortcode portability.

Incidental / correctly discarded:
- Generic `Standard_endpoints` API layer + `api.json` descriptors + `api_auth()`
  (removed from the framework; v2 endpoints are plain controller methods).
- API explorer dev link.
- `trongate_comments` coupling on delete.
- Per-instance extra CSS/JS directory (`trongate_pages_extra`) — app-level
  theming concern, not page storage.
- Font Awesome dependency of the old templates (decision in `design.md`).
- "Manage Articles" label and admin theme colour system.
- The odd prod-mode behaviour where `/slug/edit` with no valid token still
  enabled the editor chrome (auth was only enforced at save time).

## Framework dependencies of the old module (and v2 equivalents)

| v1 dependency | What it did | v2 status |
|---|---|---|
| `INTERCEPT_404` config + engine dispatch | route unknown URLs to module | Renamed `ERROR_404` config in engine/Core (default `templates/error_404`); same interception concept, original segments preserved |
| `MODULE_ASSETS_TRIGGER` (`_module`) | served module assets (`trongate_pages_module/…`) | Still present; allow-list dirs: css, js, images, uploads, etc. |
| `Standard_endpoints` + `assets/api.json` | generic CRUD/API descriptors + auth rules | Removed; v2 uses direct public controller methods + `json()` |
| `api_auth()` helper | URL-to-endpoint auth matching | Removed; replaced by `trongate_security->make_sure_allowed()` (session) |
| Engine `model` magic (`$this->model`) | DB access | Replaced by v2 `Db` module; modules use their own `*_model` |
| `validation_helper` / engine `Validation` | rules + CSRF gate | v2 `validation` module; CSRF token auto-injected by `form_open`, verified by `validation->run()` |
| `template('public'/'admin')`, themes, `dynamic_nav` | layout | v2 `templates` module: `templates->public($data)` / `templates->admin($data)` |
| `Pagination` engine class | list pagination | v2 `pagination` module |
| `trongate_tokens` (admin bearer tokens) | auth on public pages | v2 `trongate_tokens` module still exists; session-based admin auth is the norm for admin screens |

## Open historical quirks (noted, not preserved)

- Homepage was hard-wired to **record id 1** with a magic `last_updated = 0`
  "invite first-time admin to clear the homepage" flag. The v2 design decouples
  the homepage from a specific id (see `design.md`).
- Slugs were immutable after creation (title change did not re-slug).
- Page URLs were single flat segments only; no nesting, no slug editing UI.
- The `edit` URL suffix was the only editor entry point; the admin "Edit"
  button navigated to the public page in edit mode.
