> **Feature plan:** [Step 2](./viewer_nav_prepaint_step2_feature_description.md) · [Step 3](./viewer_nav_prepaint_step3_development_plan.md) · [Step 4](./viewer_nav_prepaint_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/viewer_nav_prepaint`; [ledger](../effort_estimation_trial.md#cycle-v3viewer_nav_prepaint). Standing P2 enrollment.
Extended estimates: enabled
Approval: Step 2 v2 approved 2026-10-27; v3 revision (rebased on `main` `c5783108`, Waiting option A) approved 2026-10-29 (“Approved Step 2 v3”). See [Revision v3](#revision-v3--2026-10-29-utc).
Related: [high-traffic mode](../high_traffic_mode/high_traffic_mode_step1_solution_assessment.md) (shared HTML, client-side identity placeholders) and [nav links load stability](../nav_links_load_stability/nav_links_load_stability_step2_feature_description.md) (Messages button keeps its size). Step 1 skipped: the user chose the approach in conversation.

**Cycle estimate: 0.50**
Revised from 0.40 (v2) for the app shell, on-demand messaging scripts and the Waiting tab (option A).

## Request

“Is the messages and invite buttons supposed to be consistent between different pages in high traffic mode?” → “Option 2 sounds good to me. We want to work to eliminate post-paint changes, not add more.” → “Can we decide entirely in client without making the server do the work? Otherwise the pages aren't entirely static?” → “In this case, a post-paint change would be acceptable.”

## Problem

Messages and Invite are rendered on the server only for approved signed-in viewers. High-traffic pages are rendered once with no viewer and shared, so members see the buttons disappear on cached pages and reappear on live pages.

Still true on `main` `c5783108`, and more visible: the ZenMemes app shell puts Messages in the main nav (a phone tab-bar slot) and Invite in the “You” account menu, so a whole tab comes and goes. The Users page's “Waiting (N)” tab (added 2026-10-28) is decided the same way, on the server for approved viewers only, so cached and live `/users/` differ too.

## User stories

- As an approved member, I want Messages and Invite in the same place on every page, cached or live, from the first paint.
- As a signed-out or unapproved visitor, I want a menu without those buttons and without an empty gap.
- As a member who signs in, signs out or changes status, I want the menu to reflect that right away.
- As an operator, I want every page's menu HTML identical for all viewers so pages stay fully static.

## Core requirements

- The server renders the same menu HTML for every viewer: Messages (when private messages are enabled) and Invite are always present but hidden by default, in whichever header the site uses (app shell: Messages in the main nav, Invite in the account menu; forum header: both in the nav). qdb navigation keeps omitting Invite. No per-viewer menu items and no new cookies or server work.
- Messaging scripts load for remembered-approved members on every page, cached or live, rather than only when the server rendered Messages. Other viewers do not download them.
- *(Option A, chosen 2026-10-29; see [decision](#open-decision-waiting-tab))* The Users page's Members / Waiting (N) tabs follow the same rule: same HTML for every viewer, shown before paint for remembered-approved members. Waiting itself stays approved-only (403 otherwise).
- The existing head pre-paint script shows them before first paint when local state says the browser is signed in (`identity_hint` and local keypair) and was last known approved.
- The last-known approval is remembered in browser storage by client code, from responses the client already receives (`/api/authenticate_identity` `approved=`, `/api/auth_status` 200/401), and cleared on sign-out or identity change.
- No change after first paint when status is unchanged. When the client learns of a real status change during a visit, the menu updates in place; this is the only post-paint change allowed.
- The unread indicator keeps working without a server-rendered viewer token; the Messages button never changes size or caption. Authorization of Messages/Invite routes is unchanged.

## Delivery scope and completion boundary

Application change: renderer menu items, nav partial and app-shell header/account menu, the nav fragment returned after feature-flag changes, layout head pre-paint script and critical CSS (and `standalone_layout.php` parity), client approval memory in existing sign-in/status scripts, on-demand messaging script loading, unread script viewer lookup, nav and tab-bar CSS, Users tabs, existing smoke/high-traffic/browser tests, UI copy checklist and this cycle's planning/ledger. No schema, API, authorization, cookie, dependency, Apache or deployment changes.

Normal entry: an approved member browses Board → thread → About → Tools with high-traffic mode on and off. Recovery: a stale memory (revoked or signed out elsewhere) corrects in place when the client next sees a 401 or sign-out; links still lead to live pages that enforce access. Release condition: real-browser checks in both modes showing identical menu position from first paint for a returning member (no layout shift), in-place update on sign-in/sign-out, no buttons for anonymous/unapproved visitors, identical menu HTML across viewers, and phone/desktop plus Word97/Chicago/Midnight icon rendering.

## Risks

- **Stale approval memory:** a revoked member could see buttons until the client next checks. Impact: misleading menu, not access. Validate early with revocation and sign-out flows; mitigation: clear on 401, sign-out and identity change; update in place.
- **Live pages losing per-viewer menus:** members without JavaScript would no longer see Messages/Invite anywhere. Impact: minor; direct URLs still work. Accept and record; validate the no-JS menu.
- **Pre-paint timing:** the decision must apply before body paint. Validate with a layout-shift check in a real browser; mitigation: extend the existing head script that already sets the theme pre-paint.
- **Unread indicator coupling:** it currently reads the viewer from server markup. Validate on a cached page; mitigation: read the viewer from local state.
- **Tab-bar layout overriding `hidden`:** app-shell tab items are styled `display: flex`, which beats the `hidden` attribute unless a rule restores it, and that rule must be in the inline critical CSS to apply before paint. Validate computed display and tab widths on phone first.
- **Messaging scripts arriving late:** loading them on demand must not change the Messages button's size or caption once painted. Validate on a cached page with a slow script response.

## Shared component inventory

- `TemplateRenderer` nav items/placements and `navigationState`, `templates/partials/nav.php` and `templates/partials/app_header.php`: extend (always include member items, hidden by default).
- `forum:identity-changed` and `forum:authenticated-in-place` events and `PrivateSiteAuth`'s current-identity check: reuse for approval memory.
- `ProfilePageController` Users page and `templates/partials/users_tabs.php`: extend.
- `templates/layout.php` head pre-paint script: extend; `standalone_layout.php` checked for parity.
- Client sign-in/status scripts (`browser_signing.js`, `auth_navigation.js`, private-site auth) and `IdentityHintController` sign-out: reuse; client code records/clears approval memory.
- `private_message_unread.js`: extend viewer lookup; keep the fixed-size contract.
- High-traffic renderer and cache: reuse unchanged; verify output.
- `theme-nav-icons.css` Messages/Invite icons: reuse.

## User flow

1. A member signs in; the client records approval and the menu shows Messages and Invite.
2. They open any page, cached or live; the head script shows the items before paint.
3. They sign out; the client clears the memory and the items disappear in place.

## Success criteria

- With high-traffic mode on and off, a returning approved member sees Messages and Invite on Board, thread, About and Tools with zero layout shift after first paint.
- Sign-in and sign-out update the menu in place without reload.
- Anonymous and unapproved visitors see neither item and no gap.
- Menu HTML is byte-identical for anonymous and member requests; cached files contain no viewer data.
- Unread indicator works on a cached page; button size unchanged.
- In the app shell, the phone tab bar and the account menu show no gap or empty slot for anonymous/unapproved visitors, and no tab moves after paint for returning members.
- `/users/` shows Members / Waiting (N) from first paint for returning approved members and no tabs for others, cached or live.

## Enhancement requests

- **Request:** decide entirely in the client so pages are fully static. **Disposition:** in-scope revision before approval; replaces the server-written marker. **Approval:** Step 2 pending. **Outcome:** this revision.
- **Request:** allow post-paint updates for real status changes. **Disposition:** adopted as the sole exception. **Approval:** Step 2 pending.
- **Request:** save the first-paint knowledge where other agents benefit. **Disposition:** the user asked for it to be done directly, outside this cycle. **Approval:** explicit user request. **Outcome:** first-paint rule added to `CONTRIBUTING.md` (UI copy and verification) and a pointer in `AGENTS.md` (UI copy); this cycle follows it.

## Study snapshots and approval

Intake and first Step 2 draft, 2026-10-27 UTC: scope v1 (server-written marker), starting revision `2bfab452`, R1/P2, Claude Code single exposed rater. **0.45 projected total**. Not pristine: nav rendering, the head script, identity hint cookie, unread script and prior plans were inspected while answering questions.

Step 2 revision, 2026-10-27 UTC: scope v2 (client-only decision, uniform server HTML, in-place updates on status change; contributor guidance later moved out of scope). **0.40 projected total**, revised for scope change: no server marker work, small docs addition. Additionally inspected `AuthApiController` status/approval responses, `auth_navigation.js` and `CONTRIBUTING.md`. No implementation. Awaiting `Approved Step 2`; planning uncommitted.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

Approval update: 2026-10-27 UTC — user supplied “Approved Step 2” for scope v2, including the client-only decision and real-status-change exception. Earlier pending statements above describe the prior handoff. Step 3 drafted separately; no implementation authorized.

*— Approval recorded by Codex · 2026-10-27 UTC*

## Revision v3 — 2026-10-29 UTC

Request: “Please revise plans” after `main` advanced past the app shell (`feat/ui-shell-navigation`), background sign-in and the Users/person-page redesign. Approved v2 behavior is unchanged; v3 restates where it applies and adds what the new code requires:

- **New understanding (no added behavior):** the app shell renders the nav in two places (main nav and account menu), and only the main nav comes back in the nav fragment after feature-flag changes. Messaging scripts are added to a page only when the server rendered Messages, so a uniform menu needs client-side loading. Tab-bar items use `display: flex`, so hiding needs an explicit pre-paint rule. Sign-in now has a current-identity check and `forum:identity-changed` / `forum:authenticated-in-place` events to build on.
- **Scope addition, if you choose it:** the Users page's Waiting tab ([open decision](#open-decision-waiting-tab)).

### Open decision: Waiting tab

The Waiting tab's caption carries a count, so to avoid a post-paint caption change the number has to be in the shared HTML.

- **A — Include, count in the shared HTML (recommended).** Same treatment as Messages/Invite. The pending count becomes visible in the page source to anyone, though the tab and the list stay approved-only. Adds about 0.05.
- **B — Include, caption without the count (“Waiting”).** Nothing new in the shared HTML, but it drops the count you asked for on 2026-10-28.
- **C — Leave it out.** Keep this cycle to Messages/Invite; Waiting stays inconsistent on cached `/users/` until a follow-up. Cycle estimate 0.45.

**Decision, 2026-10-29 UTC:** user chose **A**: “Do Option A, add a reminder to resolve this to todo.txt”. The count exposure and stale-count questions are recorded in `todo.txt` for later resolution; they don't block this cycle. Scope v3 includes the Waiting tab; projection 0.50 applies. This is a scope choice, not `Approved Step 2`.

### Study snapshot — Step 2 v3 revision

2026-10-29T18:37:06Z, `railspike-effort` / `v3/viewer_nav_prepaint`, Step 2 revision. Scope **v3** (v2 plus app-shell placements, on-demand messaging scripts and, with A, the Waiting tab); starting revision moves from `2bfab452` to `c5783108` (the v2 baseline predates the app shell). R1/P2; all three frozen R1 hashes verified. Claude Code single exposed rater, exposed to the earlier 0.45/0.40 and Codex stage estimates and to current header, auth, unread, script-loading, tab-bar CSS and Users source. **0.50 projected total** with A; **0.45** with B or C. Change reason: mostly new understanding of the code (two render sites, conditional script loading, tab-bar hiding), plus the optional scope addition. Earlier v1 0.45 and v2 0.40 retained above. No implementation, measured labor or accepted outcome; await `Approved Step 2`.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*

Approval update: 2026-10-29 UTC — user supplied “Approved Step 2 v3” for scope v3 with Waiting option A. The “await `Approved Step 2`” statement in the v3 snapshot describes the prior handoff.

*— Approval recorded by Claude Code · 2026-10-29 UTC*
