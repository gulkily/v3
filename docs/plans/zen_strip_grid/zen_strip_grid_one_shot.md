Cycle: `v3/zen_strip_grid`; Mode: one-shot; Record/index: [plans index](../README.md). Follow-up to [zen_strip](../zen_strip/zen_strip_step4_implementation_summary.md) (PR #344, merged).

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_strip_grid`; host ledger [zen_strip_grid.md](../effort_ledger/zen_strip_grid.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.25**

Status: implemented and committed; next permitted action: maintainer review (PR).

Original request: "Please expand the strip into a full module without horizontal scrolling. Let it be as tall as it needs to be. The images need to be viewable without clicking on them. Please also log a task that the All button needs to go to an image gallery view with full-size images."

Authorization: the maintainer's message on 2026-11-20 asks for the change directly; the outcome and its limits are clear, so it runs as a one-shot. Exclusions: the gallery view for All is logged as a task, not built.

Outcome and work type: application change. The Zen strip becomes a wrapping grid with no sideways scrolling. Each image shows whole, uncropped, at a size where its caption can be read on the board. The module grows as tall as its cards need. Text cards keep their title. All stays, still linking to /tags/zen+meme, until the gallery task is done.

Approach and risks:
- A CSS grid with `auto-fill` columns at least 18rem wide: one column on a phone, two in the 712px content column. Images use their natural aspect ratio instead of a square crop.
- Sharp images: the card offers the 480px tile and the original through `srcset`, so a wide or high-density screen gets the original (1080px for the seeds).
- **Risk:** on a phone, twelve full-width images push the threads far down. Impact: a reader scrolls past about twelve screens of images before the threads. The maintainer asked for this ("as tall as it needs to be"); recorded, not mitigated. Early validation: a 380px screenshot.

Reuse decisions: `ListThumbnails::firstPerKey` gains a `full` URL (`/images/<file>`, the same path `ThumbnailStore::url` uses for originals) instead of a new lookup; `partials/zen_strip.php` and the `.zen-strip__*` rules in `site.css` change in place; `ZenStrip`, `BoardPageController` and the templates are unchanged.

Completion conditions: on the board's first page, the module shows every strip thread in a grid with no horizontal scroll at 380px and 1280px; seed captions are readable without opening a thread; with JavaScript off it looks the same; other themes keep working. Deploy with Deploy now; no schema change.

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (changed: grid instead of a row; All's future noted).

Verification and recovery plan: `./v3 test --smoke BoardListTest ZenStripTest` plus a new assertion that cards carry the full image in `srcset`; screenshots at 380px and 1280px in Gloss, Bubblegum, Ochre and Word97; `scrollWidth <= clientWidth` on the module. Recovery: the existing flag turns the module off.

Task logged for later: All should open an image gallery view with full-size images. Logged as a ZenMemes #issue #meta thread: https://zenmemes.com/threads/thread-20261120173657-2cb6e813e62a6457e4cde6eccd4c5828

Starting revisions and branch: `zen-strip-grid` from `origin/main` `a552a6ab3`.

Planning commit: `32568d5e6` (plan only, before any implementation).

Study history:
- 2026-11-20 UTC, intake and execution entry: **0.25**, before implementation, after reading the merged strip code. Claude, single exposed rater (wrote the code being changed). Scope v1. Reference R1, hashes not re-verified.


Dated execution history:
- 2026-11-20: `ListThumbnails` gives each thumbnail a `full` URL (`/images/<file>`). Addition to a shared component; the list rows and gallery don't use it.
- 2026-11-20: `partials/zen_strip.php` is now a `zen-strip__grid` list. Image cards use the tile with `srcset` "tile 480w, original <width>w" and `sizes="(min-width: 48rem) 22rem, 100vw"`. All moved out of the grid into a right-aligned link below it.
- 2026-11-20: `site.css` replaces the sideways row with a grid (`auto-fill`, columns at least 18rem), images at `height: auto` (no crop), text cards at least 9rem tall with a larger title.
- 2026-11-20: logged the gallery task on ZenMemes (link above). Disposition: separate work, not built here.
- Discovery: on a phone the module is about 2,300px for the seven local cards (about 2,000px for production's six), roughly three screens before the threads. Lower than the plan's worry; accepted as asked.

Study history, when enrolled:
- 2026-11-20 UTC, closeout: retrospective scope assessment **0.20**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. The strip is a wrapping grid, as tall as it needs, with whole images readable on the board. The gallery for All is logged, not built.

Verification:
- `./v3 test --smoke BoardListTest ZenStripTest GalleryPageTest`: 487 run, 487 passed. New `BoardListTest::testZenStripImageCardsShowTheWholeImageWithTheOriginalForSharpScreens`; `testZenStripOpensTheFirstPageOnly` updated for the All link. The full suite was not run.
- Local import of zenmemes.com, Playwright with the system Chrome, /threads/: no horizontal overflow at 380px or 1280px in Gloss, Bubblegum, Ochre and Word97. One column at 380px (348px), two at 1280px (350px each; 400–410px in Word97 and Ochre). Seed captions readable at both widths.
- Image choice: at 380px with 2x density the browser loads the original; at 1280px with 1x density it loads the 480px tile.
- Not rerun: JavaScript off (the module is plain HTML and CSS, as before).

Final review: the diff touches `ListThumbnails.php`, `zen_strip.php`, `site.css`, `BoardListTest.php`, the concept entry and this record. No duplicated component, no dependency change, no schema change. Deploy now is enough.

Commit/merge/publication status: completion commit pending at time of writing; branch `zen-strip-grid`; not merged.

Attribution: Claude · 2026-11-20 UTC · Model: claude-opus-5-5
