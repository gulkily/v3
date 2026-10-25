# request_path_cheap_wins — Railspike debug log

## 2026-10-24 UTC — Step 1 (Solution Assessment)

### Necessary/useful instructions
- `RAILSPIKE.md` "keep work within a day or eight Step 3 stages" + warning signs: justified batching three trivial changes into one cycle (Option A) rather than three cycles, and capping stages at three. Retain.
- `step1_solution_assessment.md` guardrails (≤1 page, bullets over prose): kept the batched-cycle rationale compact while still distinguishing the three items' shared risk profile. Retain.
- Enhancement-request classification (`RAILSPIKE.md#enhancement-requests`): directly informed the #6 "investigation gate" handling — if `AssetFingerprint` already memoizes, record a no-op; if a deeper issue appears, treat as separate work, not scope creep. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates: not enabled.
- Read-model schema-change checklist: deliberately out of scope for this no-schema cycle (that surface lives in the separate `read_model_hot_indexes` cycle).

### Uncertain assessments
- Item #6 outcome is genuinely unknown pending the Stage 3 investigation gate; the ≈0.40 projected total assumes a per-request memo, not a larger fingerprinting rework.
- ≈0.40 is a single exposed, non-blind rater judgment; spanning three independent stages is the main reason it sits slightly above the 0.30–0.35 R1 band despite each change being trivial.

## 2026-10-25 UTC — Step 2 (Feature Description), after Approved Step 1

Drafted Step 2 grounded in the three target files (`FastmodMetaRead`, `FrontController`/`RouteServices`, `AssetFingerprint`).

### Necessary/useful instructions
- Enhancement-request "investigation gate" handling: resolved the #6 gate by reading the code instead of deferring — `AssetFingerprint::assetHash()` calls `hash_file()` with no cache, and `fingerprintedPath()` is called per asset reference, so #6 is a **real** per-request memo, not the possible no-op Step 1 hedged on. Recording the resolved gate now (not at Step 3) keeps Step 2 requirements concrete. Retain.
- Shared component inventory: tracing the real call sites corrected the Step 1 wording for #5 — the flag evaluator is built up to **three** times per request (not a vague "multiple"), and `activeStaticReleaseRoot()` is hit by both `sendStaticDocsResource()` and every `firstExistingPath()` call. Grounding in the live code sharpened the requirements. Retain.
- "A feature is a normal-flow vertical slice": pushed the framing to the user-visible invariant (identical output/headers/gate decisions with less redundant work) rather than "add a cache," keeping the completion boundary behavior-neutral. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates: not enabled for this cycle; no per-option/per-stage estimate lines. Noted in the header.
- Read-model schema-change checklist: no schema change in scope.
- Per-stage stage-work estimates: begin at Step 3, and only if extended estimates were on (they are not).

### Uncertain assessments
- #4's transaction wrap assumes `decorate()` is not already running inside a caller transaction; flagged as the top risk with an `inTransaction()`-guard mitigation to confirm at Step 3.
- ≈0.40 reaffirmed; resolving the #6 gate removed the main Step 1 uncertainty without adding scope. Single exposed, non-blind rater.

## 2026-10-25 UTC — Process suggestion: record step approval inside the step document

### The problem this cycle exposed
Approval of a Railspike step currently lives only in the chat transcript as an
"Approved Step N" message. That is fragile:
- **Not auditable from the artifacts.** Reading the plan folder does not tell you
  which steps are approved; you must reconstruct it from conversation.
- **Does not survive context loss / handoff.** After compaction or a new session,
  the agent cannot verify an approval happened and must *infer* it — exactly what
  happened here: `request_path_cheap_wins` Step 1 was treated as approved based on
  the debug log's "after Approved Step 1" note and prior context, not a verifiable
  record. The agent correctly flagged the inference, but it should never have had
  to infer.
