> **Feature plan:** [Step 1](./post_records_step1_solution_assessment.md) · [Step 2](./post_records_step2_feature_description.md) · [Step 3](./post_records_step3_development_plan.md) · [Step 4](./post_records_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/post_records`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle. Split child: [`v3/retraction_target_id`](../retraction_target_id/retraction_target_id_step2_feature_description.md), which ships first.

Extended estimates: enabled

**Cycle estimate: 0.95**

## Problem

A reader viewing a post can't see the signed records that affect it, such as likes, flags, labels, title and format records. They also can't take back their own like, because a like can't be retracted once it is made.

## User stories

- As a reader, I want a list of every record that targets a post, so that I can see who liked, flagged or retitled it and when.
- As a reader, I want each record on its own page, so that I can check its fields, author and signature without reading the raw file.
- As a member, I want to retract my own like, so that a mistaken or outdated like stops counting.
- As an operator, I want to retract another member's like from the same Retract button, so that I can clean up abuse without the CLI.
- As an operator, I want removal of any record to leave the same receipt that post removal leaves, so that the site has one retraction trail.
- As anyone reading the files, I want a retraction to say plainly what it targets, so that `Post-ID` no longer means "this post" in one record type and "the post I remove" in another.

## Core requirements

- The post page's Records section lists every current record that targets the post, plus the removal receipt for each retracted one. Each row links to that record's page.
- `/records/<Record-ID>` shows one record in readable form. A retracted record's address shows its removal notice instead.
- A member can retract their own like through the existing signed retraction, widened from posts to records. After retraction, the like stops counting, the Like button says "Like" again, and the member can like the post again.
- An operator can retract anyone's like the same way, with the same authority check posts use today (`RetractionAuthority::currentOperator`, `Retraction-Class: operator`). The receipt names the operator as the actor.
- No new retraction record family or format. Removal uses the existing signed request, removal receipt and targeted read-model update, with the `Target-ID` header from `retraction_target_id`. A reaction's Record-ID goes in `Target-ID`.
- A feature flag, off by default, hides the Records section, the record pages and the Retract action for non-post records.

## Format change

Split out on 2026-11-11 into its own cycle, [retraction_target_id](../retraction_target_id/retraction_target_id_step2_feature_description.md). That cycle renames the retraction target header to `Target-ID` for posts, reads both header forms, keeps `post_id` as an API alias, and stops posts from using reaction prefixes. This cycle starts after it ships, and adds reaction IDs as targets of the same `Target-ID` header.

Superseded format section, scope v7, kept for the record: rename `Post-ID` to `Target-ID` in requests and receipts (`Target-Created-At` for the time field), with the target family read from the ID prefix, the post-ID guard, dual-form reading and the API alias, all inside this cycle.

## Delivery scope

- Work type: application change.
- In the first slice, Retract covers likes: a member's own, or anyone's for an operator. Records of other kinds appear in the list and get their own pages, but can't be retracted yet.

## Completion boundary

- Normal entry: open a post, then expand Records.
- End-to-end outcome: from the list or the record page, a member retracts their own like. The like count drops, the row shows the removal notice, and the Like button works again.
- Recovery: if signing or submission fails, the like stays and the page shows the error with a retry. A replayed request is accepted only once.
- Release condition: deployed with the flag on at zenmemes.com, and a like retracted there end to end.

## Risks

- **Depends on `retraction_target_id`.** Impact: this cycle can't start Step 4 until that one is deployed. Mitigation: plan Step 3 against the child's approved format. Status 2026-11-14: met. The child merged in PR #189 on 2026-11-13, and the maintainer reports a successful live retraction since.
- **Readers keep counting removed likes.** Scores, the Liked view, board tags and Fastmod each read reactions. Impact: a retracted like still shows somewhere. Earliest validation: an equivalence test comparing a targeted removal with a full rebuild, like the existing retraction test. Mitigation: list every reaction reader in Step 3.
- **Re-liking after a retraction.** An accepted removal blocks the old content from coming back, so a new like must be a new record, not the old one. Impact: the member can't like the post again. Earliest validation: a Step 4 test that likes, retracts and likes again.
- **Page cost on busy posts.** A post with many likes produces a long list. Mitigation: collapse the section by default, and paginate with readable URLs if needed.

## Shared component inventory

- Signed retraction request: `Canonical/PostRetractionRecordParser.php`, `Write/LocalWriteService.php` (`finalizePreparedRetraction`), the `prepare_retraction` API in `Http/WritePostAndIdentityApiController.php`, `public/assets/browser_signing.js`. Extend these to take a record target. No new path.
- Removal receipt: [removal acceptance v1](../../specs/removal_acceptance_record_v1.md), `Canonical/RemovalReceipt.php`, `Canonical/AcceptedRemoval.php`. Extend so `Target-ID` can hold a reaction Record-ID.
- Specs: after the child's rename, `post_retraction_record_v1.md` and `removal_acceptance_record_v1.md` gain reaction targets.
- Targeted removal: `ReadModel/IncrementalReadModelUpdater.php` (`applyPostRemoval`), `Write/StaticArtifactInvalidator.php`, `Write/SourceLabelRemoval.php` (`./v3 delete-record`). Extend to reaction records. Step 3 checks whether `delete-record` on a single reaction file already writes a receipt or only runs `git rm`.
- Operator authority: `Security/RetractionAuthority.php`, `viewerCanRetractOthers` in `Http/ThreadAndPostPageController.php`, `templates/partials/retract_post_control.php`. Reuse as is.
- Reaction records: `Canonical/PostReactionRecordParser.php`, `Canonical/ThreadLabelRecordParser.php`, `ReadModel/ReactionSignatureIndex.php`, `ReadModel/ViewerTagLookup.php`. Reuse.
- Post page evidence: `templates/partials/source_metadata.php` and the `/source/current/` route. Reuse for each row's source and signature links.
- Retract control: `templates/partials/retract_post_control.php`. Reuse its flow for the record-level button.
- Like button and signals: `templates/partials/post_signals.php`, `templates/partials/post_card.php`. Reuse. The Like state already follows the read model.
- Feature flags: `Support/FeatureFlags/FeatureFlagRegistry.php`. Add one flag.
- New: the Records section and the record page template. Nothing similar exists, because evidence is shown only for the post record itself.

Concepts touched: [post retraction](../../concepts/post_retraction.md) (changed: its target widens from posts to records); [incremental source removal](../../concepts/incremental_source_removal.md) (changed: it also removes reaction records); [reaction record](../../concepts/reaction_record.md) (used: a reaction can now be removed); [source history links](../../concepts/source_history_links.md) (used unchanged); [post presentation](../../concepts/post_presentation.md) (changed: new Records section). Proposed inventory addition: **post record list**, the records that target one post and their record pages.

## User flow

1. A member opens a post they liked and expands Records.
2. The list shows the post record, their like and other records, each with its kind, author and time.
3. They open their like's record page and choose Retract.
4. The browser signs the retraction request, and the site accepts it.
5. The page shows the removal notice, the post's like count drops, and the Like button says "Like".

## Success criteria

- On a test post, the Records list matches the set of files under `records/` that target the post.
- Every listed record's page loads, and its fields match the file.
- Retracting a like leaves the same read-model state as a full rebuild without that like.
- Like, retract, then like again leaves exactly one counted like.
- With the flag off, the post page is unchanged.

**Approval:** Approved Step 2 by the maintainer, 2026-11-11T23:12:00Z, scope v5 (including operator retraction of anyone's like). Reopened 2026-11-11T23:28:00Z when the maintainer chose to rename the target headers during Step 3 review. Renewed approval: Approved Step 2 by the maintainer, 2026-11-14T08:15:00Z, scope v8.

## Study checkpoint

2026-11-11T06:40:00Z, `railspike-effort` / `v3/post_records`, Step 2 handoff. Scope v4, unchanged since the Step 1 approval: Option B with the Records list, record pages and own-like retraction in one slice. Starting revision `d1052b3ff716c55b8d6bb53118d00aa585294b0e`. R1, P2, single exposed Claude rater. Projected total reaffirmed at **0.85**. The receipt-format risk could move it if Step 3 finds that v1 needs widening. Ledger update still pending, as at Step 1: this is a fresh clone.

Revision, 2026-11-11T22:58:00Z. The maintainer asked whether operators can retract records too. Scope v5: operator retraction of anyone's like moves into this slice through the existing operator retraction class. Projected total 0.85 → **0.90**, reason changed scope (one more permission path and its tests; the authority check already exists for posts). Same baseline, R1, P2 and single exposed Claude rater.

Revision, 2026-11-11T23:30:00Z. The maintainer chose to rename `Post-ID` to `Target-Kind` / `Target-ID` in retraction requests and receipts, because the header is ambiguous. Scope v6: the format change plus compatibility reading for existing records. Projected total 0.95 (the Step 3 value) → **1.10**, reason changed scope (two spec and parser changes, dual-form reading and its tests). Because this is above 1.0, a split is worth discussing: the rename could ship first as its own cycle for posts only, with `post_records` built on it. Same baseline, R1, P2 and single exposed Claude rater.

Revision, 2026-11-11T23:35:00Z. The maintainer asked whether `Target-Kind` is necessary. It isn't, because the ID prefix already identifies the family. Scope v7: `Target-ID` only, plus a post-ID prefix guard. Projected total 1.10 → **1.05**, reason changed scope (no kind field and no agreement check). Still above 1.0, so the split option stands. Same baseline, R1, P2 and single exposed Claude rater.

Revision, 2026-11-11T23:42:00Z. The maintainer approved splitting the header rename into `v3/retraction_target_id`. Scope v8: this cycle without the format change, built on the child's `Target-ID`. Parent scope v7 and its **1.05** stay as the split record. Projected total for v8, judged independently: **0.95**. It matches the Step 3 value, because that plan already put reaction IDs in the single target slot without a rename. Same baseline, R1, P2 and single exposed Claude rater. The child and parent are not independent outcomes.

## Enhancement Requests

Carried from Step 1, still pending:
- Show replies in the Records section. Disposition: out of scope.
- Rename the `post-retraction-` prefix and the `records/post-retractions/` directory to match the wider target. Disposition: out of scope, because it moves files. It can be a follow-up if the name keeps confusing people.
- Per-instance retraction mode (keep both, delete from tree, purge). Disposition: this slice ships delete-from-tree with a receipt. Keep-both is a separate cycle, linked to the presets brainstorm at https://zenmemes.com/threads/thread-20261110171842-0dece695

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
