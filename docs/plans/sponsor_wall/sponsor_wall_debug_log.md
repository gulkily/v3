# Railspike debug log — sponsor_wall

## 2026-11-27 UTC — Step 1

**Necessary/useful instructions**
- `AGENTS.md`, feedback search before a plan: ran it; there's no matching item, and the result is recorded.
- `RAILSPIKE.md`, Extended estimates: per-option estimates made it easy to compare A against B and C. Retain.

**Redundant/unnecessary instructions**
- `RAILSPIKE.md`, the effort-study recovery rules (frozen-reference hashes): heavy for a short Step 1 in a fresh clone, and the hashes were not re-verified. Suggest one line saying a fresh clone may record "not re-verified" and move on. Clarify.

**Inapplicable instructions**
- Step 4 ledger and commit cadence: no commit in Step 1.

**Uncertain assessments**
- Whether an epic checklist was expected alongside this Step 1 (the maintainer asked for "an epic checklist plus Step 1" on 2026-11-26 in another chat). No checklist is in `origin/main`, so this cycle sits under `pledges_checklist.md` for now.

## 2026-11-27 UTC — Step 2

**Necessary/useful instructions**
- `step2_feature_description.md`, Shared component inventory: led straight to the guestbook as the pattern for a page that names one thread, and that moved the form onto the page. Retain.
- `step2_feature_description.md`, Known user or environment: kept the iPhone SE release check honest. Retain.

**Redundant/unnecessary instructions**
- The "≤1 page" deliverable limit, next to eleven required sections: this doc runs past a page with every section kept short. Suggest dropping the page limit or marking some sections optional. Clarify.

**Inapplicable instructions**
- Documentation-only scope fields: this is an application change.

**Uncertain assessments**
- Moving the guest username prompt out of this cycle is a scope call made under "plans kept simple"; flagged in the doc for the maintainer.

## 2026-11-27 UTC — Step 3

**Necessary/useful instructions**
- `step3_development_plan_do.md`, "Resolve each material risk before dependent work begins": checking the stale-page risk before planning a stage for it showed `/support/` is never baked, which removed a stage's worth of work. Retain.
- Canonical components as their own bullet: kept the guestbook reuse visible in every stage. Retain.

**Redundant/unnecessary instructions**
- No supported findings.

**Inapplicable instructions**
- Documentation-only alternatives in the Completion Contract.

**Uncertain assessments**
- The "≤1 page" limit again: four stages with every required field run past a page.

## 2026-11-27 UTC — Step 4

**Necessary/useful instructions**
- `step4_implementation_do.md`, Stage Boundary Protocol: committing per stage with the summary kept the screenshot findings (link color, block edges, full-width presets) attached to the stage that fixed them. Retain.
- `step3_development_plan_do.md`, a stage that replays the known user's condition: led to running the browser check at the reported 375 by 667 size, rather than only the theme sweep. Retain.

**Redundant/unnecessary instructions**
- `step4_implementation_before.md`, "Do not add the ledger or summary to the planning commit" next to the effort section's "record the entry projection there": the order (commit, then summary, then entry projection) is right but takes two readings. Suggest one sentence listing the order. Clarify.

**Inapplicable instructions**
- Documentation-only verification at stage boundaries: this is an application change.

**Uncertain assessments**
- Whether adding `SupportWallTest` to the smoke set was expected. CONTRIBUTING asks for one fast test per new main path; it runs in about 0.01s.
