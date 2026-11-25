# Debug log — v3/private_access_card_settle

## 2026-11-25T10:45Z — One-shot closeout

### Necessary/useful instructions

- `AGENTS.md` (host), reuse: `pending_approvals.js` was the model but is tied to its table, so its behaviour was followed rather than shared; the card's markup gained hooks, no new component or CSS.
- `AGENTS.md` (host), UI copy: one status line replaces the row's "Confirmed"; recorded in the UI copy checklist.
- Implementation standards, verify the reported condition: the browser test renders the real partial with seven keys, close to the reported nine.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Navigation icons, read-model schema rules, feature flags.

### Uncertain assessments

- Themes other than the default were not looked at; the change uses only existing styles.
- The browser test used playwright-core found in the environment (`/opt/node-tools`), linked as an untracked `node_modules`.
