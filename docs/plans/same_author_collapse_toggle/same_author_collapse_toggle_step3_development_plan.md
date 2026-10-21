> **Feature plan:** [Step 2](./same_author_collapse_toggle_step2_feature_description.md) · [Step 3](./same_author_collapse_toggle_step3_development_plan.md) · Step 4 pending

Debug mode: enabled

# Same-author collapse toggle — development plan

## Completion Contract
- Entry/outcome: an authorized operator uses Tools → Feature Flags to persist “Collapse posts from same author”; opening/reloading a thread reflects that state. Default and reset enable grouping; disabling restores individual metadata/actions and uncollapsed reply counts.
- Recovery: retain existing save-failure feedback and retry, authorization, environment-lock, and signed attribution behavior; verify saved state after reload and restoration through reset.
- Release: focused flag/rendering regressions and rendered desktop/phone checks pass; record evidence and limitations. Use an isolated local fixture, without changing a deployed instance. Deployment and production verification require a separate request.
- Preparation: after approval, create a feature branch, organize this cycle under `docs/plans/same_author_collapse_toggle/`, update the plan index, and commit approved plans before implementation. Commit each stage with its summary before proceeding.

## Key Risks
- Metadata and reply counts depend on continuation flags. Existing `LocalAppSmokeTest` coverage identifies the dependency; gate the continuation decision itself so the existing root/run-tail logic derives the separate-post state consistently.
- Feature flags have authorization, signing, and configuration precedence rules. Reuse the registered site-mutable flag path and evaluator; validate reset, locked overrides, and rejected writes through existing tests and operator UI.
- Theme rules style continuation classes. Verify both states with loaded content and long values on desktop/phone; fix only regressions caused by this toggle and retain unresolved evidence explicitly.

## Stage 1 — Deliver the toggle and rendering behavior
- Goal: make the operator control work end to end with default-enabled compatibility.
- Dependencies: Step 3 approval and preparation above.
- Expected changes: register `FORUM_COLLAPSE_SAME_AUTHOR_POSTS_ENABLED` as site-mutable; pass its evaluated state from `ThreadAndPostPageController` via `RouteServices::featureFlags()` to `thread.php`; gate continuation classification. Add focused cases to existing flag and thread tests. No schema, public API signature, or navigation changes.
- Verification approach: run relevant registry/evaluator/flag behavior tests and thread continuation regressions; assert enabled/default, disabled, restored, root and later runs, time/author boundaries, agent exclusion, metadata/actions, and reply counts. Inspect alternate views and fragments to confirm they do not independently group posts.
- Risks or open questions: disabled grouping must clear deferred root metadata as well as reply continuation styling; inspect generated cards and counts in both states before committing.
- Canonical components/API contracts touched: feature registry/evaluator, existing flag write/reset contracts, thread controller/template, shared root/post cards as rendered.

## Stage 2 — Verify operator recovery and rendered presentation
- Goal: establish normal operator and reader outcomes in the browser.
- Dependencies: Stage 1 checks, summary, and commit complete.
- Expected changes: reuse existing isolated browser fixtures/checks for operator save/reset and thread rendering; add only missing acceptance coverage. Record cleanup/verification in `ui_copy_audit_checklist.md` and the implementation summary.
- Verification approach: exercise disable → reload → enable/reset → reload; check unauthorized/read-only and save-failure/retry states. Check loaded threads with root and later runs, shared actions and expanded menus, empty replies, long text, and overflow at phone/desktop sizes across all themes containing continuation styling plus Chicago. Check the Feature Flags control remains discoverable and usable.
- Risks or open questions: fixture authentication must exercise the actual signed operator flow; record any unavailable scenario as blocked and resolve it before claiming the completion contract met.
- Canonical components/API contracts touched: existing Feature Flags UI and signed save/reset flow, canonical thread cards and theme styles; no new navigation/icon mapping required.
