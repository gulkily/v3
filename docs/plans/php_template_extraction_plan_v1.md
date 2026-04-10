# PHP Template Extraction Plan V1

This document describes how to move HTML out of application logic code while keeping the generated HTML easy to inspect in browser "view source".

## Progress

- Branch: `feature/template-extraction`
- Status: in progress
- Slice 1: completed
- Slice 2: completed
- Slice 3: completed
- Slice 4: pending
- Verification: `php tests/run.php` passed after Slice 3

## Goals

- Keep route and application logic separate from HTML templates.
- Make page HTML easy to read in source form.
- Preserve the current PHP-first runtime model.
- Avoid introducing a heavy framework or opaque templating layer.

## Non-Goals

- Do not change route ownership or response semantics.
- Do not redesign the visual system as part of this refactor.
- Do not introduce client-side rendering for page content.
- Do not minify server-rendered HTML.

## Desired End State

The application should follow a simple rendering pipeline:

1. route/application code loads data
2. route/application code selects a template
3. route/application code passes a small view-model array to a renderer
4. a shared layout template wraps the page template
5. reusable fragments live in partial templates

`Application.php` should stop assembling large HTML strings inline.

## Template Structure

Recommended layout:

```text
templates/
  layout.php
  partials/
    nav.php
    feedback.php
    post_card.php
  pages/
    board.php
    thread.php
    post.php
    profile.php
    compose_thread.php
    compose_reply.php
    account_key.php
    instance.php
    activity.php
```

## Renderer Design

Add a minimal renderer class, for example:

- `src/ForumRewrite/View/TemplateRenderer.php`

Responsibilities:

- load page templates from `templates/pages/`
- load the shared layout template
- expose a small set of helpers such as HTML escaping
- keep rendering deterministic and simple

Suggested shape:

```php
$renderer->renderPage('thread.php', [
    'title' => $title,
    'active_section' => 'board',
    'script_paths' => ['/assets/openpgp.min.js', '/assets/browser_signing.js'],
    'thread' => $thread,
    'posts' => $posts,
]);
```

The renderer should use plain PHP templates and output buffering, not string concatenation inside route methods.

## Template Rules

- Templates should be plain PHP plus readable HTML.
- Templates should receive prepared data, not query the database directly.
- Templates should use an escaping helper for all untrusted values.
- Templates should avoid hidden logic beyond simple conditionals and loops.
- Shared fragments should move into partials instead of repeating markup.

## View Source Readability Rules

To keep browser "view source" readable:

- do not minify HTML
- preserve indentation and line breaks
- prefer one structural element per line where practical
- keep scripts and styles external
- keep semantic wrappers and stable class names
- avoid giant concatenated strings and inline HTML fragments inside PHP logic

The generated HTML should closely resemble the template file structure.

## Migration Strategy

Use incremental extraction rather than a one-shot rewrite.

### Slice 1

- add `TemplateRenderer`
- add `templates/layout.php`
- migrate board page
- migrate thread page
- status: completed

This proves the rendering model and extracts the shared shell.

### Slice 2

- extract post page
- extract instance page
- extract activity page
- extract shared nav and post-card partials
- status: completed

### Slice 3

- extract profile page
- extract account-key page
- extract compose thread/reply pages
- extract feedback partial
- status: completed

### Slice 4

- remove obsolete inline HTML builders from `Application.php`
- tighten helper boundaries
- update tests if they rely on exact inline formatting
- status: pending

## Application Responsibilities After Refactor

`Application.php` should remain responsible for:

- routing
- request parsing
- calling write services
- querying read-model data
- selecting templates
- choosing status codes and redirects

Templates should be responsible only for presentation.

## Risks

- accidental behavior drift while moving HTML out of string builders
- duplicated escaping if helper boundaries are unclear
- too much logic leaking into templates

These risks are controlled by migrating route-by-route and keeping the renderer minimal.

## Testing Expectations

After each migration slice:

- existing smoke tests should still pass
- generated page HTML should still contain the same important route markers and assets
- manual browser "view source" inspection should confirm readable indentation and structure

## Recommendation

Start with the shared layout plus the board/thread pages first. They cover the common shell and repeated content patterns without the extra complexity of compose/account flows.

## Progress Notes

- Slice 1 completed:
  - added `TemplateRenderer`
  - moved shared shell/layout into `templates/layout.php`
  - moved board page into `templates/pages/board.php`
  - moved thread page into `templates/pages/thread.php`
  - kept non-migrated routes on the same shared layout while leaving their page markup in `Application.php`
- Slice 2 completed:
  - extracted `templates/partials/nav.php`
  - extracted `templates/partials/post_card.php`
  - moved post page into `templates/pages/post.php`
  - moved instance page into `templates/pages/instance.php`
  - moved activity page into `templates/pages/activity.php`
- Slice 3 completed:
  - extracted `templates/partials/feedback.php`
  - moved profile page into `templates/pages/profile.php`
  - moved account-key page into `templates/pages/account_key.php`
  - moved compose thread/reply pages into `templates/pages/compose_thread.php` and `templates/pages/compose_reply.php`
