> **Feature plan:** [Step 2](./retraction_target_id_step2_feature_description.md) · [Step 3](./retraction_target_id_step3_development_plan.md) · [Step 4](./retraction_target_id_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/retraction_target_id`; [host ledger](../../effort_estimation_trial.md). Standing P2 enrollment. Child of `v3/post_records`; not an independent outcome.

Extended estimates: enabled

**Cycle estimate: 0.40**

## Completion Contract

- Normal entry: retract a post from its page, through the API with `target_id` or `post_id`, or with `./v3 delete-record <post-id>`.
- End-to-end outcome: every new request and receipt carries `Target-ID` and contains no `Post-ID`. Old files still read. The post shows its removal notice.
- Recovery: replay returns `already_retracted: true` as today. A file with both headers, or neither, is rejected with an error that names the file.
- Deployment: the maintainer deploys to zenmemes.com. Before that, a scan of `records/posts/` must find no IDs starting with `post-reaction-` or `thread-label-`. A partial scan of the live root thread IDs through `list_index` found none.
- Release condition: smoke tests and the tests for the changed parts pass, and one post is retracted on zenmemes.com, with its request and receipt checked in the repository.

## Key Risks

- **High risk:** Rollback. Older versions reject the new receipts, because v1 refuses unknown fields. Rollback path: deploy a version that reads both forms, or remove the receipts written since this deploy and run a full rebuild. A full rebuild treats a missing receipt as no removal, and the deleted posts stay deleted because their files are gone. Stage 3 documents this in the release notes and `v3_cli.md`.
- A missed reader would silently drop a retraction. Mitigation: all five readers go through one shared lookup, and Stage 1 runs both fixture forms through each of them.

## Stage 1

**Estimate: 0.12**

- Goal: everything that reads retractions accepts both header forms, and posts can't use reaction prefixes.
- Dependencies: none.
- Expected changes: planned helper `RemovalTarget::fromHeaders(array $headers): array{id: string, created_at: ?string}`. It accepts `Target-ID` (with `Target-Created-At` on receipts) or `Post-ID` (with `Post-Created-At`), and rejects a mix or neither. `PostRetractionRecordParser` takes one of the two required sets. `RemovalReceipt::decode` accepts either field list. `RetractionReceipt` and `LabelRemoval` decode through the helper. `PostRecordParser` rejects the two prefixes.
- Verification approach: parser tests for the new form, the old form, a mix and neither, for both the request and the receipt. Guard tests. A full rebuild over old fixtures is unchanged.
- Estimate details: driver: one shared lookup and five callers; uncertainty: low.
- Risks or open questions: `RemovalReceipt` checks the exact field set in two places, lines 17 and 39. Both must change.
- Canonical components/API contracts touched: `Canonical/PostRetractionRecordParser.php`, `Canonical/PostRetractionRecord.php`, `Canonical/RemovalReceipt.php`, `Canonical/RetractionReceipt.php`, `Canonical/LabelRemoval.php`, `Canonical/AcceptedRemoval.php`, `Canonical/PostRecordParser.php`.

## Stage 2

**Estimate: 0.12**

- Goal: new retractions are written in the new form, and the API takes `target_id`.
- Dependencies: Stage 1.
- Expected changes: the request builder in `LocalWriteService` (around line 945) writes `Target-ID`. `RetractionReceipt::encode` and `LabelRemoval::encode` write `Target-ID` and `Target-Created-At`. `prepareRetraction` and `finalizePreparedRetraction` read `target_id`, then fall back to `post_id`; sending both with different values is rejected. Responses keep `post_id` and add `target_id`. `browser_signing.js` sends `target_id`.
- Verification approach: API smoke tests with `target_id`, with `post_id`, with both, and with replay. An author and operator retraction test checks the written bytes. Rebuild equivalence over a mix of old and new files. A `delete-record` CLI test.
- Estimate details: driver: three writers and the API alias; uncertainty: low.
- Risks or open questions: other clients send `post_id`, such as `public/frm.py` and Claude's posting skill. The alias covers them.
- Canonical components/API contracts touched: `Write/LocalWriteService.php`, `Http/WritePostAndIdentityApiController.php`, `public/assets/browser_signing.js`, `scripts/delete_record.php`.

## Stage 3

**Estimate: 0.08**

- Goal: the specs, docs and agent instructions describe the new form, and the change is released.
- Dependencies: Stage 2.
- Expected changes: `post_retraction_record_v1.md` and `removal_acceptance_record_v1.md` gain the `Target-ID` header set and a compatibility section modeled on `reaction_record_v1.md`. Updates to `agent_api.md`, the `llms.txt` retraction line in `LlmsTxtRenderer.php` (line 139), `docs/concepts/post_retraction.md`, and the rollback note in `v3_cli.md`. Then the pre-deploy scan, the deploy, and one live retraction.
- Verification approach: concept and spec links resolve, `llms.txt` renders, and the live files are checked.
- Estimate details: driver: documentation plus a release check; uncertainty: low.
- Risks or open questions: the scan needs a shell on the server: `ls ~/v3/records/posts | grep -E '^(post-reaction|thread-label)-'`.
- Canonical components/API contracts touched: the specs above, `docs/reference/agent_api.md`, `docs/reference/v3_cli.md`, `Http/LlmsTxtRenderer.php`, `docs/concepts/post_retraction.md`.

Concepts touched: [post retraction](../../concepts/post_retraction.md) (changed: target header). Inventory updated in Stage 3.

## Study checkpoint

2026-11-12T02:10:00Z, `railspike-effort` / `v3/retraction_target_id`, Step 3 handoff. Scope v1, unchanged since the Step 2 approval. Planning revision `273bf937`. R1, P2, single exposed Claude rater. Projected total reaffirmed at **0.40**. Stage estimates 0.12, 0.12 and 0.08 are stage work only and don't add up to the cycle. Ledger update pending: fresh clone.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
