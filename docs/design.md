# Trongate Pages v2 — Design Record

Design decisions for the standalone v2 module, made after the reverse
engineering documented in `history.md`. Approved by Simon (DaFa) on
2026-09-07 via four decisions: port the v1 visual editor close to the
original; fully standalone module (own `index()`, config.php + custom routes
allowed, no engine/core-module/welcome changes); module `pages`, table
`pages`; the module developed in its own repository.

## Positioning

`pages` is an installable, self-contained Trongate v2 module: DB-backed
webpages with clean URLs, publish/draft workflow, an admin manage screen, and
an in-page visual editor ported from v1. Everything is a module; the module
touches nothing in the framework core or in other core modules.

## Module layout (module root == repo root)

```
trongate-pages/
├── Pages.php              controller (module root, v2 convention)
├── Pages_model.php        DB access
├── pages.sql              schema (auto-run by v2 module import wizard in dev)
├── css/                   editor CSS (ported) + manage screen styles
├── js/                    editor managers (ported) + module JS
├── images/                editor/media icons + images/uploads (writable)
├── views/
│   ├── display.php            front-end page render
│   ├── enable_page_edit.php   editor bootstrap (edit mode only)
│   ├── manage.php             admin list + create modal
│   ├── not_published_info.php dev-only notice on draft pages
│   ├── permissions_error.php  uploads dir not writable
│   └── default_homepage_content.php  dev seed content
├── docs/history.md        v1 reverse-engineering record
├── docs/design.md         this file
└── README.md              install + usage
```

## Database (table `pages`)

Retained from v1 because it is genuinely sensible; renamed table to match the
module (v2 naming), columns kept for behavioural compatibility:

