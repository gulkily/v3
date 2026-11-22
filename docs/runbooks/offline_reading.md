# Offline Reading Runbook

Offline reading keeps recent public Board, Tag, and thread URLs usable without
a connection. It is intended for recently active public discussions, not a
complete archive.

## What is saved

When a visitor loads a public page while online, the browser may install the
offline-reader cache. It stores the Offline Reading health page, a separate
reader shell, public New post and Reply composer pages, their required
same-origin assets, and the public SQLite snapshot at `/offline/snapshot.sqlite3`.

Each static release builds a fresh snapshot from visible public content. The
fast offline publication command can refresh that snapshot later without
rebuilding the release. The snapshot contains every visible pinned thread plus
up to 50 additional recently active non-pinned threads, with all of their
visible replies, subject to a 25 MiB file limit. Pinned threads are selected
first; if the file limit is reached,
the oldest whole non-pinned threads are omitted. The thin `offline mode` bar
identifies snapshot-backed reading.
Hidden records, identity/bootstrap records, profile data other than imported
public keys, account data, workflow state, and LLM/operational tables are not
included. Every approved public key is imported; an unapproved key is included
only when it accompanies saved public discussion. Keys already downloaded to a
browser are subject to the same no-remote-revocation boundary as saved posts.

When the network is unavailable, normal Board URLs (including All/Liked and
Newest/Oldest/Top controls), the Tags index, saved tag-result URLs, and normal
URLs for threads included in the snapshot render from saved records. Board
filters, tag counts, previews, and results are evaluated only against the
downloaded public snapshot; an unavailable tag or thread explains that live
results require reconnecting. Profiles, search, feeds, most tools, and threads
outside the snapshot require a connection. **Tools → Outbox** is a separate
local-work screen: it can be opened offline but is not part of the public
snapshot.

## Refresh behavior

An online public page load asks the service worker to refresh the reader shell
and snapshot. Normal navigations remain network-first and are not saved as
page copies. The replacement resources download before the new snapshot is
saved, so a failed refresh leaves the prior saved snapshot available. To
obtain a new release, reconnect and reload a public page.

## Publishing a fresh snapshot

The standard initial public read-model rebuild automatically creates the first
offline snapshot when none is served. It reports an error after model promotion
if that required publication fails; correct the error and use `offline publish`
as the recovery command. Approved-members-only instances intentionally skip
public snapshot publication.

On an eligible public instance, the PHP snapshot route also repairs a missing
snapshot when it receives its normal anonymous `GET` or `HEAD` request. It uses
the same atomic publisher and a short lock, so concurrent first requests reuse
one publication rather than competing builds.

The configured internal task queue requests this publication after successful
public read-model updates. After the normal
ingest or read-model rebuild has completed, an operator can also publish the
latest offline reading set without rendering every static post page:

```sh
./v3 offline publish
./v3 task-queue enqueue-offline-snapshot
./v3 offline diagnose --url=https://your-public-domain
```

`offline publish` creates the bounded public SQLite base and compact update,
atomically replacing each independently published copy. It does not rebuild the read model,
render static HTML, deploy an application release, or refresh any browser
cache. The reader uses the application’s canonical fingerprinted SQLite runtime
URL, rather than relying on an unfingerprinted binary path. It preserves the
prior snapshot if the new build fails. `build-static` remains the complete-site
publication command and its snapshot remains a fallback when no independently
published snapshot exists.

Do not run the fast command until the read model contains the content intended
for readers. It refuses to publish while approved-members-only mode is enabled.
After a successful publish, visitors need to load a public page while online so
their service worker can download the new snapshot.

## Health check

Open **Tools → Offline Reading** (or `/offline/`) to inspect this browser's
offline-reading setup. The page reports the connection, root service worker,
saved reader shell/assets, saved public snapshot, and—only while connected—the
published snapshot's reachability. It also reports:

- **Archive generated** — the timestamp embedded in the saved snapshot. This
  is the saved-content date, not the date a browser last opened it.
- **Saved reader revision** — the fingerprinted reader interface cached on the
  device.
- **Current reader revision** and **Saved reader freshness** — an online
  comparison of the cached interface with the currently published interface.

Use those signals to distinguish a capability issue from stale content:

