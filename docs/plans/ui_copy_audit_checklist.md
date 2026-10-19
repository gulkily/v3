# UI copy audit checklist

Reduce reading required to complete ordinary forum tasks. Favor removing text over rewriting or relocating it, relying on an intuitive, explorable interface. For every finding below, first consider deleting the explanation; suggestions to shorten text or move it into help are fallbacks only when users need information the interface cannot communicate. Keep material consequences and recovery actions beside the affected control.

Source review dated October 17, 2026 covered page templates, shared partials, and selected first-party browser messages. These are copy and presentation findings; rendered layouts and every runtime state have not been reviewed. Completion notes and verification below track the subsequent cleanup.

## First fixes

- [x] **Invite generation** — Removed policy mechanics; kept link-sharing and existing-account access warnings, with expiry beside the result. Verified phone/desktop rendering and issuance, redemption, and public/private zero-click browser flows. Commit: `30c4ab6e`.
- [x] **Public backup downloads** — Deleted repeated restoration explanations and preview instructions. Kept backup exclusions, matching image/repository guidance, and the index caveat. Phone/desktop rendering and five backup scope/image checks passed. Commit: `cafd7c64`.
- [x] **Activity security notice** — Removed the repeated policy explanation from both activity views; kept current policy status. The Feature Flags control retains “Existing approvals remain valid.” Phone/desktop classic and Forte rendering plus seven approval history/policy checks passed. Commit: `882de3b6`.
- [x] **Profile diagnostics** — Deleted the redundant self-profile card. Technical diagnostics had already moved to User Access. Phone/desktop self-profile rendering and nine profile/access checks passed. Commit: `aa8c3cde`.

## Other cleanup

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
- [ ] **Root approval evidence** — [root_approval.php](../../templates/pages/root_approval.php). Missing authorizer/acceptance-time information is stated twice around repository history. Consolidate it near the evidence; keep the distinction between commit dates and acceptance dates. This is an evidence page, so technical detail has a clearer purpose here.
- [x] **Image attach control** — [image_compose_fields.php](../../templates/partials/image_compose_fields.php), compose menus. The standalone “Choose an image” button became “Attach image” in the `…` menu of thread and reply forms; the error text no longer says “Choose an image”.

## Verify each fix

- [ ] Read the rendered flow on desktop and mobile, including relevant empty, success, failure, and conditional states. Confirm the main action is easy to find.
- [ ] Remove text that repeats visible controls or state. Check shared partials and browser/server feedback so the same explanation does not return through another source.
- [ ] Confirm concise copy still explains material access, privacy, data-loss, and recovery consequences at the relevant decision. Keep useful help accessible without relying on hover alone.
- [ ] Update copy assertions affected by the edits and run the relevant existing checks. Mark individual findings complete only after their UI changes are verified.

## Member directory / access split

- [x] Standard Users and member/profile subpages — removed approval controls, key inventories/history, and technical diagnostics; retained public status/attribution, messaging and contextual User Access recovery links.
- [x] Tools User Access — concise account/queue links; existing policy consequences and signing failures remain beside actions. Technical key details stay expandable. Standard legacy history anchors and pagination retain a path to Tools.
- [x] Forte compatibility — retained its combined directory/access UI; shared history links remain within Forte. No Forte copy cleanup included in this cycle.
