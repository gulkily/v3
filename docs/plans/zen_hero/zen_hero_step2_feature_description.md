> **Feature plan:** [Step 1](./zen_hero_step1_solution_assessment.md) · [Step 2](./zen_hero_step2_feature_description.md) · [Step 3](./zen_hero_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_hero`; host ledger [zen_hero.md](../effort_ledger/zen_hero.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.35**

## Problem

The Zen meme grid takes about three phone screens before the first thread, shows on every board view, and glares in dark themes. The maintainer wants one meme, the top-liked one, with a row of tiny thumbnails under it.

## Approved direction

Step 1 was approved on 2026-11-21 ("Approved Sub 1", read as Approved Step 1). No option was named, so this follows the recommended Option A with the revision of the same day: the thumbnail row opens the All page and there is no separate All link. The open questions weren't answered, so the Step 1 defaults apply. Each is marked **(default)** below and can still be changed.

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (changed: one featured meme and a thumbnail row; home only; the row replaces the All link). [Tag browsing](../../concepts/tag_browsing.md) (unchanged). Feedback: no matching item.

## User stories

- As a first-time visitor, I want one good Zen meme and then the threads, so that the first screen shows both what the site is called and what happens on it.
- As a reader, I want the row of thumbnails to take me to every Zen meme, so that I don't need a separate All link.
- As a member, I want my like to count toward which meme leads, so that the front page reflects what people enjoy.
- As a reader in a dark theme, I want the images dimmed to suit the page, so that they don't glare.
- As a reader browsing another board view, I want the module out of the way, so that the list I chose starts at the top.

## Core requirements

- The featured meme is the Zen meme with the highest thread score, the number Top sort uses **(default)**. Among tied memes the newest wins **(default)**. It shows whole, like a grid card today, and links to its thread. A text-only meme shows its title in large type.
- Under it, one row of tiny thumbnails shows the other Zen memes, newest first, leaving out the featured one **(default)**. Thumbnails are about 3rem squares cropped to the middle of the image **(default)**; a text meme gets a small text tile. The row is one line, never scrolls sideways or wraps, and shows as many as fit.
- The whole row is a single link to /tags/zen+meme. The All link is removed.
- The module shows only on the board's default view, first page **(default)**. A chosen view, sort, tag or later page hides it.
- In dark themes the module's images dim slightly, through one theme setting. Light themes are unchanged.
- Which threads are Zen memes, the server-only HTML, the no-JavaScript behaviour and the feature flag all stay as they are.

## Delivery scope

- Work type: application change: the strip's sort order and limit, the board's view check, the strip partial and styles, a theme setting for image dimming, the concept entry and tests.
- Out of scope: the gallery for All (logged task, proposed as the next cycle), swapping the featured meme from a thumbnail, daily rotation among tied memes, counting only approved members' likes, the strip offline, dimming images elsewhere on the site.

## Completion boundary

- Normal entry: a signed-out visitor opens zenmemes.com; a member likes a Zen meme.
- End-to-end outcome: at 380px and desktop width, in Gloss, Gloss Dark, Bubblegum and Ochre, the home page shows one featured meme and one thumbnail row, and the first thread appears within about one phone screen. A tap on the row opens /tags/zen+meme.
- Recovery: the feature flag turns the module off. A stray thread leaves it when its tag is removed or the thread is retracted, as today.
- Release: merged to main and tried on production by the maintainer. Deploy now is expected to be enough; no database change is planned.

## Known user or environment

The maintainer demos on an iPhone SE (3rd gen). Entry: zenmemes.com in Safari. Evidence: a 380px-wide screenshot of the home page in Gloss and Gloss Dark, then his own check on the phone after deploy. If the row overflows or the featured meme is cropped there, the flag turns the module off while it's fixed.

## Risks

- **A row of pictures leads to a page without pictures.** /tags/zen+meme lists titles and excerpts. Impact: the tap disappoints. Earliest validation: tap through on the local copy. Mitigation: none in this cycle; the gallery is proposed as the next one. The maintainer should accept this gap or pick the gallery now.
- **One like decides the front page.** Most memes have zero or one like, and Top sort's score counts any like. Impact: the featured meme may flip often, or never. Earliest validation: check the six seeds' scores on a copy of production before building. Mitigation: the newest-wins tie rule keeps the result stable and predictable.
- **Saved copies of the home page go stale.** The board is cached, and a like now changes what leads. Earliest validation: like a meme on the local copy and reload the signed-out home page. Mitigation: confirm in Step 3 that a like already refreshes the board, as it must for Top sort.
- **Dimming harms other themes.** Some themes restyle images. Earliest validation: screenshots in the four named themes. Mitigation: the setting defaults to no change and only dark themes set it.

## Shared component inventory

- `ReadModel/ZenStrip.php`: the Zen meme query. Extended with a score-first order; the approved-tag rule is untouched.
- `Http/BoardPageController.php`, `zenStrip()`: decides when the module shows. Extended to check the view, not just the page number.
- `Images/ListThumbnails`: already gives each thread a small preview and the full image. Reused for both the featured image and the thumbnails.
- `templates/partials/zen_strip.php` and the `.zen-strip__*` rules in `site.css`: changed in place; no new partial.
- Theme variables in `site.css` and the theme files: one new variable for image dimming, the pattern themes already use for colours.
- `FeatureFlagRegistry::ZEN_STRIP_ENABLED`: reused. No new flag.
- `docs/concepts/zen_strip.md`: revised.

## User flow

1. A visitor opens zenmemes.com on a phone.
2. They see one Zen meme, large, then a row of tiny thumbnails, then the threads.
3. They tap the meme and land on its thread, where they can like it.
4. Back on the home page, they tap the thumbnail row and see every Zen meme at /tags/zen+meme.
5. They switch the board to All: the module is gone.

## Success criteria

- On production at 380px, the first thread starts within 1.5 screens of the top, down from about three.
- A test shows the highest-scored Zen meme is featured, and that a tie goes to the newest.
- A test shows the module on the default first page and absent on a chosen view, a chosen sort and page 2.
- The home page has no All link, and the row's link goes to /tags/zen+meme.
- No sideways overflow at 380px or 1280px in Gloss, Gloss Dark, Bubblegum and Ochre.
- In Gloss Dark the seed images are visibly dimmed; in Gloss they are unchanged.

## Enhancement requests

- **Request:** gallery for All. **Disposition:** not in this cycle. **Approval status:** logged as a [task thread](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37). **Follow-up:** proposed as the next cycle.

## Study snapshots and approval

- 2026-11-21 UTC: **Approved Step 1** by the maintainer; Option A assumed, as recommended.
- 2026-11-21 UTC, Step 2 handoff: **0.35** projected total, reaffirmed from Step 1. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` `09a15f6`.
- 2026-11-21 UTC: **Approved Step 2** by the maintainer.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*
