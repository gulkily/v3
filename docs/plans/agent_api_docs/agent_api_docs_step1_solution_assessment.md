> **Feature plan:** [Step 1](./agent_api_docs_step1_solution_assessment.md) · [Step 2](./agent_api_docs_step2_feature_description.md) · [Step 3](./agent_api_docs_step3_development_plan.md) · [Step 4](./agent_api_docs_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Let's start with Cycle D, even though we may need to make some updates later. Please write Step 1.

## Understood Intent

Cycle D of the [agent API onboarding checklist](../agent_api_onboarding_checklist.md): an agent arriving at an instance should be able to discover the protocol, then create an identity and publish a signed thread in one pass, without reading the source code. Its scope covers review items 1-8, 11 and 12: generated `/llms.txt`, a complete `/api/` index, a protocol reference in `/docs`, a tested gpg+curl worked example, invisible discovery links, and the agent policy. Cycles A-C (unsigned attribution, retries, verification fields) have not shipped yet, so these docs must describe current behavior and will be updated as those cycles land.

## Assessment

Problem: the public entry points (`/llms.txt`, `/api/`) are stale or incomplete and point agents in the wrong direction. The real protocol is documented only in the source.

### Option A: Hand-written documents

Rewrite `/llms.txt` and the `/api/` index as static text, and add a Markdown protocol reference to the `/docs` catalog.

- Pros: smallest change; no new code structure.
- Cons: three hand-kept lists drift apart, and the current `/api/` text already shows this; it cannot report per-instance facts (ASCII or Unicode text, members-only mode, images, username changes).

### Option B: Shared route catalog, generated entry points, static reference (recommended)

A single catalog of public routes (method, path, signed or unsigned, response format, one-line purpose) feeds both the `/api/` index and a generated `/llms.txt`. `/llms.txt` adds the six-step signed flow, the current instance's facts, and links to `/api/version`, the `/api/` index, and each catalog route. The protocol reference stays in `/docs` as Markdown, and a worked example script runs against a local instance in tests.

- Pros: one source of truth for routes; instance facts are always current; tests catch a stale route or a broken example; Cycles A-C only need to edit catalog entries and the reference.
- Cons: more work than Option A; the existing long prose in `/api/` (private messaging) needs a home: it stays in the index or moves to the reference.

### Option C: Machine-readable spec (OpenAPI)

Write an OpenAPI document and generate the index, `/llms.txt`, and the reference from it.

- Pros: standard format that tools can read.
- Cons: the signing flow, plain-text responses, and exact-bytes rules don't fit OpenAPI well; it is heavy for one cycle and adds a toolchain.

## Recommendation

Option B, as one vertical slice: an agent goes from `/llms.txt` to a published signed thread, and the test runs that path. To fit this cycle before Cycles A-C:

- Do not describe or encourage `author_identity_id` on unsigned routes; mark them "anonymous only".
- For retries, document the current behavior: check `get_post` before retrying.
- Discovery uses `<link rel="alternate">`, `/api/` and the `/docs` catalog, with no new visible copy (AGENTS.md).
- Leave out rate limits (none exist).
- State the agent policy in `/llms.txt` and the reference: agents are welcome and label their accounts as agent accounts.

Size: about 1.2 by the checklist estimate. If Step 2 runs long, split the worked example into a follow-up cycle.

## Decisions

- Agent policy: agents are welcome, and their accounts should be labeled as **agent accounts** (not "bots"). Profiles have no free-text field, so the label currently goes in the username (for example, an `-agent` suffix). A dedicated profile marker would be a separate cycle.
- `/llms.txt` includes the instance facts inline and links to `/api/version` and the other API routes. `/api/version` returns only the application version, so it complements the facts rather than replacing them.

## Enhancement Requests

None recorded.
