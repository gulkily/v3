# Offline Reading Health Check Step 2 Feature Description

## Problem

`/offline/` currently opens the snapshot reader and provides one generic error
when offline reading is unavailable. Readers and operators need a single page
that identifies whether the published snapshot or this browser's offline setup
is ready, without disrupting normal offline Board and thread reading.

## User stories

- As a reader, I want `/offline/` to tell me whether offline reading is ready
  on this device so that I know whether I can safely disconnect.
- As an operator, I want to distinguish a missing published snapshot from a
  service-worker or browser-cache issue so that I can recover production
  quickly.
- As a reader, I want actionable recovery guidance when the feature is not
  ready so that I can prepare it successfully.
- As a reader of saved public content, I want the normal Board and saved thread
  URLs to continue working offline so that my usual navigation remains useful.

## Core requirements

- Make `/offline/` a status and health-check page, not a snapshot browser.
- Report connection state, service-worker status, and local availability of the
  artifacts required for offline reading; make a failed or pending check clear.
- When online, report whether the published public snapshot is reachable; when
  offline, report only locally verifiable readiness and do not imply a server
  check occurred.
- Preserve the existing bounded public snapshot, public-only privacy boundary,
  and offline reading at normal Board and saved-thread URLs through a dedicated
  reader fallback.
- Add an **Offline Reading** link to the Tools index.

## Shared component inventory

- **Tools registry and Tools index — extend:** add the Offline Reading
  destination from their single shared registry.
- **Offline status page — new:** render device and published-snapshot health at
  `/offline/`, separate from saved-content browsing.
- **Offline cache lifecycle and service worker — extend:** keep refreshing the
  reader fallback and expose accurate browser-level readiness.
- **Public snapshot builder and route — reuse:** remain the only published
  content source and preserve their existing public-data bounds.
- **Offline Board/thread renderer — reuse:** continue rendering supported normal
  URLs from the dedicated fallback shell.

## User flow

1. A user opens **Tools → Offline Reading** while connected.
2. `/offline/` reports published-snapshot, service-worker, and local-cache
   health, then states whether offline reading is ready.
3. If a check fails, the page identifies it and provides the next recovery
   action; the user reconnects or refreshes and checks again.
4. Once ready, the user disconnects and opens the normal Board or a saved
   public thread URL, which continues to read from the bounded snapshot.

## Success criteria

- The page distinguishes an unavailable published snapshot from a missing
  service worker or missing local offline artifacts.
- A prepared public browser reports ready after refresh; a prepared disconnected
  browser reports locally available saved artifacts without claiming server
  reachability.
- Normal Board and snapshot-contained thread URLs remain readable offline with
  their existing offline-state indication.
- The Tools index contains one working Offline Reading link.
- Approved-members-only deployments continue not to cache public snapshots and
  clearly report that offline reading is unavailable.
