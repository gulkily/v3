# Repository cleanup — Railspike debug log

## 2026-10-12T19:41Z — Step 2 (feature description)

### Necessary/useful instructions
- `docs/workflow/step2_feature_description.md` § Delivery scope (documentation-only: allowed files, audience, no runtime changes): turned "remove unused files" into a bounded file scope and kept `index.html`, scripts, and workflow files out. Retain.
- § Risks (impact, earliest validation, mitigation): surfaced the untracked-file loss risk and the live-looking-draft risk before any file is touched. Retain.
- `RAILSPIKE.md` § Debug Mode (durable state under the nav bar): gave a clear place to record the user's "debug mode on" request. Retain.

### Redundant/unnecessary instructions
- The reprint-instructions rule appears in `RAILSPIKE.md` § How to Use This Chain and § Key Rules, and again in the README Tips. Suggest consolidating to one statement with links.
- Step 2 § Guardrails repeats the documentation-only boundary already stated in § Delivery scope and in `RAILSPIKE.md` § Documentation-only scope. Suggest keeping one source.

### Inapplicable instructions
- Plan navigation Step 1 link: Step 1 was skipped at the user's request, so it is omitted as the template allows.
- Shared component inventory for UI/API surfaces: this feature renders no data; entries describe documentation indexes instead.

### Uncertain assessments
- The user's "go straight to step 2" follows a `/clear`, so "step 2" was resolved as Railspike Step 2 from the repository's process files and the backlog item's text. If the user meant another step 2, this artifact needs revision.
- The instructions do not say where a debug log lives when plans are not yet in `docs/plans/{feature_name}/`; it was placed beside the Step 2 artifact in `docs/plans/` and will move with the plans at organization time.
- No guidance covers untracked files that the cleanup could affect (`railspike.txt`, `temp/`); they were treated as protected.

## 2026-10-12T19:42Z — Step 1 (solution assessment)

Written after Step 2 at the user's request ("go back and write step 1"); Step 2 remains a draft pending Step 1 approval.

### Necessary/useful instructions
- `docs/workflow/step1_solution_assessment.md` § Structure (Original Query first, then Understood Intent): kept the backlog wording intact while recording the evidence that most files are still referenced. Retain.
- § Recommendation "vertical-slice viability": forced a definition of the releasable slice (documented, link-clean layout). Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- `## Review and revision`: no revision requested yet.

### Uncertain assessments
- The instructions do not cover writing a Step 1 after a Step 2 already exists; Step 2's nav bar was updated to add the Step 1 link (a navigation repair only) and its content was not changed. Step 2 may need revision once Step 1 is approved or revised.
- Which files count as "verifiably finished" is not decided here; Step 2/3 must establish it.

## 2026-10-12T23:43Z — Step 1 (solution assessment), Revision

Revision superseding the `Inapplicable instructions` group of the 2026-10-12T19:42Z Step 1 entry.

### Necessary/useful instructions
- `step1_solution_assessment.md` § Review and revision ("Preserve the Original Query; record the revised direction separately"; "does not approve the step"): applied to the user's choice of Option C, so the original recommendation was kept as superseded and approval is still requested. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- No supported findings; the revision section earlier listed as inapplicable now applies.

### Uncertain assessments
- The user's option choice is treated as a revision, not as "Approved Step 1", because the instructions require the exact phrase. The Step 1 text does not say whether choosing an option counts as approval; it was resolved conservatively.

## 2026-10-12T23:44Z — Step 2 (feature description), Revision

Revision superseding the 2026-10-12T19:41Z Step 2 entry; Step 2 was rewritten for Option C after "Approved Step 1".

### Necessary/useful instructions
- `step2_feature_description.md` § Risks (impact, earliest validation, mitigation): a grep for citations of the four removal candidates found open backlog items and two historical plan mentions, changing the risk list. Retain.
- § Delivery scope (documentation-only file list): bounded removals and reference repairs to named files. Retain.
- `RAILSPIKE.md` § Debug Mode (inherit state): the field carried over from Step 1 without a new request. Retain.

