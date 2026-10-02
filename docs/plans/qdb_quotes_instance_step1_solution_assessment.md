# QDB Quotes Instance — Step 1: Solution Assessment

## Problem

We need a new site instance whose board/index page looks like the
classic QDB (a flat numbered list of full quote text) while clicking a
quote leads into a normal discussion thread, without forking the
existing board/thread rendering pipeline.

## Context found

- A proven "new instance" pattern already exists: `SiteProfileRegistry`
  (site name/theme/composer copy per `FORUM_SITE_ID`) plus per-vhost
  `FORUM_REPOSITORY_ROOT` / `FORUM_DATABASE_PATH` / `FORUM_STATIC_HTML_ROOT`
  — this is exactly how the `chouse` instance (`state/local_repository_chouse`,
  `state/static_html_chouse`) was stood up.
- The discussion-thread requirement is already solved: `templates/pages/thread.php`
  renders a root post plus nested replies for any `/threads/<id>`. No new
  work needed for "click a quote → see a discussion."
- The index/list requirement is not solved: `templates/pages/board.php` and
  `templates/partials/thread_card.php` render a forum-style card (subject
  as `<h2>`, meta date, labels, truncated preview, reply count) — QDB's
  list instead shows the *full* quote body under a bare `#ID`, with no
  subject/labels/preview truncation. This is a content-shape mismatch, not
  just a style mismatch.

## Options

### Option A — CSS-only theme on existing templates
- Add a `SiteProfileRegistry` entry + `ThemeRegistry` theme + one new
  `theme-qdb.css`; reuse `board.php`/`thread_card.php` unchanged.
- Pros: smallest change; follows the theme guide's "CSS-only illusion"
  principle; zero template/controller risk.
- Cons: can't actually get QDB's format — subject-as-title and
  truncated preview are baked into the markup, not styleable away; still
  shows per-thread subject composer flow, which QDB quotes don't have.

### Option B — New instance + new quote-list partial, reuse thread page (Recommended)
- Follow the `chouse` precedent for a new instance (profile + vhost env
  vars + theme CSS), plus one new partial (e.g. `quote_card.php`) swapped
  into the board template for this instance, rendering `#<id>` + full body,
  no subject/labels/preview truncation. Thread page (`thread.php`) is reused
  untouched as the click-through discussion view.
- Pros: reuses the proven instance-creation path and the entire
  thread/reply/discussion system as-is; new code is bounded to one partial
  + one theme + the board template's card choice; matches FDP's "reuse
  before forking markup" rule.
- Cons: still a template change (not pure CSS), so it touches
  `board.php`'s rendering choice, scoped by site profile.

### Option C — Standalone quotes micro-app (new routes/controller)
- New `/quotes`, `/quotes/<id>` routes and controller, independent of
  `board.php`/`tag.php`, querying the same read model directly.
- Pros: fully isolated from the shared board/tag pipeline; no risk of
  regressing zenmemes/chouse board views.
- Cons: duplicates routing/controller plumbing that Option B gets for free
  from the existing instance + partial mechanism; bigger surface area for
  a feature that fits the existing thread model well.

## Recommendation

**Option B.** It reuses the already-proven multi-instance mechanism (same
path that stood up `chouse`) and the existing thread/reply system for the
discussion side, keeping new work to one partial, one theme, and a
per-instance board-rendering choice — the smallest change that produces an
authentic QDB list without forking shared templates.
