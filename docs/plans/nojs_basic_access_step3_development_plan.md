> **Feature plan:** [Step 1](./nojs_basic_access_step1_solution_assessment.md) · [Step 2](./nojs_basic_access_step2_feature_description.md) · [Step 3](./nojs_basic_access_step3_development_plan.md) · [Step 4](./nojs_basic_access_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Zenmemes public forum: ordinary entry → pagination → reading → anonymous thread/reply → persisted content after reload, including retained-draft validation recovery. Verify in a browser with scripts disabled from startup; preserve signing and access gates. No fingerprinting, schema/authentication changes, or production deployment. Release requires local browser evidence and focused regressions. After approval: branch, organize/commit plans, then commit each verified stage with its summary.

## Key Risks

- **High risk: unusable controls.** Chromium reproduced a blocked inline prompt; standalone forms work. Reproduce in Stage 1; repair native interaction in Stage 3.
- **High risk: misleading recovery.** Protected GET correctly returned 401 but displayed perpetual Reconnecting. Stage 4 preserves denial and supplies truthful recovery.
- **New pagination behavior:** only QDB currently paginates. Stage 2 adds default-forum pagination as Step 2 requires; verify sorting, feeds, and caches before acceptance.

## Stage 1 — Isolated browser baseline (≤1 hour)

- Goal: make the exact disabled-script journeys reproducible.
- Dependencies: approved plans committed on the feature branch.
- Expected changes: isolated browser fixture: records, sessions, caches, public/protected modes, multiple pages; capture baseline.
- Verification approach: fresh script-disabled browser reproduces navigation, standalone submissions, validation recovery, and blocked inline prompts; verify cleanup.
- Risks or open questions: fixture pollution would invalidate results; verify separate paths and teardown before posting.
- Canonical components/API contracts touched: existing test bootstrap/front controller; new no-script browser fixture/check, no production behavior changes.

## Stage 2 — Native board pagination (≤1 hour)

- Goal: browse successive default-forum pages through ordinary links.
- Dependencies: Stage 1 fixture.
- Expected changes: wire page selection into board routing; use existing pagination helper, 25 threads/page. Preserve view/sort, clamp bounds, reset page on filter changes; retain complete RSS and existing QDB behavior.
- Verification approach: native page navigation, empty/invalid/boundary cases, stable complete traversal, cached entry/query pages; board/feed/QDB regressions.
- Risks or open questions: omitted/duplicated content or stale results; check boundary IDs and cache invalidation after a write before continuing.
- Canonical components/API contracts touched: Application board routing, BoardPageController, BoardViewOptions, board/pagination partials; existing read model, no schema/API signature change expected.

## Stage 3 — Native anonymous composition (≤1 hour)

- Goal: reach and submit both composers from normal controls, including inline prompts.
- Dependencies: Stage 2 browsing works.
- Expected changes: repair disclosure activation; reuse forms, validation, redirects, and anonymous writes; preserve drafts/targets on error.
- Verification approach: mouse/keyboard inline/standalone entry, both guest writes, validation failure/correction per journey, reload persistence; script-enabled disclosure/signing checks.
- Risks or open questions: native changes could interfere with signing; preserve existing hooks and verify before proceeding.
- Canonical components/API contracts touched: board/thread inline composers, shared forms/action menu, compose styling/script, existing compose controller/write contracts.

## Stage 4 — Honest availability and protected recovery (≤1 hour)

- Goal: remove misleading script-dependent controls from the scoped journey and end the false reconnecting state.
- Dependencies: Stage 3 posting works.
- Expected changes: progressively expose script-only controls in scoped pages; keep native actions usable. Show concise authentication limitation/recovery links; update copy audit checklist.
- Verification approach: scripts off/on controls; protected denial/recovery without false progress; affected themes and required icons if destinations change.
- Risks or open questions: overly broad hiding could remove recovery or native controls; use targeted availability changes, no global hiding of buttons.
- Canonical components/API contracts touched: layout/theme/density menus, affected compose/post partials and assets, authentication_resume.php; authorization unchanged.

## Stage 5 — Acceptance and regression evidence (≤1 hour)

- Goal: prove the complete approved outcome and recovery.
- Dependencies: Stages 1–4 verified and committed.
- Expected changes: finish browser assertions/regressions and summary evidence.
- Verification approach: fresh disabled-script entry, pagination, inline/standalone posts, validation correction, reload, protected denial; affected PHP, disclosure, lazy-signing, signing-normalization, private-auth, shared variant/theme checks. Record commands/statuses/limitations.
- Risks or open questions: browser/signing/access failures block release; replan oversized stages (>1 hour), >8 stages, or schema/authentication changes.
- Canonical components/API contracts touched: browser fixture/check, focused existing tests, Step 4 evidence; no additional feature surface.

Workflow status: set aside at the user's request on 2026-10-19. Steps 1 and 2 are approved; Step 3 remains a draft. The user explicitly requested committing the planning artifacts and adding a todo reference, overriding the usual pre-implementation planning commit gate for this archival handoff only. No implementation is authorized. Revalidate against current code before resuming Step 3 review.

## Request record

- Request: set aside the plan by committing it and adding a reference in `todo.txt`.
- Disposition and reason: preserve Steps 1–3 and the debug log in their current locations for future review; defer implementation.
- Approval status: archival commit and todo edit explicitly authorized; Step 3 remains unapproved.
- Outcome: planning artifacts and todo reference included in the archival commit; no Step 4 started.
