# Offline Like Presentation Parity — Step 2 Feature Description

## Problem

The snapshot reader renders the same Like button class and basic state
attributes as the online thread page, but omits the shared interaction
stylesheet. Its Like control therefore does not reliably match the online
natural-width, link-like button presentation.

## User stories

- As an offline reader, I want Like to look familiar so I can distinguish a
  queued local action from a different feature.
- As a forum user, I want thread and reply Likes to have the same visual
  language online and offline so the interface feels consistent.

## Core requirements

- Reuse the online `thread-reaction-button` presentation contract rather than
  introducing an offline-specific button style.
- Apply it to both snapshot thread-root and reply Like controls.
- Preserve the existing offline queue, signing, disabled, and `Liked` states.
- Do not add offline Reply, Flag, or action-toggle controls in this visual-only
  slice.

## Shared component inventory

- Online root and reply templates use `thread-reaction-button` inside
  `post-card-actions`; retain that contract in the snapshot renderer.
- `content-interactions.css` is the canonical shared interaction presentation;
  make it available to the offline-reader template instead of copying rules.
- `offline_reader.js` remains responsible only for snapshot rendering and its
  queued Like behavior.

## User flow

1. Open a saved thread without a connection.
2. See the thread or reply Like control styled like its online counterpart.
3. Select Like; it retains the familiar applied/disabled appearance while the
   signed local action is queued.

## Success criteria

- The offline-reader page loads the shared interaction stylesheet.
- Snapshot root and reply Likes retain the online class and natural action-row
  presentation.
- Existing offline Like queue tests continue to pass.