### Redundant/unnecessary instructions
- Step 2 § Guardrails restates the documentation-only boundary already in § Delivery scope. Suggest keeping one source.

### Inapplicable instructions
- Shared component inventory for UI/API surfaces: no data is rendered; entries describe documentation indexes.

### Uncertain assessments
- The user did not answer the two Step 1 questions (prune plans or index only; drop or rewrite backlog items). Defaults were applied (keep plans; drop items that exist only for a removed file, rewrite the others) and surfaced for confirmation, not treated as decided.
- The instructions do not say how a deletion-only cleanup satisfies "normal entry, end-to-end outcome"; the GitHub page view was used as the entry point.

## 2026-10-12T23:50Z — Step 2 (feature description), Revision

Revision superseding the 2026-10-12T23:44Z Step 2 entry findings only where noted; the user asked that files be moved to a directory in `~` instead of deleted.

### Necessary/useful instructions
- `step2_feature_description.md` § Delivery scope (allowed files, no runtime changes): kept the new out-of-repo destination explicit and bounded to this feature. Retain.
- § Risks (mitigation before Step 3): the move changes the recovery path, so the risk and completion text were updated before planning. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- No supported findings.

### Uncertain assessments
- The destination name `~/railspike-archive/` was chosen by the assistant; the user specified only "a directory in ~". It is surfaced for confirmation.
- The instructions do not cover file operations outside the repository; the move was scoped as part of documentation-only work because it only relocates documents.

## 2026-10-12T23:55Z — Step 3 (development plan)

### Necessary/useful instructions
- `step3_development_plan_do.md` § Structure (documentation-only Completion Contract: file scope, evidence boundary, not-applicable checks): produced a bounded contract and a hash-based evidence list. Retain.
- § Key Risks with `**High risk:**` prefix: led to ordering reference repairs before the move so no intermediate commit fails link checks. Retain.
- `step3_development_plan_before.md` § Before Checklist (confirm no scope added after approval): the two Step 1 defaults were carried unchanged. Retain.

### Redundant/unnecessary instructions
- The "keep Step 1–3 uncommitted" rule appears in `step3_development_plan_before.md`, `step3_development_plan_after.md`, and `RAILSPIKE.md` § Commit discipline. Suggest consolidating to one statement with links.
- `step3_development_plan_after.md` item 1 repeats the `Do` file's structure list. Suggest linking instead.

### Inapplicable instructions
- Database, function-signature, and UI/API contract guidance in the `Do` file: no application code is touched.

### Uncertain assessments
- Stage order was chosen to keep every commit link-clean; the instructions do not say how to order stages for deletion-style work.
- The plan assumes `BACKLOG.md`'s pending edit may be committed with Stage 1; the instructions do not cover pre-existing uncommitted maintainer changes.

## 2026-10-12T23:56Z — Step 3 (development plan), Revision

Revision to the 2026-10-12T23:55Z Step 3 entry: the maintainer excluded `BACKLOG.md` at approval.

### Necessary/useful instructions
- `RAILSPIKE.md` § Planning Artifacts ("Do not ... silently revise approved scope"): the exclusion was recorded as an approval note instead of rewriting the approved stages. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- No supported findings.

### Uncertain assessments
- "You can leave the backlog file" can mean either "do not edit `BACKLOG.md`" or "leave the files it cites in place". It was read as the former, which conflicts with moving files that `BACKLOG.md` links to; the instructions do not say how to resolve a scope conflict raised at approval time, so Stage 2 is held for clarification.

## 2026-10-13T00:01Z — Step 4 (implementation, Stage 1 boundary), planning revision

### Necessary/useful instructions
- `RAILSPIKE.md` § Key Rules ("Flag scope creep early and bounce back to planning steps rather than improvising"): stopped a changed destination and an in-scope `BACKLOG.md` edit from being applied without renewed approval. Retain.
- Step 4 Stage Boundary Protocol (hash baseline, link check): exposed the dead-link conflict before any file was moved. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- No supported findings.

