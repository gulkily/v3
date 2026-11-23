# Zen Meme Gallery Implementation Plan

[Plans index](../README.md)

Cycle: v3/zen_meme_gallery. Mode: one-shot.
Debug mode: enabled by the repository marker.
Effort study: railspike-effort P2, R1, scope v1. Extended estimates: enabled.

**Cycle estimate: 0.30**

Status: implemented and verified locally. Production acceptance follows review and rollout.

Goal: Open a gallery of Zen memes from the thumbnails on the homepage.

Architecture: Serve a Zen view from the existing Gallery route and page template. Select threads with the strip's approved-member tag rule and order, then use the same whole-image card markup on the strip and the Zen gallery. Give the gallery its own pagination and retain the tag listing as the strip's destination when Gallery is disabled.

Tech stack: PHP, SQLite read model, server-rendered templates, shared site CSS.

Original request: Find a high-impact, low-effort task. [Board feedback](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37) records the thumbnail link landing on a list without images and asks to include text-only memes.
Authorization: On 2026-11-23 UTC the requester replied “yes, that looks like a great task! please go ahead!” to the scope of a PR for the gallery, using the homepage's tag rules and including linked text-only entries. Scope covers the route, views, relevant tests and concepts, and a feature-branch PR. No feedback item in docs/feedback/items matched the source thread, title, or Zen strip gallery.
Dependency inventory: Zero new or updated dependencies.

## Testing plan

- Exercise the homepage through Application::handle in tests/GalleryPageTest.php. Follow the thumbnail row's link, confirm its destination shows a responsive image and linked text entries, and check that an unsigned tag and an unrelated image do not enter the collection.
- Follow the Next and Previous links to verify that the last eligible memes can be reached after the first page and the view stays filtered. Turn Gallery off and confirm the homepage still links to the ordinary combined tag listing.
- Run the smoke set with BoardListTest, GalleryPageTest, and ZenStripTest. Verify the homepage flow and ordinary Gallery on a local phone and desktop viewport, including a light and dark theme, real image loading, and overflow.

NOTE: I will write *all* tests before I add any implementation behavior.

## Implementation details

- In src/ForumRewrite/Application.php route /gallery/?view=zen to the filtered view. Treat the normal Gallery and its with-post redirects as existing contracts. Check the Gallery flag, Zen strip flag, and configured site profile for the Zen view.
- In src/ForumRewrite/Http/RecentPageController.php use PageNavigation and ZenStrip with a bounded limit and offset. In src/ForumRewrite/ReadModel/ZenStrip.php preserve the homepage limit and add offset support for pages of eligible threads. Use existing tables and approval conditions.
- In src/ForumRewrite/Http/BoardPageController.php point the thumbnail row to the Zen view when Gallery is available. In templates/pages/gallery.php use the shared Gallery shell and native page navigation. Extract the whole-image and text-card markup from templates/partials/zen_strip.php into one reusable partial. Use the existing responsive display sources at their natural proportions and lazy loading in the gallery. The regular Gallery keeps its tile grid and zoom.
- Where an image is missing, the thread remains available as a linked title. A page beyond the end offers the existing first-page recovery. The current tag listing URL stays valid.
- Concepts touched: [Zen strip](../../concepts/zen_strip.md), [Post presentation](../../concepts/post_presentation.md), [Collection selection](../../concepts/collection_selection.md). Update the entries alongside implementation.

Starting revision: 8a5aa406818c1a6c0cd0a04f776b42b25aff1845. Branch: zen-meme-gallery.
Planning commit: 5fbd75f951176acb8ae758d6d2de5b38ca79730e.

Study history: 2026-11-23 UTC. Intake and execution entry 0.30, R1, P2, v1. The initial recommendation and targeted source inspection preceded the first R1 rating. The display and tag-selection paths already exist; the work is in connecting them with bounded pagination and running both views through verification.

*— Jiro · 2026-11-23 UTC*

## Outcome and verification

- Before implementation, the homepage click-through regression followed the old tag link and found no gallery image. The new view passes this regression. Tests also cover pagination, unsigned-tag exclusion, untitled text, the normal Gallery, and recovery when Gallery is disabled.
- Passed `./v3 test --smoke BoardListTest GalleryPageTest ZenStripTest FeatureFlagsBehaviorTest` sequentially, 501 tests. The full suite was not run. An initial two-worker run hit unrelated read-model metadata failures and a missing local zip executable. Relevant metadata tests passed in isolation on the starting revision and in the sequential run. The archive test passed with the fixture runtime's zip tools present.
- Passed PHP syntax checks on changed PHP and `git diff --check`. Independent review found no blocking issues.
- Headed Chromium, 390 px Gloss light and 1366 px Midnight dark, with a local fixture. Clicked the home thumbnail row, loaded two uncropped responsive images, opened a text-only thread by keyboard, and checked for horizontal overflow. Verified recovery from an out-of-range page and checked ordinary Gallery zoom.
- Production acceptance is pending. On hosts serving generated HTML, refresh the published homepage after rollout. The deploy command updates the checkout only. Use the instance's usual static build, or `./v3 build-static --shared-only` when its published release supports a shared-only refresh, then follow the homepage thumbnails.

Final review: Reused the existing listing rules, display sources, gallery page, and page navigation. The change uses the existing schema and introduces no dependency changes.

Study closeout: Retrospective scope assessment 0.30 (R1, P2). Measured effort unavailable. Maintainer fit judgment pending.

Commit status: planning commit confirmed above. This record accompanies the implementation commit. Deployment and merge are pending.

*— Jiro · 2026-11-23 UTC*
