Cycle: `v3/prompt_library`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); log: [prompt_library_debug_log.md](prompt_library_debug_log.md)
Effort study: `railspike-effort` / `v3/prompt_library` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_library); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.25**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

Maintainer, in a Claude session on 2026-11-14: "For Zen memes, please upgrade the agents page to a prompt sharing page where approved prompts appear that are useful and allow for easy copying, and people can share their prompts. And it should be like interesting for people who use agents a lot so that they actually want to use it. And we should link it from the navbar once it's done." Followed by: "Let's work towards a PR".

## Authorization

The two messages above, 2026-11-14, read as the go-ahead for a one-shot cycle carried through to a pushed branch and an open PR. Merging and deploying are not covered. The approval rule below is an agent choice the maintainer reviews in the PR; changing it is a small follow-up.

## Outcome and work type

Application change. Normal entry: `/agent/` (unchanged URL, now also in the main nav as **Prompts**), plus the thread composer for sharing.

Outcome:

- A prompt is a thread tagged `#prompt`: the title names it, the body is the prompt. Its other tags are its categories. Sharing one is posting such a thread; `/agent/` links to the composer with `#prompt` filled in.
- A prompt appears in the library when it is tagged `#prompt` (by its author or as a label) **and** either its author is an approved member or an approved member has labeled it `#prompt`. Hidden, flagged-away and retracted threads stay out (the existing listed-roots rule).
- The library shows each prompt as a card: title linking to its thread, author, likes and replies, its categories, and the prompt in the existing copy field with a Copy button. Sort is Top (score, then newest) or New (`/agent/?sort=new`). A search box and category chips filter the cards in place.
- Prompts tagged `#prompt` that are not yet approved are listed by title in a collapsed "Waiting" section, so approved members can find and tag them.
- The site's own three prompts (get introduced, post as an agent, run your own site) stay at the top as a short "Start here" section.
- The main nav gains **Prompts** (`/agent/`) while the agent page is on, with an icon in every icon set. Following the page-title rule, the page drops its visible `h1`.

Excluded: paging (the library caps at 200 prompts), copy counts or other telemetry, voting from the card (likes stay on the thread page), new record types, schema changes, seeding prompt content.

## Approach and risks

Read-model only. A new `PromptLibrary` query selects listed roots tagged `prompt` from `threads.board_tags_json` or `threads.thread_labels_json`, joined to `posts` for the body and author. Approval is `profiles.is_approved = 1` for the root author, or an `EXISTS` on `viewer_tags` (`record_family = 'thread-labels'`, tag `prompt`) joined to approved `profiles`.

Risk: `viewer_tags` mirrors every parsed label record, including inactive ones, so a retracted `#prompt` label by an approved member could still count. Mitigation: also require `prompt` to be among the thread's tags (board tags or active labels), so a fully retracted label drops the prompt unless the author tagged it. Remaining edge (approved member's label retracted while an unapproved member's stays) is recorded as a limitation.

Risk: long prompts make tall cards. Mitigation: cap the library's copy field height with scrolling; the Start-here prompts keep their full height.

Risk: nav crowding on desktop. Prompts goes after About in bar order; on phones it sits in More like every destination.

## Reuse decisions

- Copy field and button: `templates/partials/agent_prompt.php` and the script in `templates/pages/agent.php`, unchanged behaviour; the partial gains an optional `rows`/class for the library variant.
- Thread selection: `ThreadKeysetPage::listedRootsSql()` and `listJson()` (as `TagPage` and `CalendarQuery` use).
- Approval: existing `profiles.is_approved` and `viewer_tags` tables; no schema change.
- Tags and links: `/tags/{tag}` links and `PostHashtags` conventions; composer prefill via existing `/compose/thread?board_tags=` query.
- Filter chips: the existing `board-filter` link style used for tabs elsewhere.
- Nav: `TemplateRenderer::navItems()`/`navigationItems()` bar order; icons in `public/assets/theme-nav-icons.css`, widening the existing `/agent/` glyph rules from Tools-only to `.nav-link`.

## Completion conditions

