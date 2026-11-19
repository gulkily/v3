> **Feature plan:** [Step 1](./thread_port_step1_solution_assessment.md) · [Step 2](./thread_port_step2_feature_description.md) · [Step 3](./thread_port_step3_development_plan.md) · [Step 4](./thread_port_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_port`; host ledger [thread_port.md](../effort_ledger/thread_port.md). Standing P2 enrollment.

Extended estimates: enabled. Stage estimates are copied from Step 3, not newly rated.

Cycle estimate: 0.95

Branch: `thread-port`, from `origin/main` `7d9b4b4d`; planning commit `2e97e6ca`.

Step 4 entry (2026-11-19 UTC): Step 3 projection **0.95** reaffirmed after the planning commit, before implementation. Claude, same exposed rater.

## Stage 1 - Ported headers and read-model columns

**Original estimate: 0.12**

**Retrospective: 0.12**

- Changes:
  - New `PortedSource` reads the four `Ported-*` headers, all or none, with Hacker News item addresses only. `PostRecord` gains `ported`.
  - `posts` gains `ported_from`, `ported_author`, `ported_created_at` and `ported_by`, filled by the full rebuild and both incremental insert paths. A retraction clears them. Schema 55 → 56; `read_model_schema_v1.md` updated.
  - Concept entry `docs/concepts/ported_thread.md` created.
- Verification:
  - `./v3 test ThreadPortRecordTest`: 3 passed. These cover parsing, partial and wrong-site rejection, and a schema-55 database rebuilt at startup before the columns are read.
  - `./v3 test --smoke EventWhenRecordTest TagUnlocksTest`: 498 passed, 0 failed.
- Notes: `tests/run.php` lists test files by hand; the new file is added there.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 2 - Ported bylines

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - The read model stores a ported post's `author_label` as "name on Hacker News" in the full rebuild and both incremental paths. Every view that prints a byline shows it with no further change, offline included.
  - `TemplateRenderer::renderAuthorHtml` links the site name to `ported_from`. The 11 queries that select `posts.author_label` also select `posts.ported_from`.
  - `RecentPostRepository` lists a ported thread by its root only.
  - `post_presentation.md` gains a Ported line.
- Verification:
  - `./v3 test ThreadPortRecordTest`: 4 passed. The new test checks both bylines link to Hacker News, no `/user/` or `/profiles/` link appears, and Recent shows the root but not the reply.
  - `./v3 test --smoke ThreadPortRecordTest RecentPostTest`: 489 passed, 0 failed.
- Notes:
  - Profiles needed no change: a ported post has no `Author-Identity-ID`, so `linkPostAuthors` and profile counts never see it. Signal actors count reactions, not authorship, so they are unchanged.
  - The offline snapshot copies `author_label`, so it shows the ported byline without the link.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 3 - One-commit write

**Original estimate: 0.18**

**Retrospective: 0.18**

- Changes:
  - New `Port\PortFile` and `Port\PortItem` read and write the plain-text port file (`hn-v1`). The reader refuses a file over 500 comments, a comment whose parent comes later or is missing, and malformed items, before anything is written.
  - `LocalWriteService::createPortedThread(PortFile, porterIdentityId)` writes all records plus one `ported` thread label in one commit. It then runs `synchronizePostDerivedState` per record and stops early if one falls back to a rebuild. A repeat port returns the existing thread.
  - Refused characters become `?` instead of refusing the port.
  - `Ported-By` now requires the `openpgp:<40 hex>` identity form, the same rule reaction records use.
  - `ported_thread.md` describes the file and the write.
- Verification:
  - `./v3 test ThreadPortWriteTest ThreadPortRecordTest`: 7 passed. These cover one commit for 3 and for 300 comments, every parent pair, the `ported` label, a repeat port, a full rebuild keeping parents and order, a missing parent refused with no commit, `%` escaping round-trips, and an emoji replaced by `?`.
  - Timing probe: 300 comments took 3.6 s in total (read model 3.5 s, about 12 ms per post), incremental path, no rebuild.
- Notes: production may be slower than this workspace. If needed, a later cycle can batch the incremental update.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 4 - Hand-off, preview and flags

**Original estimate: 0.15**

**Retrospective: 0.18**

- Changes:
  - `PortPageController` handles `POST /port`, which holds the file and answers 303 to `/port/<id>`. `GET /port/<id>` shows the preview, and `POST /port/<id>` runs Port. Port needs `RouteServices::signedInApproved` and `SameOriginRequest`.
  - The routes sit behind `FORUM_THREAD_PORT_ENABLED` in `Application` and are listed as application routes.
  - `Port\HeldPortStore` is a private folder keyed by repository, like the staged store. IDs are 12 hex digits from the file's hash, so sending the same file twice returns the same preview. Held ports expire after 7 days, with at most 200 held.
  - Two flags in `FeatureFlagRegistry` (EXPERIENCE group, off by default) and in `FeatureFlagEvaluatorTest`'s order lists.
  - `createPortedThread` now takes back every written file and unstages them if writing or the commit fails.
  - `templates/pages/port.php` shows Port, Try again, Already ported, waiting-for-a-member, members-only or Sign in.
- Verification:
  - `./v3 test ThreadPortPageTest`: 3 passed. These cover a cross-site POST held and redirected, the preview, a cross-site Port refused with nothing written, a same-origin Port writing 3 posts, and the held port removed afterwards. A repeat shows Already ported. A guest sees Sign in, or Waiting with the guest flag on. A failing pre-commit hook returns 409 with Try again, the error is kept, the tree stays clean, and the retry then succeeds. A malformed form gets 422. The real `Application` route answers 303 to a cross-site POST, and 404 with the flag off.
  - `./v3 test --smoke` plus all port tests, `FeatureFlagEvaluatorTest`, `FeatureFlagsBehaviorTest` and `StagedPageTest`: 497 passed, 0 failed.
- Notes:
  - On an approved-members-only site, the members-only gate turns away the signed-out cross-site POST. Porting there would need a gate exception; not in scope.
  - `docs/concepts/feature_flags.md` describes the registry, not individual flags, so it is unchanged. The flags are documented in `ported_thread.md`.
  - The rollback was not planned. It closes the "nothing half-written" recovery from the Completion Contract.
- Estimate details: retrospective 0.18 above the original 0.15 because of the unplanned rollback; scope unchanged.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 5 - Hacker News bookmarklet

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - `hnPortSubmit` in `tools_bookmarklets.js` reads a story page, a comment's own page or a selection into a `hn-v1` port file and submits it by `POST` to `/port`, same-window or `target=_blank`. `bookmarkletScript` serializes it for the new kind `hn-port`. The host prefix and copy code are reused unchanged.
  - `ToolsPageController::bookmarklets` lists **Port HN** in both modes while `FORUM_THREAD_PORT_ENABLED` is on.
  - `[deleted]` is accepted as `Ported-Author` and port-file author.
  - Synthetic Hacker News pages in `tests/fixtures/hn/` copy the markup with invented users and text, so no real comments are kept in the repository.
- Verification:
  - `node tests/browser/thread_port_bookmarklet.mjs` (Chrome, Playwright): ok. It covers the story page (8 items, every parent pair, full links, deleted author, `%%` text, no reply links) and a comment's own page (root plus story title and link, new-window). It also covers a selection in a nested comment (that subtree only) and a non-Hacker-News page (alert, nothing sent). Every sent file is parsed by `PortFile`.
  - Ad-hoc run, not committed, on the real pages saved during planning: item 8863 gave 71 comments, matching the page's 71 rows; item 9224 gave the comment chain 9224 → 9272 → 9479.
  - `./v3 test ThreadPortPageTest ThreadPortRecordTest ThreadPortWriteTest`: 11 passed, including the Bookmarklets page listing Port HN twice with the flag on and never with it off.
  - `node tests/browser/bookmarklet_copy.cjs`: pass, so the existing seven are unchanged.
- Notes:
  - `npm install` added the already-approved `playwright-core` to the ignored `node_modules`; no dependency change.
  - Hacker News splits very long threads into pages ("More"). The bookmarklet ports the page it is on.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
