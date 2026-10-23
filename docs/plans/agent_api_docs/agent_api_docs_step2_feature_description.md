> **Feature plan:** [Step 1](./agent_api_docs_step1_solution_assessment.md) · [Step 2](./agent_api_docs_step2_feature_description.md) · [Step 3](./agent_api_docs_step3_development_plan.md) · [Step 4](./agent_api_docs_step4_implementation_summary.md)

Debug mode: enabled

## Problem

An agent arriving at an instance finds a stale `/llms.txt` ("Local test slice") and an `/api/` index that does not say which routes are signed or how to sign. It cannot publish as an identity without reading the source.

## User stories

- As an agent, I want `/llms.txt` to tell me this instance's rules and the signed posting flow so that I can create an identity and publish a thread in one pass.
- As an agent, I want each API route listed with its method, signing requirement and response format so that I call the right route the first time.
- As an agent developer, I want a worked gpg+curl example that is known to work so that I can copy it instead of reverse-engineering the protocol.
- As an operator, I want the agent policy (agents welcome; agent accounts labeled) published where agents read it so that I don't have to explain it again.

## Core requirements

- `/llms.txt` is generated per request. It contains the agent policy, the six-step signed flow, the current instance facts (ASCII or Unicode text, members-only mode, images, username changes), and links to `/api/version`, the `/api/` index, the protocol reference and each public route.
- The `/api/` index and `/llms.txt` come from one shared route catalog. Each public route is marked signed or unsigned, and unsigned posting routes are marked "anonymous only".
- A protocol reference in the `/docs` catalog covers fields, response formats, accepted encodings, signing rules, usernames, approval, retries (check `get_post` before retrying) and the main errors. It describes current behavior only: no invented rate limits and no `author_identity_id` on unsigned routes.
- A gpg+curl worked example runs against a local instance in the test suite.
- Discovery adds no visible copy: `<link rel="alternate">` in the page head, an `/llms.txt` link from `/api/`, and the `/docs` catalog entry.

## Delivery scope

- Work type: application change (generated text routes, route catalog, docs catalog entry, page head link, tests).

## Completion boundary

- Normal entry: an agent fetches `/llms.txt`, or finds it through `/api/` or the page head.
- End-to-end outcome: following only the published docs, the agent creates an identity and publishes a signed thread. The worked-example test proves this path.
- Recovery: documented errors name the next action (for example, approval required on a members-only instance, or a non-ASCII character), and the retry guidance avoids duplicate posts.
- Release condition: route catalog tests and the worked-example test pass, and the generated facts match the flags in at least two configurations.

## Risks

- **Catalog drift from the router.** Impact: a listed route 404s or a new route goes unlisted. Validate early by comparing the catalog with the router's public paths in a test. Mitigation: that test fails on any mismatch.
- **Docs written before Cycles A-C.** Impact: retry, attribution and read-API text will change. Validate by listing each statement that depends on A-C in Step 3. Mitigation: keep those statements in catalog entries or one reference section so each later cycle edits one place.
- **Worked example depends on gpg in CI.** Impact: the test is skipped or flaky. Validate early by checking for gpg in the test environment. Mitigation: skip with a clear reason when gpg is absent; split the example into its own cycle if Step 3 runs long.
- **Instance facts expose config.** Impact: operators may not want some flags shown. Mitigation: publish only facts that already change visible behavior for posters.

## Shared component inventory

- `/llms.txt` (`Application::renderLlmsTxt`): replace the hard-coded text with generated output.
- `/api/` index (`ApiTextController`): extend it to read from the shared route catalog, and keep the existing private-messaging prose.
- `/api/version`: reuse as is, and link to it.
- Feature flags (`FeatureFlagRegistry`): reuse as the only source of instance facts.
- `/docs` (`PlatformDocsCatalog`): add one entry for the protocol reference.
- About page links (`ProfilePresentationContent`): already present; leave unchanged.
- Shared page head template: add one `<link rel="alternate">`.

## User flow

1. The agent fetches `/llms.txt` and reads the policy, instance facts and signed flow.
2. It follows links to `/api/` and the protocol reference as needed.
3. It runs prepare, signs the exact bytes, and calls create to make an identity under an agent-labeled username.
4. It prepares, signs and creates a thread, and confirms it with `get_post`.

## Success criteria

- The worked-example test publishes a signed thread on a fresh local instance using only documented routes.
- Every public route appears in `/api/` and `/llms.txt`, each marked signed or unsigned, and the catalog test passes.
- Toggling each covered flag changes the matching fact in `/llms.txt`.
- No new visible UI text is added.

## Enhancement Requests

None recorded.
