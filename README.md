# Trongate Pages (v2)

A standalone, installable **pages** module for Trongate v2: database-backed
webpages with clean URLs, a publish/draft workflow, an admin management
screen, and an in-page **visual editor** (ported from the original Trongate
Pages feature that shipped in the v1 framework).

Designed for Trongate v2: everything is a module. Nothing in the framework
engine or in core modules is modified.

## What it provides

- A `pages` database table: `url_string`, `page_title`, meta fields, raw HTML
  `page_body`, publish flag, timestamps, author.
- **Homepage as a page**: the page whose `url_string` is `homepage` is served
  at the site root. In dev mode an empty homepage is seeded automatically.
- **Clean URLs**: `/about-us` renders the page whose slug is `about-us`.
  Unknown URLs fall through to a normal 404. Real module URLs are unaffected.
- **Publish/draft workflow**: unpublished pages return 404 to visitors.
- **Admin management**: list with search, pagination, per-page control,
  publish/draft toggle (MX, no reload), delete with confirmation. The
  homepage cannot be deleted.
- **Visual editor** (admin only): open any page in edit mode by appending
  `/edit` to its URL. Add headlines, text blocks, images, buttons, YouTube
  videos, dividers and raw HTML (code view with beautify). Media library with
  sub-folders and uploads. Page settings modal (title, URL string, meta,
  publish). Editor assets are only loaded in edit mode for authenticated
  admins - visitors download nothing extra.
- `[website]` placeholder: write portable links/image URLs in page content;
  they are expanded to the site URL when a visitor views the page.

## Requirements

- Trongate v2 (framework master, 2026 line)
- PHP 8.x (typed declarations used)
- MariaDB/MySQL

## Installation

1. Copy the module folder into your application:

   ```
   cp -r pages /path/to/app/modules/pages
   ```

   (The folder itself is the module: `Pages.php`, `Pages_model.php`,
   `pages.sql`, `css/`, `fonts/`, `js/`, `images/`, `views/`.)

2. Make the uploads folder writable by the web server:

   ```
   modules/pages/images/uploads/
   ```

3. Import `pages.sql` into your application database. In dev mode the module
   import wizard also picks it up automatically (first visit to any `pages`
   URL shows the wizard; choose **Run SQL** - it imports the schema and
   removes the file from your app copy).

4. Give the module two app-level config constants in `config/config.php`:

   ```php
   define('DEFAULT_MODULE', 'pages');            // site root = the homepage page
   define('ERROR_404', 'pages/attempt_display'); // unknown URLs try a page first
   ```

   - `DEFAULT_MODULE` only matters if you want the homepage managed as a page.
     If the site root is served by another module, leave it alone and just set
     `ERROR_404` - sub-pages still work.
   - `ERROR_404` is optional too: without it, pages are reachable at
     `pages/display/<slug>` and edit mode at `pages/display/<slug>/edit`.
     Set it to get clean URLs (`/<slug>`).

5. Log in to the admin panel and visit **pages/manage** (add a link to your
   admin nav if you like). The dev-mode auto-login also applies, so a fresh
   install is immediately usable.

## Usage

### Creating and editing pages

1. `pages/manage` → **Create New Webpage** → enter a title → Submit.
2. You are taken straight into the visual editor at `/<slug>/edit`.
3. Edit with the dock/toolbar, then click the save (tick) button. Use the
   settings gear for title, URL string, meta description and the
   publish/draft toggle.
4. Visit `/<slug>` to view the published page as a visitor. Unpublished pages
   show 404 to the public (with a friendly notice in dev mode).

Slugs are generated from the page title at creation and can be changed later
via the settings modal. Slugs must not collide with existing module names
(module URLs win) or with another page's slug; the editor explains the error.

### The editor's media library

The image manager stores uploads under `modules/pages/images/uploads/`.
Sub-folders are supported (create/rename/delete). Uploaded files are served
through the standard module asset trigger:

```
/pages_module/images/uploads/<file>
```

Files are validated server-side: image type, max 5 MB, max 4200x3200 px.
Names are slugified; duplicates get numeric suffixes.

### Homepage

The homepage is the page with `url_string = 'homepage'`. It is seeded
automatically in dev mode when missing, renders at `/`, is editable at
`/homepage/edit`, and is protected from deletion (any page row can be renamed
away, but deleting the homepage record is refused).

## Architecture notes

- One controller (`Pages.php`), one model, plain views. No service layers.
- Public methods: `index()` (homepage), `display()` (slug page),
  `attempt_display()` (404 interceptor entry). Admin/editor endpoints are
  gated with `trongate_security->make_sure_allowed()`.
- The editor's XHR endpoints verify a CSRF token sent in the
  `trongateToken` header against the session (`hash_equals`). Regular admin
  forms use the standard `csrf_token` field.
- Editor saves are **partial updates**: a quick save sends only `page_body`;
  the settings modal sends the full field set. Only provided fields change.
- The JSON endpoints return raw `json_encode` output (the framework `json()`
  helper wraps output in `<pre>` for display and is not suitable for
  JavaScript `JSON.parse`).
- The visual editor JS is the v1 editor, ported with surgical URL/endpoint
  changes only (see `docs/history.md`). Modal helpers that v1 relied on from
  app-level `app.js` are bundled into the module's bootstrap view so the
  module is self-contained.
- The module depends only on core Trongate v2 modules (`trongate_security`,
  `trongate_tokens`, `validation`, `pagination`, `templates`, `form`,
  `flashdata`) - all of which ship with the framework.

## Migrating from the v1 Trongate Pages table

The v1 table (`trongate_pages`) maps cleanly:

```sql
INSERT INTO pages (id, url_string, page_title, meta_keywords, meta_description,
                   page_body, date_created, last_updated, published, created_by)
SELECT id, url_string, page_title, meta_keywords, meta_description,
       page_body, date_created, last_updated, published, created_by
FROM trongate_pages;
```

Notes:

- Column-for-column compatible; the v1 schema was genuinely sound.
- `created_by` carries over only if the target app uses the same admin user
  ids.
- In v1 the homepage was the row with `id = 1`; in v2 the homepage is the row
  with `url_string = 'homepage'`. If your v1 homepage row used a different
  `url_string`, update it to `homepage` (or rename a fresh homepage row).
- v1 visual-editor content is plain HTML and renders unchanged.

## Documentation

- `docs/history.md` - reverse-engineering record of the v1 feature.
- `docs/design.md` - design decisions for the v2 module.
