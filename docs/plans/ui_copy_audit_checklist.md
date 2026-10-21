# UI copy audit checklist

## Access history in User Access — October 21, 2026

- [x] Removed the detached Feature Flags paragraph and loose User Access audit link. Show five recent events in User Access, with concise summaries, expandable canonical evidence and conditional View all.
- [x] Keep legacy uncertainty and authentication failure/retry feedback; protect history with authenticated operator authorization and no-store. Hint-only operators get recovery; authenticated members (including forged operator hints) receive no protected history.
- [x] Actual Chromium flow: Tools/User Access → event evidence → full history/older page → return; empty history, grant/invitation/policy/legacy events, 111-event shared-date paging, expired-session signing/retry, invalid cursor, keyboard expansion, pending approvals and source links. Existing Forte retry/parity harness passes.
- [x] Loaded phone/desktop at 390/1280 px across all 16 themes, recent and expanded full history; no page overflow. Reviewed `/tmp/access-history-light-1280.png` and `/tmp/access-history-midnight-390.png`. Existing User Access launcher/subnav icons verified in Word97, Chicago and Midnight; no destination/icon mapping was added or renamed.
- Evidence: `node tests/browser/access_history.mjs`, `node tests/browser/forte_activity_parity.mjs`, 47 focused PHP tests. The original user's browser session was not inspected; isolated tests reproduce hint-only one-line 403 and empty authorized history separately. Non-Chromium browsers, production deployment and the full test suite were not run in this cycle.


## Browser identity ready notice — October 21, 2026 triage

- [ ] Remove the routine “Browser identity ready” notice and hide its empty status element; retain necessary pending/failure/recovery feedback and explicit Account results. [Triage and acceptance](browser_identity_ready_triage_2026-10-21.md).
- Verification so far: source inspection only; the message and compose initialization path remain in `public/assets/browser_signing.js`. Implementation, phone/desktop shared-view and theme checks, and relevant regression checks are pending. This item is not resolved.
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

- [ ] **Outbox** — Review repeated action summaries and technical timestamps,
  including “Action time (signed)” and “Integration time pending.” Check empty,
  draft, queued, published, failed, and retry states. Keep delivery uncertainty,
  unsaved work, and recovery actions clear. Source: `public/assets/outbox.js`.
- [ ] **Bookmarklets** — Review overlap among the introduction, installation
  instructions, group notes, and button descriptions. Check same-window and
  new-window groups, copy success/failure, and phone installation. Retain necessary
  setup, compatibility, and popup-blocking guidance. Source:
  `templates/pages/bookmarklets.php` and its browser feedback.
- [ ] **Fastmod** — Review the enabled-score summary against checked controls,
  repeated selection instructions, and whether full prompts need to appear by
  default. Check operator/read-only views, settings failures, and save feedback.
  Preserve the meaning of scores, the minimum-selection requirement, and access
  recovery. Source: `templates/pages/fastmod.php` and its browser feedback.
- [ ] **Offline reading** — Review repeated “Showing saved…” success feedback
  and overlapping missing-content notices. Check saved board, tags, thread,
  unavailable content, queued likes, and reconnect outcomes. Retain snapshot scope,
  freshness limitations, and actionable recovery. Sources:
  `templates/pages/offline_reader.php`, `public/assets/offline_reader.js`.
- [ ] **Thread/post analysis** — Review expanded analysis for overlapping
  summaries and technical fields that do not help the reader make a decision.
  Check root/reply cards, post detail, and affected alternate views, including
  pending, failed, and completed states. Preserve useful attribution and moderation
  consequences. Source: `templates/partials/post_card.php` and shared feedback.
- [ ] **Feature Flags** — Review descriptions, state badges, save feedback,
  and operator-access explanations for duplication. Check search/no matches,
  enabled/disabled/overridden flags, and save failure/retry. Keep site-wide effects
  and flag-specific consequences beside their controls. Sources:
  `templates/pages/feature_flags.php`, `public/assets/feature_flags.js`.
- [ ] **Users empty state** — Review whether the explanation following “No
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
