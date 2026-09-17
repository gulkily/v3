# Forte New Thread Dialog Status — Step 1: Solution Assessment

## Problem
Now that the New Thread dialog runs the full compose JS, every in-flight/error status message (`setStatus`, e.g. "Preparing signed thread...", identity/signing errors) is silently dropped because the dialog has no `[data-role="compose-identity-status"]` element for it to render into, unlike every other compose form.

## Option A — Add a dedicated status line inside the dialog
Mirror the Forte reply panel, which already has a `data-role="compose-identity-status"` paragraph inside its compose root.
- Pros: matches the pattern every other compose root already uses successfully; small, additive template change; no JS/selector changes needed.
- Cons: adds one more visible line to a small modal; needs Windows-chrome styling to fit.

## Option B — Route dialog status to the board's existing statusbar
Show messages in the `.paned-statusbar` at the bottom of the window instead of inside the dialog.
- Pros: no new UI surface inside the compact dialog.
- Cons: the status node must be a descendant of the compose root for `bindComposePage` to find it, so this needs a broader lookup change; feedback (especially errors) appears far from where the user is looking, inside a modal.

## Option C — Repurpose the existing (hidden) character-normalization status line
Point the identity-status selector at the already-present `[data-role="compose-normalization-status"]` node instead of adding a new element.
- Pros: zero new markup.
- Cons: conflates two unrelated feedback channels (character warnings vs. signing/identity progress) that can clobber each other; requires changing which selector `bindComposePage` treats as the identity-status node.

## Recommendation
**Option A.** Lowest risk, smallest diff, and consistent with the pattern already proven on every other compose form (including Forte's own reply panel).

## Refinement (per user direction)
Unlike the reply panel's status line (`hidden` until the first message), this one should be:
- Always visible, not `hidden`-by-default.
- Populated with a default "Ready" state before any action, instead of being empty/blank until something happens.
