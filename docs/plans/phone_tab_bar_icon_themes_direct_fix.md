# Phone tab bar in icon themes (Direct work, 2026-11-02)

Problem: On a 375px phone with six tabs (signed-in: Board, Recent, Users,
Messages, New post, You), the Word 97, Chicago and Midnight tab bar overflowed.
Each 16px nav icon sat beside its label in a 56px tab, so the Users icon landed
on "Messages", "Messages" was clipped and "New post" wrapped. Light and other
text-only themes fit at the same width. Reported from an iPhone running Firefox.

Reuse decision: The icons come from the shared sheet
`public/assets/theme-nav-icons.css` (keyed to nav hrefs; loaded for every theme,
rules scoped to the three icon themes). The base phone tab bar lives in
`public/assets/site.css` under `@media (max-width: 639px)`. The overflow is
caused only by the icon sheet, so the fix is one phone-width block appended to
that sheet: tab items become a column (icon above label), label at 0.7rem, icon
margin reset, "New post" kept on one line. No markup change
(`templates/partials/app_header.php`, `templates/partials/nav.php` untouched),
no per-theme duplication.

Verification (Chrome, prod HTML with the local icon sheet substituted):
- iPhone 8 viewport, six tabs: zero horizontal overflow in Word 97, Chicago,
  Midnight; light unchanged. Screenshots reviewed for all three icon themes.
- 1280px desktop: tab widths identical before and after (media query only).
- `tests/browser/nav_active_theme_styles.mjs` passes (16 themes).
- `tests/browser/tab_bar_scroll.mjs` passes (phone hide/show, desktop unchanged).
- `./v3 test Word97ThemeCoverageTest` passes.
- Not verified on a physical iPhone / WebKit; no WebKit build available locally.

Follow-ups noticed, not changed:
- Desktop: the Recent icon has no right margin and touches its label
  (`theme-nav-icons.css`, `[href="/recent/"]::before` lacks `margin-right`).
- `FORUM_APP_VERSION_NOTIFICATION` is off on prod because `/api/version`
  returns the content repository's git head, which changes with every post.
  Tracking the code checkout's head instead would make the reload banner fire
  only on deploys.
