> **Feature plan:** [Step 1](./thread_activity_drawer_step1_solution_assessment.md) · [Step 2](./thread_activity_drawer_step2_feature_description.md) · [Step 3](./thread_activity_drawer_step3_development_plan.md) · [Step 4](./thread_activity_drawer_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_activity_drawer`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.55**

## Completion Contract

- **Normal entry:** a reader opens `/threads/<id>` with `FORUM_THREAD_ACTIVITY_DRAWER_ENABLED` on.
- **End-to-end outcome:** tag-only leaf replies leave the conversation. A collapsed "Activity (N)" drawer above the reply box lists them, plus likes and flags by post, with links.
- **Recovery:** if building the drawer throws, the page renders as today: all replies in place, no drawer. With the flag off, the HTML is unchanged.
- **Deployment:** no schema change, no rebuild, no new dependency. The maintainer turns the flag on after merge.
- **Release condition:** deployed with the flag on and checked on a real thread with likes and a hashtag-only reply. This check is the maintainer's, after merge; Step 4 records it as pending.

## Key Risks

- **High risk:** hiding a reply that people answered. Impact: an orphaned conversation. Early validation: Stage 2 tests with an answered tag-only reply. Mitigation: move only leaves, as `RetractedLeafFilter` does.
- **High risk:** real content counted as tag-only. Impact: text disappears into the drawer. Early validation: Stage 1 table of bodies. Mitigation: any non-hashtag text keeps the reply. So does a subject, agent-response metadata, an event or a retraction. Images live in the body as text, so they keep it too.
- Reply runs shift when replies leave. Early validation: Stage 2 test with a run around a moved reply. Mitigation: filter before the template computes continuation flags.
- Drawer names drift from the signal lines. Mitigation: read `SignalActors`, already loaded, with no second query.

## Stage 1

**Estimate: 0.10**

- Goal: one shared hashtag rule, and a test for "tag-only".
- Dependencies: none.
- Expected changes: move `extractThreadLabelsFromBody` into a small class, `ThreadLabels::fromBody(string $body): list<string>`; `LocalWriteService` delegates to it. Add `ThreadLabels::isTagOnly(array $post): bool`. A post is tag-only when its body has at least one label and nothing else after removing `>` lines, label hashtags and whitespace, and it has no subject, agent-response metadata, event or retraction.
- Verification approach: a new `ThreadLabelsTest` with a table of bodies: `#bug #ux` yes; `#bug see above` no; `> quote` plus `#bug` yes; `> quote` alone no; `#Bug` no, because it is not a valid label; an empty body no. Run the existing label-write tests to show writes are unchanged.
- Estimate details: driver: a refactor with a body-example table; uncertainty: low.
- Risks or open questions: the write and read rules could diverge. Mitigation: both call the same function.
- Canonical components/API contracts touched: `Write/LocalWriteService.php` (`extractThreadLabelsFromBody`, now delegating); new `Support/ThreadLabels.php`, because no shared hashtag helper exists.

## Stage 2

**Estimate: 0.15**

- Goal: with the flag on, tag-only leaves leave the conversation.
- Dependencies: Stage 1.
- Expected changes: register `FORUM_THREAD_ACTIVITY_DRAWER_ENABLED` (off, site-mutable, `RENDERING` group). Add `TagOnlyLeafFilter::split(array $replies): array{kept, moved}`. It repeats until stable, like `RetractedLeafFilter`, so a chain of tag-only leaves goes whole. The template applies it after `RetractedLeafFilter` and before the continuation flags.
- Verification approach: `TagOnlyLeafFilterTest` for leaf, answered and chain cases. Page tests: flag off gives identical HTML; flag on removes the leaf card; a same-author run around a moved reply still groups.
- Estimate details: driver: page fixtures with replies and runs; uncertainty: low to medium.
- Risks or open questions: the root reply count may change; that is intended, because it counts what is shown.
- Canonical components/API contracts touched: `ReadModel/RetractedLeafFilter.php` (pattern reused); `templates/pages/thread.php` (reply partition); `Support/FeatureFlags/FeatureFlagRegistry.php` (one flag).

## Stage 3

**Estimate: 0.20**

- Goal: the drawer itself, with entries and links.
- Dependencies: Stage 2.
- Expected changes: `ThreadActivity::build(array $rootPost, array $movedReplies, array $shownReplies, array $signalActors, bool $recordsEnabled): list<entry>`. It makes one "tagged" entry per moved reply, with author, labels, date and post link. It makes one group per post with likes or flags, labelled "Root post" or "Reply by d". Thread likes count toward the root. Each group links to `/posts/<id>#records` when `FORUM_POST_RECORDS_ENABLED` is on, otherwise to the post. A new partial, `thread_activity_drawer.php`, renders a `<details class="card">` like `post_records.php`. It sits before the reply composer and is skipped when there are no entries. The template wraps the build in a try/catch; on failure it shows all replies and no drawer.
- Verification approach: `ThreadActivityTest` for entries, counts and both link forms. Page test: names in the drawer match the signal lines. Page test: a forced builder failure gives the flag-off reply list. Browser check of the open drawer in Word97, Gloss and Bubblegum.
- Estimate details: driver: the builder, the partial and theme checks; uncertainty: medium, mostly styling.
- Risks or open questions:
  - Impact: theme styling may not fit `<details>` in every theme. Early validation: screenshots in three themes. Mitigation: reuse the `post-records` card classes, adding as little CSS as possible.
  - Impact: UI copy grows. Mitigation: only the summary label and entry text; no explanatory paragraph.
- Canonical components/API contracts touched: `ReadModel/SignalActors.php` (read only); `templates/partials/post_records.php` (pattern and `#records` anchor); `public/assets/site.css`; new partial and builder, because no thread-level activity list exists.

## Stage 4

**Estimate: 0.06**

- Goal: documentation and the cycle's final checks.
- Dependencies: Stage 3.
- Expected changes: new concept entry **thread activity**; update [post presentation](../../concepts/post_presentation.md) for moved tag-only leaves; flag description; Step 4 summary.
- Verification approach: smoke set plus the cycle's tests; final diff review; full suite not run, per `AGENTS.md`. Production check recorded as pending for the maintainer.
- Estimate details: driver: docs and review; uncertainty: low.
- Risks or open questions: none beyond the pending production check.
- Canonical components/API contracts touched: `docs/concepts/`.

Concepts touched: post presentation (changed), reaction record (used), post record list (used for links), thread activity (new).

## Study checkpoint

2026-11-14T19:55:00Z, `railspike-effort` / `v3/thread_activity_drawer`, Step 3 handoff. Scope v1, unchanged since Step 2 approval. Revision `0f456b8`. R1, P2, single exposed Claude rater, resumed in a new conversation from the committed Steps 1–2. Projected total reaffirmed at **0.55**: the plan matches the Step 2 scope and adds no stage beyond it. Stage estimates 0.10, 0.15, 0.20 and 0.06 are separate judgments and don't add up to the total.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
