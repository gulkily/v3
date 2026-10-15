> **Feature plan:** [Step 1](./agent_reply_length_step1_solution_assessment.md) · [Step 2](./agent_reply_length_step2_feature_description.md) · [Step 3](./agent_reply_length_step3_development_plan.md) · [Step 4](./agent_reply_length_step4_implementation_summary.md)

Debug mode: disabled

# Scripture Response Guidance and Expansion Step 3 Development Plan

## Completion Contract

- Members read scripture analysis and request one shared expansion beneath it. Support thread/post pages, reloads, keyboard/mobile, and stored cached/offline display; generation requires connectivity.
- Preserve initial answers; show progress, failure, and explicit recovery. Reject unauthorized/stale/hidden/duplicate publication. Keep technical limits separate from editorial guidance.
- Release requires regression/browser checks and live output review. Isolate repositories, queues, scoring/exchange databases; cap review at eight calls within existing allowances. No production publication/deployment.

## Key Risks

- **High risk:** Target the initial response; narrowly exempt verified scripture expansion from agent-loop prevention and prevent recursion.
- **High risk:** Persist canonical provenance; reconcile publication before retry. Validate rebuild/crash recovery before dependent UI work to prevent duplicate/lost expansion.
- Guidance can produce formulaic answers. Review simple/complex concerns, supplied quotations, and uncertain references for accuracy/completeness/added depth, without length scores.
- Hidden or edited sources can invalidate expansion. Version both source post and initial response; recheck access before generation, publication, and display.

## Stage 1

- Goal: Improve initial scripture responses (about one hour).
- Dependencies: Step 3 approval, feature branch, organized plans, planning commit.
- Expected changes: Revise scripture guidance for actual quotations, directness, nuance, and completion without quotas/templates.
- Verification approach: Review prompt semantics and generator routing; preserve technical allowance/exhaustion checks.
- Risks or open questions: Avoid pressure toward uniformly short answers; retain necessary quotation/context.
- Canonical components/API contracts touched: Scripture prompt, shared generator.

## Stage 2

- Goal: Preserve identifiable expansion publication (about one hour).
- Dependencies: Stage 1 committed.
- Expected changes: Persist optional canonical kind/parent/source versions; expansion is an initial-reply child. Extend publication lookup; recognize older scripture through trusted request provenance, never text prefix alone.
- Verification approach: Test round-trip/rebuild/uniqueness/old records; update schema version/spec and startup regression per CONTRIBUTING. Verify `./v3 rebuild` and normal access.
- Risks or open questions: Reconcile canonical publication before replay after interrupted posting; validate metadata before dependent work.
- Canonical components/API contracts touched: Writer, parser, read-model builders/metadata/spec, publication identity.

## Stage 3

- Goal: Fulfill one recoverable expansion (about one hour).
- Dependencies: Stage 2 committed and persistence/recovery checks pass.
- Expected changes: Add `requestExpansion(string $replyPostId, array $viewerProfile): array`, status, task, prompt, fulfillment, and targeted recovery. Supply post/initial answer; enforce mode, permissions, and versions.
- Verification approach: Test concurrency, changed/unavailable sources, provider/exhaustion failures, retry, interrupted enqueue/publication, and recursion rejection.
- Risks or open questions: Permit the narrow agent-target exception only for verified scripture responses; leave original reply limits intact.
- Canonical components/API contracts touched: Request/status API, task catalog, stores, worker, generator, recovery CLI.

## Stage 4

- Goal: Offer accessible expansion in discussion (about one hour).
- Dependencies: Stage 3 committed.
- Expected changes: Add shared Expand/progress/failure controls and supplementary content; collapse by default, open for requester, preserve quotations.
- Verification approach: Thread/post, root/reply cards, keyboard, mobile, refresh, shared completed result, permissions, and safe error feedback.
- Risks or open questions: Render supplementary content once, without also duplicating it as an ordinary child card.
- Canonical components/API contracts touched: Shared cards/partials, page controllers, feedback script, CSS.

## Stage 5

- Goal: Preserve expansion across delivery surfaces (about one hour).
- Dependencies: Stage 4 committed.
- Expected changes: Carry provenance/display through caches/offline readers; invalidate affected artifacts and suppress unavailable-source expansions.
- Verification approach: Rebuild/refresh, cache invalidation, offline rendering without request controls, hidden/retracted/edited-source cases.
- Risks or open questions: Cached content must respect existing visibility/publication policy; verify before release.
- Canonical components/API contracts touched: Artifact builder, snapshot/export/readers, shared rendering, visibility filters.

## Stage 6

- Goal: Validate the complete experience (about one hour).
- Dependencies: Stages 1–5 committed.
- Expected changes: Finish guidance/summary; perform isolated live review and normal-flow expansion/recovery checks.
- Verification approach: Focused suites, browser desktop/mobile/keyboard, canonical rebuild, varied initial/expanded output pairs and passage accuracy.
- Risks or open questions: Failed/unavailable live checks block release readiness; retain evidence and recoverable work.
- Canonical components/API contracts touched: Existing reader/operator entry points, diagnostics, documentation.