Open `/agent/` from the nav: Start-here prompts copy as before; approved prompts list with Copy working; search and category chips filter; Top/New switch; Waiting lists unapproved prompts; Share opens the composer with `prompt` filled in. With the agent page flag off, neither the page nor the nav item exists.

## Concepts touched

Uses unchanged: thread, tag/label, member approval. New use of tags: `#prompt` marks a shared prompt. No inventory change proposed beyond noting the tag in the agent page docs.

## Verification and recovery plan

- `./v3 test --smoke PresentationProfileMatrixTest NavIconSetsTest` plus a new `PromptLibraryTest` (approved author, label by approved member, unapproved waiting, hidden excluded, sort).
- Render `/agent/` locally with fixture threads; screenshots in Gloss, Word97 and Bubblegum at phone and desktop widths, including the nav icon.
- Full suite not run (maintainer runs it). Baseline smoke failure before changes: `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`.

Starting revision and branch: `26dd2fbff07b1e99b30d2429cd9d322a02318683`; `feat/prompt_library`
Planning commit: `2fe61838`

## Study history

- 2026-11-14T22:10Z intake: whole-cycle projection **0.25**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `agent.php`, `AgentPageController`, `TemplateRenderer` nav code, `TagPage` and the read-model schema; no pristine intake.
- 2026-11-14T22:10Z execution entry: 0.25 reaffirmed.

## Dated execution history

- 2026-11-14T22:10Z: no matching item in `docs/feedback/items/`.
- 2026-11-14T22:40Z: implemented as planned. `ReadModel/PromptLibrary` (one query, no schema change), `AgentPageController` takes the read model and `?sort=`/`?tag=`, `agent.php` renders library then "Use this site with your agent" (`#start`). The copy partial gained `label_hidden` and a `library` variant (height capped at 16rem). About and the first-visit intro now link to `/agent/#start`. Nav: `Prompts` after Recent/Calendar/Gallery, bar order after `gallery`; phone-tab CSS lists `agent`. Flag description updated.
- 2026-11-14T22:40Z: correction found in visual check: the page's own Top and All links (`href="/agent/"`) picked up the nav glyph; the three glyph rules and the main-nav sizing rule now exclude `.board-filter > *`.
- 2026-11-14T22:40Z: Tools still has its Agent Prompts launcher. Removing it now that the nav links the page would follow the UI-copy rule against repeated controls, but it is outside this request; left for the maintainer.

## Study closeout

Intake 0.25; execution entry reaffirmed; no scope revision; **retrospective 0.25**. Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered as scoped: `/agent/` is the Prompts page, linked from the main nav. Approved `#prompt` threads list as cards with Copy, likes, replies/Discuss and category chips; Top/New and category are readable links; search filters in place; unapproved ones sit under "Waiting for approval"; "Share a prompt" opens the composer with `prompt` filled in. The site's three prompts follow under "Use this site with your agent".

Limitations: library capped at 200 with no paging; a retracted `#prompt` label from an approved member still counts while any other `#prompt` label or the author's tag remains (`viewer_tags` keeps retracted label rows); the library starts empty until members post or tag prompts.

## Verification

- Passed: `./v3 test --smoke PromptLibraryTest AppNavigationPlacementTest PresentationProfileMatrixTest NavIconSetsTest`: 476/477. The one failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, fails identically on the starting revision before any change.
- Passed: local server with seeded read-model rows (approved author, member-labeled prompt, waiting prompt). Playwright: search "diff" leaves one card, nonsense search shows "No prompts match.", Copy shows "Copied" and the clipboard holds the prompt, `?tag=coding&sort=new` lists only coding prompts newest first. Screenshots in Gloss, Glossdark, Word97 and Bubblegum at 390px and 1200px, including the nav and phone tab icon.
- Not run: full suite (maintainer runs it); production with real `#prompt` threads (none exist yet).

## Final review

Diff checked for duplication (reuses copy partial, board toolbar/filter, `$rowMeta`, listed-roots SQL) and file scope; `git diff --check` clean. No concept inventory change.

Commit/merge/publication status: planning `2fe61838`, completion `2d9411bc`; pushed with a PR; not merged or deployed.
Attribution: Claude (claude-opus-5-5), 2026-11-14 UTC.