```sql
CREATE TABLE pages (
    id int NOT NULL AUTO_INCREMENT,
    url_string varchar(255) NOT NULL,
    page_title varchar(255) NOT NULL,
    meta_keywords text,
    meta_description text,
    page_body text,
    date_created int NOT NULL,
    last_updated int NOT NULL DEFAULT 0,
    published tinyint(1) NOT NULL DEFAULT 0,
    created_by int NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY url_string (url_string)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Changes from v1: NOT NULL where the app always writes values, utf8mb4,
`KEY url_string`. No foreign keys (created_by references the app's admin
table, which the module does not own). Uniqueness of `url_string` stays
code-enforced as in v1 (a UNIQUE key would turn slug collisions into hard
errors rather than the friendly suffix behaviour). v1 → v2 migration is a
simple `INSERT INTO pages (...) SELECT ...` plus slug/author column handling;
documented in README, not shipped as a wizard.

Homepage decoupled from id 1: the homepage is the row with
`url_string = 'homepage'`. `Pages::index()` (the `/` route when
`DEFAULT_MODULE = 'pages'`) seeds that row in dev mode if it is missing, and
renders it. Homepage deletion is refused by slug check, not id check.

## Serving pages (URL strategy)

Two app-level config lines in `config/config.php` (documented in README;
nothing else changes in the app):

- `DEFAULT_MODULE` = `'pages'`  → `/` is served by `Pages::index()`
  (homepage record).
- `ERROR_404` = `'pages/attempt_display'` → any URL that does not resolve to a
  real module/controller falls through to the pages module with the original
  URL segments intact. Real module URLs are unaffected because module
  resolution happens first.

`attempt_display()` behaviour for `/some-slug`:
- `some-slug` resolves to a published page → `http_response_code(200)` (the
  dispatcher pre-set 404) and render the `public` template.
- No page → keep the 404 code and render the standard error page via the
  `templates` module (no recursion: that method renders a view directly).
- Last segment is `edit` and the visitor is an authenticated admin → render
  the page with the editor bootstrapped. `/edit` without an admin session
  redirects to the admin login screen (fixed v1's prod-mode quirk).

Slugs stay flat and single-segment (v1 behaviour, documented limitation).

## Administration

- Entry point `pages/manage` (admin session required on every admin method via
  `$this->trongate_security->make_sure_allowed()`).
- List: title, URL, author, created/updated, published state, edit action.
  Search + pagination via the v2 pagination module. Light MX niceties: the
  publish/draft toggle posts via MX and swaps the status badge in place
  (no full page reload).
- Create asks for the page title on its own admin screen (v1 used a modal;
  a dedicated screen needs no modal JavaScript). `submit()` validates title
  uniqueness + module-name conflict, inserts an unpublished page, redirects to
  the edit URL of the new page.
- Delete uses a confirmation screen (`delete_conf`) then `submit_delete`,
  mirroring the standard generated v2 CRUD flow; the homepage slug is guarded.
- Editor save hits plain controller endpoints (Standard_endpoints is gone).

## Endpoints (all editor endpoints admin-gated)

| Endpoint | Purpose | Notes |
|---|---|---|
| `pages/display` / `pages/attempt_display` | public render | display() used by index() too |
| `pages/index` | homepage (DEFAULT_MODULE) | |
| `pages/manage` | admin list | |
| `pages/submit` | create page (title) | form + CSRF via validation |
| `pages/update_page/{id}` | editor save (partial: body, title, slug, meta, published) | JSON body + `trongateToken` CSRF header |
| `pages/submit_delete` | delete page (homepage-guarded) | |
| `pages/submit_image_upload/{id}` | media upload | JSON response |
| `pages/submit_delete_image` | media delete | |
| `pages/submit_create_new_img_folder` | media folder create | |
| `pages/submit_rename_img_folder` | media folder rename | |
| `pages/submit_delete_folder` | media folder delete | |
| `pages/fetch_uploaded_images` | media library listing | |
| `pages/submit_beautify` | code view beautify | |
| `pages/check_youtube_video_id` | YouTube URL validation | |
| `pages/replace_website_shortcode` / `restore_website_shortcode` | `[website]` handling | |

Internal helpers are `private`/`protected` or `public` with `block_url()` when
another module may call them (v2 three-tier method protection). CSRF for the
JSON endpoints: the editor is only ever bootstrapped inside an admin session,
token injected into the page by the controller and echoed back by the editor
in a `X-CSRF-Token` header (wire name `trongateToken`, kept from v1),
verified server-side with `hash_equals` against the session token (same
convention as `validation->run()`). Editor saves are partial updates: only the
fields present in the JSON body are written (a quick save sends only
`page_body`; the settings modal sends the full set). Endpoints that return
JSON for the editor use raw `json_encode` output; the framework `json()`
helper wraps output in `<pre>` for human display and cannot be parsed by the
editor's `JSON.parse`. Image uploads are validated server-side
(type/size/dimensions as v1), names sanitised, `move_uploaded_file` only,
destination constrained to `modules/pages/images/uploads/`.

## Visual editor port (v1 → v2)

Ported "as close to the original as possible" per decision, with surgical
adaptation, not a rewrite:

1. Copy `trongate_pages_editor.css` → `css/`, all manager JS → `js/`, media
   icons → `images/`.
2. URL plumbing renames: module name `trongate_pages` → `pages` in URLs and
   asset trigger paths (`pages_module/…`); the editor config object is built
   by the view, so most JS needs no change.
3. Endpoint swap: the single generic `api/update/trongate_pages/{id}`
   (Standard_endpoints) call becomes `pages/submit_body`; `api_auth()`
   calls removed (session admin auth instead).
4. Response contracts kept identical so manager JS logic is untouched where
   possible.
5. Dead/incidental v1 pieces dropped: API explorer link, per-instance extra
   CSS/JS directory, comments coupling, admin theme system.
6. Font Awesome: the module keeps FA classes in the editor chrome as v1 did,
   but the FA stylesheet is loaded by the module only in edit mode (module
   ships/loads it locally in `css/`, no CDN dependency). Page *content* that
   uses FA icons is the site owner's choice; README documents how to load FA
   site-wide if desired.

Editor behaviour preserved: element types (headline h1–h5, text, image,
button, YouTube, divider, code), node selection/DOM manipulation, media
library with sub-folders, raw code view + beautify, `[website]` shortcode,
save-with-confirmation modal. Editor assets are only fetched in edit mode for
authenticated admins; public visitors download nothing extra.

## Deliberately omitted (and why)

- `Standard_endpoints` / `api.json` / API explorer — dead framework machinery.
- Slug editing UI — v1 never had it; keep scope tight (README documents that
  renaming a page title is separate from slug; slugs are created on insert).
  *(Revisit only if Simon asks.)*
- Page nesting / parent-child pages — framework URLs are flat-segment based;
  v1 was flat; nested pages add a tree UI for little gain here.
- Per-page custom CSS/JS directory (`trongate_pages_extra`) — theming belongs
  to the app/template layer, and the editor already supports arbitrary HTML.
- Comments integration — separate concern; comments module can hook the
  page body or template independently.
- Multi-language page variants — out of scope for a "pages" store.
- Any third-party packages, JS frameworks, or CSS frameworks.

## Migration from v1

Trivial and worth supporting (documented in README as optional SQL):
`trongate_pages` → `pages` is a straight column-for-column copy (id, url_string,
page_title, meta_keywords, meta_description, page_body, date_created,
last_updated, published, created_by). `created_by` ids carry over only if the
target app uses the same admin user ids. No schema gymnastics required; the v1
schema was genuinely sound.

## Design principles applied

Simple, clean, predictable, lightweight, maintainable, native to Trongate v2.
One module, not a module family — the page store, renderer and admin UI share
one lifecycle and one table; splitting them would add ceremony without
benefit. Minimal abstraction: controller + model + views, no service layers,
no config beyond two documented app constants. Pure PHP + v2 modules + the
ported vanilla editor JS. Everything is a module; nothing touches the engine.
