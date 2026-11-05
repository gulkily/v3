> **Feature plan:** [Step 1](./adaptive_nav_step1_solution_assessment.md) · [Step 2](./adaptive_nav_step2_feature_description.md) · [Step 3](./adaptive_nav_step3_development_plan.md) · [Step 4](./adaptive_nav_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/adaptive_nav`; [host ledger](../effort_estimation_trial.md#cycle-v3adaptive_nav). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.85

Approved direction: Step 2 v2, approved 2026-11-05 ("Approved step 2").

## Completion Contract

- Normal entry: a reader opens any ZenMemes page in the app layout, on a phone or a desktop.
- End-to-end outcome: desktop shows Board, Recent, Calendar (when on), Users, Messages (members), Search, About and Tools in the top bar; You keeps Profile, Account, Invite and theme controls. A phone shows at most five tabs: Board, Calendar (when on), New post, personal slots and More. After a few visits to one destination through More, it earns a slot and keeps it. Pinning from More locks a slot.
- Required recovery: unpin from More; cleared storage gives the default bar; every destination stays reachable through More; a feature-flag nav refresh keeps the slots.
- Deployment/external verification: the maintainer checks the deployed site on his iPhone (Stage 8). No schema, API, cookie, dependency or Apache change.
- Release condition: smoke set plus changed tests pass; browser checks at 375px and 1280px in light, Word97, Chicago and Midnight show at most five tabs and zero layout shift after first paint; the iPhone check is recorded.

## Key Risks

- **High risk: slots jump after paint.** Impact: tabs move under the thumb. Early validation: Stage 4 extends `viewer_nav_prepaint.mjs` before any slot rule lands. Mitigation: the server renders every slottable tab hidden by CSS; the head script reveals the chosen ones, the same way `data-member-only` works today.
- **Nav refresh drops slots.** Impact: slots vanish after a feature-flag change until reload. Early validation: Stage 6 replays the toggle with a filled slot. Mitigation: `feature_flags.js` reruns slot placement after swapping the nav.
- **Members miss messages.** Impact: no unread signal on phones when Messages is not slotted. Early validation: Stage 3 asserts the indicator on More. Mitigation: More carries the unread marker; `private_message_unread` script updates both.
- **A slot fills with a destination the viewer cannot use.** Impact: an unapproved viewer sees a Messages tab. Mitigation: slot placement skips `data-member-only` items unless `data-member="approved"`.
- **Cycle exceeds one day.** Mitigation: Stages 1–3 deliver the fixed layout on their own; Stages 4–7 can split into a follow-up cycle on the same branch.

## Stage 1 - Desktop bar and menu split

**Estimate: 0.08**

- Goal: desktop top bar shows every main destination; You keeps only personal items.
- Dependencies: none.
- Expected changes: `TemplateRenderer` placement step grows a desktop placement (`main`, `menu`) separate from phone placement; Search, Calendar, About and Tools become `main`. `nav.php` and `app_header.php` read both. `site.css` widens the desktop bar.
- Verification approach: `AppNavigationPlacementTest` asserts the new split; smoke set; `nav_active_theme_styles.mjs` at 1280px.
- Estimate details: driver: two placements from one list; uncertainty: low.
- Risks or open questions: the desktop bar wraps on narrow laptops. Validation: 1024px screenshot. Mitigation: allow wrapping or shrink gaps.
- Canonical components/API contracts touched: `navItems()`/`navigationState()` in `src/ForumRewrite/View/TemplateRenderer.php` (extend); `templates/partials/nav.php`, `templates/partials/app_header.php` (extend); `public/assets/site.css` (extend).

## Stage 2 - Fixed phone bar and More

**Estimate: 0.12**

- Goal: phones show Board, Calendar (when on), New post and More; More opens full-screen with every other destination and theme controls.
- Dependencies: Stage 1.
- Expected changes: phone placement marks Board, Calendar and New post `bar`, everything else `more`. The You disclosure becomes More on phones (label and icon change by width in CSS; it stays You on desktop). `site.css` gives the open panel full height on phones. `tab_bar_scroll.js` already keeps the bar while the menu is open.
- Verification approach: `AppNavigationPlacementTest` for phone placement; `tab_bar_scroll.mjs` and `viewer_nav_prepaint.mjs` tab-count assertion lowered to at most five at 375px.
- Estimate details: driver: one disclosure serving two layouts; uncertainty: low.
- Risks or open questions: a full-screen panel covers the bar's own close affordance. Validation: 375px screenshot. Mitigation: panel keeps a visible Close row at the bottom.
- Canonical components/API contracts touched: `app_header.php`, `account_menu.js` (reuse), `site.css`, `tab_bar_scroll.js` (unchanged, verified).

## Stage 3 - Unread indicator on More

**Estimate: 0.08**

- Goal: members see the unread marker on More when Messages has no tab.
- Dependencies: Stage 2.
- Expected changes: More trigger carries a second `data-private-message-unread` target; the unread script updates every target, not the first.
- Verification approach: `nav_unread_outline.mjs` extended at 375px; `private_message_live_navigation.mjs` still passes.
- Estimate details: driver: a second target for an existing script; uncertainty: low.
- Risks or open questions: double announcement for screen readers. Mitigation: only the visible target carries `aria-describedby`.
- Canonical components/API contracts touched: `public/assets/private_message_unread.js` (extend), `app_header.php`.

## Stage 4 - Slot markup and pre-paint placement

**Estimate: 0.14**

- Goal: slot tabs exist in the HTML for every viewer, hidden by default, and the head script reveals the saved ones before first paint.
- Dependencies: Stage 2.
- Expected changes: `nav.php` renders every `more` destination once more inside the bar with `data-slot`, hidden by a CSS rule. The head script in `layout.php` (and `standalone_layout.php`) reads `forum_nav_slots` from local storage, skips member-only items unless approved, and sets `data-slot-active` on at most the free slot count (two, or one while Calendar shows). A new `nav_slots.js` owns the storage format: a JSON object of section, pinned flag and visit count, as internal browser state (CONTRIBUTING.md allows JSON for internal state; `invite_issuance.js` and `reply_continuation.js` already do this).
- Verification approach: `viewer_nav_prepaint.mjs` seeds storage and asserts zero shift and identical HTML across viewers; `AppNavigationPlacementTest` asserts slot markup is viewer-independent.
- Estimate details: driver: pre-paint placement and storage format; uncertainty: medium.
- Risks or open questions: duplicated destinations confuse screen readers. Mitigation: hidden slots use `hidden` semantics, not just `display: none`.
- Canonical components/API contracts touched: `templates/layout.php` head script (extend), `templates/standalone_layout.php` (parity), `nav.php`, `site.css`, new `public/assets/nav_slots.js` (no existing script owns per-viewer nav state).

## Stage 5 - Filling and pinning

**Estimate: 0.14**

- Goal: a slot fills after repeated More visits; pinning locks it.
- Dependencies: Stage 4.
- Expected changes: `nav_slots.js` counts a More click per destination; after three visits a free slot fills with the leader. A filled slot changes only when another destination leads it by three or more visits across at least two days. Each More row gets a pin control; pinned items occupy slots first.
- Verification approach: a new `nav_slots.mjs` drives clicks and clock, asserting fill, stickiness and pin precedence; smoke set.
- Estimate details: driver: the stickiness rule; uncertainty: medium, thresholds may change after the iPhone check.
- Risks or open questions: thresholds feel too slow or too twitchy. Validation: maintainer's use in Stage 8. Mitigation: thresholds live in one constant.
- Canonical components/API contracts touched: `nav_slots.js`, `app_header.php` (pin control in More rows), `site.css`.

## Stage 6 - Slots survive nav refresh

**Estimate: 0.08**

- Goal: a feature-flag change keeps slots in place.
- Dependencies: Stage 5.
- Expected changes: `feature_flags.js` calls `nav_slots.js` placement after it swaps `nav.nav`; the nav fragment carries the slot markup.
- Verification approach: `railspike_auth_navigation.mjs` or a new case toggles messaging with a filled slot and asserts the slot stays.
- Estimate details: driver: one hook in an existing refresh; uncertainty: low.
- Risks or open questions: the refresh runs before `nav_slots.js` loads. Mitigation: placement is idempotent and runs on a `forum:nav-refreshed` event.
- Canonical components/API contracts touched: `public/assets/feature_flags.js` (extend), `nav_slots.js`.

## Stage 7 - Icons, concepts and docs

**Estimate: 0.08**

- Goal: icon themes show glyphs for More and pin; the concept inventory describes slots.
- Dependencies: Stages 1–6.
- Expected changes: `theme-nav-icons.css` adds More and pin glyphs; new `docs/concepts/navigation_slots.md`; update `theme_navigation_icons.md`; UI copy checklist note.
- Verification approach: `nav_active_theme_styles.mjs` asserts a non-empty glyph on More in three themes; `git diff --check`; link check.
- Estimate details: driver: glyph drawing; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `public/assets/theme-nav-icons.css`, `docs/concepts/`.

## Stage 8 - Release check and iPhone replay

**Estimate: 0.13**

- Goal: the Completion Contract holds on the deployed site.
- Dependencies: Stage 7, deployed by the maintainer.
- Expected changes: Step 4 summary records the check; ledger closeout.
- Verification approach: browser matrix at 375px and 1280px in four themes, zero shift; the maintainer uses More a few times on his iPhone and reports the bar, the earned slot and a pin. A headless pass alone leaves this stage pending.
- Estimate details: driver: a matrix run plus a wait on the maintainer; uncertainty: medium.
- Risks or open questions: thresholds need tuning after the replay. Mitigation: a threshold change is an in-scope refinement of Stage 5.
- Canonical components/API contracts touched: none new.

## Concepts touched

- [Theme navigation icons](../../concepts/theme_navigation_icons.md): changed (Stage 7).
- [Calendar](../../concepts/calendar.md): used unchanged.
- [Tools launcher](../../concepts/tools_launcher.md): used unchanged.
- *Navigation slots*: created (Stage 7).

## Study checkpoint

2026-11-05T05:05:00Z, `railspike-effort` / `v3/adaptive_nav`, Step 3 handoff. Scope v2 unchanged. Starting revision `a24e16df`, R1/P2; R1 hashes not re-verified. Single exposed Claude rater, now also exposed to `feature_flags.js`, the tab bar CSS and the existing browser checks. Whole-cycle projected total **0.85 reaffirmed**. Stage work originals 0.08 / 0.12 / 0.08 / 0.14 / 0.14 / 0.08 / 0.08 / 0.13, non-additive. No implementation, measured labor or accepted outcome.

## Enhancement Requests

- Request (2026-11-05, maintainer): "Would it be easier to use JSON here?" for slot storage. Disposition: in-scope refinement of Stage 4; JSON is the project's stated format for internal state, and browser-built parsing removes a hand-written parser. Approval status: adopted in this revision. Outcome: Stage 4 text updated above.
- Request (2026-11-05, maintainer): a planned local-storage editor with a JSON view will make this state easy to inspect by hand. Disposition: separate work, feedback item [PF-20261104-F18](../../feedback/items/PF-20261104-F18.txt); this cycle keeps the slot object flat and self-describing (section, pinned, visits, last visit date) so a generic JSON editor renders it without special handling. Approval status: noted, no approval needed. Outcome: follow-up link retained here.

*— Claude · 2026-11-05 UTC · Model: claude-fable-5-1*
