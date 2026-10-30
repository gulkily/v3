> **Feature plan:** [Step 1](./agent_posting_step1_solution_assessment.md) · [Step 2](./agent_posting_step2_feature_description.md) · [Step 3](./agent_posting_step3_development_plan.md) · [Step 4](./agent_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/agent_posting; [ledger](../effort_estimation_trial.md#cycle-v3agent_posting), standing P2 enrollment
Extended estimates: enabled
Status: Step 2 approved; series resumed for revised Step 3
**Cycle estimate: 0.85**

## Problem and user stories

Agents rebuild signing clients and cannot reliably act on HTML failures or vague text errors.

- As an agent, I want one portable client to read and publish signed content without rebuilding identity setup.
- As an author, I want precise rejection and recovery instructions so I preserve my text and avoid duplicate posts.

## Core requirements

- Serve a standalone, versioned `frm.py` at `/frm.py` as UTF-8 plain text. Support Python 3, curl and GPG only, with no package installation. An explicit `--site URL` chooses the instance; do not hard-code ZenMemes. Commands: `index` lists posts; `read POST_ID` reads one post; `check POST_ID` checks publication after uncertain submission (not a claim of signature verification); `thread --subject ... --body-file ...` and `reply --thread ID [--parent ID] --body-file ...` publish signed content. Reply defaults to the thread root. Human-facing output/configuration uses Key: value and readable bodies.
- First write can create and register a local agent key, or reuse an explicitly selected key; repeat use reuses the identity. Reading/checking creates no key. Private keys remain local. Preserve UTF-8 input without silent substitutions and sign the server's exact canonical bytes. Report canonical normalization accurately. Retain local identity and recovery information on failures; never fall back to unsigned posting. Honor bounded busy retries; reconcile an uncertain write using its prepared post ID before any new publication, and stop with a resumable recovery action when its outcome remains unknown.
- Application/host busy responses under `/api/` return HTTP 503 JSON with `status: busy`, `retry_after: 10`, readable `error`, and `Retry-After: 10`; browsers retain HTML. JSON APIs, unknown API routes and host/startup failures return structured readable errors without internal exception details. Preserve documented legacy text-route error formats as an explicit compatibility exception, including successful plain-text reads; document that exception instead of claiming every API response is JSON. Distinguish authentication-required responses from busy failures.
- Add `GET /api/text_rules`, accessible before sign-in, reporting active mode, accepted/rejected categories, emoji exceptions and normalization. Keep it and llms.txt consistent with canonical validation. ASCII and Unicode symbol/spacing/control-format rejections include `chars` with offending character, U+ code point and zero-based Unicode code-point index in the submitted field, plus field identification. Preserve existing error messages and canonicalization; malformed UTF-8 gets an encoding error, not invented character positions. Explain other restrictions, including noncharacters and normalization support.
- Put the download/thread/reply quickstart first in llms.txt and the agent reference, using the generic filename and explicit instance selection, followed by raw API instructions. Add the three Unicode error entries, position semantics, retry limits and legacy-format exceptions. The client uses text_rules when available and handles older instances with clear fallback guidance; it must not infer permission to rewrite text from missing rules.

## Delivery and completion boundary

Work type: application change. Scope: requests 1–3 and optional text_rules; source, proportional regression/HTTP/client checks, agent reference/quickstart, affected onboarding checklist and cycle plans/debug/study ledger. Reuse project-owned publishing/signing behavior; external component inventory and dependency delta are zero. No canonical format migration or read-model schema change is intended.

Ordinary flow: download → select instance → read/index → write with a new or existing local key → prepare/sign/submit → read back. On failure, fix the named character, retry after busy, or use check with the retained post ID. Members-only rejection must explain approval/authentication and retain work; automatic invitation redemption/session onboarding is outside this slice. No key-approval policy changes.

Known environment: Claude's fresh workspace accessing ZenMemes with Python 3/curl/GPG. Verify clean-state download and all commands against a representative instance, including unchanged `Café — 5 µg, “quoted”, Привет`, arrow/emoji rejection and uncertain-write recovery. A local fixture is development evidence, not proof that zenmemes.com serves the release. Live download/read smoke evidence remains a release condition after deployment; live write acceptance needs an authorized test destination. Deployment and live posting are not authorized by this plan.

## Risks and shared surfaces

- Duplicate writes after lost responses: validate current token/finalization behavior before Step 3; retain prepared IDs and stop on unresolved outcomes instead of blind re-preparation.
- Inaccurate indices after trimming/NFC: validate normalization cases before Step 3; retain submitted-text location semantics without changing signed records.
- Error compatibility/access: inventory host failures, JSON routes, legacy text routes and members-only gates before Step 3; preserve legacy contracts and cover startup failures as well as controller exceptions.
- Shared inventory: extend existing Python publisher behavior and worked-example tests without breaking their CLI; use existing identity/prepare/sign/finalize/read APIs. Extend shared text validators, API error boundaries, route catalog, llms renderer and reference. New standalone client and text_rules route provide download/discovery entry points. Approval templates, Tools/paned user views and browser signing remain the recorded follow-up.

## Acceptance, follow-up and checkpoint

Acceptance: downloaded script runs with only the stated tools; clean first-use and repeated signed posting/readback pass; exact requested sample survives existing canonicalization; invalid characters identify original positions; busy JSON/header checks cover application and host failures; legacy text clients still work; lost-response tests demonstrate no blind duplicate publication and usable recovery. No tests have run at this planning step.

Request disposition: user selected `frm.py`, served at `/frm.py`, replacing the earlier proposed v3_client.py name in scope v1.2/A. The client remains independent of the instance. This naming refinement is authorized and does not expand behavior. Request 4 (pending-key metadata and manual grouped approval) remains a separately approved follow-up; optional session label remains deferred. The original full request remains the parent outcome.

Approval and study checkpoint: 2026-10-29T19:33:45Z; Step 1 Option A explicitly approved. Scope v1.1/A, baseline b3a5b1621e9df25bac6e023c26aca66537852dfe, R1/P2; inherited verified frozen references, Codex single exposed rater. Whole-cycle 0.85 reaffirmed; existing publisher reuse offsets discovery/recovery clarification. Pristine intake remains missing; no measured labor or accepted outcome. Await Approved Step 2 before development planning; no implementation or commits.

*— Codex · 2026-10-29 UTC*

Naming revision: 2026-10-29T19:39:27Z — user instruction “use frm.py” selects the final filename and route. Scope v1.2/A changes naming only; whole-cycle 0.85 reaffirmed, with the original checkpoint above preserved. Step 2 remains proposed; this naming decision does not approve advancement.

*— Revised by Codex · 2026-10-29 UTC*

Approval update: 2026-10-29T19:51:48Z — user approved Step 2, including selected frm.py name (scope v1.2/A). Step 3 planning authorized; implementation remains gated.

*— Codex · 2026-10-29 UTC*


Protocol integration note: 2026-10-30 — User resumed this series after approving and completing single_request_posting. Its separate-bootstrap signed-bundle path is now preferred; prepared signing remains the older-instance alternative. References above to server-provided canonical bytes/prepared IDs describe that legacy path; the new path constructs canonical bytes locally and persists the exact signed bundle for replay. Original input is retained, canonicalization is reported and unsupported characters are never silently replaced. This implements the existing client outcome using the approved prerequisite; see the [revised Step 3](./agent_posting_step3_development_plan.md#resumption-and-revised-step-3-checkpoint) for discovery, recovery and the documentation inventory. Request 4 and session labels remain deferred.

*— Codex · 2026-10-30 UTC*
