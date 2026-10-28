> **Feature plan:** [Step 2](./ui_account_states_step2_feature_description.md) · [Step 3](./ui_account_states_step3_development_plan.md) · [Step 4](./ui_account_states_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_account_states` ([ledger](../effort_estimation_trial.md#cycle-v3ui_account_states)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.95**

Cycle 4 of the [UI redesign checklist](../ui_redesign/implementation_checklist.md#cycle-4-ui_account_states), [account mockup](../ui_redesign/mockups/account.html). Process: Railspike without Step 1 (decided 2026-10-26). User request: "move to the next task" (2026-10-28).

User decisions (2026-10-28): **all sites** get the new page; **one cycle** for the whole checklist item; guest naming by **name field + Continue**, no prompt. Revised the same day after the user pointed out that the first draft treated the keypair as the account: in v3 the user is the **name**, and approved keys sharing a name are shown as one user ([profile read contract](../../specs/profile_read_contract_v1.md#username-collision-handling)).

## Key model this page must reflect

- A key is cheap: browsers may prepare a `guest` key automatically, and anyone can start a new key at any time.
- The **user** is the name. `/user/<name>` and `/users/` combine all approved keys that use that name; approval is granted per key, and an unapproved key with the name is shown separately until a member approves it.
- Losing a key costs that key's approval (a new key under the same name is pending until approved), private messages encrypted to it, and signing as that key (for example retracting its posts). Posts stay under the name.

## Problem

The account page shows one "Account Key" card in every state, with raw keys and storage details beside everyday actions, and says nothing about the name being the account. A first redesign draft overcorrected toward a traditional single-key account (a red "account is gone" warning, set-up before posting, "you won't post as mouse again"), which is untrue in v3.

## User stories

- As a guest, I want to see that I can already post as guest and optionally pick a name, so that naming isn't a gate.
- As someone picking a name that approved members already use, I want to know my posts join that user once approved.
- As a member, I want to see my user and this browser key's state (waiting or approved), and reach all of my posts across keys.
- As an approved member, I want to save a copy of this key so that another device or a cleared browser doesn't need re-approval and keeps my messages.
- As a member removing this key, I want to know my posts stay under my name and what a new key would need.
- As an experienced member, I want keys, linking and storage details under Advanced.

## Core requirements

- **States, right on first paint** (no flash of the wrong one): **Guest** ("You're posting as guest", optional name field + Continue, Use a key from another device), **Waiting for approval** (name; "This browser's key: waiting for approval"; "You can post now…"), **Approved** (name; "This browser's key: approved"; link to all of the name's posts at `/user/<name>`).
- **Save a copy** prompt only when this key is approved: a calm line explaining it avoids re-approval and keeps messages, with "Save a copy"; after a download it reads "Copy saved Oct 28" for this key. No red warning; nothing for unapproved or guest keys.
- **Everyday rows:** Name (edited in place when username changes are enabled; says this key's posts move to the new name and, for a name approved members already use, join that user once approved), shortened fingerprint with Copy, Save a copy of this key, Use a key from another device, Advanced (keys, linking, storage), Remove this key.
- **Save-a-copy sheet:** passphrase, confirmation, a strength hint, format (Text file or Starfield image), one Download. **Remove this key** confirmation: posts stay under the name; a new key with the name is pending until approved; offers "Save a copy first" when the key is approved; Undo stays. One Remove replaces the two duplicate clear actions.
- Errors inline in their row with a recovery action ("Couldn't publish your key. Retry"); without JavaScript the Link a public key form still works.

## Delivery scope

- Work type: application change.
- Includes the account page for all sites (ZenMemes, Chouse, MIT Rap Club, QDB), its title changing from "Account Key" to "Account", its script, the account actions in `browser_signing.js`, the backup UI and its per-key record (gaining a date), styles, hooks (`data-tone` on rows, a starfield slot and `night` class on the account card, `data-sky` on the page; inert), tests, the copy record, `docs/reference/key_backups.md` wording, this cycle's plans and ledger bookkeeping.
- Excludes avatars, new third-party code (the strength hint is a small built-in check), changes to how keys, names, approval or aggregation work, a list of the user's other keys on this page (the `/user/<name>` link covers it), and the navigation label (already "Account").

## Completion boundary

- Normal entry: `/account/key/` on each site as guest (automatic guest key on and off), unapproved key and approved key, phone and desktop, including approved-members-only sites.
- Outcome: a guest can post without naming, or names the key and lands in Waiting for approval; a member sees their name and key state, reaches all of the name's posts, saves a copy, uses a key from another device, renames, reaches Advanced, and removes the key after confirming.
- Recovery: inline Retry for a failed publish or rename; Undo after Remove; a new key with the same name rejoins the user after approval; the sheet and dialog close with Cancel or Escape without side effects.
- Release: all permitted themes per site at phone and desktop in each state with the sheet and dialog open; existing account, backup, approval, invite and private-site browser tests pass, updated for wording only.

## Risks

- **Wrong state on first paint.** Impact: a member briefly sees the guest card, or an approved key shows as waiting. Earliest validation: browser test per state with a cold load. Mitigation: choose the state before paint from browser storage and the server's view of the signed-in key, as the profile link does.
- **Prompt-free naming and rename break existing flows.** Impact: invite, approval and private-site sign-in rely on today's set-up. Validation: run `account_key_approval`, `testing_self_approval`, `invite_zero_click`, `invite_signing_readiness`, `key_backup_ui`, `access_history` in the first stage. Mitigation: keep the action hooks; change only how the name is collected.
- **Copy misstates the key model.** Impact: people over- or under-value saving a key. Validation: review each state's text against the profile contract and `authentication_modes.md` in Step 3; the "join that user" hint only when the name already has approved keys. Mitigation: state consequences per key (approval, messages, signing), never "account gone".
- **All sites change at once.** Impact: an approved-members-only site or QDB regresses. Validation: per-site checks in each stage. Mitigation: one shared page; site differences stay where they are today.

Noted, not changed: with username changes enabled (default off), an approved key can rename to another user's name and be grouped with that user; this is existing behavior of the read-side aggregation.

## Shared component inventory

- `pages/account_key.php` and its inline script: **extend** (one page for all sites).
- `browser_signing.js` account actions (`generate-browser-key`, `rename-profile`, `clear-browser-identity`, `undo-clear-browser-key`): **reuse**; the name comes from the page instead of `window.prompt`.
- `key_backup_ui.js`: **extend** into the sheet. `key_backup_status.js`: **extend** its per-key record with a date; its Messages reminder keeps working.
- `/user/<name>` aggregate and the username lookup in the read model: **reuse** for the link and the "join that user" hint.
- Pending/Approved chip style (cycle 2): **reuse**. `account.css`, `identity.css`: **extend**.

## User flow

1. A guest opens Account: "You're posting as guest". They type "mouse" and press Continue; approved members already use "mouse", so the page says this key's posts join mouse once approved.
2. The card shows "mouse · This browser's key: waiting for approval". Later it shows "approved" and "All of mouse's posts".
3. Approved, they see "Save a copy of this key to skip re-approval on another device and keep your messages", save a Text file copy; the line becomes "Copy saved Oct 28".
4. They tap Remove this key; the dialog says their posts stay under mouse and a new key would be pending until approved; they confirm, and Undo appears.

## Success criteria

- Each state renders its own card on first paint on every site, in every permitted theme, phone and desktop.
- Naming and renaming complete without any browser prompt; guests can post without naming.
- The save-a-copy prompt appears only for approved keys without a saved copy, and no text claims the account is lost with a key.
- Remove always asks first; Undo restores the key.
- Existing account, backup, approval, invite and private-site browser tests pass.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-10-28T16:23:12Z | 0.70 | v1 / R1 / P2, baseline `main` `cba7d54f9` | Claude, single exposed rater; authored the mockup and checklist; rated before inspecting code for this cycle | Cycle 4 as listed in the checklist |
| Step 2 handoff | 2026-10-28 | 0.95 (revised) | v1 (whole checklist item, all sites, name-first set-up) / R1 / P2 | Same, after reading `account_key.php`, the account actions in `browser_signing.js` and `key_backup_status.js` | All sites and the prompt-free rename add per-site checks and test churn; the sheet, dialog and first-paint state are larger than the checklist suggested; the existing backup record and duplicate clear actions offset a little |
| Step 2 revision (key model) | 2026-10-28 | 0.95 (reaffirmed) | v1, reframed: name is the account, key per browser; no scope added or removed beyond the `/user/<name>` link and the join hint / R1 / P2 | Same, after reading the profile read contract and `authentication_modes.md` | Simpler backup condition (approved keys only) offsets the join hint and the docs wording update |
| Step 2 approved | 2026-10-28 | 0.95 | v1 / R1 / P2 | Same | "Approved Step 2"; no scope change |

Step 1 skipped (not applicable, not zero). R1 hashes verified 2026-10-27.
