# Feature Flags page redesign: implementation brief

Page: `/tools/feature-flags/` (template renders `<table>` with rows marked `data-feature-flag-row`, forms marked `data-feature-flag-form` posting `key` + value to `/tools/feature-flags/`).
Reference mockup: `feature-flags-redesign.html` (same folder). Match its layout and behavior, but use the site's existing CSS tokens, fonts, and dark-mode handling instead of the mockup's inline palette.

## Goals
- Readable at any width; no mid-word wrapping of keys.
- Scales to 50+ flags (grouping, search, filters).
- Surface only what deviates from normal (overrides, locks, broken dependencies).

## Changes

### 1. Replace the table with a grouped list
- Each flag is one row: **name** (bold, ink color) → **description** (regular weight, muted, max ~60ch) → meta line with the **key** in small mono (one line; `overflow-wrap:anywhere` as fallback only).
- Right side of the row: a switch (`<button role="switch" aria-checked>`) showing the **effective** value, with a small `enabled` / `disabled` label under it (green when enabled).
- Mobile (<520px): switch sits top-right of the row, text flows full width below.

### 2. Remove the Effective / Default / Source / Mutable columns
Show instead, only when relevant:
- `overridden` badge (amber) when effective ≠ default, with tooltip "Default is X", plus a **reset to default** link for mutable flags.
- `🔒 env` badge when not mutable, switch disabled, tooltip "Set via environment variable; restart to change". If the source value (e.g. `env`, `db`) is informative, put it in the tooltip.

### 3. Grouping
- Group by key prefix (`FORUM_`, `DEDALUS_`, `LLM_`, …). Support an optional `group` field on the flag definition that overrides the prefix, and an optional display-name map (e.g. `DEDALUS` → "Dedalus agent", `LLM` → "LLM exchanges").
- Group header: small uppercase label + "N of M on" on the right.

### 4. Dependencies
- Add an optional `requires: <FLAG_KEY>` field to the flag registry. Set it for:
  - `FORUM_EMOJI_AUTHORED_TEXT` → `FORUM_UNICODE_AUTHORED_TEXT`
  - `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED` → `DEDALUS_AGENT_REPLIES_ENABLED`
  - `LLM_CONVERSATION_UI_ENABLED` → `LLM_CONVERSATION_RECORDING_ENABLED`
  (Check the code to confirm each one really depends on the other before adding it.)
- Meta line shows "requires <Parent name>". If the parent is off, dim the row and show "⚠ inactive — requires …".
- Remove the dependency sentence from the Emoji description, since the UI now shows it.

### 5. Summary + toolbar
- Under the H1: "**9** flags · **4** enabled · **0** overridden · **4** locked by environment".
- Sticky toolbar: search input (matches name, key, description; `/` focuses it) + filter chips with counts: All, Enabled, Overridden, Locked. Filtering hides empty groups; show "No flags match." when nothing does.

### 6. Saving
- Progressive enhancement: each row stays a `<form method="post">` that works without JS (switch = submit button that posts the flipped value; reset = a submit button that posts the default). Keep existing CSRF handling.
- With JS: intercept submit, POST via `fetch`, update the row in place, show a brief toast ("X enabled · saved"). On error, revert the switch and show the error.
- Remove the `<select>` + Save button pair.

### 7. Small fix
- Tools sub-nav: "Feature Flags" currently wraps alone to a second line; let the nav items wrap evenly (or tighten padding) so it sits with the others.

## Constraints
- No new dependencies; plain CSS + vanilla JS (~50 lines), inline or in the existing asset pipeline.
- Keep `data-flag-key`, `data-role="feature-flag-effective"` and `data-role="feature-flag-source"` hooks (or update any tests that use them).
- Accessible: switches keyboard-operable with visible focus; badges have text, not just color.

## Done when
- Existing feature-flag tests pass (update selectors if needed); add tests for override/lock/dependency rendering and the no-JS POST path.
- Page checked at ~1150px and ~375px widths, light and dark themes.
