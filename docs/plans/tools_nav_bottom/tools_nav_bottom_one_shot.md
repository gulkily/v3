# Tools bar at the bottom of tool pages

Cycle: `v3/tools_nav_bottom`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: disabled

Effort study: not enrolled. This record was written after two of the three PRs had merged, so no estimate before the work was possible.

Status: PRs #109 and #114 merged and deployed; the plain-heading PR (this branch) is open, awaiting maintainer review, merge and deploy.

## Original request

Thread: [this menu should be what's also on /tools/codebase/ and all...](https://zenmemes.com/threads/thread-20261103062812-321f286b), posted by guest on 2026-11-03: the Tools menu should be on System status and every other tool page, as an expandable menu at the top and/or the full menu at the bottom after scrolling (the bottom version would help demos). Feedback item: PF-20261104-F54.

## Authorization

Maintainer, 2026-11-09, after pasting the thread: "Just put the whole tools menu at the bottom of each tool page, and remove it from the top. I'm often annoyed by seeing the full list of tools when I just clicked through to get to a tool." Then: "can you add one more enhancement: a title for the tools module at the bottom of the pages", and, after asking whether the title matched the site: "please go ahead, don't make it a link". Under `docs/agents/thread_to_pr.md` this authorizes one-shot execution, pushing branches, opening PRs and replying in the thread. It does not authorize merging, deploying or production access.

## Outcome and work type

Application change. On every /tools/* page the Tools bar moves from the top to its own card at the bottom, titled "Tools" like any other card section. The /tools/ launcher is unchanged, since it is the list.

Excluded: an expandable menu at the top (the thread's other option; the maintainer chose bottom only).

## Approach and risks

- Move, don't duplicate: one bar per page, at the end of the page's `section.stack`, keeping each page's existing condition (SQLite viewer only when not embedded, Backup only when nav options are set, pending approvals only under access management).
- `feature_flags.js` swaps `.tools-nav` in place after a flag change; the bar's position doesn't matter to it, and the main nav (`nav.nav`) still comes first in the document.
- Title: a plain `<h2>Tools</h2>`. #114 first shipped a new `.tools-nav-heading` class with its own size and a link to /tools/; that was a new heading format and skipped the reuse step below. This branch replaces it with the site's ordinary card heading and no link (Tools is already in the main navigation).

## Reuse decisions

- Bar: `templates/partials/tools_nav.php` and `ToolsPageSupport::navOptions()`, unchanged apart from `role="navigation" aria-label="Tools"`.
- Card: the existing `.card` article, as every tool page already used for the bar.
- Heading: the plain card `<h2>` (47 uses in `templates/`, e.g. "Codebase" on System status), styled by the base and theme stylesheets. Not `.feature-flag-group-heading` or `.tool-launcher-group-heading`, which label groups inside one page.
- New: `templates/partials/tools_nav_card.php`, so 17 page templates share the card instead of repeating it. No new CSS.

## Completion conditions

- Normal entry: open any /tools/* page from the main navigation or the launcher.
- End-to-end: the page shows the tool first; the Tools card, titled "Tools", comes last.
- Release: PRs merged and deployed by the maintainer.

## Concepts touched

None changed. [Navigation slots](../../concepts/navigation_slots.md) used unchanged.

## Verification and recovery plan

Smoke set plus the affected tests (`LocalAppSmokeTest` asserts the bar and title come after the Backup heading). Render tool pages locally in Light, Word 97 and Dark at phone width. Check the live pages after deploy. Full suite and browser tests not run, per `AGENTS.md`.

Starting revisions and branch: #109 `tools-nav-bottom` from `3bfd4ac1`; #114 `tools-nav-title`; this fix `feat/tools-nav-bottom` from `08d4116d`.

## Dated execution history

- 2026-11-09: #109 moved the bar to a bottom card on 17 templates; `LocalAppSmokeTest` order assertion reversed. Merged and deployed.
- 2026-11-09: #114 added a title in a new shared partial, with a new `.tools-nav-heading` class (1rem, link to /tools/). Merged and deployed.
- 2026-11-09: maintainer asked whether the title matched the site and whether there are directives for that. It didn't: it was a new format, and the AGENTS.md reuse step and this record had been skipped. This branch makes it a plain `<h2>Tools</h2>`, removes the CSS and adds this record and the item update.

## Outcome

Delivered: bar at the bottom of every tool page, titled with the site's normal card heading. No known limitations.

## Verification

- `./v3 test --smoke LocalAppSmokeTest BackupPageTest AssetFingerprintTest`: 445 passed.
- Local render of System status: the "Tools" heading matches the "Codebase" heading in Light; in Word 97 it renders as the card's title bar.
- Live check after #109/#114 (2026-11-09, anonymous): on Outbox, Bookmarklets, User Access, Feature Flags, System status, SQLite Viewer, Backup, Fastmod, Operations and Deploy the bar follows the page's `<h1>`. Visitor Statistics and LLM Exchanges are access-gated for anonymous visitors and were not checked.

## Final review

Diff checked for leftover `.tools-nav-heading` references (none) and for scope (one partial, CSS removal, one test, this record, the index and the feedback item).

Commit/merge/publication status: #109 and #114 merged and deployed; this branch's PR open.

Attribution: Claude (claude-opus-5-5), 2026-11-09 UTC.
