# Debug log: v3/bubblegum_menu_labels

## 2026-11-14T19:10Z, closeout (technically complete, push blocked)

- Mode: one-shot under `docs/agents/thread_to_pr.md`; debug enabled by `.railspike_debug_on`.
- Reproduction: no local app server was started; the live `/` and `/tools/` HTML was saved, hashed asset names rewritten to the repo's `public/assets/` files, and rendered in headless Chrome with Bubblegum set in local storage. This exercises the real stylesheets but not server-side theme selection.
- Cause 1: `.account-menu__links .nav-link::before` shared the Tools tile rule (32px, `margin: 0`) outside any media query, so the desktop dropdown got the phone tile icon with no gap; the `::after` status dot was also unscoped and sat on the pill's rounded edge.
- Cause 2: `.card { overflow-wrap: anywhere }` reaches the launcher buttons; with 5.5rem columns and 0.2rem padding the caption box (about 82-88px) was narrower than "Bookmarklets" (about 90px).
- Friction: `./v3 help` was refused by the session's permission check; `./v3 test` ran fine. Push to GitHub refused by the git proxy (repository not attached to the session); attaching needs the maintainer's approval.
- Pre-existing failure seen: `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` fails on `main` at `9122adf0`.
