> **Feature plan:** [Step 1](./ui_first_post_identity_step1_solution_assessment.md) · [Step 2](./ui_first_post_identity_step2_feature_description.md) · [Step 3](./ui_first_post_identity_step3_development_plan.md) · [Step 4](./ui_first_post_identity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_first_post_identity` ([ledger](../effort_estimation_trial.md#cycle-v3ui_first_post_identity)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.80** (revision 2; was 0.85 in revision 1, 0.80 originally)

## Original Query

Please resume in @docs/plans/ui_redesign/implementation_checklist.md, what's next?

Go ahead, please.

So the key is used not just for writing, but also for signing votes, so we cannot wait to publish it, it has to be published right away, as it already should be if that feature is enabled. One thing we could do would be to hold unused keys in timeout and clean them up if not used, but of course, this can be problematic since they're already in the repo...

Hmm, the write lock is an issue

## Revision 2 — 2026-10-28: no registration per visitor

**Why.** Publishing for every visitor takes the write lock once per visitor (Option D). The lock should be taken only when someone actually acts.

**Findings.** A signed vote is a record plus a signature file, checked against `records/public-keys/openpgp-<fingerprint>.asc` (`ReactionSignature`). So a key has to be in the repository only by the time its first post or vote is written, not before. Today, with automatic guest keys on, the background check on pages with Like buttons already publishes every visitor's unused key, so the per-visitor lock exists now on those sites.

### Option G: The key is registered by its first action

**Estimate: 1.05**

The browser creates its key locally (on any page with automatic guest keys on, otherwise on the first composer focus or Like press) and does not publish it. The background check stops publishing unused keys. The first post, reply, Like or rename sends the public key along with the action. The server registers the key and records the action in one locked write and one commit, so a visitor who never acts never takes the lock or leaves files. Messages and invitations keep publishing first, as today. On the browser side: "Post as guest · Change" in composers, no dialogs anywhere, and "Creating your account…" with Retry and the draft kept.

- Pros: no per-visitor lock and no unused keys in the repository, so nothing to clean up; one round trip for the first action; votes work from the first press.
- Cons: the post, reply, reaction and rename write paths each gain an "unregistered key" branch. Combining them with registration needs its own failure and retry tests. Over 1.0, so it would need to be split.
- Driver: the server-side combined write across four paths. Uncertainty: how the incremental read-model update handles a new identity and its first record in one step.

### Option G1 + G2: The same, split into two releasable cycles

**Estimate: 0.80** (G1, this cycle) · **Estimate: 0.60** (G2, follow-up; revised from 0.55)

**G1 (this cycle):** all of the browser side of G. The browser creates its key locally, the background check stops publishing unused keys, and the inline name, no dialogs and "Creating your account…" with recovery are in. The first post, reply, Like or rename registers the key first and then acts, as today, but without dialogs. That leaves a one-time wait of one registration round trip. **G2 (follow-up cycle):** the server accepts the key with the first action, which removes that round trip.

**G2 request shape (user decision, 2026-10-28):** a first post, reply or rename keeps its two requests. The prepare request also sends the public key and returns the bootstrap record to sign; the create request carries both signatures. A first Like or vote is **one request**: the browser builds and signs the bootstrap record itself, and the server checks it (fields, time skew, fingerprint) before writing. Each first action is one locked write and one commit. Estimate for G2 revised from 0.55 to **0.60** for browser-built bootstrap records and the server's checks of them.

- Pros: each cycle fits; G1 already removes the per-visitor lock and every dialog; G2 changes only the server and the request payload.
- Cons: until G2 ships, the first action waits once for registration.

### Revised recommendation

**G1 now, G2 next.** Together they make up Option G. Neither needs a cleanup job or a history purge, and both take the write lock only for visitors who act. D, E and F (revision 1) are superseded: D and E register every visitor, and F's server-only key store is larger than G for the same result. G1 is a releasable slice: normal entry is any composer or Like button as a new visitor with JavaScript, the outcome is a first post or vote under the chosen name or `guest` with no dialogs, and recovery is Retry with the draft kept (anonymous posting stays). It applies to all sites. New branch from `main`.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Revision 1 — 2026-10-28: publish right away (superseded by revision 2)

**Why.** Votes are signed with the same key, so a key cannot sit unpublished until the first post. Options A and B below are superseded; C is kept for comparison.

**What exists today.** With automatic guest keys on, the browser makes a `guest` key on any page. It publishes the key immediately only if the per-browser "Publish immediately" setting on Account is on; otherwise publishing waits. Pages with Like buttons publish a saved key in the background anyway. Publishing writes four files in one commit (public key, identity record, a signed bootstrap post and its signature) under `records/`. Unapproved profiles show in the Users page's pending list, so an unused guest key adds a row there.

### Option D: Publish right away; keep unused keys and hide them

**Estimate: 0.85**

When a key is created (automatically on any page with automatic guest keys on, otherwise on the first focus of a composer or the first Like press), it is published at once, with no setting. Composers show "Post as guest · Change": naming an unused guest key renames it (cycle 4's path) instead of making a second key. Post only signs and sends, and a Like waits only if the key is still being created. No dialogs anywhere. Guest keys with no posts or votes are left out of the pending list and counts.

- Pros: meets "Post only signs and sends"; votes always have a published key; no deletion problem; reuses automatic guest keys and cycle 4's naming.
- Cons: the repository grows by four small files and a commit for every visitor whose browser runs JavaScript, including ones who never act. Each registration also takes the write lock briefly.
- Driver: making publishing immediate, renaming before the first post, and the pending-list filter. Uncertainty: visitor volume against registration cost on ZenMemes.

### Option E: D, plus a timed cleanup of unused keys

**Estimate: 1.10**

Like D, but a scheduled job deletes guest keys that stay unused for some days, removing their four files in a commit. The browser republishes its key if it comes back later. Removing them from Git history waits for the history purge planned in [retraction simplification](../retraction_simplification_handoff.md).

- Pros: the working tree stays small.
- Cons: the files stay in history until a purge exists, which hasn't been built yet. A returning visitor's first action races the deletion. Archives and offline copies keep the files. Over 1.0, so it would need to be split.
- Driver: deleting safely and re-registering. Uncertainty: depends on purge work that isn't scheduled.

### Option F: The server knows the key before the repository does

**Estimate: 1.25**

Publishing stores the key only in a server table that is not part of the repository, which is enough to verify votes and posts. The key's four files are committed together with its first post or vote. Unused keys simply expire from the table, so there is nothing to purge.

- Pros: no clutter and no cleanup; still a single round trip.
- Cons: every signed write path (posts, votes, messages, invitations, renames) must commit the identity on first use. Needs a new store, it has to survive read-model rebuilds, and the work is large. Over 1.0, so it would need to be split.
- Driver: the first-use commit across write paths. Uncertainty: high.

### Revised recommendation

**Option D.** It follows the rule that keys are published right away, removes every dialog and makes Post only sign and send. Its cost is repository growth from unused keys. If that becomes a problem, E's cleanup or F's deferred commit can be a separate later cycle. E is better once the retraction history purge exists. D is a releasable slice: normal entry is any page or composer as a new visitor with JavaScript, the outcome is a published key and a first post or vote under the chosen name or `guest`, and recovery is Retry with the draft kept (anonymous posting stays). It applies to all sites. New branch from `main`.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Original assessment (superseded options A and B)

## Understood Intent

Start cycle 5 of the [UI redesign checklist](../ui_redesign/implementation_checklist.md#cycle-5-ui_first_post_identity). The goal is for a new visitor to write and publish a first post without browser dialogs or a long wait after pressing Post. Today Post opens `window.prompt("What should we call you?")`, then a `window.confirm` if they cancel, then "Generating browser keypair…" and "Publishing your public key…" (reported on the board as "new account bootstrapping is unacceptably slow"). Cycle 4 already made naming on the account page prompt-free and settled that keys are cheap and the user is the name.

## Problem

A first post goes through browser dialogs and then key setup only after the person presses Post. Publishing the key is a signed registration that writes records and refreshes the site's read model, so it cannot be done for every visitor who merely opens a composer.

## Option A: Make the key ready when the composer opens, publish it when Post is pressed

**Estimate: 0.80**

When a composer gets its first focus, the browser loads OpenPGP and makes an unpublished `guest` key in the background. Composers show a "Post as guest · Change" row. Changing the name before the first post just makes a new unpublished key with that name, which is cheap and leaves nothing behind. Post publishes the key and then signs and sends, showing "Creating your account…" with Retry and the draft kept if it fails. Every signed action stops prompting and uses the chosen name or `guest`.

- Pros: no dialogs; nothing is registered for visitors who never post; library load and key creation happen while the person is typing.
- Pros: reuses the existing automatic-guest path (`scheduleAutomaticGuestIdentity`, deferred publication) and cycle 4's naming.
- Cons: the first Post still waits for one registration round trip. That is about 1.3 s on QDB-scale data after the registration fixes, and less on ZenMemes, but it is not just signing.
- Driver: the key and name states across the board quick compose, the New post page and the reply form, plus the failure and retry paths. Uncertainty: the real ZenMemes registration time on a phone.

## Option B: Make the key on open, publish once the person starts writing

**Estimate: 0.85**

Same as A, but the key is published as soon as there is real intent (text in the body or a chosen name), so Post only signs and sends.

- Pros: meets "Post only signs and sends" literally.
- Cons: abandoned drafts leave registered identities, each with its bootstrap records and a site-wide refresh. That clutters the users list and adds write load.
- Cons: a name change after publishing needs a rename record or leaves an orphan key.
- Driver: name changes after publishing. Uncertainty: how many drafts are abandoned.

## Option C: Register the key together with the first post

**Estimate: 1.20**

The first post's request carries the public key, and the server registers the identity and accepts the post in one locked write. The browser side matches A.

- Pros: one round trip and no orphan registrations.
- Cons: a new server write path that combines identity registration and posting, with its own failure recovery and tests. Over 1.0, so it would need to be split.
- Driver: server-side combined write and lock handling. Uncertainty: high.

## Recommendation

Option A. It removes every dialog and moves library loading and key creation off the Post press, while registering only people who actually post. The remaining wait is one registration, shown as "Creating your account…" with recovery. If that wait is still too long on a real phone, a later cycle can choose C. A is a releasable slice: normal entry is any composer as a new visitor with JavaScript, the outcome is a published first post under the chosen name or `guest`, and recovery is Retry with the draft kept (anonymous posting stays available). All sites, as in cycle 4, since the prompt is shared code. New branch from `main`.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Add a note to the checklist that cycle 3 is paused | Separate small edit, done with this assessment (uncommitted) | Requested 2026-10-28 | Done in the checklist |
| Publish the key right away (votes use it); maybe clean up unused keys after a timeout | Changed scope inside Step 1: revision 1 adds D/E/F; cleanup is option E, recommended as a later cycle | Pending Step 1 approval | Revision 1 above |
| The write lock is an issue | Changed scope inside Step 1: revision 2 adds G and the G1 + G2 split; D/E/F superseded | Pending Step 1 approval | Revision 2 above |
| Where are keys stored; same commit means same request? First vote in 1 request | Clarification, then a G2 design decision recorded under G1 + G2; does not change G1 | Decided by the user 2026-10-28 | G2 request shape above |

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Effort study

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-10-28T18:13:41Z | 0.75 | v1 / R1 / P2, baseline `main` `74d044c1f` | Claude, single exposed rater; authored the checklist item and mockup, built cycle 4's naming; no code inspected for this cycle before rating | Checklist cycle 5 as written |
| Step 1 handoff | 2026-10-28 | 0.80 (Option A) | v1 / R1 / P2 | Same, after reading the compose-identity and automatic-guest paths in `browser_signing.js`, `lazy_compose_signing.js`, the thread compose form and the registration cost notes | Three composers and the shared prompt callers (likes, messages, invites) add states; reuse of automatic guest offsets it |
| Step 1 revision 1 handoff | 2026-10-28 | 0.85 (Option D) | v1 → v1.1 (publish right away; A/B superseded) / R1 / P2 | Same, plus the reaction identity path, the registration write and the pending-list query | User correction: votes need a published key. Immediate publishing, renaming before the first post and the pending-list filter add a little; publishing no longer waits at Post. D/E/F 0.85/1.10/1.25, not summed |
| Step 1 revision 2 handoff | 2026-10-28 | 0.80 (G1); G2 follow-up 0.55; whole G 1.05 | v1.1 → v1.2 (no per-visitor registration; split into G1 + G2) / R1 / P2 | Same, plus `ReactionSignature` key lookup and the vote prewarm publishing path | User correction: the write lock rules out per-visitor publishing. Scope change, not new understanding; G1 is cycle 5, G2 a proposed follow-up cycle with its own ID. Not summed |
| G2 note | 2026-10-28 | G1 0.80 reaffirmed; G2 0.60 (from 0.55) | v1.2 / R1 / P2 | Same, plus the reaction and post request paths | User chose one request for a first vote (browser-built bootstrap record); G2 only |

Option estimates are separate alternatives and are not summed. R1 hashes verified 2026-10-28T18:13:41Z.

## Follow-up API note — 2026-10-30

[Single-request posting](../single_request_posting/single_request_posting_step4_implementation_summary.md) adds an agent endpoint bundling a separate signed bootstrap with the ordinary post. It does not implement G2 browser or vote integration or change this cycle’s historical decisions.
