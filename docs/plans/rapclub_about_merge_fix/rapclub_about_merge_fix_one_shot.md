Cycle: `v3/rapclub_about_merge_fix`; Mode: one-shot; Record/index: [plans index](../README.md). Corrects the merge `7b4b8298c` of [MIT Rap Club About copy](../rapclub_about_copy/rapclub_about_copy_one_shot.md) (PR #439) with [MIT Rap Club music](../mitrapclub_music/mitrapclub_music_step1_solution_assessment.md) (PR #438).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/rapclub_about_merge_fix`; host ledger [rapclub_about_merge_fix.md](../effort_ledger/rapclub_about_merge_fix.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.03**

Status: planned; next permitted action: implementation.

Feedback item: none matches (searched `docs/feedback/items/` for 500, presentation slot and About; PF-20261030-B17 is an unrelated code-heavy-post 500).

Original request (maintainer, 2026-11-26): "please help me resolve the 500 error", then "it has to do with the merge i mis-did on the most recent commit probably".

Authorization: that request, 2026-11-26, as a one-shot fix of the 500. Exclusions: no change to the About copy, the Music page or either branch's behaviour; merging and deploying are the maintainer's.

Diagnosis: `./v3 status` reports HTTP 500 at https://mitrapclub.com/ since the 12:59 UTC deploy of `64331adc0`; `state/private/task_queue_errors.log` gives "Site profile mitrapclub has an invalid presentation slot selection." (`PresentationSlotRegistry::validate`, from `SiteProfileRegistry::all()`). PR #439 renamed the About variant `socials` to `short` in both the registry and the mitrapclub profile. The merge took the branch's registry (`short`) but main's profile line (`'about' => 'socials'`), so every request that loads a profile throws. The same conflict hunk also left `homePage()`'s docblock above the new `musicSection()` method.

Bounded outcome and work type: application fix.
1. The mitrapclub profile selects `'about' => 'short'`, keeping `musicSection` and every other slot from the merge.
2. `homePage()`'s docblock moves back above `homePage()`.

Approach and reuse decisions: restore the value PR #439 intended; no new component, contract or test. `PresentationSlotRegistryTest` already fails on main with this error (4 of 5 tests), so it is the regression check.

Risks: none material beyond the outage itself. Other merge damage was checked: `git diff 711db36fe 64331adc0` on the profile, slot, About content and About template files shows only the Music additions and this line.

Completion conditions: profiles validate; mitrapclub's About page renders the short copy; `./v3 status` reports the frontend OK after Deploy now.

Concepts touched: none.

Verification and recovery plan: `./v3 test --smoke PresentationSlotRegistryTest SiteProfileRegistryTest` plus any About page test; render `/about/` and `/music/` locally with `FORUM_SITE_ID=mitrapclub`; `git diff --check`. Full suite not run. Production recovery needs the maintainer to merge and Deploy now; until then the replay is local only.

Starting revisions and branch: `fix/rapclub_about_merge` from `origin/main` `64331adc0`.

Planning commit: pending.

Study history:
- 2026-11-26 UTC, intake and execution entry: **0.03**. Recorded after diagnosis, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Attribution: Claude · 2026-11-26 UTC · Model: claude-opus-5-5
