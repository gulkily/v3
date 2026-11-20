> **Feature plan:** [Step 1](./zen_strip_step1_solution_assessment.md) · [Step 2](./zen_strip_step2_feature_description.md) · [Step 3](./zen_strip_step3_development_plan.md) · [Step 4](./zen_strip_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_strip`; host ledger [zen_strip.md](../effort_ledger/zen_strip.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.70**

## Problem

A first-time visitor to zenmemes.com sees two navigation rows, a notice, filters and a composer before any content, and no Zen memes at all. The name makes a promise the first screen doesn't keep.

## Approved direction

Step 1 was approved on 2026-11-19 with Option B: a strip of Zen memes at the top of the board, ending in an "All" link to /tags/zen+meme. Six seed threads are already posted (see Enhancement requests).

Concepts touched: [visitor intro](../../concepts/visitor_intro.md) (moved below the strip); [site description](../../concepts/site_description.md) (unchanged; the tagline sits beside it); [tag browsing](../../concepts/tag_browsing.md) (extended to pairs of tags). Proposed addition: a `zen_strip.md` concept entry. Feedback: no matching item; PF-20261030-B22 is related but separate.

## User stories

- As a first-time visitor, I want Zen memes on the first screen, so that the site matches its name.
- As a phone reader, I want to swipe through the strip, so that it takes one row of the screen.
- As an approved member, I want to add #zen and #meme to a thread, so that it joins the strip.
- As the maintainer, I want only approved members' tags to count, so that the strip stays high-signal.
- As a reader, I want "All" to open every Zen meme at a readable address, so that I can see the whole set.

## Core requirements

- A thread joins the strip when it carries #zen and #meme and an approved member added each one. The author's own tags count when the author is approved. A label counts when an approved member wrote it. This is the rule the Prompts page uses for #prompt.
- The strip opens the first page of the board, above the visitor intro, the board controls and the composer. It shows the twelve newest Zen memes by thread date, under the profile's tagline. Image threads show their first image, and text threads show their title in large type. Each card links to its thread.
- /tags/zen+meme lists threads carrying both tags, in 25-thread pages like any tag page. A single tag's page is unchanged.
- The strip is the same server HTML for every viewer. It needs no JavaScript and scrolls sideways on a narrow screen.
- The site profile names the strip's tags and tagline. A profile without them, or the feature flag turned off, shows the board as it is today.

## Delivery scope

- Work type: application change: a read query, a strip partial, `board.php` order, a pair-of-tags route and page, profile settings, a feature flag, styles, a concept entry and tests.
- Out of scope: the strip in offline mode, a "newest Zen meme" order counted from the second tag, a separate approval filter on /tags/zen+meme, and changes to the navigation rows.

## Completion boundary

- Normal entry: a signed-out visitor opens zenmemes.com; an approved member tags a thread #zen and #meme.
- End-to-end outcome: the strip shows the seed threads on the first screen at 380px and on desktop, in Gloss, Gloss Dark, Bubblegum and Ochre. "All" opens /tags/zen+meme with the same threads.
- Recovery: removing a tag or retracting the thread takes it out of the strip after the next read model update. Turning the flag off restores today's board.
- Release: merged to main and tried on production by the maintainer.

## Risks

- **The All page disagrees with the strip.** Tag pages count anyone's tags, so a guest's #zen #meme thread shows on /tags/zen+meme but not in the strip. Validate with one guest-tagged test thread. Default: keep tag pages open, as tag browsing works today. The maintainer can ask for the approval rule there too.
- **The strip slows the busiest page.** The board is cached and rebuilt often. Validate the query time on a copy of production data before styling. Mitigate by reusing the indexed `viewer_tags` lookup and one thumbnail query for all twelve cards.
- **Themes break the row.** Some themes restyle every card on the board. Validate the four named themes at 380px first. Mitigate by giving strip cards their own class rather than the board card's.

## Shared component inventory

- `PromptLibrary.php` and `ApprovalLabel.php`: the approved-tag rule. The strip query follows the same pattern instead of a new rule.
- `ListThumbnails::forThreads`: the image for each card, as board rows use it. Reused.
- `TagPage.php`, `TagsPageController.php` and the `/tags/` route in `Application.php`: extended to a pair of tags joined by `+`. Pagination stays in `ThreadKeysetPage`.
- `templates/pages/board.php` and `partials/visitor_intro.php`: reordered, not changed.
- `ProfilePresentationContent.php`: holds the tagline and tag pair beside `siteDescription`.
- `FeatureFlagRegistry.php`: one new flag. `thread_row.php` is not reused, because a strip card is a tile, not a row.

## User flow

1. A visitor opens zenmemes.com on an iPhone SE.
2. The first screen shows the tagline and the newest Zen memes in one row.
3. They swipe the row, then tap a card and land on its thread.
4. Back on the board, they tap "All" and see /tags/zen+meme.
5. An approved member tags another thread #zen and #meme, and it leads the strip after the next update.

## Success criteria

- On production, the first screen at 380px shows the tagline and at least two Zen meme cards above any control.
- A test shows a pending key's or guest's tags leave a thread out of the strip, and an approved label puts it in.
- /tags/zen+meme returns the six seed threads; /tags/zen still works.
- With the flag off, the board's HTML matches today's.
- The board's render time stays within 10% of today's on a copy of production data.

## Enhancement requests

- **Request:** seed images. **Disposition:** done as content outside the code. Six threads by claude with board tags zen and meme: [Leave it open](https://zenmemes.com/threads/thread-20261120000354-88cbb22a), [Every commit is a stone](https://zenmemes.com/threads/thread-20261120000415-e7a6cf6f), [Come in, we're building](https://zenmemes.com/threads/thread-20261120000429-383e1a38), [Built by whoever shows up](https://zenmemes.com/threads/thread-20261120000609-3be022bf), [A good thread is a quiet garden](https://zenmemes.com/threads/thread-20261120000622-9cf12198), [Unread: 0. Breathe.](https://zenmemes.com/threads/thread-20261120000636-067a50d3).
- **Request:** show the strip offline. **Disposition:** deferred; the offline snapshot would need to save each card's image. **Approval status:** not approved. **Follow-up:** a later cycle.

## Open choices for the maintainer

- Tagline. Default: "Memes in the old sense: ideas worth passing on."
- Should /tags/zen+meme apply the approved-tag rule too? Default: no.

## Study snapshots and approval

- 2026-11-19 UTC: **Approved Step 1** by the maintainer with Option B.
- 2026-11-19 UTC, Step 2 handoff: **0.70** projected total, reaffirmed from Step 1's Option B. Offline is now out of scope, but the pair-of-tags route and the performance check weigh about the same. Claude, single exposed rater. Scope v2. Reference R1, hashes not re-verified this session. Baseline `origin/main` `eb0f5177`. Awaiting `Approved Step 2`.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
