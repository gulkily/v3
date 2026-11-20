> **Feature plan:** [Step 1](./zen_strip_step1_solution_assessment.md) · [Step 2](./zen_strip_step2_feature_description.md) · [Step 3](./zen_strip_step3_development_plan.md) · [Step 4](./zen_strip_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_strip`; host ledger [zen_strip.md](../effort_ledger/zen_strip.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.70**

Concepts touched: [visitor intro](../../concepts/visitor_intro.md) (moved below the strip); [tag browsing](../../concepts/tag_browsing.md) (extended to pairs of tags in Stage 3). Stage 5 adds `docs/concepts/zen_strip.md`. Feedback: none.

Correction to Steps 1 and 2: zenmemes.com's board is `board_list.php` (the profile's `boardCard` slot is `list`), not `board.php`. The strip goes in `board_list.php`; `board.php` gets the same partial so other profiles can turn it on.

## Completion Contract

- Normal entry: a signed-out visitor opens zenmemes.com; an approved member tags a thread #zen and #meme.
- End-to-end outcome: the first page of the board opens with a row of Zen meme cards, newest first, up to twelve, then "All". "All" opens /tags/zen+meme. The six seed threads show in both.
- Recovery: removing a tag, or retracting the thread, takes it out of the strip after the next read model update. The flag off, or a profile without the setting, gives today's board HTML.
- Deployment: no schema change. Production needs `./v3 build-static` so baked board pages carry the strip.
- Release: merged to main, deployed with the rebuild, and tried on production by the maintainer.

## Key Risks

- **High risk:** baked or cached board pages don't refresh when a label changes. Impact: a new Zen meme never reaches the first screen, or a removed one stays. Early validation: Stage 2 labels a thread on a local copy and reloads the board, baked and live. Mitigation: use the board's existing rebuild trigger for thread labels; if labels don't trigger it, add them in Stage 2 before styling.
- The strip query slows the board. Impact: the busiest page gets slower for every visitor. Early validation: Stage 1 times the query on a copy of production data. Mitigation: one query with a limit of twelve, the `viewer_tags` lookup `PromptLibrary.php` already uses, and one `ListThumbnails::forThreads` call.
- Themes restyle the row. Impact: cards overlap or the row breaks at 380px. Early validation: Stage 4 checks four themes at 380px. Mitigation: strip cards get their own classes, so board-row rules don't reach them.

## Stage 1

**Estimate: 0.15**

- Goal: a query returns the threads that qualify for the strip.
- Dependencies: none.
- Expected changes: a new `ReadModel/ZenStrip` with `load(PDO $pdo, list<string> $tags, int $limit = 12): list<array>`. For each tag, a thread qualifies when the tag is in the root's board tags and the author is approved, or an approved member's label carries it and it is still in `thread_labels_json`. Order: `root_post_created_at` descending. Profile setting `zenStrip => ['tags' => ['zen', 'meme']]` on zenmemes only. A `FORUM_ZEN_STRIP_ENABLED` flag, on by default and site-mutable.
- Verification approach: `./v3 test --smoke ZenStripTest`. Cases: approved author's tags; approved labels; a guest's labels; a pending key's labels; one tag only; a retracted label; a retracted thread. Time the query on a copy of production data.
- Estimate details (extended mode only): driver: the per-tag approval rule; uncertainty: low, because `PromptLibrary.php` already does this for one tag.
- Risks or open questions:
  - Impact: a retracted label still counts, because `viewer_tags` keeps retracted records.
  - Early warning / validation: the retracted-label test case.
  - Mitigation: also require the tag in `thread_labels_json`, as `PromptLibrary.php` does.
- Canonical components/API contracts touched: `PromptLibrary.php` (pattern followed, not changed), `ThreadKeysetPage::listedRootsSql()` (reused), `SiteProfileRegistry.php` (new key), `FeatureFlagRegistry.php` (new flag).

## Stage 2

**Estimate: 0.15**

- Goal: the strip shows first on the board's first page.
- Dependencies: Stage 1.
- Expected changes: `BoardPageController` loads the strip for page 1 when the flag is on and the profile has the setting, with images from `ListThumbnails::forThreads`. A new `partials/zen_strip.php` renders a list of cards: image cards show the thumbnail and its description as alt text; text cards show the title. "All" links to /tags/zen+meme (a dead link until Stage 3, so the two stages ship together). `board_list.php` and `board.php` include it above `visitor_intro.php`. Styles in the shared stylesheet scroll the row sideways.
- Verification approach: `./v3 test --smoke BoardListTest ZenStripTest LocalAppSmokeTest`. The flag-off board HTML matches the board's HTML before the change. Label a thread locally and check the live and baked board both update.
- Estimate details (extended mode only): driver: two board templates and the cache check; uncertainty: medium.
- Risks or open questions:
  - Impact: the cache risk above.
  - Early warning / validation: the label-and-reload check, done before any styling.
  - Mitigation: hook thread labels into the board rebuild if they aren't already.
- Canonical components/API contracts touched: `BoardPageController::board()` and `boardList()`, `templates/pages/board_list.php` and `board.php` (reordered), `ListThumbnails::forThreads` (reused), `partials/visitor_intro.php` (unchanged). `thread_row.php` is not reused: a strip card is a tile, not a row.

## Stage 3

**Estimate: 0.15**

- Goal: /tags/zen+meme lists threads that carry both tags.
- Dependencies: none; it ships with Stage 2.
- Expected changes: the `/tags/` route in `Application.php` accepts tags joined by `+`. `TagPage::page()` and `pageOf()` take a list of tags and require every one, by anyone's board tag or label, as today. `TagsPageController` titles the page "#zen + #meme" and pages it at /tags/zen+meme/2. A single tag works as before.
- Verification approach: `./v3 test --smoke TagPaginationTest LocalAppSmokeTest`, with new pair cases in `TagPaginationTest`. Cases: the pair; one tag; an unknown pair (404 as today); page 2; mixed case; a reversed pair (/tags/meme+zen redirects to /tags/zen+meme, so each set has one address).
- Estimate details (extended mode only): driver: route, query and pagination together; uncertainty: low.
- Risks or open questions:
  - Impact: a `+` in the path is read as a space by some code.
  - Early warning / validation: the route test with the raw path.
  - Mitigation: match `+` in the route pattern itself; never decode it as form data.
- Canonical components/API contracts touched: `ReadModel/TagPage.php`, `Http/TagsPageController.php`, `Application.php` `/tags/` route, `PageNavigation` (reused).

## Stage 4

**Estimate: 0.10**

- Goal: the strip reads well in every theme and with JavaScript off.
- Dependencies: Stage 2.
- Expected changes: theme-specific fixes only where a check fails.
- Verification approach: screenshots at 380px and desktop in Gloss, Gloss Dark, Bubblegum and Ochre, plus one pixel theme (Word97); keyboard focus on each card; the board with JavaScript off.
- Estimate details (extended mode only): driver: number of themes; uncertainty: low.
- Risks or open questions: the theme risk above.
- Canonical components/API contracts touched: theme stylesheets in `public/assets/` (only where a check fails).

## Stage 5

**Estimate: 0.10**

- Goal: docs, deploy note and a final diff review.
- Dependencies: Stages 1–4.
- Expected changes: `docs/concepts/zen_strip.md` and its inventory entry; `tag_browsing.md` names pairs of tags; the flag's description; the PR names `./v3 build-static` as a deploy step.
- Verification approach: the docs smoke test; review the whole diff against Step 2's requirements; check the six seed threads on a local import of zenmemes.com.
- Estimate details (extended mode only): driver: writing; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/` (inventory).

## Study snapshots and approval

- 2026-11-20 UTC: **Approved Step 2** by the maintainer, after dropping the tagline and keeping tag pages open.
- 2026-11-20 UTC, Step 3 handoff: **0.70** projected total, reaffirmed. Moving the strip to `board_list.php` and the cache check add work; dropping the tagline and the offline strip removed about as much. Claude, single exposed rater. Scope v2. Reference R1, hashes not re-verified this session. Baseline `origin/main` `eb0f5177`. Awaiting `Approved Step 3`.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
