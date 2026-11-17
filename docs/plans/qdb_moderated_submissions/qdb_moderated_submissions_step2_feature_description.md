# QDB moderated submissions Step 2 feature description

> **Feature plan:** [Step 1](./qdb_moderated_submissions_step1_solution_assessment.md) · [Step 2](./qdb_moderated_submissions_step2_feature_description.md) · [Step 3](./qdb_moderated_submissions_step3_development_plan.md) · [Step 4](./qdb_moderated_submissions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/qdb_moderated_submissions`; [host ledger](../effort_ledger/qdb_moderated_submissions.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.60**

Approved direction: Step 1 Revision 1, Option B (approved 2026-11-17). Pending quotes are ordinary records, listed on a public queue page and left out of the other views; an operator approves them with a tag.

## Problem

Every qdb submission, including anonymous ones, appears on Latest at once. qdb.us originally showed new quotes only on its public queue until a moderator approved them.

## User stories

- As a visitor, I want my submitted quote to land in the queue with a clear "pending approval" note so that I know it was received.
- As a reader, I want Latest, Top, 1337, Random and search to show only approved quotes so that the main pages stay curated.
- As a reader, I want a queue page of pending quotes so that I can see and vote on what is waiting.
- As an operator, I want to approve or reject a quote from the queue so that I can moderate without SSH.

## Core requirements

- With the flag on, a new quote from anyone but an operator is pending. Operator quotes and `news` posts publish approved.
- Pending quotes appear only on the queue page (`/queue` and `/?queue`) and their own permalink, which marks them pending. Every other listing, count, static page and offline snapshot leaves them out.
- An operator's `approved` tag moves a quote out of the queue and into the normal views. Tags from anyone else have no effect on approval.
- An operator's `rejected` tag removes a quote from the queue and every view. Deleting the record and purging history stay with the existing `delete-record` command.
- Quotes that existed before the flag was turned on count as approved, without rewriting their records.

## Delivery scope

Work type: application change (qdb listing rules, queue page, approve and reject controls, footer counts, one feature flag, documentation and concept entry).

## Completion boundary

- Normal entry: qdb Add page and `/api/create_quote` for submitters; the queue page for readers and operators.
- End-to-end: a quote is submitted, appears on the queue but not on Latest, an operator approves it, and it appears on Latest at its number.
- Recovery: a failed approve or reject leaves the quote pending and shows the error; the operator retries. Turning the flag off makes every quote visible as today.
- Release condition: the flag ships off. The maintainer turns it on for qdb.us after trying it.

## Known user or environment

qdb.us on the shared host `pdx1-shared-a2-13`, maintained over SSH as user `qdb`. Entry: the maintainer enables the flag, submits a test quote anonymously, then approves it from the queue while signed in as an operator. Failure handled: the quote shows on Latest before approval, or approval does not move it. Verification: the quote is on `/queue` and absent from `/latest` before approval, then the reverse. Evidence: the two pages on qdb.us before and after; a local test run alone does not satisfy this.

## Risks

- **Existing quotes**: the archive holds thousands of imported quotes with no approval tag. Impact: they would vanish into the queue. Validate first by choosing how "existing" is decided (flag enable time or a recorded cutoff). Mitigate by testing against an imported-archive fixture.
- **Missed views**: some surface may bypass the shared qdb listing query. Impact: a pending quote shows early, a visual bug rather than a leak. Validate by listing every reader of qdb threads before Step 3 stages. Mitigate with one shared "listed" rule used by all of them.
- **Approval authority**: anyone can apply tags today. Impact: a visitor could approve their own quote. Mitigate by counting `approved` and `rejected` only from current operators, as resolution marks count only approved keys.
- **Spam in Git**: rejected quotes remain in history until `delete-record` runs. Accepted per the retraction direction; no mitigation this cycle beyond the `rejected` hide.

## Shared component inventory

- `src/ForumRewrite/ReadModel/QdbPage.php`: Latest, Top, 1337 and search share the `qdb_page_eligible` rule; extend it with the pending rule and add a `queue` section.
- `src/ForumRewrite/Qdb/QdbExperience.php` and `QdbBoardPolicy.php`: route table, Random and news; add the queue route and reuse the same rule.
- `templates/partials/qdb_footer.php`: total count; extend with real "approved; pending" counts.
- `src/ForumRewrite/Http/TagApiController.php` and the reaction records: reuse for `approved` and `rejected`; no new write path.
- `src/ForumRewrite/Security/ApprovalPolicy.php` `isCurrentOperator`: the one operator check for counting approval tags.
- `qdb_quote_actions.php` and the quote card: reuse for queue entries, adding approve and reject for operators only.
- `PublicOfflineSnapshotBuilder.php` and `StaticArtifactBuilder.php`: consumers that must apply the same listed rule.
- `FeatureFlagRegistry.php`: one new flag. The private `/staged/` store is not used.

## User flow

1. A visitor submits a quote on Add.
2. The page confirms it is pending and links to its permalink and the queue.
3. Readers see it on the queue and may vote.
4. An operator presses Approve on the queue.
5. The quote leaves the queue and appears on Latest.

## Success criteria

- With the flag on, a new anonymous quote is on `/queue` and absent from Latest, Top, 1337, Random, search, the footer's approved count and the offline snapshot.
- After an operator approves it, it is on Latest and absent from the queue. A non-operator's `approved` tag changes nothing.
- A rejected quote is absent from the queue and all listings.
- Every quote that existed before the flag was enabled still appears on Latest.
- With the flag off, qdb behaves as on `main`.

Concepts touched: [Tagset](../../concepts/tagset.md) (operator-only approval tags), [Static publication](../../concepts/static_publication.md) (listed rule). Proposed inventory addition: "Quote queue" (pending qdb quotes, public on one page, approved or rejected by operator tags).

Enhancement requests: none pending. The classic `/queue` URL and footer counts from Step 1 are in scope.

Change from Step 1 defaults: rejection is now an operator `rejected` tag rather than immediate deletion, because no web deletion path for operators exists. Deletion stays with `delete-record`.

## Study snapshots

- Step 2 handoff, 2026-11-17 UTC: 0.60 projected total reaffirmed for scope v2. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
