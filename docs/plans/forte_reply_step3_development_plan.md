# Forte Reply — Step 3: Development Plan

## Stage 1
- Goal: Embed the reply composer markup into the Forte paned content pane, hidden by default, targeting the thread and default-selected (root) post.
- Dependencies: none (Step 2 approved)
- Expected changes: new partial (e.g. `templates/partials/paned_compose_panel.php`) wrapping `partials/reply_form.php` with a `hidden` attribute and Forte-specific wrapper classes; included from `templates/pages/forte.php` or `paned_content_pane.php`; `renderForte()` passes whatever `thread_id`/`board_tags` values the partial needs (already available via `$thread`)
- Verification approach: load a Forte thread page, inspect DOM for the hidden panel with correct `thread_id`/`parent_id` hidden inputs; confirm classic thread pages are unaffected
- Risks or open questions:
  - Confirm field names available on `$thread` (e.g. tags) match what `reply_form.php` expects for `board_tags`
- Canonical components/API contracts touched: `partials/reply_form.php` (reused unchanged), `templates/pages/forte.php` / `paned_content_pane.php` (extended)

## Stage 2
- Goal: Enable the toolbar "Reply" button and wire it to toggle the compose panel's visibility.
- Dependencies: Stage 1
- Expected changes: remove `disabled` from the Reply button in `forte.php`, add a `data-paned-reply` hook; `paned_reader.js` gets a click handler toggling the panel's `hidden` attribute (button stays enabled since Forte always has a selected post by default)
- Verification approach: load Forte, click Reply, confirm panel appears/disappears; confirm no console errors
- Risks or open questions: none identified
- Canonical components/API contracts touched: `public/assets/paned_reader.js` (extended)

## Stage 3
- Goal: Keep the compose panel's target post in sync with the pane's current post selection.
- Dependencies: Stage 2
- Expected changes: existing post-selection handler in `paned_reader.js` updates the compose form's hidden `parent_id` input whenever selection changes
- Verification approach: select different posts, open the composer, confirm `parent_id` matches the selected post each time
- Risks or open questions:
  - Decide behavior when a draft is in progress and selection changes (reset vs. preserve draft) — default to resetting the field only, leaving typed body text untouched, unless testing shows confusion
- Canonical components/API contracts touched: `public/assets/paned_reader.js` (extended)

## Stage 4
- Goal: Style the compose panel to visually match Forte's paned chrome.
- Dependencies: Stage 1
- Expected changes: new scoped rules in `forte.css` for the compose panel and its inner elements (textarea, buttons, labels), following the same `.paned-window`-scoped pattern used for the folder tree and post list; no `site.css` changes
- Verification approach: visual comparison of Forte vs. classic reply UI; rerun the `forte_css_isolation` static-build fingerprint check to confirm no cross-contamination
- Risks or open questions:
  - Visual fidelity is subjective; may need a review/iteration pass with the user
- Canonical components/API contracts touched: `public/assets/forte.css` (extended)

## Stage 5
- Goal: Verify and fix the end-to-end submit flow so replying from Forte returns the reader to Forte (not the classic thread view).
- Dependencies: Stages 1-4
- Expected changes: `handleComposeReplySubmit()` currently redirects unconditionally to `/threads/{id}`; add a way to preserve "came from Forte" (e.g., a hidden `return_to` field on the composer read back on submit) so success and validation-error paths both return to the Forte view instead of the classic one
- Verification approach: submit a reply (named and anonymous) from Forte and confirm redirect back into Forte with the new post visible; submit an invalid reply and confirm the error re-render also stays in Forte
- Risks or open questions:
  - Need to confirm the validation-error path (`renderComposeReplyPage`) can also honor a Forte return target, or whether it needs a Forte-specific error re-render instead
- Canonical components/API contracts touched: `Application.php::handleComposeReplySubmit` (extended), `partials/reply_form.php` (gains a hidden return-target field, reused by both surfaces)
