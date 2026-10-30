> **Feature plan:** [Step 1](./agent_posting_step1_solution_assessment.md) · [Step 2](./agent_posting_step2_feature_description.md) · [Step 3](./agent_posting_step3_development_plan.md) · [Step 4](./agent_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/agent_posting; [ledger](../effort_estimation_trial.md#cycle-v3agent_posting), standing P2 enrollment
Extended estimates: enabled
Status: Resumed by user; Step 2 approved, revised Step 3 ready for approval
**Cycle estimate: 0.95**

## Completion Contract

Deliver the approved v1.2/A posting slice with protocol integration revision v1.3/A: download frm.py → select instance → read/check → create or reuse a local identity → signed thread/reply → readback, with original-coordinate text errors, busy JSON, preserved legacy text protocols and resumable failure recovery. Keep canonical formats, text acceptance and approval policy unchanged. No read-model schema change or new dependency is planned. Request 4 and optional identity label remain deferred; the approved single_request_posting prerequisite supplies the preferred protocol without adding a new user-facing outcome.

Local completion requires Stage 7 evidence from the downloaded artifact, not only imported Python functions. Release remains conditional on deployment and live ZenMemes download/read smoke checks; live write verification requires a separately authorized destination. Neither deployment nor live posting is authorized here. Runtime/host checks apply; browser UI changes and theme work do not. Server configuration for serving the client is in scope; upstream service failures are outside application control.

## Key Risks

- **High risk:** lost responses can cause duplicate posts. The new endpoint accepts exact-bundle replay, while createPreparedPost rejects existing text targets and tokens can expire/disappear. Persist protocol, IDs and signed bytes before sending; never switch protocol or generate replacement IDs after an ambiguous write. Check and resume the saved operation.
- **High risk:** trim, CRLF conversion and NFC shift character positions. Preserve a source-coordinate map or equivalent original-text diagnostics while keeping validator order and canonical bytes; regression fixtures must cover these transformations before client integration.
- Host errors can occur outside Application and member gates can emit HTML. Audit all PHP host boundaries and controller catches, with explicit legacy text exceptions; validate actual HTTP responses before client retry work.
- Existing Python publisher is reusable project code but lacks retry/session onboarding. Keep its entry points unchanged; members-only recovery is guidance, not new authentication automation. Retraction coordination handoff read; registration/publication integration does not authorize changing retraction guards or history behavior.

## Stage 1 — Actionable text rejection

**Estimate: 0.18**

- Goal: Actionable text rejection.
- Dependencies: None.
- Expected changes: Carry original submitted-field positions through validation, using a typed text-validation error with field/chars details. Preserve existing canonicalization and messages; expose metadata through shared JSON error serialization, affected prepare handlers and create_signed_post. Signed-record errors use positions within the submitted body/subject, not the whole record; frm.py maps these to the original body-file text when canonicalization changes coordinates.
- Verification approach: Extend UnicodeTextPolicyTest, CanonicalTextBoundsTest and WriteApiSmokeTest: accepted sample; repeated symbols, emoji, spacing, format, ASCII failures, malformed UTF-8, leading whitespace, CRLF and decomposed accents before the failing character. Verify unchanged accepted canonical bytes.
- Risks or open questions: Normalization can shift positions; retain original coordinates and identify only characters actually rejected after existing normalization. Inventory callers before modifying shared helpers.
- Canonical components/API contracts touched: UnicodeTextPolicy, LocalWriteService authored/ASCII normalization, shared HTTP error serialization, JSON prepare consumers and SignedPostBundleValidator. Preserve existing signed endpoint error codes and replay behavior.
- Estimate details: Stage work, S1/v1; driver: Original-coordinate tracking; uncertainty is caller-side normalization before the shared validator.

## Stage 2 — Discover rules and recover from API failures

**Estimate: 0.18**

- Goal: Discover rules and recover from API failures.
- Dependencies: Stage 1.
- Expected changes: Add public text_rules and catalog/access wiring from shared policy facts. Generalize the existing create_signed_post host/error handling: make API lock/busy errors JSON 503 with Retry-After 10; provide safe JSON host/unknown-route/authentication errors while retaining legacy text-route contracts. Keep browser errors unchanged.
- Verification approach: HTTP tests for lock timeout, schema recovery, unexpected startup failure, unknown route, wrong method and denied member access; test /api, /api/ and query strings. Compare browser HTML and legacy text responses; cover ASCII/Unicode/emoji facts and unauthenticated discovery.
- Risks or open questions: Exceptions occur before Application exists; exercise outer host handling too. Scope guarantees PHP/application-controlled responses, not upstream proxy or missing-PHP failures.
- Canonical components/API contracts touched: FrontController including early startup boundaries, Application routing/access/404 handling, RouteServices, ApiRouteCatalog, text policy facts.
- Estimate details: Stage work, S2/v1; driver: Multiple error boundaries; uncertainty is controllers that catch lock errors as ordinary validation failures.

## Stage 3 — Download and read with frm.py

**Estimate: 0.12**

- Goal: Download and read with frm.py.
- Dependencies: Stage 2.
- Expected changes: Serve a versioned standalone project-owned script at /frm.py with UTF-8 text MIME, including GET/HEAD and member-only onboarding access. Implement --site, help/version, index/read/check, plain-text output and dependency diagnostics. Inspect GET /api/ route discovery before creating a write operation; positively identify the new endpoint or a recognizable older catalog. Unavailable/malformed discovery stops with guidance; absence of text_rules alone does not imply an older posting API. Use existing publisher transport/signing conventions where applicable without changing its CLI.
- Verification approach: Download over actual HTTP and run the downloaded file in an isolated directory; test all read commands, distinct found/missing/unknown check results, malformed responses and no key creation. Verify a second instance URL and no implicit ZenMemes target.
- Risks or open questions: Static delivery may choose the wrong MIME or bypass package copies; test normal host and direct static serving configuration. Do not follow cross-origin redirects with authored data.
- Canonical components/API contracts touched: Public asset delivery and packaging, host/application download routing, existing read APIs, scripts/publish_text.py behavior.
- Estimate details: Stage work, S3/v1; driver: Standalone distribution and read parsing; uncertainty is deployment asset routing.

## Stage 4 — First-use signed thread and reply

**Estimate: 0.20**

- Goal: First-use signed thread and reply.
- Dependencies: Stage 3.
- Expected changes: Implement local key selection/creation and persisted per-instance identity selection. Prefer create_signed_post, adapting project-owned agent_single_request_post.sh record construction: separately signed dedicated bootstrap and ordinary post in one submission, existing keys/anchors reused. Registration probing is optional for this path; a valid bootstrap proposal/public key can accompany an already known key without duplication. Retain prepare_identity/register and prepare/sign/finalize for positively identified older instances. Support --name and --signing-key, agent-labelled default and private local generated-key ring; selected existing keys stay in their current custody.
- Verification approach: Real GPG/local HTTP tests for clean first-use thread, root/child reply, repeated invocation, selected existing key, known registration with extra bootstrap proposal, signing failure and member denial. Assert one publication request on the new path, separate bootstrap, stable identity/key/anchor and valid signatures. Run the prepared fallback against a fixture advertising only the older API.
- Risks or open questions: Persist key fingerprint before network registration and complete signed bundle before publication. Preserve original body files; use the existing documented canonicalization only, report changes and retain canonical text beside the original. Never delete rejected characters or downgrade to unsigned. Explain generated-key storage and existing-key passphrase/pinentry behavior in help. Freshness rejection must offer clock correction without silently replacing an uncertain operation.
- Canonical components/API contracts touched: frm.py; create_signed_post and existing identity/prepare/finalize APIs; project-owned example/publisher/GPG behavior and canonical post/bootstrap specifications. No server identity or transaction redesign.
- Estimate details: Stage work, S4/v2: 0.20 reaffirmed; driver: two supported protocol paths using existing project code; uncertainty is GPG behavior and exact canonicalization parity. Original S4/v1 0.20 and prepared-only scope retained in revision history below.

*— Revised by Codex · 2026-10-30 UTC*

## Stage 5 — Bounded retries and resumable recovery

**Estimate: 0.18**

- Goal: Bounded retries and resumable recovery.
- Dependencies: Stage 4.
- Expected changes: Add bounded busy retries honoring server delay, readable recovery records and explicit resume using thread/reply --resume OPERATION_DIR. Save protocol/site/key/IDs and canonical text/signatures separately before first submission; resume sends the exact saved bundle on the signed endpoint. check POST_ID remains read-only publication lookup, with found/missing/unknown results; missing is not proof of rejection. The prepared fallback reconciles saved IDs before any replay and stops on unresolved registration/finalization or expired tokens. Never automatically reprepare or switch protocol after possible acceptance.
- Verification approach: Inject busy-before-write, commit-then-response-loss, unavailable status reads, malformed/proxy errors, restart and refresh_pending. Assert bounded attempts, exact bundle replay, one post/identity and a usable --resume command. Exercise missing/expired tokens and interrupted registration on the older path; assert no silent fallback after signed-endpoint validation/permission errors, 5xx, timeout or ambiguous 404.
- Risks or open questions: A stale read cannot authorize a new post. Preserve unknown state and original input; conflict/removal errors stop with guidance. Accepted refresh_pending is accepted publication, with same-bundle recovery rather than a new post. Persisted protocol selection prevents an upgrade or transient failure from changing an in-flight operation.
- Canonical components/API contracts touched: frm.py transport/recovery; create_signed_post replay/error contract; get_post/get_profile and prepared-token lifetime. No server idempotency redesign.
- Estimate details: Stage work, S5/v2: 0.18 reaffirmed; driver: explicit recovery across two protocols; uncertainty is legacy read-model lag. Original S5/v1 0.18 and prepared-only recovery scope retained below.

*— Revised by Codex · 2026-10-30 UTC*

## Stage 6 — Quickstart and complete error guidance

**Estimate: 0.08**

- Goal: Quickstart and complete error guidance.
- Dependencies: Stages 1–5.
- Expected changes: Put frm.py download/thread/reply quickstarts first in llms.txt and reference, with explicit --site examples. Describe real text categories, normalization/index semantics, error format exceptions and recovery. Update existing onboarding limitations to match delivered behavior.
- Verification approach: LlmsTxtRendererTest and ApiRouteCatalogTest; run documented commands against the fixture. Check ASCII/Unicode/emoji settings and ensure examples never imply silent text replacement or prepared-flow idempotency. Distinguish exact-bundle replay on the new API from older-instance recovery.
- Risks or open questions: Prose can drift from policy; share policy facts and verify settings-specific output. Do not invent an instance URL from untrusted request headers.
- Canonical components/API contracts touched: LlmsTxtRenderer, agent_api.md, agent_api_onboarding_checklist.md, client help; preserve raw API docs and old publisher instructions.
- Estimate details: Stage work, S6/v1; driver: Documentation consistency; uncertainty is instance-specific link generation.

## Stage 7 — Clean-workspace acceptance and closeout

**Estimate: 0.15**

- Goal: Clean-workspace acceptance and closeout.
- Dependencies: Stages 1–6.
- Expected changes: Replay Claude’s complete posting slice from HTTP download in a clean workspace with only Python 3/curl/GPG; consolidate verification, operational limitations and dependency audit in the summary and ledger. Keep grouped approval/session label follow-ups visible.
- Verification approach: Run focused PHP tests above plus AgentApiWorkedExampleTest and existing publisher transport tests. Exercise all five commands and --resume, both protocol paths, accepted sample, character correction, lock busy, lost response and resumed recovery; verify zero external additions. Record actual platform and local fixture configuration.
- Risks or open questions: Local tests do not establish deployed ZenMemes behavior; record live download/read smoke as pending after deployment and require an authorized destination for live writes. No production posting/deployment in this cycle.
- Canonical components/API contracts touched: Downloaded frm.py ordinary flow; HTTP host/application boundaries; existing worked-example/publisher compatibility tests and cycle documentation.
- Estimate details: Stage work, S7/v1; driver: Shared end-to-end acceptance and closeout owned here; uncertainty is environment-specific host/GPG behavior.

## Execution and study checkpoint

Historical authorization at original Step 3 handoff: user approved Step 2 after selecting frm.py; scope v1.2/A. No implementation until Approved Step 3. Then create a feature branch, organize plans/debug under docs/plans/agent_posting/, preserve the original request in the approved planning inventory, update the plan index/references and make the planning-only commit. Each stage requires verification, summary update and its own commit before the next. Planning documents remain uncommitted now; ledger bookkeeping is included in the approved documentation scope and its accumulated update belongs in closeout.

Dependency inventory: no additions, upgrades or external copied source; Python standard library, curl/GPG and existing project code only. No supplied zm.py is available or imported. Final audit must report actual zero delta. todo.txt stays untouched.

Checkpoint: 2026-10-29T19:51:48Z, Step 3 handoff, v3/agent_posting, v1.2/A, baseline b3a5b1621e9df25bac6e023c26aca66537852dfe, R1/P2, Codex single exposed rater. All three frozen R1 hashes reverified. Whole-cycle 0.95 revises preserved 0.85 due to original-coordinate normalization and uncertain-write recovery depth discovered during inspection, not changed scope. S1–S7 originals 0.18/0.18/0.12/0.20/0.18/0.08/0.15 are separate stage-work judgments, not summed. Shared acceptance/closeout belongs to S7; planning overhead is only in the independently judged cycle projection. Pristine intake remains missing; no measured labor, runtime test result or accepted outcome claimed.

Sizing: seven focused work units target about an hour each using existing infrastructure; line count is not an estimate. If implementation reveals a stage cannot remain bounded, split/replan before dependent work rather than expanding this cycle silently.

*— Codex · 2026-10-29 UTC*

Pause: 2026-10-29T19:57:39Z — user requested pausing this cycle to plan one-request signed posting including identity. See [new assessment](../single_request_posting/single_request_posting_step1_solution_assessment.md). No implementation began. Resume only on user instruction and reconcile this plan with the selected protocol before seeking implementation approval. Whole-cycle 0.95 retained; no outcome claimed.

*— Codex · 2026-10-29 UTC*

API handoff — 2026-10-30: [single-request implementation](../single_request_posting/single_request_posting_step4_implementation_summary.md) retains a dedicated signed bootstrap. On explicit resumption, revise frm.py to prefer create_signed_post and preserve old-instance prepared-flow fallback. This cycle remains paused and its Step 3 remains unapproved.


## Resumption and revised Step 3 checkpoint

Timestamp: 2026-10-30T11:33:22Z
Authorization: User said “you can now proceed with the agent_posting plan series.” This resumes the paused series; this revised Step 3 is the concrete next artifact. The prior Approved Step 3 applied to single_request_posting. Await agent_posting Approved Step 3 before implementation, under the existing staged workflow.
Scope: v1.3/A integrates the separately approved and locally completed protocol prerequisite; original v1.2/A outcome, seven stage IDs and exclusions remain. Original starting revision b3a5b1621e9df25bac6e023c26aca66537852dfe retained; current prerequisite head 28665bdcd. Implementation will branch feature/agent-posting from this head, preserving predecessor commits and keeping release acceptance separate.
Protocol: Discovery/read requests are permitted; “one request” means a single publication submission including identity material. Discovery must finish before a write is selected; no fallback based on a failed submission. Bootstrap remains separate from the ordinary post. Existing server full-rebuild cost is inherited, not optimized in this cycle.
Revision history: S4/v1 was prepared-only local key selection, registration, thread/reply preparation, exact signing and publication, tested with new/existing keys and denied members. S5/v1 used bounded busy retry, readable recovery, prepared identity/post IDs and status reconciliation before replay, tested for response loss, expired tokens, interrupted registration and unavailable reads. Their original estimates 0.20/0.18 are preserved; revised v2 scopes above add the now-available signed-bundle path and retain that legacy behavior. S1/v2 adds signed-record diagnostics; S2/v2 reuses existing endpoint error work; S3/v2 adds discovery; S6/v2 and S7/v2 cover both protocols. Their original estimates remain 0.18/0.18/0.12/0.08/0.15 respectively.
Documentation inventory: Update public frm.py help/version; docs/reference/agent_api.md; LlmsTxtRenderer; docs/plans/agent_api_onboarding_checklist.md; README agent entry; route catalog and generated discovery. Review docs/specs/canonical_post_record_v1.md and identity_bootstrap_record_v1.md for client links only, preserving protocol; cross-link both existing examples and preserve existing publisher instructions. Record completion in this cycle summary, plan index and study ledger. No todo.txt changes. Stage 6 owns documentation consistency; Stage 7 owns shared acceptance/closeout.
Study: v3/agent_posting, R1/P2, Codex single exposed rater. Three frozen R1 hashes reverified. Whole-cycle 0.95 reaffirmed: new-protocol construction/recovery work is offset by reusable example and endpoint guarantees, while legacy support remains necessary. This estimates the whole agent_posting cycle from its original start, excluding independently recorded prerequisite implementation; it is not remaining effort or the sum of stage estimates. Preserve 0.85/0.95 history, missing pristine intake and unknown measured labor. No runtime tests or implementation in this resumption; no acceptance/count increment.
Verification: Current endpoint, strict validator, example, route catalog and shared Unicode/host boundaries inspected. Planning links, stage metadata and whitespace checked. Runtime and deployed acceptance remain future Stage 7 evidence; the prerequisite's tests do not establish frm.py completion.

*— Codex · 2026-10-30 UTC*

Approval: 2026-10-30 — User approved Step 3 v1.3/A; implementation authorized.
