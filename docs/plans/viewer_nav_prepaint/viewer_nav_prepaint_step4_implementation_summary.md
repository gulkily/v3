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

## Stage 3 - Load messaging on demand and keep unread working

**Original estimate: 0.08** (v1, Codex) · **Revised estimate: 0.12** (v2, Step 3 revision)
**Retrospective stage work: 0.14**

- Changes:
  - Messaging scripts are no longer added to every page for approved viewers. Only Messages pages (`activeSection === 'messages'`, approved viewer) still get script tags; elsewhere the fingerprinted list is published for everyone in `window.__forumAssetPaths.privateMessaging`, and `member_approval.js` loads it in order only for a browser remembered as approved, skipping scripts the page already has, then starts unread and history sync. Losing approval stops both.
  - The Messages link no longer carries `data-viewer`, and `navigationState` no longer returns `viewer`. `private_message_unread.js` binds to the viewer named by a Messages page's own content, or else to the first valid unread response, then rejects responses for any other viewer; it sets `data-viewer` on the link client-side and fires `private-message-viewer-known`. It also retries after an in-place sign-in, so a shared page that started without a session gets a real count.
  - `private_message_history_sync.js` waits for that event when the link has no viewer yet. `feature_flags.js` drops its now-meaningless viewer comparison.
- Verification:
  - `PrivateMessageFeatureFlagTest::testPagesAreIdenticalForEveryViewerOutsideMessages` (new): a full About layout is byte-identical for anonymous, unapproved and approved viewers on ZenMemes (app shell) and chouse (forum header), and never contains the viewer's name. Existing tests now assert messaging assets are listed but not script-tagged outside Messages, and are script-tagged on Messages pages.
  - `php tests/run.php` (same nine classes as Stage 2 plus messaging flag tests): 203/204, only the baseline `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp` failure.
  - `member_approval_contract.cjs` extended: no messaging downloads for other viewers; ordered loading for a remembered member that skips already-present scripts; start once; stop on lost approval; in-place load after sign-in. Passed. `private_site_auth_contract.cjs` passed.
  - Existing messaging browser suites passed: `private_message_live_navigation`, `private_message_unread_ui`, `private_message_unread_api`, `nav_unread_outline`, `private_message_feature_flag`, `private_message_operator_flag`, `private_message_unavailable`.
  - `private_message_history_sync_browser.mjs`: updated to wait for on-demand loading on `/`; all history, restore and outage phases pass. It then fails at its final operator-flag step (“Choose theme” button, moved into the app-shell account menu); the `c5783108` worktree fails at the same step.
  - `private_message_list_browser.mjs` fails at Users → “bob” (Users redesign); the `c5783108` worktree fails identically.
  - `viewer_nav_prepaint.mjs` extended: anonymous pages download no messaging scripts; on member pages unread reaches `ready` with no server viewer, and Messages keeps its caption and position. Full matrix: 100 page states passed (5 themes × 390/1280 × 5 routes × anonymous/member), plus in-place sign-in/sign-out and no-JS.
  - Measurement change: visibility is compared from the moment each link is parsed; positions only on frames painted after `<main>` is parsed and every stylesheet has loaded. Two intermittent failures led here. One was a frame painted mid-parse on `/tools/`, before the account menu existed. The other was a 2–16px move on dark desktop that eight later probes (branch member, baseline anonymous) could not reproduce once styles had loaded. Moves while `site.css` is still loading asynchronously are therefore not covered by this test.
- Notes:
  - Finding outside this cycle: in Word97 (desktop, about one load in three) the whole app-shell header shifts right after first paint, also on `c5783108` for anonymous visitors (Board 368→436px). Late theme styling, not member links. It happens while styles are still loading, which the browser test now excludes from position checks; the header shift needs its own follow-up.
  - Not done: the members-only unapproved nav (Lobby/Profile/Account) stays server-decided; those pages are never shared.
- Estimate details: v2 scope included script loading and viewer binding; history sync's dependency on the viewer and the Messages-page binding were extra discovery, so retrospective 0.14 against 0.12.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 4 - Users tabs follow the same rule

**Original estimate: 0.05** (v2, Step 3 revision; new in v3)
**Retrospective stage work: 0.05**

- Changes:
  - `ProfilePageController::directory` always computes the Waiting count, and `users.php` always renders `users_tabs.php` with `memberOnly`, so the tabs nav carries `data-member-only` and is hidden by the Stage 2 rule unless the browser is remembered as approved. Option A: the count is in every viewer's `/users/` HTML.
  - The Waiting page (`/users/pending`) keeps its tabs unmarked; it is approved-only (403 otherwise) and never shared.
