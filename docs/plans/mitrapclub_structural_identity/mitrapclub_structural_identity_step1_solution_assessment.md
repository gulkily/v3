> **Feature plan:** [Step 1](./mitrapclub_structural_identity_step1_solution_assessment.md) · [Step 2](./mitrapclub_structural_identity_step2_feature_description.md) · [Step 3](./mitrapclub_structural_identity_step3_development_plan.md) · [Step 4](./mitrapclub_structural_identity_step4_implementation_summary.md)

## Original Query

Please write Step 1 for the next change.

## Understood Intent

- "The next change" is read as **Cycle 3 ("Visual identity extension")** from `docs/plans/mitrapclub_theme_and_features_checklist.md` — the next unstarted item after Cycles 1-2 (both done, unmerged to `main`).
- Cycle 3 as originally bucketed bundles five different items: nav relabel/trim, theme-freedom decision, per-profile favicon/icon (deferred here from Cycle 1), display typeface, and a hero/banner treatment. Those last two are open-ended visual design work; the first three are mechanical config changes with direct precedent (qdb already trims its nav and uses its own theme). This Step 1's real uncertainty is **how much of that bucket to take as one feature** — the options below address that, not just "which design to pick."

## Problem Statement

Cycle 3's "visual identity extension" bucket mixes low-risk, precedented config changes (nav, theme permissions, favicon) with open-ended creative design work (hero banner, display typeface), risking a slice too large for one Step 3 plan.

## Solution Options

- **Option A: One bundled feature.** Do nav relabel/trim, the theme-freedom decision, per-profile favicon/icon, display typeface, and a hero/banner treatment all in one feature.
  - Pros: ships the whole checklist bucket at once; no bucket-splitting overhead.
  - Cons: likely exceeds the day/8-stage guardrail; forces an open-ended creative task (hero banner design) onto the same timeline as three mechanical config edits.
- **Option B: Split by kind of work.** Ship the structural half now — nav relabel/trim, theme-freedom decision, per-profile favicon/icon — as this feature. Treat the creative half (hero banner, display typeface, overall visual motif) as a separate, later feature once there's room to give it real design attention.
  - Pros: this feature stays a clean, fast, low-risk slice matching Cycles 1-2's shape; each of the three items already has a working precedent (qdb) to copy; doesn't rush the creative work.
  - Cons: one more cycle to track; nav and the eventual hero banner ideally get designed with each other in view (minor — nav/theme-freedom don't block or constrain a later banner design).
- **Option C: One item at a time.** Take just the single clearest item (nav relabel/trim, closest qdb precedent) as this feature; leave theme-freedom, favicon, typeface, and hero-banner as separate future features one at a time.
  - Pros: smallest possible slice, lowest risk per cycle.
  - Cons: three to four near-identical-effort cycles for items that are each small enough to naturally group; slower overall with no real benefit over Option B.

## Recommendation

**Option B.** The three structural items (nav, theme-freedom, favicon) are small, precedented, and genuinely vertical on their own — bundling just those stays inside the day/8-stage guardrail while still clearing most of Cycle 3's checklist. The creative half (hero banner, typeface) is different in kind — it deserves its own Step 1 once there's appetite to actually design it, rather than being squeezed in alongside config edits.

**Vertical-slice viability:** Yes. Entry is a visitor on `mitrapclub`; outcome is a trimmed, club-specific nav, a settled answer on whether other app themes stay selectable, and a club-branded favicon/PWA icon — all independent of each other and of the deferred creative work; no regression to `zenmemes`/`chouse`/`qdb`.

Waiting for "Approved Step 1" before drafting Step 2.
