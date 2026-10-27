# UI redesign — plan-prompt (mockup phase)

Date: 2026-10-24 · Branch: `ui-redesign-mockups`

**Scope guard:** this task produces plans and mockups only. No application
code, templates, CSS or tests change. Railspike and its approval steps are
skipped for this task at the user's request; work proceeds unattended.

## Prompt

Act as a senior Apple design team. Redesign the most-used ~20% of the forum's
interfaces so they feel calm, legible and direct: content first, one obvious
primary action per screen, quiet chrome, generous spacing, system typography.
Produce one static HTML mockup per interface (desktop + phone, light + dark).
Mockups are design artifacts only; implementation into `templates/` and theme
CSS is a separate, separately approved step.

## Decisions (confirmed by the user 2026-10-24)

- **Focus:** wording, arrangement and process flow. Themes handle the
  visual skin; the mockups use one neutral system-styled skin only so the
  structure is readable. Every recommendation must survive being re-skinned
  by any theme (no layout that depends on a specific palette or font).
- **Usage basis:** best guess, accuracy not required. No production
  analytics were available locally (`state/high_traffic/` is empty).
  Selection is by nav prominence, link graph from the Board, and core forum
  jobs (read, post, reply, identify, message).
- **Theme scope:** themes stay as they are; implementation must keep their
  scoped overrides working.
- **Site scope:** default site (zenmemes) only.
- **Location:** `docs/plans/ui_redesign/` (this file and `mockups/`), plus a
  private published gallery of the mockups.
- **Dependencies:** none. System font stack, inline SVG icons, no web fonts,
  no CDN, no frameworks. Dependency delta: zero.
- **Copy:** per `AGENTS.md`, remove text rather than rewrite it. Keep
  material warnings (private key exposure, unapproved/pending state, data
  loss, encryption scope) beside the decision they affect.
- **Behavior parity:** every existing control on a mocked screen must have a
  home in the mockup, even if moved into a menu. Anything dropped is listed
  in that screen's notes.

## Design principles

1. **Content is the interface.** Remove boxes around everything; separate
   with whitespace and hairlines, not borders on every card.
2. **One primary action per screen**, filled accent button; everything else
   is secondary (tinted or plain).
3. **Hierarchy through type, not chrome.** System font, 17px body, clear
   title / headline / footnote scale. Metadata is secondary-gray and short
   ("ilyag · 2h"), with full timestamps on hover/long-press.
4. **Navigation you never think about.** Desktop: slim top bar with
   wordmark, five destinations, search-free; phone: bottom tab bar
   (Board, Users, Messages, Account, More).
5. **Segmented controls for filters** (All / Liked; Newest / Oldest / Top),
   not rows of identical buttons.
6. **Progressive disclosure.** Rarely used fields (event, subject override,
   agent/operator actions, keys) behind clearly labeled disclosure rows.
7. **States are designed:** empty, pending/unapproved, error with recovery.
8. **Accessible by default:** 44pt touch targets, WCAG AA contrast in both
   modes, focus rings, no hover-only information.

## Interface checklist

Selected 11 of 53 page templates (~21%). Each item: mockup file under
`docs/plans/ui_redesign/mockups/`.

- [x] 1. **App shell & navigation** — header, nav, theme menu, phone tab bar
      (`templates/layout.php`, `partials/nav.php`, `partials/theme_menu.php`) → `shell.html`
- [x] 2. **Board** — thread list, filters, quick compose, pinned, unapproved
      (`pages/board.php`, `partials/thread_card.php`) → `board.html`
- [x] 3. **Thread** — root post, replies, continuations, Like/Flag, agent
      reply, inline reply (`pages/thread.php`, `partials/thread_root_card.php`,
      `partials/post_card.php`, `partials/reply_form.php`) → `thread.html`
- [x] 4. **New thread composer** — body, subject & tags, event, image, more
      menu (`pages/compose_thread.php`, `partials/thread_compose_form.php`) → `compose-thread.html`
- [x] 5. **Reply composer** — standalone reply with parent context
      (`pages/compose_reply.php`) → `compose-reply.html`
- [x] 6. **Profile** — user header, stats, approvals, threads/posts
      (`pages/profile.php`, `pages/username.php`) → `profile.html`
