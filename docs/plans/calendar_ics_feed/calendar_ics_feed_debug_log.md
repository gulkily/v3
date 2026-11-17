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
