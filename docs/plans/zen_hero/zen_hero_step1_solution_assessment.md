> **Feature plan:** [Step 1](./zen_hero_step1_solution_assessment.md) · Step 2 · Step 3 · Step 4

Cycle: `v3/zen_hero`. Follows [zen_strip](../zen_strip/zen_strip_step1_solution_assessment.md) (PR #344) and [zen_strip_grid](../zen_strip_grid/zen_strip_grid_one_shot.md) (PR #345), both merged.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_hero`; host ledger [zen_hero.md](../effort_ledger/zen_hero.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.35

## Original Query

For the front page memes, I only want one, and I want it to be the top liked one, and a row of tiny thumbnails under it.

[Posted unsigned on ZenMemes, 2026-11-21: [thread](https://zenmemes.com/threads/thread-20261121083631-65a3194c). In chat the maintainer then said "Yes, please." to: "Want me to write Railspike Step 1 for the single top-liked meme with thumbnails, folding in the home-only and dark-theme fixes?"]

## Understood Intent

The front page shows one Zen meme large: the one with the most likes. Under it sits one row of tiny thumbnails of the other Zen memes. This replaces the grid of whole images from zen_strip_grid.

Two fixes from the [gallery task thread](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37) come along:

- **Home only:** the module shows on the home page and goes away when the reader switches the board's view. Today it shows on the first page of every view.
- **Dark themes:** the seed images have a flat cream background and look like near-white squares on a dark page.

## Problem

Six whole images take about three phone screens before the first thread, show on every board view, and glare in dark themes.

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (changed: one featured meme and a thumbnail row instead of a grid; home only). [Tag browsing](../../concepts/tag_browsing.md) (unchanged; All still opens /tags/zen+meme). Option C adds a new concept, Zen gallery. Feedback: no matching item in `docs/feedback/items/`.

## Shared by every option

- Which threads are Zen memes doesn't change: #zen and #meme, each from an approved member.
- The featured meme is the Zen meme with the highest thread score, the number the board's Top sort already uses.
- The thumbnail row is one line. It never scrolls sideways and never wraps. It holds as many thumbnails as fit, then All.
- A text-only Zen meme can be featured (its title in large type) and gets a small text tile in the row.
- The module is plain server HTML, the same for every viewer, and works without JavaScript.
- Images dim slightly in dark themes, through one theme setting that other images on the site can use later.
- The existing feature flag still turns the module off.

## Option A — Featured meme with a row of links

**Estimate: 0.35**
Main driver: a new sort order in the strip's query, a reshaped partial and styles, the view check, and screenshots across themes. Uncertainty: low.

The module shows the top-liked meme whole, at the size one grid card has today. Each thumbnail under it is a link to that meme's thread. The featured meme links to its thread too.

- Pro: about one phone screen instead of three, so threads start much sooner.
- Pro: the smallest change. It reuses the strip's query, thumbnails and flag.
- Con: seeing another meme at full size means leaving the front page.

## Option B — Thumbnails swap the featured meme

**Estimate: 0.50**
Main driver: Option A plus a second way to pick the featured meme, by a readable address, and the caching rules for it. Uncertainty: medium.

As Option A, but a thumbnail shows its meme large in place, on the front page. The address names the meme in readable words, so it works without JavaScript and can be shared.

- Pro: a visitor can flip through every Zen meme without leaving the first screen.
- Con: the front page gains a second state, which touches static copies and high-traffic mode.
- Con: likes matter less if any meme is one tap from the top spot.

## Option C — Option A plus the gallery for All

**Estimate: 0.65**
Main driver: Option A plus a new gallery view of full-size Zen memes, including a place for text ones. Uncertainty: medium; the gallery task still has an open rule question.

As Option A, and All opens a gallery of full-size images instead of the tag page. This closes the [logged gallery task](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37) in the same cycle.

- Pro: with five memes now reduced to thumbnails, the gallery becomes the place to see them properly.
- Con: two features in one cycle, and the gallery's rule (approved members' tags or anyone's) isn't decided.

## Recommendation

Option A. It is exactly what was asked for, and it fixes the height, scope and dark-theme problems in one small cycle. The gallery (Option C) is worth doing straight after as its own cycle, because the thumbnails make it more needed. Option B can wait until we see whether people tap the thumbnails.

Vertical slice: on an iPhone SE, open zenmemes.com signed out in Gloss Dark. One Zen meme shows large and dimmed to suit the theme, with a row of tiny thumbnails and All under it, and the first thread is visible within about one screen. Like a different meme until it leads; after the next read model update it is the featured one. Switch the board to All or to another page: the module is gone.

## Open questions for the maintainer

- **Ties.** Most memes sit at zero or one like today, so ties will be common. Default: among tied memes, the newest wins. Alternative: tied memes take turns, one per day.
- **Whose likes count.** Default: the thread score as Top sort counts it. Alternative: only likes from approved members, to match the tagging rule. With little human traffic, one like can decide the front page either way.
- **Home only.** Default: the module shows only on the board's default view, first page (`/` and `/threads/`). Any chosen view, sort, tag or later page hides it.
- **Is the featured meme also in the row?** Default: no, the row shows the others.
- **Thumbnail size.** Default: about 3rem square, cropped to the middle of the image. At that size the captions can't be read; the thumbnails are there to show that more exist.

## Enhancement requests

- None in this cycle. Related and still open: the gallery for All (logged thread above), `hashtag_self_tags`, and the #notX task.

## Study snapshots and approval

- 2026-11-21 UTC, intake after code inspection (`ZenStrip.php`, `BoardPageController.php`, `partials/zen_strip.php`, `site.css`, `zen_strip.md`): 0.35 projected total for the recommended scope. No pristine intake. Claude, single exposed rater (wrote the code being changed); R1 hashes not re-verified this session; baseline `origin/main` `09a15f6`.
- Step 1 handoff: 0.35 for Option A (alternatives B 0.50, C 0.65; not summed). No implementation. Awaiting `Approved Step 1`.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*
