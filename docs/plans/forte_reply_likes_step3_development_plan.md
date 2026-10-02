# Forte Reply Likes — Step 3: Development Plan

## Stage 1 — Supply persisted reply-Like state

- **Goal:** Make Forte aware of the current viewer's Like state for every rendered reply.
- **Dependencies:** Approved Steps 1–2.
- **Expected changes:** Extend Forte's existing bulk post-reaction state preparation to pass reply Like state to the reply tree. No database or API change.
- **Verification:** Render Forte as a viewer with a recorded reply Like; confirm that reply begins as Liked, disabled, and pressed. Confirm anonymous rendering remains enabled and error-free.
- **Risks/open questions:** Keep the lookup bulk across all rendered posts; do not introduce per-reply reads.
- **Canonical components/API contracts:** Forte board state; shared viewer post-reaction lookup; existing post-reaction records.

## Stage 2 — Render Like for every reply node

- **Goal:** Show a post-level Like beside Flag for replies at every nesting depth.
- **Dependencies:** Stage 1.
- **Expected changes:** Extend the canonical Forte reply action row with the existing post-reaction control contract and its supplied applied state. No new endpoint, client interaction, or root-thread behavior.
- **Verification:** Render a thread with nested replies; confirm each has Like and Flag, and that a Like targets the individual reply's post identity.
- **Risks/open questions:** Preserve the existing permalink, nesting, highlighting, Flag control, and Forte styling.
- **Canonical components/API contracts:** Forte reply tree; shared post-reaction interaction; post-reaction API.

## Stage 3 — Cover and smoke-test the complete interaction

- **Goal:** Prevent regressions in reply Like rendering, persisted state, and interaction.
- **Dependencies:** Stages 1–2.
- **Expected changes:** Add focused Forte render coverage for reply Like controls and a previously Liked reply; no production behavior beyond Stages 1–2.
- **Verification:** Run the focused test and manually Like a top-level reply and a nested reply in Forte, reload, then confirm both remain Liked; recheck root Like and reply Flag.
- **Risks/open questions:** Confirm the existing lazy identity preparation and reaction feedback work unchanged for a reply Like.
- **Canonical components/API contracts:** Existing reaction test harness; shared post-reaction interaction and identity flow.

Reply **Approved Step 3** to begin implementation.
