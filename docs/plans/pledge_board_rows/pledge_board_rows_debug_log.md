# Pledge board rows debug log

## 2026-11-17T21:35Z — Step 1

**Necessary/useful instructions**
- `AGENTS.md` "Implementation standards … inspect existing implementations": reading the board and the invalidator found that approval and replies leave `/threads/`'s baked copy in place. That gap decides whether Option A is correct for anonymous visitors, so it went into the option. Retain.
- `step1_solution_assessment.md` "Original Query … do not summarize or omit anything": a first draft elided the request's setup half; the rule caught it, and the query is now whole. Retain.
- `RAILSPIKE.md` "Extended estimates … every Step 1 option gets a complete scoped-cycle estimate": three options with estimates made the cost comparison the maintainer asked for explicit. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- The `/new or /clear` reminder: this session had no discussion before the Step 1 task, so it is omitted.

**Uncertain assessments**
- The setup half of the request (clone, study, PR habits) makes the Original Query long and is not about the feature. Railspike could say whether such operational instructions may be summarized there; this artifact keeps them whole.
- `.railspike_debug_on` exists; enabled. R1 hashes not re-verified.

## 2026-11-17T21:40Z — Step 2

**Necessary/useful instructions**
- `implementation_standards.md` "Identify all existing UI/API surfaces that already render the relevant data": checking how the board picks its rows found that the default view is Liked, so an unliked campaign is not on the default board at all. That bears on "easy to find" and went to the maintainer as a question instead of being built. Retain.
- `step2_feature_description.md` "Known user or environment": the maintainer's iPhone SE check is stated as pending, and the Liked rule is named as the thing that could hide the demo thread from him. Retain.
- `step2_feature_description.md` "A feature is a normal-flow vertical slice, not a … cache": the invalidation work is framed as part of the board outcome (current numbers for a visitor with no cookies), not as a cache task. Retain.

**Redundant/unnecessary instructions**
- `step2_feature_description.md` "Completion boundary … needed recovery": the row has no action to fail, so recovery is restated as guarding against stale numbers. The field fits read-only features awkwardly; Railspike could say recovery may be "not applicable, because …".

**Uncertain assessments**
- The maintainer approved Step 1 without naming an option; Option A was the recommendation and the only one compared in the follow-up, so it is recorded as chosen.

## 2026-11-17T21:50Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md` "Resolve each material risk before dependent work begins": checking operator grants for the freshness risk found they already clear every page (`invalidateApprovalSeed()`), so only operator removal is left to confirm in Stage 3. Retain.
- `implementation_standards.md` "Inspect existing implementations": `Application::fetchThreadPosts()` returns exactly the shape `fromPosts()` needs, so Stage 1 reuses it instead of a batched copy. This departs from Step 2's suggested mitigation; the plan says so and keeps batching as a fallback. Retain.
- User preference "fix any test that fails on the base commit": written into Stage 1's dependencies as a base run before any change. Retain (host instruction, not Railspike).

**Redundant/unnecessary instructions**
- `step3_development_plan_do.md` "include a stage that replays each reported failure": the maintainer reported no failure, only a missing feature. Stage 4's replay covers his ordinary check instead. The instruction could say what to do when no failure was reported.

**Uncertain assessments**
- Step 2's open question was not answered before approval. Treating the suggested answer as chosen follows earlier cycles' practice, and the Status line records it.

## 2026-11-17T22:25Z — Step 4

**Necessary/useful instructions**
- `step4_implementation_before.md` "Planning Docs Commit Gate": PR #319 merged between Step 3 and Step 4. Branching from a fresh `origin/main` and recording the moved base in the summary kept the planning commit clean. Retain.
- `implementation_standards.md` "Replay each reported failure in the relevant user/environment": the browser fixture's router does not bake pages, so the existing check could not show the visitor-with-no-cookies case. A separate replay through `FrontController` with a baked board did, and it is the only evidence that approval refreshes what such a visitor sees. Retain.
- `RAILSPIKE.md` "Enhancement Requests … record every request before acting": the excerpt refinement was found by looking at screenshots, not requested. Recording it kept the Stage 2 diff explainable. Retain.
- Host rule "fix any test that fails on the base commit": a base run of the named tests before Stage 1 found none. Retain.

**Redundant/unnecessary instructions**
- `step4_implementation_do.md` "Stage Boundary Protocol" steps 4 and 6 (`git status`, then `git log` after each commit): useful, but they repeat what a commit's output already shows. Railspike could fold them into one "confirm the commit" step.

**Inapplicable instructions**
- Documentation-only verification rules: this was an application change.

**Uncertain assessments**
- The thumbnail row was planned in Stage 2 but not exercised; it is recorded as pending, not done. Railspike has no word for a planned check dropped as low-risk; "pending" overstates the intent to come back to it.
- `.railspike_debug_on` exists; enabled. R1 hashes not re-verified.
