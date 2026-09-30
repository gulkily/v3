# Offline Support Roadmap

**Status:** Backlog. This records product direction and sequencing; each
selected slice must still enter the Feature Development Process (FDP) before
implementation.

## Product intent

After visiting the public site online, a reader should be able to continue
using a clearly bounded, trustworthy version of the forum without a network
connection. The long-term aspiration is to support offline participation, but
read-only continuity and safe recovery come first. Offline writes must never
silently lose, duplicate, expose, or misrepresent a user's work.

## Completed foundation

The following is merged and considered the baseline for future work:

- A public-only SQLite snapshot is built for each static release.
- It includes every visible pinned thread plus up to 50 recent non-pinned
  threads, all visible posts in those threads, and no private or operational
  tables; the snapshot remains capped at 25 MiB.
- Normal public Board and saved-thread URLs work from that snapshot when the
  network fails. Online navigation remains network-first.
- Board controls supported by the snapshot are available offline: All/Liked
  and Newest/Oldest/Top.
- The snapshot also supports a bounded Tags index and saved tag-result views;
  the shared offline subnav includes Tags and a disabled New Post control.
- Offline Board and thread cards follow normal title, hidden-post, heat, and
  theme presentation rules. A thin `offline mode` bar is the persistent
  offline-state indicator and carries compact archive-time and reader-revision
  indicators.
- Tools → Offline Reading reports saved archive metadata, cached-versus-live
  reader revision, and a confirmed refresh/recheck action.
- Public-only deployments opt in to the cache; approved-members-only
  deployments do not expose or register it.

See [Offline Reading Release 1](../offline_reading_release1/) and
[Offline Normal Navigation](../offline_normal_navigation/) for the completed
FDP records and [the operator runbook](../../runbooks/offline_reading.md) for
current bounds and recovery instructions.

## Non-negotiable guardrails

Every later slice must preserve these unless an FDP explicitly changes them:

- Never cache private/member-only pages, account data, private keys,
  identities, LLM/operational data, or authenticated API responses in the
  public offline cache.
- Never replace an online response with a saved HTML page; normal navigation
  remains network-first and uses the snapshot only after a network failure.
- Never imply that a stale snapshot is current, that an unavailable action
  succeeded, or that a queued write has reached the server.
- Keep full-thread snapshots atomic: a failed refresh retains the last
  complete snapshot.
- Treat data removal, account changes, moderation state, and conflicts as
  first-class offline-write cases, not edge cases.

## Roadmap

### 1. Snapshot lifecycle and reader confidence

**Status:** Partially complete; finish confirmation, clearing, and browser QA.

**Reader outcome:** A reader can tell that a snapshot has been saved, how old
it is when needed, and how to refresh or remove it without using browser
developer tools.

**Progress and remaining pieces:**

1. [ ] A short, non-persistent online confirmation after a complete snapshot is
   saved; it must distinguish success, retained prior snapshot, and failure.
2. [x] An optional details surface for saved-at time, reader revision, thread
   and post counts, and size. The ordinary offline view remains limited to its
   thin `offline mode` bar and compact indicators.
3. [x] An explicit refresh/recheck control with confirmed status messages.
4. [ ] An explicit clear control with confirmation and accessible status.
5. [ ] A browser QA matrix covering first install, refresh, failed refresh,
   worker upgrade, clear, offline restart, and reconnection.

**Dependencies / decisions:** Decide where the optional details and controls
belong (settings, an unlinked diagnostics page, or another deliberately
minimal surface), and whether refresh is automatic-only or user-triggered.

### 2. Broader read-only navigation

**Status:** Partially complete; Tags navigation is delivered.

**Reader outcome:** More public navigation remains useful while offline,
without turning the browser cache into a copy of the site.

**Progress and candidate slices, each independently scoped:**

1. [x] Snapshot-backed tag index and tag-thread views, with a bounded tag index
   derived only from the saved threads.
2. [ ] Links between saved posts, threads, and permitted public metadata.
3. [ ] Carefully selected public profile summaries, only if their data boundary
   and deletion/revocation behavior are acceptable.
4. [ ] Explicit unavailable pages for search, feeds, tools, and routes not in the
   snapshot, instead of ambiguous browser errors.

**Do not assume:** Tags, profiles, search, or feeds belong in the snapshot by
default. Each changes the public-data and staleness boundary.

### 3. Saved drafts, without sending offline writes

**Status:** Proposed; useful precursor to participation.

**Reader outcome:** A user can safely compose a thread or reply while offline
and recover it after restart, but it remains a draft until the user explicitly
submits online.

**Likely pieces:** Local draft storage, clear ownership/retention rules,
per-thread draft recovery, conflict-safe restoration, deletion controls, and
privacy warnings on shared devices.

**Why separate it:** Draft persistence is much safer than a write queue and
can establish local-data lifecycle and recovery UX before any server mutation
is attempted.

### 4. Queued low-risk reactions

**Status:** Later; requires an approved write-queue design.

**Reader outcome:** A user can queue a like or flag offline and later see
whether the server accepted, rejected, or no longer needs that intent.

**Required design work:**

1. An authenticated, encrypted local queue with no private-key leakage into
   the public cache.
2. Stable intent IDs and server-side idempotency, so retries cannot duplicate
   reactions.
3. Per-item states such as queued, sending, accepted, rejected, conflicted,
   and cancelled, with user-visible recovery actions.
4. Reconciliation against changed/deleted/hidden posts and changed
   authorization or moderation state.
5. Explicit background-sync and browser-support policy; the queue must also
   work when background sync is unavailable.

**Open policy question:** A queued flag may affect moderation expectations.
Decide whether it is appropriate to display it as a local intention before the
server has received it.

### 5. Queued replies and new threads

**Status:** Later; substantially larger than queued reactions.

**Reader outcome:** A user can create content offline and have it reliably
submitted after reconnecting, with clear control over every pending item.

**Required design work:**

1. Everything in queued reactions, plus durable compose payloads, attachments
   (if applicable), parent/thread references, and local preview state.
2. A signing/authentication strategy that works after a browser restart
   without retaining unsafe credentials.
3. Server-side idempotency for post creation and a durable mapping from local
   draft IDs to canonical post IDs.
4. Resolution paths when the parent thread is deleted, locked, hidden, or
   changed; when validation rules change; or when the identity loses access.
5. User controls to edit, reorder, retry, cancel, export, or discard pending
   content before transmission.
6. Clear disclosure that a queued post is not public until the server accepts
   it.

This should be its own multi-stage release, not an extension of a read-only
cycle.

### 6. Offline-first synchronization maturity

**Status:** Future strategy, not scheduled work.

Potential capabilities include incremental snapshots, content-delta sync,
storage budgets, multi-device behavior, attachment policy, conflict history,
and observability. Do not start this work until queued-write behavior has
proved reliable and the product requires more than bounded reading.

## Explicitly out of scope for the current read-only feature

- Offline access to approved-members-only or other personalized content.
- Offline search across the entire forum.
- Automatic caching of all static pages or the full server database.
- Offline posting, voting, tagging, moderation, account changes, and invite
  flows.
- Silent background retries that hide pending or failed user actions.

## Suggested ordering

1. Snapshot lifecycle and reader confidence.
2. One bounded read-only navigation expansion, if usage demonstrates a need.
3. Saved drafts.
4. Queued reactions.
5. Queued replies and threads.
6. Incremental/offline-first synchronization only if justified.

The first three are separable; do not make queued writes a prerequisite for a
better reading experience. Queued reactions and queued posts, however, share
security, idempotency, and reconciliation foundations and should be planned
together before either is built.
