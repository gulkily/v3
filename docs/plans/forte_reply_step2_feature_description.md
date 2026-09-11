# Forte Reply — Step 2: Feature Description

## Problem
Forte's paned reader has a disabled "Reply" toolbar button and no way to compose a reply to the selected post without leaving the paned window for the classic UI.

## User Stories
- As a Forte reader, I want to reply to the currently selected post without leaving the paned window, so that I stay in a consistent newsreader workflow.
- As a Forte reader, I want the reply composer to look like part of the paned interface, so that it doesn't feel like a bolted-on classic-UI form.
- As a Forte reader, I want the "Reply" toolbar button to reflect the current selection, so that it's clear what post my reply will attach to.

## Core Requirements
- Clicking the toolbar "Reply" button reveals an in-pane composer targeting the currently selected post (`thread_id` + `parent_id`).
- The composer reuses `partials/reply_form.php` and submits to the existing `/compose/reply` endpoint unchanged — no new backend logic.
- The composer is styled via `forte.css`-scoped classes so it visually matches the paned-window chrome (borders, spacing, font) rather than the classic `site.css` form look, per the existing CSS-isolation constraint (`.paned-window` scoping).
- The "Reply" toolbar button becomes enabled once a post is selected (it is currently permanently `disabled`).
- Submitting or cancelling the composer returns the reader to the thread view with the replied-to post's context intact (matching existing `/compose/reply` redirect behavior).

## Shared Component Inventory
- `partials/reply_form.php` — canonical reply form (fields, hidden inputs, submit actions). **Reused as-is**; only its container/wrapper classes differ per surface (Forte vs. classic), no fork of the form markup itself.
- `POST /compose/reply` (`Application.php::handleComposeReplySubmit`) — canonical reply-submission endpoint. **Reused unchanged.**
- `templates/partials/post_card.php` / `thread_root_card.php` "Reply" links — classic per-post reply entry point. **Not reused directly**; Forte instead drives the same form from its toolbar button, since Forte's single-selected-post model differs from the classic per-post-link layout.
- `forte.css` — existing Forte-only stylesheet (from `forte_css_isolation`). **Extended** with new scoped classes for the compose panel; no new stylesheet needed.
- `paned_reader.js` — existing pane interaction script. **Extended** with toggle logic for showing/enabling the composer; no new script file needed.

## Simple User Flow
1. Reader selects a post in the paned list, its content shows in the content pane.
2. Reader clicks the "Reply" toolbar button (now enabled).
3. An in-pane compose panel appears, styled to match the paned window, pre-filled with the selected post's `thread_id`/`parent_id`.
4. Reader types a body and submits (or posts anonymously, or clears/cancels).
5. On submit, the existing `/compose/reply` flow processes the reply and returns the reader to the thread, with the new reply visible in the pane.

## Success Criteria
- The toolbar "Reply" button is enabled whenever a post is selected and opens the in-pane composer.
- The composer visually matches Forte's paned chrome (reviewed against `forte.css` conventions), not the classic form look.
- Submitting the composer creates a reply via the existing `/compose/reply` endpoint with no new backend code, and the new reply is visible in the Forte pane afterward.
- No `site.css`/classic-page styling leaks into the composer, and no Forte-specific CSS leaks outside `.paned-window` (consistent with `forte_css_isolation`).
