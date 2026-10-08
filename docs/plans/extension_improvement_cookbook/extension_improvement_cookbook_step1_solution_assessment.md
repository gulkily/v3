# Extension/Improvement Cookbook Step 1 Solution Assessment

## Problem Statement

Developers cannot readily see how the site’s existing architecture supports safe, practical feature extensions, especially LLM- and agent-assisted workflows.

## Option A: Architecture tour

Pros:
- Explains the system’s extension points in one place.
- Low documentation-maintenance cost.

Cons:
- Leaves developers to translate abstractions into a working feature.
- Does not demonstrate the lifecycle or safety boundaries of agentic work.

## Option B: Four standalone example features

Pros:
- Makes the requested moderation, synthesis, feature-request, and bug-report outcomes concrete.
- Lets readers adopt one example without reading a broad guide.

Cons:
- Repeats common workflow and safety guidance.
- Risks presenting divergent patterns rather than a coherent extension model.

## Option C: Cookbook with a shared extension pattern and progressive recipes

Pros:
- Shows one reusable lifecycle: trigger, eligibility, durable work, bounded agent task, guarded result, and audit/disable path.
- Uses progressive recipes: agent reply extensions first; developer-workflow and cross-post synthesis extensions later.
- Makes existing facilities discoverable while keeping each example outcome-focused.

Cons:
- Requires careful scope to remain a cookbook rather than a second architecture reference.
- Aggregate/synthesis guidance needs especially clear provenance and human-review expectations.

## Recommendation

Recommend Option C.

Brief justification:
- Pair a short map of the existing extension facilities with four concise recipes: NVC reply for high moderation signal, high-signal synthesis, feature-proposal handoff, and structured bug-report follow-up.
- Treat classification as advisory; make canonical writes reviewed or explicitly guarded, and require provenance, idempotency, bounded cost/work, auditability, and a disable path in every recipe.

## Candidate Cookbook Recipes

- NVC rewrite/reply for posts with a high moderation signal.
- High-signal post aggregation and synthesis into a reviewed post.
- Feature-proposal extraction into a feature-request/Codex handoff.
- Short bug-report expansion into reproduction steps, expected behavior, and actual behavior.
- **Favorite — Duplicate/related-post concierge:** draft links to the closest earlier discussion and summarize the difference.
- Claim/evidence extractor: identify factual claims and draft a neutral verification checklist.
- **Favorite — Decision log builder:** draft the decision, rationale, dissent, owner, and follow-ups from a converged thread.
- **Favorite — FAQ candidate generator:** identify recurring questions and draft an FAQ entry with source links.
- Accessibility/plain-language companion: offer an author-approved clearer, shorter version of dense text.
- Thread title/tag suggester: propose a concise title and normalized tags for author approval.
- Onboarding responder: draft a welcoming first-response with relevant guides and discussions.
- Stale issue follow-up: draft a status-check on unresolved bug or feature threads from visible activity.
- Contradiction finder: draft a neutral note when new content may conflict with earlier decisions or documentation.
- Release-note synthesizer: turn completed development discussions into a reviewed release-note draft.
