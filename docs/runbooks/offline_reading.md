# Offline Reading Runbook

Offline reading keeps recent public Board and thread URLs usable without a
connection. It is intended for recently active public discussions, not a
complete archive.

## What is saved

When a visitor loads a public page while online, the browser may install the
offline-reader cache. It stores the Offline Reading health page, a separate
reader shell, their required same-origin assets, and the public SQLite snapshot
at `/offline/snapshot.sqlite3`.

Each static release builds a fresh snapshot from visible public content. It
contains every visible pinned thread plus up to 50 additional recently active
non-pinned threads, with all of their visible replies, subject to a 10 MiB
file limit. Pinned threads are selected first; if the file limit is reached,
the oldest whole non-pinned threads are omitted. The thin `offline mode` bar
identifies snapshot-backed reading.
Hidden records, identity/bootstrap records, profiles, account data, workflow
state, and LLM/operational tables are not included.

When the network is unavailable, the normal Board URL and normal URLs for
threads included in the snapshot render from those saved records. The saved
Board retains its All/Liked and Newest/Oldest/Top controls, evaluated only
against the downloaded public snapshot. Posting, voting, tagging, profiles,
search, feeds, tools, and threads outside the snapshot require a connection.

## Refresh behavior

An online public page load asks the service worker to refresh the reader shell
and snapshot. Normal navigations remain network-first and are not saved as
page copies. The replacement resources download before the new snapshot is
saved, so a failed refresh leaves the prior saved snapshot available. To
obtain a new release, reconnect and reload a public page.

## Health check

Open **Tools → Offline Reading** (or `/offline/`) to inspect this browser's
offline-reading setup. The page reports the connection, root service worker,
saved reader shell/assets, saved public snapshot, and—only while connected—the
published snapshot's reachability.

`Offline reading is ready on this device` means the browser has the worker and
all saved artifacts necessary to read the bounded snapshot. A connected browser
can still be ready from its saved copy when the newest published snapshot is
temporarily unavailable. While disconnected, the published-snapshot check is
explicitly marked as not checked rather than guessed.

## Privacy boundary

Approved-members-only deployments do not register the offline cache and do
not expose the snapshot to unauthenticated visitors. The cache does not
intercept APIs, account pages, posting routes, or other personalized pages.

Browser data that was already downloaded cannot be remotely revoked. If an
instance changes to approved-members-only mode, operators should tell prior
public visitors to clear their site data if the old public cache must be
removed from their device.

## Browser-cache recovery

If the health page says offline reading is not ready, or saved Board or thread
content fails to load while offline:

1. reconnect, reload a public page, and wait for it to finish loading;
2. reopen **Tools → Offline Reading** and use **Check again** until the saved
   reader shell, assets, and public snapshot are available;
3. disconnect, then reopen the normal Board or saved thread URL and check that
   the `offline mode` bar appears;
4. if it still fails, clear this site's storage/cache in the browser settings,
   revisit a public page online, and check the health page again.

Clearing site data removes the saved reader and snapshot until the browser
next refreshes them online.
