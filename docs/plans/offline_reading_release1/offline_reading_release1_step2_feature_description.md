# Offline Reading Release 1 Step 2 Feature Description

## Problem

Recent public discussion is unavailable when a visitor loses connectivity, even though the browser can already execute SQLite queries locally. The site needs a small, refreshable offline reading experience without exposing the complete operational read model or changing online writes.

## User Stories

- As a returning visitor, I want to browse the recent thread list offline so that I can catch up without a connection.
- As a returning visitor, I want to open a recent thread and read all of its visible replies offline so that a listing is useful rather than a dead end.
- As a visitor, I want to see when my offline content was last refreshed so that I understand it may be stale.
- As an operator, I want offline content limited to intentionally public fields so that browser caching does not expose operational or private data.

## Core Requirements

- Cache a public-only snapshot of the 50 most recently active visible threads, every visible reply in those threads, and necessary display metadata; enforce a defined storage budget.
- Make the snapshot available through an offline recent-thread reader after one successful online load; preserve the last complete snapshot while a refresh is pending or fails.
- Clearly label offline and cached content with its snapshot generation time.
- Keep posts, replies, reactions, APIs, account/tools pages, personalized pages, and the full `read_model.sqlite3` online-only and outside the offline cache.
- Preserve normal online navigation and graceful non-PWA behavior.

## Shared Component Inventory

- `templates/layout.php` / `TemplateRenderer`: extend the canonical shared layout for PWA discovery and registration; do not fork page chrome.
- `/threads/` board and `/threads/{id}` thread pages: reuse their public content semantics as the canonical online reader; add a new, deliberately narrow offline reader because server-rendered pages cannot query a local snapshot.
- `/tools/sqlite/` and `sqlite_viewer.js`: reuse the existing browser-local SQLite capability and runtime loading behavior; do not reuse its developer-query UI as the reader.
- `sql-wasm.js` / `sql-wasm.wasm`: reuse as the local read-only query runtime.
- Static artifact release and fingerprinted assets: extend the existing public-release/asset model for cacheable offline resources.
- Compose, signing, and reaction surfaces: leave unchanged and online-only in this release.

## User Flow

1. Visitor opens the public site online; the app shell and current recent-content snapshot become available locally.
2. Later, without a connection, the visitor opens the offline reader and sees the snapshot timestamp and recent thread list.
3. The visitor opens any listed thread and reads its visible replies locally.
4. On a later online visit, the app replaces the snapshot only after the new one is complete.

## Success Criteria

- After one successful online load, a browser in offline mode can open the offline reader, see a labeled recent-thread list, and read all visible replies in each of the snapshot's 50 threads.
- The reader makes no network request while offline and still renders with its required local assets.
- The cached artifact contains no workflow, LLM, handoff, account, session, or other non-public operational data.
- A failed refresh leaves the previous complete snapshot readable; a successful refresh displays the new generation time.
- Existing online reads and all write paths continue to work without service-worker support.
