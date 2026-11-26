Cycle: v3/merge_about_slot; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort, standing P2 enrollment; [ledger](../effort_ledger/merge_about_slot.md)
Extended estimates: enabled

**Cycle estimate: 0.08**

Status: Planned; implementation follows planning commit.
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
Planning commit: pending

Study history:
Intake: Missing pristine intake; logs, diff and source already inspected during Direct diagnosis.
Execution entry: 2026-11-26 UTC; scope v1; projected total 0.08; R1/P2; Codex single exposed rater. All three frozen R1 hashes verified against the host ledger. Small selection correction plus existing tests, live replay and records; no new stages or alternatives. No measured labor; maintainer comfortable-fit judgment pending.

Evidence before implementation: Live GET https://zenmemes.com/about/ returned 500. php tests/run.php SiteProfileRegistryTest::testActiveDefaultsToZenmemesWhenUnset failed with Site profile mitrapclub has an invalid presentation slot selection. Apache HTTP and HTTPS error logs show the same exception from PresentationSlotRegistry::validate through SiteProfileRegistry::active at public/index.php:13.

*— Codex · 2026-11-26 UTC*
