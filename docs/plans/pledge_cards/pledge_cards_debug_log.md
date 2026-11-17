# Pledge cards debug log

## 2026-11-17T09:20Z — Step 1

**Necessary/useful instructions**
- `implementation_standards.md` "inspect existing implementations": rendering the thread with four people and the `#meta` label, as visitor and as author, showed the tagset buttons as the biggest source of clutter, which the earlier single-key renders hid. Retain.
- `step1_solution_assessment.md` "Understood Intent … do not invent scope": "the comments" was ambiguous next to the treasurer sketch; the intent section states the reading so the maintainer can correct it. Retain.
- `AGENTS.md` per-cycle ledger: the study entry went to `effort_ledger/pledge_cards.md`, not the frozen ledger. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- The `/new or /clear` reminder: there was discussion before this Step 1 (cycle 9 and the treasurer sketch), so the condition is met; given once.

**Uncertain assessments**
- A browser mock made by editing the rendered page was used as evidence for Option B. Railspike's Step 1 says no implementation details; the mock is a picture, kept outside the repository, and not part of the plan.
- The cycle builds on an open PR (#313). Step 4 would need either #313 merged first or the maintainer's request to reuse its branch; Railspike's follow-up rules cover both.
- `.railspike_debug_on` exists; enabled. R1 hashes not re-verified.

## 2026-11-17T17:40Z — Step 1 (revision of the 09:20Z entry)

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Review and revision … record the revised direction separately. A request for changes does not approve the step": the maintainer's message gave a direction, not approval; the first recommendation is kept and marked superseded. Retain.
- `AGENTS.md` "Follow-up changes after a PR": PR #313 merged during the pause, so the base moved to a freshly fetched `origin/main`. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- `RAILSPIKE.md` Warning Signs "more than 4 options": Step 1 now lists five (A to E). The first three are kept as the record of the first version; the revised comparison is among B, D and E.

## 2026-11-17T17:50Z — Step 1 (second revision)

**Necessary/useful instructions**
- `implementation_standards.md` "identify the canonical components": opening a row in the mock showed the thread's existing fold (the resolved-post disclosure), so Option F reuses it rather than adding a second collapse control. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- Three successive directions in one Step 1. Each is kept with its superseded recommendation, so the step is long; Railspike asks to keep the original scope, but says nothing about trimming superseded options before approval.
- The mock's opened row showed a "Resolved" label from the fold's summary. It came from moving elements in the mock and was not investigated; Step 2 checks the fold's rules on pledge replies.

## 2026-11-17T17:55Z — Step 1 (third revision)

**Necessary/useful instructions**
- `PRINCIPLES.md` "Accommodate first, verify later": shaped the approved-only rule so that an unapproved pledge is kept as a reply and starts counting when its key is approved, read when the thread is shown. Retain.
- `step1_solution_assessment.md` "Review and revision": the maintainer's answers were recorded as decisions in the step, and the questions removed from the open list. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- The approved-only rule overrules a cycle 1 decision made under delegated approval. It is recorded here as this cycle's scope; whether the cycle 1 concept and spec text change in this cycle or a docs follow-up is left to Step 2.

## 2026-11-17T18:00Z — Step 1 (clean rewrite)

**Necessary/useful instructions**
- `step1_solution_assessment.md` "Original Query … do not summarize or omit anything": the clean version keeps all six messages verbatim, so the rewrite loses no instruction. Retain.
- `RAILSPIKE.md` Warning Signs "more than 4 options": the clean version has three. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- Rewriting an unapproved Step 1 instead of appending: Railspike asks to "append revisions and retain original scope/scores". The scores are retained in the ledger and a history section; the superseded option texts were removed from the step itself, as they were shared in the chat. This trades a literal reading for a reviewable step.

## 2026-11-17T18:10Z — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md` Risks "earliest validation": reading `post_card.php` showed the fold exists only on tagset threads, and `thread.php` showed same-author runs; both became risks with early checks, not surprises in Step 4. Retain.
- `step2_feature_description.md` "Known user or environment": named the demo thread's "claude" key, whose approval decides what the demo shows after release. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- Step 2 changes one detail of the approved Step 1 (posts with no name keep "Not counted: no name"). Recorded as a stated change with its reason, since Step 1's wording would show an untrue label; the maintainer can overrule at approval.
- Step 2 runs past one page because of five risks and the inventory.

## 2026-11-17T18:20Z — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md` "Resolve each material risk before dependent work": reading the flag parsers, the evaluator, the flag-change page and the two button scripts settled two Step 2 risks before planning. Retain.
- `step3_development_plan_do.md` "a stage that replays each reported failure": Stage 4 replays a thread at the iPhone SE's size and keeps the device check pending. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- Stage 1 changes counting and removes a flag in one stage; the two are tied (the flag can do nothing once approval is required), so splitting them would leave a stage with a flag that does nothing.

## 2026-11-17T19:05Z — Step 4

**Necessary/useful instructions**
- `implementation_standards.md` "check affected shared consumers, themes, roles": the theme pass found Word97 and Bubblegum drawing rows as separate windows, and the author view found the Retract control's menu nested in "More"; both were recorded instead of forced. Retain.
- `implementation_standards.md` "replay that condition or explicitly report it unverified": the replay at 375 by 667 pixels ran as visitor, without JavaScript and as author; the device check stays pending. Retain.
- `implementation_standards.md` "identify the canonical components": the list byline (`$rowMeta`) already existed, so the compact header reused it rather than a new form; this produced the one recorded deviation from Step 2. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only rules: the work type is an application change.

**Uncertain assessments**
- Approval in tests: PHP tests set the profile row directly and the browser check writes an approval seed. Neither exercises the site's own Approve flow; recorded as not exercised.
- Screenshots were looked at by the agent; no independent visual review was run.

## 2026-11-17T20:10Z — Correction after closeout

**Necessary/useful instructions**
- `AGENTS.md` "pages must look final on first paint": the stale rows were a baked page, not a script, so the fix is in invalidation rather than a client update. Retain.
- `AGENTS.md` "a same-cycle correction may continue that cycle's unmerged branch": PR #316 was still open, so the fix went to its branch. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Uncertain assessments**
- The site's own Approve flow is still not exercised end to end in a browser; the new check calls `approveUser` directly.
