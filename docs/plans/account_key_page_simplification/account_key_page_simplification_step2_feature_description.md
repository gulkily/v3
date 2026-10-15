> **Feature plan:** [Step 1](./account_key_page_simplification_step1_solution_assessment.md) · [Step 2](./account_key_page_simplification_step2_feature_description.md) · [Step 3](./account_key_page_simplification_step3_development_plan.md) · [Step 4](./account_key_page_simplification_step4_implementation_summary.md)

Debug mode: enabled

## Problem
`/account/key/` shows far more than most visits need, and the one frequently used item, the fingerprint, is not on the page at all. Keys are disposable here, so backup and restore are edge cases, but technical users still need quick access to keys and storage.

## User stories
- As a member, I want to see my name and fingerprint the moment the page loads so that I can confirm and share my identity without clicking through to my profile.
- As a member, I want one obvious action and nothing else competing for attention so that I can set up or reset my identity quickly.
- As a technical user, I want keys and storage one click away at the same level as backup and restore so that I can inspect and verify them.
- As an occasional user, I want backup and restore to remain available but out of the way so that they do not clutter normal visits.

## Core requirements
- Default view is the status card only: state, username, fingerprint with a copy action, primary action, and clear identity. The profile link stays.
- Three sibling, collapsed expandos sit below it with equal weight: Back up or restore, Keys, and Storage and privacy. No outer wrapper.
- Every current capability remains reachable; duplicate controls are consolidated and explanatory copy that restates the obvious is removed.
- `#key-backup` links (reminder, resume) still land on and open the backup expando.
- The fingerprint is read from the same browser value the profile link already uses; key handling, signing, storage, and server behavior are unchanged.

## Delivery scope
- Work type: application change (template, page-scoped styles, and the page's inline script; the shared signing script only if a consolidated control requires it).

## Completion boundary
- **Entry:** `/account/key/` opened directly, from nav, or via a backup-reminder or resume link.
- **Outcome:** a visitor sees name and fingerprint immediately, sets up or resets their identity, and reaches backup/restore, keys, or storage in one click; a deep link to `#key-backup` opens backup/restore.
- **Recovery:** clear-with-undo, restore from backup, and status/error messages behave as before; with no key yet, the fingerprint area shows a clear empty state.
- **Release:** full test suite and the key backup browser test pass; visual checks across themes at desktop and 375px.

## Risks
- **Removed control still needed** — Impact: a consolidated button breaks the script or a test. Earliest validation: Stage 1 hook-contract update lists which hooks are intentionally removed. Mitigation: remove only controls whose action another control reaches; keep the hook until the script is updated.
- **Stale or wrong fingerprint** — Impact: card shows a fingerprint that does not match the active key after setup, restore, or clear. Earliest validation: manual and browser check across set up, restore, and clear. Mitigation: update it from the same identity-change events the page already listens to; show an empty state when no key.
- **Deep link regression** — Impact: `#key-backup` opens nothing inside a nested or collapsed element. Earliest validation: follow the reminder link in the browser test. Mitigation: keep the anchor on the backup expando and open it from the hash.
- **Hidden-too-far for technical users** — Impact: keys and storage are missed behind three headers. Earliest validation: Step 3 review of labels. Mitigation: plain, parallel labels and the same level as backup.

## Shared component inventory
- `templates/pages/account_key.php`: canonical page and inline script; extended in place (card, fingerprint, expandos).
- `public/assets/identity.css` (`.account-key-*`): extended, page-scoped; prior group/section styles replaced.
- `public/assets/browser_signing.js` and `key_backup_ui.js`: own key and backup behavior; reused unchanged unless a consolidated control needs a hook.
- Profile link and `forum_pki_fingerprint` value: reused as the fingerprint source; no new storage.
- `partials/feedback.php`, `key_backup_reminder.php`, resume and lobby links: unchanged entry points.
- No new component needed.

## Simple user flow
1. Open `/account/key/`; see name, fingerprint, and one action.
2. Copy the fingerprint, or open the profile link.
3. Set up, rename, or clear the identity from the card.
4. Open one of the three expandos only when needed: back up or restore, inspect keys, or review storage.

## Success criteria
- Name and fingerprint are visible and copyable without any click.
- The default page contains only the status card and three collapsed expandos.
- Every pre-existing capability is still reachable within one expando click; existing tests pass.
- No horizontal scroll at 375px; readable contrast in all themes.