- If **Saved reader freshness** says `Matches current reader`, a missing
  offline control is not caused by an old cached reader. Check the documented
  [supported reading routes](#what-is-saved) and
  [offline writing limits](#writing-offline).
- If it says `Different from current reader`, choose **Refresh saved reader**
  while online. Wait for `Saved reader refreshed and rechecked.`, then check
  the comparison again. That refresh downloads the reader assets and public
  snapshot but does not cache normal Board or Tag page documents.
- If the comparison says `Not checked while offline`, reconnect before
  determining whether the interface is stale. The saved archive can still be
  read while disconnected.

The offline Board and Tags subnav retains the supported reading controls.
To write offline, use an available composer as described below. A matching
reader revision does not confirm that a particular action is supported offline.

## Local Outbox

Open **Tools → Outbox** to review local Likes, reply drafts, and thread drafts
saved on this browser. The Outbox is cached as its own shell, while its items
live in this browser's IndexedDB storage—not in the public SQLite snapshot or
the service-worker cache. Its content is therefore device-local and is never
published merely because it appears in the Outbox. Each item is a compact row;
open it to inspect its status, timing, outcome, and available controls.

A draft has been saved locally; queue it when it is ready. Queued work is
processed automatically when the browser regains a connection, and when a page
with a composer, the Outbox or the offline reader opens while connected.
Drafts are never sent automatically. A closed browser has no Background Sync
worker and therefore cannot deliver queued work until an appropriate page is
opened again.

An offline Like is signed when the person presses **Like**, before it is saved
locally. This applies to both thread and reply Likes. The signature includes an
**action time**, which is the person's client-asserted time of the action; it is
context rather than server ordering authority. At successful server handling,
the server records an **integration time**. The Outbox shows both separately
when available. Server acceptance, integration time, and the normal server
ordering/moderation rules remain authoritative.

On delivery, replies and threads use the normal identity, prepare, sign, and
finalize flow; a queued Like submits its signed reaction intent to the
verification endpoint. The outcome remains visible as accepted, rejected,
conflicted, waiting-for-connection, or needs-attention. An accepted reply or
thread links to its server record.

If a reply or thread delivery loses its response, the Outbox retains the
prepared record and retries that same record; it checks the known post ID
before treating a missing prepared record as a failure. Do not clear browser
site data until you have reviewed or discarded the local items. Browser storage
is also the current recovery boundary: sharing a device can expose saved drafts
to another person using the same browser profile.

### Writing offline

After the browser has saved the public composer pages, it can reopen them
without a connection at their usual addresses, `/compose/thread` and
`/compose/reply`. The offline reader also provides an inline composer on its
Board and saved thread views. The cached Reply page takes its target from the
address and can show the quoted post from the saved snapshot. If the thread
isn't saved on this device, you can still write the reply locally.

For a text post using the signed posting path, pressing **Post** while the
browser is offline queues it and reports "Saved on this device, not sent."
A saved thread lists its own unsent replies above the reply composer. Check
**Tools → Outbox** for delivery status. Queued items can be delivered when
you reconnect with an appropriate page open, through the normal signed
posting flow. **Save to Outbox** saves an unqueued local draft instead; it
won't send until you explicitly queue it.

Guest submissions and posts with images are not queued offline; the text
stays in the composer. If a request fails while the browser reports a
connection, the server may already have received it. The composer reports
the failure and keeps the text; it does not automatically queue another send.

This MVP does not yet provide draft editing, export or bulk clear confirmation;
use **Discard** or **Remove** in the Outbox to remove individual local records.

`Offline reading is ready on this device` means the browser has the worker and
all saved artifacts necessary to read the bounded snapshot. A connected browser
can still be ready from its saved copy when the newest published snapshot is
temporarily unavailable. While disconnected, the published-snapshot check is
explicitly marked as not checked rather than guessed.

## Privacy boundary

Approved-members-only deployments do not register the offline cache and do
not expose the snapshot to unauthenticated visitors. The cached composer pages
are public shells, not account pages or personalized content. The worker
does not cache posting requests or API responses for offline use. Unsent
Outbox items live in this browser's IndexedDB, separate from both the
service-worker cache and the public snapshot.

Browser data that was already downloaded cannot be remotely revoked. If an
instance changes to approved-members-only mode, operators should tell prior
public visitors to clear their site data if the old public cache must be
removed from their device.

## Browser-cache recovery

If the health page says offline reading is not ready, or saved Board, Tag, or
thread content fails to load while offline:

1. reconnect and open **Tools → Offline Reading**;
2. if the saved reader differs from the current reader, use **Refresh saved
   reader** and wait for its recheck confirmation; otherwise use **Check again**;
3. confirm that the saved reader shell, assets, public snapshot, and the
   desired archive generation time are available;
4. disconnect, then reopen a normal Board control URL, Tags or saved tag-result
   URL, or saved thread URL and check that the `offline mode` bar appears;
5. if it still fails, clear this site's storage/cache in the browser settings,
   revisit a public page online, and check the health page again.

Clearing site data removes the saved reader and snapshot until the browser
next refreshes them online.

## Snapshot exclusion contract (version 5)

Public base and update databases include `excluded_posts(post_id TEXT PRIMARY KEY)`. These IDs are temporary publication exclusions for valid retained retractions whose canonical target was confirmed absent at build entry; they do not grant permanent removal authority. The records themselves are not copied into public snapshots. Target reappearance stays excluded for that build and is validated normally by a later build. Canonical records are retained.

The reader applies exclusions even when their threads are outside an update's bounded window, removes the matching cached post, clears excluded-root presentation and refreshes affected thread counts/activity while preserving surviving replies. Older snapshots without this table remain readable. Version 5 artifacts require the updated reader to enforce these exclusions when applying updates to older bases; refresh the existing reader/service-worker assets with the application release. Existing saved offline copies cannot be changed until the client reconnects and refreshes.

For this absent-target condition, publication needs no canonical repair: run the existing `./v3 offline publish` command or let the existing queued publication run. Both publish base and compact update. If validation instead reports unsafe or unavailable evidence, inspect `records/post-retractions/` in the selected repository, restore missing tracked files or repair malformed/unsafe evidence, then rerun publication. A queued retry can succeed after repair; for an exhausted task use the existing `task-queue enqueue-offline-snapshot` and targeted worker commands against that same instance. Do not delete retained retractions to bypass validation.
