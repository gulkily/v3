> **Feature plan:** [Step 1](./qdb_numeric_url_retention_step1_solution_assessment.md) · [Step 2](./qdb_numeric_url_retention_step2_feature_description.md) · [Step 3](./qdb_numeric_url_retention_step3_development_plan.md) · [Step 4](./qdb_numeric_url_retention_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Runtime numeric quote pages

- Changes: QDB numeric resolution now calls the existing thread detail controller and returns its page directly. Exact-ID aliases retain redirects; missing numbers retain 404. Updated runtime and fallback expectations and extended the existing vote-state test to cover numeric quote pages and flag state.
- Verification: `./v3 test QuoteCardDisplayNumberTest QdbExperienceRoutingTest`: 25/26 pass. The welcome-page copy assertion (`⚑ Flag something that`) also fails at planning baseline `3ded830c` in an isolated worktree. `./v3 test WriteApiSmokeTest::testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote`: passes, including numeric-page vote/flag state and anonymous state. PHP lint and `git diff --check` pass.
- Verification: Local PHP server and real Chrome/Playwright against a disposable quote #123: listing click, refresh, copied-link reopening retain `/123`; HTTP 200 with no Location header or redirect chain; missing number returns 404; no browser page errors.
- Notes: Planning commit `3ded830c`; branch `feature/qdb-numeric-url-retention`. Updated the existing static-fallback assertion here because it exercises the runtime behavior changed in this stage; Stage 2 expands delivery verification. No schema migration or deployment performed.

## Stage 2 - Hosted delivery and release verification

- Changes: Extended the static-release regression to request numeric pages with/without cookies before and after withdrawing prebuilt details. Added Leetness to numeric listing-link coverage. Existing static artifacts, Apache rewrites, and reaction APIs required no changes.
- Verification: The expanded static-release regression and listing-link test pass individually. Full `./v3 test`: **1106 run, 1098 passed, 8 failed**; the final listing-test extension also passes after that run. PHP lint and `git diff --check` pass.
- Verification: Real local Apache using the shipped `.htaccess`, Chrome, and a disposable QDB repository passed all four combinations of numeric static artifact present/absent and anonymous/saved viewer. Each retained `/123` through listing click, refresh, and copied-link reopening, returned 200 without a Location header, preserved long-link access, and returned 404 for missing numbers. Anonymous static output matched the prebuilt quote artifact exactly.
- Verification: Browser vote and flag actions succeeded at `/123` and remained pressed after refresh. Saved viewer state survived both static-present and runtime-fallback journeys. Browser checks wait for identity initialization before clicking; an initial harness attempt raced that initialization. Latest, Top, Leetness, Random, and Search links were exercised in Chrome. Temporary harnesses and logs reside in `/tmp/qdb-url-check/`.
- Notes: All eight suite failures reproduce at baseline `3ded830c`: core-route account-link copy; feature-flag lock tooltip; two offline snapshot bootstrap tests; two offline snapshot publish tests; QDB welcome copy; queued agent reply processing. The tooltip and queue failures require the same private configuration as this checkout; they pass in the configuration-free baseline worktree. No new failing test identified.
- Notes: Application scope and completion contract are satisfied. Verification used local hosting; production deployment, schema migration, and external-service changes are outside this change. Existing suite failures remain unrelated follow-up work.
