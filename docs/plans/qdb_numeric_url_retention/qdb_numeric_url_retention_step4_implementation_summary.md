> **Feature plan:** [Step 1](./qdb_numeric_url_retention_step1_solution_assessment.md) · [Step 2](./qdb_numeric_url_retention_step2_feature_description.md) · [Step 3](./qdb_numeric_url_retention_step3_development_plan.md) · [Step 4](./qdb_numeric_url_retention_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Runtime numeric quote pages

- Changes: QDB numeric resolution now calls the existing thread detail controller and returns its page directly. Exact-ID aliases retain redirects; missing numbers retain 404. Updated runtime and fallback expectations and extended the existing vote-state test to cover numeric quote pages and flag state.
- Verification: `./v3 test QuoteCardDisplayNumberTest QdbExperienceRoutingTest`: 25/26 pass. The welcome-page copy assertion (`⚑ Flag something that`) also fails at planning baseline `3ded830c` in an isolated worktree. `./v3 test WriteApiSmokeTest::testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote`: passes, including numeric-page vote/flag state and anonymous state. PHP lint and `git diff --check` pass.
- Verification: Local PHP server and real Chrome/Playwright against a disposable quote #123: listing click, refresh, copied-link reopening retain `/123`; HTTP 200 with no Location header or redirect chain; missing number returns 404; no browser page errors.
- Notes: Planning commit `3ded830c`; branch `feature/qdb-numeric-url-retention`. Updated the existing static-fallback assertion here because it exercises the runtime behavior changed in this stage; Stage 2 expands delivery verification. No schema migration or deployment performed.
