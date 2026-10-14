> **Feature plan:** [Step 1](./bookmarklet_one_line_copy_step1_solution_assessment.md) · [Step 2](./bookmarklet_one_line_copy_step2_feature_description.md) · [Step 3](./bookmarklet_one_line_copy_step3_development_plan.md) · [Step 4](./bookmarklet_one_line_copy_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- **Entry/outcome:** Tools → Bookmarklets offers an icon-only copy button for each of seven existing entries. Copying yields the complete, single-line `javascript:` bookmarklet, identical to its draggable source, without executing it or adding a visible source field.
- **Recovery:** accessible success/failure feedback, keyboard focus retention, and retry; unavailable or denied clipboard access never produces false success.
- **External/release checks:** record actual qutebrowser copy-to-binding execution into Compose Thread, ordinary/tweet content parity and both window modes; check live and locally regenerated static pages. Release requires both stages passing. Missing browser evidence leaves completion open; production deployment is outside this task.

## Key Risks

- **High risk: altered source/content.** Before changing generation, capture ordinary/tweet behavior with quotes, percent signs, Unicode, and content newlines. Preserve escaping and control flow; compare all seven variants rather than blindly removing whitespace.
- **High risk: unusable copy controls.** Exercise unavailable/denied clipboard access and keyboard use early; provide truthful feedback, retry, and compatible fallback without a visible source field.
- **qutebrowser decoding/quoting.** The executable exists at `/usr/bin/qutebrowser`, but launch and clipboard access remain unverified. Confirm an isolated browser session before dependent acceptance work; use its documented bookmarklet URL evaluation mode. Resolve blockers before claiming compatibility.

## Stage 1

- **Goal:** deliver the complete icon-copy flow for all existing variants using the normal clipboard path (about one hour).
- **Dependencies:** explicit Step 3 approval; create the feature branch, organize plans under `docs/plans/bookmarklet_one_line_copy/`, repair navigation/index links, and commit approved planning artifacts before implementation.
- **Expected changes:** extend existing bookmarklet generation to produce one physical line; wire compact icons to the same source as draggable links; add accessible names, tooltips, focus styling, brief result announcements, and retry after failure. Preserve page grouping and both modes.
- **Verification approach:** targeted Node regression coverage of all seven variants for source equality, prefix, absence of line separators, content/attribution parity, and no navigation on copying; run `php tests/run.php LocalAppSmokeTest::testToolsPageRendersBookmarkletsAndComposeThreadAcceptsPrefills`; check pointer/keyboard activation on the rendered page.
- **Risks or open questions:** source corruption and asynchronous false-success feedback; compare baseline behavior and failed writes before accepting the slice. Resolve qutebrowser launch capability before Stage 2.
- **Canonical components/API contracts touched:** `templates/pages/bookmarklets.php`, `public/assets/tools_bookmarklets.js`, `public/assets/tools.css`; reuse the controller's seven-entry inventory and existing source-generator inputs. No new public API or database changes.

## Stage 2

- **Goal:** complete clipboard recovery and prove the qutebrowser use case (about one hour).
- **Dependencies:** Stage 1 checks pass, its Step 4 summary and changes are committed, and an isolated qutebrowser session is available.
- **Expected changes:** add compatible clipboard fallback where feasible without exposing selectable fields; preserve focus and cleanup; finalize transient feedback and any theme/narrow-layout fixes. Add focused recovery/browser coverage and record acceptance evidence.
- **Verification approach:** test allowed, denied, unavailable, fallback-success/failure, and retry paths; verify keyboard operation and supported themes at desktop/narrow widths. Enter through Tools on live and regenerated static pages, copy and bind actual output in qutebrowser, then execute against controlled ordinary/tweet source pages and verify Compose Thread content and both window modes. Record browser version and results; rerun affected checks after fixes.
- **Risks or open questions:** browser restrictions may defeat fallback; report failure honestly and retain retry. If required external verification cannot run, report incomplete and resolve the environment or return to Step 2 for scope approval.
- **Canonical components/API contracts touched:** same bookmarklet template/script/styles, targeted browser regression coverage, existing static-page and Compose Thread paths for verification only; no new routes or configuration UI.

Each stage ends with its verification evidence and Step 4 summary update in a stage-scoped commit before further work. Keep planning documents uncommitted until Step 3 approval.
