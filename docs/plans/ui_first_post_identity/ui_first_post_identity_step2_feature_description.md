> **Feature plan:** [Step 1](./ui_first_post_identity_step1_solution_assessment.md) · [Step 2](./ui_first_post_identity_step2_feature_description.md) · [Step 3](./ui_first_post_identity_step3_development_plan.md) · [Step 4](./ui_first_post_identity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_first_post_identity` ([ledger](../effort_estimation_trial.md#cycle-v3ui_first_post_identity)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

Cycle 5 of the [UI redesign checklist](../ui_redesign/implementation_checklist.md#cycle-5-ui_first_post_identity), [compose mockup](../ui_redesign/mockups/compose-thread.html). Step 1 approved 2026-10-28: Option **G1** (this cycle). G2, the server accepting the key with the first action (one request for a first vote), is a follow-up cycle.

## Problem

A first post goes through `window.prompt` and `window.confirm`, then key setup after Post. With automatic guest keys on, every reader's key is published in the background, so each visitor takes the write lock and leaves records, even if they never act.

## User stories

- As a new visitor, I want to write and post without browser dialogs, so that my first post feels like any other.
- As a new visitor, I want to see the name I'll post as and change it right there, so that I don't have to find Account first.
- As a reader who never posts or votes, I want my visit to leave nothing on the server.
- As someone whose first post fails, I want my draft kept and a Retry, so that I don't lose what I wrote.

## Core requirements

- **No browser dialogs** on any site for the first post, reply, Like or naming. The shared name prompt and its "Continue with 'guest'?" confirm are gone; an unnamed key posts as `guest`.
- **Local until first use.** The browser creates its key locally: on any page when automatic guest keys are on, otherwise on the first composer focus or Like press. Nothing is sent until the first post, reply, Like or rename. The background check on Like pages stops publishing unregistered keys; it still checks registered ones. The "Publish guest key immediately" setting on Account goes.
- **Name in the composer:** a "Post as guest · Change" row in every signed composer (new thread, reply, board quick compose) on every site, correct on first paint. Changing the name of an unregistered key replaces it locally with a key under that name, sending nothing. A registered key renames as on Account (cycle 4).
- **First action registers, then acts:** Post or Like shows "Creating your account…", registers once, then proceeds. Later actions only sign and send.
- **Recovery inline:** if key creation or registration fails, the message and Retry sit beside the composer or Like, with the draft kept. "Post anonymously" stays available.

## Delivery scope

- Work type: application change (browser identity scripts, composer and account templates, theme hooks and styles, tests, docs). This ledger's bookkeeping is included.
- Completion boundary: normal entry is any composer or Like button as a new visitor with JavaScript, on phone and desktop, on every site. The outcome is a first post or vote under the chosen name or `guest` with no dialogs, and no registration for readers. Recovery is Retry with the draft kept. The release condition is the checklist's every-cycle checks plus the success criteria below.
- Known environment: the report came from a phone. Entry is ZenMemes on iPhone Safari as a new visitor. Failure means a dialog appears, the wait after Post doesn't end, or an error has no Retry. Unaided, the person can press Retry or Post anonymously. Success is the post appearing in the thread. Evidence is a real-iPhone check; browser emulation alone doesn't satisfy this, so that check stays pending until done.

## Risks

- **Code that assumes a saved key is published** (account state, profile link, identity hint, toolbar name, private-site sign-in, Outbox sending). Impact: errors, or "waiting for approval" shown for a key the server doesn't know. Earliest validation: browser tests with an unregistered key on each surface. Mitigation: one shared "registered?" check, with an unregistered key shown as guest under its name.
- **Members-only sites need a registered key to sign in.** Impact: a visitor can't request access. Validation: the Lobby flow with no key. Mitigation: signing in and invitations stay explicit actions that register, as today.
- **First-action wait on a phone.** Impact: still feels slow. Validation: time the registration on ZenMemes-sized data in Stage 1. Mitigation: the visible state now; G2 removes the round trip.
- **Keys already published unused** stay. Impact: none new; no cleanup is in scope.

## Shared component inventory

- Identity preparation in `browser_signing.js` (`ensureReadyIdentity`, the prompt helpers, background check, automatic guest): extended in place; every signed action already goes through it.
- Composers: `thread_compose_form.php` (board quick compose, New post, QDB quotes), `reply_form.php`, Forte's `paned_board_compose_panel.php` and reply tree: one shared name row partial, not one per composer.
- Account page name editor and guest card (cycle 4): reused for the composer's Change, and shows an unregistered key as guest.
- Reaction feedback in `thread_reactions.js` and the composer status line: reused for "Creating your account…" and errors.
- Other prompt callers (messages, invitations, operator tools, Outbox): lose the prompt and register first as today; no new UI.

## User flow

1. A new visitor opens a ZenMemes thread; the browser creates a local `guest` key and sends nothing.
2. They tap Reply; the composer shows "Post as guest · Change".
3. They press Change, type "mouse", press Done; the local key is replaced by one named mouse.
4. They press Post: "Creating your account…", then the reply appears.
5. Later they Like a post; it signs and sends at once.
6. If step 4 fails: "Couldn't create your account. Retry", with the draft kept.

## Success criteria

- No `window.prompt` or `window.confirm` during a first post, reply, Like or naming on any site (browser tests).
- Reading the board and threads with JavaScript and automatic guest keys on makes zero registration requests and no repository commits.
- A first action makes exactly one registration; later actions make none.
- The name row is correct on first paint for guest, named-unregistered and registered keys, in every theme, on phone and desktop.
- Failure states show Retry, keep the draft, and recover on retry.

## Decisions for review

- **Every site** gets the composer name row (the prompt removal is shared code anyway). Default: yes, as in cycle 4.
- **Remove "Publish guest key immediately"** from Account, since it publishes unused keys. Default: remove it.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| G2: server accepts the key with the first action; first vote in one request | Separate follow-up cycle (Step 1 decision) | Decided 2026-10-28; not started | Pending |

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-10-28 | 0.85 (revised from 0.80) | v1.2 (G1) / R1 / P2, baseline `main` `74d044c1f` | Claude, single exposed rater; Step 1 inspection plus the account page, composer partials and the other prompt callers | New understanding: the account page's handling of unregistered keys, removing the publish setting, Outbox and members-only sign-in add work |

Earlier checkpoints are in [Step 1](./ui_first_post_identity_step1_solution_assessment.md#effort-study).
