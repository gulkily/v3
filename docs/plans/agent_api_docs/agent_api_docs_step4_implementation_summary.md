> **Feature plan:** [Step 1](./agent_api_docs_step1_solution_assessment.md) · [Step 2](./agent_api_docs_step2_feature_description.md) · [Step 3](./agent_api_docs_step3_development_plan.md) · [Step 4](./agent_api_docs_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feat/agent-api-docs`, from `origin/main` at `c6fe21b4` (which adds the [onboarding checklist](../agent_api_onboarding_checklist.md), pushed at the user's request). Planning commit: `6d404f4b`.

## Stage 1 - Route catalog and `/api/` index
- Changes:
  - New `ApiRouteCatalog`: every routed `/api/` path with method, access (`public`, `anonymous`, `signed`, `hint`, `session`, `member`, `operator`, `testing`, `retired`), format and purpose. Access and format were checked by probing each route on a local app.
  - `/api/` now renders the catalog with an access legend and a pointer to `/llms.txt`. The private-messaging prose is unchanged.
  - The index now also lists routes it used to omit: `prepare_*`/`create_prepared_*`, profile name, approval, invitation, retraction, Forte fragments, `read_model_status`. `agent_expansion_status` and `private_messages/conversation` lines now show their full query parameters.
  - `create_thread`, `create_reply` and `create_quote` are marked `anonymous` ("Anonymous ... only").
- Verification:
  - `php tests/run.php ApiRouteCatalogTest`: 3 passed. Mutation check: removing `read_model_status` from the catalog makes the parity test fail.
  - Old index route lines compared with the new index: none lost (two lines gained query parameters). Private-messaging prose is byte-identical.
  - `php tests/run.php ApiRouteCatalogTest LocalAppSmokeTest PrivateMessageFeatureFlagTest`: 148 passed, 2 failed. Both failures (`testBuildStaticCommandReportsProgressAndArtifactSummary`, `testSharedStaticRefreshCommandKeepsDetailArtifactsWithoutRebuildingTheReadModel`) reproduce on the base commit without this change.
- Notes:
  - `apply_thread_tag`/`apply_post_tag` are marked `hint`: they act on the unsigned identity hint cookie. This is related to the Cycle A attribution finding; flagged for Cycle A, not changed here.
  - The parity test reads the `/api/` literals in `Application.php`, the only file that routes them.

## Enhancement Requests

- **Request:** commit the onboarding checklist to `main` and push it, so the user can work on other cycles elsewhere. / **Disposition:** separate work, done outside this branch (`c6fe21b4` on `origin/main`); this branch is based on it. / **Approval:** requested by the user. / **Outcome:** pushed.
