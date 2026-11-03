> **Feature plan:** [Step 1](./reply_title_directive_step1_solution_assessment.md) · [Step 2](./reply_title_directive_step2_feature_description.md) · [Step 3](./reply_title_directive_step3_development_plan.md) · [Step 4](./reply_title_directive_step4_implementation_summary.md)

Debug mode: enabled (project marker `.railspike_debug_on` present at workflow start; snapshot 2026-11-03T05:19:48Z)
Effort study: `railspike-effort` / `v3/reply_title_directive` — [host ledger](../effort_estimation_trial.md#cycle-v3reply_title_directive); standing enrollment under protocol P2
Extended estimates: enabled (new-cycle default)
**Cycle estimate: 0.85** (revised from 0.80, see Revised direction)

# Reply title directive — Step 1: Solution Assessment

## Original Query

Feature request: I want to be able to comment "title: some text here" and if I have the authority, change the title of the post. It can be rolled into the same visibility layer as likes and flags (which should be visible, currently are not really).

Source: [thread-20261103051304-2fa9fbd6](https://zenmemes.com/threads/thread-20261103051304-2fa9fbd6), posted by ilyag on 2026-11-03T05:13:04Z. Register entry [PF-20261103-F01](../production_feedback_register.md#pf-20261103-f01).

## Understood Intent

- A reply whose body contains a `title: …` line retitles its thread when the replier is allowed to. The reply still posts as an ordinary comment, text intact.
- The retitle belongs in the same on-thread visibility layer as likes and flags, and that layer has to exist first: thread and reply cards today show only the viewer's own Like/Flag button state. Counts and actors appear only in Activity.
- "Post" is read as the thread (root post). Replies have no titles.

## Problem statement

Nobody can change a thread's title after creation, and readers cannot see who liked or flagged a thread or reply, so there is no place to show a retitle either.

## Context found

- `records/thread-subjects/` already exists: `ThreadSubjectRecord` with `Operation: set`, written only by the system media-title fetch (`LocalWriteService::setThreadSubjectIfEmpty`) and applied only while the root subject is empty; earliest record wins (`ReadModelBuilder::indexThreadSubjects`, `IncrementalReadModelUpdater::applyThreadSubjectWrite`). No format spec exists in `docs/specs/`; import and archive catalogs already cover the family.
- Reply bodies already mutate thread state: `#hashtag` lines become thread-label reaction records with `Source-Post-ID`/`Source-Post-SHA256` provenance in the same commit as the reply, quoted lines skipped (`extractThreadLabelsFromBody`); `LabelOwnership` ties the label to its source reply for retraction cleanup.
- Deterministic body-line precedent: `Approve-Identity-ID:` in approval replies.
- Authority primitives: verified post attribution (`attribution_verified`), `ApprovalPolicy::isOperator`, `isApprovedIdentity`. No thread-owner permission check exists yet.
- Visibility data already in the read model: `post_score_total`, `approved_flag_count`, `threads.score_total`, per-record reaction rows. Templates render only `Labels:` in the meta line plus viewer-state buttons (`thread_root_card.php`, `post_card.php`, `paned_thread_reply_tree.php`). Activity lists `thread_label_add` and `post_reaction_add` items.
- Server HTML must be identical for every viewer (`CONTRIBUTING.md`), so a who-liked list is a server-rendered fact, not viewer-specific UI.

## Concepts touched

- [Reaction record](../../concepts/reaction_record.md) — used unchanged (A); presentation changed (B, C).
- [Post presentation](../../concepts/post_presentation.md) — changed in every option (meta/signals line).
- Thread title (proposed, no entry yet) — created: subject-record precedence, authority, reply provenance.
- Reply directive (proposed, no entry yet) — created only by Option C.
- Site feature flags ([record spec](../../specs/site_feature_flags_record_v1.md), registry `FeatureFlagRegistry`) — used unchanged in v2: two new flags registered, no mechanism change.

## Options

### Option A — Title directive only, shown in the existing meta line

**Estimate: 0.45**

Parse one `title: …` line in new replies with the hashtag rules (not inside quoted lines, first match wins, case-insensitive key, single-line value). If the replier is the thread's verified author or a current operator, write a thread-subject `set` record carrying `Author-Identity-ID` and reply provenance in the same commit as the reply, and change the reducer to "latest authored set wins; system-fetched sets still fill only an empty subject". The thread root meta line gains `Title set by <user>`. Unauthorized directives post as plain text and change nothing.

- Pros: smallest slice; reuses the existing record family, provenance pattern and incremental updater; no read-model schema change; adds the missing spec.
- Cons: leaves the visibility half of the request undone; the retitle shows only in the meta line and Activity.
- Driver: reducer precedence and authority rules. Uncertainty: static-artifact invalidation for board listings that cache the old title.

### Option B — Option A plus a thread signals layer (recommended)

**Estimate: 0.80**

Option A, plus one server-rendered signals line on thread root and reply cards listing likes, flags and title changes with actors, for example `Liked by a, b · Flagged by c · Title set by a`, built from read-model data that already exists and updated in place after the viewer's own Like or Flag click. Retitles without authority show nothing extra; the comment itself is the record. Covers classic, paned and Forte card variants and every theme.

- Pros: delivers the request as stated; likes and flags become visible to every reader; one place for all three signals; static pages stay viewer-independent.
- Cons: template work across three card variants and all themes; needs a copy rule for long actor lists; approved-only counting must be shown honestly.
- Driver: three card variants and theme verification. Uncertainty: paned/Forte layouts and high-traffic static pages.

### Option C — Generic reply directives plus signals layer

**Estimate: 1.10**

Option B, but `key: value` lines become a general directive mechanism (`title:`, `label:`, later `pin:` and so on) with a directive registry, per-directive authority and a shared signals renderer.

- Pros: one model for future comment-driven moderation.
- Cons: speculative scope; above 1.0, so it would need a split; labels already have hashtags; conflicts with cutting anything unnecessary.
- Driver: registry design and authority matrix. Uncertainty: which future directives would actually be wanted.

## Recommendation

Option B as one cycle. Option A is its first vertical slice (reply → record → title changes → meta line) and the signals layer is the second slice of the same cycle; each is independently releasable. Decisions for the user before Step 2:

1. Authority set: thread author or current operator (proposed), or also any approved member?
2. Unauthorized directive: plain text with no effect (proposed), or shown as `Title suggested by <user>`?
3. Signals copy: up to three names then `+N`, counting approved members only, matching today's scoring (proposed).

Not in scope: retitling replies, removing titles, editing bodies, a settings UI, QDB quote cards.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Revised direction — 2026-11-03 UTC

User answers to the three decisions (the original options and recommendation above are retained unchanged):

1. **Authority.** Option B stays, with two site feature flags in the Forum experience group, following the registry's presentation-order rule:
   - `FORUM_TITLE_DIRECTIVE_ENABLED`, default **on**: a `title:` line in a reply can retitle its thread at all. Off: the line is plain text everywhere; existing authored subject records stay as they are.
   - `FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS`, default **off**: off means only the thread's verified author or a current operator may retitle; on means any approved member may. This is the user's "operators and own only / approved only" switch; the dependent flag sits directly after its parent.
2. **Unauthorized `title:` line.** Out of scope. The reply posts unchanged and nothing else happens. Follow-up recorded under Enhancement Requests for a later "suggested title" extension.
3. **Signals copy and timing.** Read as: keep the proposed copy rule (up to three names, then `+N`, approved-member counting matching today's scoring) and apply retitles immediately with no pending state. If "same as 2" meant the signals layer itself is out of scope, say so and the cycle drops to Option A at 0.45.

Scope v2 = Option B plus the two flags. The flags add registry entries, Feature Flags page ordering checks, record spec notes and documentation; projected total moves from 0.80 to **0.85** as a scope change, not new understanding.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Effort snapshots and approval

- Study `railspike-effort`, cycle `v3/reply_title_directive`, protocol P2, reference R1 (three frozen hashes verified 2026-11-03T05:19:48Z), scope v1, baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26` on `main`. Rater: Claude Code, single exposed rater; no independent rating.
- Intake: no pristine intake. Source inspection of the subject record family, hashtag labels, reaction templates and read model preceded any rating; the first number below is post-inspection and is disclosed as such.
- Step 1 revision, 2026-11-03T05:29:44Z: scope v2 (Option B plus two feature flags) projected total **0.85**; reason: scope change from user decisions. Option alternatives unchanged at A 0.45, B 0.80 (v1), C 1.10. Still no implementation, measured effort, approval or accepted outcome.
- Step 1 handoff, 2026-11-03T05:19:48Z: recommended Option B projected total **0.80**. Option whole-cycle alternatives A **0.45**, B **0.80**, C **1.10**, independently judged, not additive. No implementation, measured effort, approval or accepted outcome.

## Enhancement Requests

- **Suggested titles from unauthorized repliers** (user, 2026-11-03): show or queue a `title:` line from someone without authority as a suggestion. Disposition: out of scope for this cycle; recorded here as the follow-up. Not added to `todo.txt`, which this project edits only on explicit request.

## Step 1 approval

2026-11-03T05:34:01Z: User: "reading: confirmed. approved step 1". Scope v2 (Option B plus two feature flags, immediate retitles, unauthorized directives out of scope) approved; proceed to Step 2 only.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*
