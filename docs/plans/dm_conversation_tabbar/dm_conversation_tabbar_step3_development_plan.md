# DM conversation tab bar Step 3 development plan

> **Feature plan:** [Step 1](./dm_conversation_tabbar_step1_solution_assessment.md) · [Step 2](./dm_conversation_tabbar_step2_feature_description.md) · [Step 3](./dm_conversation_tabbar_step3_development_plan.md) · [Step 4](./dm_conversation_tabbar_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/dm_conversation_tabbar`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.30**

Concepts touched: [Navigation slots](../../concepts/navigation_slots.md) (one line: conversation pages show no phone bar).

## Completion Contract

- Normal entry: Messages → a conversation, at phone width.
- End-to-end: no tab bar from first paint; composer at the bottom edge above the safe area; back arrow returns to Messages, where the bar shows.
- Recovery: none new; the bar-free offset is the one already used when the bar scrolls away.
- Deployment: ordinary deploy, no data or flag changes.
- Release: maintainer confirms on iPhone Safari (Stage 4).

## Key Risks

- **High risk:** a bottom-pinned element in some theme still reserves the bar's height or sits under the composer. Early validation: Stage 2 screenshots of every theme at 375px. Mitigation: all positions go through `--app-tabbar-offset`, set to the safe area only.
- `tab_bar_scroll.js` toggling the bar back. Validation: Stage 1 browser check after scrolling and focusing the composer. Mitigation: the script does nothing when the page opts out.
- Losing the account menu inside conversations (it lives in the bar on phones). Accepted per Step 1; the back arrow leads to pages that have it.

## Stage 1

**Estimate: 0.10**

- Goal: conversation pages render without the phone tab bar.
- Dependencies: none.
- Expected changes: `renderPageTemplate` passes a page-level `tabBar` setting to the layout; the conversation controller sets it to none; `layout.php` marks the shell `data-tabbar-page="none"`. Phone CSS in `site.css` hides `.site-header-actions` for that marker, drops the main area's bar padding, and sets `--app-tabbar-offset` to the safe area. `tab_bar_scroll.js` exits early on that marker.
- Verification approach: browser check at 375px: no visible bar on a conversation after load, scroll and composer focus; bar present on the Messages list; composer bottom at the screen bottom; back arrow returns to Messages. PHP check that only the conversation page carries the marker.
- Estimate details: driver: one marker through renderer and layout; uncertainty: other rules keyed to the bar's height (`site.css` lines using `--app-tabbar-height`).
- Risks or open questions: rules using `--app-tabbar-height` directly (popovers) would still reserve space. Mitigation: override that variable too under the marker.
- Canonical components/API contracts touched: `TemplateRenderer::renderPageTemplate`, `templates/layout.php` shell, `site.css` phone tab bar block, `tab_bar_scroll.js`, `PrivateMessagePageController::conversation`.

## Stage 2

**Estimate: 0.10**

- Goal: every theme lays out the bar-free conversation correctly.
- Dependencies: Stage 1.
- Expected changes: only if a theme breaks; otherwise none.
- Verification approach: 375px screenshots of a conversation in each theme (Gloss, Light, Dark, Word97, Chicago, Midnight, Engagify and the rest present in `public/assets/theme-*.css`), checking composer position and that nothing floats where the bar was. `private_message_redesign.mjs` still passes.
- Estimate details: driver: number of themes; uncertainty: Gloss's own bottom rules (`theme-gloss.css` uses `--app-tabbar-offset` in several places).
- Risks or open questions: see Key Risks.
- Canonical components/API contracts touched: theme files, only if needed.

## Stage 3

**Estimate: 0.05**

- Goal: record the change and run the project checks.
- Dependencies: Stages 1–2.
- Expected changes: note in the navigation slots concept; UI checklist entry.
- Verification approach: smoke set plus changed PHP tests; messaging browser checks through `checkHistoryRecovery`; `git diff --check`; final diff review for duplicated offset rules.
- Estimate details: driver: routine; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/navigation_slots.md`, `docs/plans/ui_copy_audit_checklist.md`.

## Stage 4

**Estimate: 0.05**

- Goal: replay the reported condition on iPhone Safari.
- Dependencies: PR merged and deployed.
- Expected changes: none; acceptance evidence only.
- Verification approach: maintainer opens a conversation on iPhone Safari and confirms no tab bar, composer at the bottom, back arrow works. Desktop or emulated evidence does not count.
- Estimate details: driver: coordinating the check; uncertainty: Safari's safe-area handling.
- Risks or open questions: if the composer collides with Safari's toolbar, adjust the safe-area offset.
- Canonical components/API contracts touched: none.

## Study snapshots

- Step 3 handoff, 2026-11-14 UTC: 0.30 reaffirmed; stage sum also 0.30. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-14 UTC · Model: Claude Opus 5.5*
