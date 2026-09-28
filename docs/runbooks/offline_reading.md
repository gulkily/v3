# Offline Reading Runbook

Release 1 provides a public, read-only offline reader at `/offline/`. It is
intended for recently active public discussions, not a complete archive.

## What is saved

When a visitor opens `/offline/` while online, the browser may install the
offline-reader cache. The prototype is scoped to `/offline/`; it does not
control normal site pages. It contains the reader shell, its required
same-origin assets, and the public SQLite snapshot at
`/offline/snapshot.sqlite3`.

Each static release builds a fresh snapshot from visible public content. It
contains up to 50 recently active threads and their visible replies, subject
to a 10 MiB file limit. The reader displays the snapshot generation time.
Hidden records, identity/bootstrap records, profiles, account data, workflow
state, and LLM/operational tables are not included.

The snapshot is read-only: posting, voting, and tagging require a connection.

## Refresh behavior

An online visit to `/offline/` asks the service worker to refresh the
offline-reader shell and snapshot. It downloads the replacement resources
before saving the new snapshot, so a failed refresh leaves the prior saved
snapshot available. To obtain a new release, reconnect and reload `/offline/`.

## Privacy boundary

Approved-members-only deployments do not register the offline cache and do
not expose the snapshot to unauthenticated visitors. The cache does not
intercept APIs, account pages, posting routes, or other personalized pages.

Browser data that was already downloaded cannot be remotely revoked. If an
instance changes to approved-members-only mode, operators should tell prior
public visitors to clear their site data if the old public cache must be
removed from their device.

## Browser-cache recovery

If the reader is stale or fails to load while offline:

1. reconnect, reload a public page, and wait for it to finish loading;
2. reopen `/offline/` and check its saved-snapshot timestamp;
3. if it still fails, clear this site's storage/cache in the browser settings,
   revisit a public page online, and try `/offline/` again.

Clearing site data removes the saved reader and snapshot until the browser
next refreshes them online.