- [x] 7. **Users directory** (`pages/users.php`) → `users.html`
- [x] 8. **Account & identity** — status, set up browser, username, backup /
      restore, keys, storage & privacy (`pages/account_key.php`) → `account.html`
- [x] 9. **Messages inbox** — conversation list, unread, auth-required state
      (`pages/private_messages.php`, `partials/private_message_list.php`) → `messages.html`
- [x] 10. **Message conversation** — bubbles, encryption note, composer,
      retry states (`pages/private_message_conversation.php`,
      `partials/private_message_composer.php`) → `conversation.html`
- [x] 11. **Tools launcher** (`pages/tools.php`, `partials/tools_nav.php`) → `tools.html`
- [x] Gallery index linking all mockups → `index.html`

Deferred (next 20%): Tags index/tag page, Activity, About, Invites, Lobby,
Outbox, Forte.

## Acceptance for each mockup

- Desktop (1280) and phone (390) frames on one page; light/dark toggle.
- Realistic content from the local dev repository (real thread titles,
  usernames, long values).
- Shows at least one non-happy state (empty, pending, error).
- No horizontal page overflow at 390px; wide content scrolls in its container.
- Notes section with three lists — **Wording** (before → after, removed),
  **Arrangement** (what moved where), **Flow** (steps removed/merged, new
  states) — plus the template/partial each change touches.

## Implementation handoff (not part of this phase)

- Land design tokens as the new `theme-light.css` / `theme-dark.css`
  variable blocks plus a shared `site.css` restructure; verify every novelty
  theme still renders (theme guide: `docs/runbooks/theme_development_guide.md`).
- Phone tab bar is a new navigation surface: update
  `public/assets/theme-nav-icons.css` for Word97, Chicago and Midnight.
- Follow `CONTRIBUTING.md` UI verification (phone/desktop, all states,
  overflow) and record results in `docs/plans/ui_copy_audit_checklist.md`.
- Update copy assertions in tests that reference removed strings.

## Status — 2026-10-24

All 11 mockups and the gallery index are complete in `mockups/` (open
`mockups/index.html`). No application code, templates, CSS or tests changed.
Dependency delta: zero (the local screenshots used the already-approved
`playwright-core` devDependency; nothing was added).

### Most important findings

1. **First post flow is the biggest problem.** Posting as a new user triggers
   a native `window.prompt("Choose your username:")`, then a visible wait for
   key generation and publication (`browser_signing.js` identity states). Users
   reported this on the board ("new account bootstrapping is unacceptably
   slow"). Mockups 4 and 8 move the name inline and pre-generate the key.
2. **Navigation costs two rows on phone** before any content shows. Mockup 1
   moves it to a bottom tab bar and sends rare destinations to the account menu.
3. **The thread reply box goes off screen on focus** (reported on the
   board). Mockup 3 docks it to the bottom of the viewport.
4. **Tag-only posts (#pin, #pinned) render as empty reply cards.** Mockup 3
   shows them as one-line activity entries.
5. **The Tools page lists every tool twice.** Mockup 11 groups them once.
6. **Losing an account is one cleared cache away, and nothing warns about
   it.** Mockup 8 keeps a "Not backed up" warning at the top until a backup
   exists.

### Proposals that need a product decision (marked "Proposal" in the notes)

- Show the "Pending" chip only to the author and to approvers (board).
- Unlike: needs a reaction-retraction record and a spec change (thread).
- Undo after Approve: delay publishing the signed approval record ~5 s (users).
- Record the last backup date locally to drive the backup warning (account).
- Queue private messages to the Outbox while offline (conversation).
- Hide Tools groups the viewer can't use (tools).

### Verification

- Each page was rendered headless at 1400px light and 390px dark: no console
  errors and no horizontal page overflow (fixed one flex-shrink overflow).
- Each page's stage was screenshotted and reviewed. Fixed: a clipped ··· menu,
  hidden tile badges on phone, a cut-off "Not sent" state, and nav for unapproved viewers.
- Current-UI copy quoted in the notes was checked against templates and JS.
  Corrections made during review: "Top" sorts by score (it is not "most
  liked"); the reply page already shows parent context; usernames are not
  unique, so there is no "Available" check; flags carry author identity; the
  agent allowance failure needs an operator, so no Retry button is shown.
- Limitations: no real usage data; theme interaction is only described, not
  rendered; mockups are static (menus and dialogs shown open as separate states).
