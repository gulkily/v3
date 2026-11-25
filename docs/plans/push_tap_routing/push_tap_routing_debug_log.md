# push_tap_routing — debug log

## 2026-11-25T01:55Z — closeout

- Instructions used: AGENTS.md (one-shot, feedback search, smoke tests only, effort study); the handoff `push_tap_routing_handoff.md`; Railspike `one_shot.md`; the `owa_start_url` record as the one-shot shape.
- Essential: the handoff's "decide early" list. Reading `post_card.php` showed the handoff's premise (no reply anchor on thread pages) was wrong, which settled the URL question without the maintainer; checking `supportsOfflineNavigation()` showed its Unread fallback could not work offline.
- Useful: `tests/browser/push_preferences.mjs` already stubbed the worker under `vm`, and asserted the old focus-only tap; `OfflineNavigationWorkerTest` gave the node harness shape.
- Friction: browser tests import `playwright-core`, which is not installed in the repository; a symlink outside the repository to the copy bundled with `@playwright/mcp` let them run.
- Missing: nothing in the repository says whether `WindowClient.navigate()` works in an iPhone home-screen app; the fallback covers both answers and the phone check decides which path runs.

*— Claude · 2026-11-25 UTC*
