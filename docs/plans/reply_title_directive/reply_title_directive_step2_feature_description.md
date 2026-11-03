> **Feature plan:** [Step 1](./reply_title_directive_step1_solution_assessment.md) · [Step 2](./reply_title_directive_step2_feature_description.md) · [Step 3](./reply_title_directive_step3_development_plan.md) · [Step 4](./reply_title_directive_step4_implementation_summary.md)

Debug mode: enabled (inherited)
Effort study: `railspike-effort` / `v3/reply_title_directive` — [host ledger](../effort_estimation_trial.md#cycle-v3reply_title_directive); standing enrollment under protocol P2
Extended estimates: enabled (inherited)
**Cycle estimate: 0.85**

# Reply title directive — Step 2: Feature Description

## Problem

A thread's title cannot be changed after it is posted, and readers cannot see who liked or flagged a thread or reply. The approved direction ([Step 1, Option B, scope v2](./reply_title_directive_step1_solution_assessment.md#revised-direction--2026-11-03-utc)) lets an authorized reply retitle its thread and shows likes, flags and title changes together on the thread.

## User stories

- As a thread author, I want to reply with `title: Better name` so that my thread gets a proper title without editing or reposting it.
- As an operator, I want the same reply to work on any thread so that I can fix misleading or empty titles in the normal conversation flow.
- As a reader, I want to see who liked, flagged or retitled a thread or reply, signed in or not, so that reactions mean something and title changes are not silent.
- As an operator, I want two site feature flags so that I can turn the directive off entirely, or widen authority from author/operator to any approved member, without a deploy.

## Core requirements

1. A reply whose body contains a `title:` line (same scanning rules as hashtags: not inside quoted lines, first match wins, key case-insensitive, one-line value, leading/trailing space trimmed) sets the thread's title when the replier has authority and `FORUM_TITLE_DIRECTIVE_ENABLED` is on. The reply posts unchanged in every case, text intact.
2. Authority: the thread root's verified author or a current operator; with `FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS` on, any approved member. Unauthorized or flag-off directives have no effect and no extra UI.
3. The retitle is an authored thread-subject `set` record with reply provenance, written in the same commit as the reply. Latest authored set wins everywhere the title is shown; system-fetched media titles keep filling only an empty subject; a retracted or deleted source reply withdraws its retitle the way hashtag labels are withdrawn today.
4. Every thread root and reply card shows one server-rendered signals line: likes and flags with actor names (up to three, then `+N`) and `Title set by <user>`, counting the same records today's scores count. Server HTML is identical for every viewer; the viewer's own Like or Flag click updates the line in place.
5. Both flags appear in the Feature Flags page's Forum experience group, the dependent flag directly after its parent, with registry completeness and order checks kept current. The thread-subject record format gets its spec in `docs/specs/`.

## Delivery scope

Work type: application change. This handoff authorizes requirements review only, not implementation.

Includes: reply write path and directive parsing, thread-subject authority and precedence, read-model subject derivation (rebuild and incremental), signals line in the classic, paned and Forte card variants with theme coverage, flag registration, record spec, concept entries, and the production feedback register and ledger bookkeeping. No new third-party code.

Excludes: retitling replies, removing titles, title suggestions from unauthorized repliers (recorded follow-up), QDB quote cards, body editing, a settings UI beyond the two flags, changes to scoring or approval semantics, and historical rewriting of existing subject records.

## Completion boundary

- **Normal entry:** the ordinary reply composer (inline or dedicated page), signed or prepared reply, on a thread page.
- **End-to-end outcome:** after the reply posts, the thread page, board listings, Recent, Forte, text API, RSS and static copies show the new title; the thread root's signals line shows `Title set by <user>`; likes and flags on every card show their actors.
- **Recovery:** a directive the writer rejects (no authority, flag off, empty value) still posts the reply as text with no error; a read-model rebuild from canonical records reproduces the same titles and signals; static pages for the thread and listings are invalidated as for a label write; retracting the source reply withdraws the retitle on the next derived-state update.
- **Release condition:** focused PHP tests for parser, authority, precedence, rebuild/incremental parity and flags pass; both flags verified on the Feature Flags page in order; the three card variants verified across all themes at phone and desktop widths, including no-JavaScript, long actor lists, retracted replies and the flag-off state; affected shared consumers checked; dependency delta reported as zero.

## Known user or environment

The request comes from the maintainer on zenmemes.com (production). Their ordinary entry is replying on a thread they authored with `title: …`. Failure to handle: the title does not change, or the reply is lost. Unaided next action: reload the thread, confirm the reply posted, check the two flags on the Feature Flags page, and retry the reply. Success evidence: the thread title changes on the thread page and board listing, and the root card reads `Title set by ilyag`. Local verification does not establish production behavior; deployment and a production retry remain the acceptance evidence.

## Risks

- **Precedence change alters existing titles.** Today the earliest subject record applies only to empty subjects. Impact: moving to latest-authored-wins could change titles on threads that already have system-fetched records. Earliest validation: enumerate existing `records/thread-subjects/` on the real repository at Step 3 planning and confirm all are system-authored. Mitigation: only records with `Author-Identity-ID` override an existing subject; system records keep fill-empty semantics.
- **Signals counts disagree with scores.** The `viewer_tags` projection has no inactive or retraction exclusion, while scores do. Impact: a signals line that names withdrawn or unapproved reactions contradicts the number. Earliest validation: compare a signals query to the score reducer on existing fixtures before Step 3 is finalized. Mitigation: derive actors from the same eligible-record set the score uses, or extend the projection within the read-model schema rules if needed.
- **Template fan-out and first paint.** Three card variants, thirteen themes, static high-traffic pages. Impact: layout shifts or viewer-specific markup. Earliest validation: render one variant with long actor lists in two themes early in Step 4. Mitigation: a single shared partial, server-rendered, with the viewer's own click appending in place only.
- **Static pages keep the old title.** Impact: stale titles on listings after a retitle. Earliest validation: check `invalidateBoardThread` coverage against every surface in the inventory. Mitigation: reuse the label-write invalidation path; add any missing path there.

## Shared component inventory

- Title rendering: `ThreadTitle::displayTitle` and its callers in `Application.php`, `BoardPageController.php`, `ForteBoardController.php`, `ForteContentAndUserDetailApiController.php`, `RecentPageController.php`, `ComposeAndAccountKeyController.php`, `TemplateRenderer.php` — reused unchanged; they read `threads.subject`, which the reducer already feeds.
- Subject records: `ThreadSubjectRecord`/`ThreadSubjectRecordParser`, `LocalWriteService::buildThreadSubjectRecord`, `ReadModelBuilder::indexThreadSubjects`, `IncrementalReadModelUpdater::applyThreadSubjectWrite` — extended (authored records, provenance, precedence). No new record family.
- Reply write paths: `createReply`, `createPreparedPost`, `createSignedPost` and `extractThreadLabelsFromBody` — extended with directive extraction beside hashtag extraction; provenance reuses `Source-Post-ID`/`Source-Post-SHA256` and `LabelOwnership`-style withdrawal.
- Reaction UI: `thread_root_card.php`, `post_card.php`, `paned_thread_reply_tree.php`, `paned_board_content_article.php`, `thread_reactions.js` — extended with one new shared signals partial, because no existing partial renders actor lists; `qdb_quote_actions.php` excluded.
- Reaction data: `viewer_tags`, `post_score_total`, `approved_flag_count`, `threads.score_total`, profiles — reused; schema change only if the eligibility risk above requires it, under the CONTRIBUTING read-model rules.
- Flags: `FeatureFlagRegistry`, Feature Flags page and change records — reused; two entries added.
- Activity: `thread_label_add`/`post_reaction_add` items — unchanged; a title-change Activity item is not in scope.

## Concepts touched

- [Reaction record](../../concepts/reaction_record.md) — used unchanged; presentation gains the signals line.
- [Post presentation](../../concepts/post_presentation.md) — changed: signals line on every card.
- Thread title — proposed new entry `docs/concepts/thread_title.md`: subject precedence, directive authority, provenance and withdrawal.
- Site feature flags — used unchanged; two flags registered. No entry exists; none proposed beyond the registry and spec.

## Simple user flow

1. Open a thread you authored and reply with a body containing `title: Clear name for this thread`.
2. The reply posts normally and stays visible with that line.
3. The thread heading, listing entries and static copies show the new title; the root card's signals line reads `Title set by <you>`.
4. Another reader, not signed in, sees the new title and the signals line listing who liked, flagged and retitled.
5. An operator turns `FORUM_TITLE_DIRECTIVE_ENABLED` off; new `title:` lines are plain text; existing titles stay.

## Success criteria

- An authorized `title:` reply changes the thread title on every surface in the inventory within one request, and a rebuild reproduces it.
- An unauthorized or flag-off reply posts unchanged and the title is unchanged.
- Each card shows actors for every like and flag the score counts, and no others; the line reads identically for anonymous and signed-in viewers before the viewer's own click.
- Both flags appear in order on the Feature Flags page and switch behavior without restart.
- Dependency delta zero; verification and limitations recorded in the Step 4 summary.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Study checkpoint and approval

2026-11-03T05:34:01Z: User confirmed the Step 1 reading and wrote "approved step 1" for scope v2 (Option B plus two flags). Step 2 projected total **0.85 reaffirmed**; baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26`, R1 (three hashes verified at Step 1) and P2 retained. Claude Code single exposed rater; no pristine intake, as recorded. Whole-cycle estimate, not hours or additive stage work. No implementation, measurement or accepted outcome; all records uncommitted. Await Approved Step 2.

## Enhancement Requests

- Suggested titles from unauthorized repliers — pending follow-up, carried from Step 1; out of this cycle.

## Step 2 approval

2026-11-03T08:53:36Z: User: "approved step 2". Requirements approved unchanged; proceed to Step 3 only.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*
