> **Feature plan:** [Step 1](./private_message_local_pull_step1_solution_assessment.md) · [Step 2](./private_message_local_pull_step2_feature_description.md) · [Step 3](./private_message_local_pull_step3_development_plan.md) · [Step 4](./private_message_local_pull_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_message_local_pull`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.80

## Original Query

"Can we please talk about how to sync private messages from public instance to localhost?" After discussing three approaches: "go with option 2 plus the approved-second-key trick."

## Understood Intent

A member pulls their own private messages from zenmemes.com into a local clone and reads them there. Only that member's mail moves, never other members' mailboxes. The local browser holds its own key, approved on zenmemes.com under the same username. Existing history sync on zenmemes.com then gives that key access to older messages. The pull copies the original envelopes and those transfers without changing them. The public part still arrives through `import-instance`.

Facts from main `6bdc52d7`:

- Envelopes and read state live in `state/private/messages.sqlite3`. Transfers live in `state/private/message_history_sync.sqlite3`. Neither file is in the content archive.
- Member endpoints already return conversations, inbox, sent and per-message transfers. They return pages, not a complete export, and fetch transfers one message at a time.
- No import path stores a fetched envelope under its original `message_id`. Sending creates a new message.
- [Browser key backups](../../reference/key_backups.md) can already put the zenmemes.com key itself into the local browser. That works today, but it copies a private key, which the second-key approach avoids.

## Problem Statement

A member cannot read their zenmemes.com private messages in a local clone, because private storage never leaves the server and no path imports it.

## Concepts touched

- All options: [private-message history](../../concepts/private_message_history.md) (used unchanged on zenmemes.com; the local clone reads transfers it did not create). [Approval acceptance](../../concepts/approval_acceptance.md) (used unchanged: the local key is approved like any second key).
- Options A and B: proposed *participant mailbox export*, a member-only endpoint that returns the member's envelopes and transfers to their keys, in complete resumable batches.
- Option A: [local development import](../../concepts/local_development_import.md) (changed: gains a messages pull).
- Option C: proposed *mailbox file*, a downloaded file of the same content.

## Solution Options

### Option A: Pull button on the local development page
**Estimate: 0.80**
Driver: signing in to zenmemes.com from localhost without sending the private key anywhere. Uncertainty: how the local server borrows a session the browser created.

The local development page gains **Pull my messages from zenmemes.com**. The local browser signs in to zenmemes.com with its approved key. The local server then fetches the participant mailbox export and stores each envelope and transfer under its original identity. Running it again fetches only what is new.

- Pros: one click, next to the existing public import, with the same loopback guard. Repeat pulls do not duplicate messages. The private key never leaves the browser.
- Cons: needs the new export endpoint on zenmemes.com and a cross-instance sign-in. Works only while zenmemes.com is reachable. No schedule yet.

### Option B: CLI pull with a GPG key
**Estimate: 0.65**
Driver: the raw sign-in flow for members-only access. Uncertainty: whether the CLI key and the browser key should be the same key.

`./v3` gains a pull command that signs in with a GPG key, the same way Quill signs posts, and imports the same export. Cron can run it.

- Pros: can be scheduled; fits agents; reuses the export endpoint.
- Cons: reading still needs a browser key with access, so the member manages two keys or copies one. More setup than a button.

### Option C: Download a mailbox file, import it locally
**Estimate: 0.45**
Driver: a bounded, verifiable file format. Uncertainty: file size for long histories.

Messages on zenmemes.com offers **Download my mailbox**. The file holds the member's envelopes and transfers, all still encrypted. Importing the file on localhost adds what is missing.

- Pros: no live link between instances and no cross-instance sign-in. The file is inspectable and doubles as a personal backup.
- Cons: manual each time, with no incremental pull. The file reveals who the member talks to and when, so it needs careful handling.

## Recommendation

**Option A**, built on the participant mailbox export, so Option B can reuse the endpoint later. Keep the pull one-way: messages sent from localhost stay local. Defer scheduling and the CLI.

**Vertical-slice viability:** approve the local browser key on zenmemes.com, then run the public import so localhost learns the approval. Visit zenmemes.com once with an existing device so history sync covers the new key. Press **Pull my messages** on localhost. The local Messages page then shows the same conversations, with original senders verified. A second pull adds only new messages. Step 2 should settle the export's batch limits, what the pull does with messages it cannot yet decrypt, and whether read state comes along.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Record the missing participant mailbox export, and the missing original-identity import, in the list of APIs that should exist. Disposition: proposed above, pending approval.

## Study Checkpoint

2026-11-17T06:10Z; scope v1; starting revision `6bdc52d7`; reference R1 (hashes not re-verified this session); P2 standing enrollment, no parent. Claude, single exposed rater. No pristine intake: messaging code and plans were inspected before rating.

- Intake after inspection and Step 1 handoff: **0.80 projected total**, Option A. Alternatives B 0.65 and C 0.45, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.
- Ledger update pending: the ledger entry for this cycle is not yet written, because that edit is outside this step's scope.
