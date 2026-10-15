> **Feature plan:** [Step 1](./invite_zero_click_step1_solution_assessment.md) · [Step 2](./invite_zero_click_step2_feature_description.md) · [Step 3](./invite_zero_click_step3_development_plan.md) · [Step 4](./invite_zero_click_step4_implementation_summary.md)

Debug mode: enabled

# Implementation summary

## Stage 1 - Opt-in destination
- Changes: Invite captures path/query/fragment; checking destination prefills the source once and preserves edits. Shared server validation accepts fragments and rejects off-site/control/backslash escapes; client validation agrees.
- Verification: `php tests/run.php InvitationIssuanceTest CanonicalRecordParsersTest` — 53 passed; `git diff --check` passed.
- Notes: Existing fragment-rejection expectation updated for the approved behavior; browser acceptance remains Stage 7.
