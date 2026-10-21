> **Feature plan:** [Step 1](./agent_api_docs_step1_solution_assessment.md) · [Step 2](./agent_api_docs_step2_feature_description.md) · [Step 3](./agent_api_docs_step3_development_plan.md) · [Step 4](./agent_api_docs_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Normal entry: an agent fetches `/llms.txt` directly, or finds it from `/api/` or the page head.
- End-to-end outcome: using only the published docs, a gpg+curl script creates an identity and publishes a signed thread on a fresh local instance, then confirms it with `get_post`.
- Recovery: the docs list the main errors with the next action to take, and the retry guidance (check `get_post` first) prevents duplicate posts.
- Deployment/external verification: after deploy, fetch `/llms.txt` and `/api/` on zenmemes and confirm that the facts match its flags. This is a manual check, recorded in the Step 4 summary.
- Release condition: the catalog, `/llms.txt`, docs and worked-example tests pass, and the full PHP suite has no new failures.

## Key Risks

- **Catalog drift from the router.** Impact: listed routes 404 or new routes go unlisted. Early validation: Stage 1's parity test against `Application`'s public `/api/` paths. Mitigation: the test fails on any mismatch.
- **Text that Cycles A-C will change.** Impact: stale retry, attribution or read-API text. Early validation: tag those statements in Stage 3. Mitigation: keep them in catalog notes or a single "Current limitations" section.
- **gpg in CI.** Impact: the worked example is skipped silently. Early validation: gpg is present locally (`/usr/bin/gpg`); check CI in Stage 4. Mitigation: skip with an explicit reason, never pass silently.

## Stage 1
- Goal: one route catalog, with the `/api/` index rendered from it.
- Dependencies: none.
- Expected changes: new `ApiRouteCatalog::entries(): list<{method, path, access, format, purpose}>`. `access` is one of anyone / signed / authenticated / operator, and unsigned post routes are marked "anonymous only". `ApiTextController::index()` renders the catalog, keeps its private-messaging prose, and adds a link to `/llms.txt`.
- Verification approach: a parity test (every `/api/` path the router accepts is in the catalog, and the reverse); an index test that checks access markers and the `/llms.txt` link.
- Risks or open questions:
  - Impact: about 60 entries push the change over 50 lines, but it is mostly data.
  - Early warning / validation: diff the old index text against the new one for any route that disappeared.
  - Mitigation: purpose text kept to one line per entry.
- Canonical components/API contracts touched: `/api/` text response (additive); `ApiTextController`.

## Stage 2
- Goal: generated `/llms.txt`.
- Dependencies: Stage 1.
- Expected changes: new `LlmsTxtRenderer::render(ApiRouteCatalog, FeatureFlagEvaluator): string`. It outputs the agent policy (agents welcome; label accounts as agent accounts, for example with an `-agent` username suffix); the six-step signed flow; instance facts from `FORUM_UNICODE_AUTHORED_TEXT`, `FORUM_APPROVED_MEMBERS_ONLY`, `FORUM_IMAGE_CONTENT_ENABLED` and `FORUM_USERNAME_CHANGES_ENABLED`; and links to `/api/version`, `/api/`, the reference and the routes agents use. It replaces `Application::renderLlmsTxt()`.
- Verification approach: unit tests that toggle each flag and check the matching fact; a route test for `/llms.txt`.
- Risks or open questions:
  - Impact: a cached or static copy of `/llms.txt` could serve stale facts.
  - Early warning / validation: checked; `StaticArtifactBuilder` and `FrontController` do not prebuild it. Recheck in the route test.
  - Mitigation: no-store headers if needed.
- Canonical components/API contracts touched: `/llms.txt`; `FeatureFlagRegistry` (read only).

## Stage 3
- Goal: protocol reference in `/docs`.
- Dependencies: Stage 1 (route names).
- Expected changes: `docs/reference/agent_api.md`, covering fields, response formats, encodings, signing rules, usernames and agent labeling, approval and members-only mode, retries, main errors, and a "Current limitations" section for A-C. Plus a new entry in `PlatformDocsCatalog`.
- Verification approach: a test that every route the doc names exists in the catalog and that the doc renders at `/docs/...`.
- Risks or open questions: the doc stating behavior the code doesn't have; check each claim against the controller code.
- Canonical components/API contracts touched: `PlatformDocsCatalog`.

## Stage 4
- Goal: tested worked example.
- Dependencies: Stages 2 and 3.
- Expected changes: `docs/examples/agent_api_worked_example.sh` (gpg+curl: key, prepare/create identity, prepare/sign/create thread, `get_post`) with sample responses in the reference; a PHP test that runs it against a local server with a temporary GNUPGHOME.
- Verification approach: the test passes locally; it reports a skip when gpg is missing.
- Risks or open questions:
  - Impact: the test is slow or flaky with a real server and gpg.
  - Early warning / validation: reuse the `LocalAppSmokeTest` server setup.
  - Mitigation: if it exceeds this stage, split it into its own cycle (per Step 1).
- Canonical components/API contracts touched: signed write routes (read only, no changes).

## Stage 5
- Goal: invisible discovery.
- Dependencies: Stage 2.
- Expected changes: `<link rel="alternate" type="text/plain" href="/llms.txt">` in `templates/layout.php` and `standalone_layout.php`. No visible copy, and no navigation or icon changes.
- Verification approach: a page test that checks the head link; the UI copy checklist is unaffected.
- Risks or open questions: none material.
- Canonical components/API contracts touched: shared layouts.

## Enhancement Requests

None recorded.
