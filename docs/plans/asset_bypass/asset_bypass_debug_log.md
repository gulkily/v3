# Asset bypass — Railspike debug log

## 2026-11-17T03:35Z — Step 1 completed

**Necessary/useful instructions**

- Step 1 problem statement against the code: measuring the board's asset references (34 of 34 fingerprinted, none on disk) showed that the obvious regex fix would have no effect, which reshaped the options around high-traffic mode's existing copies.

**Redundant/unnecessary instructions**

- No supported findings.

**Inapplicable instructions**

- Steps 2–4 rules; planning commit (no approval yet).

**Uncertain assessments**

- The server's local `.htaccess` edit is unknown from the repository; left as a Step 2 question.

## 2026-11-17T03:50Z — Step 2 completed

**Necessary/useful instructions**

- Step 2 "Known user or environment" (`step2_feature_description.md`): naming the production server forced a concrete answer for the deploy-conflict path (`local_changes_conflict`, then the SSH stash steps). Retain.
- Shared component inventory under the implementation standards: reading `FrontController` for reuse turned up two Step 1 inaccuracies (no members-only gate on raw `/assets/*.html`; PHP sends no `nosniff` on assets). Retain.

**Redundant/unnecessary instructions**

- No supported findings.

**Inapplicable instructions**

- Documentation-only scope rules; UI copy and navigation icon rules (no UI change).

**Uncertain assessments**

- Step 1 had already been committed on the branch before this session, although the commit rules keep Steps 1–3 uncommitted until `Approved Step 3`. That looks like a cross-session handoff need in cloud sessions; not judged further here.

## 2026-11-17T04:25Z — Step 3 completed

**Necessary/useful instructions**

- `step3_development_plan_do.md` "Resolve each material risk before dependent work begins": thinking through how Apache merges per-directory rules showed that a `.htaccess` in `public/assets/` could stop root rewrites for that folder. The plan now validates that in Stage 1, before Stage 3, and avoids the file. Retain.
- Known-environment replay requirement: produced Stage 4's stand-in deploy replay, with production marked pending. Retain.

**Redundant/unnecessary instructions**

- No supported findings.

**Inapplicable instructions**

- Documentation-only rules; database change rules.

**Uncertain assessments**

- The approval arrived without answers to the open Step 2 questions. The instructions do not say how an approval relates to recommended defaults stated in the artifact. I treated it as covering the recommendations and kept the unanswerable question as a risk.

## 2026-11-17T05:05Z — Step 4 completed

**Necessary/useful instructions**

- Step 3 requirement to replay a known-environment failure (`step3_development_plan_do.md`, carried into Stage 4): the stand-in deploy replay found a real conflict. The plain-file header block sat at the end of `public/.htaccess`, so any server edit appended there would have made the deploy refuse. Retain.
- `implementation_standards.md` "identifying reproduced baseline failures": running the Apache test on the base commit separated four pre-existing failures from this branch's changes. Retain.
- Key Risks with early validation (Step 3): Stage 1's throwaway Apache showed that the planned risk did not hold. Without the experiment, a wrong comment would have shipped. Retain.

**Redundant/unnecessary instructions**

- No supported findings.

**Inapplicable instructions**

- Documentation-only rules; UI and navigation icon checks (no UI change).

**Uncertain assessments**

- The user's standing rule says to fix tests that fail on the base commit as part of the work, but two of those fixes are product decisions under another plan's approval boundary (the retraction handoff). Railspike's enhancement classification ("Ambiguous: clarify before dependent work") covered it, but only indirectly. A line on pre-existing failures that need a product decision might help.
- `StatusCommandTest` fails whenever `public/.htaccess` has uncommitted edits. Stage-boundary test runs before the commit therefore show a false failure, and I reran after each commit. The Stage Boundary Protocol puts verification before the commit; it does not cover tests that depend on a clean working tree.
