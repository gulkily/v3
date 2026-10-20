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

## Stage 2 - Generated `/llms.txt`
- Changes:
  - New `LlmsTxtRenderer`, replacing the hard-coded "Local test slice" text. It renders the agent policy, four instance facts (text mode with emoji detail, members-only posting, images, username changes), the six-step signed flow, retry guidance, the agent routes from `ApiRouteCatalog`, and links to `/api/`, `/api/version`, the reference and `/docs/`.
  - The members-only gate now lets `GET /llms.txt` and `GET /api/` through (`Application::membersOnlyRequestAllowed`). Content routes such as `list_index` stay gated. See Enhancement Requests.
  - The `LocalAppSmokeTest` `/llms.txt` assertions are updated for the new content.
- Verification:
  - `php tests/run.php LlmsTxtRendererTest`: 5 passed. Covers each flag on and off, emoji only under Unicode, policy and links, routes drawn from the catalog, and members-only access.
  - Mutation check: disabling the gate change makes the members-only test fail.
  - `php tests/run.php LlmsTxtRendererTest ApiRouteCatalogTest LocalAppSmokeTest`: 147 passed, 2 failed (the same baseline static-build failures as Stage 1). `WebServerRoutingTest UserAccessTest`: passed.
  - Flow claims checked against code: `prepareIdentityBootstrap`/`createIdentityBootstrap` field names, the `openpgp:<fingerprint>` identity ID, username normalization in `OpenPgpKeyInspector`, the 600-second prepare token lifetime, JSON request bodies in `RouteServices::requestData`, and no approval check on signed prepare when members-only is off.
  - Cache risk (Step 3): `/llms.txt` is not prebuilt or cached; it renders per request.
- Notes:
  - I removed a draft line telling agents to "post at a human pace": it was unapproved policy, and Step 1 excludes rate-limit statements. A test guards against "rate limit" text appearing.
  - The reference link resolves after Stage 3.

## Enhancement Requests

- **Request:** commit the onboarding checklist to `main` and push it, so the user can work on other cycles elsewhere. / **Disposition:** separate work, done outside this branch (`c6fe21b4` on `origin/main`); this branch is based on it. / **Approval:** requested by the user. / **Outcome:** pushed.
- **Request:** let `/llms.txt` and `/api/` through the members-only gate. I found during Stage 2 that both returned the lobby page, so agents on members-only instances could not read them. / **Disposition:** in-scope refinement, needed for the Completion Contract's normal entry; it is an access-policy change, so I asked first. / **Approval:** the user chose "Allow both". / **Outcome:** implemented in Stage 2 with a test.
