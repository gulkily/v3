# QDB moderated submissions Step 1 solution assessment

> **Feature plan:** [Step 1](./qdb_moderated_submissions_step1_solution_assessment.md) · [Step 2](./qdb_moderated_submissions_step2_feature_description.md) · [Step 3](./qdb_moderated_submissions_step3_development_plan.md) · [Step 4](./qdb_moderated_submissions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/qdb_moderated_submissions`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.60 (Revision 1; was 0.70)

## Original Query

Claude, we need to return qdb to its roots of being moderator-approved content only. Please use Railspike for this one.

## Understood Intent

On qdb.us, a submitted quote should stay out of public view until a moderator approves it, as on the original bash.org and qdb.us queue. Today every submission publishes at once, including anonymous ones through `/api/create_quote`. Earlier qdb work skipped the classic Queue because the app had no pre-publish moderation ([classic URLs checklist](../qdb_classic_urls_checklist.md), [theme fidelity checklist](../qdb_theme_fidelity_checklist.md) footer note).

## Problem

Any visitor can publish a quote to qdb immediately, and nothing lets a moderator review it first.

Concepts touched: [Staged submission](../../concepts/staged_submission.md) (Option A extends it), [Post retraction](../../concepts/post_retraction.md) (Option B depends on it for rejections), [Viewer session](../../concepts/viewer_session.md) (operator check). Option A proposes a "quote queue" entry, or a broader staged-submission entry.

## Option A — Hold submissions in a private queue until approved
**Estimate: 0.70** — driver: approve/reject flow and moving unsigned anonymous quotes into a store built for signed posts; uncertainty: when quote numbers are assigned.

New quotes from anyone except an operator go to a private queue, like the existing staged posts at `/staged/`. They never enter the records tree, Git history, the read model, static pages or exports. An operator sees the queue, approves a quote (which publishes it through the normal write and commit) or rejects it (which deletes it). The submitter sees "submitted for approval" instead of the quote page.

- Matches the original: unapproved text is never public anywhere, and the footer can show real "approved; pending" counts.
- A rejection leaves nothing to retract or purge from history. This fits the [retraction simplification direction](../retraction_simplification_handoff.md).
- Reuses `StagedSubmissionStore`, the `/staged/` page and its operator view rather than a new mechanism.
- The store today holds signed posts and limits each author to 20. Anonymous quotes need a shared cap and possibly rate limiting.
- Pending quotes live only in private server state, not Git, so a lost host loses the queue.

## Option B — Publish to records hidden, approve with a tag
**Estimate: 0.55** — driver: filtering every read surface; uncertainty: which surfaces bypass the board query.

Write the quote as today, but hide it from public pages until an operator applies an `approved` tag. The existing `is_hidden` read-model field carries the state.

- Reuses records, reaction tags and Git backup; the queue survives host loss.
- Unapproved text still enters Git, exports, mirrors and history. Each rejection then needs a retraction and a later history purge.
- Every surface must filter it: board, search, random, permalinks, static pages, llms.txt, feeds and counts. One miss leaks unapproved content.

## Option C — Only approved members may submit
**Estimate: 0.15** — driver: turning off anonymous quote creation; uncertainty: low.

Turn off anonymous submission on qdb and let only approved keys post.

- Smallest change, built on existing member approval.
- This moderates people, not quotes. It ends anonymous submission, which was qdb's own roots.

## Recommendation (original, superseded by Revision 1)

Option A. It is the only option where unapproved quotes are never public, and it builds on the staged-submission store and page already in the code. A vertical slice is viable: a qdb submission goes to the queue, an operator approves or rejects it at the queue page, and the approved quote appears on the board. Gate it behind a feature flag so it can be turned off in production.

Questions for Step 2, with proposed defaults:

- Scope: new quotes only. Existing quotes stay published. Replies, votes and flags stay as they are.
- Operators and `news` posts skip the queue.
- The quote number is assigned at approval, so the public sequence has no gaps.

Enhancement requests: a classic `/queue` URL and "approved; pending" footer counts follow naturally from Option A. They belong in Step 2 scope only if the maintainer wants them in this cycle.

## Revision 1 — public queue

Maintainer, 2026-11-17: "so qdb had a public queue, it just didn't show the quotes in most views, but they were visible in the queue page. we don't have to keep them secret, just out of view in most views."

Revised direction: pending quotes are public on a queue page and absent from the other views. Secrecy is no longer a goal, so Option A's main advantage falls away and Option B's main cost shrinks.

### Option B, revised — Publish pending, list on `/queue`, approve with a tag
**Estimate: 0.60** — driver: the queue page and keeping pending quotes out of every other listing; uncertainty: which views bypass the shared listing query.

Write each new quote to the records and Git as today, marked pending. The board, Top, 1337, Random, search, news, static pages and counts leave pending quotes out. A public `/queue` page (and `/?queue`) lists them. An operator approves a quote by applying an `approved` tag, which is an ordinary signed tag record, so approvals sit in Git history.

- Matches the original queue, including the "approved; pending" footer counts.
- No new storage: records, tags and Git backup already exist, and the queue survives host loss.
- Many views already skip hidden posts through `posts.is_hidden`, which gives a starting point for the filter.
- A rejected quote is still in Git. Rejection becomes an ordinary deletion commit with a later history purge, per the [retraction simplification direction](../retraction_simplification_handoff.md). Spam volume makes that cost real.
- A missed view shows a pending quote early. Since the queue is public, that is a visual bug, not a leak.

### Option A, revised — Private store with a public queue page
**Estimate: 0.75** — driver: as before, plus rendering staged items as public quote cards.

Keep pending quotes outside the records, as in the original Option A, but list them publicly on `/queue`.

- Rejected spam never reaches Git.
- Public quote cards would be drawn from a store built for private `/staged/` drafts, a second rendering path for the same thing.
- Pending quotes still live only in private server state.

Option C is unchanged and still moderates people rather than quotes.

### Recommendation, revised

Option B, revised. The quote queue becomes ordinary records with a pending state, which keeps everything in Git and reuses the tag and listing code already in place. A vertical slice: a qdb submission appears on `/queue` but not on the board, an operator approves it, and it appears on Latest. Gate it behind a feature flag.

Questions for Step 2, with proposed defaults:

- Scope: new quotes only. Existing quotes count as approved.
- Operators and `news` posts publish approved.
- Rejection deletes the quote through the existing delete path; no separate `rejected` state.
- Votes and the permalink work on pending quotes, as on the original queue. The permalink marks the quote as pending.
- The quote number is assigned at submission, as today, so a pending quote already has its permalink. Rejected numbers leave gaps, as on the original.

*— Revised by Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.70 projected total for the recommended scope. Baseline `6bfe124310aa6c57a6d490241c02efada1c790c4`, scope v1, R1, P2; Claude single exposed rater, 2026-11-13 UTC. R1 hashes not re-verified this session.
- Step 1 handoff: 0.70 for Option A. Options A 0.70, B 0.55, C 0.15; not summed.
- Revision 1 (2026-11-17 UTC, scope v2: public queue): 0.60 for revised Option B. Options A 0.75, B 0.60, C 0.15; not summed. Changed scope, not new understanding; original values retained above.

*— Claude · 2026-11-13 UTC · Model: Claude Opus 5.5*
