> **Feature plan:** [Step 1](./tag_unlocks_step1_solution_assessment.md) · [Step 2](./tag_unlocks_step2_feature_description.md) · [Step 3](./tag_unlocks_step3_development_plan.md) · [Step 4](./tag_unlocks_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/tag_unlocks`; [host ledger](../effort_estimation_trial.md#cycle-v3tag_unlocks). Standing P2 enrollment.

Extended estimates: enabled (inherited)

Branch: `tag-unlocks`, from `origin/main` at `338215268` (main moved on from the Step 1 starting revision `6d0f9da1`). Planning commit `36cfc6e03`.

**Cycle estimate: 0.65** (Step 4 entry: Step 3 projection reaffirmed after the planning commit)

Stage estimates below are copied from the approved Step 3 plan; copying is not a new rating.

## Stage 1 - Tagset file and loader

**Original estimate: 0.08**

**Retrospective stage work: 0.07**

- Changes:
  - `resources/tagsets.txt`: `Key: value` blocks (Parent, Target, Unlocks, Exclusive) instead of the one-line format sketched in Step 3, following the `AGENTS.md` human-readable-format rule. Ships `meta` → resolved, closed, open, exclusive, on replies.
  - `src/ForumRewrite/Support/Tagsets.php`: `parse`, `fromFile`, `forApplication`, `unlockedFor`, `isUnlockable`, `exclusiveGroupOf`, `exclusiveTags`. Missing file, malformed block, unknown target or empty Unlocks unlock nothing.
  - Feature flag `FORUM_TAG_UNLOCKS_ENABLED` (site-mutable, default on, appended to the Experience group); added to the registry test lists.
  - `tests/TagsetsTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test --smoke TagsetsTest FeatureFlagEvaluatorTest`: 452 run, 452 passed.
  - `./v3 test FeatureFlagsBehaviorTest`: `testRenderedOrderIgnoresRegistryInputOrder` fails with a fatal in `tools_nav_card.php`; it fails the same way on `origin/main` without these changes (baseline failure, not caused here).
  - Full suite not run.
- Notes: the flag is defined but nothing reads it yet; Stages 2 and 5 gate on it.
- Estimate details: original scope unchanged; uncertainty low; no revisions.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 2 - Accept unlocked tags on replies, with repeat marks

**Original estimate: 0.12**

**Retrospective stage work: 0.13**

- Changes:
  - `LocalWriteService::applyPostTag` checks `unlockedPostTag()` before the ordinary tag check. An unlockable tag (with the flag on) is accepted only on a reply whose thread carries a parent tag; the refusal names the parent (`tag closed applies only to replies in threads tagged #meta.`). Thread roots are refused.
  - `threadTagsForUnlocks()` reads the thread's board tags and labels through `FastmodMetaRead::forApplication`, on its own read-model connection, so a Fastmod `meta` counts.
  - Exclusive-group tags skip the one-per-key guard, so Resolved, Open, Resolved writes three records. Replays of one offline intent (`intent_id`, or `Intent-ID` in a signed record) still write once; `hasPostTagFromIdentity` gained an optional intent match. Ordinary tags keep the one-per-key rule.
  - `Tagsets::parentsOf()` for the refusal text.
  - `tests/TagUnlocksTest.php` (registered in `tests/run.php`).
- Verification:
  - `./v3 test TagUnlocksTest TagsetsTest`: all pass (meta-only, roots refused, unknown tag refused, repeat marks, scores unchanged, intent replay, unapproved key accepted with `viewer_is_approved=no`, flag off).
  - First focused run of `WriteApiSmokeTest` found five failures from an eager signed-intent lookup that threw on records without `Intent-ID`; fixed by reading it only for state tags, without throwing. The five reran and passed, plus `ReactionOutboxTest`.
  - `./v3 test --smoke WriteApiSmokeTest`: 660 run, 660 passed. Full suite not run.
- Notes: Scores already ignore non-scored tags in both read-model builders, so no score change was needed. Offline replays are ordered by tap time in Stage 3, so a late resend cannot override a later mark.
- Estimate details: original scope unchanged plus intent-replay dedupe (new understanding, not new scope); uncertainty was medium, resolved.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 3 - Per-reply state in the read model

**Original estimate: 0.12**

**Retrospective stage work: 0.10**

- Changes:
  - Read model schema 55: `posts.resolution_state`, `resolution_set_at`, `resolution_set_by` (`ReadModelSchema`, `ReadModelMetadata`); documented in `docs/specs/read_model_schema_v1.md`.
  - New `ReadModel\PostResolution::derive(records, approvedIds)`: latest exclusive-group mark by an approved key, ordered by Action-At, then Created-At and record ID; `open` stores null.
  - `ReadModelBuilder` (full rebuild) and `IncrementalReadModelUpdater::derivePostReactionState` / `updatePostReactions` fill the columns. The incremental path covers mark writes and `refreshApprovalSensitivePostReactions`, so approval changes recount.
- Verification:
  - `./v3 test TagUnlocksTest`: 8 passed, including latest-wins across incremental writes and a rebuild, a late offline send of an earlier tap not overriding a later mark, a later Open unfolding, an unapproved mark ignored until approval, and a removed latest mark falling back.
  - `./v3 test --smoke` plus read-model classes (`ReadModelPostReactionsTest`, `ReadModelPostRetractionsTest`, `ReadModelSchemaTest`, `ReadModelMetadataTest`, `ReadModelDropSchemaTest`, `ReadModelSignalEligibilityTest`, `ReadModelViewerTagsProjectionTest`, `ReadModelViewerTagsRebuildTest`, `PostSignalsTemplateTest`, `ReadModelBuilderTimingTest`): 463 and 26 run, all passed. Full suite not run.
  - Rebuild timing on a production-sized snapshot: **pending**. No production snapshot is available in this workspace. The added work is one pass over each post's reaction records already loaded, so it should not change rebuild time much; confirm during the Stage 7 deploy.
- Notes: the schema bump forces a rebuild on the next startup after deploy; see Stage 7.
- Estimate details: original scope unchanged; uncertainty medium, reduced to the pending timing check.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 4 - State in the plain-text API

**Original estimate: 0.06**

**Retrospective stage work: 0.06**

- Changes:
  - `ApiTextController`: `get_post` adds `Resolution: resolved|closed` after `Body-Format` for a marked reply; `get_thread` prefixes the reply line with `(resolved) ` or `(closed) ` in the existing marker slot (retracted still wins). Both are hidden when the flag is off.
  - `posts.resolution_state` added to the post selects in `Application::fetchThreadPosts`, `Application::fetchPost` and `ThreadRepository` (also used by the thread page in Stage 5).
  - `LlmsTxtRenderer`: short "Reply resolution" section. `docs/reference/agent_api.md`: "Reply resolution (tag unlocks)" section.
- Verification:
  - `TagUnlocksTest::testTextApiReportsResolution`: header and marker appear after a mark, absent before.
  - `./v3 test --smoke LlmsTxtRendererTest TagUnlocksTest AgentApiWorkedExampleTest PlatformDocsCatalogTest`: 471 run, 471 passed. Full suite not run.
- Notes: open replies get no header, so clients that parse `get_post` headers see no change for ordinary posts.
- Estimate details: original scope unchanged; uncertainty low.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 5 - Resolved, Closed and Reopen buttons on replies

**Original estimate: 0.10**

**Retrospective stage work: 0.12**

- Changes:
  - `TemplateRenderer` passes `tagUnlocksEnabled` to templates.
  - `post_card.php`: on a reply whose thread carries a parent tag (board tags plus labels, read through the Fastmod overlay connection), renders `partials/post_unlocked_tags.php` after Like and Flag, and sets `data-resolution` on the card for a resolved or closed reply. Thread roots, retracted replies and the single-post page get no buttons.
  - New `partials/post_unlocked_tags.php`: one button per unlocked tag, through the existing `apply-post-tag` action. State buttons carry `data-state-tag`; an open reply shows Resolved and Closed, a marked one shows Reopen (tag `open`). All three are rendered and swapped with `hidden`, so no reload is needed. Non-state unlocked tags (none shipped) would render as ordinary tag buttons.
  - `thread_reactions.js`: state buttons are not remembered or kept pressed. On success an approved key's mark updates `data-resolution` and swaps the buttons (`applyPostResolution`, which also dispatches `forum:post-resolution` for Stage 6); an unapproved key sees "Saved. Only approved members' marks change a reply's state." A failed tap restores the button and shows the error; a retryable failure stays in the Outbox as before.
  - Browser test `tests/browser/tag_unlocks.mjs` with fixture `tests/Support/tag_unlocks_browser_fixture.php`.
- Verification:
  - `TagUnlocksTest::testThreadPageOffersStateButtonsOnlyOnMetaReplies`: buttons only on `#meta` replies, swap after a mark, none on a non-meta thread.
  - `./v3 test --smoke TagUnlocksTest PostSignalsTemplateTest ReactionOutboxTest ReactionRecordFormatParityTest TemplateRendererMediaEmbedsScriptTest`: first run failed three `ReactionOutboxTest` cases because their DOM stubs have no `hasAttribute`; switched to `getAttribute`, and `ReactionOutboxTest` reran 5/5. Full suite not run.
  - `node tests/browser/tag_unlocks.mjs` (Playwright, Chromium): an approved viewer taps Resolved, the card gets `data-resolution="resolved"` and Reopen appears; it survives a reload; Reopen clears it and that survives a reload. Closed button inside the viewport in light, console and gloss at 375 px and 1280 px. Passed three runs in a row after the test waited for page load before tapping.
  - Offline retry: not driven in the browser; the Outbox path is shared with Like and Flag (covered by `ReactionOutboxTest`), and state buttons only skip the "keep applied" step.
- Notes: the fixture's replies share an author, so they render as one merged run; Stage 6's browser check uses a second author.
- Estimate details: original scope unchanged; uncertainty low. Slightly over because of the test-stub fix and browser test flakiness.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 6 - Folded replies

**Original estimate: 0.15**

**Retrospective stage work: 0.12**

- Changes:
  - `post_card.php`: a reply that can take a state wraps its content in `<details class="post-resolution">`, open unless the reply is resolved or closed. The summary shows the state label, author, date (`M j`) and the first 90 characters of the body. Open replies hide the summary, so they look as before.
  - `content-interactions.css`: the summary is hidden on open replies; the state label is solid green for Resolved and dashed muted for Closed; the summary line is one line with an ellipsis; the folded card hides its `#` permalink; the first line inside the fold keeps the card's top spacing. The hover reveal for same-author runs also reaches actions inside the fold.
  - `theme-word97.css`, `theme-midnight.css`: their title-bar byline (`.post-card > .meta:first-child`) also matches the byline inside the fold.
  - `thread_reactions.js`: `applyPostResolution` opens or closes the fold and updates the label after a tap.
  - Browser fixture: the root and the closed reply are anonymous, so the test shows separate cards.
- Verification:
  - `node tests/browser/tag_unlocks.mjs`: without JavaScript the closed reply is folded and expands on tap, and the open reply shows in full. With JavaScript, tapping Resolved folds the reply, tapping the line expands it, and Reopen unfolds it, each surviving a reload. Folded line on one line inside the viewport and closed reply folded in all 16 themes (light, dark, console, lcd, chicago, vapor, forge, sticker, arena, thermal, whitehot, word97, midnight, chouse, gloss, engagify) at 375 px and 1280 px; contact sheet reviewed by eye. Passed three more single-theme runs.
  - `./v3 test --smoke TagUnlocksTest PostSignalsTemplateTest ReactionOutboxTest NavIconSetsTest`: 466 run, 466 passed. Full suite not run.
- Notes: in word97 a folded same-author continuation keeps some empty space under the line; separate cards look right. The fold summary is plain text, so a Markdown reply's excerpt shows its raw marks.
- Estimate details: original scope unchanged; uncertainty medium (sixteen themes), resolved with only two theme files needing a selector.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 7 - Release on zenmemes and close out

**Original estimate: 0.08**

**Retrospective stage work: 0.05 so far** (production verification pending)

- Changes:
  - New concept entry `docs/concepts/tagset.md`; `reaction_record.md` and `post_presentation.md` updated.
  - Final diff review of `origin/main..tag-unlocks`: 28 non-plan files, all within the approved scope; `git diff --check` clean. No new dependencies.
- Verification:
  - Production, **pending (maintainer deploy)**: after deploy, the next startup rebuilds the read model (schema 55). Then mark the handled Agent Accessibility reports, check they fold on desktop and iPhone in three themes, check `get_thread` shows the markers, and confirm an unapproved key's mark leaves the reply open. Rebuild time on the production snapshot is pending too.
  - Phone verification on a real iPhone: **not done** (Chromium at 375 px only).
  - Feedback items: `docs/feedback/items/` has no matching item; PF-20261104-F02 (hide posts with expandable placeholders) is related but different, so no `Feedback:` trailer.
- Notes: `llms.txt` describes the feature even when the flag is off; the API itself hides the state then.
- Estimate details: original scope unchanged; the production part is pending, so this retrospective covers only the work done.

## Cycle retrospective

- Delivered: Stages 1-7 as planned; production verification pending. Starting revision `338215268` (main at branch time; Step 1 recorded `6d0f9da1`).
- **Retrospective cycle assessment: 0.65**, matching the projection. Not measured time.
- Additions inside scope: offline-intent dedupe for repeat marks, and theme selectors for the fold in word97 and midnight.
- Corrective work: eager signed-intent lookup (Stage 2), test-stub `hasAttribute` (Stage 5), browser test load timing (Stage 5).
- Checks run: focused classes per stage plus the smoke set; `tests/browser/tag_unlocks.mjs` in all 16 themes. Full suite not run. Baseline failure seen on main: `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder`.
- Maintainer comfortable-fit judgment: **pending**.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