- Verification:
  - `UserAccessTest::testStandardSeparationLegacyLinksAndFortePreservation` extended: the `/users/` tabs block (with “Waiting (”) is byte-identical for an anonymous and an approved session. `php tests/run.php UserAccessTest LocalAppSmokeTest ProfilePublicDetailsTest`: 165/166, only the baseline archive-filename failure.
  - Whole `/users/` page compared once by hand for those two viewers: the only difference is the existing public sign-in-resume scripts and body attributes for a visitor without a session (not visible UI; predates this cycle).
  - `tests/browser/users_profile.mjs` (visitor assertion updated from “no tabs in HTML” to “tabs not visible”): search, sort, own page, Replies, approve from Waiting, Message, approved-only Waiting, no-JS. Passed.
  - `viewer_nav_prepaint.mjs` on `/users/` and `/`, five themes, 390/1280: members see Members / Waiting (N) from first paint with no change after, anonymous visitors never see them, and the first visit after sign-in is final from first paint. 40 page states passed. Inspected the Midnight phone screenshot.
- Notes:
  - Refresh finding (for the `todo.txt` item, which I left unchanged): the shared `/users/` copy, and so its Waiting count, is regenerated only by a static release build or the shared-page refresh (`./v3` → `scripts/refresh_static_shared_artifacts.php`). A registration or approval does not trigger it, so cached copies can show an old count until then. Live pages are always current.
- Estimate details: as planned; the template change reused the Stage 2 mechanism unchanged.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*

## Stage 5 - Verify the full browsing and recovery flow

**Original estimate: 0.10** (v1 verification stage, Codex; unchanged in v2)
**Retrospective stage work: 0.13**

- Changes:
  - `viewer_nav_prepaint.mjs` gains configurations (`NAV_SITE`, `NAV_MESSAGING`), a theme-applied check, keyboard order and open-account-menu checks, and in-place revocation; theme storage now uses each site's own key (`forum-<site>-theme`).
  - New `tests/browser/viewer_nav_high_traffic.mjs`: isolated root, `scripts/high_traffic.php prepare/enable/disable`, removes its cache afterwards.
  - Enhancement request (user decision, 2026-10-29): the earlier static/offline release guard banned all messaging markup, which conflicts with Step 2's hidden link on every page. The user chose “Allow hidden link”. `PrivateMessageReleaseIsolationTest` now removes exactly the hidden member-only Messages link and the `privateMessaging` script list, then applies every existing ban unchanged, and also bans any `private_message` script tag. Mailbox pages, mailbox scripts, private databases and message data stay excluded.
  - `ui_copy_audit_checklist.md` entry added.
- Verification:
  - Full PHP suite `php tests/run.php`: 1626/1630. The four failures: `LocalAppSmokeTest::testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `PlatformDocsStaticTest::testStaticReleaseIncludesCompleteInventoryAndSafeAssets` and `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts` are long-standing (runner history: failing since 2026-10-24; the latter two also fail on a `c5783108` worktree). `PrivateMessageReleaseIsolationTest` was new; fixed as above and now passes. The full suite was not rerun after that fix.
  - `viewer_nav_prepaint.mjs` ZenMemes full matrix: 100 page states (Board, thread, About, Users, Tools; light/dark/Word97/Chicago/Midnight; 390/1280; anonymous and member) plus keyboard, sign-in, sign-out, revocation and no-JS. Passed.
  - Same test with chouse (forum header), qdb (no Invite) and messaging disabled, at light/Midnight on Board and About: 16 page states each. Passed.
  - `viewer_nav_high_traffic.mjs`: high-traffic mode on and off, `/`, `/about/`, a thread, 390/1280. Member and anonymous get the same header (and the same cached page when on); member links are final from first paint; unread reaches ready on cached pages. 24 page states passed.
  - Earlier-stage suites (Stages 1–4): contracts, messaging browser suites, `users_profile`. Known baseline failures in `private_message_history_sync_browser` (final theme-button step) and `private_message_list_browser` (Users → “bob”) reproduce on `c5783108`.
  - Not run: physical devices, screen readers, deployment.
- Notes: Contract outcomes, normal entry, recovery and release checks are covered above, except deployment and physical-device evidence (out of scope). Background sign-in on cached pages does not run (an existing design choice), so a returning member first remembered on such a page is recognized by the once-per-session check.
- Estimate details: verification grew with the configuration matrix, the high-traffic fixture and the release-guard conflict; retrospective 0.13 against 0.10.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*

## Closeout

**Cycle retrospective scope assessment: 0.55** (Step 4 entry projection 0.50 retained; not measured effort)

- Delivered scope v3, Waiting option A: identical header HTML for every viewer (outside Messages pages and members-only sites' unapproved nav), pre-paint visibility from per-identity approval memory, in-place updates on sign-in/sign-out/revocation, on-demand messaging, unread and history without a server viewer, Users tabs.
- Starting revision `c5783108`; branch `feat/viewer-nav-prepaint`. Planning `c8beee50`; stages `d50d10ac`, `32fce977`, `d9a0b0a1`, `e6185887`, then the Stage 5 commit. Five of five stages complete. Zero dependency delta; no schema, API, cookie, authorization, Apache or deployment change.
- Corrective work beyond plan: history-sync viewer binding, Messages-page viewer binding, unread retry after in-place sign-in, the release-guard narrowing (user-approved).
- Stage-work estimates: S1 0.10 v1 / 0.08 v2 → 0.08 retro; S2 0.12 / 0.15 → 0.15; S3 0.08 / 0.12 → 0.14; S4 — / 0.05 → 0.05; S5 0.10 / 0.10 → 0.13. Never summed.
- Follow-ups: Word97 whole-header shift while styles load (pre-existing); `todo.txt` Waiting-count item (exposure and staleness; refresh path recorded in Stage 4); the three long-standing PHP failures and two browser baseline failures above.
- Maintainer comfortable-fit judgment and study acceptance: **pending**. No accepted-count increment. Not merged or pushed.

*— Claude Code · 2026-10-29 UTC · Model: claude-opus-5-5*
