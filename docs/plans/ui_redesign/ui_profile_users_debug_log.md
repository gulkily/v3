# ui_profile_users debug log

Process notes for cycle 7 (`ui_profile_users`, a **Direct** cycle: no Railspike steps or Railspike debug mode). Kept in the same shape as Railspike debug logs so the findings can be compared.

## 2026-10-28 — Inconsistent look: one-off components instead of existing ones

### What happened

The first build of the Users and person pages added new styles (`.users-tabs` pills, `.user-rows` lists) instead of reusing components from earlier cycles: the board's All | Liked filter and thread rows (cycle 2), and Account's grouped rows (cycle 4). The user noticed that the pages looked "rough around the edges compared to other pages".

### Where the information existed, and why it didn't reach this cycle

- **Railspike Key Rules:** "Prefer shared components/API contracts first; reuse or extend instead of forking markup, CSS, or payloads". **Step 2** also requires a *shared component inventory*, which in cycle 5 caught every composer.
  - Why it was missed: Direct cycles are the user's Railspike opt-out (`implementation_checklist.md`, "Process per cycle"), so neither the rule nor the inventory step applies to them.
- **`implementation_checklist.md`, "For a Direct cycle":** says to use the checklist items as the spec and to apply the every-cycle checks.
  - Why it was missed: neither the Direct rules nor "Checks that apply to every cycle" mention reusing existing components or avoiding one-off styles.
- **`CONTRIBUTING.md` (UI copy and verification):** covers first paint, overflow, themes and copy.
  - Why it was missed: it has nothing on component reuse.
- **The mockups** draw every list with the same `.group` / `.row` / `.seg` pieces, which signals a shared vocabulary.
  - Why it was missed: no document maps those mockup pieces to the real components already built (`.board-filter`, `.thread-row`, `.account-group` / `.account-row`). Each cycle has to rediscover the mapping.
- **The agent's own process:** cycle 6 reused the composer partials because the cycle was about them. Cycle 7 began from the mockup and the templates, not from a search for components built in earlier cycles.

### Suggestions (advisory; changing project guidance is separate, authorized work)

1. Add a line to "For a Direct cycle": *Before building, list the existing components each changed screen will use, and reuse or extend them instead of adding one-off styles.* This is the lightweight version of Railspike's Step 2 inventory.
2. Add an every-cycle check: *No new one-off component styles where an existing component fits; shared components live in shared CSS, not page CSS.*
3. Keep a short component map in `docs/plans/ui_redesign/`, from mockup piece to real component (for example `.seg` → `.board-filter`, `.group > .row` → `.account-group > .account-row`, thread list → `partials/thread_row.php`), and update it whenever a cycle adds one.
4. Treat the grouped-row and card styles in `account.css` as shared, and either move them into `site.css` or name them neutrally (for example `.group` / `.row`). This cycle loads `account.css` on the Users and person pages instead, which works but ties those pages to a stylesheet named for Account.

### Outcome

Fixed within the cycle (user: "go ahead").
- The tabs use `.board-filter`.
- Member rows and Identities use `.account-group` / `.account-row`.
- The person heading uses `.account-card` / `.account-id`.
- Threads use `partials/thread_row.php`.
- The one-off `.users-tabs` / `.user-rows` / `.person-head__main` CSS is gone; only page layout remains.

Verification: `users_profile`, `account_states`, `profile_public_details`, `access_history` and `board_compose_focus` pass. The theme × site matrix (59 combinations, 390px and 1280px) found no automatic problems, and the contact sheets now match the board and Account. The person page, Users and Waiting pages were checked at phone size. See the [checklist entry](implementation_checklist.md#cycle-7-ui_profile_users).
