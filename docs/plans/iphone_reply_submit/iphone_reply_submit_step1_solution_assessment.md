> **Feature plan:** [Step 1](./iphone_reply_submit_step1_solution_assessment.md) · [Step 2](./iphone_reply_submit_step2_feature_description.md) · [Step 3](./iphone_reply_submit_step3_development_plan.md) · [Step 4](./iphone_reply_submit_step4_implementation_summary.md)

Debug mode: enabled

Interactive review: [Compare the three mockups](./iphone_reply_submit_mockups/index.html) (simulated keyboard; no device-validation claim).

## Original Query

Please write Step 1.

## Understood Intent

Assess Feature 16 from the [Zenmemes feedback summary](../zenmemes_meta_feedback_2026-10-18_summary.md): keep Send/Post visible on iPhone, including while the keyboard is open. Cover the ordinary inline thread-reply form and dedicated compose-reply page. The earlier recommendation proposed comparing an action above the editor with one attached to the visible viewport.

## Problem

The shared reply form places its submit action below the editor, where the iPhone keyboard may make it difficult to reach; current-device reproduction remains pending.

## Option A — Place the action above the editor

Move the existing action row above the reply body so users encounter Post before entering text.

- Pros: simple, discoverable placement; little moving UI; ordinary document flow works without JavaScript.
- Cons: scrolling a long draft can still move Post offscreen; placement alone does not satisfy continuous keyboard-open visibility.

## Option B — Keep the action at the visible viewport edge

Keep one primary action row visible while the reply editor is active, positioned above the keyboard, and return it to ordinary flow when editing ends.

- Pros: directly addresses keyboard-open reachability, including long drafts; keeps one primary submit control and existing secondary actions.
- Cons: keyboard, browser chrome, rotation, and zoom can change available space; the row must not cover text, errors, or recovery controls. Requires actual iPhone validation.

## Option C — Keep an editor header visible while composing

Place the action above the body and keep that header visible as the user scrolls within the active composer.

- Pros: stable relationship between Post and the editor; can combine the discoverability of A with continued visibility.
- Cons: nested scrolling can make draft navigation harder; keeping the header visible across keyboard and page scrolling still needs device validation and may reduce usable writing space.

## Recommendation and viable slice

Prefer **Option B**, subject to early reproduction and feasibility checks on an actual iPhone. A alone is insufficient for the requested visibility; C remains an alternative if B cannot preserve comfortable editing. Reassess here if device evidence changes the recommendation.

The releasable slice starts from a thread's ordinary Reply entry or its dedicated reply page and ends with one successful submission. Keep the draft, selection, validation feedback, and retry action usable through keyboard opening/closing, scrolling, and rotation. Preserve secondary actions and the ordinary no-JavaScript flow; avoid duplicate submissions and extra explanatory copy. Private messages and Forte composer redesign are outside scope, but shared-form changes require regression checks of Forte.

Acceptance needs iPhone Safari and Firefox checks in portrait and landscape with short and long drafts, plus phone/desktop and affected-theme regression checks. Emulation supplements these checks; it cannot close the keyboard requirement. Arrange device access before dependent implementation and leave acceptance pending if unavailable. This assessment records no reproduced device result or implementation approval.

## Review request

- **Request:** Build a mockup of each option. **Disposition:** In-scope Step 1 exploration, authorized by the user; no advancement or application implementation. **Outcome:** Local interactive comparison linked above, including keyboard/height simulations, long drafts, and simulated failure/retry. Chromium interaction and 390px overflow checks passed; actual iPhone verification remains pending.
