> **Feature plan:** [Step 1](./nojs_basic_access_step1_solution_assessment.md) · [Step 2](./nojs_basic_access_step2_feature_description.md) · [Step 3](./nojs_basic_access_step3_development_plan.md) · [Step 4](./nojs_basic_access_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/nojs_basic_access` ([ledger](../effort_estimation_trial.md#cycle-v3nojs_basic_access)); standing enrollment P2, joining at this checkpoint
Extended estimates: disabled (resumed pre-P2 cycle; prior artifacts carry no estimates, so the new-cycle default is not applied retroactively)

**Cycle estimate: 0.35**

Revision 2026-11-16: Step 2 reconfirmed by the user ("approved step 2") after 1,124 commits of drift. This plan replaces the unapproved 2026-10-19 draft (archived in commit `8c55cd50`), rebuilt from a fresh baseline. Follow-on cycle: [Windows ME compatibility](../windows_me_compat_step1_solution_assessment.md) ([checklist](../windows_me_compat/checklist.md)).

## Completion Contract

Zenmemes public forum, scripts disabled from startup in a real browser: ordinary entry → board pagination → thread → inline and standalone anonymous thread and reply → persisted content after reload. A failed submission keeps the draft. A members-only instance denies protected pages truthfully, with no endless "Reconnecting". Signing and access gates are unchanged. No fingerprinting, schema, authentication or deployment changes. Release requires a committed no-script browser check, the focused regressions, and a Lynx posting replay.

## Baseline (2026-11-16, Chromium, scripts off, local)

- Already working: inline board composer visible with Post; 32 of 33 script-only buttons hidden; standalone thread and reply posting (`tests/browser/composers.mjs` PASS); empty body re-renders with the title kept and "body is required."; board pagination wired (`1e536cff`, `BoardPageController.php:92`).
- Still broken: the "View query" button shows but needs scripts. `authentication_resume.php` claims "Reconnecting / Verifying" with nothing to complete it (`Application.php:257`, HTTP 401).
- Baseline failure: `tests/browser/board_compose_focus.mjs` times out at line 61 (`networkidle`, scripts-on section) on two runs, so its no-script assertions never run.

## Key Risks

- **High risk: regressions for script-enabled visitors.** Hiding controls or changing the resume page could hide working controls or break private-site sign-in. Mitigation: only the existing `@media (scripting: none)` pattern; private-site auth and composer checks run with scripts on.
- **Unverified pagination.** The local data has too few threads. Mitigation: the Stage 1 fixture seeds more than one page and checks page 2 and back.
- **Masked coverage.** The `board_compose_focus` timeout hides its no-script checks. Mitigation: Stage 1 finds the cause. A fix stays in scope only if it is to the test harness; anything else is recorded as a follow-up.

## Stage 1 — No-script journey check

- Goal: one committed, repeatable browser check of the whole Step 2 journey.
- Dependencies: approved plans committed on the feature branch.
- Expected changes: new `tests/browser/nojs_basic_access.mjs` on the existing fixture/router pattern from `composers.mjs`. It seeds more than one board page and covers entry, page 2 and back, a thread, inline and standalone thread and reply, an empty-body error keeping the draft, then correction and reload. Diagnose the `board_compose_focus.mjs:61` timeout.
- Verification approach: the new check fails only on the known gaps (View query, resume page); `composers.mjs` still passes.
- Risks or open questions:
  - Impact: fixture pollution invalidates results.
  - Early warning / validation: temp root, sessions and caches, checked after teardown.
  - Mitigation: reuse the fixture helpers, which already clean up.
- Canonical components/API contracts touched: `tests/Support/signed_reaction_browser_fixture.php` and router (reused); no production code.

## Stage 2 — Truthful controls and protected recovery

- Goal: no dead script-only control on the scoped pages, and an honest members-only denial.
- Dependencies: Stage 1 check.
- Expected changes:
  - Hide `[data-view-query]` and any other dead control Stage 1 finds when scripts are off, using `@media (scripting: none)`.
  - On the resume page without scripts, the heading and copy state the denial, with the existing account-key and lobby links kept.
  - Record the copy change in `ui_copy_audit_checklist.md`.
- Verification approach: Stage 1 check fully green; scripts-on private-site auth resume and view-query checks pass.
- Risks or open questions:
  - Impact: hiding too much removes recovery links.
  - Early warning / validation: the check asserts that the links stay visible.
  - Mitigation: targeted selectors only, no global button hiding.
- Canonical components/API contracts touched: `site.css` no-script block, `authentication_resume.php`, `Application::renderAuthenticationResumePage` (signature unchanged).

## Stage 3 — Acceptance, Lynx replay and closeout

- Goal: prove the contract and hand off to the Windows ME cycle.
- Dependencies: Stages 1–2.
- Expected changes: Step 4 evidence only.
- Verification approach:
  - Run the new check, `composers.mjs`, `board_compose_focus.mjs` and the affected PHP tests.
  - Lynx: entry → `/compose/thread` → guest post → shown after reload.
  - Themes: modern browser with scripts on and off.
  - Record statuses and limits honestly.
- Risks or open questions: Lynx shows the clutter that CSS hides (theme buttons). That belongs to the Windows ME cycle and only Lynx posting is required here.
- Canonical components/API contracts touched: none beyond Stages 1–2.

## Concepts touched

- [Shared composer](../../concepts/shared_composer.md): used unchanged.
- Page shell / no-script presentation (proposed entry, shared with the Windows ME cycle): changed in Stage 2.
- Private-site authentication resume (proposed entry): changed in Stage 2.

## Requests

- From the Windows ME sequencing (2026-11-16), "hide script-only controls with selectors IE6 and Netscape understand": modern no-script hiding already works, so the old-engine technique moves to the Windows ME cycle. Not in this scope.
- From the same sequencing, "add Lynx to the checks": included in Stage 3 for posting only.
- 2026-10-19 archival request (commit plans, todo reference): done in `8c55cd50`.

## Study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff (first recorded) | 2026-11-16 | 0.35 | v1 (Step 2 of 2026-10-19, reconfirmed) / R1 / P2; baseline `main` `32f884b1` | Claude (claude-opus-5-5), single exposed rater. Saw the 2026-10-19 draft, the live baseline and the Windows ME evidence. | Most of the journey already works; remaining work is one check, two targeted fixes and evidence |

Earlier checkpoints (intake, Steps 1–2 and the first Step 3 draft) predate P2 and stay missing, with nothing backfilled. R1 hashes verified 2026-11-16T07:15Z. No implementation, measured labor or accepted outcome.

Debug: [diagnostic log](./nojs_basic_access_debug_log.md#2026-11-16--step-3-revision).

Approval status: awaiting `Approved Step 3`. No implementation authorized.

*— Claude (claude-opus-5-5) · 2026-11-16 UTC*
