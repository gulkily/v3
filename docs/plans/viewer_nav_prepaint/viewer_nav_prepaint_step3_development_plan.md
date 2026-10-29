> **Feature plan:** [Step 2](./viewer_nav_prepaint_step2_feature_description.md) · [Step 3](./viewer_nav_prepaint_step3_development_plan.md) · [Step 4](./viewer_nav_prepaint_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/viewer_nav_prepaint; [ledger](../effort_estimation_trial.md#cycle-v3viewer_nav_prepaint). Standing P2 enrollment.
Extended estimates: enabled
Approval: Step 2 v3 approved 2026-10-29; Step 3 (this revision) approved 2026-10-29 (“Approved Step 3”, given before Step 2 v3 and confirmed standing once it was approved). Step 1 skipped. Waiting tab: [option A chosen](./viewer_nav_prepaint_step2_feature_description.md#open-decision-waiting-tab) 2026-10-29 (count in shared HTML; follow-ups in `todo.txt`).

**Cycle estimate: 0.50**
Revised from 0.40 for scope v3 on `main` `c5783108`.

## Completion Contract

- Normal entry/outcome: approved member browses Board → thread → About → Users → Tools with high-traffic mode on/off; Messages (main nav / phone tab bar) and Invite (account menu) have their final visibility and position at first paint, in the app shell and the forum header. Anonymous/unapproved visitors have neither and no gap. Server header HTML is identical across viewers for the same route/configuration; qdb omits Invite and disabled messaging omits Messages. With Stage 4, `/users/` tabs follow the same rule.
- Recovery: existing authentication/status responses, sign-out and identity changes update local approval and the menu in place; unchanged status causes no visible mutation. Stale approval never grants route access. Unread state works on cached pages without changing button size/caption.
- Release: real-browser first-paint and transition evidence, phone/desktop overflow and Word97/Chicago/Midnight icon checks, focused regressions and updated UI audit. Include standalone layout where it renders affected UI. Deployment/physical-device claims require separate evidence; no deployment is authorized.

## Key Risks

- **High risk: incorrect identity memory** could display another identity's links. Bind approval to the identity `PrivateSiteAuth` already snapshots; ignore responses that fail its current-identity check, clear on `forum:identity-changed`, sign-out and 401. Network failures are not revocations. Server authorization unchanged.
- **High risk: late hiding.** The hide rule must be in the inline critical CSS and beat the tab bar's `display: flex`; the show decision must run in the existing head script before body paint, with hidden as the fallback for missing/blocked storage. Validate on phone before Stage 3.
- **On-demand messaging scripts** must not resize or re-caption Messages when they arrive, and must not load for other viewers. No new status polling or server personalization.
- Missing prior approval memory cannot prove membership at first paint: stay hidden until an existing response establishes approval, and record that as a real state change. No-JS members see no Messages/Invite (accepted in Step 2); direct routes keep authorization.

## Stage 1 — Remember approval for the current identity

**Estimate: 0.08**
Revised from 0.10: sign-in now has a current-identity check and change events to build on.

- Goal: existing sign-in/status/sign-out flows maintain accurate client approval memory.
- Dependencies: none; establish identity binding and response-race behavior before Stage 2.
- Expected changes: record approval from `authenticate_identity` `approved=` (in `PrivateSiteAuth`, after its current-identity check) and from `auth_status` 200/401 (`auth_navigation.js`); clear on `forum:identity-changed`, `clearBrowserIdentity` and cross-tab `storage` changes. One small client state contract next to the existing identity keys; no endpoint/cookie changes. Dispatch one event when the remembered state changes.
- Verification approach: focused auth/signing tests for approved/unapproved, 401, transport failure, missing/blocked storage, a delayed old-identity response and background (in-place) sign-in; confirm existing sign-in/recovery still works.
- Risks or open questions: `auth_status` has no identity field; capture identity when requesting and drop mismatched completions.
- Canonical components/API contracts touched: `private_site_auth.js`, `auth_navigation.js`, `browser_signing.js`; existing auth responses unchanged.
- Estimate details: S1/v2 stage work; driver is lifecycle wiring across three scripts, uncertainty is the background sign-in paths.

## Stage 2 — Render a static header with pre-paint visibility

**Estimate: 0.15**
Revised from 0.12: two render sites (main nav and account menu), the nav fragment, critical CSS and tab-bar layout.

- Goal: returning members see consistent Messages/Invite on live and cached pages, with real status transitions applied in place.
- Dependencies: Stage 1 identity/race checks pass.
- Expected changes: `navItems` always emits eligible Messages/Invite (hidden by default) without viewer tokens; `nav.php` and `app_header.php` mark them for the pre-paint rule; a hide rule in the inline critical CSS that wins over tab-bar/nav-link display; the head script sets one root attribute from Stage 1 state; same-visit updates toggle it. `navigationState` and the feature-flag nav refresh keep working, including the account-menu Invite it does not re-render today. Check `standalone_layout.php` parity; preserve active-section, qdb, members-only and feature-flag behavior.
- Verification approach: update LocalAppSmokeTest, HighTrafficTest and AppNavigationPlacementTest assertions; compare same-route header bytes for anonymous/unapproved/approved viewers; browser-check initial visibility, phone tab widths and unchanged-status stability before advancing.
- Risks or open questions: themes may override hiding or leave gaps in the tab bar or account menu; verify computed styles and positions, including no-JS/storage-failure fallback.
- Canonical components/API contracts touched: `TemplateRenderer` (`navItems`, `navigationItems`, `navigationState`, critical CSS), `nav.php`, `app_header.php`, `layout.php`, `standalone_layout.php`, `site.css`, Stage 1 client contract; reuse icon mappings.
- Estimate details: S2/v2 stage work; driver is shared rendering/timing across two headers, uncertainty is theme and tab-bar interaction.

## Stage 3 — Load messaging on demand and keep unread working

**Estimate: 0.12**
Revised from 0.08: messaging scripts now have to load from the client, since the server no longer knows who will see Messages.

- Goal: unread state and messaging availability work through the static menu on cached and live pages, and across sign-in/out, without resizing Messages.
- Dependencies: Stage 2 header and initial-paint checks pass.
- Expected changes: stop adding messaging scripts only when Messages was rendered; load them for remembered-approved members (reusing the script list `navigationState` already returns), and not for others. Resolve the unread viewer from local identity with existing normalization; start/stop unread with eligibility, invalidate stale responses on identity change, keep retry and messaging-disabled behavior.
- Verification approach: existing unread UI/API/live-navigation checks on cached and live pages; slow script and delayed unread responses, identity switch, sign-out, retry and feature-flag transitions; assert fixed caption/dimensions and no messaging downloads for anonymous visitors.
- Risks or open questions: username/token mismatch can reject valid unread payloads; verify against the existing API viewer contract before removing markup coupling. Pages that list messaging scripts for their own reasons (Messages itself) keep them.
- Canonical components/API contracts touched: `TemplateRenderer` script selection, `private_message_unread.js`, `private_message_availability.js`; unread API unchanged.
- Estimate details: S3/v2 stage work; driver is script loading and request lifecycle, uncertainty is existing availability listeners.

## Stage 4 — Users tabs follow the same rule

**Estimate: 0.05**
New in v3.

- Goal: `/users/` shows Members / Waiting from first paint for returning approved members and no tabs for others, cached or live.
- Dependencies: Stage 2 pre-paint attribute in place.
- Expected changes: the Users page always renders `users_tabs.php`, hidden by default via the Stage 2 rule; the Waiting (N) count comes from the shared render (option A). `/users/pending` stays approved-only.
- Verification approach: update the Users browser test and Users page assertions for anonymous/unapproved/approved, cached and live, phone/desktop.
- Risks or open questions: the count can go stale between static rebuilds and is visible in page source; both are tracked in `todo.txt`, outside this cycle. Note what refreshes the static copy when someone registers.
- Canonical components/API contracts touched: `ProfilePageController::directory`, `users.php`, `users_tabs.php`, `tests/browser/users_profile.mjs`.
- Estimate details: S4/v2 stage work; small template change on Stage 2's mechanism.

## Stage 5 — Verify the full browsing and recovery flow

**Estimate: 0.10**
Unchanged from original S4 0.10.

- Goal: demonstrate the reported cached/live inconsistency is resolved and record release limits.
- Dependencies: Stages 1–4 verified and committed.
- Expected changes: extend existing browser fixtures/checks; update `ui_copy_audit_checklist.md`, cycle summary/debug log and study ledger.
- Verification approach: Board → thread → About → Users → Tools, both traffic modes, desktop/phone, app shell and forum header, and Word97/Chicago/Midnight; record first-paint visibility and nav positions through load with delayed responses, not just final screenshots. Check sign-in/out (including background sign-in), approval/revocation, identity switch, empty storage, unchanged refresh, no-JS, qdb, disabled messaging, unread failure/retry, account menu open/closed, keyboard reachability and overflow. Run affected existing PHP/browser suites and inspect shared HTML for viewer leakage.
- Risks or open questions: layout-shift metrics alone miss pre-observer flashes; install observation before page scripts and pair it with rendered evidence. Unresolved required states block completion.
- Canonical components/API contracts touched: existing smoke/high-traffic/auth/unread/users tests and browser fixtures; approved documentation only beyond application scope.
- Estimate details: S5/v2 stage work; owns shared acceptance/closeout, uncertainty is fixture coverage; earlier stages own their focused verification.

## Inventory and study checkpoint

Dependency delta: zero additions/upgrades, packages, manifests or copied components; use existing tools. No schema, API, authorization, cookies, Apache or deployment changes. Keep planning uncommitted; after `Approved Step 3`, create a feature branch from current `main`, organize this cycle and make its planning commit, then one verified summary-bearing commit per stage.

Original checkpoint (v2): Step 3 handoff, 2026-10-27 UTC; scope v2, original baseline `2bfab452`, R1/P2. Codex single exposed rater. Whole-cycle 0.40 reaffirmed; stage-work originals S1–S4/v1 0.10 / 0.12 / 0.08 / 0.10 (Stage 1, 2, 3 and verification), never summed. Four stages planned, none complete.

Revision checkpoint (v3): 2026-10-29T18:37:06Z, Step 3 revision; scope v3, starting revision `c5783108`, R1/P2; all three frozen R1 hashes verified. Claude Code single exposed rater, exposed to the earlier Claude/Codex scores and to current renderer, header, auth, unread, script-loading, tab-bar and Users source. **0.50 whole-cycle projection** (option A; 0.45 had been the without-Waiting alternative), revised from 0.40: new understanding of the shell's two render sites and conditional script loading, plus the optional Waiting scope. Stage-work S1–S5/v2: 0.08 / 0.15 / 0.12 / 0.05 / 0.10, separate judgments, never summed; v1 originals above are retained. Five stages planned, none complete; no implementation, measured work or accepted outcome.

*— Codex · 2026-10-27 UTC*
*— Revised by Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5 (all stages rebased on `c5783108`; Stage 4 new)*
