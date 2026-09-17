# Forte New Thread Dialog Status — Step 3: Development Plan

## Stage 1
- Goal: New Thread dialog has an always-visible status line, reading "Ready" by default, that updates through the existing signing/identity progress and error messages during submission.
- Dependencies: none (Step 2 approved).
- Expected changes:
  - `templates/partials/paned_board_new_thread_dialog.php`: add a status element (`data-role="compose-identity-status"`) between the titlebar and the compose form, visible by default with initial text "Ready".
  - `public/assets/forte.css`: style the new status line to fit the dialog's Windows-chrome look; reuse/extend the reply panel's existing `.paned-compose-status` styling where it fits, without changing that class's hidden-by-default behavior for the reply panel itself.
- Verification approach:
  - Open the New Thread dialog and confirm the status line is visible and reads "Ready" before any interaction.
  - Submit a thread successfully and confirm progress text (e.g., "Preparing signed thread...") appears before navigation away.
  - Force a failure (e.g., no browser key configured) and confirm the error message renders and stays visible in the dialog.
  - Confirm the reply panel's status line is unaffected (still hidden until its first message).
- Risks or open questions:
  - `setStatus()` unconditionally sets `node.hidden = false` and overwrites text/kind on every call — confirm no flicker/race between the default "Ready" text and the first real status update.
  - Confirm reusing `.paned-compose-status` styling doesn't accidentally change the reply panel's hidden-by-default behavior, since both status lines share the same selector convention.
- Canonical components/API contracts touched: `[data-role="compose-identity-status"]` element + `setStatus()` in `browser_signing.js` (both existing and unchanged).
