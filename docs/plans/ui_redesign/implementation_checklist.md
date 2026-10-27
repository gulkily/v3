# UI redesign: implementation checklist

A backlog of cycles for building the redesign and its theme from the mockups.
It is a planning input, not a Railspike artifact. Each cycle is marked with
the process it uses (see below); Railspike cycles still go through Railspike's
steps with an explicit approval at each gate, and may be reshaped during their
own Step 1 or Step 2. Context and decisions are in [next_steps.md](next_steps.md).

## Process per cycle

Decided by the user on 2026-10-26. The mockups and this checklist already
cover much of Railspike's problem framing and requirements, so Railspike is
kept where there are real unknowns or high stakes. Marking a cycle **Direct**
is the user's explicit Railspike opt-out for that cycle under `AGENTS.md`.

| Process | Cycles | Reason |
|---|---|---|
| **Railspike** (start with Step 1, solution assessment) | 1, 5, T1 | Open design choices: a new navigation surface across every theme (1), key generation and signing (5), registering a day/night theme (T1). |
| **Railspike, no Step 1** (start with Step 2) | 3, 4, 8, T2, D1, D2, D3 | Clear direction but risky behavior: the docked reply box on phones, backups, encrypted message failures, hooks into decryption and verification, read-model schema changes, security UX. |
| **Direct** (no Railspike) | 2, 6, 7, 9, T4 | Rearranging markup and wording on screens whose behavior already exists, or a small settings change (T4). |
| **Decide when scheduled** | T3, D4 | Optional or deferred. |

For a **Direct** cycle:

- Work on its own branch (no direct merges to `main`).
- Use this checklist's items for the cycle as the spec; record any change of
  scope here rather than silently expanding it.
