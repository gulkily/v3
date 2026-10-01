# Extended Offline Parity Roadmap

**Status:** Product direction and backlog. Each selected slice requires its
own Feature Development Process (FDP) record before implementation.

## Goal

Deliver the closest practical offline equivalent of the forum while keeping
state honest, private data private, and every unavailable or pending action
understandable. “Parity” has three distinct meanings:

1. **Read parity:** public information and navigation available from an
   explicitly bounded saved snapshot.
2. **Prepare parity:** a person can prepare work locally without claiming it
   was sent or accepted.
3. **Action parity:** a person can deliberately queue selected actions and
   inspect their eventual server outcome.

Full parity is not an unconditional promise. Personalized, privileged, or
irreversible features need a separate security and revocation design before
they can leave the online-only boundary.

## Current baseline

- [x] Public Board, saved threads, Tags index, and saved tag results.
- [x] Board filters/sorts, normal read URLs, theme/title/hidden-post
  presentation, and a disabled offline New Post control.
- [x] Snapshot time, reader revision, offline health diagnostics, and a
  confirmed refresh/recheck flow.
- [x] Public-only cache boundary and approved-members-only exclusion.
- [x] Tools → Outbox: compact expandable local Likes, reply drafts, and thread
  drafts with visible outcomes and foreground automatic delivery of queued
  work after reconnect; drafts remain local.
- [ ] User-visible snapshot-saved confirmation, clear-storage action, and
  browser QA matrix.

## Parity matrix

| Area | Target offline experience | Current state | Safety/decision gate |
| --- | --- | --- | --- |
| Board | Browse saved threads; filter/sort; clearly show snapshot bounds | Partial, delivered | Broaden coverage only within public size/privacy budget |
| Thread and replies | Read complete saved threads and follow saved links | Partial, delivered | Missing/deleted/hidden content must say why it is unavailable |
| Tags | Browse saved tags and saved results | Delivered | Keep index derived only from the snapshot |
| Profiles | Read selected public summaries | Not started | Deletion, revocation, visibility, and identity boundary |
| Search and feeds | Search/browse only saved public content | Not started | Local index size, stale-result disclosure, unavailable global search |
| Tools and diagnostics | Open a minimal offline-help/health surface | Partial, delivered; Outbox is cached separately | Never cache operator or personalized tools |
| Compose | Create, retain, edit, export, and discard local drafts | Partial; capture from an open compose page | Shared-device privacy and clear retention rules |
| Reactions | Queue eligible reactions and reconcile an outcome | Thread/reply Like queue delivered; signed at click time | Identity, idempotency, moderation semantics |
| Threads and replies | Queue signed content with clear status and foreground automatic delivery | Initial delivery delivered | Durable signing, conflicts, parent availability, server idempotency |
| Account, invitations, moderation, administration | Remain online unless separately designed | Intentionally online | Authentication, privilege, revocation, and audit guarantees |

## Delivery sequence

### 0. Trust and convenience foundation

**Outcome:** A reader understands what is saved, what is missing, and how to
recover without developer tools.

- [x] Archive date, reader revision, cached-versus-live comparison, and
  refresh/recheck.
- [ ] Brief automatic completion/failure/retained-copy notice after a refresh.
- [ ] Clear saved offline data with a confirmation, result message, and a
  straightforward reinstall path.
- [ ] Snapshot coverage summary: included threads/tags, limits, and why a
  requested item is absent.
- [ ] Browser QA matrix for install, upgrade, refresh failure, offline restart,
  reconnect, quota pressure, clear, and supported browsers.

### 1. Maximum safe public read parity

**Outcome:** All suitable public reading tasks work from a bounded snapshot;
unsupported routes say so plainly instead of failing ambiguously.

- [x] Board, saved threads, and Tags navigation.
- [ ] Saved-content links among posts, thread references, and public metadata.
- [ ] Selected public profile summaries, if the visibility/removal policy is
  approved.
- [ ] Saved-content search and feeds with scope and freshness labels.
- [ ] A consistent unavailable-route screen for unsupported public routes.
- [ ] Reader preferences for download size, recency window, and manual
  download/update where browser support permits.

