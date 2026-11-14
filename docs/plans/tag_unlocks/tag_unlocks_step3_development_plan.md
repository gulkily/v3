> **Feature plan:** [Step 1](./tag_unlocks_step1_solution_assessment.md) · [Step 2](./tag_unlocks_step2_feature_description.md) · [Step 3](./tag_unlocks_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/tag_unlocks`; [host ledger](../effort_estimation_trial.md#cycle-v3tag_unlocks). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved (2026-11-12); implementing on `tag-unlocks`.

**Cycle estimate: 0.65**

## Completion Contract

- Normal entry: an approved member opens a `#meta` thread on zenmemes.com and taps Resolved, Closed or Open on a reply.
- Outcome: Resolved and Closed replies fold to one labelled line that expands on tap, also without JavaScript; Open unfolds. `get_thread` and `get_post` report the state. Scores are unchanged.
- Recovery: a failed tap (offline, busy) shows the error and can be retried without a duplicate mark; an unapproved key's mark is stored and not counted.
- Deployment: behind feature flag `FORUM_TAG_UNLOCKS_ENABLED` (on for zenmemes); the new posts column needs a read-model rebuild, triggered in the background after deploy.
- Release: handled Agent Accessibility reports are marked on production and fold correctly in light, console and one more theme, including on an iPhone (or phone verification recorded as not done).

## Key Risks

- **High risk:** the duplicate-mark guard (`hasPostTagFromIdentity`) refuses Resolved, Open, Resolved from one key. Stage 2 lets exclusive-group tags be marked again as new records; validated by a focused test before any UI work.
- **High risk:** a rebuild for the new column stalls page loads. Use the existing background rebuild after deploy; measure on a production-sized snapshot in Stage 3.
- Theme CSS breaks the folded line. Check light and console in Stage 6 before other themes.
- Status tags leak into scores or Fastmod. Focused test in Stage 2.

## Stage 1

**Estimate: 0.08**

- Goal: tagset file and loader.
- Dependencies: none.
- Expected changes: tracked plain-text file `resources/tagsets.txt` (one parent per line, unlocked tags after it, exclusive group marked), shipping `meta` → resolved, closed, open (exclusive, replies). New `Tagsets` support class: `load(string $path): self`, `unlockedFor(array $threadTags, string $targetKind): array`, `exclusiveGroupOf(string $tag): ?array`. Missing or malformed file means no unlocks, not an error.
- Verification approach: unit tests for parsing, an unknown parent, a malformed line and the exclusive group.
- Estimate details: driver: file format decisions; uncertainty: low.
- Risks or open questions: the format is a new contract. Mitigation: document it at the top of the file and in the tagset concept entry.
- Canonical components/API contracts touched: new `Tagsets`; shape modelled on `QdbVoteCaptionCatalog` lookup methods.

## Stage 2

**Estimate: 0.12**

- Goal: accept unlocked tags on posts, with repeat marks.
- Dependencies: Stage 1.
- Expected changes: `normalizeThreadTag` takes the post's thread tags (labels plus Fastmod effective tags) and allows tags `Tagsets::unlockedFor` returns; refusal names the missing parent. `applyPostTag` skips the duplicate guard for exclusive-group tags so each tap writes a new signed reaction. `TagScore` and Fastmod ignore unlocked tags. Behind the feature flag.
- Verification approach: focused write tests (allowed in `meta` thread, refused elsewhere, Resolved/Open/Resolved by one key writes three records, scores unchanged); smoke set.
- Estimate details: driver: duplicate guard and effective-tag lookup at write time; uncertainty: medium.
- Risks or open questions:
  - Impact: a replayed offline intent could double-write. Validation: test with the same offline intent ID twice. Mitigation: keep the existing intent-ID dedupe.
- Canonical components/API contracts touched: `LocalWriteService::applyPostTag`, `normalizeThreadTag`, `/api/apply_post_tag` (unchanged fields), `PostReactionRecord`.

## Stage 3

**Estimate: 0.12**

- Goal: per-reply state in the read model.
- Dependencies: Stage 2.
- Expected changes: posts gain a resolution state (state, set-at, set-by) computed as the latest exclusive-group mark from an approved key. Filled by `ReadModelBuilder` and `IncrementalReadModelUpdater`, including on approval changes.
- Verification approach: tests for latest-wins, unapproved ignored, approval granted later counts, retraction of the latest mark falls back; rebuild timing on a production-sized snapshot.
- Estimate details: driver: keeping full and incremental builds in agreement; uncertainty: medium.
- Risks or open questions: rebuild time (see Key Risks).
- Canonical components/API contracts touched: `ReadModelSchema` posts table, `ReadModelBuilder`, `IncrementalReadModelUpdater`.

## Stage 4

**Estimate: 0.06**

- Goal: state in the plain-text API.
- Dependencies: Stage 3.
- Expected changes: `get_post` adds a `Resolution-State` header; `get_thread` marks resolved and closed lines. `llms.txt` and the agent API reference describe the tags and how to set them.
- Verification approach: API text tests; read Agent Accessibility via `get_thread` locally.
- Estimate details: driver: doc wording; uncertainty: low.
- Risks or open questions: clients parsing `get_thread` lines may break on a new marker. Mitigation: reuse the existing marker slot.
- Canonical components/API contracts touched: `ApiTextController::getThread`, `get_post`, `LlmsTxtRenderer`, `docs/reference/agent_api.md`.

## Stage 5

**Estimate: 0.10**

- Goal: Resolved, Closed, Open buttons on replies.
- Dependencies: Stages 2 and 3.
- Expected changes: `post_card.php` shows unlocked buttons beside Like and Flag only on replies in a thread with the parent tag, and only Open when folded. The existing post-tag button script submits them; success refolds, failure shows the error with retry.
- Verification approach: render tests; manual tap in local server, including offline retry.
- Estimate details: driver: button state across folded and open replies; uncertainty: low.
- Risks or open questions: crowded action row on phones. Mitigation: check at 375 px in this stage.
- Canonical components/API contracts touched: `templates/partials/post_card.php`, existing post-tag JS.

## Stage 6

**Estimate: 0.15**

- Goal: folded replies.
- Dependencies: Stage 5.
- Expected changes: a resolved or closed reply renders inside a `<details>` whose summary shows author, date, state label (Resolved and Closed styled differently) and the first words; open replies render as today. Base CSS plus overrides where themes need them.
- Verification approach: screenshots of a folded and expanded reply in light, console and gloss at phone and desktop width; no-JS check.
- Estimate details: driver: sixteen themes; uncertainty: medium.
- Risks or open questions: themes styling `.post-card` children directly. Mitigation: light and console first, then the rest.
- Canonical components/API contracts touched: `post_card.php`, `<details>` pattern from `retract_post_control.php`, theme CSS.

## Stage 7

**Estimate: 0.08**

- Goal: release on zenmemes and close out.
- Dependencies: Stages 1-6.
- Expected changes: concept entries (new tagset; reaction record and post presentation updated); final diff review.
- Verification approach: after deploy and background rebuild, mark the handled Agent Accessibility reports, confirm folding on desktop and iPhone in three themes, check `get_thread`, and confirm an unapproved test key's mark does not count. Record any not-done check.
- Estimate details: driver: production check depends on deploy timing; uncertainty: low.
- Risks or open questions: deploy is done by the maintainer. Mitigation: hand off with exact steps and mark production checks pending until then.
- Canonical components/API contracts touched: none new.

## Concepts Touched

[Reaction record](../../concepts/reaction_record.md) changed (Stages 2-3); [post presentation](../../concepts/post_presentation.md) changed (Stage 6); [effective tags](../../concepts/effective_tags.md) used (Stage 2); new *tagset* entry (Stage 7).

## Study Checkpoint

2026-11-12T06:40:00Z; scope v2/A, starting revision `6d0f9da1c4c12b3a1107d74543227d167c252417`, R1/P2. User explicitly approved Step 2. Whole-cycle **0.65 reaffirmed** after planning verification and the background rebuild. Stage work estimates are separate and not summed. Claude, single exposed rater. Approved Step 3 received 2026-11-12.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
