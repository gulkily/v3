# PHP Non-ASCII Compose Autocorrection Slices V1

This document outlines a pragmatic plan to bring the non-ASCII compose handling from `~/v2` into `v3`.

## Goal

Keep the current ASCII-only canonical write contract, but improve the compose experience so common non-ASCII punctuation is corrected in the browser and clearly unsupported characters can be removed before submission.

## What `v2` Already Does

The relevant `v2` behavior is:

- browser-side normalization in `~/v2/templates/assets/browser_signing.js`
- a narrow replacement map for common punctuation and spacing characters
- explicit unsupported-character detection
- one explicit `Remove unsupported characters` action in `~/v2/templates/compose.html`
- focused tests for the helper seam in `~/v2/tests/test_browser_signing_normalization.py`

The important product boundary in `v2` is:

- safe deterministic punctuation is rewritten automatically
- unsupported characters are not transliterated semantically
- unsupported characters can be removed only through an explicit user action
- backend ASCII-only validation remains in place

## Current `v3` Gap

`v3` already enforces ASCII on the server in `src/ForumRewrite/Write/LocalWriteService.php`:

- `normalizeAsciiLine()`
- `normalizeAsciiBody()`
- `requireAsciiToken()`

But `v3` does not yet have the `v2` browser-side safety layer:

- no shared compose normalization helper in `public/assets/browser_signing.js`
- no compose-page normalization status message
- no explicit remove-unsupported control
- no focused tests for frontend normalization behavior

That means users currently hit hard submission failures for characters that `v2` already handles more gracefully.

## Slice 1: Shared Browser Normalization Helper

Focus:

- port the narrow deterministic normalization seam from `v2` into `v3`

Checklist:

- add a shared helper in `public/assets/browser_signing.js` with a contract equivalent to:
  - `normalizeComposeAscii(text, { removeUnsupported = false })`
  - returns normalized text plus correction/removal metadata
- port only the narrow `v2` character replacements:
  - smart single quotes
  - smart double quotes
  - en dash
  - em dash
  - ellipsis
  - non-breaking space
- keep newline normalization aligned with existing `v3` compose expectations
- do not change backend write validation in this slice

Expected outcome:

- `v3` gains one deterministic browser-side normalization seam without weakening the ASCII-only canonical contract

## Slice 2: Compose UI Status And Explicit Removal

Focus:

- expose the helper clearly in the existing thread/reply compose pages

Checklist:

- add one normalization status element to:
  - `templates/pages/compose_thread.php`
  - `templates/pages/compose_reply.php`
- add one shared `Remove unsupported characters` button/action, hidden or disabled unless unsupported characters remain
- wire the body textarea input flow in `public/assets/browser_signing.js` so:
  - common punctuation is corrected automatically
  - unsupported characters are detected and surfaced in status text
  - explicit removal updates the textarea contents and status text
- keep the action limited to body text in V1 rather than broadening immediately to every input field

Expected outcome:

- users get immediate browser feedback instead of only server rejection
- unsupported-character removal stays explicit and understandable

## Slice 3: Submission Alignment

Focus:

- ensure the compose flow submits exactly the normalized text the user sees

Checklist:

- make sure submit-time compose handling uses the already-normalized textarea value
- confirm browser identity/bootstrap flow continues to work unchanged
- confirm reply and thread compose pages both use the same normalization behavior
- preserve the current server-side ASCII rejection as the final safety net

Expected outcome:

- the text shown in the compose textarea is the same text that is submitted
- frontend normalization and backend validation reinforce each other instead of drifting

## Slice 4: Focused Tests

Focus:

- add targeted coverage around the normalization seam and compose templates

Checklist:

- add focused helper tests for:
  - punctuation correction
  - unsupported-character detection
  - explicit removal behavior
- choose a lightweight `v3` test seam similar in spirit to `v2/tests/test_browser_signing_normalization.py`
- extend compose-page smoke tests to assert the new status hook and removal control are rendered
- avoid broad browser-E2E scope in this slice

Expected outcome:

- the copied behavior is locked in by tests at the helper and template level

## Guardrails

- Keep the replacement list narrow and deterministic.
- Do not introduce semantic transliteration, emoji aliases, or language-specific rewriting in V1.
- Do not relax the server-side ASCII contract.
- Do not silently drop unsupported characters during ordinary typing; only the explicit removal action should remove them.
- Prefer one shared helper and one shared UI pattern over route-specific special cases.

## Recommended Order

1. add the shared browser normalization helper
2. add compose-page status and explicit removal UI
3. align submit-time behavior with the normalized textarea content
4. add focused tests

## Summary

The right copy strategy from `v2` is not "allow Unicode now." It is:

- keep canonical records ASCII-only
- add a narrow browser-side correction layer for obvious punctuation
- add one explicit unsupported-character removal affordance
- cover that behavior with focused tests

That gives `v3` the main usability benefit from `v2` without expanding scope into a broader encoding-policy rewrite.
