# UI copy audit checklist

Reduce reading required to complete ordinary forum tasks. Favor removing text over rewriting or relocating it, relying on an intuitive, explorable interface. For every finding below, first consider deleting the explanation; suggestions to shorten text or move it into help are fallbacks only when users need information the interface cannot communicate. Keep material consequences and recovery actions beside the affected control.

Source review dated October 17, 2026 covered page templates, shared partials, and selected first-party browser messages. The original review identified copy and presentation findings without rendered-state verification. Per-item notes below record the subsequent implementation and checks.

## First fixes

- [x] **Invite generation** — Removed policy mechanics; kept link-sharing and existing-account access warnings, with expiry beside the result. Verified phone/desktop rendering and issuance, redemption, and public/private zero-click browser flows. Commit: `30c4ab6e`.
- [x] **Public backup downloads** — Deleted repeated restoration explanations and preview instructions. Kept backup exclusions, matching image/repository guidance, and the index caveat. Phone/desktop rendering and five backup scope/image checks passed. Commit: `cafd7c64`. Core-route assertion follow-up: `49af02c1` (passed).
- [x] **Activity security notice** — Removed the repeated policy explanation from both activity views; kept current policy status. The Feature Flags control retains “Existing approvals remain valid.” Phone/desktop classic and Forte rendering plus seven approval history/policy checks passed. Commit: `882de3b6`.
- [x] **Profile diagnostics** — Deleted the redundant self-profile card. Technical diagnostics had already moved to User Access. Phone/desktop self-profile rendering and nine profile/access checks passed. Commit: `aa8c3cde`.

## Other cleanup

- [x] **SQLite query prerequisite message** — Removed the initial database-loading explanation; retained the live status element for query feedback. PHP syntax and diff checks passed.
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
