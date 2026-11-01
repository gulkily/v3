# Word 97 theme repair checklist

Date: 2026-11-01
Status: in progress on branch `fix/word97-theme-repair`; each fix lands as its own commit, recorded on the item.

Screenshots of the board, Users and /user/ pages under `word97` showed the
theme no longer holds together. The cause is coverage, not regression: three
recent changes added components that `public/assets/theme-word97.css` never
styles, so they fall back to the generic variables (grey panel on grey desktop,
rounded corners, pill chips).

- App shell header (`site-header--app`, `site-brand`, `new-post-button`,
  `account-menu`): commits 4ace73ab7, 650fe55d9 (2026-10-27).
- Paged thread list board (`board-toolbar`, `board-filter`, `thread-rows`,
  `thread-row`, `section-label`, `chip`): commit 1e536cffb (2026-10-27).
- Users and person pages built from Account's `account-card`,
  `account-group`, `account-row`, `account-avatar`: commit bb95e3d57 and the
  users rebuild (2026-10-28).

No sibling theme (Chicago, Midnight, ...) covers these selectors either; this
checklist is scoped to Word 97 but the gap should be recorded for them.

## Header (app shell)

- [x] (a4f8870e4) Wordmark text is blue on the navy title bar. `:root[data-theme="word97"] a { color: #0000ff }`
      outranks `.site-brand { color: inherit }`. Add a theme rule for `.eyebrow a` (white, no underline).
- [x] (a4f8870e4; full-width title bar over a toolbar row) Wordmark title bar is cramped: icon, text and the fake min/max/close controls overlap
      because `.eyebrow` is now a `flex: 0 0 auto` item on one row. Either give it a min-width
      and gap, or (better for the theme) make the title bar span the full header width with the
      nav as a toolbar row beneath it, as Word 97 lays out title bar then toolbar.
- [x] (a4f8870e4; ruler on its own line) Stray vertical bar after the You button. The theme's `.site-header::after` ruler is still
      generated inside the flex app header and collapses to a 2px-wide flex item. Give it
      `flex-basis: 100%` so the ruler sits on its own line under the toolbar, or drop it in the app shell.
- [x] (bb9abf9d5) New post is a flat navy block and You is a white box. Both should be bevelled Win95
      buttons (`#c0c0c0`, white/grey inset bevel); New post may keep a bold label.
- [x] (no change needed: `theme-nav-icons.css` already squares it for icon themes) `.account-menu__icon` is a round ring (`border-radius: 50%`); square it or hide it behind the
      existing pixel icon from `theme-nav-icons.css`.
- [x] (bb9abf9d5; verified open at 1400 and 390) Verify: `.account-menu__panel` uses a soft blurred drop shadow. Match the theme menu popover
      (hard 3px offset shadow, bevel, no blur). Not in the screenshots; check when fixing.
- [x] (bb9abf9d5; raised strip, pressed current tab) Verify: phone tab bar (max-width 639px) under word97. Not in the screenshots.

## Board (list slot)

- [x] (commit 3: list board) Filter toolbar (All / Liked / Newest / Tags) floats on the bare grey desktop. Style
      `.board-toolbar` as a toolbar strip: grey surface, bevelled edge, aligned with the header.
      The sunken active state on "Liked" is right; keep it. Same fix covers Members / Waiting
      on Users and Threads / Replies on /user/, which reuse `.board-filter`.
- [x] (commit 3: list board; white document inside the window frame) `.thread-rows` has no window chrome while the composer card above it has the full frame
      and hard shadow. Decide one treatment. Recommended: render the rows as the white Word
      "document" page (white background, inset bevel), since titles are already serif underlined
      links; the composer stays a floating window.
- [x] (commit 3: list board; title bar pulled over the frame; verified with pinned threads on ?view=all) `.section-label` ("Pinned", "Latest") is soft uppercase text on the desktop. Turn it into
      the title bar of its rows group, or hide it when the frame carries a title.
