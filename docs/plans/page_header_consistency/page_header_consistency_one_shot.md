# Consistent page titles

Cycle: `v3/page_header_consistency`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: disabled

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: disabled (no real alternatives or work units; see Approach)

**Cycle estimate: 0.40 (intake)**

Status: implemented and verified locally; PR open, awaiting maintainer review, merge and deploy.

## Original request

Thread: [the headers are still inconsistent across pages...](https://zenmemes.com/threads/thread-20261110165756-f4ce2a5b), posted by ilyag (maintainer) on 2026-11-10, with screenshots of Board, Recent, Calendar, Users, Unread, Messages, Search, About, Tools, Account, Invites and a thread page. Summary: the page headers are inconsistent and all look bad; Board and Recent look fine with no header ("maybe this is the way?"); Calendar's title looks accidentally placed; Unread, Invites and the thread page have an extra blank line above the title; About has a large filled square; Search looks too bare. Feedback item: PF-20261110-B01.

## Authorization

Maintainer, 2026-11-10, in a Claude session after Claude reviewed the thread and proposed the rule "pages in the nav get no visible title; pages outside it get one title, always done the same way", plus the per-page fixes listed below: "yes, i agree with that rule. go ahead please." Under `docs/agents/thread_to_pr.md` this authorizes one-shot execution of that outcome, pushing the branch, opening the PR and replying in the thread. It does not authorize merging, deploying or production access.

## Outcome and work type

Application change (templates and CSS).

1. Main-nav pages (Board, Recent, Calendar, Users, Unread, Messages, Search, About, Tools launcher): no visible page title. The active nav item names the page. Each keeps an `<h1>` for screen readers, visually hidden.
2. Pages outside the main nav (Invites, Tools subpages, compose, and similar): keep the existing convention, the title as the first heading of the first card. About 40 templates already do this, and pixel themes (Word 97, Midnight) render it as the window title bar. Revised at inspection from "on the page background like Account"; see the history.
3. No blank line above the first heading in a card.
4. Thread page: when the thread title is only the start of the body, show the body without repeating it as a heading.
5. Calendar: the month navigation joins the calendar card; the card that held only the title and Earlier/Later goes.
6. Messages: no card inside a card; the list sits on the page like Recent's.
7. About: no stray square before the title.

Excluded from this cycle and listed as follow-ups: Account's title, which stays on the page background (its header and cards have their own theme styling in Word 97, Midnight and Chicago); Unread's combined "all caught up" empty state; filling Search's empty page with tags or recent threads; hidden headings for Board, Recent and Search, which have none today; and the nav wrapping onto two rows (part of the separate navbar rethink).

## Approach and risks

One approach: shared CSS and template changes, reusing the existing page and card markup. Alternatives would be a new page-header partial for every page; rejected unless the inspection shows the existing markup cannot carry the rule. Risks:

- Themes (Word 97, Chicago, Midnight and others) style headings and cards their own way; a card whose first child becomes a hidden heading may change their title bars. Check pixel themes.
- Tests assert page headings by text; hidden headings stay in the HTML, so text assertions should hold.
- Thread titles: some threads have a real title distinct from the body. Only hide the heading when the title is derived from the body.

## Reuse decisions

- Hidden page heading: `<h1 class="visually-hidden">` directly in the page's `section.stack`, outside any card, as `templates/pages/inbox.php` (Unread) already does. Putting it outside the card keeps theme rules for `.card > h1:first-child` (Word 97 and Midnight title bars) from applying to it. `.visually-hidden` from `public/assets/site.css`.
- Blank line above the first heading: browser default heading margins inside the card's padding. One rule beside `.card` in `site.css` zeroes the first child's top margin. Theme rules for `.card > h1:first-child` are more specific and keep their own margins.
- Messages list: the bare `.thread-rows` list in the stack, as `templates/pages/recent.php` renders it; the card wrapper (`.message-list-module`, used nowhere else) goes. `private_message_list.js` finds its parts by `data-role` inside `[data-conversation-list]`, which stays.
- Calendar month navigation: the existing `nav.calendar-nav` moves into the first `article.card.calendar-month`; no new markup.
- Thread title: `templates/partials/thread_root_card.php` already compares the title with the body's first line to avoid repeating it. Extend that: when the thread has no subject and the title did not come from a whole first line (so it is a truncated excerpt), the `<h1>` keeps the title for the document outline but gets `.visually-hidden`.
- About: the intro `<h1>` icon rule in `public/assets/about.css` has no glyph for the `intro` section (only for a `zenmemes` section the template no longer has), so its mask is unset and the box fills solid. The heading becomes a hidden page heading, and the dead `h1` selectors go.
- No new components or dependencies.

## Completion conditions

- Normal entry: open each page from the main navigation, the You menu or a link.
- End-to-end: nav pages start with their content under the active nav item; non-nav pages show one consistently placed title; no card has a blank line above its first heading.
- Release: PR merged and deployed by the maintainer.

## Concepts touched

[Navigation slots](../../concepts/navigation_slots.md) used unchanged (the active nav item now names the page). No inventory changes.

## Verification and recovery plan

Smoke set plus tests for the touched pages. Render the affected pages locally at phone and desktop width in Light, Dark and one pixel theme (Word 97), and compare against the thread's screenshots. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/page-header-consistency` from `b50af911`.

Planning commit: `4dd650c` (confirmed with Git before implementation).

## Dated execution history

- 2026-11-10: intake. Claude reviewed the thread's 12 screenshots and the list of templates containing `<h1>` before this estimate; no template contents were read.
- 2026-11-10: inspection. Of 48 page templates with an `<h1>`, 42 put it first in their first card; Account and Users put it on the page background; Unread already uses a hidden `<h1>` outside its cards. Correction to the outcome Claude described in the session (titles "outside the card, like Account"): the in-card convention is kept for non-nav pages, because it is what nearly every page and the pixel themes already use, and the maintainer called Invites (in-card) "the least worst" apart from the blank line. Account becomes a follow-up. The Messages fix becomes "list on the page like Recent" since the card held only the heading and the list.

## Study history

- 2026-11-10 intake: **0.40** whole-cycle projection, scope v1, R1, P2. Prior exposure: thread screenshots and a list of template file names only. Claude single exposed rater. No measured labor.
- 2026-11-10 execution entry: **0.40 reaffirmed**, scope v2 (non-nav title treatment kept in-card; Account deferred; Messages unwrapped). Same rater, R1, P2.
- 2026-11-10 closeout: scope v2 delivered, no corrections after implementation. Retrospective scope assessment 0.40. Maintainer acceptance pending.
- 2026-11-10: implementation. Hidden `<h1>` outside the card on Calendar (visible `#tag` heading kept for tag calendars), Users and its Waiting tab, Messages, About and the Tools launcher. `.card > :first-child` (and the element after a hidden first heading) loses its top margin in `site.css`. Calendar's Earlier/Later navigation moved into the first month card. Messages list unwrapped like Recent; its empty state uses Recent's `.board-empty`. This also makes an existing Chicago rule (`[data-conversation-list] > [data-role="rows"]`) match again; it had stopped matching when the list was put in a card. Thread root card hides an excerpt title that the body repeats. Removed now-unused `.users-page__title` rules from `site.css` and the Word 97, Midnight and Chicago themes (Word 97 and Midnight had already hidden that heading for the same reason), the dead About `h1` icon rules, and the selector from `Word97ThemeCoverageTest`.
- 2026-11-10: three heading assertions updated to the hidden heading (thread excerpt title, Messages, About).

## Outcome

Delivered scope v2 as recorded. Follow-ups, not started: Account's title on the page background; Unread's combined empty state; Search's empty page; hidden headings for Board, Recent and Search; the nav wrapping onto two rows.

## Verification

- `./v3 test --smoke CalendarPageTest InboxPageTest PrivateMessageListTest PrivateMessagePageControllerTest ThreadRootCardRepeatedTitleTest ThreadTitleTest ToolsRegistryTest UsersDirectorySortTest Word97ThemeCoverageTest ProfilePresentationContentTest WriteApiSmokeTest LocalAppSmokeTest NavIconSetsTest`: 704 run, 3 failed on the old heading markup; after updating them, rerun of the three plus the smoke set: 448 passed.
- Local render in headless Chrome at 760px of Board, Recent, Calendar (events off and on), Users, Search, About, Tools, Account and a fixture thread in Light, Gloss and Word 97; Calendar also at 390px. A subjectless long thread posted locally as guest shows the body once, no visible heading; a titled thread keeps its heading.
- Not verified: Unread, Messages and Invites with an approved identity (local guest sees the access notice, which renders correctly); Midnight and Chicago rendering; the browser test suites; the full suite (not run, per `AGENTS.md`).

## Final review

Diff checked for scope (12 templates and stylesheets, three tests, this record, the index and the item) and for leftover `.users-page__title` and About `h1` references (none). No new components or dependencies.

Commit/merge/publication status: planning commit `4dd650c`; completion commit pending at writing; PR open after push.

Attribution: Claude (claude-opus-5-5), 2026-11-10 UTC.
