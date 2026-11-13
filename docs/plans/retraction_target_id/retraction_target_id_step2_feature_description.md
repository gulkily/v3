> **Feature plan:** [Step 2](./retraction_target_id_step2_feature_description.md) · [Step 3](./retraction_target_id_step3_development_plan.md) · [Step 4](./retraction_target_id_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/retraction_target_id`; [host ledger](../../effort_estimation_trial.md). Standing P2 enrollment. Split from `v3/post_records` Step 2 scope v7 at the maintainer's request on 2026-11-11. This is a child cycle, not an independent outcome. The parent's original scope and estimate are kept in [post_records Step 2](../post_records_step2_feature_description.md).

Extended estimates: enabled

**Cycle estimate: 0.40**

Step 1 skipped: the maintainer chose the direction during `post_records` planning. Context: [post_records Step 1](../post_records_step1_solution_assessment.md).

## Problem

In a retraction request and its receipt, `Post-ID` names the post being removed, while in a post record the same header names the post itself. The header means two different things, and a reader of the files can't tell which without knowing the record type.

## User stories

- As anyone reading the repository, I want a retraction to name its target in a header called `Target-ID`, so that I don't confuse the target with the record's own identity.
- As an author or operator, I want retracting a post to work exactly as it does today, so that the rename changes nothing I do.
- As an agent or script author, I want existing calls with `post_id` to keep working, so that my client doesn't break.

## Core requirements

- New signed retraction requests use `Target-ID` instead of `Post-ID`. The other headers are unchanged: `Record-ID`, `Created-At`, `Operation: retract`, `Author-Identity-ID`, and optional `Actor-Identity-ID` and `Retraction-Class`.
- New receipts, both signed and local operator ones, use `Target-ID` and `Target-Created-At` instead of `Post-ID` and `Post-Created-At`. `Thread-ID` and `Parent-ID` keep their names.
- Existing requests and receipts with `Post-ID` stay valid and are read as before. A file uses one form or the other, never both. Signed bytes are never rewritten, and nothing is migrated.
- The post parser rejects Post-IDs that start with `post-reaction-` or `thread-label-`, so a target's family can always be read from its ID. This guard has no effect yet in this cycle; `post_records` relies on it.
- `prepare_retraction` accepts `target_id` and keeps `post_id` as an alias. Paths and the `post-retraction-` prefix don't change.

## Delivery scope

- Work type: application change. Targets stay posts only. Reaction targets belong to `post_records`.
- No feature flag. The change is internal to the record format, and retraction keeps behaving the same.

## Completion boundary

- Normal entry: retract a post from its page, or run `./v3 delete-record <post-id>`.
- End-to-end outcome: the new request and receipt carry `Target-ID`, the post shows its removal notice, and a full rebuild over a mix of old and new files gives the same result as before.
- Recovery: replays and retries behave as today. A file that mixes the two headers is rejected with a clear error.
- Release condition: deployed to zenmemes.com, with one post retracted there and the new files checked in the repository.

## Risks

- **High risk:** Rollback. Older app versions reject the new receipts, because v1 refuses unknown fields. Impact: after rolling back, rebuilds fail on any receipt written since the deploy. Earliest validation: Step 3 names the rollback path. Mitigation: the release notes say a rollback must go to a version that reads both forms, or must first restore the old receipts from git.
- **A missed reader.** Five classes read these fields. Impact: an old or new retraction is silently ignored somewhere. Earliest validation: a fixture set with both forms run through each reader. Mitigation: one shared header lookup used by all of them.
- **Existing posts with a reaction prefix.** Impact: the new guard would reject an existing post. Earliest validation: scan zenmemes.com's `records/posts/` for those prefixes before deploying. Mitigation: if any exist, return here before release.

## Shared component inventory

- Request: `Canonical/PostRetractionRecordParser.php`, `Canonical/PostRetractionRecord.php`, and the request builder in `Write/LocalWriteService.php`. Extend for the new header.
- Receipts: `Canonical/RemovalReceipt.php`, `Canonical/RetractionReceipt.php`, `Canonical/LabelRemoval.php` and `Canonical/AcceptedRemoval.php`. Extend for the new header, still reading the old form.
- Post guard: `Canonical/PostRecordParser.php`. Extend.
- API and clients: `Http/WritePostAndIdentityApiController.php`, `Http/ApiRouteCatalog.php`, `Http/LlmsTxtRenderer.php`, `public/assets/browser_signing.js`, `docs/reference/agent_api.md`. Add the `target_id` parameter and keep the alias.
- Specs: `docs/specs/post_retraction_record_v1.md` and `docs/specs/removal_acceptance_record_v1.md`. Add the new header and a compatibility section, modeled on `reaction_record_v1.md`.
- No new components.

Concepts touched: [post retraction](../../concepts/post_retraction.md) (changed: target header renamed). No inventory additions.

## User flow

1. An author retracts their post from its page.
2. The browser signs a request that names the post as `Target-ID`.
3. The site deletes the post, writes a receipt with `Target-ID`, and shows the removal notice.

## Success criteria

- New requests and receipts contain `Target-ID` and no `Post-ID`.
- A full rebuild over a mix of old and new retractions gives the same read model as today's code does on the old files alone, plus the new removals.
- An API call with `post_id` still retracts the post.
- A post file whose ID starts with `post-reaction-` or `thread-label-` is rejected.

**Approval:** Approved Step 2 by the maintainer, 2026-11-12T02:02:00Z, scope v1.

## Study checkpoint

2026-11-11T23:40:00Z, `railspike-effort` / `v3/retraction_target_id`, intake and Step 2 handoff. Scope v1: rename the retraction target header for posts, with dual-form reading, an API alias and the post-ID prefix guard. Starting revision `273bf937`. R1, P2, single exposed Claude rater, who is also the parent's rater; pristine intake missing because the readers were inspected before rating. Projected total **0.40**. Child of `v3/post_records`; not an independent outcome. Ledger update pending: fresh clone.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