- [x] (commit 3: list board; white surface, #c0c0c0 separators) Grey hairlines between rows vanish on the grey panel (`--line: #808080` on `#c0c0c0`).
      Resolved by the white document surface above; otherwise use the `#dfdfdf` / `#808080`
      etched-line pair.
- [x] (commit 3: list board; Times inside the document) Row metadata mixes Times (author link) with Tahoma (date, reply count). Pick one per
      surface: Times for everything inside the document area, Tahoma only for chrome.
- [x] (commit 3: list board; flat grey square fields) `.chip`, `.chip--pending`, `.chip--approved` are rounded pills with coloured outlines.
      Square them (`border-radius: 0`), black 1px border, grey fill, Tahoma 0.72rem; drop the
      colour tint or keep it only on the text.
- [x] (no change needed: the density toggle only renders on `board.php` and `tag.php`, where `.thread-card` still exists; the list board has no toggle, so nothing to port) Dead rules: the theme's `[data-thread-density="compact"] .thread-list .thread-card`
      block targets the old card board. The list board renders `.board-list .thread-rows`, so
      the density toggle does nothing under word97. Port the compact rules to `.thread-row`
      or remove them.
- [x] (commit 3: list board; #404040 on white) Excerpt line (`.thread-row__excerpt`) is `--ink-soft` grey on grey; verify contrast after
      the surface change.

## Users page

- [ ] `.users-page__title` "Users" is a Times h1 on the bare desktop. Make it the title bar of a
      frame that encloses the tools and list, or remove it (the nav already says Users; the UI
      copy rule favours removal).
- [ ] `.account-group` has `border-radius: 0.75rem`. Square it and give it the sunken white
      ListView look (white background, inset bevel), which also fixes grey-on-grey rows.
- [ ] `.account-row--link::after` renders a "›" chevron. Hide it in word97; rows are plain
      underlined links or ListView rows with navy selection.
- [ ] `select` in `.users-tools` (the Activity sort) is not covered by the theme's
      `input, textarea` bevel rule and renders with platform styling. Add `select` to the
      sunken rule, or style it as a raised button with the pixel dropdown arrow already used
      by `.theme-menu__trigger`.
- [ ] Search input placeholder is Times while the select is Tahoma; align to Tahoma for form
      chrome.

## Person page (/user/...)

- [ ] `.account-card.person-head` is a rounded grey card with no window chrome. Square it and
      apply the `.card` window treatment with the username as the title bar.
- [ ] `.account-avatar` is a round navy circle with an initial. Replace with a square 32px
      bevelled tile, or hide it in word97.
- [ ] `.person-identities__title` "Identities" is a Times h2 on the desktop; same fix as the
      Users title (title bar of the identities frame).
- [ ] Identities `.account-group` rows: same rounded-corner and chevron fixes as Users.
- [ ] "Manage access" sits as a bare link under the list; fine as a link, but place it inside
      the frame or a status line rather than on the desktop.
- [ ] Verify: `.starfield.banner` is hidden only while empty. If the client fills it, a starfield
      paints behind the Win95 card. Hide it explicitly in word97.

## Cross-cutting

- [ ] The theme has three greys (`--page-bg`, `--panel`, `--line`) that new components rely on
      for contrast. Add a content-surface override (white document area) in the theme file rather
      than per-component patches where possible.
- [ ] Reuse: all fixes belong in `theme-word97.css`; do not add word97-specific rules to
      `site.css` or `account.css`. Check that the `.card` window treatment can be shared with
      `.thread-rows`, `.account-card`, `.account-group` via a shared selector list instead of
      duplicating the shadow stack.
- [ ] Verification: extend the theme matrix screenshot test (added in ui_tools_launcher stage 3)
      to the list board, Users and /user/ pages under word97 at desktop and phone widths. Check
      Chicago and Midnight once for the same components and record the gap for them.