- Apply every check in [Checks that apply to every cycle](#checks-that-apply-to-every-cycle),
  and record UI verification in `docs/plans/ui_copy_audit_checklist.md` or a
  short note in this folder.
- Commit when the cycle's "Done when" is met. Direct cycles are not part of
  the `railspike-effort` study, which only collects Railspike cycles.

Sizing follows Railspike's rules for every cycle, Direct ones included: each is
a releasable vertical slice (normal entry, end-to-end outcome, required
recovery) that fits within a day or eight Step 3 stages. The item lists below
are suggestions for Step 3 (or the spec, for Direct cycles), not approved plans.

Mockup references are relative to [mockups/](mockups/). Template paths are
from [plan_prompt.md](plan_prompt.md#interface-checklist).

## Before the first cycle

- [x] Decide the theme: **Ma first**; Living Sky optional and unscheduled ([next_steps.md](next_steps.md#decisions), 2026-10-26).
- [x] Decide default versus opt-in: **opt-in** until ready; cycle T4 makes it the default.
- [x] Decide fingerprint colors: **deferred** to cycle D4.
- [x] Member preview: **skipped**, no regular members yet.
- [x] Decide the six round 1 product proposals (2026-10-26; see [next_steps.md](next_steps.md#decisions), item 5).

## Checks that apply to every cycle

Copy these into each Railspike cycle's Step 3 verification and Step 4 summary; Direct cycles apply them before committing.

- [ ] Rendered flow checked on phone and desktop in initial, loaded, empty, success, failure and expanded states (`CONTRIBUTING.md`, UI copy and verification).
- [ ] Page overflow checked with real content, long values and pagination.
- [ ] Every registered theme checked on the changed screens, not only Light and Dark (`src/ForumRewrite/View/ThemeRegistry.php`, `docs/runbooks/theme_development_guide.md`).
- [ ] Navigation changes mapped in `public/assets/theme-nav-icons.css` for Word97, Chicago and Midnight, including Tools buttons and subnavigation, and checked visually in each (`AGENTS.md`).
- [ ] Copy assertions in tests updated for removed or changed strings; copy cleanup recorded in `docs/plans/ui_copy_audit_checklist.md`.
- [ ] No new third-party dependency without approval under `CONTRIBUTING.md`.
- [ ] Theme hooks for the screen added (listed per cycle), inert in existing themes.
- [ ] Changed shared static assets go through the repository's existing asset fingerprinting (hashed copies such as `browser_signing.<hash>.js` exist in `public/assets/`).
- [ ] Any new motion animates only `transform` or `opacity` and stops under `prefers-reduced-motion`.

## Phase 1: Base structure

Wording, arrangement and flow from round 1, theme-independent. Each cycle also
adds its screen's theme hooks.

### Cycle 1: `ui_shell_navigation`

**Process: Railspike.** Step 1 compares options: the phone tab bar is a new
navigation surface, and moving rare destinations into the account menu has
alternatives.

App shell and navigation. Mockup: `shell.html`. Templates: `templates/layout.php`,
`templates/partials/nav.php`, `templates/partials/theme_menu.php`.

- [ ] Top bar with three destinations (Board, Users, Messages), one New post, and an account menu.
- [ ] Rare destinations (Tools, About, Invite, theme choice) move into the account menu.
- [ ] Phone bottom tab bar with a central compose button; About replaces Messages for guests and unapproved members.
- [ ] Theme menu as swatches inside the account menu.
- [ ] Hooks: `data-tab` on navigation items, an empty `aria-hidden` sky layer as the first child of the app shell, an empty `.mark` slot in the wordmark.
- [ ] Icon themes: tab bar and account menu icons mapped for Word97, Chicago and Midnight.
- Done when: every page uses the new shell in every theme, with no lost destination.

### Cycle 2: `ui_board_list`

**Process: Direct.**

Mockup: `board.html`. Templates: `pages/board.php`, `partials/thread_card.php`.
Depends on cycle 1.

- [ ] Denser thread rows with separate filter (All / Liked) and sort (Newest / Oldest / Top) controls.
- [ ] Pinned threads grouped above Latest.
- [ ] Designed empty state for Liked.
- [ ] "Show older threads" pager.
- [ ] "(unapproved)" restyled as a "Pending" chip, still shown to everyone (decided 2026-10-26).
- [ ] Hooks: `--person` on each row (from the author's avatar color), `pinned-list` class, star icon for Like.
- Done when: browsing, filtering, sorting and paging work in all themes.

### Cycle 3: `ui_thread_reply_dock`

**Process: Railspike, no Step 1.**

Mockup: `thread.html`. Templates: `pages/thread.php`, `partials/thread_root_card.php`,
`partials/post_card.php`, `partials/reply_form.php`. Depends on cycle 1.

- [ ] Reply box docked to the bottom of the viewport, fixing the reported off-screen textarea.
- [ ] Tag-only posts (`#pin`, `#pinned`) shown as one-line activity entries instead of empty cards.
- [ ] Consecutive posts by one person joined into one block.
- [ ] Agent status shown in place ("Writing a reply…"), and agent failures explained without a Retry that cannot work.
- [ ] Hooks: `data-sky` (original poster) on the page, `--person` on posts and replies.
- Done when: reading, liking, replying and the agent states work on phone without the keyboard hiding the reply box.

### Cycle 4: `ui_account_states`

**Process: Railspike, no Step 1.**

Mockup: `account.html`. Template: `pages/account_key.php`.

- [ ] Three clear states: guest, set up and waiting for approval, approved.
- [ ] "Not backed up" warning at the top until a backup exists.
- [ ] Back up as a sheet with passphrase and format choice (text file or Starfield image).
- [ ] Advanced key controls (keys, linking, storage) moved behind Advanced.
- [ ] Record the last backup date in the browser to drive the warning (decided 2026-10-26).
- [ ] Hooks: `data-tone` on rows, a starfield slot and `night` class on the account card, `data-sky` on the page.
- Done when: a member can see their state, back up, restore and reach advanced controls in all themes.

### Cycle 5: `ui_first_post_identity`

**Process: Railspike.** Step 1 compares options for when and where keys are
generated.

Mockups: `compose-thread.html`, `account.html`. Templates: `pages/compose_thread.php`,
`partials/thread_compose_form.php`, `pages/account_key.php`, plus the browser
signing scripts. Depends on cycle 4.

- [ ] Name chosen inline (composer and account page); no `window.prompt` or `window.confirm`.
- [ ] Key generation starts when the composer or account page opens, so Post only signs and sends.
- [ ] A visible state while the key is created ("Creating your account…"), with recovery if it fails.
- Done when: a new visitor can write and publish a first post with no browser dialogs and no long wait after pressing Post.

### Cycle 6: `ui_composers`

**Process: Direct.**

Mockups: `compose-thread.html`, `compose-reply.html`. Templates:
`pages/compose_thread.php`, `pages/compose_reply.php`. Depends on cycle 5.

- [ ] One shared composer component: title, body, tools.
- [ ] Reply composer as the no-JavaScript fallback with parent context.
- Done when: new threads and replies post from both pages, with and without JavaScript.

### Cycle 7: `ui_profile_users`

**Process: Direct.**

Mockups: `profile.html`, `users.html`. Templates: `pages/profile.php`,
`pages/username.php`, `pages/users.php`.

- [ ] One person page with a Message button; keys listed as identities.
- [ ] Searchable compact member list with pending approvals beside it.
- [ ] Hooks: a banner slot (`.starfield.banner`) with `data-seed`, `--person` on the profile head.
- Done when: finding a member, opening their profile and messaging or approving them works in all themes.

### Cycle 8: `ui_messages`

**Process: Railspike, no Step 1.**

Mockups: `messages.html`, `conversation.html`. Templates: `pages/private_messages.php`,
`partials/private_message_list.php`, `pages/private_message_conversation.php`,
`partials/private_message_composer.php`.

- [ ] Conversation list with two panes on desktop and an explanation for members not yet approved.
- [ ] Bubbles, a plain-language encryption line, and per-message failure recovery (verify, confirm, retry).
- [ ] Previews decrypt progressively, with a per-row "Couldn't decrypt preview" and Retry.
- [ ] Hooks: `--person` on the conversation, a starfield slot on the first-message screen, a hook on incoming messages for the decrypt effect.
- Done when: reading, sending and recovering from each failure work on phone and desktop.

### Cycle 9: `ui_tools_launcher`

**Process: Direct.**

Mockup: `tools.html`. Templates: `pages/tools.php`, `partials/tools_nav.php`.

- [ ] Tools grouped by who uses them (Your device, Reading, Community, Operator), each listed once.
- [ ] Hooks: a stable `data-tone` key per tool in `src/ForumRewrite/Tools/ToolsPageSupport.php`, `data-shape` on group headings.
- [ ] Icon themes: Tools launcher buttons and subnavigation mapped in `theme-nav-icons.css`.
- Done when: every tool is reachable once, in all themes.

## Phase 2: Theme hooks

There is no separate cycle. Hooks ship with the base cycle for their screen
(listed above). Before Phase 3, confirm the full set exists:

- [ ] Sky layer in the app shell (decorative, `aria-hidden`).
- [ ] `data-tab`, `data-tone`, `data-shape`, `data-sky`, `data-person` / `--person`.
- [ ] Avatar `data-cut` and `--av-rot` from a hash of the name (or fingerprint, per decision 3).
- [ ] Starfield slots with `data-seed`: profile banner, account card, identity thumbnails, first-message screen, backup format choice.
- [ ] Empty `.mark` slot in the wordmark; `.hud` caption slots on account cards.

## Phase 3: The theme

Ma was chosen (2026-10-26) and ships opt-in. Living Sky (T3) is optional and unscheduled.

### Cycle T1: `ma_theme_day`

**Process: Railspike.**

Ma in light mode. Step 1 decides how a theme with a day and a night look
is registered (one `auto` theme that follows the system, or separate "Ma" and
"Ma Night" entries), given `ThemeRegistry`'s one-mode-per-theme entries.

- [ ] Register the theme in `src/ForumRewrite/View/ThemeRegistry.php` and update `tests/ThemeRegistryTest.php`.
- [ ] `public/assets/theme-ma.css`, adapted from `mockups/zen/ma.css` to the real templates.
- [ ] Paper surfaces and grain, the mincho serif stack (system fonts only), vermilion accent.
- [ ] Ando light shaft, Turrell light field in the page's person color, teamLab petals.
- [ ] Ensō-face logo and seal; Fukuda loaders and the empty-Liked face.
- [ ] Tanaka avatars (flat circles with a per-name cut); shapes on navigation and Tools groups.
- [ ] Flow-field generator as first-party JavaScript (from `mockups/mockup.js`), drawn once per element, for the account card, profile banner and identity thumbnails.
- [ ] Profile skyspace banner with the person's flow field and the slow dusk glow.
- [ ] Contrast verified against the values in [visual_direction_notes.md](visual_direction_notes.md) (muted text 4.7:1 or better, tool glyphs 3:1 or better).
- Done when: Ma can be chosen from the theme menu and every redesigned screen renders correctly in it on phone and desktop.

### Cycle T2: `ma_theme_night`

**Process: Railspike, no Step 1.**

Ma's cyberpunk dark mode. Depends on T1.

- [ ] Night tokens; neon logo and seal with a one-time flicker (no more than 3 flashes a second; none under reduced motion).
- [ ] Rain in place of petals, still following the community pulse once cycle D1 exists; neon light shaft.
- [ ] Flow fields as neon on the account card and banner.
- [ ] ID-chip account cards with captions of real key data; "Signatures verified" only after verification succeeds.
- [ ] Decrypt scramble on incoming messages, hooked into the real decryption step; screen readers get the final text; skipped under reduced motion.
- [ ] Glitch only on short failure labels; long error text gets a split neon edge.
- [ ] Holographic agent; monospace system font for times, counts and fingerprints.
- Done when: night mode renders every redesigned screen with the night contrast values met (cyan 12.6:1, neon 5.4:1, muted text 5.7:1 or better).

### Cycle T3 (optional, unscheduled): `living_sky_theme`

**Process: decide when scheduled.**

- [ ] Register and build `theme-living-sky.css` from `mockups/zen/zen.css`.
- [ ] Measure frosted-glass cost on a low-end phone; fall back to solid surfaces under `prefers-reduced-transparency` or on low-end devices.
- Done when: Living Sky is selectable and scrolls smoothly on the test phone.

### Cycle T4: `ma_theme_default`

**Process: Direct.**

Only when we decide Ma is ready to become the default (decision 2: opt-in until then).

- [ ] Make the chosen theme the default for ZenMemes; keep the previous default selectable.
- [ ] Check Auto, legacy aliases and other sites' permitted theme lists.
- Done when: new visitors get the new theme and existing choices are preserved.

## Phase 4: Features that need data

Each needs its own product decision before its cycle starts.

### Cycle D1: `community_pulse`

**Process: Railspike, no Step 1.**

- [ ] One cached count of posts in the last 24 hours, mapped to `data-pulse` (`quiet` / `steady` / `busy`) on the app shell.
- [ ] Prefer existing read-model queries; if a schema change is needed, follow the read-model rules in `CONTRIBUTING.md`.

### Cycle D2: `profile_constellation`

**Process: Railspike, no Step 1.**

- [ ] Per-identity post and like counts drive the profile artwork (one star per post, brighter for liked posts), capped.
- [ ] Likely a read-model schema change: bump `ReadModelMetadata::SCHEMA_VERSION`, update `docs/specs/read_model_schema_v1.md`, add the rebuild regression test, and verify `./v3 rebuild`.

### Cycle D3: `post_seals`

**Process: Railspike, no Step 1.** Step 2 must state the security rule: a seal
appears only after verification succeeds.

- [ ] A seal per author key, generated from the key (not the per-post signature).
- [ ] Shown only after the post's signature verifies; never a placeholder seal.
- [ ] Generator keeps lookalike seals apart, including for color-blind users.

### Cycle D4 (deferred): `fingerprint_colors`

**Process: decide when scheduled.**

- [ ] Person colors and avatar cuts derived from the key fingerprint instead of the name.
- [ ] Decide what happens when a person changes keys.

## Not yet planned

Decided against or deferred on 2026-10-26: Unlike (not implemented), Undo
after Approve (skipped), offline message queue (deferred), hiding Tools groups
the viewer cannot use (deferred), fingerprint colors (cycle D4, deferred).

From the graded idea list in [visual_direction_notes.md](visual_direction_notes.md#round-4-ideas--amplifying-living-sky-2026-10-25-discussion-only),
still unbuilt even as mockups: time-of-day sky (A4), planet flight between
screens (A5), kintsugi for edits and resends (B3), the closing ensō for reply
progress (B1), riso meme thumbnails (B6, risky), and bolder titles for busy
threads (B7, needs font approval). Add a cycle here if any is chosen.
