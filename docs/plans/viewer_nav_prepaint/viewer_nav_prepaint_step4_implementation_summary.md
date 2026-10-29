> **Feature plan:** [Step 2](./viewer_nav_prepaint_step2_feature_description.md) · [Step 3](./viewer_nav_prepaint_step3_development_plan.md) · [Step 4](./viewer_nav_prepaint_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; [debug log](./viewer_nav_prepaint_debug_log.md))
Effort study: railspike-effort / v3/viewer_nav_prepaint; [ledger](../effort_estimation_trial.md#cycle-v3viewer_nav_prepaint). Standing P2 enrollment.
Extended estimates: enabled (inherited)
State: Step 3 approved 2026-10-29; branch `feat/viewer-nav-prepaint` from `main` `c5783108`; planning commit `c8beee50`. Waiting tab option A.

**Cycle estimate: 0.50**
Step 4 entry: Step 3 projection reaffirmed.

## Step 4 entry checkpoint

2026-10-29 UTC, Step 4 entry, scope v3, starting revision `c5783108`, R1/P2; all three frozen R1 hashes verified at the Step 3 revision the same day. Claude Code single exposed rater (also the Step 3 reviser). **0.50 reaffirmed**: nothing learned between the Step 3 revision and branch setup changes the scope. Stage-work originals copied from [Step 3](./viewer_nav_prepaint_step3_development_plan.md): v1 (Codex, 2026-10-27) S1 0.10, S2 0.12, S3 0.08, verification 0.10; v2 (Claude Code, 2026-10-29) S1 0.08, S2 0.15, S3 0.12, S4 0.05, S5 0.10. Copying is not a new rating; never summed.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 1 - Remember approval for the current identity

**Original estimate: 0.10** (v1, Codex) · **Revised estimate: 0.08** (v2, Step 3 revision)
**Retrospective stage work: 0.08**

- Changes:
  - New `public/assets/member_approval.js`, loaded on every `layout.php` page right after the theme script. It stores the lowercase fingerprint of the identity last known approved (`forum_pki_approved_fingerprint`) and reflects it as `data-member="approved"` on the root element, firing `forum:member-approval-changed` when it flips. Approval only counts when it matches the current stored fingerprint and both keys are present, so a replaced or cleared identity never shows member links.
  - `private_site_auth.js` records each completed sign-in (`approved=1` / `0`) for the identity it signed with, after its existing current-identity check.
  - `auth_navigation.js` records approval when `/api/auth_status` returns 200 for the identity captured before the request.
  - `forum:identity-changed` (sign-out, key replacement) and cross-tab `storage` changes forget an approval that no longer matches the current identity.
- Verification:
  - `node tests/browser/member_approval_contract.cjs` (new, run from `PrivateSiteAuthTest::testMemberApprovalMemoryContract`): no identity, remembered approval without a request, once-per-session learning per identity, 401 never clearing memory, unregistered guest keys not asking on public sites (but asking on members-only), stale response after identity change ignored, approve/not-approved/other-identity sign-in results, identity replacement, sign-out, other-tab update and blocked storage. Passed.
  - `private_site_auth_contract.cjs` extended: sign-in records `[fingerprint, approved]` for both outcomes and nothing when the identity changes mid-request. Passed.
  - `AuthNavigationTest::testStatusCheckRemembersApprovalOnlyForAnApprovedSession` (new). `php tests/run.php AuthNavigationTest PrivateSiteAuthTest`: 17/17 passed.
  - `php tests/run.php LocalAppSmokeTest ProfileThemePresentationTest AppNavigationPlacementTest HighTrafficTest`: 171/172. The one failure, `LocalAppSmokeTest::testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp` (method `InstancePageController::repositoryArchiveDownloadFilename()` no longer exists), fails identically on a `c5783108` worktree; not caused by this cycle.
  - Real-browser checks: not applicable yet; nothing is visible until Stage 2 adds the CSS rule. Standalone layout has no navigation, so it does not load the script.
- Notes:
  - Refinement within approved scope: a 401 from `auth_status` does **not** clear memory, because it can mean an expired session rather than revocation; the sign-in that follows (`approved=0`) is the definitive answer. A revoked member with a live session can keep seeing the links until the next sign-in; the routes still refuse them.
  - Refinement within approved scope: members whose session predates this memory would otherwise never be remembered (an existing session skips sign-in). `member_approval.js` asks the existing `auth_status` endpoint once per browser session per identity, and only for registered keys (or on members-only sites), so automatic guest keys add no requests. Not polling.
- Estimate details: original v1 scope included the same three scripts; v2 lowered it for the existing identity check and events. Retrospective matches v2: the added learning check was offset by reusing the existing events.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 2 - Render a static header with pre-paint visibility

**Original estimate: 0.12** (v1, Codex) · **Revised estimate: 0.15** (v2, Step 3 revision)
**Retrospective stage work: 0.15**

- Changes:
  - `TemplateRenderer::navItems` emits Messages (when private messages are enabled) and Invite (except qdb) for every viewer, marked `member_only`; `nav.php` and the app-shell account menu in `app_header.php` render them with `data-member-only`. The forum header uses the same `nav.php`, so both headers are covered.
  - Inline critical CSS (top of `site.css`): `:root:not([data-member="approved"]) [data-member-only] { display: none !important; }`, which beats the tab bar's `display: flex` and applies before paint, with no-JS and blocked storage falling back to hidden.
  - `layout.php` head script sets `data-member="approved"` before body parse using the same rule as `member_approval.js` (remembered fingerprint equals the current one, both keys present).
  - Members-only sites keep their separate Lobby/Profile/Account nav for unapproved viewers; those pages are never served as shared static copies (`FrontController` skips static artifacts when approved-members-only is on).
  - `navigationState` (feature-flag nav refresh) is unchanged apart from script selection: Invite no longer depends on the viewer, so the account menu needs no refresh.
- Verification:
  - `php tests/run.php LocalAppSmokeTest AppNavigationPlacementTest HighTrafficTest HighTrafficPublicationTest PrivateMessageFeatureFlagTest UserAccessTest FeatureFlagsBehaviorTest PrivateSiteAuthTest AuthNavigationTest`: 202/203; the only failure is the baseline `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp` (see Stage 1). Updated assertions: placements for every viewer, `data-member-only` Invite markup, anonymous board now containing a hidden Invite, and live-navigation scripts/viewer only for approved viewers.
  - New `tests/browser/viewer_nav_prepaint.mjs` (real PHP server, Chromium): anonymous and returning-approved visitors on `/`, thread, `/about/`, `/users/`, `/tools/` in light/dark/Word97/Chicago/Midnight at 390 and 1280px — 100 page states. Visibility is recorded from the moment each link is parsed and positions on every painted frame through load: anonymous never shows the links; members show both while parsing and nothing moves after first paint. Phone tab bar shares its width evenly with no empty slot; Messages icon present in Word97/Chicago/Midnight; no horizontal overflow; no page errors. Sign-in shows the links in place, sign-out hides them in place and clears memory, the next visit is final from first paint, and no-JS pages keep them hidden. Passed.
  - Inspected screenshots: Midnight and Word97 phone (member and anonymous) and Chicago desktop (member).
  - A first test run reported a 10px "shift" on desktop; a probe against `c5783108` and this branch showed it was the test measuring positions mid-parse, before the rest of the header existed. The test now compares positions only across painted frames.
  - Standalone layout: no navigation; not applicable.
- Notes:
  - Moved to Stage 3: removing the per-viewer `data-viewer` token from Messages, and loading messaging scripts on the client. Doing either here would break unread between commits. Until Stage 3, approved live pages still differ from shared ones in those two places; Stage 3 verifies byte-identical headers.
  - During verification I briefly ran `git stash` by mistake, which set aside the uncommitted Stage 2 work and the user's `todo.txt` edit; it was popped back immediately with nothing lost.
- Estimate details: v2 anticipated two render sites, critical CSS and tab-bar interaction; all three were needed. Retrospective matches v2.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*
