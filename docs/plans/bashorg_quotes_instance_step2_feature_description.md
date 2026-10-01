# Bash.org-Style Quotes Instance — Step 2: Feature Description

## Problem

We want a new site instance whose index page recreates the nostalgic
look of bash.org's QDB (circa 2007) — numbered quotes, vote score,
monospace body, burnt-orange/white chrome, blended with small UX
refinements from a later interface (qdb.us, circa 2016) — but clicking
a quote leads into a live discussion thread instead of a dead end.

## Reference Material

- bash.org (2007 Wayback snapshot): base palette/chrome/markup shape —
  `#ID`, courier-new quote body, burnt-orange/white Arial chrome.
- qdb.us (2016 Wayback snapshot, the operator's own later build): voting
  UX refinements worth borrowing — labeled up/down actions, a
  score-vs-total-votes display, and no-reload voting — adopted as "small
  modifications" on top of the bash.org base look, not a wholesale
  second skin.

## User Stories

- As a former bash.org visitor, I want the quote list to look and read
  like the old QDB (colors, fonts, numbering, markup shape) so browsing
  feels genuinely nostalgic.
- As a visitor, I want to click a quote and land on a real discussion
  thread with replies, so the nostalgia experience adds something the
  original site never had.
- As a visitor, I want to upvote/downvote and flag a quote without a full
  page reload, the way qdb.us's refined voting worked, so the list feels
  responsive and reflects real community signal.
- As an operator, I want this delivered as its own site instance (own
  branding/content root) so the existing zenmemes/chouse boards are
  unaffected.
- As a developer, I want voting to reuse the existing reaction system
  rather than a new data model, so no database schema changes are needed.

## Core Requirements

- Stand up a new site instance via a new `SiteProfileRegistry` entry plus
  per-vhost `FORUM_SITE_ID`/`FORUM_REPOSITORY_ROOT`/`FORUM_DATABASE_PATH`/
  `FORUM_STATIC_HTML_ROOT` values, following the existing `chouse`
  precedent.
- Index page renders each thread root as a flat `#ID (score) quote body`
  entry matching the archived bash.org markup/CSS shape identified in
  Step 1: monospace quote body, burnt-orange/white Arial chrome, no
  subject title, no preview truncation, no labels.
- Voting/flagging controls borrow qdb.us's refinements — no full-page
  reload, a visible score — layered on the bash.org-styled list rather
  than replacing its look.
- Clicking a quote's `#ID` link navigates to the existing `/threads/<id>`
  discussion page, unmodified — replies render exactly as they do today.
- Upvote/downvote reuse the existing per-post reaction/tag system (already
  powering Like/Flag, scored through a generic tag-weight table) by adding
  new tag weights — no new record family, table, or migration.
- The flag action reuses the existing Flag reaction as-is.

## Shared Component Inventory

- `SiteProfileRegistry` / `ThemeRegistry` — reused and extended with one
  new profile entry and one new theme, same mechanism as `chouse`.
- `templates/pages/thread.php` and its reply/compose partials — reused
  unmodified as the discussion view; no new thread/reply rendering.
- The existing reaction/tag API (`apply-post-tag` action, Like/Flag today)
  and its generic score weighting — extended with upvote/downvote tag
  weights rather than replaced; no new write path.
- `templates/pages/board.php` / `templates/partials/thread_card.php` —
  **not** reused for this instance's index; Step 1 established the content
  shape (full body, no subject/preview/labels) doesn't fit the existing
  card, so a new list partial is needed, scoped to this instance only.

## Simple User Flow

1. Visitor opens the quotes instance's homepage and sees a flat list of
   `#ID` entries: net vote score, monospace quote body, burnt-orange
   chrome.
2. Visitor clicks a quote's `#ID`.
3. Visitor lands on that quote's existing discussion-thread page and can
   read or post replies normally.
4. From either the list or the thread view, the visitor can upvote,
   downvote, or flag the quote; the displayed score updates accordingly.

## Success Criteria

- The new instance is deployable through site-profile + vhost env vars
  alone, with zero changes to zenmemes/chouse rendering behavior.
- The index page's palette, fonts, and per-entry markup shape match the
  archived bash.org reference closely enough that a former visitor
  recognizes it at a glance, with voting that feels as responsive as
  qdb.us's.
- Every quote's `#ID` link reaches a fully working discussion thread
  (existing reply functionality intact, untouched).
- Vote score shown per quote reflects real upvote/downvote reactions,
  with no new database table or schema migration.
