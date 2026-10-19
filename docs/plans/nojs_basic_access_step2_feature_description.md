> **Feature plan:** [Step 1](./nojs_basic_access_step1_solution_assessment.md) · [Step 2](./nojs_basic_access_step2_feature_description.md) · [Step 3](./nojs_basic_access_step3_development_plan.md) · [Step 4](./nojs_basic_access_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Visitors with JavaScript disabled need to browse and contribute basic text content through the ordinary site interface. Existing HTML links and forms are a foundation, but complete journeys and recovery are not yet verified.

## User stories

- As a visitor with JavaScript disabled, I want to browse boards, pages, and threads so that I can read discussions.
- As an anonymous participant, I want to create a text thread and reply so that I can contribute without browser signing.
- As a participant whose submission fails validation, I want my draft and an actionable error so that I can correct it and retry.

## Core requirements

- Ordinary navigation, board pagination, thread reading, and access to thread/reply composition work with JavaScript disabled before the first request.
- Both anonymous text submission journeys use the existing content and policy model; successful submissions lead to readable persisted content and remain visible after reload.
- Validation failures preserve submitted text and relevant form context, display the error, and allow correction and resubmission without scripts.
- Preserve site gates and action-specific signing requirements. No new fingerprint collection, identity proof, guest account system, or browser storage requirement; ordinary session cookies may be used. Cookie-free participation is outside this slice.
- Essential controls and recovery remain discoverable and usable without scripts; script-dependent actions must not misrepresent availability. Keep copy minimal and track any copy cleanup in the existing audit checklist.

## Delivery scope and completion boundary

**Work type: application change.** Acceptance target: the default Zenmemes public forum, with anonymous text participation permitted and ordinary cookies enabled. Reuse the existing interface; no separate basic site. Other site variants, search, reactions, uploads, signed actions, key management, encrypted messages, offline tools, and external signing are deferred. Shared changes must preserve existing script-enabled behavior and protected access.

Release requires entry through the normal home/navigation path, board pagination and thread reading, both persisted anonymous posting outcomes, and correction/retry after validation failure. Verify in a real browser with JavaScript disabled from startup, using isolated content. No production posting is required. A successful source review or HTTP-only check does not satisfy browser acceptance.

## Known environment and recovery

The visitor starts with scripts disabled and no saved identity or authenticated session. From normal navigation they reach a board, open a thread, and compose. On validation failure they correct the visible form without retyping the retained draft; success is verified by following the result and reloading the content. An unauthenticated visitor to a members-only instance remains denied protected content and has usable existing entry/recovery links; fresh no-JavaScript member authentication is deferred. Evidence must cover this exact disabled-script environment, including absence of false success or an endless automatic-reconnection state.

## Risks

- **Hidden interaction dependencies:** menus, styling, or pagination may require scripts and strand visitors. Validate the baseline journeys before Step 3; reuse native links/forms/disclosures and expose only needed recovery.
- **Anonymous write and access policy:** apparent UI availability may disagree with server permissions or attribution. Before Step 3, inspect both submission paths and site gates; retain anonymous attribution and existing authorization, and block dependent planning on unresolved policy conflicts.
- **Shared-interface regressions:** fixes may disturb signing or variant rendering. Inventory shared surfaces before Step 3; plan focused script-enabled and protected-access regression checks, with variant checks wherever shared changes affect them.

## Shared component inventory

- Extend canonical layout/navigation (`layout.php`, `partials/nav.php`) and board/page navigation (`pages/board.php`, `partials/board_pagination_nav.php`); reuse existing board cards and thread/post rendering (`pages/thread.php`, `partials/thread_card.php`, `thread_root_card.php`, `post_card.php`).
- Reuse/extend existing compose pages, shared thread/reply forms, native compose action menu, and feedback partial; include inline composers wherever these are the ordinary entry. No duplicate composer or content renderer.
- Reuse the existing compose GET/POST handlers, write service, validation, and success redirects; retain the existing site gate and authentication recovery surfaces. JSON APIs and browser signing remain shared existing contracts, not a new prerequisite for this slice.

## Simple user flow

1. Enter the public site, browse a board and its next page, and open a thread.
2. Reach composition through the normal controls; submit an anonymous text thread or reply.
3. If validation fails, correct the retained form and retry; follow success to the saved content and reload it.

## Success criteria

Both posting journeys and board pagination pass in the disabled-script browser; at least one recoverable validation error per posting journey retains entered text and context, then succeeds after correction. Content remains readable after reload, with anonymous attribution. Protected access stays denied without authentication, and focused existing script-enabled signing checks pass. Record browser evidence and any unverified boundary explicitly.

Approval status: Approved Step 2 received 2026-10-19; implementation awaits approval of the completed Step 3 artifact.
