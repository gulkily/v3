# Effort estimation trial ledger

[Setup plans](./effort_estimation_trial/) · [Your checklist](./effort_estimation_trial_maintainer_checklist.md)

> Current enrollment: [protocol P2](#continuing-enrollment--2026-10-21-protocol-p2) enrolls every future cycle from 2026-10-21. Earlier individual-selection rules are historical; R1 and candidate histories remain unchanged.

## Current state

Setup complete; maintainer acceptance of the provisional R1 anchor remains pending. E07 reduced scope v3 is technically complete on `feat/fastmod-root-meta`; maintainer comfortable-fit judgment and acceptance are pending. Original v2 was superseded after its Stage 1 sizing gate. Completed accepted outcomes: **0 / 6**. The setup cycle is not a study outcome; E01–E06 remain proposed, and the additional selected E07 scope/score history is preserved below.

## Frozen reference set R1

Recorded 2026-10-19T03:20:46.805149+00:00. Definition: **1.0 is the largest well-scoped, releasable slice we would comfortably approve for one Railspike cycle, including required verification and documentation.** Startup dependency recovery is the provisional 1.0 reference, conditional on its existing installer/test infrastructure. Maintainer acceptance is pending. Smaller comparisons include duplicate preview suppression (0.10), about-copy integration (0.20), numeric permalinks (0.25), score emphasis (0.30), toolbar identity (0.35) and composer decluttering (0.60).

| Source | SHA-256 |
| --- | --- |
| [Calibration report](../../../../railspike-dev/docs/research/effort_estimation/report.md) | `d3a30b6400b83bb25283b4234b41ba07515df9a696ea44de59c10186103771b0` |
| [Implementation diff review](../../../../railspike-dev/docs/research/effort_estimation/diff_review.md) | `068b3519326e08a939df40b5abe0106ddcbab80bd796616c76b5084a21462b84` |
| [User feedback](./zenmemes_meta_feedback_2026-10-18_summary.md) | `ef335604ecd5b51e5c04067d88e4d6f01cb4fdb21b8f4d7dba7b5b90e4012bfc` |

Reference documents live in an external checkout. If unavailable, restore these exact sources and verify hashes before rating; do not silently use changed anchors. Explicitly version any accepted replacement as R2, retaining R1 and affected score histories. The same frozen reference set applies across a batch. Local path relocation may repair links without changing content identity.

## Rating protocol

- Preserve inherited feedback scores independently of initial scoped estimates. One agent is the current rater, exposed to the feedback scores and calibration examples: results are neither blind nor independent of those sources. A second rater is optional, not assumed.
- Rate the **total scoped cycle from its starting point**, including inspection, planning, implementation, required verification, documentation and ordinary correction. Exclude approval waiting and unrelated cleanup. Freeform nonnegative decimals are judgments, not elapsed hours, priority, approval, additive forecasts or measured actual effort. Values above 1.0 prompt a split discussion without waiving recovery.
- Preserve the intake scope/estimate before detailed implementation inspection. Planning/status evidence may screen for prior work first; record prior exposure and any inspection already performed. Do not imply an initial score is uninformed by available plans.
- Record artifact snapshots at Step 1 handoff when used, Step 2 handoff, Step 3 handoff and Step 4 entry. Explicitly reaffirm unchanged values. Append new snapshots on revisions with a reason distinguishing scope change from new understanding; never erase prior values. Preserve approval context. Implementation discoveries can trigger a new total projection before dependent work; changed scope still requires normal approvals.
- At Step 4 closeout, record a **retrospective scope assessment** and the maintainer's comfortable-fit judgment separately from projections. Missing/skipped checkpoints stay missing/not applicable; do not reconstruct a prospective history. Skipped Step 1 has no zero score.
- Each snapshot carries candidate/cycle ID, checkpoint, UTC date, rater, scope version, reference version, exposure, score, reason and artifact link. Scope versions retain outcome, required recovery, evidence, dependencies and starting Git revision.
- Keep snapshots canonical in feature artifacts after those exist; ledger rows link to them. Intake snapshots live here until a feature exists. Updates during planning remain uncommitted under normal planning rules; include this ledger in each feature's approved documentation scope and commit its accumulated update with closeout. The optional trial does not widen any cycle's write authority.

## Selection and outcome accounting

Use useful unfinished feedback first, cross-checking backlog and feature records. Six proposed scopes are a target; size diversity is a preference, not a quota. Statuses: proposed, selected, active, completed, deferred, replaced, abandoned. Keep replacements with reasons and links. Selection is not approval. Required device/environment checks must be part of the candidate's acceptance evidence.

One completed outcome is the accepted original candidate scope with required evidence and a recorded maintainer judgment. If split, preserve the original estimate/scope and link every child cycle; the original counts once only after its full agreed outcome is accepted. A deliberately reduced/replaced scope is versioned and cannot be silently compared as the original; child cycles are not independent observations by default. Missing acceptance remains visible and does not count as a completed outcome. Six completions do not imply six commits or necessarily six implementation cycles.

## Outcome record template

For each candidate retain delivered scope/version; feature plan/summary links; starting and final revisions and stage commits/diffs; planned/completed stages; verification passed/failed/blocked/pending/inapplicable and omissions; corrective work versus new requests; splits/follow-ups; retrospective scope assessment; maintainer comfortable-fit judgment and acceptance date. Record active-work measurements only if reliably collected; never infer effort from lines, stage counts or timestamps.

## Next action and study review

The user selected additional candidate E07 on 2026-10-20. Review its root-only implementation and supply the maintainer comfortable-fit/acceptance judgment next; E01–E06 remain proposed. The provisional anchor still awaits maintainer acceptance. Each feature gets its own approvals. The agent keeps active candidate, pending items and next action current for resumption. After six accepted outcomes, start a separately approved documentation cycle to assess size errors, scope changes and incomplete acceptance; qualify conclusions if ceiling examples are absent. No measured labor, absolute capacity ceiling or delivery forecast is claimed.

## Candidate intake batch B1

Recorded 2026-10-19T03:21:59.566650+00:00, before application source inspection for these candidates. Starting revision for all six: `e8548e7512940b4e164483fbd33d79eca2b85099` (application baseline is unchanged from the planning commit). Scope version: **v1**; reference: **R1**; rater: **Codex, single rater**. Exposure: all inherited feedback scores and the reference study were visible; planning/status records were screened first. `todo.txt` had unrelated uncommitted edits; it was read as a status hint, not modified (SHA-256 at screening: `5d241274915baebf0588f49516aaf3be41e68bde7d0596e30d1bc1aa23c96b85`). No independent-rater claim.

| ID | Proposed remaining outcome | Feedback item | Inherited | Initial total | Status |
| --- | --- | --- | ---: | ---: | --- |
| E01 | Explain conduct/integrity vouching at user approval | Feature 03 | 0.05 | 0.08 | Proposed, current-source screen pending |
| E02 | Copy All on public thread/reply body text boxes | Feature 05 | 0.15 | 0.25 | Proposed, current-source screen pending |
| E03 | Stop tag-input automatic capitalization on mobile | Bug 01 | 0.05 | 0.08 | Proposed, current-source screen pending |
| E04 | Keep reply Send/Post reachable above the iPhone keyboard | Feature 16 | 0.40 | 0.55 | Proposed, device acceptance pending |
| E05 | Optional expanded excerpts in the standard Board listing | Feature 34 | 0.35 | 0.45 | Proposed, current-source screen pending |
| E06 | Reversible distraction-free mode on New thread | Feature 26 | 0.40 | 0.50 | Proposed, current-source screen pending |

Scores are judgments for the bounded scopes below, not rewrites of the broader feedback scores. Required verification is included. No ceiling candidate is forced into this batch. Later per-step columns remain pending until separate cycles exist.

### E01 — Approval meaning

- Outcome/entry: At the standard user-approval action, explain that approval vouches for conduct and integrity; retain the existing action and permission rules.
- Recovery/evidence: Copy remains readable on mobile and keyboard navigation; existing success, refusal and failure/retry controls remain visible. Verify approval and rejection paths without changing policy.
- Dependencies/overlap: Existing approval UI and [vouching design](./php_user_approval_vouching_slices_v1.md); [root approval pages](./root_approval_pages/) and User Access may already supply the meaning. Confirm remaining copy before implementation.
- Initial rationale: Smaller than about-copy integration, but locate the real shared action and check unchanged permissions/recovery; 0.08 total.

### E02 — Copy body text

- Outcome/entry: Copy the full body from public New thread and reply text boxes without losing or submitting the draft; exclude secret-key/password fields and unrelated text boxes from this proposed first slice.
- Recovery/evidence: On denied/unavailable clipboard access, preserve text and offer ordinary manual selection/copy. Verify empty, Unicode and multiline drafts, keyboard activation and a mobile browser; no signed-content changes.
- Dependencies/overlap: Existing composer and clipboard helpers; bookmarklet/key copy controls are related infrastructure, not proof that body Copy All exists. Shares composer surface with E04/E06 but delivers an independent outcome.
- Initial rationale: Similar to a bounded routing extension, with shared controls and clipboard denial recovery; 0.25 total. Maintainer must confirm this narrowed scope is useful.

### E03 — Tag capitalization

- Outcome/entry: Stop automatic initial capitalization in standard public thread/reply tag inputs on mobile while preserving deliberately entered characters and existing tag validation.
- Recovery/evidence: Native keyboard no longer forces capitals; existing validation and draft recovery remain usable. Verify on a real affected mobile keyboard, plus render coverage for shared input attributes; desktop attributes alone do not close the report.
- Dependencies/overlap: Shared tag input and existing normalization; [composer declutter](./compose_thread_declutter/) changed layout, not documented keyboard behavior.
- Initial rationale: Small shared-input change with targeted device acceptance; 0.08 total, conditional on reproducibility.

### E04 — iPhone posting control

- Outcome/entry: In the standard inline thread-reply form and compose-reply page, keep Send/Post reachable while the iPhone software keyboard is open in portrait and landscape. No redesign of private-message or Forte composers.
- Recovery/evidence: Draft, validation errors and retry action survive keyboard opening/closing and rotation; avoid covering text or causing duplicate sends. Capture actual iPhone Safari/Firefox checks and supported desktop regression evidence; emulation alone is insufficient. Device access remains to be arranged in its feature cycle.
- Dependencies/overlap: Shared composer and [declutter history](./compose_thread_declutter/); private-message sticky composer is a separate surface. Coordinate later E02/E06 changes through new baselines.
- Initial rationale: Several viewport/focus transitions plus real-device acceptance; near composer decluttering's 0.60 reference but narrower surfaces, 0.55 total.

### E05 — Expanded Board excerpts

- Outcome/entry: A standard Board reader can switch between existing compact cards and longer text excerpts, then open a post normally. Scope excludes Forte and new search, loading or media behavior.
- Recovery/evidence: Toggle back without losing current filter/sort; handle short/empty bodies and unavailable preferences gracefully; preserve safe text rendering and navigation on mobile/keyboard. Define excerpt length during feature planning.
- Dependencies/overlap: Existing thread-card preview behavior and [duplicate-preview suppression](./thread_card_remove_preview/); do not count that completed narrow change again.
- Initial rationale: More state/rendering work than score emphasis or toolbar identity, while reusing cards and data; 0.45 total.

### E06 — Distraction-free New thread

- Outcome/entry: From standard New thread, enter and exit a focused writing view that reduces surrounding chrome while retaining draft, submit, required warnings and a visible way out. No persistent/cross-device preference or new editor.
- Recovery/evidence: Exit via a visible keyboard-accessible control; failed validation exposes relevant fields without losing text. Verify mobile layout, keyboard navigation and repeated entry/exit; unavailable JS leaves ordinary composition usable.
- Dependencies/overlap: [Composer declutter](./compose_thread_declutter/) already simplifies sections. [Hide numbers focus toggle](./backlog/hide_numbers_focus_toggle_step1_solution_assessment.md) concerns reading/counts, not this writing mode; keep separate.
- Initial rationale: Bounded layout state and recovery, below the broader 0.60 composer reference; 0.50 total.

### Screening dispositions before source inspection

- Feature 01 composer simplification overlaps delivered declutter; exclude the broad historical request from this batch.
- Feature 27 image paste overlaps delivered image upload enhancements; exclude the combined image/audio/video request rather than re-estimate completed image work.
- Bug 10 missing-PHP startup overlaps the calibration anchor; exclude from prospective candidates.
- Feature 28 compose-reply context and Feature 25 Reply scrolling may overlap existing thread/reply work; defer, not counted or declared fixed.
- Feature 23 ActivityPub and bundled agent/notification requests remain underspecified; no artificial near-ceiling scope is introduced.

## Post-intake source screen — 2026-10-19T03:22:54.041492+00:00

Read-only source screening followed the saved intake batch. This is evidence of plausible remaining work, not browser reproduction or a feature implementation audit. Candidate selection and feature approvals remain pending.

| ID | Current scope / projected total | Evidence and disposition |
| --- | --- | --- |
| E01 | v1 / 0.08 reaffirmed | `templates/pages/users_pending.php` exposes Approve with no conduct/integrity explanation; existing profile/about copy references trust but does not establish the requested action-level explanation. Retain proposed; confirm all actual approval surfaces during planning. |
| E02 | v1 / 0.25 reaffirmed | `templates/partials/thread_compose_form.php` and `reply_form.php` contain body textareas and submit/clear actions, with no body Copy All control. Retain proposed; existing clipboard helpers need detailed inspection later. |
| E03 | **v2 / 0.06** | Thread form's editable `board_tags` input has no autocapitalization hint; reply form's board tags are hidden. Narrow to the editable standard New thread tag input, preserving explicit user capitalization and required real-mobile validation. Reason: source-informed scope reduction, not measured effort. v1/0.08 remains above. |
| E04 | v1 / 0.55 reaffirmed | Shared reply form renders submit below the body; inspected compose CSS has no keyboard-viewport handling. Actual iPhone reproduction remains pending, so retain as proposed with device acceptance required; do not claim a reproduced bug. |
| E05 | v1 / 0.45 reaffirmed | `templates/partials/thread_card.php` renders `body_preview`; standard Board has no excerpt-mode control in the screened template. Existing duplicate-preview suppression is reused. Retain proposed; query/data sufficiency still needs inspection. |
| E06 | v1 / 0.50 reaffirmed | Thread form has disclosure sections but no writing-mode toggle; inspected disclosure script manages those sections/actions, not a distraction-free view. Retain proposed; keep separate from hide-numbers reading mode. |

All these snapshots use rater Codex, reference R1 and the same starting revision as B1, with prior score exposure and additional source exposure as listed. They are **post-intake screening checkpoints**, not retrospectively invented Step 1/2/3 feature scores. Later feature cycles will record their own handoffs. No detailed implementation design or runtime verification was performed.

Feature 28 (original text on compose-reply) is now excluded as apparently already present: `templates/pages/compose_reply.php` renders the parent body and a missing-parent message. This source observation does not certify its runtime behavior. Other deferred/rejected items remain visible above. No replacements were needed among E01–E06; E03's narrower scope remains explicit.

## Compact checkpoint comparison

| Candidate | Inherited | Intake | After source screen | Step 1 | Step 2 | Step 3 | Step 4 entry | Closeout |
| --- | ---: | ---: | ---: | --- | --- | --- | --- | --- |
| E01 | 0.05 | 0.08 | 0.08 | Pending | Pending | Pending | Pending | Pending |
| E02 | 0.15 | 0.25 | 0.25 | Pending | Pending | Pending | Pending | Pending |
| E03 | 0.05 | 0.08 | 0.06 (v2) | Pending | Pending | Pending | Pending | Pending |
| E04 | 0.40 | 0.55 | 0.55 | Pending | Pending | Pending | Pending | Pending |
| E05 | 0.35 | 0.45 | 0.45 | Pending | Pending | Pending | Pending | Pending |
| E06 | 0.40 | 0.50 | 0.50 | Pending | Pending | Pending | Pending | Pending |

All six remain proposed and unfinished on the screened evidence. Pending columns mean the feature cycles have not begun; convert Step 1 to not applicable only if that step is actually skipped. Shared composer dependencies mean execution order may change later baselines and estimates; do not sum these scores as a forecast. This batch lacks near-ceiling examples, so it cannot by itself validate the 1.0 ceiling.

## Operational checklist for the agent

1. Resume here: read current state, selected candidate and next action; verify R1 source hashes and restore the latest scope/score snapshot. A ledger selection triggers the vendored study contract; copy the study/candidate ID and ledger link into the feature artifact.
2. Before new detailed inspection, preserve the intake estimate and starting revision. For an existing B1 candidate, keep its original record and append any new baseline/scope judgment rather than reset it.
3. Run the selected feature’s normal approvals and capture each applicable checkpoint. Include this ledger in its approved documentation scope. Keep planning updates uncommitted; preserve the planning-commit file gate.
4. At implementation entry reaffirm or revise the total; append meaningful later discoveries and enforce changed-scope approvals. Keep the comparison table linked to canonical artifact snapshots.
5. Close out in the feature’s final stage commit: record evidence, retrospective assessment and maintainer judgment (or pending), update status/next action and retain splits/corrections versus new requests. Technical completion alone does not increment accepted outcomes.
6. After six accepted outcomes, propose the separate study-review cycle. Keep sources and all earlier estimates intact; qualify any conclusion about the ceiling because this proposed batch has no near-ceiling examples.

Synthetic walkthrough evidence lives in the [setup summary](./effort_estimation_trial/effort_estimation_trial_step4_implementation_summary.md), outside candidate histories. Later real feature plans will supply the currently pending checkpoint links.


## E07 — Fastmod rubric actions

- Selection: explicitly requested 2026-10-20; status **v3 technically complete; maintainer acceptance pending**. Additional candidate outside B1; no E01–E06 replacement or independent child-outcome claim. Target remains six accepted outcomes.
- Canonical scope v1, intake and Step 1 snapshots: [fresh assessment](./fastmod_rubric_actions/fastmod_rubric_actions_step1_solution_assessment.md#study-snapshots). Starting revision `4c521b743575979b8fefc66b38271551a57a08ef`; Codex single exposed rater; no inherited score. Intake **0.85**, Step 1 **0.85 reaffirmed**, R1. Intake was recorded after historical-plan and read-only source exposure, not reconstructed as pre-inspection.
- Proposed outcome: evidence-backed, discoverable meta tagging of newly scored local posts, including human correction, provenance, stale-result handling, retries and rollback of machine-owned tags. Automated moderation, author standing, ranking changes and historical bulk tagging are deferred; predecessor records remain intact. No application implementation or later-step approval is implied.
- R1 verification: report and diff-review files at the recorded external paths match frozen hashes. Current feedback document differs; restored exact frozen feedback bytes read-only from Git revision `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e` to `/tmp/fastmod-r1-feedback.md` and verified SHA-256 `ef335604ecd5b51e5c04067d88e4d6f01cb4fdb21b8f4d7dba7b5b90e4012bfc`. Recover by Git revision, not the temporary path. No R2 was introduced.
- Step 2 checkpoint: [feature description](./fastmod_rubric_actions/fastmod_rubric_actions_step2_feature_description.md#study-snapshot-and-approval-state), **0.95**, R1, scope v1 clarified, Codex single exposed rater, UTC 2026-10-20. New understanding of reaction/label coupling and discovery/rebuild/rollback integration revises the total from 0.85; original snapshots remain intact.
- Step 3 checkpoint: [development plan](./fastmod_rubric_actions/fastmod_rubric_actions_step3_development_plan.md#execution-and-study-snapshot), **0.95 reaffirmed**, R1, scope v2, Codex single exposed rater, UTC 2026-10-20. User explicitly removed the human-labeled accuracy gate after Step 2 approval; machine-owned tagging/correction/recovery scope remains.
- Next: user reviews Step 3; wait for `Approved Step 3`. Step 4 entry and closeout scores remain pending. Retain this ledger within the cycle's eventual approved documentation boundary; planning writes remain uncommitted, with accumulated ledger updates reserved for authorized closeout.

- E07 Step 3 architecture refinement A2 (2026-10-20): user accepted live tag joins/bulk lookups with generation-time public artifact snapshots instead of continuous read-model copying. Scope v2 and total **0.95 reaffirmed**; [canonical revision](./fastmod_rubric_actions/fastmod_rubric_actions_step3_development_plan.md#accepted-refinement--live-joins-and-artifact-snapshots). Step 3 remains awaiting approval.

- E07 Step 4: branch `feat/fastmod-rubric-actions`, planning commit `766deab1`; [entry/discovery snapshots and Stage 1 evidence](./fastmod_rubric_actions/fastmod_rubric_actions_step4_implementation_summary.md). Entry **0.95 reaffirmed**, discovery **1.20** for unchanged v2/A2 scope; offline item format/client and generation-safe build/activation work exceed the approved atomic stage breakdown. No application changes; awaiting revised scope or stage-budget decision. No closeout or accepted outcome. Ledger remains outside the investigation commit under the approved closeout bookkeeping rule.

- E07 revised Step 2 v3 (2026-10-20): user accepted reduced root-only automatic meta tagging, existing styling, configuration/CLI-only controls, no evidence viewer, restore, generic mapping engine or automatic reclassification. [Canonical revised scope/snapshot](./fastmod_rubric_actions/fastmod_rubric_actions_step2_feature_description.md#study-snapshot--revised-step-2-handoff): **0.85 projected total**, R1, Codex single exposed rater; includes already performed planning/investigation and renewed planning, not just remaining implementation. This replaces, and does not complete, original v2. Await renewed Approved Step 2 then revised Step 3 and its approval; no accepted outcome or independent child observation. Historical v1/v2 approvals and scores remain preserved.

- E07 revised Step 3 v3 (2026-10-20): renewed Step 2 approval received. [Canonical development plan/checkpoint](./fastmod_rubric_actions/fastmod_rubric_actions_step3_development_plan.md#execution-and-study-checkpoint) reaffirms **0.85 total**, R1, Codex single exposed rater, including prior investigation/replanning. Eight root-only stages separate private persistence, worker eligibility, live discovery, CLI correction, artifact guard, artifact materialization, verification and closeout. Await renewed Approved Step 3; no application edits or accepted outcome. Historical broader scope remains preserved.

- E07 v3 closeout (2026-10-20): [canonical implementation/outcome record](./fastmod_rubric_actions/fastmod_rubric_actions_step4_implementation_summary.md#commits-and-study-outcome). Reduced implementation start `79bc519f`; original cycle start `4c521b743575979b8fefc66b38271551a57a08ef`; final production code `d1da4d40`; revised planning `fe9578f9`, then eight stage commits including closeout. Projected total **0.85 preserved**; **retrospective scope assessment 0.90**, Codex single exposed rater, R1. Outcome: root-only meta tags, existing presentation/live lookup/public artifact labels, CLI correction/retry/rollback and private retained evidence. Final focused 27 passed; broader focused 68 passed; real browser/rebuild passed. Full suite 1,380 / 1,372 passed / 8 reproduced baseline/environment failures; started before final atomic-evidence/enablement fixes, with final focused/browser coverage of those changes. No deployment or live-model accuracy test. Maintainer comfortable-fit/acceptance **pending**, accepted outcomes remain **0 / 6**. v3 is an explicit reduced replacement, not completion of original v2 and not an independent child observation.

- E07 closeout commit: `1f35e6be` (Stage 8), completing the eight-stage v3 sequence after planning `fe9578f9`. Current-state text corrected in a separate closeout follow-up; no application changes or history rewrites. Maintainer study acceptance remains pending.

## Continuing enrollment — 2026-10-21 (protocol P2)

Maintainer request: “I want every future railspike to be part of the same data collection.” This is standing enrollment of every new Railspike cycle in this project, including documentation, research and maintenance cycles. It supersedes individual-selection-only guidance above from this date; earlier text and candidate histories remain historical. R1 and all prior scores remain unchanged. The maintainer authorized provisional beta use of the scale; this does not supply E07's pending comfortable-fit judgment or accept an individual outcome.

- Continuing study ID: `railspike-effort`; reference R1; collection protocol P2. New cycle ID: `v3/<unique feature prefix>`; retain E01–E07 as legacy candidate IDs. Reuse the same ID across worktrees for the same cycle. A distinct follow-up gets its own prefix and parent link; check existing plans/ledger before allocating it.
- Every new cycle records intake, applicable Step 1/2/3 handoffs, Step 4 entry, material revisions and closeout using the existing rating protocol and outcome template. Include this ledger's bookkeeping in the cycle's documentation scope. Canonical snapshots stay in feature artifacts; append a ledger index row with ID, status, scope/reference version, current score, checkpoint/artifact link and parent/replacement relationship. Preserve all earlier values. Initial inspection exposure and missing checkpoints must be explicit.
- Record new cycles at their first artifact, including work that pauses, is abandoned or never reaches implementation. Work already underway joins at its next checkpoint with its earlier estimates marked missing unless evidence exists; do not invent intake history. Ordinary tasks that do not invoke Railspike are outside the cycle population. E01–E06 remain proposals until actually started; automatic collection is not implementation approval.
- Keep projections, retrospective scope assessment, verification and maintainer comfortable-fit/acceptance separate. Record stage counts, corrections versus new requests, omissions, revisions/diffs and splits. Unknown active work and absent independent ratings remain unknown. Technical completion does not increment accepted outcomes.
- Six accepted outcomes now trigger a review checkpoint, not the end of collection or a size quota. Freeze R1 until an explicit reference revision; protocol P2 changes enrollment only. Retain every scope/version and related child cycle; no extra independent outcomes from cloned files or split children.
- This checkout's ledger and artifacts are local copies of one dataset. Integrate updates through normal Git review; merge by stable cycle ID/checkpoint and retain conflicting snapshots, rather than overwriting or summing checkouts. Collection is local; no automatic upload is authorized. Repair source paths by recorded content hash/Git revision, never by silently changing references.

### Enrollment index after P2

E01–E06: proposed, no feature-cycle checkpoints. E07: reduced v3 technically complete, projected 0.85, retrospective 0.90, maintainer acceptance pending. These counts are unchanged by P2. Append each new cycle here with links to its canonical snapshots; keep unsuccessful/incomplete cycles visible. The historical “0 / 6” is accepted outcomes toward the first review checkpoint, not the number of collected records.

- `v3/feature_flags_security_review_cleanup` — active Step 1, awaiting approval; scope v1, R1/P2, intake **0.18**, Step 1 **0.18 reaffirmed**; [canonical assessment and snapshots](./feature_flags_security_review_cleanup/feature_flags_security_review_cleanup_step1_solution_assessment.md#study-snapshots). Remove the detached Feature Flags security-review shortcut while retaining canonical authorized audit access. Source exposure preceded intake; no earlier prospective estimate invented. No parent/replacement or accepted outcome. Implementation and later checkpoints pending; ledger changes remain uncommitted until authorized closeout.

- `v3/feature_flags_security_review_cleanup` revision — scope **v2**, Step 1 awaiting approval; projected total **0.45**, R1/P2, 2026-10-21T19:51:05Z, Codex exposed single rater. User clarified relocation/presentation rather than removal alone: recommend embedded Access history in User Access, compact events with expandable evidence and session/empty-state recovery. [Canonical revised assessment](./feature_flags_security_review_cleanup/feature_flags_security_review_cleanup_step1_solution_assessment.md). Preserve v1 intake/Step 1 **0.18** above; no implementation, replacement cycle or accepted outcome.

- `v3/feature_flags_security_review_cleanup` — Step 1 v2/mockup approved; [Step 2](./feature_flags_security_review_cleanup/feature_flags_security_review_cleanup_step2_feature_description.md#approval-and-study-snapshot) awaiting approval. 2026-10-21T20:11:34Z, scope v2 clarified, R1/P2, Codex exposed single rater; projected total **0.45 reaffirmed**. Recent five events, full-history handoff, evidence and operator authentication recovery remain one User Access slice. Earlier v1/v2 snapshots retained; no implementation or outcome acceptance.

- `v3/feature_flags_security_review_cleanup` — Step 2 approved; [Step 3](./feature_flags_security_review_cleanup/feature_flags_security_review_cleanup_step3_development_plan.md#approval-and-study-checkpoint) awaiting approval. 2026-10-21T20:31:02Z, scope v2, R1/P2, Codex exposed single rater; **0.45 total reaffirmed** after checking existing bounded queries, evidence and authentication-return support. Four stages: protected recovery, embedded history, full/shared history, browser acceptance/closeout. No runtime edits, implementation approval or accepted outcome. Prior snapshots remain canonical.

- `v3/feature_flags_security_review_cleanup` — **technically complete, maintainer acceptance pending**, scope v2/R1/P2, 2026-10-21T20:58:23Z; [entry and outcome](./feature_flags_security_review_cleanup/feature_flags_security_review_cleanup_step4_implementation_summary.md#study-closeout). Entry **0.45 reaffirmed**, projected total **0.45 preserved**, retrospective scope **0.45**; Codex exposed single rater, no measured labor or independent rating. Start `0b9f5f8b`, planning `e563a057`, stages `3df2e156`, `b37e11af`, `2c367f7d`, followed by Stage 4 acceptance/closeout commit (hash in debug handoff). Four stages completed; real signed browser role/recovery/history checks and 47 PHP regressions pass, 16 phone/desktop themes checked. Chromium only, no full suite/deployment/original-session inspection. Earlier scope v1 was superseded, not completed independently; retain all snapshots. Accepted outcomes remain unchanged; next action is maintainer review, with no implementation work outstanding.
