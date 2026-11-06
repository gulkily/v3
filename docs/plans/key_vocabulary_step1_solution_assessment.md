> **Feature plan:** [Step 1](./key_vocabulary_step1_solution_assessment.md) · [Step 2](./key_vocabulary_step2_feature_description.md) · [Step 3](./key_vocabulary_step3_development_plan.md) · [Step 4](./key_vocabulary_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/key_vocabulary`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.50

Work item: [thread-20261106164113-8b520a73](https://zenmemes.com/threads/thread-20261106164113-8b520a73)

## Original Query

Under You, add a profile link to the current profile. Profile page should also link to the user page.

All of this needs to be refined so that it's easier to navigate the user vs profile vs account stuff, especially for a non-technical user.

Perhaps profile should be called session or device or ???

All of these things are available to rename.

## Understood Intent

From the conversation on 2026-11-06 UTC, the maintainer agreed to these limits:

- Rename only words people see. Routes, API names and `llms.txt` field names stay.
- Change only the You menu. The wider navbar rethink is a separate cycle.
- Moving the keys list on the user page into its own tab is a separate cycle.

Problem: People see five words for two things, so they cannot tell their username's page from their key's page or the page that manages this browser's key.

The two things are a person (a username with a page at `/user/<name>`) and a key (one keypair, with a public page at `/profiles/<slug>`). The Account page manages the key saved in this browser. Today the same key is called a profile, an identity, an account, a browser identity and a guest key.

Concepts touched: [viewer session](../concepts/viewer_session.md) (used unchanged; it already owns the word "session"); [initial naming](../concepts/initial_naming.md) (used unchanged; a name never replaces its key); [key retirement](../concepts/key_retirement.md) (used unchanged; retirement applies per key); [profile delivery](../concepts/profile_delivery.md) (changed: its pages get a new visible name); [navigation slots](../concepts/navigation_slots.md) (used unchanged; You items also appear in the phone More panel); [theme navigation icons](../concepts/theme_navigation_icons.md) (changed: new You items need icons); *key* (proposed: one entry naming the person/key split).

## Option A - Call it a key everywhere

**Estimate: 0.50**

Use one noun, "key", with the house-key picture: the username is the house, and each browser holds a key to it. The profile page becomes the key's page and links to its owner's user page. The user page lists "Keys" instead of "Identities". The Account page becomes "Key settings", and "Link identity" becomes "Add this key to my username". The You menu opens with your username, then "This browser's key", "Key settings" and Invite.

- People already know that a house has several keys, that keys can be copied, and that a key must not be shared.
- Matches how the system works: approval, retirement and post history already belong to each key.
- Touches many templates and messages; Step 2 must list every place the old words appear.

Driver: the sweep of visible words across pages, scripts and messages. Uncertainty: how many server and client messages say "profile" or "identity".

## Option B - Add the links, keep the words

**Estimate: 0.20**

Add the two requested links and leave every label alone. The You menu gains "Profile", and the profile page links to its user page.

- Smallest change; ships quickly.
- Does not fix the confusion the request describes. A non-technical reader still meets five words for two things.

Driver: the two links and their pre-paint fill. Uncertainty: low.

## Option C - Call it a device

**Estimate: 0.50**

Same scope as Option A, but the noun is "device": "This device", "Devices" on the user page, "Add this device to my username".

- Familiar from other sites' "signed-in devices" lists.
- Wrong in practice. A backup PNG moves a key into another browser, and agents hold keys in workspaces, not devices. The label would contradict the behavior.

Driver and uncertainty: the same as Option A.

"Session" is not an option. [Viewer session](../concepts/viewer_session.md) already uses it for the sign-in cookie, which is temporary; a key is not.

## Recommendation

Choose Option A. It fixes the confusion and its noun matches the system.

It is one vertical slice: the links, the menu and the new words ship together, so people learn one vocabulary once. The You menu shows your username and key, so a script must fill those links before paint from the key saved in the browser. A browser with no key shows "Set up this browser" instead. If Step 2 finds too many messages to rename, ship the menu and links first and the remaining messages next, on the same branch.

*— Claude · 2026-11-06 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-06T20:58:07Z, `railspike-effort` / `v3/key_vocabulary`, Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `a0fa5b9b10af9f624aa52fed4c59c7bb31352982`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Pristine intake missing: the Account page, the user and profile templates, the nav renderer and `member_approval.js` were read before rating. Intake and Step 1 projected total **0.50**. Option estimates 0.50, 0.20 and 0.50 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

- **Request:** move the keys list on the user page into its own tab. **Disposition:** separate work, agreed by the maintainer on 2026-11-06. **Approval status:** not started. **Outcome:** future cycle.
- **Request:** a smaller first step that adds a key count near the top of the user page. **Disposition:** declined by the maintainer on 2026-11-06. **Approval status:** none. **Outcome:** not planned.
