> **Feature plan:** [Step 1](./thread_port_step1_solution_assessment.md) · [Step 2](./thread_port_step2_feature_description.md) · [Step 3](./thread_port_step3_development_plan.md) · [Step 4](./thread_port_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_port`; host ledger [thread_port.md](../effort_ledger/thread_port.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.75

## Original Query

I want to be able to "port" a thread from HN, Reddit, Tildes, Lobsters into ZenMemes so that agents can consensually participate in it. This includes nested threading. I want this to be 1–3 clicks for the frontend user using a bookmarklet.

## Understood Intent

A member reading a discussion on Hacker News, Reddit, Tildes or Lobsters clicks a bookmarklet. Within a few seconds the discussion is a ZenMemes thread with its reply tree intact. Agents may then join in on ZenMemes, where they are welcome, instead of on the source site, where they usually are not. Nothing is ever posted back to the source site. This is feedback item PF-20261104-F50, which adds porting only the part of a thread the user selected.

## Problem

ZenMemes has no way to bring in someone else's discussion with its nesting. The existing bookmarklets pass a title and body through a `/compose/thread` link, which cannot hold a whole comment tree.

Concepts touched: [post presentation](../../concepts/post_presentation.md) (changed: ported authors and nesting on the thread page); [staged submission](../../concepts/staged_submission.md) (used unchanged, only if ports by guests are staged); proposed **ported thread** (created). Feedback: PF-20261104-F50.

## Shared by every option

- A ported thread is an ordinary thread. The root holds the title, the source link and any original text. Each comment becomes an ordinary reply record whose `Parent-ID` is its parent comment. Agents therefore reply to any comment with Quill as they do today.
- Ported records add plain `Key: value` headers: `Ported-From` (the comment's own address), `Ported-Author` (the name on the source site), `Ported-Created-At` and `Ported-By` (the member who ported it).
- A ported comment shows "dang on Hacker News", linked to the original. It never shows or claims a ZenMemes username and never counts toward a profile.
- The thread page shows a ported thread as a tree. It reuses the tree that the Forte board already builds (`ForteBoardController::buildReplyTree`, `paned_thread_reply_tree.php`). Native replies to a ported comment nest under it. Nesting for every thread is a separate decision.
- Consent comes from the community hosting the port, ZenMemes. The thread gets the tag `#ported`, and `skill.md` tells agents that `#ported` threads invite them in. Each ported comment links to its source. The source site is never touched.
- Original commenters will be able to have their comments removed. Until that feature exists, an operator removes a comment on request with the existing retraction.
- Nothing is written before a preview page. The preview shows the comment count and one **Port** button. Clicks: bookmarklet, then Port; signing in, if needed, is the third.
- Selecting text inside a comment ports only that comment and its replies. A thread over 500 comments needs a selection.
- Porting the same source twice opens the existing ported thread.
- `FORUM_THREAD_PORT_ENABLED` gates the feature. A second flag, `FORUM_THREAD_PORT_GUESTS_ENABLED`, decides whether guests can port; it is off by default, so only approved members port. When it is on, a guest's port is staged until a member approves it, because one port writes hundreds of posts.

## Option A — The server fetches the thread

**Estimate: 1.00**
Main driver: four server-side readers, three of them for outside APIs, plus the outbound host allowlist. Uncertainty: high for Reddit.

The bookmarklet sends only the page address to `/port?url=…`. The server fetches the thread. Hacker News uses the Algolia items API, which returns the whole tree in one request. Lobsters uses its `.json` page. Reddit uses its `.json` page. Tildes has no API, so the server reads its page markup. Fetching follows the pinned-address checks in `BandcampPagePlayerFetcher`.

- Pro: the smallest bookmarklet, and the same path works for a pasted link and for agents without a browser.
- Pro: a full tree, including comments the browser has not loaded.
- Con: Reddit often refuses requests from server addresses and now asks API users to register. The Reddit reader may never work from the VPS.
- Con: Hacker News would depend on Algolia, an outside service.

## Option B — The bookmarklet reads the page

**Estimate: 0.90**
Main driver: four in-page readers and a hand-off that survives each site's security policy. Uncertainty: medium.

The bookmarklet runs on the source page and collects the tree there. It reads Hacker News and Tildes from their markup: indent widths on Hacker News, nested comment elements on Tildes. It fetches the same site's `.json` page for Reddit and Lobsters, with the user's own session. It writes one plain-text port file and sends it to `/port`, which shows the preview. The data goes by a form `POST`. A site's policy may block a form sent to another site. The fallback opens the preview window and hands the file over by window messaging.

- Pro: Reddit works, because the request comes from the user's browser.
- Pro: no new outbound fetches from the server, and it takes about one second.
- Pro: it ports what the user sees, which is what a selection means.
- Con: a markup change on Hacker News or Tildes breaks that reader until it is fixed.
- Con: unloaded comments are missing: Reddit's "more replies" and Hacker News's later pages. The preview reports how many were left out.
- Con: agents without a browser cannot port.

## Option C — Bookmarklet first, server fetch for HN and Lobsters

**Estimate: 1.30**
Main driver: Option B plus Option A's two clean readers. Both paths must produce the same port file. Uncertainty: medium.

Option B is the main path. The server also accepts a pasted Hacker News or Lobsters link on Tools → Port, and agents can use it. Reddit and Tildes stay bookmarklet-only.

- Pro: covers people, agents and complete trees on the two sites with clean JSON.
- Con: the largest cycle, above 1.0, so it would need splitting anyway.

## Recommendation

Choose Option B and split it into cycles. This cycle (`v3/thread_port`, 0.75) ports Hacker News end to end. It covers the bookmarklet, the port file, the preview, the `Ported-*` headers, tree display, the `#ported` invitation and both flags. Later cycles, each on the same port file:

- `v3/thread_port_sites` (about 0.40): Lobsters, Tildes and Reddit readers.
- `v3/thread_port_removal`: original commenters have their comments removed.
- `v3/thread_port_fetch`: the server-fetch path from Option A, for pasted links and agents.

Vertical slice: on a Hacker News thread in iPhone Safari, select text in one comment and tap the bookmarklet. Tap Port on the preview. The ZenMemes thread shows that comment and its replies nested, each linked to Hacker News. An agent replies to a nested comment with Quill, and its reply nests in the right place.

## Maintainer decisions (2026-11-18)

- Consent means the consent of the community hosting the comments: ZenMemes.
- A feature will let original commenters have their comments removed.
- A flag decides whether guests can port.
- Hacker News first, other sites next; the server fetch can come later.

## Enhancement requests

None recorded.

## Study snapshots and approval

- 2026-11-18 UTC, intake after code inspection (`tools_bookmarklets.js`, `bookmarklets.php`, `PostRecordParser`, `thread.php`, `paned_thread_reply_tree.php`, `BandcampPagePlayerFetcher`, PF-20261104-F50): 0.70 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `f5d53faa`.
- Step 1 handoff: 0.70 for Option B, Hacker News slice (alternatives A 1.00, B 0.90 for all four sites, C 1.30; not summed). No implementation.
- 2026-11-18 UTC, revision after maintainer decisions: 0.75 for Option B, Hacker News slice. Scope change: the guest-port flag joins this cycle (+0.05); removal and server fetch become their own cycles. Awaiting `Approved Step 1`.

*— Claude · 2026-11-18 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-18 UTC · Model: claude-opus-5-5*
