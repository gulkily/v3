Cycle: v3/merge_about_slot; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort, standing P2 enrollment; [ledger](../effort_ledger/merge_about_slot.md)
Extended estimates: enabled

**Cycle estimate: 0.08**

Status: Fixed in serving checkout; live recovery and required tests passed; PR #440 open.
Original request: please help me fix the 500 caused by recent merge
Authorization: 2026-11-26 user requests the outage fix, supplies ~/logs/, and confirms all pages return 500. Bounded correction in this serving checkout, verification and new follow-up PR; no unrelated production settings, data changes or main merge.
Predecessor: PR #439, rapclub_about_copy; integration with PR #438 left the old profile value.
Feedback: Searched docs/feedback/items/ for 500, fatal, parse error and recent merge. No matching item; PF-20261030-B17 is an unrelated posting failure.
Outcome: Restore normal application startup and existing short About content by selecting the registered short variant for mitrapclub.
Reuse decisions: src/ForumRewrite/SiteProfileRegistry.php owns profile selections; PresentationSlotRegistry.php already registers short; ProfilePresentationContent.php and templates/pages/about.php already implement it. Correct only the selection, retaining validation. Existing SiteProfileRegistryTest and ProfilePresentationContentTest cover startup and all profiles; reuse them, adding no duplicate tests or components.
Completion conditions: The actual HTTPS About request changes from 500 to 200; representative home, board and Recent requests recover; existing profile/content tests pass. Repeated requests verify recovery without changing data or restarting services.
Risks: This checkout serves production (confirmed by Apache trace). A narrow one-value correction avoids changing unrelated behavior. Fresh origin/main equals HEAD; preserve all untracked files. Do not switch the serving checkout back to the broken revision after fixing it.
Verification plan: Replay failure through live GET and existing focused test, then run ./v3 test --smoke SiteProfileRegistryTest ProfilePresentationContentTest. Check PHP syntax and git diff --check. Full suite will not run. No schema, dependency, navigation-icon or CSS changes; visual layout checks inapplicable to configuration restoration, rendered profile checks still apply.
Recovery: Retrying affected pages should suffice after the correction. If 500 remains, inspect the new log entries before any further change. Do not roll back to the known-broken profile value.
Concepts touched: Site presentation-slot selection; propose a concise docs/concepts/presentation_slots.md entry documenting existing validation and registered selection contract.
Starting revision: 64331adc097390601e371d321a0ca01ae316e2ea, freshly fetched origin/main.
Branch: fix/merge-about-slot
Planning commit: aeaefc24c; confirmed before implementation.

Study history:
Intake: Missing pristine intake; logs, diff and source already inspected during Direct diagnosis.
Execution entry: 2026-11-26 UTC; scope v1; projected total 0.08; R1/P2; Codex single exposed rater. All three frozen R1 hashes verified against the host ledger. Small selection correction plus existing tests, live replay and records; no new stages or alternatives. No measured labor; maintainer comfortable-fit judgment pending.

Evidence before implementation: Live GET https://zenmemes.com/about/ returned 500. php tests/run.php SiteProfileRegistryTest::testActiveDefaultsToZenmemesWhenUnset failed with Site profile mitrapclub has an invalid presentation slot selection. Apache HTTP and HTTPS error logs show the same exception from PresentationSlotRegistry::validate through SiteProfileRegistry::active at public/index.php:13.

*— Codex · 2026-11-26 UTC*

Execution: 2026-11-26 UTC; changed only mitrapclub presentationSlots.about from socials to short. Existing validation, renderer and template remain intact. Added the presentation-slots concept entry for the existing contract.
Live replay: Initial GET immediately after the write still returned 500. Subsequent HTTP/HTTPS About, HTTPS home, Threads and Recent returned 200, including a repeated About request. No restart or data change was needed.
Test environment: The first smoke-plus-profile run encountered an existing /tmp/forum-rewrite.lock owned by another hosting account. Rerunning with a dedicated TMPDIR isolates temporary databases and locks; results pending.

Authorization update: 2026-11-26 UTC; user says “great job, please push it”. Push and follow-up PR explicitly authorized; supplied credential is excluded from repository records.
Merge diagnosis: Original implementation d30a25b3a correctly changed the selection to short; the subsequent integration restored socials while retaining the new registered choices. This fix restores the intended selection.

Verification: Passed TMPDIR=/tmp/v3-merge-about-slot-tests-dh-zsagad ./v3 test --smoke SiteProfileRegistryTest ProfilePresentationContentTest: 509 run, 509 passed, zero failed. Full suite was not run. Log: /tmp/merge-about-slot-isolated-tests.log.
Initial test attempt: 509 run, 394 passed, 115 failed with shared temporary-file permission errors and their downstream effects. No test code changed; all pass with isolated TMPDIR.
Additional checks: PHP syntax, git diff --check and task document links passed. Existing rendered About/docs checks cover every registered site profile. Live HTTPS /, /threads/, /recent/, /about/ and HTTP /about/ returned 200. The logged startup exception stopped after the initial post-write retry.
Final review: One production value changed; no duplicated components, dependencies, schema, data or UI structure changed. Unrelated untracked files preserved. Concept source links verified. No additional requests or split cycles.
Study closeout: 2026-11-26 UTC; v1/R1/P2; retrospective scope assessment 0.08. Execution projection 0.08 retained. One bounded correction completed; extra work was test-environment isolation. Codex single exposed rater; maintainer comfortable-fit judgment pending. No measured labor or accepted outcome count asserted.
Outcome: Reported outage recovered through ordinary live page requests. Leave serving checkout on this fixed branch until main contains the correction.
Commit status: Planning aeaefc24c; completion commit pending at record creation. Push explicitly authorized; follow-up PR pending. Main has not been merged.

*— Codex · 2026-11-26 UTC*

Publication: Completion commit c99c706736e2b7ecdce5b52650066051c9076c7d pushed to origin/fix/merge-about-slot. Follow-up PR: https://github.com/gulkily/v3/pull/440 (open). Initial push lacked credentials; authenticated retry succeeded without storing credentials. Main merge remains pending.
