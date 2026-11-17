# QDB moderated submissions Step 3 development plan

> **Feature plan:** [Step 1](./qdb_moderated_submissions_step1_solution_assessment.md) · [Step 2](./qdb_moderated_submissions_step2_feature_description.md) · [Step 3](./qdb_moderated_submissions_step3_development_plan.md) · [Step 4](./qdb_moderated_submissions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/qdb_moderated_submissions`; [host ledger](../effort_ledger/qdb_moderated_submissions.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.65**

Approved: Step 2 (2026-11-17). Concepts touched: [Tagset](../../concepts/tagset.md), [Static publication](../../concepts/static_publication.md), [Theme navigation icons](../../concepts/theme_navigation_icons.md); new "Quote queue" entry in Stage 6.

## Completion Contract

- Normal entry: qdb Add page and `/api/create_quote` for submitters; `/queue` and `/?queue` for readers and operators.
- End-to-end: a quote is submitted, shows on the queue and not on Latest, an operator presses Approve, and it shows on Latest at its number.
- Recovery: a failed Approve or Reject leaves the quote pending with a visible error, and the operator retries. Turning the flag off shows every quote as today.
- Deployment: no read-model schema change; the rule reads existing tag rows. One new private-config setting, the queue start time.
- Release: flag ships off. The maintainer enables it on qdb.us and replays the Step 2 check there (Stage 6).

## Key Risks

- **High risk:** existing quotes. Impact: the imported archive disappears into the queue. Validation: Stage 1 tests with an archive fixture. Mitigation: quotes created before the recorded queue start time count as approved, and the flag has no effect until that time is set.
- **High risk:** approval authority. Impact: a visitor approves their own quote. Validation: Stage 1 test with a non-operator `approved` tag. Mitigation: count `approved` and `rejected` only from keys that `ApprovalPolicy::isActiveOperator` accepts.
- Missed views. Impact: a pending quote shows early; a visual bug, not a leak. Validation: Stage 1 lists every qdb thread reader. Mitigation: one shared "listed" rule used by all of them.
- Query cost. Impact: slower Latest on the shared host. Validation: time Latest on a 20,000-quote fixture in Stage 1. Mitigation: fall back to a read-model column with the full schema-change checklist, which needs a Step 3 revision.

## Stage 1

**Estimate: 0.15**

- Goal: pending quotes leave Latest, Top, 1337, search, Random and news.
- Dependencies: none.
- Expected changes: new flag `FORUM_QDB_QUEUE_ENABLED` and private-config setting `QDB_QUEUE_STARTED_AT`. A new `QdbModeration` class with `state(threadRow): pending|approved|rejected` and a matching SQL predicate. A quote is pending when the flag is on, it was created at or after the start time, its author is not an operator, it has no `news` tag, and no operator has tagged it `approved` or `rejected`. `QdbPage::page`, `QdbExperience::random` and `QdbBoardPolicy::newsThreads` apply the predicate.
- Verification approach: new `QdbModerationTest` (operator versus visitor tags, cutoff, news, flag off), existing QDB listing tests, and timing on a large fixture. `./v3 test --smoke` plus those classes.
- Estimate details: driver: one rule shared by SQL and PHP callers; uncertainty: how tag rows record the tagger.
- Risks or open questions:
  - Impact: stale cutoff semantics if the flag is toggled. Early warning: test flag off, then on. Mitigation: the start time, not the flag, decides which quotes are new.
  - Impact: other readers of qdb threads exist. Validation: grep every caller of `ThreadRepository` on qdb and record them in the Step 4 summary.
- Canonical components/API contracts touched: `src/ForumRewrite/ReadModel/QdbPage.php` (`qdb_page_eligible`), `src/ForumRewrite/Qdb/QdbExperience.php`, `QdbBoardPolicy.php`, `FeatureFlagRegistry.php`, `ApprovalPolicy::isActiveOperator`, `Support/PrivateConfigSchema.php`.

## Stage 2

**Estimate: 0.12**

- Goal: a public queue page lists pending quotes, newest first, with voting.
- Dependencies: Stage 1.
- Expected changes: `queue` section in `QdbPage::page`; `/queue`, `/queue/N` and `/?queue` routes in `QdbExperience`; a Queue entry in the qdb navigation, shown only with the flag on, with icons in both sets of `theme-nav-icons.css`. Reuse the board template and quote card.
- Verification approach: route tests for both URL forms and pagination; `NavIconSetsTest`; visual check of the nav icon in qdb, Word97, Chicago, Midnight, Gloss and Engagify themes.
- Estimate details: driver: navigation and icons across themes; uncertainty: whether qdb navigation uses the shared icon mapping.
- Risks or open questions: Impact: an empty queue looks broken. Mitigation: reuse the board's existing empty state.
- Canonical components/API contracts touched: `QdbPage.php`, `QdbExperience.php`, `PresentationSlotRegistry` qdb navigation, `public/assets/theme-nav-icons.css`, board template and quote card.

## Stage 3

**Estimate: 0.08**

- Goal: submitters and permalink readers see that a quote is pending.
- Dependencies: Stage 1.
- Expected changes: after Add, the confirmation says the quote awaits approval and links to its permalink and the queue. `/api/create_quote` returns a `Moderation: pending` line. The permalink shows one short "pending approval" marker; a rejected quote's permalink returns 404 to non-operators.
- Verification approach: compose controller and API tests; permalink test for each state.
- Estimate details: driver: two submission responses; uncertainty: low.
- Risks or open questions: Impact: extra copy against the UI copy rule. Mitigation: one marker, no explanation text.
- Canonical components/API contracts touched: `ComposeAndAccountKeyController.php`, `WritePostAndIdentityApiController.php`, `ThreadAndPostPageController.php`, `docs/reference/agent_api.md`.

## Stage 4

**Estimate: 0.12**

- Goal: operators approve or reject from the queue.
- Dependencies: Stages 1 and 2.
- Expected changes: Approve and Reject buttons on queue cards, decided in the client before paint for operators only. They apply signed `approved` or `rejected` thread tags through the existing tag API and browser signing. Success removes the card; failure keeps it and shows the error.
- Verification approach: tag API tests for operator and non-operator; browser check of approve, reject and a forced failure.
- Estimate details: driver: client-side operator detection without changing static HTML; uncertainty: how operator status reaches the browser today.
- Risks or open questions: Impact: buttons flash for non-operators. Mitigation: hide by default; reveal only after the viewer check, per the first-paint rule.
- Canonical components/API contracts touched: `templates/partials/qdb_quote_actions.php`, `TagApiController.php`, `public/assets/browser_signing.js`.

## Stage 5

**Estimate: 0.08**

- Goal: counts and published copies follow the rule.
- Dependencies: Stage 1.
- Expected changes: the footer shows "N quotes approved; M pending" with the flag on. The offline snapshot, static artifacts and llms.txt listings use the Stage 1 predicate.
- Verification approach: footer test; `PublicOfflineSnapshotBuilder` and static build tests with a pending fixture.
- Estimate details: driver: several builders with their own queries; uncertainty: which builders read qdb listings.
- Risks or open questions: Impact: a builder bypasses the rule. Mitigation: test each builder with a pending fixture.
- Canonical components/API contracts touched: `templates/partials/qdb_footer.php`, `BoardPageController.php`, `PublicOfflineSnapshotBuilder.php`, `StaticArtifactBuilder.php`.

## Stage 6

**Estimate: 0.06**

- Goal: document the feature and replay the Step 2 check on qdb.us.
- Dependencies: Stages 1–5.
- Expected changes: "Quote queue" concept entry; flag and setting in the operator docs; update the classic URLs and theme fidelity checklists to mark Queue and the pending count done.
- Verification approach: link check and `git diff --check`. On qdb.us, the maintainer sets the start time, enables the flag, submits an anonymous test quote, confirms `/queue` but not `/latest`, approves it, and confirms the reverse. Evidence: the maintainer's report of both pages; local runs do not satisfy this.
- Estimate details: driver: production replay needs the maintainer; uncertainty: deploy timing.
- Risks or open questions: Impact: replay cannot run in this session. Mitigation: record it as pending in the Step 4 summary rather than complete.
- Canonical components/API contracts touched: `docs/concepts/`, `docs/plans/qdb_classic_urls_checklist.md`, `docs/plans/qdb_theme_fidelity_checklist.md`.

## Study checkpoint

- Step 3 handoff, 2026-11-17 UTC: projected total 0.60 → **0.65**, same scope v2. New understanding: queue navigation needs icons in both sets, and operator controls need pre-paint viewer detection. Stage work 0.15/0.12/0.08/0.12/0.08/0.06, independent and not summed. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
