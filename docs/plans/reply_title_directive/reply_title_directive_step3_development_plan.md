> **Feature plan:** [Step 1](./reply_title_directive_step1_solution_assessment.md) · [Step 2](./reply_title_directive_step2_feature_description.md) · [Step 3](./reply_title_directive_step3_development_plan.md) · [Step 4](./reply_title_directive_step4_implementation_summary.md)

Debug mode: enabled (inherited)
Effort study: `railspike-effort` / `v3/reply_title_directive` — [host ledger](../effort_estimation_trial.md#cycle-v3reply_title_directive); standing enrollment under protocol P2
Extended estimates: enabled (inherited)
**Cycle estimate: 0.85**

# Reply title directive — Step 3: Development Plan

Shared metadata: scope v2 (approved Step 2), baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26`, reference R1, rater Claude Code (single exposed rater, model claude-fable-5-1), stage-work estimates dated 2026-11-03, independently scoped and never summed.

## Completion Contract

- **Normal entry:** ordinary reply composer (inline or dedicated page), prepared or signed reply, body containing `title: New name`, with `FORUM_TITLE_DIRECTIVE_ENABLED` on.
- **End-to-end outcome:** reply posts unchanged; thread title changes on thread page, board listings, Recent, Forte, text API, RSS and static copies; root card signals line reads `Title set by <user>`; every root and reply card lists eligible likers and flaggers by name, identically for anonymous and signed-in viewers.
- **Required recovery:** rejected directive (no authority, flag off, empty value) posts the reply as text with no error; `./v3 rebuild` reproduces titles and signals from canonical records; prior-schema read models rebuild on normal startup; retracting or deleting the source reply withdraws the retitle on the next derived-state update; static pages invalidate as for a label write.
- **Deployment/external verification:** production acceptance is the maintainer replying `title: …` on an own thread on zenmemes.com after deploy and seeing the new title plus `Title set by ilyag`; local evidence does not establish it. Flags default on/off without a deploy step.
- **Release condition:** focused PHP tests pass (parser, flags, authority, write, precedence, rebuild/incremental parity, prior-version rebuild, signals eligibility equals score counts); Feature Flags page shows both flags in order; classic, paned and Forte cards verified in every theme at phone and desktop widths, including no-JavaScript, long actor lists, retracted replies and flag-off; affected shared consumers checked; dependency delta zero; concept entries and spec written.

## Key Risks

- **High risk: signals name reactions the score does not count.** Impact: visible contradiction and exposure of withdrawn or unapproved reactions. Early validation: Stage 5 asserts per-target eligible actor count equals the score reducer's counted set on existing fixtures before any template work. Mitigation: eligibility is computed once in the reducers (approved, not inactive, deduped per identity and tag) and stored, never recomputed per request.
- **High risk: precedence change retitles existing threads.** Impact: silent title changes after deploy. Early validation: local repository inspected 2026-11-03: one thread-subject record, system-authored. Stage 4 test covers a system record beside an existing root subject. Mitigation: only records with `Author-Identity-ID` override a non-empty subject; system records keep fill-empty semantics.
- **Read-model schema change.** Impact: deployed instances with older models must rebuild before new queries run. Early validation: Stage 4 prior-version regression test. Mitigation: one `SCHEMA_VERSION` increment covering both new columns, spec update, `./v3 rebuild` check, per `CONTRIBUTING.md`.
- **Template fan-out and first paint.** Impact: layout shift or viewer-specific HTML on static high-traffic pages. Early validation: Stage 6 renders the shared partial with a long actor list in two themes first. Mitigation: one server-rendered partial; the viewer's own click appends in place only.
- **Per-request record globbing.** Impact: the incremental updater's subject loader globs the whole directory; a page-render path doing the same would not scale. Mitigation: render reads only read-model columns; globbing stays in write/rebuild paths.

## Concepts touched

- [Reaction record](../../concepts/reaction_record.md) — used unchanged; Stage 8 adds the eligibility/signals sentence if behavior shown changes its description.
- [Post presentation](../../concepts/post_presentation.md) — changed in Stage 8: signals line and sources.
- Thread title — new entry `docs/concepts/thread_title.md` in Stage 8: precedence, directive authority, provenance, withdrawal.
- Site feature flags — used unchanged (two registrations); no entry proposed.

## Stage 1 — Subject record provenance and spec

**Estimate: 0.08**

- Goal: thread-subject records can carry author and reply provenance, and the format is documented.
- Dependencies: none.
- Expected changes: `ThreadSubjectRecord` gains nullable `sourcePostId`, `sourcePostSha256`; `ThreadSubjectRecordParser` reads `Source-Post-ID`/`Source-Post-SHA256` (both or neither, thread scope only, same validation as reaction records); new `docs/specs/thread_subject_record_v1.md` modeled on the reaction record spec, documenting `Operation: set`, system versus authored records, precedence and the optional detached signature statement. Existing records remain valid unchanged.
- Verification approach: extend parser tests (valid provenance pair, one header alone rejected, legacy record unchanged); spec reviewed against `CONTRIBUTING.md` human-readable format rules.
- Estimate details: driver: mirroring existing parser conventions; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `ThreadSubjectRecord`, `ThreadSubjectRecordParser`, new spec.

## Stage 2 — Feature flags

**Estimate: 0.08**

- Goal: both flags exist, default correctly and appear in order.
- Dependencies: none.
- Expected changes: `FeatureFlagRegistry` constants `TITLE_DIRECTIVE_ENABLED` (`FORUM_TITLE_DIRECTIVE_ENABLED`, default true, site-mutable) and `TITLE_DIRECTIVE_APPROVED_MEMBERS` (`FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS`, default false, dependent on the first) appended to the `EXPERIENCE` presentation list, parent then child; short labels and one-sentence descriptions; feature flag reference doc updated.
- Verification approach: `FeatureFlagsBehaviorTest` completeness/order/dependency assertions; Feature Flags page renders both in the Forum experience group; toggling through the page writes the change record.
- Estimate details: driver: registry conventions and tests; uncertainty: low.
- Risks or open questions:
  - Impact: dependent flag shown enabled while parent off would mislead. Early warning: existing dependency rendering checks. Mitigation: declare the dependency as sibling flags do.
- Canonical components/API contracts touched: `FeatureFlagRegistry`, Feature Flags page, `site_feature_flags_record_v1` (values only).

## Stage 3 — Directive extraction, authority and write

**Estimate: 0.15**

- Goal: an authorized reply writes an authored subject record in the same commit.
- Dependencies: Stages 1, 2.
- Expected changes: `LocalWriteService::extractTitleDirective(string $body): ?string` using the hashtag line rules (skip quoted lines, first match, case-insensitive `title:` at line start, trimmed one-line value, max length as `Subject` today); new `Security/TitleDirectiveAuthority::allows(string $root, PDO $pdo, PostRecord $root, string $identityId, FeatureFlagEvaluator $flags): bool` (verified root author, or `RetractionAuthority::currentOperator`, or approved member when the second flag is on); `buildThreadSubjectRecord` gains author, provenance and reason parameters; `createReply`, `createPreparedPost` and `createSignedPost` write the subject record beside the label record, include it in the commit, and call `synchronizeThreadSubjectDerivedState`; rejected directives leave the reply untouched.
- Verification approach: new `LocalWriteServiceTitleDirectiveTest`: author retitles; operator retitles; approved member only with flag on; guest and unapproved rejected; flag off rejected; quoted `> title:` ignored; empty value ignored; record round-trips through the parser; reply body byte-identical; signed reply path covered.
- Estimate details: driver: three write paths and authority matrix; uncertainty: verified-author determination for unsigned legacy roots (fall back to `attribution_verified`).
- Risks or open questions:
  - Impact: authority judged from stale read model. Early warning: tests with a freshly approved member. Mitigation: read approval state the way `isApprovedIdentity` does.
  - Impact: directive and hashtags in one reply write two records. Early warning: test. Mitigation: both in one commit, as today for labels.
- Canonical components/API contracts touched: `LocalWriteService` reply paths, new `TitleDirectiveAuthority`, thread-subject record family.

## Stage 4 — Read-model precedence and schema

**Estimate: 0.15**

- Goal: the latest authored set wins everywhere the title is read; schema carries title author and signal eligibility.
- Dependencies: Stage 1.
- Expected changes: `ReadModelMetadata::SCHEMA_VERSION` 43→44; `threads` gains nullable `subject_author_identity_id`; `viewer_tags` gains `eligible` integer default 0 (populated in Stage 5); `ReadModelBuilder::indexThreadSubjects` and `IncrementalReadModelUpdater::deriveThreadSubjectState` apply: authored records (with `Author-Identity-ID`, source present and not retracted or removed) latest-wins over any subject; system records fill only an empty subject; `posts.subject` for the root updated alongside; `docs/specs/read_model_schema_v1.md` updated.
- Verification approach: `ReadModelThreadSubjectsTest` extended (authored overrides root subject; later authored wins; system never overrides; withdrawn source ignored; incremental equals rebuild); new prior-version startup rebuild regression test per `CONTRIBUTING.md`; `./v3 rebuild` on the local repository leaves the single existing system record's behavior unchanged.
- Estimate details: driver: two reducers plus schema contract; uncertainty: ordering ties between same-second records (record ID tiebreak as today).
- Risks or open questions:
  - Impact: deployed model lacks new columns. Early warning: regression test. Mitigation: startup rebuild on version mismatch, already the contract.
- Canonical components/API contracts touched: `ReadModelMetadata`, `ReadModelSchema`, `ReadModelBuilder`, `IncrementalReadModelUpdater`, read-model spec.

## Stage 5 — Signal eligibility and actor query

**Estimate: 0.12**

- Goal: a cheap read-model query returns, per target, the eligible likers and flaggers and the title author, with usernames.
- Dependencies: Stage 4.
- Expected changes: thread-label and post-reaction reducers (rebuild and incremental) set `viewer_tags.eligible` for exactly the records they count toward score or flag count (approved author, not inactive, first per identity and tag); new `ReadModel/SignalActors::forPosts(PDO $pdo, array $postIds, string $threadId): array` returning per post `like` and `flag` actor lists (username, profile slug) and the thread's title author, joined through `profiles`, ordered by record time.
- Verification approach: new test asserting eligible like count equals `post_score_total` contribution and eligible flag count equals `approved_flag_count` on the existing reaction fixtures, including a retracted-source label and an unapproved liker; incremental write after a Like matches rebuild.
- Estimate details: driver: aligning with two existing reducers' dedupe keys; uncertainty: verified-attribution mode (`FORUM_VERIFIED_ATTRIBUTION`) selects claimed versus verified sets; eligibility follows the effective set.
- Risks or open questions:
  - Impact: verified-attribution flag flip leaves eligibility stale. Early warning: existing startup swap logic. Mitigation: recompute eligibility in the same startup path that swaps effective scores.
- Canonical components/API contracts touched: `ReadModelBuilder`, `IncrementalReadModelUpdater`, new `SignalActors`.

## Stage 6 — Signals line UI

**Estimate: 0.20**

- Goal: every root and reply card shows the signals line; the viewer's own click updates it in place.
- Dependencies: Stage 5.
- Expected changes: new shared partial `templates/partials/post_signals.php` (`Liked by a, b, c +N · Flagged by d · Title set by e`, names linking to profiles, hidden when empty); included by `thread_root_card.php`, `post_card.php`, `paned_thread_reply_tree.php` and `paned_board_content_article.php`; `ThreadAndPostPageController` and the paned/Forte controllers pass `SignalActors` results; `thread_reactions.js` appends the viewer's name after a successful Like or Flag; CSS in the shared stylesheet with checks in theme files; no viewer-specific server markup; `qdb_quote_actions.php` untouched.
- Verification approach: template tests for the four inclusions and the empty state; browser test (`tests/browser`) across all themes at phone and desktop: long actor list wraps without shifting the main action, no-JavaScript renders the same line, own click appends once; UI checklist entry in `docs/plans/ui_copy_audit_checklist.md`.
- Estimate details: driver: four card variants and thirteen themes; uncertainty: paned layout width for long lists.
- Risks or open questions:
  - Impact: copy repeats the Like button state. Early warning: review against `AGENTS.md` UI copy rule. Mitigation: the line is the only place names appear; buttons keep their current text.
  - Impact: layout shift after load. Early warning: compose layout stability browser test pattern. Mitigation: server-rendered, fixed on first paint.
- Canonical components/API contracts touched: new `post_signals.php`, four card templates, `thread_reactions.js`, thread/paned/Forte controllers.

## Stage 7 — Withdrawal, deletion and shared consumers

**Estimate: 0.12**

- Goal: a retracted or deleted source reply withdraws its retitle, and every title surface and static copy updates.
- Dependencies: Stages 3, 4.
- Expected changes: `LabelOwnership`-equivalent check for subject records (`SubjectOwnership::inspect`, or a generalized helper) used by the reducers from Stage 4; `SourceLabelRemoval` and `RetractionDeletion` include matching authored subject records in removal evidence so `delete-record` cleans them as it cleans labels; confirm `StaticArtifactInvalidator::invalidateBoardThread` covers thread, listings, Recent, Forte, RSS and offline snapshot paths, adding any missing path; text API and import catalog already read the family.
- Verification approach: tests: retract source reply → title reverts to previous effective subject on incremental update and on rebuild; delete source → subject record removed with evidence; static artifact paths deleted after retitle; Forte/API/RSS/Recent show the new title in an HTTP smoke test.
- Estimate details: driver: fitting into the removal evidence format; uncertainty: evidence format fields for a non-label family.
- Risks or open questions:
  - Impact: orphaned authored subject records after deletion. Early warning: deletion test. Mitigation: reducers also ignore records whose source is absent, so an orphan is inert even if cleanup fails.
- Canonical components/API contracts touched: `SourceLabelRemoval`, `RetractionDeletion`, removal evidence, `StaticArtifactInvalidator`.

## Stage 8 — Acceptance, known-user replay and closeout

**Estimate: 0.12**

- Goal: replay the maintainer's flow, finish documentation and record the study closeout.
- Dependencies: Stages 1–7.
- Expected changes: `docs/concepts/thread_title.md` created; `docs/concepts/post_presentation.md` updated; register entry PF-20261103-F01 moved to blocked (deployment pending) with local evidence; ledger closeout; Step 4 summary; dependency delta reported.
- Verification approach: local replay of the known-user flow through the ordinary composer: author replies `title: …` on own thread with flags at defaults, title changes on thread, board, Recent and Forte, root card shows `Title set by <user>`; same reply by a second approved identity is ignored until the second flag is turned on through the Feature Flags page, then works; flag off makes the line plain text. Full focused suite run; broader suite failures reproduced on baseline listed separately. Production acceptance explicitly recorded as pending.
- Estimate details: driver: browser matrix and documentation; uncertainty: pre-existing suite failures unrelated to this work.
- Risks or open questions:
  - Impact: local pass mistaken for production acceptance. Early warning: Step 2 known-user condition. Mitigation: register stays blocked until the maintainer confirms on zenmemes.com.
- Canonical components/API contracts touched: concept inventory, register, ledger, Step 4 summary.

## Execution and study checkpoint

2026-11-03T08:53:36Z: Step 2 explicitly approved ("approved step 2"), no new scope. Eight stages planned, zero implemented. Scope v2, baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26`, R1 (hashes verified at Step 1), P2. Step 3 projected total **0.85 reaffirmed**, independently judged; stage-work originals S1 0.08, S2 0.08, S3 0.15, S4 0.15, S5 0.12, S6 0.20, S7 0.12, S8 0.12, not additive. Shared schema change owned by Stage 4; shared UI acceptance owned by Stage 8. Claude Code single exposed rater. No implementation, measured effort or accepted outcome; await Approved Step 3; records uncommitted. Enhancement request "suggested titles from unauthorized repliers" remains pending and out of scope.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Step 3 approval

2026-11-03T17:10:05Z: User: "approved step 3". Plan approved unchanged; feature branch `feat/reply-title-directive`, planning artifacts organized into `docs/plans/reply_title_directive/`. Implementation authorized for the eight planned stages.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*
