# No-JavaScript basic access — Railspike debug log

## 2026-10-19T17:22:01Z — Step 1

### Necessary/useful instructions

- `step1_solution_assessment.md`, Structure: comparing approaches clarified that guest continuity and interface resilience are separate decisions. Retain.
- `step1_solution_assessment.md`, Next: the approval gate keeps this request to assessment; no Step 2 or application changes were made. Retain.
- `RAILSPIKE.md`, Debug state: root marker presence resolved enabled mode, recorded in the assessment. Retain durable state.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- Step 4 branching, organization, and commit requirements do not apply to this unapproved Step 1 draft. Planning files remain uncommitted.

### Uncertain assessments

- The one-page limit does not specify rendering. The assessment is concise Markdown; exact printed pagination is unknown. Consider a word budget if literal pagination matters.

## 2026-10-19T17:23:27Z — Step 2

### Necessary/useful instructions

- `step2_feature_description.md`, Known user or environment and Completion boundary: requiring actual disabled-script entry and error recovery prevents treating native form markup as completed browser support. Retain.
- `step2_feature_description.md`, Shared component inventory: existing layout, board, forms, handlers, and access gates provide a shared surface rather than requiring a second interface. Retain.
- `RAILSPIKE.md`, approval and debug recovery: inherited enabled state and recorded Step 1 approval; Step 2 remains uncommitted and no Step 3 was opened. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- Documentation-only scope restrictions do not apply to this proposed application feature.

### Uncertain assessments

- The concise-document requirement and extensive required fields compete for space; physical page count remains rendering-dependent. Consider a word budget.

## 2026-10-19T18:27:29Z — Step 3

### Necessary/useful instructions

- `step3_development_plan_before.md`, Before Checklist: confirming Step 2 approval prevented treating the premature Step 3 message as approval of an unwritten artifact. Retain.
- `step3_development_plan_do.md`, Completion Contract and known environment acceptance: actual Chromium with JavaScript disabled reproduced blocked inline disclosure, successful standalone guest thread/reply posting, retained drafts after rejected image-reference text, correction/success, and protected HTTP 401 with misleading reconnecting text. Those findings focus stages on native interaction and truthful recovery. Retain browser-specific evidence requirements.
- `step3_development_plan_do.md`, risks before dependent work: inspecting BoardPageController exposed that pagination only exists for QDB. The plan explicitly calls out new default-forum pagination to satisfy approved Step 2, rather than claiming an existing paginator merely needs repair. Retain.
- `step3_development_plan_after.md`, stage sizing and approval: five bounded stages, uncommitted draft, no Step 4 opened. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- Documentation-only verification does not apply; this is application work.
- Planned signature/schema changes are unnecessary: existing controller/helper contracts support the intended work.

### Uncertain assessments

- `step3_development_plan_do.md` requests Completion Contract before Key Risks, while `RAILSPIKE.md` says Step 3 begins with Key Risks. The plan follows the detailed file's explicit order and places risks immediately after the contract. Clarify the entrypoint to say risks belong near the top.
- One-page rendering remains unspecified; this plan is 679 words with flat stage fields. A word budget would make the constraint reproducible.

### Baseline evidence boundary

- Temporary local fixture and browser probe, outside the project, used Chrome `/opt/google/chrome/chrome` through Playwright with `javaScriptEnabled:false`; records, read model, sessions, and caches were isolated and cleaned.
- Final probe passed native New Post entry, guest thread creation, Reply-link entry, guest reply creation, both validation corrections, and observed protected denial. Clicking the inline prompt left its form invisible. Pagination was absent; source confirmed it is QDB-only. Reload persistence, full navigation/theme coverage, and production behavior were not claimed as tested.
- An earlier probe used a non-recognized image marker and consequently did not trigger validation; corrected to `Image-v1:` before recording validation results. No application files were changed by probes.

## 2026-11-16 — Step 3 revision

Mode: enabled, inherited from the 2026-10-19 entries.

### Necessary/useful instructions

- `step3_development_plan_before.md` "Confirm Step 2 is approved": the user's "approved step 2" matched no pending gate (Step 2 was approved 2026-10-19; the related Windows ME cycle is at Step 1). It was read as reconfirming nojs Step 2 and authorizing the Step 3 rewrite, and explicitly not as Windows ME Step 1 approval.
- The old draft's own note, "Revalidate against current code before resuming": with 1,124 intervening commits, a live baseline overturned three of five draft stages (pagination landed, inline composer fixed via `@media (scripting: enabled)`, posting covered by `composers.mjs`).
- Effort "Existing cycles join at their next checkpoint without invented earlier scores" plus the extended-mode legacy rule: a first snapshot at the Step 3 handoff with extended estimates disabled.
- AGENTS.md "replay that condition or report it unverified": kept the members-only "Reconnecting" replay and the Lynx posting replay as acceptance evidence.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Schema and signature planning: no changes needed.

### Uncertain assessments

- Running the browser checks needed `node_modules`. The pinned `playwright-core@1.58.1` (declared devDependency) was copied from a sibling checkout instead of fetched; it is gitignored and adds no new dependency.
- `board_compose_focus.mjs:61` times out on two runs on unmodified `main`. The cause is not yet known; Stage 1 diagnoses it.
- Moving "old-engine-safe hiding" out of this cycle reverses what I told the user one turn earlier. The fresh baseline shows modern no-script hiding already works, so the old-engine technique belongs to the Windows ME cycle.

### Baseline evidence boundary

- Chromium (`/opt/google/chrome/chrome`) through Playwright with scripts off, against the local dev server (`127.0.0.1:8000`, this checkout). Read-only probe plus one failed-validation submit, which wrote nothing. Probe scripts live in the session scratchpad, not the repo.
- Pagination and members-only denial were not exercised in a browser. Stage 1 seeds them.