### 2. Local preparation

**Outcome:** Offline work is useful before it is networked, but never appears
submitted.

- [x] Thread and reply drafts stored locally from an already-open compose page.
- [ ] Restore, edit, delete, export, and discard drafts after restart.
- [ ] Per-thread draft association and safe recovery when a parent is absent.
- [ ] Visible distinction between a local draft, a queued action, and an
  accepted server record.

### 3. Shared Outbox and queued low-risk actions

**Outcome:** Every queued user action appears in one honest, controllable
Outbox rather than being hidden behind individual screens.

The **Outbox** is a first-class offline screen that displays all queued actions
across the device: reactions, flags (if approved), replies, threads, and any
future eligible action. It must show, for each item:

- action type, target, local creation time, last attempted time, and a safe
  summary of what will be sent;
- state: `draft`, `queued`, `waiting for connection`, `sending`, `accepted`,
  `rejected`, `conflicted`, `cancelled`, or `needs attention`;
- a concise server result or recovery explanation, without claiming success
  before confirmation;
- item controls appropriate to the state: open/edit draft, retry, cancel,
  export, or discard; and
- filtered counts and an accessible status summary so people can identify
  work that still needs attention.

Delivered in the initial Outbox release:

- [x] A device-local Outbox under Tools, separate from the public snapshot.
- [x] Stable local item IDs, honest item states, and explicit Send/retry/discard
  controls.
- [x] Foreground reconnect delivery for queued work without Background Sync;
  drafts are never sent automatically and a closed browser does not deliver.
- [x] Queue and reconcile signed Likes for threads and replies; their signed
  action time and server integration time remain distinct.

Remaining hardening before broadening action types:

- [ ] Encrypted/authenticated local storage appropriate for shared devices that
  does not expose private keys
  through the public cache;
- [ ] Server-side client-intent idempotency across all post retry windows;
- [ ] reconciliation for removed, hidden, locked, or no-longer-authorized
  targets; and
- [ ] clear-device-data behavior that warns before pending work is removed.

After those foundations:

- [ ] Decide whether flags can be queued without misleading moderation users.

### 4. Queued replies and threads

**Outcome:** A person can deliberately submit composed content after
reconnection and follow its result from the Outbox.

- [x] Durable compose payloads and explicit signed send/retry for reply and
  thread Outbox items.
- [ ] Parent/thread availability checks and conflict paths for locked, removed,
  hidden, or changed threads.
- [x] Accepted Outbox items link to their canonical post/thread IDs.
- [ ] Server mapping from local intent IDs to canonical post IDs.
- [ ] Outbox controls to edit, reorder, cancel, export, and bulk discard
  (individual retry and discard are delivered).
- [x] Clear disclosure that queued content is not public until accepted.

### 5. Synchronization maturity

**Outcome:** Offline use scales without turning a browser cache into an
unbounded mirror of the site.

- [ ] Incremental snapshot/content-delta updates.
- [ ] Storage budgets, quota warnings, eviction rules, and download controls.
- [ ] Multi-device expectations, conflict history, and observability.
- [ ] Attachment policy and offline media lifecycle.

## Non-negotiable constraints

- Never cache private/member-only pages, account data, private keys,
  identities, authenticated API responses, or operational data in the public
  offline cache.
- Never present a local draft or queued item as a server-accepted action.
- Preserve complete prior saved data when refresh or synchronization fails.
- Make snapshot age, scope, and missing-content reasons visible.
- Treat deletion, moderation, authorization changes, and account revocation as
  core queue/reconciliation cases, not edge cases.

## How to choose the next FDP slice

Prioritize the smallest item that removes a common source of uncertainty:

1. Finish clear-storage, completion feedback, and browser QA.
2. Add explicit unavailable-route treatment and saved-content coverage clarity.
3. Implement drafts and their privacy lifecycle.
4. Design the shared Outbox before allowing any queued action.
5. Queue reactions, then threads/replies, only after identity, idempotency,
   and reconciliation guarantees are proven.

This ordering pursues broad offline usefulness first, while ensuring that
participation parity never outruns trustworthiness.
