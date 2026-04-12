# PHP Non-ASCII Compose Autocorrection Improvement Plan V1

This document outlines a follow-up plan to reduce friction in the current `v3` non-ASCII compose handling.

## Current Problems

The current implementation works technically, but the UX is still heavier than it needs to be:

- a separate normalization status area is always present, even when nothing is wrong
- the unsupported-character action is an extra standalone button row instead of a tightly scoped inline recovery affordance
- successful automatic punctuation correction is too visible during ordinary typing
- the user still has to interpret multiple compose statuses instead of seeing one clear problem/recovery path

## Product Direction

The normalization flow should feel mostly invisible during normal use:

- quiet when text is already acceptable
- quiet when safe punctuation correction happens automatically
- obvious only when unsupported characters actually block submission
- one-step recovery when cleanup is needed

## Stage 1: Hide Idle Normalization UI Completely

- Goal: remove always-visible normalization chrome when the body has no unsupported characters.
- Expected changes:
  - stop rendering an always-visible empty normalization status line
  - ensure the unsupported-character action stays fully hidden in the idle state
  - show normalization UI only when there is an actual blocking problem
- Verification:
  - thread and reply compose pages render normally without extra normalization UI when the body is empty or ASCII-only
  - pages with smart quotes/dashes corrected automatically do not show extra visible success chrome

## Stage 2: Replace Separate Action Row With Inline Error Recovery

- Goal: make unsupported-character cleanup feel like part of the body-field validation state rather than an extra tool panel.
- Expected changes:
  - move the unsupported-character message and cleanup affordance closer to the body textarea
  - prefer a compact inline message with one clear action such as `Remove unsupported characters`
  - avoid a full-width extra button row when a smaller inline control will work
- Verification:
  - when unsupported characters are present, the body field shows one concise message and one obvious recovery action
  - when unsupported characters are removed, the inline message disappears cleanly

## Stage 3: Reduce Success-Path Noise

- Goal: stop announcing routine automatic correction unless it helps the user materially.
- Expected changes:
  - do not show a visible success message for normal punctuation normalization during typing
  - keep automatic correction deterministic, but make it mostly silent
  - reserve visible messaging for blocking unsupported-character cases and explicit removal results
- Verification:
  - typing smart quotes, dashes, or ellipses results in normalized textarea text without extra status clutter
  - explicit removal still gives a short confirmation when characters were actually removed

## Stage 4: Simplify Submit-Time Failure Messaging

- Goal: keep submit blocking clear and single-purpose.
- Expected changes:
  - if unsupported characters remain on submit, focus the body problem state instead of layering multiple messages
  - keep identity/bootstrap status separate from body-normalization errors
  - ensure the blocking message points directly to the inline recovery action
- Verification:
  - submitting with unsupported characters shows one clear body-related error path
  - identity readiness messages do not compete with normalization failure messaging

## Guardrails

- Keep server-side ASCII enforcement unchanged.
- Keep deterministic punctuation replacement unchanged.
- Do not silently remove unsupported characters without an explicit user action.
- Prefer fewer visible UI elements over adding more explanatory copy.
- Treat this as a compose UX cleanup, not a broader encoding-policy rewrite.

## Recommended Order

1. hide idle normalization UI
2. move unsupported-character recovery inline with the body field
3. suppress routine success-path messages
4. simplify submit-time failure messaging

## Summary

The next improvement pass should not add more controls. It should make the existing normalization behavior quieter and more local:

- no always-visible extra button area
- no routine success chatter
- one compact inline recovery path only when unsupported characters actually matter
