# UI copy audit checklist

## Backup section grouping — October 25, 2026

- [x] Split the page into three existing-style cards: Backup details, Downloads, and Recent included items. Added only the Downloads heading; preserved all fields, links, notes and empty states.
- Verification: 4/4 focused PHP checks (BackupPageTest and LocalAppSmokeTest portability/core routes); Chromium across 14 theme choices at 390px/1280px with populated, empty and long-value fixtures, all aliases, real downloads and revision-mismatch retry. No horizontal overflow. Inspected updated Word97 phone and Midnight desktop screenshots at `/tmp/backup-restored-{theme}-{width}.png`; browser evidence `/tmp/backup-restored-check.mjs`. Diff check passed.
- No dependencies, CSS, navigation/icon, data-contract or `todo.txt` changes. Existing tall Tools navigation on phones remains outside scope. Physical devices, other engines, full suite and deployment were not checked.


## Backup data restoration — October 25, 2026

- [x] Corrected the preceding cleanup: restored instance name, linked admins, generated time, snapshot hash and recent included items with All activity. These are useful data, not explanatory clutter. Kept direct download links; reduced notes to adjacent SQLite staleness/image matching and one short public-content-only limitation. “Index generated” accurately labels the existing read-model timestamp rather than implying archive generation time.
- Regression checks now assert actual instance/admin values, stored timestamp/hash and recent-item content/link presence, alongside unique downloads and essential limits. Focused PHP 8/8 passed, with the strengthened BackupPageTest rechecked. Browser verification passed on all three aliases and 14 theme choices at 390px/1280px, including populated data, template-rendered empty/missing metadata and long names/items, four successful downloads and wrong-revision failure/retry. No horizontal overflow; inspected `/tmp/backup-restored-word97-390.png` and `/tmp/backup-restored-midnight-1280.png`. Browser evidence: `/tmp/backup-restored-check.mjs` and `/tmp/backup-data-router.php`.
- Principle for later copy work: identify the information and actions a page supports first; remove duplicate explanations while preserving evidence, timestamps, identities, counts, previews and recovery controls. Verify preserved data as well as removed wording.
- No dependencies, schema, navigation/icon or `todo.txt` changes. Existing tall phone Tools navigation remains outside scope. Physical devices, other engines, full suite and production deployment were not checked. This record supersedes the earlier claim that removing the useful data completed the cleanup.


## Backup page reduction — October 25, 2026

- [x] Removed site/admin details, freshness headings/metadata, repeated download tables/descriptions, activity preview and the separate explanation card. All three Backup aliases now show direct format links, conditional image pairing guidance, SQLite staleness and a concise separate-backup warning. Removed the corresponding page-data queries and description strings; download behavior is unchanged.
- Verification: 8/8 focused PHP checks (`BackupPageTest`, `RepositoryContentExportTest`, and `LocalAppSmokeTest` portability/core-route methods). Actual Chromium rendering passed at 390px/1280px across 14 theme choices, with visible download links and no horizontal overflow; all aliases and all four real downloads passed. Wrong-revision HTTP 409 retained actionable refresh/re-download guidance and retry succeeded. Image-unavailable rendering showed three links and no pairing note at both widths. There is no activity empty state or disclosure after removal; native downloads add no success/pending UI.
- Inspected Word97 phone and Midnight desktop screenshots (`/tmp/backup-word97-390.png`, `/tmp/backup-midnight-1280.png`); browser check `/tmp/backup-copy-check.mjs`. Existing tall Tools navigation remains on phones and is outside this copy change. No navigation destination/icon changes, dependencies, schema changes or `todo.txt` edits. Physical devices, other browser engines, full suite and production deployment were not checked.


## Outstanding-item review — October 23, 2026

- Reviewed unchecked entries against the completion records and current source. Seven copy-audit candidates remain open: Outbox, Bookmarklets, Fastmod, Offline reading, Thread/post analysis, Feature Flags, and Users empty state. Their rendered-state review and acceptance checks below remain outstanding; source inspection does not close them.
- Current source still contains repeated Outbox summaries and technical timestamps, Bookmarklets introduction/group/button descriptions, Fastmod enabled-score and selection explanations, routine offline “Showing saved…” notices, and the Users empty-state explanation. These remain candidates for removal, subject to checking the affected flows and necessary recovery information.
- Reconciled the stale Browser identity ready triage entry with its completed implementation record below. Local completion does not establish deployment to the live instance. Deployment/live verification and the original feedback-comment reply remain open, as does the separate production legacy-ownership acceptance task.
- This review changed tracking only; no product changes, rendered checks, production inspection, or deployment were performed.

## Image attachment copy reduction — October 23, 2026

- [x] Attachment status now shows only dimensions and file size (for example, “175 × 68 · 2 KiB”). Removed the image description field from the shared composer and its unused field styling/visibility and lazy-signing selector. Existing preparation, error and recovery feedback remains.
- Verification: `node tests/browser/image_content_browser.mjs` passed with updated assertions for dimensions/size-only status and absent description input. Covered phone/desktop theme geometry and overflow, keyboard removal, busy/failure/offline recovery, dedicated reply, Forte reply/new-thread, QDB and real signed upload/retry. Inspected Midnight desktop screenshot `/tmp/image-content-browser-ZsPuUf/remove-preview.png`; PHP/JavaScript syntax and diff checks passed. No dependencies changed; `todo.txt` untouched.
- Limitations: local Chromium fixtures and emulated viewports; physical devices, other engines, production and full-suite checks were not performed.

## Image preview removal — October 23, 2026

- [x] Replaced the shared “Remove image” text button with an × in the preview’s upper-right corner. Retained its accessible name, tooltip, 44px target, keyboard focus, busy disabling, and separate “Remove attachment to post text” recovery action. Preparation/failure states retain removal even without a thumbnail. No dependencies added or updated; `todo.txt` unchanged.
- Verification: `node tests/browser/image_content_browser.mjs` passed, including all available standard themes at 390px/1280px for preview/control geometry and page overflow, keyboard removal, busy disabling, invalid-image removal, offline recovery removal, dedicated reply, Forte reply/new-thread dialog, QDB attachment and real signed thread/reply publication/retry. PHP/JavaScript syntax and diff checks passed. Inspected Midnight desktop screenshot `/tmp/image-content-browser-8iZ8xk/remove-preview.png` (extremely wide one-pixel-high image fixture).
- Limitations: local Chromium fixtures and emulated viewports; physical devices, other browser engines, production deployment and full suite were not checked.

## Standard Activity relevant evidence — October 23, 2026

- [x] Replace repeated full-commit file lists with Relevant files for each standard Activity item; keep the complete list behind the existing commit link. Preserve unsigned status, identity/signature/key evidence and recovery controls. Forte copy/behavior stays unchanged.
- Verified the Git-backed bulk-commit browser fixture with/without JavaScript, all 13 themes at 390px/1280px, historical source/full-commit links, pagination, empty/invalid/error/retry states and long values. Actual local Activity source access, Next/Back and overflow checks passed at both widths. Focused PHP 30/30 plus the core-route regression; signed-reaction and real Forte append/Commits browser regressions passed. [Outline and measured outcome](standard_activity_manifest_size_fix_outline.md).
- Existing deployed static HTML requires republishing; production rollout and physical devices remain unverified.

## Standard Activity pagination — October 23, 2026

- [x] Add only Previous/Next navigation, an empty state and actionable First page recovery for invalid/expired positions. Use native link and browser retry behavior; no routine success notice or help panel.
- Verified real standard Activity navigation with JavaScript enabled/disabled, empty/invalid/HTTP-failure/retry states, source evidence, keyboard links and history. All 13 standard themes passed at 390px/1280px with long subjects/paths and no page overflow; Word97 phone and Midnight desktop screenshots inspected. Forte production controls/copy remain unchanged; real append/detail/Commits and existing shared-reader/signed-button checks passed.
- Physical devices and production deployment remain unverified. [Implementation summary](activity_feed_pagination/activity_feed_pagination_step4_implementation_summary.md).

## Source-owned label removal — October 22, 2026

- [x] Keep legacy label uncertainty actionable after accepted retraction: the existing reload acknowledgment displays operator-review guidance only when ownership candidates remain unresolved. No new navigation, confirmation or routine explanatory panel.
- Verified real signed author/operator/account and partial-retry browser flows; legacy warning in all 13 themes at 390px/1280px without warning/page overflow. Inspected Word97/Chicago phone and Midnight desktop screenshots; existing menu keyboard/Escape/touch check passed. Fixture also verifies physical root deletion preserves conversation. Screenshots are local `/tmp/retraction-browser-evidence/` evidence, not deployment artifacts.
- [ ] Production ZenMemes rollout and live legacy ownership resolution remain operator acceptance tasks; missing historical proof is not silently inferred. [Implementation record](retraction_effects/retraction_effects_step4_implementation_summary.md).
## Access history in User Access — October 21, 2026

- [x] Removed the detached Feature Flags paragraph and loose User Access audit link. Show five recent events in User Access, with concise summaries, expandable canonical evidence and conditional View all.
- [x] Keep legacy uncertainty and authentication failure/retry feedback; protect history with authenticated operator authorization and no-store. Hint-only operators get recovery; authenticated members (including forged operator hints) receive no protected history.
- [x] Actual Chromium flow: Tools/User Access → event evidence → full history/older page → return; empty history, grant/invitation/policy/legacy events, 111-event shared-date paging, expired-session signing/retry, invalid cursor, keyboard expansion, pending approvals and source links. Existing Forte retry/parity harness passes.
- [x] Loaded phone/desktop at 390/1280 px across all 16 themes, recent and expanded full history; no page overflow. Reviewed `/tmp/access-history-light-1280.png` and `/tmp/access-history-midnight-390.png`. Existing User Access launcher/subnav icons verified in Word97, Chicago and Midnight; no destination/icon mapping was added or renamed.
- Evidence: `node tests/browser/access_history.mjs`, `node tests/browser/forte_activity_parity.mjs`, 47 focused PHP tests. The original user's browser session was not inspected; isolated tests reproduce hint-only one-line 403 and empty authorized history separately. Non-Chromium browsers, production deployment and the full test suite were not run in this cycle.


