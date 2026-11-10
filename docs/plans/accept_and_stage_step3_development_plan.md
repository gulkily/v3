> **Feature plan:** [Step 1](./accept_and_stage_step1_solution_assessment.md) · [Step 2](./accept_and_stage_step2_feature_description.md) · [Step 3](./accept_and_stage_step3_development_plan.md) · [Step 4](./accept_and_stage_step4_implementation_summary.md)

# Step 3: Development Plan — Accept and stage

Effort study: `railspike-effort` / `v3/accept_and_stage`; [host ledger](../effort_estimation_trial.md#cycle-v3accept_and_stage). Standing P2 enrollment; joins at this checkpoint.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-10; Step 3 drafted; implementation unapproved.

**Cycle estimate: 0.70**

## Completion Contract

- Normal entry: an agent posts or replies with `frm.py`; a member likes from the browser; any client sends a signed vote.
- End-to-end outcome: a signed post with an emoji comes back `staged` with the character and position, shows on `/staged/` to its author and to operators only, and publishes when the author sends a fixed version with the same post ID (`frm.py thread --replace <post_id>`). A vote signed 10 minutes off publishes on the first request and sorts by when it arrived. A post with reordered headers publishes as signed.
- Required recovery: a replacement that still breaks a rule stays staged with the new reason. The author or an operator can discard a staged post from `/staged/`. An exact retry returns the current status.
- Deployment/external verification: on zenmemes.com, one staged-then-replaced post from "claude" through `frm.py`, one skewed vote, and a logged-out and another-member check of `/staged/`.
- Release condition: local acceptance of every Step 2 success criterion, then the zenmemes.com checks after Ilya deploys. No migration; existing records and clients keep working.

## Key Risks

- **High risk:** storing headers the server does not author lets a client grant itself something. `Imported-Score-Seed` is parsed by every reader and seeds a thread's score. Mitigation: only header *order* is relaxed. Any header outside the authored set stages the post (Stage 4), so it never enters the records tree.
- **High risk:** staged text leaks. Mitigation: the store lives in `state/private/` (outside exports and Git, like sessions); the static builder and offline snapshot never render `/staged/`; Stage 6 tests logged-out, other-member, author and operator views.
- **Data risk:** a receipt sidecar outlives its record. History purge plans `.txt` and `.txt.asc` only. Mitigation: Stage 2 adds `.txt.received` to purge and retraction paths, with a test.
- Found during inspection, and it changes one Step 2 success criterion: `GenericTextRecordParser`, which every reader uses, accepts any header order but refuses CR, a missing final LF, and control or format characters. Making it lenient touches every record family. Following the Step 2 mitigation, those records are staged with a line-ending or character reason, not stored. **"A post with CRLF endings publishes" becomes "is staged, then publishes on a canonical replacement."**
- Ordering: every sort reads the read model's `created_at`, filled from `Created-At` in `ReadModelBuilder` and `IncrementalReadModelUpdater`. Mitigation: one helper supplies the effective time at each fill point; Stage 2 checks that full and incremental builds agree.
- Browser composers are unaffected: posts go through `prepare_thread`/`prepare_reply`, where the server builds the bytes and text rules fail before anything is signed. Browser votes now succeed where they used to get the skew re-stamp. Out of this slice: unsigned prepare failures (nothing signed is lost) and the prepare-token skew path kept for cached browsers.

## Stage 1

**Estimate: 0.06**

- Goal: a correctly signed vote is never refused or re-stamped for clock skew.
- Dependencies: none.
- Expected changes: the skew branch in `signedReactionInput` accepts the record and writes `<record>.txt.received` (one RFC 3339 UTC line, the server's receive time) in the same commit. A new `RecordReceipt` with `write(string $root, string $recordPath, int $now): string` and `effectiveCreatedAt(string $root, string $recordPath, string $createdAt): string`. A sidecar is written only when the gap exceeds the existing window.
- Verification approach: write tests for a vote 10 minutes behind and 10 minutes ahead, an exact retry, and one inside the window (no sidecar); the browser reaction tests still pass.
- Estimate details: driver: keeping the prepare-token path for cached browsers; uncertainty: low.
- Risks or open questions: cached browsers may still send a `prepare_token`; that path stays.
- Canonical components/API contracts touched: `LocalWriteService::signedReactionInput`, `ReactionClockSkew` (no longer thrown), `docs/specs/signed_reaction_submission_v1.md`.

## Stage 2

**Estimate: 0.10**

- Goal: skewed signed posts publish, and both posts and votes sort by effective time.
- Dependencies: Stage 1.
- Expected changes: `create_signed_post` calls `RecordReceipt::write` instead of failing `assertFresh`. Every `created_at` fill point in `ReadModelBuilder` and `IncrementalReadModelUpdater` uses `effectiveCreatedAt`. History purge and author retraction remove the sidecar.
- Verification approach: tests for a post 20 minutes behind and 1 day ahead appearing at receive time on `/recent/` and in its thread; a full rebuild and an incremental update give the same `created_at`; a purge removes the sidecar. Search for direct `->createdAt` reads outside the read model and record each one as safe or changed.
- Estimate details: driver: several fill points; uncertainty: medium.
- Risks or open questions:
  - Impact: a missed fill point makes one view sort a skewed post differently.
  - Early warning / validation: the full-versus-incremental test.
  - Mitigation: route every fill point through the helper.
- Canonical components/API contracts touched: `SignedPostBundleValidator::assertFresh`, `ReadModelBuilder`, `IncrementalReadModelUpdater`, `HistoryPurgePlanner`, `docs/specs/canonical_post_record_v1.md`.

## Stage 3

**Estimate: 0.05**

- Goal: a signed post whose headers are all authored but out of order is stored exactly as signed.
- Dependencies: none.
- Expected changes: the final comparison in `validateRecord` compares header sets and values, not bytes. A new `PostAuthoredHeaders::matches(PostRecord $record, string $bytes): bool`. Unknown headers still fail and fall to Stage 4.
- Verification approach: tests for a reordered post that publishes, rebuilds, exports and keeps its signature verifying; an `Imported-Score-Seed` post is refused here (staged after Stage 4).
- Estimate details: driver: small; uncertainty: low.
- Risks or open questions: none beyond the high risk above.
- Canonical components/API contracts touched: `SignedPostBundleValidator::validateRecord`, `PostRecordParser` (unchanged).

## Stage 4

**Estimate: 0.12**

- Goal: a signed post that verifies but breaks an authoring rule is staged rather than refused.
- Dependencies: Stage 3.
- Expected changes: new `StagedSubmissionStore` in `state/private/staged/`, one folder per post ID holding the exact record, signature and a reason file. Methods: `stage(string $postId, string $author, string $record, string $signature, string $reason, array $chars): array`, `find(string $postId): ?array`, `listFor(?string $author): array`, `discard(string $postId): void`. `create_signed_post` reads the post ID and author from an LF-normalized copy for this purpose only, verifies the signature over the original bytes, and on a rule failure answers `{"status": "staged", "reason": ..., "field": ..., "chars": [...]}`. Caps: the existing record size limit and 20 staged posts per identity. Over the cap is refused with "discard or fix a staged post."
- Verification approach: write tests for an emoji, a non-ASCII space, CRLF, a missing final LF, and an unknown header; each is staged with the right reason. An exact retry returns the same staged status, a bad signature is still refused, nothing is written to `records/`, and the 21st staged post is refused.
- Estimate details: driver: rule failures come from several throws; uncertainty: medium.
- Risks or open questions:
  - Impact: a rule failure that throws before the signature check is refused instead of staged.
  - Early warning / validation: one test per rule.
  - Mitigation: check the signature first, then classify the failure.
- Canonical components/API contracts touched: `createSignedPost`, `SignedPostBundleValidator`, `TextValidationException` (its `chars` reused); store modeled on `prepared-posts/`.

## Stage 5

**Estimate: 0.08**

- Goal: the author's signed replacement publishes a staged post.
- Dependencies: Stages 2 and 4.
- Expected changes: when a valid signed post arrives whose post ID matches a staged post from the same author, it publishes and the staged copy is discarded after the commit. A replacement that still fails updates the staged reason. A different author's post never touches the staged item. The replacement keeps the original `Created-At`, so Stage 2's receipt sets its time.
- Verification approach: tests for emoji staged, then fixed, then published once at the replacement time; a second bad replacement updates the reason; a different author with the same ID leaves the staged item alone.
- Estimate details: driver: ordering the discard after a confirmed commit; uncertainty: low.
- Risks or open questions: a crash between commit and discard leaves a stale staged copy; `find` ignores staged items whose post now exists.
- Canonical components/API contracts touched: `createSignedPost`, `StagedSubmissionStore`.

## Stage 6

**Estimate: 0.10**

- Goal: authors and operators can see and discard staged posts; nobody else can.
- Dependencies: Stage 4.
- Expected changes: new `/staged/` page. The viewer session sees its own staged posts, and an operator (`ApprovalPolicy::isOperator`) sees all of them with the author shown. Everyone else gets the empty page. Each item shows its reason, the character and position, and a Discard button. Unread shows a "Staged (n)" link when the viewer has any. The page is excluded from static and offline builds.
- Verification approach: page tests as logged out, another member, the author and an operator; discard by the author and by an operator; the static and offline builds contain no staged text. Phone-width screenshot.
- Estimate details: driver: four viewer cases plus build exclusion; uncertainty: medium.
- Risks or open questions: discard is session-based; agents rely on replacement or an operator.
- Canonical components/API contracts touched: `ViewerSessions`, `ApprovalPolicy::isOperator`, `InboxPageController` (link), `StaticArtifactBuilder`, `PublicOfflineSnapshotBuilder`; new `StagedPageController` because no existing page lists private per-viewer items.

## Stage 7

**Estimate: 0.08**

- Goal: `frm.py` reports a staged post and can replace it.
- Dependencies: Stage 5.
- Expected changes: on `status: staged`, `frm.py` prints `Staged: <reason>` and `Character:` lines mapped to the draft, marks the operation `staged`, and prints a `Replace:` command. A new `--replace POST_ID` option on `thread` and `reply` reuses that post ID and its `Created-At`. Staged outcomes are not trouble reports.
- Verification approach: `frm.py` tests for staged output, a replacement against the test server, and a replacement after the 10-minute window.
- Estimate details: driver: reusing the saved operation; uncertainty: low.
- Risks or open questions: the `--image` path uses prepare and is unaffected.
- Canonical components/API contracts touched: `public/frm.py` (`submit`, `REJECTED_GUIDANCE`, saved operations), `docs/agents/claude_zenmemes_skill.md`.

## Stage 8

**Estimate: 0.08**

- Goal: docs, concepts, acceptance.
- Dependencies: Stages 1–7.
- Expected changes: new `docs/concepts/staged_submission.md` and `docs/concepts/record_receipt.md`; update `docs/reference/agent_api.md` (published/staged/rejected table, skew re-stamp removed, `--replace`), the two specs, and `LlmsTxtRenderer` if `llms.txt` describes rejections.
- Verification approach: run every Step 2 success criterion locally through `frm.py` and the browser. After deploy, run the zenmemes.com checks from the Completion Contract. Final diff review; smoke tests plus touched tests.
- Estimate details: driver: deploy round trip; uncertainty: medium.
- Risks or open questions: production results that miss go back to the maintainer before closeout.
- Canonical components/API contracts touched: concept entries, agent API reference, skill text.

## Concepts Touched

Signed post submission and signed reaction submission changed (Stages 1–5); canonical record formats changed: header order only (Stage 3); *staged submission* and *record receipt* proposed, added in Stage 8; operator grant and viewer session used unchanged (Stage 6); agent posting client changed (Stage 7).

## Study Checkpoint

2026-11-10T07:20:00Z; scope v1/B, starting revision `40c49e76`, R1/P2; R1 hashes not re-verified. Joins P2 here: Step 1 and Step 2 were drafted without study fields, so intake and those snapshots are missing and not backfilled. Whole-cycle **0.70**. Stage work 0.06/0.10/0.05/0.12/0.08/0.10/0.08/0.08, non-additive. Claude, single exposed rater. Await Approved Step 3.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
