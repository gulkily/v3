# Offline Snapshot Publish Step 2 Feature Description

## Problem

Refreshing offline reading currently requires `build-static`, which renders the
entire static site even though offline reading needs only its bounded public
snapshot. Operators need a fast publication command after the read model is
current.

## User stories

- As an operator, I want one offline publish command so that I can refresh
  offline reading without waiting for every post page to render.
- As a reader, I want the last complete offline snapshot to remain available
  when a refresh fails so that a failed update does not break offline reading.
- As an operator, I want clear command output about snapshot freshness and
  contents so that I can verify what was published.
- As a privacy-conscious operator, I want approved-members-only deployments to
  continue withholding public offline content so that this fast path cannot
  expose protected data.

## Core requirements

- Provide a documented `./v3 offline publish` command that builds and publishes
  only the bounded public offline snapshot from the selected current read model.
- Do not render, copy, or activate static HTML post pages; retain `build-static`
  as the full-site publication command.
- Publish atomically and retain the previous valid public snapshot if building
  or replacing the new snapshot fails.
- Make `/offline/snapshot.sqlite3` serve the fast-published snapshot when one
  exists, with the current static-release snapshot as compatibility fallback.
- Preserve anonymous-only serving and the approved-members-only exclusion; make
  clear that normal deployment of the reader shell and SQLite runtime remains
  required.

## Shared component inventory

- **`PublicOfflineSnapshotBuilder` — reuse:** it is the canonical bounded,
  public-only snapshot producer and must be the sole data builder for both
  publication paths.
- **Offline snapshot front-controller route — extend:** resolve the independent
  published snapshot first while retaining release-based compatibility.
- **`./v3 offline` command group — extend:** add the operator-facing publish
  action beside diagnosis.
- **Offline diagnosis command — extend:** report the selected local publication
  source so verification reflects the fast path.
- **Service worker and reader shell — reuse:** consume the unchanged public URL;
  no new browser route or cache protocol is needed.

## User flow

1. The operator updates the read model through its normal ingestion or rebuild
   workflow.
2. The operator runs `./v3 offline publish` and receives snapshot metadata and
   the public URL to verify.
3. The command atomically replaces the independently published snapshot.
4. Connected browsers refresh their normal offline cache; disconnected readers
   continue using their already-saved snapshot until then.

## Success criteria

- The command completes without invoking static-page rendering and publishes a
  valid bounded SQLite snapshot from the selected read model.
- A failed publication leaves the prior public snapshot readable.
- The public snapshot endpoint returns the newly published snapshot without a
  static release rebuild.
- Approved-members-only mode publishes and serves no anonymous offline snapshot.
- Diagnosis identifies the active fast-published or fallback snapshot source.