- **Ambiguous authority and scope.** Nothing ties the approval to who gave it,
  when, or to which scope revision (v1/v2), so a stale approval can silently carry
  across a scope change (a hazard already seen in the viewer-tag cycle's merge).

### Suggested rule
Require that each step's approval is recorded **inside that step's document** before
the next step may begin. Concretely:

1. Add an **Approval** line at (or near) the top of each `stepN_*` document, e.g.:
   ```
   Approval: PENDING
   ```
   and on approval the agent updates it in place to:
   ```
   Approval: APPROVED — <approver> — <UTC timestamp> — scope v<N>
   ```
   with the triggering request quoted or referenced.
2. **Gate:** the agent may not advance to Step N+1 (or branch / commit / implement
   after Step 3) unless Step N's document reads `Approval: APPROVED` for the
   current scope revision. A conversational "Approved Step N" is the *trigger* to
   record the approval, not the record itself.
3. **Scope-change invalidation (make explicit):** any scope revision resets the
   affected step(s) to `Approval: PENDING` and requires fresh approval, with the
   superseded approval retained in history. This encodes the "prior approval no
   longer carries after a scope change" rule structurally instead of by vigilance.
4. **Completion-gate check:** before `goal complete` / merge, verify every step
   document through the reached step shows `APPROVED` for the final scope.

### Why inside the step document (not a separate ledger)
The approval travels with the artifact it authorizes: when the plan folder is
archived, the approval record is archived with it; a reviewer reading Step 3 sees
its own approval status without cross-referencing a transcript. It also lets the
agent *verify* rather than *infer* after any context loss — the single failure
this cycle surfaced.

### Immediate application
- `request_path_cheap_wins`: Step 1 should carry an explicit `Approval:` line.
  Because this session could only *infer* that approval, the honest fix is to
  obtain (or confirm) an explicit Step 1 approval and record it in
  `request_path_cheap_wins_step1_solution_assessment.md`, then record Step 2's
  approval in its document when given — rather than back-dating an approval the
  artifacts cannot substantiate.
- This is a suggested change to the vendored Railspike process
  (`docs/railspike/`); it is recorded here as a proposal and is **not** applied to
  the Railspike subtree without a separate decision.

## 2026-10-25 UTC — Approved Step 1 (recorded in the step document)

User gave an explicit, recordable "Approved Step 1". Updated the Step 1
document's `Approval:` line in place to `APPROVED — user — <timestamp> — scope
v1`, per the approval-recording convention proposed earlier in this log. This is
the first use of that convention: the conversational message is the trigger; the
step document is the record. Step 2 remains `Approval: PENDING`. No step advance
or implementation follows from the Step 1 approval alone (Step 2 is already
drafted and awaits its own approval).

## 2026-10-25 UTC — Step 2 rewritten from scratch (prior draft deleted on purpose)

The earlier Step 2 draft was **deliberately deleted by the user** because it had
been written out of process: it was drafted *before* Step 1's approval was
explicitly given and recorded in the step document, so it jumped the approval
gate. (An earlier note in this log wrongly guessed the file was lost to a
working-tree reset; corrected here — it was an intentional deletion, not an
accident.) Rewrote Step 2 from the now-approved Step 1 (Option A), using this
debug log's retained Step 2 notes and a re-read of the three target files. No
scope change: items #4/#5/#6, behavior-neutral, ≈0.40.

- Real lesson: do not draft Step N+1 before Step N's approval is recorded. The
  approval-recording convention exists precisely to make the gate enforceable; I
  violated the spirit of it by producing Step 2 ahead of a recorded Step 1
  approval. The rewrite is the correct remedy, and this Step 2 was produced only
  after Step 1 was recorded `APPROVED` for scope v1.
- Secondary note (still valid but not the cause here): committing planning
  artifacts per step would make them durable, but durability was not the issue —
  process order was.
- Corrected a path error carried from the punch-list: `AssetFingerprint` lives at
  `Host/AssetFingerprint.php`, not `View/AssetFingerprint.php`, and there is no
  separate `TemplateRenderer::assetPath` memo today. Recorded in Step 2's
  component inventory.
- Step 2 carries `Approval: PENDING`; Step 1 is `APPROVED` (scope v1). No advance
  to Step 3 until Step 2 is recorded `APPROVED`.

## 2026-10-25 UTC — Approved Step 2; Step 3 (Development Plan) drafted

Recorded the explicit Step 2 approval in the Step 2 document (`APPROVED — user —
<timestamp> — scope v1`), then drafted Step 3 — this time strictly after the
recorded approval, honoring the gate I undercut earlier.

### Necessary/useful instructions
- Approval-in-document gate: Step 3 was written only after Step 2 read `APPROVED`.
  The convention is now driving behavior, not just documented. Retain.
- "investigate before asserting": checked the #4 nesting risk against the live
  path during planning (not deferred to implementation) — `decorate()` runs only
  via `RouteServices::pdo()` on a fresh read connection with no surrounding
  `beginTransaction`, so the transaction wrap is safe; kept the `inTransaction()`
  guard anyway. Also confirmed there is no dedicated `AssetFingerprint` test, so
  Stage 3 must add one with a hash-count probe. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates / per-stage stage-work values: not enabled; stages carry no
  numeric estimate lines.
- Read-model schema-change checklist: no schema change.

### Uncertain assessments
- Stage 3's hash-count assertion needs a test seam (counting wrapper or cache-size
  assertion) since `assetHash` is private; flagged as the only non-trivial test
  design in the cycle.
- ≈0.40 reaffirmed; no new scope. Single exposed, non-blind rater.

Step 3 carries `Approval: PENDING`; no branch/commit/implementation until it reads
`APPROVED` for scope v1.
