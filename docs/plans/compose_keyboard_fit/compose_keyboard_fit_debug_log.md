# compose_keyboard_fit Railspike debug log

## 2026-11-02T01:15Z — Step 1

Mode: enabled by the project marker `.railspike_debug_on` (new workflow, no explicit request).

### Necessary/useful instructions

- `RAILSPIKE.md` "Railspike Debug Mode", project default: presence-only marker check resolved the mode without a question.
- `step1_solution_assessment.md` "Structure", Original Query and Understood Intent: forced the forum query and the maintainer's later clarification to be kept verbatim and separate, which is what makes this restart different from the superseded posts.
- `RAILSPIKE.md` "Key Rules", "Prefer shared components/API contracts first": shaped Option B (reuse `reply_viewport.js`) and the rejection of Option C (a second heuristic).
- `RAILSPIKE.md` "Experimental Effort Estimation", exposure disclosure: code was inspected before rating; the rule made the intake snapshot honest instead of implied-prospective.
- Host ledger "Continuing enrollment (P2)": supplied the cycle ID format and the requirement to record a replaced predecessor.
- `concepts.md` "Planning": produced the Concepts touched list; no inventory entries exist for the composer, so they are marked proposed.

### Redundant/unnecessary instructions

- `RAILSPIKE.md` "Step 4 commit cadence" and "Documentation-only scope": read on the way to Step 1 guidance but not actionable at Step 1; a Step 1-only reader could skip them. Suggest keeping the Step Guide pointer and letting the step file own them.
- `step1_solution_assessment.md` restates the handoff/debug/stop sequence that `RAILSPIKE.md` already specifies; consolidating to one pointer would shorten the file.

### Inapplicable instructions

- One-shot workflow references (`one_shot.md`), follow-up branch reuse, and Step 4 planning-commit organization: not reached in a staged Step 1.

### Uncertain assessments

- Whether a forum thread counts as the "first artifact" for enrollment purposes. Treated here as not an artifact (no repository file), so this cycle gets a new ID with the forum cycle recorded as a replaced predecessor. If the host study prefers reusing `v3/mobile_compose_keyboard`, the ledger row can be relabeled without changing scores.
- Attribution: the runtime model is recorded as `claude-fable-5-1` from session metadata; reasoning effort is not exposed and is omitted.

## 2026-11-02T01:20Z — Step 2

Mode: enabled, inherited from Step 1.

### Necessary/useful instructions

- `step2_feature_description.md` "Known user or environment": forced the iPhone SE Firefox entry, failure and evidence to be stated, and made explicit that headless WebKit does not count. This is the item the superseded forum plan handled loosely.
- "Shared component inventory" and "Risks": surfaced that the paned New Thread dialog renders the full composer and that the reserved picker footprint exists to prevent layout shift, both of which shape Stage 1.
- `RAILSPIKE.md` "Enhancement Requests": kept the theme overflow and forum-reply items visible as pending without widening scope.
- Effort estimation "reaffirm unchanged values": produced an explicit Step 2 snapshot rather than an implied carry-over.

### Redundant/unnecessary instructions

- `step2_feature_description.md` repeats the full documentation-only scope rules for a cycle whose work type is an application change; a one-line pointer would do.
- "Guardrails: avoid UI mockups" and "keep lightweight" overlap with the one-page deliverable limit already stated.

### Inapplicable instructions

- Documentation-only scope definitions and one-shot transition rules.

### Uncertain assessments

- Whether "Concepts touched" should list a proposed inventory entry when `docs/concepts/` has no composer entry at all. Listed as proposed and included in delivery scope; the Step 4 stage will decide the file.

## 2026-11-02T01:30Z — Step 3

Mode: enabled, inherited.

### Necessary/useful instructions

- `step3_development_plan_do.md` "include a stage that replays each reported failure": produced Stage 3's device replay and the rule that emulator evidence does not substitute for it.
- "Stages about one hour or 50 lines": kept the reorder and the phone styles as separate stages with separate verification instead of one change.
- `step3_development_plan_before.md` "reconcile recorded requests against approved Step 2": restated the two pending requests as separate work before planning.
- Extended estimates "stage work not summed": kept S1–S3 annotations beside an independently reaffirmed 0.20.

### Redundant/unnecessary instructions

- The three Step 3 files (before/do/after) repeat the debug-state restoration and effort-snapshot instructions three times for one artifact; one statement in `do` would suffice.
- `after` checklist item 1 ("verify every stage field") duplicates the template in `do`.

### Inapplicable instructions

- Database change documentation, function signatures, documentation-only boundaries, one-shot transition rules.

### Uncertain assessments

- Headless WebKit: the test harnesses support `REPLY_BROWSER=webkit`, but no Playwright browser cache was found in this environment. The plan says "WebKit where installed"; Step 4 must report which engines actually ran.
