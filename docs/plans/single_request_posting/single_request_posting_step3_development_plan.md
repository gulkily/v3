> **Feature plan:** [Step 1](./single_request_posting_step1_solution_assessment.md) · [Step 2](./single_request_posting_step2_feature_description.md) · [Step 3](./single_request_posting_step3_development_plan.md) · [Step 4](./single_request_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/single_request_posting; [ledger](../effort_estimation_trial.md#cycle-v3single_request_posting), standing P2 enrollment
Extended estimates: enabled
Status: Revised Step 2 v2/B approved; Step 3 proposed
**Cycle estimate: 1.05**

## Completion Contract

Deliver one additive API for an agent to publish an ordinary signed thread/reply with an optional separately signed bootstrap and public key in one HTTP request. Fresh registration and publication use one canonical commit. Known keys/anchors are reused; signed bytes stay unchanged; exact retries do not duplicate anything. A standalone example demonstrates fresh-key posting and recovery. Existing registration, prepare/finalize, unsigned and browser flows continue unchanged. No frm.py implementation, browser migration, votes, renames, attachments, special typed posts, automatic approval or deployment.

Local completion requires the Stage 8 clean-workspace HTTP replay, canonical/derived-state checks and documentation audit. Release still needs live ZenMemes discovery/read checks after deployment; production write verification requires an authorized destination. Local fixtures do not satisfy that external release condition. No schema or canonical format change is planned; if an implementation query introduces a schema dependency, stop and add CONTRIBUTING's version/spec/startup-rebuild requirements to the plan before that work.

## Key Risks

- **High risk: partial writes or unintended commits.** Current commitCanonicalWrite stages paths but invokes a general commit; current registration writes several files before committing. The new path must isolate its write set, refuse unrelated pre-staged changes, and recover only its own files. Test rejected commits, process interruption and lost success responses before exposing the endpoint.
- **High risk: untrusted client records.** PostRecordParser permits metadata beyond ordinary authored posts. Explicit allowlists and deterministic canonical comparison are required; bootstrap tags/fields cannot authorize approvals or other protocol actions. Verify both signatures against the same identity and never normalize signed bytes in place.
- **High risk: duplicate identities/anchors.** Check known identity state again under the write lock. First accepted registration wins; later valid supplied bootstrap proposals have no storage effects. Committed-record replay precedes new-submission clock checks. Removal receipts reserve deleted IDs; no resurrection or new retraction subsystem.
- **Read-model correctness.** Identity synchronization may already load the bootstrap post. Apply combined identity/post state once or rebuild safely; check counts, approval, readback and exported/restored state. Preserve the separate bootstrap when the ordinary post is removed.

## Proposed API contract

- Endpoint: POST /api/create_signed_post. Fields: canonical_record, detached_signature; optional public_key, bootstrap_record, bootstrap_signature. The bootstrap pair is all-or-nothing. An unknown identity requires all three optional fields; a known identity may omit them. Supplying a known public key never updates stored key packets, username, approval or bootstrap. If present, bootstrap material must still have valid structure/signature/identity binding; once the identity exists its proposal is not written and its age is not a new-registration gate.
- Identity authority: Author-Identity-ID is openpgp:<lowercase primary fingerprint>. Inspect the submitted key and compare identity; for known identities verify with the stored key rather than accepting new subkeys/packets as an update. Both signatures must verify for that identity. Bootstrap is a separate signed post, not a signature over the server-derived identity metadata.
- Client IDs: thread-, reply- or bootstrap- followed by UTC YYYYMMDDHHMMSS, a hyphen and 32 lowercase random hexadecimal characters. Prefix matches record kind; embedded time matches Created-At. Created-At is a valid UTC YYYY-MM-DDTHH:MM:SSZ. New ordinary posts and newly stored bootstraps must be within 600 seconds of server UTC in either direction. Return server time with a clock error. Accepted duplicates bypass new-record age checks. These limits apply only to this endpoint, not old records or APIs.
- Ordinary thread header order: Post-ID, Created-At, Board-Tags, Author-Identity-ID, optional nonempty Subject. Replies: Post-ID, Created-At, Board-Tags, Thread-ID, Parent-ID, Author-Identity-ID. Require both reply targets, with existing root/parent relationship checks. Reject duplicate/unknown headers, identity protocol board tags, image references and special root metadata; retain current field/body bounds and instance text rules. Require the same canonical LF/trailing-newline/normalization result as existing authoring, without changing the supplied bytes. The client example constructs that form locally; it must report changes needed before signing, not silently rewrite a failed signed request.
- Dedicated bootstrap: existing header order and shape, Board-Tags: identity internal, Subject: account bootstrap, body Automatic account bootstrap anchor. followed by LF, and Author-Identity-ID matching the ordinary post. No Thread-ID, Parent-ID or extra fields; distinct bootstrap/post IDs. Derive identity/public-key paths and bootstrap pointers on the server; no client-supplied paths or username metadata.
- Success: HTTP 200, status ok, post_id, thread_id, identity_id, profile_slug, bootstrap_post_id, record_path, signature_path, commit_sha and replayed. On replay, commit_sha identifies the original accepted post commit (derive from canonical Git evidence; do not create a new receipt solely to cache it). Existing stored identity/anchor wins even when a different valid proposal was included. A valid new detached signature over the identical stored record counts as a retry; do not replace the stored signature.
- Failure: JSON status/error and stable code; 400 malformed/invalid content or signature/clock, 403 access denial, 405 wrong method, 409 ID or state conflict, 410 removed post ID, 503 busy or recoverable server failure. Busy includes Retry-After: 10 and retry_after: 10. Preserve existing response formats on unrelated endpoints. Members-only access retains its existing authenticated-approved-session requirement; a signing key is not a new login path.
- Reconciliation: after access and signature verification, distinguish a committed identical ordinary post from a conflicting or removed ID before applying current new-post policy/time checks. Match original record bytes and signer; do not require identical armor or detached-signature bytes. Pre-commit failure leaves no published bundle; post-commit refresh failure returns an actionable accepted-but-refresh-pending result identifying the committed post, never advice to generate a new ID. Restart recovery verifies pending files against canonical Git before completing or removing its own artifacts. Never overwrite or reset unrelated files/index state.

## Stage 1 — Validate locally constructed signed records

**Estimate: 0.16**

- Goal: Validate locally constructed signed records.
- Dependencies: None.
- Expected changes: Add a dedicated bundle validator using existing parsers, authoring normalizers, reply-target rules and GPG verification. Pin the proposed contract in tests and specs; keep the old prepared paths unchanged. Planned boundary: SignedPostBundleValidator::validate(array $input): array, with repository/key checks separated from byte/shape checks.
- Verification approach: Valid ordinary thread/reply and fixed bootstrap; header/tag escalation, duplicate headers, wrong signer, normalization mismatch, path attempts, ID/time boundaries and changed bytes all reject before mutation.
- Risks or open questions: Existing parsers are broader than posting authority; compare to exact allowed canonical forms. Resolve any current limits before accepting input.
- Canonical components/API contracts touched: CanonicalPostRecord/IdentityBootstrap specs, LocalWriteService authoring helpers, OpenPgpKeyInspector and signature verifier.
- Estimate details: S1/v1 stage work; Two record kinds and canonical comparison; uncertainty is extracting checks without changing old callers.

## Stage 2 — Resolve new and known identities

**Estimate: 0.12**

- Goal: Resolve new and known identities.
- Dependencies: Stage 1.
- Expected changes: Build a mutation-free acceptance bundle: reuse known identity/public-key/bootstrap or derive missing registration artifacts from the signed bootstrap. Recheck under the shared write lock. Handle valid known-key bootstrap proposals without writing them; reject inconsistent partial canonical identity state with recovery guidance.
- Verification approach: Known key with omitted/repeated public key, changed armor, no bootstrap, repeated/different valid bootstrap, renamed/approved identity, wrong key and incomplete existing state; assert no record rewrites.
- Risks or open questions: Fingerprint reuse must not become a key-update API. Verify using stored key and preserve all existing identity metadata.
- Canonical components/API contracts touched: LocalWriteService identity preparation/lookup, CanonicalPathResolver, public-key storage and identity parser.
- Estimate details: S2/v1 stage work; Identity state combinations; uncertainty is legacy lowercase public-key paths.

## Stage 3 — Commit registration and post atomically

**Estimate: 0.20**

- Goal: Commit registration and post atomically.
- Dependencies: Stages 1–2.
- Expected changes: Add a scoped bundle transaction under existing execution/publication locks. Commit only missing key/identity/bootstrap files plus ordinary post/signature and ordinary derived label records. Use a private recoverable pending manifest with readable metadata and separate exact text/signature payloads. Recovery validates paths and expected bytes; refuse pre-existing target dirt and unrelated staged changes. Planned boundary: SignedPostTransaction::accept(array $bundle): array.
- Verification approach: Inject failure before/after each file, during staging/commit and immediately after commit; restart and verify complete canonical commit or no published registration, no lost unrelated changes and no accidental extra commit.
- Risks or open questions: An interrupted process bypasses exception cleanup. Register restart recovery before subsequent writes; do not rely only on catch-and-unlink.
- Canonical components/API contracts touched: LocalWriteService write lock/commit helpers, existing recovery patterns and canonical repositories; new transaction helper rather than a global Git rewrite.
- Estimate details: S3/v1 stage work; Crash-safe write set; uncertainty is Git failure classification. Stage cannot advance without fault evidence.

## Stage 4 — Reconcile retries and refresh derived state

**Estimate: 0.18**

- Goal: Reconcile retries and refresh derived state.
- Dependencies: Stage 3.
- Expected changes: Recognize accepted ordinary posts by committed bytes/identity, resolve original commit, reject conflicts/removals, and recover post-commit derived state. Apply identity/bootstrap and ordinary post exactly once, preserving counts and approval; regenerate ordinary publication effects without another canonical commit. Planned entry: LocalWriteService::createSignedPost(array $input): array.
- Verification approach: Concurrent same/different posts for one key, concurrent retries, two bootstrap proposals, aged retries, response loss, removed ID, refresh failure and restart. Verify counts, one bootstrap and preserved names/approvals.
- Risks or open questions: Never infer acceptance from an uncommitted file or a missing read-model row. Check canonical evidence; verify removal before replay.
- Canonical components/API contracts touched: AcceptedRemoval/LabelRemoval lookup, canonical Git readback, IncrementalReadModelUpdater/ReadModelBuilder, existing invalidation and post-publication hooks.
- Estimate details: S4/v1 stage work; Idempotence and synchronization; uncertainty is incremental refresh ordering after combined commit.

## Stage 5 — Expose the single-request HTTP path

**Estimate: 0.10**

- Goal: Expose the single-request HTTP path.
- Dependencies: Stage 4.
- Expected changes: Wire create_signed_post through a focused handler, routing, access rules and catalog. Serialize stable error/status fields and endpoint-specific host busy failures. Use standard fast-score/publication behavior for the ordinary post; never enqueue hidden bootstrap content as an ordinary submission.
- Verification approach: Real HTTP new/known key success, method/format errors, all rejection classes, startup/lock failure and member-only denial; compare old API responses and confirm one inbound submission without prepare calls.
- Risks or open questions: Host errors may precede controller creation; cover the outer response path for this endpoint without broadening into paused frm.py API cleanup.
- Canonical components/API contracts touched: Application, FrontController, API controller/RouteServices, ApiRouteCatalog and existing post-processing hooks.
- Estimate details: S5/v1 stage work; Routing and error boundaries; uncertainty is pre-Application exception handling.

## Stage 6 — Provide the standalone signing example

**Estimate: 0.10**

- Goal: Provide the standalone signing example.
- Dependencies: Stage 5.
- Expected changes: Add docs/examples/agent_single_request_post.sh using existing shell, Python standard library, curl and GPG facilities. Build/sign both records locally, save exact request artifacts, submit once, and provide an explicit repeat-same-bundle recovery command. Support existing-key bootstrap omission and optional separate readback. Keep legacy worked example intact with a cross-link.
- Verification approach: Clean keyring fresh thread/reply, existing key and always-supplied key, Unicode accepted/rejected policy, lost response and exact resend; count requests and inspect both signatures.
- Risks or open questions: Re-running key generation or regenerating IDs is not a retry. Persist readable bundle artifacts before the first send and keep private key local.
- Canonical components/API contracts touched: New example plus docs/examples/agent_api_worked_example.sh and existing worked-example fixture conventions.
- Estimate details: S6/v1 stage work; Executable interoperability; uncertainty is local GPG environment. No added dependencies.

## Stage 7 — Update discovery, references and handoffs

**Estimate: 0.08**

- Goal: Update discovery, references and handoffs.
- Dependencies: Stage 6.
- Expected changes: Complete the documentation matrix below, including generated entry points. Re-run searches for compulsory prepare/separate registration and classify every relevant hit; append historical follow-up notes rather than rewriting earlier decisions.
- Verification approach: Run renderer/catalog assertions and execute examples exactly as documented. Check all changed links, limits/fields and explicit separation from browser/member-only flows.
- Risks or open questions: Do not claim the endpoint is deployed or imply frm.py/G2 browser work is complete. Record review-only exclusions.
- Canonical components/API contracts touched: LlmsTxtRenderer, API/docs catalogs, README, reference/spec/example docs, G2 and paused frm.py plan.
- Estimate details: S7/v1 stage work; Cross-document consistency; uncertainty is additional current guidance discovered by the final search.

## Stage 8 — Verify ordinary use and close the cycle

**Estimate: 0.15**

- Goal: Verify ordinary use and close the cycle.
- Dependencies: Stages 1–7.
- Expected changes: Replay complete fresh-workspace posting/recovery on an isolated ZenMemes-profile server; verify approval, deletion, rebuild/export/import and prepared/unsigned compatibility. Record exact evidence and outstanding live release checks; audit dependencies and update study/summary/index.
- Verification approach: Check one-request/one-commit first-use, two signatures/separate hidden anchor, safe repeated key/anchor, concurrency/fault cases, approval UI smoke at phone/desktop, ordinary-post removal with identity intact, ./v3 rebuild and export/import. Run focused affected suites; distinguish any broader baseline failures.
- Risks or open questions: Local success does not verify production. No deployment or live posting; retain release checks and maintainer fit pending. If UI behavior changes, apply full CONTRIBUTING verification before completion.
- Canonical components/API contracts touched: Existing API/identity/approval/read-model/export fixtures, local HTTP environment and cycle artifacts.
- Estimate details: S8/v1 stage work; Shared integration acceptance and closeout owned here; uncertainty is environment-specific host and recovery behavior.

## Documentation ownership and verification

| Exact path | Stage / required update and verification |
| --- | --- |
| docs/specs/canonical_post_record_v1.md | S1: new endpoint's ordinary/bootstrap subsets, field order, IDs, time and bytes; parser/validator fixtures. Clarify existing Unicode support in touched body rules. |
| docs/specs/identity_bootstrap_record_v1.md | S2: separate signed bootstrap, server-derived identity metadata, known-anchor reuse; identity fixtures. |
| docs/specs/public_key_storage_v1.md | S2: fingerprint reuse without implicit key updates; old path compatibility tests. |
| src/ForumRewrite/Http/ApiRouteCatalog.php | S5: route/method/access/response; catalog and HTTP tests. |
| docs/examples/agent_single_request_post.sh | S6: new executable complete bundle/retry example; actual GPG/HTTP run. |
| docs/examples/agent_api_worked_example.sh | S6: retain prepared flow, cross-link new example; existing test still passes. |
| docs/reference/agent_api.md | S7: complete one-request bundle, bootstrap reuse, limits, exact retry/error contract and prepare fallback; execute both documented flows. |
| src/ForumRewrite/Http/LlmsTxtRenderer.php | S7: discover new path and example, retain raw/old protocol guidance; settings-specific renderer tests. |
| docs/reference/authentication_modes.md | S7: signed registration/posting does not grant session/approval; browser flow unchanged; compare access tests. |
| docs/architecture/public_architecture_and_trust.md | S7: separate bootstrap persists and signatures establish authorship; compare identity/access invariants. |
| README.md | S7: entry/example links and only directly affected registration claims; link/flow checks. |
| docs/plans/agent_api_onboarding_checklist.md | S7: update protocol/retry items with verified scope and links, leave unrelated gaps open. |
| docs/plans/ui_first_post_identity/ui_first_post_identity_step1_solution_assessment.md | S7: append relationship note; preserve historical G2 proposal and approval history. |
| docs/plans/ui_first_post_identity/ui_first_post_identity_step4_implementation_summary.md | S7: append delivered API versus pending G2 browser/vote integration; no historical estimate rewrite. |
| docs/plans/agent_posting_step3_development_plan.md | S7: append API handoff/replan requirement, keep frm.py paused and unapproved Step 3 history. |
| docs/plans/single_request_posting/ (after organization), docs/plans/README.md, docs/plans/effort_estimation_trial.md | Planning organization and S8: stages/evidence/limitations, index and study checkpoints; scope/link checks. |

S7 review-only inventory: historical agent_api_docs, identity bootstrap recovery/verification and profile_registration_cost plans; search README, docs, scripts and generated-doc sources for prepare_identity, prepare_thread, prepare_reply, create_prepared_post and separate-registration/round-trip wording. Record each newly found relevant current source and its disposition before changing it; unrelated cleanup is excluded. New requirements return to planning. Documentation owners above are stage assignments, not delegated agents.

## Execution and study checkpoint

Authorization: user approved revised Step 2 v2/B. After Approved Step 3, create a feature branch; organize only this cycle's Steps 1–3/debug in docs/plans/single_request_posting/, repair links/index and make its planning-only commit. Do not commit paused frm.py artifacts or claude_request.txt as part of this cycle's planning. Each stage requires tests, summary update and a stage-scoped commit before the next. Ledger accumulated bookkeeping belongs in this cycle's closeout; preserve unrelated records. No implementation, branch or commits yet.

Dependency inventory: zero additions/upgrades/external copied source; existing PHP/Python standard library, shell/curl/GPG/Git infrastructure only. No new runtime settings or schema changes intended. Private recovery state uses readable metadata and exact payload files; no new human-facing JSON. todo.txt remains untouched. Source references inspected include the retraction handoff; do not reintroduce canceled sweep diagnostics or history-purge work.

Checkpoint: 2026-10-29T22:59:48Z; scope v2/B, baseline b3a5b1621e9df25bac6e023c26aca66537852dfe, R1/P2, Codex single exposed rater. All three frozen R1 hashes reverified. Whole-cycle 1.05 reaffirmed; previous v1/A 0.85 and original B 1.05 preserved. S1–S8/v1 stage-work originals 0.16/0.12/0.20/0.18/0.10/0.10/0.08/0.15 are independently judged, not summed or hours. S8 owns shared acceptance/closeout; cycle planning overhead is in the independent whole-cycle projection. Missing pristine intake remains missing; no runtime testing or measured labor claimed.

Sizing review: eight focused stages, near the comfortable-cycle limit. Keep the cohesive single-request outcome rather than introducing a temporary registration-only API; this was the explicit split tradeoff in revised Step 2. Stages target bounded sessions using existing infrastructure, not guaranteed clock durations. If S3/S4 requires broader transaction or schema redesign, stop and rescope before implementation of that expansion. Technical completion and maintainer fit/release acceptance remain distinct.

*— Codex · 2026-10-29 UTC*
