# Calendar iCal feed debug log

## 2026-11-17T21:50Z — Step 1

**Necessary/useful instructions**
- `AGENTS.md` "search `docs/feedback/items/`": found PF-20261108-F02 (approved-only calendar), which the feed must honour; retain.
- `implementation_standards.md` "Inspect existing implementations": pointed every feed option at `CalendarQuery.php` rather than a second event query, so the approval rule cannot drift; retain.
- `concepts.md` planning guidance: reading the Calendar concept surfaced its Boundary line "no … export", which this cycle changes; retain.

**Redundant/unnecessary instructions**
- `RAILSPIKE.md` Experimental Effort Estimation: intake and Step 1 snapshots are identical when both follow inspection; a single combined line would do, as noted in earlier cycles.

**Inapplicable instructions**
- Documentation-only scope rules: this is an application change.

**Uncertain assessments**
- How Google Calendar shows floating (zone-less) times in a subscribed feed is stated from general knowledge, not tested; Step 2 should confirm it early.
- The Step 1 "/new or /clear" reminder applies because the conversation discussed the missing feed before this Step 1 began.

## 2026-11-17T21:55Z — Step 1 Revision 1

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Review and revision" (combine compatible parts; recheck tradeoffs and scope): combining B and C surfaced the shared iCal writer and the question of where per-event links belong; retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Step 1 "/new or /clear" reminder: once per cycle; not repeated on revision.

**Uncertain assessments**
- Whether Google's event template link fits a multi-day all-day event cleanly is not tested; Step 2 should confirm it with the time-zone check.

## 2026-11-17T22:05Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` "Known user or environment": tying acceptance to Ilya's iPhone surfaced the Google-app handoff risk, which a desktop test would hide; retain.
- `implementation_standards.md` "Identify all existing UI/API surfaces": found `event_block.php` shared by rows and cards, which moved the per-event links to `thread_root_card.php`; and found the `/threads/<id>` catch-all that would swallow `.ics`; retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only scope fields: this is an application change.

**Uncertain assessments**
- `RAILSPIKE.md` requires the exact phrase "Approved Step 1"; the maintainer wrote "approved step 2" while Step 1 was pending. Read as Step 1 approval and recorded in Step 2; Step 2 still asks for its own approval. Suggest Railspike say how to treat a step number that does not exist yet.

## 2026-11-17T22:20Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md` "Resolve each material risk before dependent work begins": produced Stage 1, a probe feed Ilya checks on his phone before the time format or the panel is built; retain.
- `step3_development_plan_do.md` "include a stage that replays each reported failure": produced Stage 7 on zenmemes.com, kept pending until Ilya reports; retain.
- `implementation_standards.md` reuse inspection: found the iPhone-safe copy code inline in `agent.php`, so Stage 5 moves it to a shared asset instead of writing a second one; retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Database change documentation: no schema change in this cycle.

**Uncertain assessments**
- Stage 1 depends on Google fetching a raw GitHub URL; not confirmed, and the web "From URL" path is the stated fallback.

## 2026-11-17T23:45Z — Step 4 Stages 1–2

**Necessary/useful instructions**
- `AGENTS.md` "Follow-up changes after a PR": the PR was opened as a draft because the maintainer reviews by merging, and the cycle is mid-way; retain.
- `step4_implementation_do.md` stage summary rules: recording that Google's add offer comes before any fetch kept a partial result from being reported as a pass; retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Read-model schema change rules: no schema change.

**Uncertain assessments**
- The maintainer asked to start Stage 2 before Stage 1's time checks finished, which the plan made a dependency. Proceeded at his request; recorded in the Stage 1 status.

## 2026-11-18T02:35Z — Step 4 (Stages 3–6 and closeout)

**Necessary/useful instructions**
- `AGENTS.md` "Follow-up changes after a PR": #324 and #325 were merged mid-cycle, so Stages 3 and 4–6 went out on new branches from `origin/main` with new PRs; retain.
- `step4_implementation_after.md` checklist item 2 (each stage has its own commit): kept Stages 5 and 6 as separate commits on one PR; retain.
- `implementation_standards.md` "replay that condition": the maintainer's screenshot of the live feed showed every event named "Event", which led to the Stage 4 title fix; retain.
- `step4_implementation_after.md` "A focused pass is never reported as a full-suite or user-outcome result": kept Stage 7 pending, not passed; retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Navigation icon rule in `AGENTS.md`: no new navigation destination.
- Read-model schema rules: no schema change.

**Uncertain assessments**
- Stage 2 reported `CalendarIcsTest` as passing, but the test was not registered in `tests/run.php`, so it never ran. The runner prints "No tests matched" only when a test is named alone. A Railspike or project check that every `tests/*Test.php` is registered would have caught this; suggest one.
- The summary's Stage 4 commit hash was written from expectation rather than read from `git log`, and was corrected in a follow-up commit. Suggest that Railspike say to record hashes only after reading them.
- The maintainer waived step approvals for the remaining stages ("You don't have to wait for my approval anymore"); Stage 5 was not a separate approval point anyway.
