# Extension/Improvement Cookbook Step 3 Development Plan

## Stage 1
- Goal: Establish the cookbook as a discoverable developer-documentation entry point.
- Dependencies: Approved Steps 1–2; existing README documentation index.
- Expected changes: Add the cookbook under `docs/examples/` and link it from the README Examples section.
- Verification approach: Check the rendered Markdown and each new or changed relative link.
- Risks or open questions:
  - Keep the entry point useful without duplicating the reference and runbook material.
- Canonical components/API contracts touched: `README.md`; `docs/examples/` documentation convention.

## Stage 2
- Goal: Document the shared, safe extension lifecycle and map it to existing facilities.
- Dependencies: Stage 1; agent-reply, Fastmod, task-queue, Codex-handoff, and LLM audit documentation.
- Expected changes: Add a concise lifecycle, selection guide, and links to the canonical per-post, queued, developer-workflow, audit, and disable controls.
- Verification approach: Confirm each mapped facility points to its canonical current documentation and the guide distinguishes draft/reviewed from guarded publishing.
- Risks or open questions:
  - Avoid turning the cookbook into a duplicate architecture reference.
- Canonical components/API contracts touched: agent-reply analyze/publish contract; Fastmod reference; `v3` CLI reference; production deployment runbook; Codex-handoff workflow.

## Stage 3
- Goal: Add the two focused per-post agent-work recipes.
- Dependencies: Stage 2; post-analysis and Fastmod guidance.
- Expected changes: Document moderation-signal NVC reply and structured bug-report follow-up examples, including advisory classification, expected output, review boundary, and operator controls.
- Verification approach: Review each recipe against the shared lifecycle and confirm it links only to existing relevant capabilities.
- Risks or open questions:
  - NVC guidance must not imply an automated moderation decision or overwrite author content.
- Canonical components/API contracts touched: `POST /api/analyze_post`; agent-reply contract; Fastmod reference; private LLM-exchange audit surface.

## Stage 4
- Goal: Add cross-post and developer-workflow recipes plus the next-recipe inventory.
- Dependencies: Stage 2; related-content, task-queue, and Codex-handoff guidance.
- Expected changes: Document high-signal synthesis and feature-proposal handoff; list the candidate recipes and mark duplicate concierge, decision log, and FAQ generation as recommended next examples.
- Verification approach: Confirm synthesis requires source provenance and review, handoff preserves author intent and duplicate awareness, and favorites are visibly marked.
- Risks or open questions:
  - Aggregation can overstate consensus without clear source attribution.
  - Feature extraction must not silently create development commitments.
- Canonical components/API contracts touched: task queue; related-content guidance; Codex-handoff workflow; canonical post-writing conventions.