### Uncertain assessments
- "Move them somewhere not in the top-level directory" was read as applying to the two root files only (the other two are already under `docs/`); the destination `docs/launch/` was chosen by the assistant.
- The instructions do not say whether a scope change stated mid-Step 4 needs both `Approved Step 2` and `Approved Step 3`; both are requested conservatively.

## 2026-10-13T00:04Z — Step 4 (implementation)

### Necessary/useful instructions
- `step4_implementation_do.md` § Stage Boundary Protocol (hash baseline, link check, `git status` before commit): the Stage 2 status check exposed that the moved checklist was not staged, so the loss was caught before handoff. Retain.
- `step4_implementation_before.md` § Planning Docs Commit Gate: produced a clean first commit that excluded `BACKLOG.md` and `railspike.txt`. Retain.
- `RAILSPIKE.md` § Key Rules (bounce back to planning on scope change): handled the redirect after Stage 1. Retain.

### Redundant/unnecessary instructions
- The commit-count rule (`1 + stages`) appears in `RAILSPIKE.md` and `step4_implementation_after.md`; suggest one statement with a link.

### Inapplicable instructions
- Step 4 After item 5 (deployment, release, migration dependencies): documentation-only; recorded as not applicable.

### Uncertain assessments
- The instructions do not warn that a repo-local exclude file can silently untrack a moved file; the correction commit `9abcd20` followed. A "stage the move, then inspect staged renames" check could be added.
- Whether one `Approved Step 3` covers a revised Step 2 is not specified; it was taken as covering it, with the choice recorded in the summary.
- The `feature/repo-cleanup` branch name follows existing `feature/*` branches; the instructions do not name a convention.

## 2026-10-13T00:06Z — Step 4 handoff, planning revision

### Necessary/useful instructions
- `step4_implementation_after.md` § Next ("If additional scope emerges, return to the appropriate earlier step instead of improvising"): applied to the request to move `BACKLOG.md`, which was outside the approved moves. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- No supported findings.

### Uncertain assessments
- The destination `docs/BACKLOG.md` was chosen by the assistant; the user said only "out of top-level dir". A one-file move still passes through Step 2/3 approval; the instructions do not offer a lighter path for trivial additions.

## 2026-10-13T01:37Z — Step 4 handoff, planning revision (Revision)

Revision superseding the preceding planning-revision entry's scope: the request now includes organizing `docs/`.

### Necessary/useful instructions
- `RAILSPIKE.md` § Key Rules ("Flag scope creep early and bounce back to planning steps"): applied again, because "organize the docs directory" widened the one-file move. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- No supported findings.

### Uncertain assessments
- "Organize the docs directory" is open-ended; the layout under `docs/maintainers/` and a `docs/README.md` index are the assistant's proposal, not a stated preference.
- The user message was not the exact phrase `Approved Step N`, so it was treated as a revision request.

## 2026-10-13T01:55Z — Step 4 (implementation), Revision

Revision superseding the 2026-10-12 Step 4 entry's counts and stage list: Stages 5–6 were added after the handoff.

### Necessary/useful instructions
- `step4_implementation_do.md` § Stage Boundary Protocol (git status before commit, link checks): confirmed the excluded checklist stayed tracked via `git mv` this time. Retain.
- Documentation-only verification list (`git diff --check`, scope check, link validation): word-level comparison against `HEAD` proved moved files changed only in link targets. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Step 4 After item 5 (deployment, release, migration): not applicable to this documentation-only work.

### Uncertain assessments
- Stage 5 absorbed the `README.md` backlog link repair planned for Stage 6 so that its commit passed the link checkers; the plan's stage boundary was adjusted, not its scope.
- The instructions do not define how to treat an empty directory left by a move; it was removed with `rmdir`.
- A second `Approved Step 3` alone was taken as covering the revised Step 2, as in the earlier revision.
