> **Feature plan:** [Step 1](./post_records_step1_solution_assessment.md) · [Step 2](./post_records_step2_feature_description.md) · [Step 3](./post_records_step3_development_plan.md) · [Step 4](./post_records_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/post_records`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.95**

Status: revised 2026-11-14 after the Step 2 scope v8 approval. It builds on `Target-ID` from `retraction_target_id`, merged in PR #189, and replaces the superseded Option 1 format decision.

## Completion Contract

- Normal entry: open a post on zenmemes.com and expand Records. Each row opens its `/records/<Record-ID>` page.
- End-to-end outcome: from the list or a record page, a member retracts their own like, or an operator retracts anyone's. The like count drops, the row and record page show the removal notice, the Like button says "Like" again, and liking again counts once.
- Recovery: if signing or submission fails, the like stays and the control shows the error and a retry. Replaying the same request is accepted once. If the targeted update fails, the site falls back to a full rebuild, as post retraction does.
- Operator CLI: `./v3 delete-record <reaction-id>` removes the record and its signature and writes the same local receipt that posts get.
- Deployment: deploy to zenmemes.com, turn the flag on, and retract a like there end to end.
- Release condition: the deployment check passes, with the smoke tests and the tests for the changed parts green.

## Key Risks

- **High risk:** A retraction receipt for a reaction could become a placeholder post. The rebuild turns every accepted removal into a placeholder row in the posts table (`ReadModelBuilder`). Impact: phantom "retracted post" rows in threads. Early validation: in Stage 1, a rebuild test with one reaction receipt. Mitigation: dispatch removals on the target's ID family before building the placeholder.
- **Format:** settled. `retraction_target_id` shipped `Target-ID` and `Target-Created-At`, read through `RemovalTarget`, and posts can't use reaction prefixes. A reaction's Record-ID goes in `Target-ID` with no header change. The receipt's `Thread-ID` and `Parent-ID` hold the like's thread and target post, and `Target-Created-At` holds the like's `Created-At`.
- **High risk:** A removed like could keep counting. Impact: wrong scores or a stuck "Liked" button. Early validation: Stage 3's equivalence test against a full rebuild. Mitigation: reuse `applyPostReactionWrite` and `applyThreadLabelWrite`, which already re-derive state from the remaining files.
- **`delete-record` leaves no trail for reactions.** Today it runs `git rm` on the one file, so it leaves the `.asc` behind and writes no receipt. Mitigation: Stage 4.

## Stage 1

**Estimate: 0.12**

- Goal: a retraction request and receipt can name a reaction in `Target-ID`, and rebuilds handle that correctly.
- Dependencies: `retraction_target_id`, merged.
- Expected changes: planned `RemovalTarget::family(string $id): string`, returning `'post'`, `'post-reaction'` or `'thread-label'` using the existing `isReactionId()`. The request parser accepts a reaction target in the new `Target-ID` form only. A legacy `Post-ID` file naming a reaction is rejected, since none can exist. Rebuild skips placeholder post rows for non-post families, and `AcceptedRetraction` / `RetractedPosts` filter by family.
- Verification approach: parser and receipt round-trip tests for both reaction families; a rebuild with a reaction receipt creates no post row.
- Estimate details: driver: one family dispatch point in a few readers, with the header work already done; uncertainty: low.
- Risks or open questions: phantom placeholder rows (Key Risks).
- Canonical components/API contracts touched: `Canonical/RemovalTarget.php`, `Canonical/PostRetractionRecordParser.php`, `Canonical/RetractionReceipt.php`, `Canonical/AcceptedRetraction.php`, `Canonical/RetractedPosts.php`, `ReadModel/ReadModelBuilder.php`; specs `post_retraction_record_v1.md` and `removal_acceptance_record_v1.md` (reaction targets documented).

## Stage 2

**Estimate: 0.15**

- Goal: a signed retraction of a like is accepted end to end through the API, with a full rebuild.
- Dependencies: Stage 1.
- Expected changes: `prepare_retraction` takes a reaction ID. Authority: the reaction's author, or `RetractionAuthority::currentOperator` with `Retraction-Class: operator`. Finalize removes the record and its `.asc` and commits them with the request and receipt.
- Verification approach: API smoke tests for author, operator, non-author refusal and replay.
- Estimate details: driver: reusing `finalizePreparedRetraction` with a second target family; uncertainty: medium, in how deeply that path assumes a post.
- Risks or open questions: the post-only checks inside finalize may need splitting. Mitigation: branch at target lookup only.
- Canonical components/API contracts touched: `Write/LocalWriteService.php`, `Http/WritePostAndIdentityApiController.php`, `Security/RetractionAuthority.php`, `Http/ApiRouteCatalog.php`, `docs/reference/agent_api.md`.

## Stage 3

**Estimate: 0.10**

- Goal: retracting a like updates only its post or thread, not the whole site.
- Dependencies: Stage 2.
- Expected changes: after commit, call `applyPostReactionWrite` or `applyThreadLabelWrite` for the target, clear its pages, and fall back to a full rebuild on failure.
- Verification approach: equivalence test against a full rebuild; a like, retract, like test leaves one counted like and the button re-enabled.
- Estimate details: driver: reuse of existing re-derivation; uncertainty: low.
- Risks or open questions: a stale "Liked" state (Key Risks).
- Canonical components/API contracts touched: `ReadModel/IncrementalReadModelUpdater.php`, `Write/StaticArtifactInvalidator.php`, `ReadModel/ViewerTagLookup.php`.

## Stage 4

**Estimate: 0.10**

- Goal: `./v3 delete-record` on a reaction leaves the same trail as on a post.
- Dependencies: Stage 1.
- Expected changes: for reaction paths, remove the `.asc` too, write a local receipt at `records/post-removals/<record-id>.txt`, and use the Stage 3 targeted update.
- Verification approach: CLI tests for both families; `--inspect` output unchanged for posts.
- Estimate details: driver: a second branch in the script; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `scripts/delete_record.php`, `Canonical/LabelRemoval.php`, `docs/reference/v3_cli.md`.

## Stage 5

**Estimate: 0.15**

- Goal: the post page shows a collapsed Records section behind a new flag, off by default.
- Dependencies: Stage 1, for receipt rows.
- Expected changes: flag `post_records`. A lookup lists the post record, its post reactions, root thread labels when the post is a root, title, format and timestamp records, and receipts for removed ones. Rows show kind, author, time, and source and signature links.
- Verification approach: page test listing every fixture record; the page is unchanged with the flag off.
- Estimate details: driver: one query over existing tables plus a partial; uncertainty: medium, in whether title and format records are indexed by post.
- Risks or open questions: long lists on busy posts. Mitigation: collapsed by default.
- Canonical components/API contracts touched: `Support/FeatureFlags/FeatureFlagRegistry.php`, `Http/ThreadAndPostPageController.php`, `templates/partials/source_metadata.php`, new partial `post_records.php`.

## Stage 6

**Estimate: 0.15**

- Goal: `/records/<Record-ID>` shows one record or its removal notice.
- Dependencies: Stage 5.
- Expected changes: route and page with fields, target link, author, signature status, and raw and signature links. A retracted ID shows the receipt's notice. Unknown IDs return 404.
- Verification approach: page tests for live, retracted and unknown records; links resolve.
- Estimate details: driver: new route and template; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `Application.php` routing, the `/source/current/` route, new template `record.php`.

## Stage 7

**Estimate: 0.15**

- Goal: Retract buttons on rows and record pages, plus documentation.
- Dependencies: Stages 3, 5 and 6.
- Expected changes: reuse `retract_post_control.php` for reactions, with signing in `browser_signing.js`. Update concepts (post retraction, incremental source removal, post presentation, new post record list) and `llms.txt`.
- Verification approach: browser check of author and operator retraction and the error-retry path; concept links resolve.
- Estimate details: driver: control reuse plus documentation; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `templates/partials/retract_post_control.php`, `public/assets/browser_signing.js`, `Http/LlmsTxtRenderer.php`, `docs/concepts/`.

## Stage 8

**Estimate: 0.05**

- Goal: release on zenmemes.com.
- Dependencies: Stage 7.
- Expected changes: none in code. The maintainer deploys and turns the flag on.
- Verification approach: retract a like end to end on the live site; review the final diff.
- Estimate details: driver: a deploy check; uncertainty: low.
- Risks or open questions: a rebuild after deploy. Mitigation: Deploy now then rebuilds in the background.
- Canonical components/API contracts touched: none.

Concepts touched: [post retraction](../../concepts/post_retraction.md) (changed: record targets); [incremental source removal](../../concepts/incremental_source_removal.md) (changed: reactions); [reaction record](../../concepts/reaction_record.md) (changed: removable); [post presentation](../../concepts/post_presentation.md) (changed: Records section); new **post record list**. Inventory updates happen in Stage 7.

**Approval:** Approved Step 3 by the maintainer, 2026-11-14T08:18:00Z.

## Study checkpoint

Revision, 2026-11-14T08:30:00Z. Revised after the Step 2 scope v8 approval, on top of the merged `retraction_target_id` (main `523b8171`). Stage 1 shrinks from 0.15 to 0.12, reason new information: the header handling and post-prefix guard already exist. Projected total reaffirmed at **0.95**, unchanged since the scope v8 judgment, which already assumed the child. Same R1, P2 and single exposed Claude rater.

2026-11-11T23:30:00Z, `railspike-effort` / `v3/post_records`, Step 3 handoff. Scope v5, unchanged since the Step 2 approval. Revision for planning: `273bf937`; the cycle start stays `d1052b3f`. R1, P2, single exposed Claude rater. Projected total 0.90 → **0.95**. Reason: new information, not a scope change. `delete-record` writes no receipt and leaves the signature for reactions, and the rebuild would turn reaction receipts into placeholder posts. Stage estimates are stage work only; they don't add up to the cycle. Ledger update pending: fresh clone.

## Enhancement Requests

Carried from Step 2, still pending: show replies in Records (out of scope); per-instance retraction mode with keep-both (separate cycle; see https://zenmemes.com/threads/thread-20261110171842-0dece695).

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
