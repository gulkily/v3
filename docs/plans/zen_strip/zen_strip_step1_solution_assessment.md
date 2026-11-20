> **Feature plan:** [Step 1](./zen_strip_step1_solution_assessment.md) · [Step 2](./zen_strip_step2_feature_description.md) · [Step 3](./zen_strip_step3_development_plan.md) · [Step 4](./zen_strip_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_strip`; host ledger [zen_strip.md](../effort_ledger/zen_strip.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.70

## Original Query

Hey Claude, I made the realization that zenmemes.com does not deliver on the promise that the name makes, meaning that the first thing that people see when they come to the site is not Zen memes, and we should probably fix that to make the front page start with Zen memes. Can we talk about this?

I think it's a combination of the second and the third. [A strip of memes above the thread list, and a redefinition of "meme" as an idea worth passing on, stated in a tagline.]

I think we should come up with some images ourselves because I don't think we have any in the website.

I think we should have image and general items that have both Zen and meme tags.

Yes, anyone who is approved can add both tags.

## Understood Intent

A first-time visitor to zenmemes.com should see Zen memes before anything else. A Zen meme is an image or a short text: an idea worth passing on. The front page opens with a strip of them, and a tagline says what the word means. The board controls, the composer and the visitor intro move below the strip. We draw the first images ourselves so the strip has a starting stock.

## Problem

The front page opens with two navigation rows, a notice, filters and a composer, then text threads, so nothing on the first screen matches the name.

Concepts touched: [visitor intro](../../concepts/visitor_intro.md) (moved below the strip); [site description](../../concepts/site_description.md) (beside the new tagline); [tag browsing](../../concepts/tag_browsing.md) (extended in Option B only). New concept: Zen strip. Feedback: no matching item in `docs/feedback/items/`; [PF-20261030-B22](../../feedback/items/PF-20261030-B22.txt) (cluttered iPhone new-post flow) is related but separate.

## Shared by every option

- A thread is a Zen meme when it carries both #zen and #meme, and an approved member added each tag. The author's own tags count when the author is approved. A label counts when an approved member wrote it. This is the rule `PromptLibrary.php` already uses for #prompt.
- Image threads show their attached image. Text threads show as a card with the title in large type.
- On a phone the strip scrolls sideways. It is plain server HTML, the same for every viewer, so it works without JavaScript and in high-traffic mode.
- The site profile names the strip's two tags and its tagline. A profile without them shows no strip, so chouse.club and local installs are unchanged. A feature flag can turn the strip off.
- Every theme must render it, including Gloss, Bubblegum and Ochre, at 380px and desktop width.

## Option A — A strip on the board

**Estimate: 0.50**
Main driver: a new read query on the `PromptLibrary.php` pattern, a strip partial in `board.php`, and checks across themes. Uncertainty: low.

The strip opens `board.php`, above the visitor intro, the board controls and the composer. It shows the newest Zen memes, up to about twelve, under the tagline. Each card links to its thread.

- Pro: the smallest change that keeps the promise the name makes.
- Pro: it reuses the approved-tag rule already trusted for prompts.
- Con: there is no page listing every Zen meme. The strip is the only place they appear together.

## Option B — Option A plus a combined tag page

**Estimate: 0.70**
Main driver: Option A plus support in `TagPage.php` and `TagsPageController.php` for a page of threads carrying both tags. Uncertainty: medium, because tag pages also have offline and pagination rules.

The strip ends with an "All" link to a page such as /tags/zen+meme. That page lists every Zen meme with the usual 25-thread pages.

- Pro: a readable, editable URL for the whole set, which fits our URL principle.
- Pro: combined tag pages help other tag pairs too.
- Con: with three seed images, the page shows little that the strip does not.

## Option C — Zen memes become the home page

**Estimate: 0.90**
Main driver: a new gallery-style `/` page, a moved board route, and every link and nav item that assumes `/` is the board. Uncertainty: high.

`/` shows a grid of Zen memes. The board moves to its own address in the Board nav item.

- Pro: the strongest match between the name and the first screen.
- Con: it breaks the links and habits that treat `/` as the board.
- Con: a grid needs far more stock than we have.

## Recommendation

Option B, chosen by the maintainer on 2026-11-19 in place of the earlier recommendation of Option A. The strip ends with an "All" link to /tags/zen+meme. That gives the whole set a readable address, and the same page works for any other pair of tags.

Vertical slice: on an iPhone SE, open zenmemes.com signed out. The first screen shows the tagline and the three seed memes. "All" opens /tags/zen+meme with the same three threads. An approved member adds #zen and #meme to a fourth thread, and it appears in both places after the next read model update. A pending key's tags change neither.

*— Revised by Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Open questions for the maintainer

- The tagline. Drafts: "Memes in the old sense: ideas worth passing on." "Zen memes: small things worth keeping."
- Order: newest thread first, or newest Zen meme first, counted from when the second tag was added?
- Should offline mode show the strip from the saved board snapshot? Our offline principle says yes, at extra cost.

## Enhancement requests

- Draw three seed images (enso, pebble stack, garage door) in one shared style. This is content, not code. The maintainer approved them, and claude posted each as a thread with board tags zen and meme on 2026-11-20 UTC: [Leave it open](https://zenmemes.com/threads/thread-20261120000354-88cbb22a), [Every commit is a stone](https://zenmemes.com/threads/thread-20261120000415-e7a6cf6f), [Come in, we're building](https://zenmemes.com/threads/thread-20261120000429-383e1a38).

## Study snapshots and approval

- 2026-11-19 UTC, intake after code inspection (`board.php`, `PromptLibrary.php`, `ApprovalLabel.php`, `TagsPageController.php`, `visitor_intro.md`, `site_description.md`): 0.50 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `eb0f5177`.
- Step 1 handoff: 0.50 for Option A (alternatives B 0.70, C 0.90; not summed). No implementation.
- 2026-11-19 UTC revision: the maintainer switched the direction to Option B. Projected total **0.70** for Option B, unchanged from its original option estimate; this is a change of chosen scope, not new understanding. Not yet approved. Awaiting `Approved Step 1`.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
