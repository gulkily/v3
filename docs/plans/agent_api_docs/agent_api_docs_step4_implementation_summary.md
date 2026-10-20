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

## Stage 3 - Protocol reference
- Changes:
  - New `docs/reference/agent_api.md`, covering policy, request encodings and response formats, identities and usernames, signing, the flow, text rules, approval and members-only access, unsigned routes, retries, main errors, and "Current limitations" (the statements Cycles A-C will change).
  - New `PlatformDocsCatalog` entry, "Agent API Reference" (Extending the Platform).
- Verification:
  - `php tests/run.php LlmsTxtRendererTest PlatformDocsCatalogTest PlatformDocsPageTest PlatformDocsStaticTest`: 19 passed. The new test checks that every route the reference names is in `ApiRouteCatalog`, and that the reference renders at `/docs/reference/agent_api.md`.
  - Each error message and field name was checked against `LocalWriteService`, `AuthApiController`, `OpenPgpKeyInspector` and `RouteServices`. The rendered checklist link resolves to `/docs/plans/agent_api_onboarding_checklist.md`.
- Notes:
  - The worked-example script is not linked from the reference: `/docs` only serves Markdown. Stage 4 decides where the example's sample responses go.

## Stage 4 - Tested worked example
- Changes:
  - New `docs/examples/agent_api_worked_example.sh` (gpg, curl, and python3 for JSON). It makes an Ed25519 key whose user ID is the username, creates the identity, prepares, signs and publishes a thread, then confirms it with `get_post`.
  - New `AgentApiWorkedExampleTest`. It boots a fresh instance (`LocalRepositoryBootstrap`) behind `FrontendTestServer`, runs the script with an isolated GNUPGHOME, and checks the transcript and the new profile (`Username: example-agent`, `Posts: 1`).
  - Reference: added a "Sample session" taken from a real run.
- Verification:
  - `php tests/run.php AgentApiWorkedExampleTest`: passed. On its first run, the test found that `create_prepared_post` also requires `record_path`, `post_id` and `author_identity_id` echoed back unchanged (`Prepared post record_path mismatch.`). That was missing from `/llms.txt` step 5 and the reference. Both now say it, and the reference error table has a `<field> mismatch` row.
  - `php tests/run.php AgentApiWorkedExampleTest LlmsTxtRendererTest ApiRouteCatalogTest PlatformDocsCatalogTest PlatformDocsPageTest LocalAppSmokeTest`: 162 passed, 2 failed (the same baseline static-build failures).
- Notes:
  - Deviation from Step 3: the plan said to skip when gpg is missing, but `tests/run.php` has no skip mechanism and many existing tests already require gpg. The test fails with a message naming the missing tool, so it never passes silently.
  - The script needs python3 to read JSON. No jq is installed here, and python3 is more common in agent sandboxes.
  - Step 1 called gpg's default key "Ed25519". gpg 2.2 defaults to RSA, so the script names `ed25519` explicitly and the reference no longer calls it the default.

## Enhancement Requests

- **Request:** commit the onboarding checklist to `main` and push it, so the user can work on other cycles elsewhere. / **Disposition:** separate work, done outside this branch (`c6fe21b4` on `origin/main`); this branch is based on it. / **Approval:** requested by the user. / **Outcome:** pushed.
- **Request:** let `/llms.txt` and `/api/` through the members-only gate. I found during Stage 2 that both returned the lobby page, so agents on members-only instances could not read them. / **Disposition:** in-scope refinement, needed for the Completion Contract's normal entry; it is an access-policy change, so I asked first. / **Approval:** the user chose "Allow both". / **Outcome:** implemented in Stage 2 with a test.
