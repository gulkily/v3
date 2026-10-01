# Offline Mode Outbox Banner Link — Step 2 Feature Description

## Problem

Offline readers can queue local actions but the offline-mode banner provides no
direct route to review them in Outbox.

## User stories

- As an offline reader, I want an Outbox link in the offline-mode banner so I
  can review my local drafts and queued actions without leaving the offline
  context.

## Core requirements

- Add a visible Outbox link to the offline-mode banner when it is shown.
- Route it to the existing Outbox view.
- Preserve the banner's current offline indicators and reader behavior.
- Do not change Outbox storage, queue processing, signing, or action states.

## Shared component inventory

- The offline-reader template owns the `offline-mode-bar`; extend that existing
  banner rather than creating another offline navigation surface.
- The existing `/tools/outbox/` view is the canonical destination for local
  drafts and queued actions; reuse it without a new outbox page or API.
- `offline_reader.js` continues to control when the banner is shown; no new
  offline interaction flow is introduced.

## User flow

1. Open saved content while offline.
2. See the offline-mode banner and select Outbox.
3. Review locally stored drafts and queued actions in the existing Outbox view.

## Success criteria

- The displayed offline-mode banner includes an Outbox link to the existing
  Outbox route.
- The link is available without changing the banner indicators.
- Existing offline-reader and Outbox behavior remains covered by focused tests.
