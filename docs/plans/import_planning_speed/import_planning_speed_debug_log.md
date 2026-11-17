# Import planning speed debug log

## 2026-11-13T04:50Z — Step 1

**Necessary/useful instructions**
- `step1_solution_assessment.md` Structure (Problem, options, Recommendation): a profiled cause made the comparison short; retain.
- `RAILSPIKE.md` Enhancement Requests: gave a place to record the progress fix done as direct work beside the cycle; retain.
- `AGENTS.md` "For a reported user/environment failure, replay that condition": led to reproducing the stall against the live archive before writing options; retain.

**Redundant/unnecessary instructions**
- `RAILSPIKE.md` Experimental Effort Estimation: intake and Step 1 snapshots are identical when both follow inspection. Suggest allowing one combined line.

**Inapplicable instructions**
- Step 1 "Next" /new or /clear reminder: no discussion preceded this Step 1, so it was omitted.

**Uncertain assessments**
- Timings come from a cloud Linux checkout, not the reporter's WSL machine. The WSL gain may differ.

## 2026-11-13T05:30Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` "Known user or environment": required naming WSL timings as acceptance evidence, which cloud Linux runs cannot replace; retain.
- `implementation_standards.md` component inventory: confirmed the cache and verifier already exist and only need wiring and extension; retain.

**Redundant/unnecessary instructions**
- Effort-study snapshot repeated at Step 2 with an unchanged value. Suggest a ledger-only "reaffirmed" line when scope is unchanged.

**Inapplicable instructions**
- Documentation-only scope fields: this is an application change.

**Uncertain assessments**
- The "about 1.5 processes per signature" criterion assumes few distinct signer keys per run; Step 3 should confirm the key count in the archive.

## 2026-11-13T05:45Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md` "replays each reported failure": produced Stage 4, which keeps cloud timings from standing in for WSL; retain.
- `step3_development_plan_do.md` stage template (Canonical components bullet): kept the plan on `OpenPgpSignatureVerifier` instead of a second verifier; retain.

**Redundant/unnecessary instructions**
- Three Step 3 phase files for a four-stage plan; the Before checklist mostly repeats `RAILSPIKE.md`. Suggest folding Before into Do for small cycles.

**Inapplicable instructions**
- Database change documentation: no schema change; the cache table already exists.

**Uncertain assessments**
- Whether a dry-run preview may write the derived cache is a maintainer choice; recorded as a Stage 1 open question.

## 2026-11-17T06:40Z — Step 4

**Necessary/useful instructions**
- `step4_implementation_before.md` planning-commit gate: rebasing onto a moved `main` before the planning commit kept the cycle's history clean after a four-day pause; retain.
- `implementation_standards.md` "Check affected shared consumers": running every `ReactionSignature` consumer's tests found the killed-process leak in `ImportDeadlineRecoveryTest`, which the verifier tests alone missed; retain.
- `step3_development_plan_do.md` replay stage for the named environment: kept cloud timings from closing the cycle; Stage 4 stays pending for WSL; retain.

**Redundant/unnecessary instructions**
- Ledger rows duplicate the stage estimate history already in the Step 4 summary. Suggest a single "see summary" link per stage.

**Inapplicable instructions**
- Documentation-only verification fields: application change.
- Database migration checks: the cache table already existed.

**Uncertain assessments**
- Missing guidance: nothing in Step 4 says what to do when `main` gains overlapping work (here, planning progress) while Step 3 awaits approval. Recorded it as reference drift in the summary; suggest a named rule.
- Missing guidance: no instruction covers a sandbox that can no longer reach the source (PHP downloads failed after the pause). Served a fresh archive on loopback and labeled it.
