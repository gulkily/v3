# viewer_nav_prepaint Railspike debug log

## 2026-10-27 — Step 2

**Necessary/useful instructions**
- Step 2 Risks with early validation: forced naming the stale-marker and shared-HTML leakage checks before planning. Retain.
- Shared component inventory: surfaced the existing head pre-paint theme script as the place to extend, rather than a new script. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Step 1 (skipped; user chose the approach in conversation); documentation-only scope.

**Uncertain assessments**
- Step 1 skip: the option comparison happened conversationally rather than in an artifact; recorded the choice and quote in Step 2 instead.

## 2026-10-27 UTC — Step 3

**Necessary/useful instructions**
- Step 3 Do, Completion Contract and risk gating: put first-paint evidence and delayed identity-response checks before dependent unread integration. Retain; final-state screenshots alone would miss the reported failure.
- Experimental Effort Estimation, frozen reference recovery: all three local hashes verified; inherited estimates and authorship retained separately from this exposed rating. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only and schema-change requirements: this is UI work without a schema change. No workflow edits implied.

**Uncertain assessments**
- Step 3 one-page target competes with mandatory per-stage risk/component/estimate fields and the browser matrix. The plan retains those reviewable fields; rendered pagination varies. Consider clarifying a word budget instead of physical pages.

## 2026-10-29T18:37:06Z — Step 2 and Step 3 revision (v3)

**Necessary/useful instructions**
- Changed scope returns to the earliest affected step: the optional Waiting tab is new scope, so Step 2 was revised and needs renewed approval rather than being folded into Step 3. Retain.
- Snapshot rules (append, keep originals, separate new understanding from changed scope): most of the 0.40 → 0.50 revision is new understanding of merged code, not added behavior; recording that split kept the score history honest. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Schema-change and documentation-only requirements: no schema change.

**Uncertain assessments**
- Whether a baseline move after an approved Step 2 counts as a scope change: treated as v3 because the starting revision and the render sites changed, even where user-facing behavior did not.

## 2026-10-29 UTC — Step 4

**Necessary/useful instructions**
- Stage boundary protocol (verify, summarize, commit per stage): caught that removing the server viewer token in Stage 2 would break unread between commits; moving it to Stage 3 kept every commit working. Retain.
- Identify reproduced baseline failures: a `c5783108` worktree separated five pre-existing failures (three PHP, two browser) from the one this cycle caused. Retain.
- Enhancement requests and approval boundaries: the static-release guard conflict was put to the user rather than resolved by relaxing a test silently. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Deployment, migration and schema requirements: none in scope.

**Uncertain assessments**
- First-paint measurement: browser frames painted mid-parse, or before asynchronously loaded stylesheets arrive, produced false movement reports, and one 2–16px move could not be reproduced. The final test compares visibility from parse and positions only after styles load, so movement during that window is not covered. The guidance could say how to measure "final on first paint" when stylesheets load asynchronously.
- Process slip: one accidental `git stash` during Stage 2 verification set aside uncommitted work and the user's `todo.txt` edit; restored immediately with no loss.
