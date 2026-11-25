# owa_resume — debug log

## 2026-11-25T07:40Z — closeout

- Instructions used: AGENTS.md (one-shot, first-paint rule, feature flags, tests, effort study); `nav_cached_copy.mjs` as the browser-check shape.
- Wrong assumption in the plan: that `pwa_registration.js` already detected standalone mode; it does not, corrected in the record.
- Friction: in the browser check, closing a page fires `pagehide`, which overwrote a hand-written stale entry; fixed by writing it with the next page's init script.
- Open: real-iPhone behavior on relaunch after swipe-away.

*— Claude · 2026-11-25 UTC*