## Browser identity ready notice — October 21, 2026 triage

- [x] Remove the routine “Browser identity ready” notice and hide its empty status element; retain necessary pending/failure/recovery feedback and explicit Account results. [Triage and acceptance](browser_identity_ready_triage_2026-10-21.md).
- Implementation and local verification are recorded under [Browser identity ready](#browser-identity-ready--october-21-2026) below; this supersedes the initial source-only triage status.
- [ ] Deploy and verify the notice removal on the live instance; local checks did not establish production completion.
- [ ] After verified completion, reply under the original feedback comment as requested by the user; distinguish this fix from the other composer issues in that comment.

## Import CLI recovery guidance — October 20, 2026

- [x] Removed unconditional `--resume` advice from import failures; show recovery guidance only when the selected destination has a pending journal. Replaced generic public-download wording with HTTP status, bounded sanitized source detail, and source retry guidance.
- Verification: Actual CLI/HTTP tests cover initial preview/import success, source failure without a journal, failure with an independently pending journal, interrupted publication, and successful resume. Full suite: 1372/1377 passed; all five failures reproduced on unchanged `main` and are listed in the [implementation summary](legacy_backup_compatibility/legacy_backup_compatibility_step4_implementation_summary.md). Browser layout/theme checks are inapplicable: this change affects terminal output only. Production source and user WSL verification remain pending.
## Same-author collapse toggle — 2026-10-21

- [x] Added the concise “Collapse posts from same author” control using the existing Feature Flags UI and recovery feedback. No new explanatory panel or navigation destination.
- [x] Isolated Chromium acceptance: real signed disable/enable/reset, reload persistence, anonymous read-only/rejected writes, environment lock/rejected writes, and failed-save retry.
- [x] Loaded root/later continuation runs, agent/other author boundaries, separate metadata/actions/counts, empty replies, expanded reply composer and long prose at 390/1280 px across light, dark, console, Word97, Chicago, Midnight, Arena, Sticker, and Whitehot. No page overflow; reviewed phone Midnight and desktop Word97 screenshots.
- Evidence: `node tests/browser/same_author_collapse.mjs`; screenshots `/tmp/collapse-{theme}-{width}-{state}.png`. Synthetic author labels are seeded only in the isolated fixture read model. Production deployment and non-Chromium browsers were not tested.


## Next audit — candidates to verify

These source-review candidates are not confirmed removal decisions. Review the
rendered states before choosing changes; keep necessary consequences and recovery
visible. An unchecked item remains open until changes and verification are recorded,
or the review establishes that the existing copy should remain.

- [x] **Outbox** — Review repeated action summaries and technical timestamps,
  including “Action time (signed)” and “Integration time pending.” Check empty,
  draft, queued, published, failed, and retry states. Keep delivery uncertainty,
  unsaved work, and recovery actions clear. Source: `public/assets/outbox.js`.
- [x] **Bookmarklets** — Review overlap among the introduction, installation
  instructions, group notes, and button descriptions. Check same-window and
  new-window groups, copy success/failure, and phone installation. Retain necessary
  setup, compatibility, and popup-blocking guidance. Source:
  `templates/pages/bookmarklets.php` and its browser feedback.
- [x] **Fastmod** — Review the enabled-score summary against checked controls,
  repeated selection instructions, while keeping the full prompts visible as explicitly requested. Check operator/read-only views, settings failures, and save feedback.
  Preserve the meaning of scores, the minimum-selection requirement, and access
  recovery. Source: `templates/pages/fastmod.php` and its browser feedback.
- [x] **Offline reading** — Review repeated “Showing saved…” success feedback
  and overlapping missing-content notices. Check saved board, tags, thread,
  unavailable content, queued likes, and reconnect outcomes. Retain snapshot scope,
  freshness limitations, and actionable recovery. Sources:
  `templates/pages/offline_reader.php`, `public/assets/offline_reader.js`.
- [x] **Thread/post analysis** — Review expanded analysis for overlapping
  summaries and technical fields that do not help the reader make a decision.
  Check root/reply cards, post detail, and affected alternate views, including
  pending, failed, and completed states. Preserve useful attribution and moderation
  consequences. Source: `templates/partials/post_card.php` and shared feedback.
- [x] **Feature Flags** — Review descriptions, state badges, save feedback,
  and operator-access explanations for duplication. Check search/no matches,
  enabled/disabled/overridden flags, and save failure/retry. Keep site-wide effects
  and flag-specific consequences beside their controls. Sources:
  `templates/pages/feature_flags.php`, `public/assets/feature_flags.js`.
- [x] **Users empty state** — Review whether the explanation following “No
  visible users yet” adds necessary information. Check truly empty and
  approved-without-visible-content cases, including affected alternate views.
  Source: `templates/pages/users.php`.

For each item:

- [ ] Inspect phone and desktop rendering after real content loads, including
  relevant expanded, empty, pending, success, partial, and failure states.
- [ ] Review shared templates and browser/server feedback together; remove
  repeated explanations and hide empty status elements.
- [ ] Verify the main action, necessary consequences, keyboard/touch access,
  recovery, page overflow, and primary-action stability in affected states.
- [ ] Update affected assertions, run relevant existing checks, and record the
  decision, changes, verification, and unresolved follow-ups before closing it.

Reduce reading required to complete ordinary forum tasks. Favor removing text over rewriting or relocating it, relying on an intuitive, explorable interface. For every finding below, first consider deleting the explanation; suggestions to shorten text or move it into help are fallbacks only when users need information the interface cannot communicate. Keep material consequences and recovery actions beside the affected control.

Source review dated October 17, 2026 covered page templates, shared partials, and selected first-party browser messages. The original review identified copy and presentation findings without rendered-state verification. Per-item notes below record the subsequent implementation and checks.

## First fixes

- [x] **Invite generation** — Removed policy mechanics; kept link-sharing and existing-account access warnings, with expiry beside the result. Verified phone/desktop rendering and issuance, redemption, and public/private zero-click browser flows. Commit: `30c4ab6e`.
- [x] **Public backup downloads** — Deleted repeated restoration explanations and preview instructions. Kept backup exclusions, matching image/repository guidance, and the index caveat. Phone/desktop rendering and five backup scope/image checks passed. Commit: `cafd7c64`. Core-route assertion follow-up: `49af02c1` (passed).
- [x] **Activity security notice** — Removed the repeated policy explanation from both activity views; kept current policy status. The Feature Flags control retains “Existing approvals remain valid.” Phone/desktop classic and Forte rendering plus seven approval history/policy checks passed. Commit: `882de3b6`.
- [x] **Profile diagnostics** — Deleted the redundant self-profile card. Technical diagnostics had already moved to User Access. Phone/desktop self-profile rendering and nine profile/access checks passed. Commit: `aa8c3cde`.

## Other cleanup

- [x] **Platform Docs discovery** — Removed the repeated source-path explanation and guide descriptions; retained paths, starting guides and the architecture disclosure. Library controls precede the explainer. Desktop/mobile search, recovery and both theme-icon surfaces passed across dynamic/static delivery; recorded in [Docs Discovery](docs_discovery/docs_discovery_step4_implementation_summary.md).
- [x] **Platform Docs landing** — Reverses part of the entry above by explicit, approved request: the landing view again shows the source-path note and one-line guide descriptions, because readers need them to choose a guide and the interface can't show that otherwise. The explainer is open again, ahead of the topic guides; the library moves to the results view. New results copy is limited to the filter, chip and page labels, plus one static no-JavaScript search notice. Verified across dynamic/static, JS on/off, 1280px/200%/320px and four themes; recorded in [Docs Landing](docs_landing/docs_landing_step4_implementation_summary.md).

- [x] **SQLite query prerequisite message** — Removed the initial database-loading explanation; retained the live status element for query feedback. PHP syntax and diff checks passed.
- [x] **Testing self-approval** — Added one conditional Account Key action, “Approve current account,” without setup explanations or a confirmation dialog. Reused existing feedback for failure/repair; recovery points back to the button. Desktop/mobile browser checks cover the action, overflow, marker removal, and persisted-failure/repair/retry. See [implementation summary](testing_self_approval/testing_self_approval_step4_implementation_summary.md).

- [x] **Activity site development heading** — [railspike_workflow.js](../../public/assets/railspike_workflow.js), activity panel. Removed “Site development · operators only” completely; workflow links remain. Browser regression passed. Commit: `6bf5bb65`.
- [x] **Account setup status** — Removed duplicate ready/setup explanations and hid empty mirrored status. Retained actionable feedback, one Ready badge, and username. Six hook checks, phone/desktop rendering, and Chromium-to-Firefox backup/restore/recovery browser checks passed. Commit: `cd95d388`.
- [x] **Key backup help** — Deleted backup/restore instructions already conveyed by controls and PNG format mechanics. Kept access-loss, forgotten-passphrase, and original-file warnings. Phone/desktop expanded panel, PHP syntax, and Chromium-to-Firefox PNG backup/restore/rejection checks passed. Commit: `01cf0f83`.
- [x] **Storage and privacy controls** — Shortened the guest-publication label and removed repeated publication timing from storage details. Kept one consequence sentence and browser/server/cookie scope. Phone/desktop expanded controls and guest-key runtime-option check passed. Commit: `2aaa9bf6`.
- [x] **Lobby recovery and invitation help** — Removed redundant approval and account instructions and optional username advice. Added direct sign-in recovery and kept automatic invitation acceptance notice. Phone/desktop lobby rendering and cleared-session, anonymous/pending access checks passed; invitation acceptance flows were verified with Invite generation. Commit: `6f1b5b27`.
- [x] **Visitor statistics** — Reduced the introduction to request scope and privacy, and shortened the existing counting details. Retained exclusions, overlapping estimates, retention, and unavailable/partial feedback. Phone/desktop expanded rendering and three statistics page/observer checks passed. Commit: `bfa5582e`.
- [x] **Composer introduction** — Deleted the paragraph describing the editor and optional sections. Phone/desktop rendered labels and compose disclosure keyboard/menu checks passed. Commit: `20809e16`.
- [x] **Backup success feedback** — Removed verification jargon and repeated storage advice. Kept “download started” and separated reminder-save failure onto its own line. Phone/desktop forced reminder failure, JavaScript syntax, and cross-browser download/restore/recovery checks passed. Commit: `1710c627`.
- [x] **Offline health recovery** — Replaced HTTP guidance paragraphs with a direct Open over HTTPS link; API explanations remain in diagnostics. Real insecure-origin phone/desktop checks verified the URL, missing-browser-API state, and no overflow. Offline health route and JavaScript syntax checks passed. Commit: `fe6b2e1f`.
- [x] **Private message send recovery** — Removed duplicate generic delivery failure and retained-draft reassurance; combined unconfirmed delivery and unsaved recovery into one outcome/action. Specific errors, duplicate-send protection, and keep-open warning remain. Four composer checks and full desktop/mobile messaging browser suite passed, including lost acknowledgments, reload, storage failure, and disabled messaging. Commit: `88d4bd21`.
- [x] **Root approval evidence** — Deleted the redundant introduction, repeated missing-facts notice, and history-count explanation. Missing authorizer/acceptance time now appears once beside repository history, with commit dates explicitly limited to file history. Phone/desktop rendering and all seven root-evidence checks passed. Commit: `55bdfb3f`.
- [x] **Image attach control** — [image_compose_fields.php](../../templates/partials/image_compose_fields.php), compose menus. The standalone “Choose an image” button became “Attach image” in the `…` menu of thread and reply forms; the error text no longer says “Choose an image”. Prior implementation: `2f1d224b`.

## Verify each fix

- [x] Read the rendered flow on desktop and mobile, including relevant empty, success, failure, and conditional states. Confirm the main action is easy to find.
- [x] Remove text that repeats visible controls or state. Check shared partials and browser/server feedback so the same explanation does not return through another source.
- [x] Confirm concise copy still explains material access, privacy, data-loss, and recovery consequences at the relevant decision. Keep useful help accessible without relying on hover alone.
- [x] Update copy assertions affected by the edits and run the relevant existing checks. Mark individual findings complete only after their UI changes are verified.

## Member directory / access split

- [x] Standard Users and member/profile subpages — removed approval controls, key inventories/history, and technical diagnostics; retained public status/attribution, messaging and contextual User Access recovery links. Prior verification: `7fd59cd1`.
- [x] Tools User Access — concise account/queue links; existing policy consequences and signing failures remain beside actions. Technical key details stay expandable. Standard legacy history anchors and pagination retain a path to Tools. Prior verification: `7fd59cd1`.
- [x] Forte compatibility — retained its combined directory/access UI; shared history links remain within Forte. Prior verification: `7fd59cd1`. This cleanup only changes the shared activity security notice in Forte.

## Completion verification — October 19, 2026

- Changes were completed as individual one-shots, with one implementation commit per remaining finding. Each commit included its completion note; the next documentation update recorded its hash. The backup core-route assertion required the follow-up recorded above.
- Isolated fixture pages were rendered at 390px and 1280px; no horizontal overflow was detected in the reviewed flows. Existing browser suites covered invitation success/failure, text/PNG backup and cross-browser restoration, composer controls, and message delivery/recovery. PHP checks covered access boundaries, backup scope, policy history, statistics, offline health, and missing/malformed/current root evidence.
- Recovery checks included a real insecure HTTP origin with a verified HTTPS link target and forced backup-reminder persistence failure. Specific message errors and unconfirmed-delivery/unsaved-recovery warnings remain visible.
- The core-route smoke check passed after its obsolete backup-copy assertion was updated. The account hook suite passed with pre-existing missing `identityHint` warnings in its minimal fixture. No production data or external services were changed by these checks.

- [x] **Security review entry** — Moved the operator-only link from standard and Forte Activity navigation to User Access beside Pending approvals; shortened it to “Security review”. Existing review URLs and authorization remain intact.
## New-member approval meaning — October 19, 2026

- [x] Kept the conduct/integrity consequence in the existing new-member review, because the approval control cannot communicate this responsibility alone. Account-key access warnings remain; own-key and cross-account-key grants omit the new-member wording. Pending approvals now use the same confirmation. Policy/access/prepared-approval checks passed (14/14); desktop/phone browser review covered cancellation, signing failure and retry; real HTTP signed approval covered all three classifications, 390px/1280px new-member rendering, history/rebuild persistence and no overflow. See the [implementation summary](user_approval_meaning/user_approval_meaning_step4_implementation_summary.md).

- Follow-up: at the user’s request, restored one-click pending-list approval, removing the confirmation introduced in this cycle. The pre-existing profile review and its conduct/integrity explanation remain. See the implementation summary for verification.

## LLM exchange display — October 19, 2026

- [x] Removed LLM exchange links from shared thread root/reply cards and stopped fetching exchange lists for thread pages. Post detail pages now render their own exchange links beside the post metadata, retaining approved-viewer, feature-flag, and retraction visibility checks. Two targeted write-API smoke tests passed, covering thread absence, root/reply detail links scoped to the selected post, anonymous/disabled UI absence, and retracted exchange privacy. PHP syntax and diff checks passed.

## Agent reply availability notice — October 19, 2026

- [x] Removed the completed-analysis availability notice and “View agent reply” link from shared root/reply cards and browser generation feedback. Pending and failure feedback remain. PHP/JavaScript syntax checks and three targeted write-API checks passed, covering selected-mode completion rendering and hidden browser feedback for generated/already-posted replies.
## Compose first paint stability

- [x] Dedicated thread and reply pages — moved existing identity/status feedback below the form so initialization does not move the action row. Existing errors and recovery feedback remain visible; no explanatory copy added. Reserved the image picker footprint during permission checks and rendered Outbox actions before JavaScript initialization. Verified mobile/desktop geometry across Light, Word97, Chicago, and Windark, upload permission states, and both Outbox saves with `node tests/browser/compose_layout_stability.mjs`.
- [x] Compose action alignment follow-up — aligned Create thread and More actions without adding UI text; removed inherited submit spacing and allowed both controls to share one row on mobile. Regression now checks equal top edges and heights as well as first-paint stability.

## Post retraction sweep

- [x] Separate accepted retraction from local cleanup: one-time “Local cleanup queued.” acknowledgment after reload; enqueue failures keep a concise warning and existing Retry retraction control visible. No new navigation or internal storage explanation in the flow. Verified author/operator/account/partial browser scenarios and keyboard/Escape/touch menu behavior.

- [x] Platform Docs retraction entry — concise title and task-focused description under Operations; reuses the existing document list and reader.

- [x] **Agent button label** — Shortened “Request agent response” to “Agent” on shared thread root and reply cards. Updated the existing rendered-page assertion; targeted write-API smoke test, PHP syntax checks, and diff checks passed.

## Pending reply identity notice — October 19, 2026

- [x] Removed “Posting as your browser identity” from pending reply cards; omit the metadata row when no author label is available. Existing author labels, posting status and failure/retry behavior remain. Four targeted browser-signing renderer/submission/recovery checks and JavaScript syntax passed. Tracks Zenmemes feedback Feature 33.

## SQLite viewer copy cleanup — October 19, 2026

- [x] Removed the backup warning, initial loading prerequisite/status copy, redundant query heading, preset descriptions, and query success announcement. Reduced the introduction to read-only/local execution, shortened download links and invalid-query feedback, and kept provenance plus the saved-data caveat in one place. Empty status elements are hidden; loading, errors, refresh recovery, pagination, and effective-query details remain.
- Verification: PHP/JavaScript syntax and diff checks passed; all six targeted SQLite smoke checks passed after updating copy assertions and one stale preset-selection assertion. Existing listing-query browser suite passed, including standalone/dialog use, pagination/sorting, storage denial, invalid queries, and load recovery. Live 390px/1280px checks covered initial/loaded/success/invalid-query states; desktop had no overflow. The loaded mobile page initially had horizontal overflow, also reproduced using the prior JavaScript (397px document at 390px viewport); resolved in the follow-up below.

- [x] **SQLite mobile overflow follow-up** — Constrained the query pagination flex item to its output width so its controls wrap on narrow screens. Added standalone browser coverage at 320px, 390px, and 1280px for page/container overflow and working Previous/Next controls; the full listing-query browser suite passed. Live 320px/390px checks also verified horizontal table scrolling and expanded effective-query details without page overflow. Diff checks passed.

## Markdown reader
- [x] Keep existing document/source navigation. Added only code-language captions, collapsed metadata, and unavailable-source/image labels where formatting alone cannot communicate the fallback. Table/code scroll regions have accessible names; no extra reading instructions or controls. Verified desktop/mobile and keyboard with JavaScript disabled.

## Shared Fastmod tagging flag — October 20, 2026

- [x] Removed the obsolete Fastmod explanation “Scores do not trigger actions” and shortened its existing Feature Flags link to the shared tagging control. Reused the existing flag row, signed save/failure feedback and tag presentation; no new styling or navigation destination.
- Verification: real-browser signed toggle, simulated failed-save/retry, anonymous refusal, no model call on save, root tagging and site-off behavior passed. At 390px/1280px, checked light/dark/console/Word97/Chicago/Midnight flag and tag discovery without page overflow; inspected phone Midnight and desktop Word97 screenshots. Existing Feature Flags toolbar browser checks passed for filtering, empty/read-only states, keyboard and failed-save preservation. No unresolved layout issue introduced by this row was observed.

## System State query controls

- [x] Restored visible inline SQL on System State, with compact magnifying-glass buttons opening the query viewers. Accessible names and tooltips retain the query context; other pages keep their existing controls. PHP syntax and existing query coverage checks passed.

- System State follow-up: query viewer icons use flat, transparent buttons without borders or shadows, including hover/pressed theme states; keyboard focus remains visible. CSS diff check passed.

## Board composer first focus — October 21, 2026

- [x] Removed the redundant readonly prompt textarea and its disclosure arrow; one editable textarea uses the existing board prompt and expands actions in place. No extra instructions or repeated controls.
- Verification: Real-board Chromium/Firefox/WebKit checks at 390px/1280px across Light, Word97, Chicago and Midnight; first focus without scripted scroll/transfer, draft/selection, empty collapse, failure/retry, successful publication, native image chooser and no-script controls passed. Inspected phone Word97 collapsed and Midnight expanded screenshots. Physical iPhone keyboard acceptance remains pending; inline-reply focus and floating-action overlap are separate work.

- Board composer correction: Restored the compact prompt and full expanded controls while retaining one editor and no disclosure arrow. Initial CSS applies before deferred scripts, preventing Post flashing and relocating after paint. Chromium/Firefox/WebKit first-paint geometry, true height expansion/empty collapse, drafts, selection, retry, attachments and no-script checks pass across phone/desktop and four themes; refreshed Light phone screenshots inspected. Actual iPhone keyboard acceptance remains pending. Reply-form Railspike remains paused.

## Browser identity ready — October 21, 2026

- [x] Removed routine “Browser identity ready” copy and hid empty status containers. Pending, failure, technical-details recovery, and explicit Account outcomes remain visible when needed.
- Verification: 88 browser-signing, lazy-compose, and identity-diagnostics checks passed. Chromium checks passed for 24 standalone composer route/theme/viewport/permission cases and 32 shared board, expanded inline-reply, paned New Thread, and QDB Add Quote cases at 390px/1280px across Light, Word97, Chicago, and Midnight. Checked empty/loaded readiness, focus, preserved drafts, pending/failure-to-ready transitions, stale technical-details cleanup, action geometry, and page overflow. Explicit Account feedback, both Outbox saves, and real signed thread/reply publication passed. Inspected the expanded Midnight phone screenshot. Syntax and diff checks passed.
- Limitations: browser checks use local fixtures and emulated viewports; physical phones and production were not checked. Deployment and the source-comment closeout remain pending. Separate composer focus/jumping requests are not resolved by this change.

## Saved home-page draft stays collapsed — October 21, 2026

- [x] Bug 03: restored home-page drafts stay compact until focused. Text, selection, empty-collapse, warning, and attachment behavior are preserved. No UI text or controls added; inline reply behavior is unchanged.
- Verification: 85 signing/lazy-compose checks and Chromium/Firefox/WebKit at 390px/1280px across Light, Word97, Chicago, and Midnight passed. Covered long restored drafts, background status/pageshow, focus, overflow, failure/retry, signed publication, and no-script controls. Inspected phone collapsed and desktop expanded screenshots; syntax/diff checks passed.
- Pending: physical-phone and production checks, deployment, source-comment reply. Other composer-jumping reports remain separate.
## Reply composer focus and layout — October 21, 2026

- [x] Removed the redundant readonly inline-reply prompt and collapsed disclosure arrow. The canonical editor preserves real compact/expanded behavior from first paint; no extra explanatory copy or repeated submit control.
- [x] The phone editor, status/validation feedback and original action row share reserved space, including the open menu. Oversized feedback/attachments, tiny viewports or zoom return to usable ordinary flow. Desktop and Forte do not activate the phone panel.
- Verification: Chromium/WebKit/Firefox matrices cover explicit editor/action/error/menu separation, viewport changes, long draft/selection, rotation, reopening, fallback and restoration. Real inline/dedicated reply checks cover unsupported-character correction, failed prepare/retry, repeated pending activation and one persisted reply; native anonymous/attachment handling and signed image reply pass. Four-theme phone/desktop first-paint and true collapse/expansion checks pass; Light, Word97, Chicago and Midnight phone composing screenshots inspected. Fixed Midnight spacing overriding the panel boundary and added a whole-panel viewport assertion. No-script and the corrected board composer retain behavior. Physical iPhone Safari/Firefox keyboard and browser-chrome acceptance remains pending user testing; browser emulation is supplementary.

## Detached Activity workflow block — October 23, 2026

- [x] Removed the private workflow block above Activity, its client rendering/fetching and Activity workflow script inclusion. Existing thread/post workflow controls and documented APIs remain available; no replacement copy or navigation added.
- Verification: operator/anonymous, JavaScript enabled/disabled, 390px/1280px Activity matrix passed with ordinary filter navigation and no overflow. Observed zero workflow requests through the former polling interval and visibility refresh. Existing signed start/answer/revise/approve, failure/retry and saved-key resumption journey passed. Standard Activity native pagination, empty filter, invalid-position/HTTP recovery, RSS and opening an entry passed with scripts on/off. All 13 themes passed phone/desktop overflow checks; inspected phone Activity heading/navigation screenshot and Word97 loaded-content/pagination screenshot. Existing Forte append/detail checks also passed.
- Limitations: local fixtures and emulated viewports; production deployment/reload and physical-device checks pending. This removes one non-repository workflow block; complete repository coverage and script-free private workflow reading are separate proposals. See the [implementation summary](./railspike_workflow_visibility/railspike_workflow_visibility_step4_implementation_summary.md).

## Access history merge verification — October 23, 2026

- [x] Retained shared standard Activity Previous/Next/First page controls and private sign-in recovery when merging Access history. Relevant-file evidence preserves signature and unavailable-commit status.
- Passed merged-code browser checks for Access history (16 themes at 390/1280), standard Activity (13 themes, native controls, JS on/off) and Forte append/Commits/parity; 15 focused PHP checks passed. Full-suite and deployment verification remain unperformed.


## LCD palette selector — October 21, 2026

- [x] Added Green, muted Amber, grayish Purple, and Gray choices to one LCD-only control left of the theme selector. No explanatory copy or settings page. Theme selector top/right offsets remain stable.
- Verified pointer/keyboard, phone/desktop rendering, text/control contrast, saved preferences/first paint, fallback and stylesheet recovery. See the [implementation summary](lcd_color_choices/lcd_color_choices_step4_implementation_summary.md) for commands, coverage, and the existing blocked-storage composer limitation. Physical-phone and production checks remain pending.

## LCD palette merge verification — October 23, 2026

- Merged current remote main and LCD palette work, retaining both verification histories. Adapted restored home-page draft engagement to the shared composer binder while preserving inline-reply expansion behavior.
- Passed `node tests/browser/lcd_palettes.mjs` and `node tests/browser/board_compose_focus.mjs` on merged code: Chromium phone/desktop palette, contrast, layout, persistence, keyboard, rotation/recovery, four-theme draft focus, failure/retry, signed publication and no-script checks. JavaScript/PHP syntax and diff checks passed. Existing blocked-storage composer errors remain separately identified by the LCD check. Full-suite, physical-device and production checks were not performed for this merge.

## Consecutive reply continuation — October 23, 2026

- [x] Keep routine identity/posting chatter out of the active composer. Pending/failed status and Retry belong to each captured reply; confirmed replies use the complete canonical post card and its normal actions. Phone re-entry uses the existing field without a help panel or new navigation control.
- [x] Actual local signed text/image flows cover immediate display with requests held, concurrent/reversed completion, retained newer draft, lost-response/reload/two-tab/expiry/storage recovery, anonymous and no-script posting. Chromium phone/desktop rendered continuation passes both routes across all 16 explicit themes; Firefox and WebKit pass the same functional flow across Light/Word97/Chicago/Midnight. Long content stays within the local stack and page bounds; representative desktop Word97/Midnight and phone Word97/Midnight screenshots inspected in `/tmp/reply-continuation-review/`.
- [x] Removed an initially hidden stack's first-paint spacing shift, corrected fixed theme status-bar overlap and preserved the desktop editor through the browser's cleared-caret rendering adjustment. Existing layout/readiness and image browser regressions pass; detailed commands and browser limits belong to the [implementation summary](reply_continuation/reply_continuation_step4_implementation_summary.md).
- [ ] Physical iPhone Safari/Firefox keyboard, caret, rotation and one-tap re-entry acceptance; production rollout/F01 acceptance remain pending. No new/renamed navigation destination or external dependency.

- Reply continuation correction, October 23: removed the accepted placeholder's “Open reply” presentation. Reconcile the full canonical body/metadata/actions in place; pending and rendering-failure recovery remain distinct from publication. Verified complete long reply HTML, signed Like, draft isolation and rendering-only Retry in three engines; phone four-theme layout/one-tap checks and representative Midnight/Word97 screenshots pass. This corrects an omission in the original acceptance coverage.

- Ordinary-flow correction: removed the separate “Your replies” region and its fixed-height/inner-scroll styling. Posted cards are direct siblings in the existing page, with canonical same-author grouping, bylines and counts reconciled in place. No new help text or navigation was added. Full-card/reload parity and phone reading/re-entry pass on both routes in Chromium/Firefox/WebKit (four themes); Chromium continuation/recovery also passes all 16 desktop/phone themes. Shared restoration fetches prevent the long-history request burst exposed by the first sweep. Device/deployment limits remain in the implementation summary.

## Outbox copy cleanup — October 23, 2026

- Removed the unlinked duplicate action summary, unchanged Last updated timestamp, absent signed/integration timestamps, and routine queued/accepted/cancelled explanations. Retained linked targets, actual signed/integration times, draft locality, sending uncertainty, failures and recovery controls. Empty status elements are hidden.
- Verification: six existing Outbox presentation/state/storage checks passed. Chromium production-script fixture covered nine expanded states, empty state, keyboard disclosure, Queue transition and failed Send feedback with retry control; Light/Word97/Chicago/Midnight at 390px/1280px passed overflow checks. Inspected Midnight phone screenshot (`/tmp/copy-outbox-midnight-390.png`). Local controlled browser fixture; actual server delivery, physical devices and production deployment were not exercised. No dependencies added or changed.


## Bookmarklets copy cleanup — October 23, 2026

- Removed the general introduction and repeated same-window note; removed repeated Compose Thread/window mechanics from seven button descriptions. Kept the installation instruction, content differences between Clip/Rip/Tweet/+URL, x.com scope, iPhone grouping and popup/iPhone warning.
- Verification: existing seven-variant behavior/copy checks and real Chromium clipboard/keyboard/fallback/failure/retry suite passed. The actual isolated Bookmarklets route loaded through Tools; available themes at 375px/1280px passed copy-button geometry and focus checks. Inspected `/tmp/bookmarklet-copy-mobile.png`. PHP syntax and diff checks passed. Physical-phone installation, qutebrowser and production deployment were not verified. No dependencies changed.
- Previous task commit: Outbox `a927c3c3f`.

## Fastmod surrounding copy — October 23, 2026

- User refinement: keep full Fastmod prompts on the page. Preserved the complete introduction and every rubric prompt, visible without expansion. Removed the enabled-score list repeated by checked controls and the extra selection explanation; retained minimum selection, score semantics in the prompts, read-only access guidance and recovery. Hid initially empty status; removed the transient Saved notice before reload.
- Verification: three rubric checks and the existing real signed-save/scoring browser suite passed (including rejected empty selection, selected-rubric persistence, no scoring on save and scoring retry). Controlled production-template/script Chromium checks covered operator/read-only/settings-error views, full prompt equality/visibility, validation/signing recovery and overflow in Light/Word97/Chicago/Midnight at 390px/1280px. Inspected Word97 phone screenshot. Syntax/diff checks passed. Physical devices and production unverified; no dependencies changed.
- Bookmarklets follow-up: four-theme 375px/1280px page-overflow checks passed. Its task commit is `88b02d864`.

## Offline reader copy cleanup — October 23, 2026

- Removed routine saved-content success strings (already hidden by existing status policy), duplicate missing-tag status/count and duplicate missing-thread paragraph. Missing content retains one reconnect instruction; saved-image scope and archive indicators remain. Explicit queued-Like and reconnect-processing outcomes are now visible rather than hidden with routine load successes, preserving delivery uncertainty and the Outbox recovery path.
- Verification: 19 existing snapshot presentation/thread/cache-worker checks passed. Chromium loaded the production reader template/script and real SQLite runtime with isolated loaded/empty snapshots; board, tags, existing/missing tag and existing/missing thread states passed. Checked single missing-content recovery, queued Like/Outbox access, reconnect outcome and four-theme 390px/1280px overflow. Inspected Midnight queued-Like screenshot. An initial browser probe lacked UTF-8 metadata; corrected the temporary fixture before the successful run. Real network reconnection/delivery, physical devices and deployment remain unverified. Syntax/diff passed; dependencies unchanged.
- Previous task commit: Fastmod `0e2663ba3`.

## Thread/post analysis copy — October 23, 2026

- Removed the generated Post summary and Question classification beside the original content in both shared root/reply templates. Kept provider attribution, moderation summary/recommended action, quality/respondability/reasons, suggested response and Unicode evidence. Canonical analysis and API data are unchanged; this is reader presentation only.
- Verification: four existing write-API checks passed for idempotent analysis, missing configuration, Unicode warnings/visibility and related content. Updated existing assertions and checked approved post-detail output as well as thread output. Actual HTTP-rendered root/detail/anonymous fixture output passed Chromium keyboard expand/collapse, retained attribution/moderation and four-theme 390px/1280px overflow checks; inspected Midnight phone detail screenshot. The initial detail assertion lacked the approved identity cookie; corrected the test setup before passing. Syntax/diff passed. No browser pending/failure generation lifecycle rerun, physical devices or production verification; those paths were not edited. No dependencies changed.
- Previous task commit: Offline Reading `e9a611a7c`.

## Feature Flags copy cleanup — October 23, 2026

- Removed routine Saved feedback after successful toggle/reset; the switch/state/override now communicate the result. Empty statuses are hidden; Saving, failed saves and navigation-refresh recovery remain visible. Reduced three repeated Fastmod help links to one concise Fastmod link beside the parent scoring flag. Kept site-wide consequences, effective-state labels, flag descriptions, dependencies and locked/read-only reasons.
- Verification: existing toolbar browser suite passed search/no matches, filters/counts, enabled/overridden/locked/read-only/no-JS states, signed-save lifecycle with controlled responses, toggle/reset, delayed saves, failure recovery and keyboard/focus preservation. Extended its actual stylesheet/layout coverage to Light/Dark/Console/Word97/Chicago/Midnight at 375px/1150px and asserted hidden successful-save status. Inspected Word97 phone screenshot. Targeted PHP flag/evaluator/private-message checks passed; syntax/diff passed. Physical devices and production unverified; dependencies unchanged.
- Analysis follow-up: repeated four-theme root/detail/anonymous rendering with every theme stylesheet explicitly loaded; inspected the corrected Midnight phone screenshot. The first pass changed attributes without loading all stylesheets. Analysis task commit: `173b897ee`.

## Users empty-state copy — October 23, 2026

- Removed the explanation beneath “No visible users yet.” Populated directory, agent attribution and profile links are unchanged. Review found the explanation could mislead: existing approved profiles with zero thread/post counts are still returned by the directory query.
- Verification: eight existing profile-content/theme and directory-invalidation checks passed. Controlled production-template Chromium empty/populated views passed keyboard member-link navigation and Light/Word97/Chicago/Midnight overflow checks at 390px/1280px; inspected Midnight phone empty screenshot. The actual isolated Users route covered a populated approved profile, that profile with zero content counts, and no approved profiles by modifying only the disposable fixture read model. The first actual-route probe assumed bootstrap had no profiles; corrected that assumption and explicitly exercised all three states. Forte has its own template and was not changed. Physical devices/production unverified; syntax/diff passed and dependencies unchanged.
- Previous task commit: Feature Flags `d587823f4`.

## Audit pass closeout — October 23, 2026

- All seven original Next audit candidates now have individual cleanup/verification records and task commits, each including this checklist. The initial tracking review was committed as `9e0225c47`; earlier task hashes are recorded in subsequent entries. `git log -- docs/plans/ui_copy_audit_checklist.md` identifies each task's own commit.
- Final core-route and Bookmarklets/compose-prefill smoke checks passed (2/2). Feature Flags targeted PHP checks passed (25/25). No new or updated dependencies, navigation destinations, icon mappings, generated assets or `todo.txt` changes.
- Newly observed candidates remain unchecked under Next phase. These local changes have not been deployed or verified on the live instance; existing production acceptance and source-comment follow-ups remain open. Temporary browser scripts/screenshots are under `/tmp/`; no full-suite or physical-device acceptance is claimed.

## Outbox aggregate summary — October 23, 2026

- Removed the loaded total/attention sentence repeated above state-labelled rows. Nonempty Outbox status is hidden; individual action states, local-draft scope, delivery uncertainty and recovery remain. Empty-state and storage/action errors still use the visible status.
- Verification: six existing presentation/state/storage checks passed. Chromium production-script fixture passed nine expanded states, hidden loaded summary, empty state, keyboard disclosure, queue transition, failed-send feedback and four-theme 390px/1280px overflow. Screenshot evidence: `/tmp/copy-outbox-*.png`. Local controlled fixtures; server delivery, physical devices and deployment unverified. JavaScript syntax/diff passed; dependencies unchanged.

## Analysis no-finding copy — October 23, 2026

- Omitted empty/none moderation recommendations and benign Unicode sections in both root/reply templates. Unicode labels, suspicious code points, non-none review priority, review concerns or recommended review actions keep the section and its script/evidence details visible. Analysis storage/API data and moderation severity remain unchanged.
- Verification: three targeted write-API checks passed for benign/idempotent analysis, missing configuration and mixed-script risk. Updated benign assertions and added risky thread coverage alongside risky post detail. Chromium replayed actual rendered benign/risky root/detail and anonymous outputs, checking keyboard disclosures, retained risk attribution/evidence, omitted no-finding fields and Light/Word97/Chicago/Midnight overflow at 390px/1280px. Inspected Midnight phone risky detail screenshot (`/tmp/copy-analysis-risk-detail-midnight-390.png`). Syntax/diff passed; physical devices, live provider generation and deployment unverified. No dependencies changed.
- Previous item commit: Outbox aggregate summary `ed7f14f33`.

## Post-detail evidence review — October 23, 2026

- Decision: retain the evidence header. Thread is the return path, Score is the post's score, Source opens its canonical record, Commit exposes history, and Signature distinguishes signed/unsigned provenance. Public key links open the key record; the bootstrap-only Advanced disclosure instead exposes raw public-key contents. These serve distinct navigation/provenance purposes; this review found no safe duplicate evidence to remove. No product changes in this item.
- Verification: production TemplateRenderer/shared partials rendered signed, unsigned, unavailable-commit, retracted and bootstrap-identity states in Chromium. Verified one source presentation, visible score, signed key/signature links, explicit unavailable history, retraction notice and expanded identity key; keyboard Source navigation passed. Light/Word97/Chicago/Midnight at 390px/1280px passed overflow checks; inspected Word97 phone signed screenshot. Two existing post-exchange/retraction API checks passed. Evidence links used controlled local destinations, not live Git/signature verification. Physical devices and production unverified; dependencies unchanged.
- Previous item commit: analysis no-finding cleanup `526dde43f`.

## LLM Exchanges introduction review — October 23, 2026

- Removed “newest first” from the introduction; descending time order is visible in the table. Retained “Private provider conversations.” because private-data scope is not conveyed by the title or columns. Empty feedback, record links, statuses and access/visibility policy are unchanged.
- Verification: eight existing exchange-recorder/store and post-exchange/retraction API checks passed, including visibility filtering and provider transport failures. Production-rendered loaded/empty list states passed four-theme 390px/1280px Chromium overflow checks with visible post/exchange links; inspected Midnight phone list screenshot. Syntax/diff passed. Browser fixtures used controlled rows; actual provider calls, physical devices and deployment unverified. Dependencies unchanged.
- Previous item commit: evidence retention review `48d4f7f95`.

## Visitor statistics initialization copy — October 23, 2026

- Removed the sentence explaining when counts appear; retained the initializing state itself. Unavailable-state recovery, incomplete-period warning, request/privacy scope, chart/table and expanded counting limitations remain.
- Verification: ten existing statistics configuration/store/observer/page checks passed, including initialization, partial periods and operator-session access. Chromium production-renderer fixtures covered initializing, unavailable, loaded, partial and zero-request periods; expanded counting/privacy details and Light/Word97/Chicago/Midnight overflow passed at 390px/1280px. Inspected Midnight initializing and Word97 partial phone screenshots. Final core-route smoke passed. Syntax/diff passed; physical devices and production deployment unverified. Dependencies unchanged.
- Previous item commit: LLM Exchanges introduction `6dfc100cf`.

## Second audit pass closeout — October 23, 2026

- Closed all five current-phase candidates, each in an individual commit including this checklist. Four copy changes; evidence-header review retained the existing UI with its rationale. No new dependencies, navigation destinations, icon mappings, Fastmod prompt edits or `todo.txt` changes.
- Collected five further candidates below; these remain observations for a future pass. Local controlled browser fixtures and targeted regression checks do not establish live-instance deployment. Full-suite and physical-device acceptance were not performed.

## Current phase — October 23, 2026

Reviewed candidates; decisions and verification are recorded above.

- [x] **Outbox loaded summary** — Assess whether the total/attention count adds value above the visible state-labelled rows, especially with many completed items. Retain actionable storage failures and empty-state feedback.
- [x] **Post analysis no-risk fields** — Review routine “Recommended action: none,” “Unicode risk: none” and script inventories in expanded analysis. Check benign and risky posts; retain actual warnings, moderation consequences and useful evidence. Sources: `templates/partials/post_card.php`, `templates/partials/thread_root_card.php`.
- [x] **Post-detail evidence header** — Review whether the Thread/Score/Source/Commit/Signature block repeats nearby post identity/evidence disclosures. Check signed, unsigned, unavailable-history and retracted posts before deciding; preserve provenance and recovery. Source: `templates/pages/post.php` and shared identity details.
- [x] **LLM Exchanges introduction** — Review whether “Private provider conversations, newest first” adds information beyond the title, access context and time-ordered table. Preserve private-data scope and empty/error feedback. Source: `templates/pages/llm_exchanges.php`.
- [x] **Visitor statistics initialization** — Review the two-sentence initialization notice for repetition and whether unavailable/initializing states need different recovery. Retain request scope and privacy limitations. Source: `templates/pages/visitor_statistics.php`.

## Outbox row labels — October 23, 2026

- Removed the generic “Local action” suffix when a row has no saved title. Retained action and state: a thread subject alone does not identify the operation, and delivery state is necessary before expansion. Saved titles and linked target text remain unchanged.
- Verification: six existing Outbox presentation/state/storage checks passed. Production-script Chromium fixture covered all nine states plus an unknown state, untitled draft, long title, reaction, expanded controls, keyboard disclosure, Queue, failed Send/recovery and empty state. Light/Word97/Chicago/Midnight at 390px/1280px passed overflow checks; inspected Midnight phone screenshot (`/tmp/copy-outbox-midnight-390.png`). Controlled storage/sender fixtures; real delivery, physical devices and production unverified. Syntax/diff passed; dependencies unchanged.

## Analysis response assessments review — October 23, 2026

- Decision: retain the expanded assessments. Generation recommendation, numeric score and response risk are independent inputs to automatic-reply gates; a positive recommendation can still accompany a skipped reply. Audience/author benefit and the response reason explain the assessment, while the actual reply status remains separate. Removing these would hide useful decision evidence. No product changes.
- Verification: current production templates rendered root/reply/detail cards with generated, low-score, declined and high-risk assessments. Chromium checked visible distinct assessments, keyboard expansion/collapse and four-theme (Light/Word97/Chicago/Midnight) 390px/1280px overflow. Inspected `/tmp/copy-assessment-root-high-risk.png`. Two existing API checks passed for idempotent generated analysis and low-score skip/visibility. The first temporary root fixture lacked its title; corrected that setup and reran successfully. Controlled assessment data does not establish live provider behavior; physical devices and deployment unverified. Dependencies unchanged.

## Post-detail Reply duplication — October 23, 2026

- Removed the header’s “Reply to this post” link. The card’s existing Reply action remains beside the content and points to the same thread/parent composer, including retracted posts. No composer or retraction behavior changed.
- Verification: current production renderer covered signed/unsigned, unavailable history, retracted, bootstrap root and long evidence-header states before/after removal. Chromium verified one remaining Reply, its thread/parent URL, keyboard navigation to the controlled compose destination, provenance links and Light/Word97/Chicago/Midnight 390px/1280px overflow. Inspected Word97 phone signed screenshot. Three existing analysis/post-exchange/retraction API checks passed; updated the existing post-detail copy assertions. Initial browser probes assumed the root fixture shared the reply’s thread ID and checked navigation before completion; corrected both probe assumptions before the passing run. Actual composer submission, physical devices and deployment unverified. Syntax/diff passed; dependencies unchanged.

## LLM Exchange absent request ID — October 23, 2026

- Omitted Request ID when it is missing, null or empty; available identifiers remain beside provider/model. Status, prompt/response, raw JSON, missing-response feedback and error evidence remain unchanged.
- Verification: eight existing recorder/store/post-exchange/retraction checks passed, including updated actual-route assertions for absent and available IDs and unchanged rows through retraction. Production-rendered completed, provider-error and transport-error fixtures covered present/missing/null/empty IDs, keyboard raw-evidence expansion and four-theme 390px/1280px overflow. Inspected Midnight phone transport-error screenshot (`/tmp/copy-exchange-after-transport-error.png`). Controlled provider records; real provider calls, physical devices and deployment unverified. Syntax/diff passed; dependencies unchanged.

## Visitor statistics zero-request period — October 23, 2026

- Removed “No eligible requests were observed in this period” beside the five zero totals and empty trend/table. Initialization and unavailable recovery remain distinct; incomplete collection still displays its warning, including zero-request periods. Privacy/counting limits remain in the existing disclosure.
- Verification: ten existing statistics configuration/store/observer/page checks and the core-route smoke passed. Current production renderer covered initialized zero traffic, loaded, initializing, unavailable, partial and partial-with-zero-traffic states; Chromium checked zero totals, empty rows, retained warnings/recovery and expanded privacy details across Light/Word97/Chicago/Midnight at 390px/1280px without page overflow. Inspected Midnight phone zero/partial-zero screenshots (`/tmp/copy-phase-visitor_statistics-*.png`). Controlled dashboard data; real collection interruptions, physical devices and deployment unverified. Syntax/diff passed; dependencies unchanged.

## Third audit pass closeout — October 23, 2026

- Closed the five next-phase candidates below: four copy reductions and one evidence-retention decision. Individual commits include each item’s checklist record. Outbox rows: `f9841409c`; analysis review: `1522ff68c`; post-detail Reply: `2d548631e`; absent request IDs: `2ac8ce8f9`.
- Targeted regression checks and controlled rendered phone/desktop checks passed as recorded per item. No new dependencies, navigation destinations, icon mappings, Fastmod prompt edits, generated assets or `todo.txt` changes. Full suite, physical devices and live deployment remain unverified; existing production acceptance and original feedback-comment follow-ups remain open.
- Collected three further source observations for a future pass at the bottom of this file; none is an approved removal decision.

## Third audit phase — October 23, 2026

Reviewed candidates; decisions and verification are recorded above.

- [x] **Outbox row labels** — Review action/state prefixes and generic “Local action” fallbacks against meaningful saved titles. Check long titles, reactions, untitled drafts and unknown states; preserve delivery-state distinctions. Source: `public/assets/outbox.js`.

- [x] **Analysis response assessments** — Review numeric Respondability, “generate yes/no,” Response value and Response reason together for overlap. Check why a reply was generated or skipped and what moderators need; preserve meaningful decision explanations. Sources: shared root/reply analysis templates.

- [x] **Post-detail Reply duplication** — Review the header's “Reply to this post” link against the card's Reply action. Check long evidence headers, root/reply details and retracted posts; retain an easy-to-find reply action. Source: `templates/pages/post.php`, `templates/partials/post_card.php`.

- [x] **LLM Exchange absent request ID** — Review the routine “Request ID: unavailable” field on detail pages when no provider identifier exists. Check completed, provider-error and transport-error records; retain available trace IDs and error evidence. Source: `templates/pages/llm_exchange.php`.

- [x] **Visitor statistics zero-request period** — Review the “No eligible requests” paragraph alongside zero totals and an empty chart/table. Distinguish no traffic from initialization, collection gaps and failures; retain privacy/counting limits. Source: `templates/pages/visitor_statistics.php`.

## Offline health pending feedback — October 23, 2026

- Removed the duplicate checking and refreshing sentences. The existing CHECKING/REFRESHING status remains; the empty summary is hidden while pending and shown again for results or failures. Readiness, storage errors, offline guidance and refresh recovery remain.
- Verification: existing offline-health route test passed. Chromium controlled service-worker/cache/publication fixtures covered initial and repeat pending checks, ready/missing archives, storage denial, offline and unavailable-publication states, refresh failure and successful retry. Light/Word97/Chicago/Midnight at 390px/1280px passed overflow checks; inspected Midnight phone storage-failure screenshot (`/tmp/copy-health-cycle4-storage.png`). An initial temporary browser script had a brace typo; fixed before the passing run. Physical devices, actual worker/cache lifecycle and deployment unverified. No dependency or navigation changes.

## LLM Exchange missing-content review — October 23, 2026

- Decision: retain both messages. “No prompt content extracted” identifies the reader's extraction limit without claiming the raw request is empty. “No response body was recorded” distinguishes absent body data from a successful readable response; raw non-text response data and provider/transport errors remain separately accessible. Blank sections or raw disclosures alone would not communicate those distinctions. No product change.
- Verification: eight existing exchange recorder/store/database checks passed. Production-template Chromium fixtures covered empty records, non-text request/response JSON, raw bodies, decoded text, provider errors and transport errors. Keyboard raw-JSON expansion and Light/Word97/Chicago/Midnight 390px/1280px overflow checks passed; inspected Midnight phone non-text screenshot (`/tmp/copy-missing-cycle4-nontext.png`). Controlled provider fixtures; actual calls, physical devices and deployment unverified. Dependencies unchanged.

## Analysis quality fallback cleanup — October 23, 2026

- Removed the manufactured discussion-value “unknown” when Quality contains only a good-faith estimate. Present discussion values and good-faith estimates (including zero) remain, with no leading comma when discussion value is absent/null. Root, reply and post-detail views share the decision. Retained moderation, provider and respondability unknowns because missing safety/provenance/decision inputs are materially different from a populated assessment.
- Verification: two existing post-analysis/Unicode-risk API checks passed. Production-template Chromium fixtures covered absent, complete, discussion-only, good-faith-only, null, zero and high-moderation records in root/reply/detail views; checked retained moderation action/summary and response assessments, keyboard disclosure and Light/Word97/Chicago/Midnight 390px/1280px overflow. Inspected Midnight phone moderation screenshot (`/tmp/copy-quality-cycle4.png`). Controlled historical-analysis fixtures; live providers, physical devices and deployment unverified. Syntax/diff passed; dependencies unchanged.

## Offline reader image-backup notice — October 23, 2026

- Removed the unconditional image-backup paragraph from the shared saved-reader shell, so it no longer appears above every saved board/tag/thread view. Backup still offers its separate Images collection download. Offline mode, missing-content reconnect guidance, queue outcomes and Outbox recovery remain.
- Verification: fourteen existing worker/navigation/thread-presentation and offline-reader route/control checks passed. Chromium used the real SQLite runtime with controlled loaded/empty snapshots across board (All/Newest), tags, found/missing tags and found/missing threads. Confirmed notice absence, one recovery message per missing view, queued Like and reconnect outcomes, and Light/Word97/Chicago/Midnight 390px/1280px overflow. Inspected Midnight phone board screenshot (`/tmp/copy-offline-cycle4-board.png`). Snapshot fixtures and sender stubs do not establish live delivery, worker upgrades on existing devices or deployment to the reported other instance. Syntax/diff passed; dependencies unchanged.

## Feature Flags account-key notice review — October 23, 2026

- Already removed by `904e055a7` (expired operator-session recovery), which deleted the exact detached paragraph and link from Feature Flags. No further product change. Access history remains in User Access with authenticated operator access and sign-in recovery. The other instance's intermittent display cannot be diagnosed or declared deployed from this checkout.
- Verification: three existing feature-flag behavior/operator-security API checks passed, including Feature Flags notice absence, authenticated history, permission denial and operator sign-in recovery. Existing Chromium toolbar/save checks passed for search/filter/counts, empty recovery, keyboard focus, failed/delayed saves, read-only and no-JavaScript views, and six themes at 375px/1150px; a temporary copy also asserted the exact notice is absent from editable/read-only/no-JavaScript fixtures. Inspected Midnight phone screenshot (`/tmp/feature-flags-midnight-375.png`). Controlled fixtures; actual signatures covered by existing API checks, not browser stubs. Physical devices and deployment unverified. Dependencies unchanged.

## Fourth audit pass closeout — October 23, 2026

- Reviewed all five candidates: three copy changes (offline pending feedback, sparse analysis quality, shared offline image-backup notice), one retained missing-content decision and one verification of an existing removal. Each item has its own commit with this checklist. Earlier item commits: `f10e5a5b9`, `5cf1b52a7`, `973150ad0`, `52907671e`; the Feature Flags verification is in this entry's commit.
- Targeted PHP checks and controlled rendered phone/desktop checks passed as recorded per item. No dependencies, navigation destinations, icon mappings, Fastmod prompts or `todo.txt` changes. Full-suite, physical-device and live-instance acceptance remain unverified; prior deployment and layout follow-ups remain open.
- The three observations below are future review candidates, not completed changes.

## Offline health result summary — October 23, 2026

- Removed the routine ready/not-ready sentences beside READY, PARTIAL and NOT READY. Empty summaries stay hidden; the existing live status announces results, and diagnostic rows and guidance retain missing/partial distinctions. HTTPS and refresh-failure summaries remain visible. Fixed the existing saved-content/publication warning branch to unhide its guidance instead of assigning text to a hidden element.
- Verification: existing offline-health route check passed. Production-script Chromium fixtures covered initial/repeat pending checks, ready, missing archive, storage denial, offline, unavailable publication, insecure context, refresh failure and retry. Confirmed the publication warning and HTTPS recovery are visible, routine summaries stay hidden and recovery summaries reappear. Four themes (Light/Word97/Chicago/Midnight) at 390px/1280px passed overflow; inspected Midnight phone storage-error screenshot (`/tmp/copy-health-cycle5-storage.png`). Controlled worker/cache/publication fixtures; actual lifecycle, physical devices and deployment unverified. Syntax/diff passed; zero dependency delta.

## Offline reader refresh success — October 23, 2026

- Removed “Saved reader refreshed and rechecked.” The REFRESHING status/disabled button transitions to the checked status, freshness rows and enabled button, including unchanged revisions. Refresh completion no longer overwrites missing-archive, storage or published-snapshot recovery guidance returned by the check. Refresh failures and offline recovery remain visible.
- Verification: four existing route/worker checks passed for refresh success/failure, unchanged revisions, preservation of failed-refresh data and forced refresh. Updated the route test's obsolete copy assertion after its initial expected failure. Production-script Chromium fixtures held the refresh pending, then covered changed/unchanged revisions, failure/retry, missing archives, storage denial, unavailable publication and offline refusal; confirmed retained warnings and revision/freshness output. Light/Word97/Chicago/Midnight 390px/1280px overflow passed; inspected Midnight phone publication-failure screenshot (`/tmp/copy-refresh-cycle5-publication.png`). Controlled worker/cache fixtures; actual lifecycle, physical devices and deployment unverified. Syntax/diff passed; zero dependency delta.

## LLM Exchange return-link duplication — October 23, 2026

- Removed the extra “Back to LLM Exchanges” link. The controller already supplies the shared Tools subnavigation with an active LLM Exchanges link to the same list. No destination, label, icon mapping or access rule changed.
- Verification: seven existing exchange recorder/store/retraction checks and two core-route/exchange-link checks passed. Production-template Chromium fixtures used the real Tools registry and covered decoded, empty, non-text, provider-error, transport-error and long expanded prompt/response records. Confirmed exactly one list-return link, keyboard navigation to the rendered list, raw-JSON disclosure, unclipped focused link and Light/Word97/Chicago/Midnight 390px/1280px overflow. Inspected Midnight phone navigation screenshot (`/tmp/copy-return-cycle5-long.png`). A temporary application-route probe confirmed anonymous and disabled-UI list/detail requests remain 403 and approved missing records remain 404. Controlled records; actual providers, physical devices and deployment unverified. Syntax/diff passed; zero dependency delta.

## Fifth audit pass closeout — October 23, 2026

- Closed all three candidates with individual commits including this checklist: redundant readiness summaries (`e8d66daae`), routine refresh success (`e7de37f44`) and the duplicate LLM Exchange return link (this entry's commit). Readiness warning visibility and preservation of checked recovery guidance were verified alongside the removals.
- Targeted PHP and controlled rendered phone/desktop checks passed as recorded above. No dependencies, navigation destinations, icon mappings, Fastmod prompts or `todo.txt` changes. Full-suite, physical-device and deployment acceptance remain unverified. Changes remain local; origin has advanced and synchronization is still needed before publication.
- Two further source observations are collected below for later review.

## How to continue this audit

Work through the unchecked next-phase candidates one at a time, favoring removal of redundant copy while preserving necessary warnings, provenance, recovery actions and the full visible Fastmod prompts; verify each decision in rendered phone/desktop states and affected themes under `CONTRIBUTING.md`, then close the item and commit it separately with its decision, verification and limitations recorded in this checklist. As you work, collect newly observed verbosity candidates for the following phase, keep that candidate section at the very bottom of this file, and distinguish local completion from live-instance deployment.

## Fourth audit phase — October 23, 2026

Reviewed candidates; decisions and verification are recorded above.

- [x] **Offline health checking feedback** — Review “CHECKING” alongside “Checking offline reading status…” in initial and repeat checks. Preserve pending feedback, offline readiness, storage failures and actionable recovery. Sources: `templates/pages/offline_health.php`, `public/assets/offline_health.js`.

- [x] **LLM Exchange missing content** — Review “No prompt content extracted” and “No response body was recorded” alongside raw request/response disclosures and errors. Check empty, non-text, provider-error and transport-error records; retain the distinction between extraction limits and absent provider output. Source: `templates/pages/llm_exchange.php`.

- [x] **Analysis quality fallbacks** — Review “Quality: unknown” when only good-faith likelihood is present, and other conditional unknown values. Check partially populated historical analyses alongside complete assessments; preserve meaningful quality and moderation evidence. Sources: `templates/partials/post_card.php`, `templates/partials/thread_root_card.php`.

- [x] **Offline reader image-backup notice** — Remove the user-reported unnecessary notice: “Saved snapshots preserve image references but do not include image files. Download the separate image backup from the online Backup page to preserve them.” Reported at the top of board newest/all and apparently every page on another instance. Verify the affected saved views. Source: `templates/pages/offline_reader.php`.

- [x] **Feature Flags account-key review message** — Remove the user-reported unnecessary message that intermittently appears in Feature Flags: “Review account key grants and approval-policy changes (authenticated operator session required).” Verify when it appears and the affected rendered states.

## Fifth audit phase — October 23, 2026

Reviewed candidates; decisions and verification are recorded above.

- [x] **Offline health result summary** — Review READY/NOT READY alongside “Offline reading is ready/not ready on this device.” Preserve PARTIAL distinctions, publication failures, actionable guidance and accessible result announcements. Sources: `templates/pages/offline_health.php`, `public/assets/offline_health.js`.

- [x] **Offline reader refresh success** — Review “Saved reader refreshed and rechecked” alongside the resulting health status and freshness rows. Check changed/unchanged revisions and failed refresh/retry; retain enough acknowledgment to distinguish a finished refresh from stale results. Source: `public/assets/offline_health.js`.

- [x] **LLM Exchange return-link duplication** — Review “Back to LLM Exchanges” against the existing LLM Exchanges Tools subnavigation link on detail pages. Check keyboard and phone navigation, access restrictions and long expanded raw records before removing any route back to the list. Sources: `templates/pages/llm_exchange.php`, `templates/partials/tools_nav.php`, `src/ForumRewrite/Http/LlmExchangesController.php`.

## Retraction simplification — October 25, 2026

- Replaced the obsolete completed-retraction promise with concise repository deletion/delayed-cache/history limits. Removed the legacy label-review warning from accepted retractions; independent labels remain independently owned. Existing signing, partial refresh and recovery feedback remains visible.
- Verification: real browser author/operator/account-attribution flows and partial refresh/retry; all 13 themes at 390px/1280px without overflow, Word97 phone result screenshot inspected. Long embedded source-record/signature content wraps; surviving replies remain navigable. Keyboard activation, Escape/focus restoration and touch pass. Offline update cases 16/16; API retraction scenarios 18/18. No navigation/icon changes or dependencies. Local controlled browser checks only; physical devices and production pending. See [implementation summary](retraction_simplification/retraction_simplification_step4_implementation_summary.md#stage-8---acceptance-recovery-and-closeout).

## QDB historical Add and Search copy — October 25, 2026

- Restored the exact user-supplied Add guidance above the quote form and the Search verse above the search form, preserving its three explicit lines. This restoration is explicitly requested; the general preference for removing explanatory copy does not apply to these historical lines. No navigation, icons, behavior or shared compose copy changed.
- Verification: `QdbExperienceRoutingTest` passed (3/3). Chromium against isolated Apache with real templates/assets checked both pages at 375px/1150px in QDB, Light, Word97, Chicago and Midnight (20 page/theme/size cases). Verified exact rendered copy, Search line breaks, editable quote body, visible Post action, search submission and zero-result recovery, and no horizontal overflow. Inspected QDB phone Add and desktop Search screenshots. Temporary evidence: `/tmp/qdb-haiku-tests.log`, `/tmp/qdb-haiku-visual.log`, `/tmp/qdb-copy-{add,search}-{theme}-{width}.png`.
- Limitations: signed quote submission, populated/paginated search results, server failures and expanded compose options were not retested for these static paragraphs. No new dependencies; no `todo.txt` changes. Local changes only; deployment remains outstanding.

## Next phase — new candidates

These observations need rendered review before deciding what to remove. Keep this section at the end of the file.

- [ ] **SQLite Viewer loading feedback** — Review “Loading...” on the disabled Load Database button alongside “Loading the published read-model database...” in the live status. Check initial load and refresh, standalone and embedded views; retain an accessible pending signal, source freshness and actionable failures. Sources: `public/assets/sqlite_viewer.js`, `templates/pages/sqlite_viewer.php`.

- [ ] **System State repository head duplication** — Review “Short head” alongside the full Repository head and the abbreviated hash in Latest commit. Check long hashes, missing Git/history and stale read-model comparisons; preserve useful provenance and mismatch diagnosis. Source: `templates/pages/codebase_state.php`.

## 2026-10-24 — Current-content repository export wording

- Replaced full-history download descriptions with “Current content; Git history excluded.” Updated the backup scope notice to list Git history among separately backed-up data. Preserved image-pair revision guidance and private-data limitations; no extra confirmation or navigation change.
- Verified initial/loaded backup pages at 390/1440 widths in Light, Dark, Word97, Chicago and Midnight, with images present/absent (20 Chromium states), real styles loaded, visible download controls and no horizontal overflow. Inspected Midnight phone/Light desktop screenshots. Actual stale-revision failure followed by Backup refresh and successful TAR.GZ/ZIP downloads passed. BackupPageTest and affected LocalAppSmokeTest checks pass. No expanded state exists in the changed copy. Full suite, physical devices and deployment unverified.
- [Implementation and archive/import evidence](repository_content_export_one_shot_summary.md). Zero dependencies. Early temporary harness checks without fingerprinted assets were discarded and rerun through FrontController.

## 2026-10-26 — ZIP image backup

- Backup now labels its image download “Images (.zip)”. Instance, admins, generated time, snapshot, recent included items and existing sections remain intact; no navigation changes or extra UI explanations.
- Passed Chromium checks at 390/1280 widths across all 14 available themes, with populated, empty and long-value fixtures; no horizontal overflow. All three Backup aliases, four browser downloads and revision-mismatch refresh/retry passed. Visually reviewed Word97 phone and Midnight desktop screenshots (`/tmp/backup-zip-word97-390.png`, `/tmp/backup-zip-midnight-1280.png`). No expanded state exists in this change. Physical devices and deployment unverified.
- [Implementation and recovery evidence](image_backup_zip/image_backup_zip_step4_implementation_summary.md). Zero dependency delta.

## Archive export contention — 2026-10-26

Change: Download failures now distinguish 503 busy/retry, 409 refresh-and-pair, and 500 operator recovery. Raw exception paths are logged privately rather than returned publicly. Existing Backup warnings and links remain.
Verification: `node tests/browser/archive_export_browser.mjs` exercises normal Backup links, cold/warm downloads, two-viewports (390/1280) busy/conflict/retry and generic failure, extraction and overflow checks. Backup links checked in light, Word97, Chicago and Midnight; plaintext error responses are theme-independent. Phone/desktop screenshots inspected locally; no navigation destinations/icons changed. PHP route/import tests cover integrity repair and retry.
Limits: No physical-device, screen-reader or qdb.us deployment/recovery claim. No expanded/empty widget state added; those states are inapplicable to this plaintext download response change. Existing page layout otherwise unchanged.
Evidence: [Implementation summary](archive_export_contention/archive_export_contention_step4_implementation_summary.md); [operator recovery](../runbooks/archive_export_recovery.md).

## High-traffic toggle — 2026-10-27

- [x] Replaced the separate High-traffic mode card and its full-width “Enable/Disable high-traffic mode” button with one flag-style row beneath the Feature Flags heading: label, existing staleness line, shared switch and on/off state. Shortened the failure text while keeping reload and `./v3 high-traffic restore` visible. No navigation destination or icon change.
- Verification: `node tests/browser/high_traffic_control.mjs` passed real signed enable/disable, failure and keyboard retry, unauthorized absence, and 13 themes at 375/1150px without clipping or overflow; toolbar counts/search unaffected. Row screenshots inspected for light, dark, Word97, Chicago and Midnight in disabled, enabled and failure states at both widths. No physical-device or deployment claim.
- Evidence: [implementation summary](high_traffic_toggle/high_traffic_toggle_step4_implementation_summary.md).
- [x] Feature Flags toolbar: removed the visible “Search flags” label and “N of N flags shown” line at the user's request. The search input keeps “Search flags” as its accessible name; the count remains a screen-reader-only live region. Toolbar, High-traffic and Fastmod browser tests passed.
