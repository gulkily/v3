> **Feature plan:** [Step 1](./agent_response_pending_notice_visibility_step1_solution_assessment.md) · [Step 2](./agent_response_pending_notice_visibility_step2_feature_description.md) · [Step 3](./agent_response_pending_notice_visibility_step3_development_plan.md) · [Step 4](./agent_response_pending_notice_visibility_step4_implementation_summary.md)

# Agent Response Pending Notice Visibility Step 3 Development Plan

## Completion Contract

- **Normal entry:** a reader opens a thread where a root or reply post with an unfinished agent-response request begins a same-author continuation run.
- **End-to-end outcome:** the originating post shows its existing requested/in-progress notice without opening the collapsed footer; published, skipped, and failed outcomes retain their existing final presentation.
- **Required recovery:** a rejected, duplicate, unavailable, skipped, or failed request leaves no stale persistent notice and preserves its current feedback.
- **Deployment/external verification:** deploy the matching rendered templates and fingerprinted interaction/style assets; manually check pointer and touch continuation layouts after deployment.
- **Release condition:** focused card/lifecycle coverage and the full test suite show no new failures, and manual checks pass for root and reply cards both after reload and after an in-page request.

## Key Risks

- **High risk: usability regression.** A persistent notice could expose the entire compact footer or leave terminal feedback permanently visible. **Early validation:** inspect each lifecycle outcome in continuation runs. **Mitigation:** give only the unfinished agent-response notice an explicit presentation state and preserve the existing footer boundary for all other content.
- **High risk: dynamic-state mismatch.** The browser may update text after a request without updating its visibility. **Early validation:** make an in-page request from a collapsed card. **Mitigation:** use the existing shared feedback controller to apply and clear the same state used by server-rendered cards.
- **High risk: root/reply divergence.** The two canonical card partials could behave differently. **Early validation:** render and inspect both surfaces in the same fixture. **Mitigation:** apply one status-visibility contract to both and cover each in focused tests.

## Stage 1

- Goal: Make an unfinished agent-response notice visible on server-rendered root and reply cards without expanding their continuation footers.
- Dependencies: approved Step 2; no schema, API, or lifecycle changes.
- Expected changes: extend the canonical root and reply card status markup with an explicit unfinished-status presentation hook; extend continuation styling so only that notice remains visible outside the collapsed footer; retain existing terminal status text, links, and compact action behavior.
- Verification approach: add or extend rendered-thread coverage for root and reply continuation cards in requested/in-progress and terminal states; manually inspect a reload of each surface on pointer and touch layouts.
- Risks or open questions:
  - Impact: a selector could affect non-agent feedback or ordinary cards.
  - Early warning / validation: assert the action controls remain inside the collapsed footer while only the unfinished notice is outside it.
  - Mitigation: scope markup and styling to the existing agent-response feedback role plus the explicit unfinished-state hook.
- Canonical components/API contracts touched: `templates/partials/thread_root_card.php`, `templates/partials/post_card.php`, continuation rules in `public/assets/content-interactions.css`, and their rendered HTML contract; no request or persistence contract change.

## Stage 2

- Goal: Keep the visibility treatment synchronized when a reader requests an agent response without reloading the page.
- Dependencies: Stage 1’s markup/state hook and status-state definition.
- Expected changes: extend the canonical post-interaction feedback path so request initiation and returned requested/in-progress/final results apply or clear the unfinished-status presentation state; add focused regression coverage for server-rendered lifecycle output and preserve the existing request-result wording.
- Verification approach: request a response from a collapsed root card and reply card in a browser, verify immediate visibility then reload; run JavaScript/PHP syntax checks, focused `LocalAppSmokeTest` and `WriteApiSmokeTest` coverage, then `php tests/run.php` and record only pre-existing failures if any.
- Risks or open questions:
  - Impact: a failed browser request could leave the notice marked unfinished.
  - Early warning / validation: force the existing request failure path after a collapsed-card request.
  - Mitigation: clear the presentation state through the same final/failure feedback handling that restores the request control.
- Canonical components/API contracts touched: `public/assets/post_analysis.js`, the existing `data-role="agent-reply-feedback"` feedback contract, `tests/LocalAppSmokeTest.php`, and `tests/WriteApiSmokeTest.php`; no new endpoint or data contract.
