> **Feature plan:** [Step 1](./account_key_page_redesign_step1_solution_assessment.md) · [Step 2](./account_key_page_redesign_step2_feature_description.md) · [Step 3](./account_key_page_redesign_step3_development_plan.md) · [Step 4](./account_key_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

## Problem
`/account/key/` gives a few essential actions (set up, back up, restore) the same weight as a dozen technical controls, so newcomers cannot tell their state or next step, while technical users cannot easily see what is stored where or which actions are destructive.

## User stories
- As a new visitor, I want to see whether this browser is set up and what to do next so that I can start posting without reading technical detail.
- As a member, I want backup and restore to be prominent and clearly explained so that I do not lose my identity.
- As a technical user, I want to inspect, copy, and link my keys and see what lives in this browser versus the server so that I can verify the system rather than trust it.
- As any user, I want destructive actions separated and recoverable so that I do not clear or expose a key by accident.

## Core requirements
- Status-first hierarchy: state, username, profile link, and one primary action are visible before any technical content.
- Backup and restore remain a first-class section with encrypted text and Starfield PNG, and their guidance on passphrases and PNG fragility is preserved.
- All current capabilities remain: guest-publication setting, load/restore/copy/clear key actions with undo, public-key link form, public/private key viewers, identity hint, and private-site auth status.
- Technical content is grouped by intent (public key, private key, storage/identity, destructive actions); private-key display and copy are visually distinct from public-key actions with a plain warning.
- Existing behavior and hooks are unchanged: no key-handling, signing, storage, or server changes; the page works at 375px, in every theme, and by keyboard.

## Delivery scope
- Work type: application change (template and page-scoped styles; script touched only if a moved control requires it).

## Completion boundary
- **Entry:** visitor opens `/account/key/` directly or from the lobby, resume, or backup-reminder links.
- **Outcome:** a new visitor sets up a browser key, backs it up, and later restores it; a technical user views, copies, links, and clears keys, with undo still available.
- **Recovery:** clear-with-undo, restore from backup, and status/error messages work as before.
- **Release:** existing account-key tests pass, with visual checks across themes at desktop and 375px.

## Risks
- **Hook breakage** — Impact: moved controls stop responding to the script, status mirroring, or tests. Earliest validation: inventory selectors before Step 3 and run the identity tests after the first markup stage. Mitigation: keep data attributes and IDs; restructure around them.
- **Accidental key exposure or loss** — Impact: private key is revealed or cleared more easily than before. Earliest validation: review the destructive and private-key grouping in Step 3. Mitigation: reveal is explicit, clear stays behind undo, and no new default exposure.
- **Theme/contrast regressions** — Impact: unreadable status or tabs in some themes. Earliest validation: theme matrix check on first styled stage. Mitigation: theme tokens only, scoped to this page.
- **Hidden-by-default content** — Impact: technical users miss details if collapsed too far. Earliest validation: Step 3 content-visibility review. Mitigation: keep one labeled section with clear headings rather than burying controls.

## Shared component inventory
- `templates/pages/account_key.php`: canonical page; extended in place.
- `public/assets/identity.css` (`.account-key-*`, `#key-backup` tabs): extended, page-scoped.
- `public/assets/browser_signing.js`: owns key behavior; reused unchanged.
- `templates/partials/key_backup_reminder.php`, lobby, and authentication-resume links: unchanged entry points that must still land correctly.
- `partials/feedback.php`: reused for notices/errors.
- No new component needed.

## Simple user flow
1. Open `/account/key/`; see state and the primary action.
2. Set up the browser and choose a name; status updates to ready.
3. Open Back up, set a passphrase, and download text or PNG.
4. On another device or after clearing data, open Restore and load the backup.
5. Technical users open the details section to inspect or copy keys, link a public key, or clear (with undo).

## Success criteria
- A new visitor identifies their state and primary action without opening any section.
- Every pre-redesign capability remains reachable and behaves identically; existing tests pass.
- Private-key and destructive actions are separated from public-key actions.
- No horizontal scroll at 375px and readable contrast in all themes.
