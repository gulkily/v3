> **Feature plan:** [Step 1](./post_markdown_step1_solution_assessment.md) · [Step 2](./post_markdown_step2_feature_description.md) · [Step 3](./post_markdown_step3_development_plan.md) · [Step 4](./post_markdown_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)
Effort study: `railspike-effort` / `v3/post_markdown` ([ledger](../effort_estimation_trial.md#cycle-v3post_markdown)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

Branch: `feature/post-markdown`, planning commit `51e1a8e9`.

## Step 4 entry

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 4 entry | 2026-11-03T17:10Z | 0.85 | v2 / R1 / P2, original baseline `fd4d0e0227c18ed840c166e2ce4948233d148769` | Claude (claude-opus-5-5), single exposed rater | Step 3 projection reaffirmed after the planning commit; no new understanding |

Original stage-work estimates copied from Step 3 (not new ratings): S1 0.20, S2 0.20, S3 0.20, S4 0.10, S5 0.15, S6 0.10.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Rewrite the Nov 2 changelog post | Separate: content change | Requested by maintainer | Done before Step 4 |

## Stage 1 - Record format

**Original estimate: 0.20**
**Retrospective assessment: 0.20**

- Changes:
  - `Body-Format: markdown|plain` documented as an optional header in `docs/specs/canonical_post_record_v1.md`; absent means plain.
  - `PostRecord` gains `bodyFormat` (nullable) and `rendersMarkdown()`; `PostRecordParser` reads and validates the header.
  - `LocalWriteService` reads an optional `body_format` input on create/prepare for threads and replies. Default `markdown`, `plain` for QDB quotes; other values are rejected. Builders add the header only when a format is given, so existing builder bytes are unchanged.
  - `SignedPostBundleValidator` accepts the header in client-built records (after `Subject` for threads, after `Author-Identity-ID` for replies). Without this, direct-signed posts carrying the header were rejected as noncanonical.
  - Read model: `posts.body_format` column in the builder and both incremental insert paths; schema version 44 -> 45.
  - `get_post` always prints `Body-Format: markdown|plain`.
- Verification:
  - Baseline full suite before any change: 1833 run, 1817 passed, 16 failed (FrmClient x8, LocalAppSmoke x3, ApprovalSeedCommand, ReadModelBuilderTiming, ReactionRecordFormatParity, TerminalOperatorUiCommand, CheckoutsCommand real-git acceptance). Recorded as pre-existing.
  - `php tests/run.php CanonicalRecordParsersTest WriteApiSmokeTest SignedPostBundleValidatorTest SignedPostTransactionTest SingleRequestPostingTest AgentPostingApiTest ReadModelBuilderTimingTest ReactionRecordFormatParityTest IncrementalReadModelUpdaterTest ReadModelProvenanceTest AgentApiWorkedExampleTest`: all pass except the two baseline failures (`ReadModelBuilderTimingTest`, `ReactionRecordFormatParityTest`).
  - New tests: header optional/validated, builders append only when given, API default/plain/invalid/reply, `get_post` reports plain for legacy fixtures, prepared and direct-signed paths with and without the header.
  - Two exact-bytes prepare tests updated to expect `Body-Format: markdown` (intended change).
- Notes:
  - Read-model rebuild: the schema fingerprint changes, so the app rebuilds the read model on the next request after deploy. A high-traffic deployment instead refuses until `./v3 high-traffic prepare` runs; Stage 6 checks which applies on zenmemes.com.
  - The public offline snapshot keeps its own `posts` table without the format; posts there stay plain. Not in this cycle's scope; noted as a follow-up.
  - `scripts/publish_text.*` build direct records without the header, so their posts stay plain unless updated later.
- Estimate details: original scope unchanged; no revision. Retrospective matches: the signed-bundle canonical check was the one unplanned discovery and was small.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 2 - Post-safe renderer

**Original estimate: 0.20**
**Retrospective assessment: 0.15**

- Changes:
  - `PublicMarkdownRenderer::renderPost(string): string` shares the docs parser through a post-mode switch; docs mode is untouched.
  - Post mode: no front matter, no hidden comments, no raw `<img>` line handling (all shown as escaped text); headings nested two levels down (`#` -> `h3`, capped at `h6`) without page ids; links only for absolute http(s) URLs, with `rel="nofollow ugc noopener"`, other targets left as visible source; bare http(s) URLs linked with trailing punctuation kept outside; emphasis requires text against both markers; single newlines become `<br>`. Any internal failure falls back to escaped text with line breaks.
- Verification:
  - New `tests/PostMarkdownRendererTest.php` (registered in `tests/run.php`): common syntax, heading nesting, hashtag lines stay text, line breaks, bare/explicit links, URLs in code and parentheses, arithmetic asterisks, twelve hostile inputs (raw HTML, `javascript:`/`data:`/protocol-relative/docs links, attribute injection, images, comments, front matter) with no unsafe markup or event-handler attributes, docs mode unchanged after a post render.
  - `php tests/run.php PostMarkdownRendererTest MarkdownListStructureTest MarkdownTaskListTest MarkdownPipeTableTest PlatformDocsPageTest PlatformDocsCatalogTest`: 61 run, 61 passed.
  - Docs output comparison: every `.md` under `docs/` (1798 files) rendered with the Stage 1 renderer and the Stage 2 renderer; 0 differ.
  - Manual render of the Nov 2 changelog post text: lists, links and the hashtag line render as intended.
- Notes:
  - The paragraph-then-list shape agents tend to write (a heading-like line followed directly by `- items`) renders as a paragraph and a list, which suits changelogs.
- Estimate details: original scope unchanged; no revision. Retrospective lower than the original: the shared parser needed only a mode switch, not restructuring.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 3 - Thread pages

**Original estimate: 0.20**
**Retrospective assessment: 0.20**

- Changes:
  - New site flag `FORUM_POST_MARKDOWN_ENABLED` ("Markdown in posts", Rendering group, on by default, site-mutable).
  - `MediaEmbedRenderer::render()` takes a `$markdown` argument: text between image references and embed cards goes through `renderPost()`; only embed URLs alone on their line become cards, others stay ordinary links. The plain path is the existing code.
  - `TemplateRenderer` adds `$isMarkdownPost($record, $formatKey)` and `$renderPostBody($record, $body, $formatKey)`; the four full-post templates (`post_card`, `thread_root_card`, `paned_thread_reply_tree`, `paned_board_content_article`) use them and add a `post-markdown` class.
  - Full-post queries select `posts.body_format` (thread posts, single post, reply lists, paned board root as `root_post_body_format`, authored content).
  - `site.css` (non-critical part): spacing, headings, lists, quotes, code, tables, rules and task items for `.post-markdown`, using `currentColor` so themes supply colors.
- Verification:
  - New tests: `WriteApiSmokeTest::testThreadPagesRenderMarkdownOnlyForMarkdownPostsAndRespectTheFlag` (Markdown thread/reply/post page render; plain post shows source; flag off shows source), two `MediaEmbedRendererTest` cases (standalone embed card plus inline link; flag-off output byte-identical to the old helper), flag list in `FeatureFlagEvaluatorTest`.
  - Related suites (BoardList, BoardListingQuery, FeatureFlag x3, Forte x2, LocalAppSmoke, MediaEmbed x7, MusicMediaEmbed, PostMarkdownRenderer, Qdb x7, RecentPost, TemplateRendererMediaEmbedsScript, ThreadTitle): 322 run, 319 passed; the 3 failures are the baseline `LocalAppSmokeTest` failures.
  - Byte-identical corpus check: every `/threads/{id}` and `/posts/{id}` page for both post fixtures (`parity_minimal_v1`, `retraction_pin_legacy`) rendered with the Stage 2 commit and with this stage; outputs identical after normalizing asset fingerprints.
  - Browser check (headless Chrome against a local dev server, one Markdown thread with the Nov 2 changelog text and one reply with heading, inline code, code block, table, quote and task items): Light, Word97, Chicago and Midnight at 1280px and 390px. Lists, links, code, tables and quotes read correctly in all eight; the first pass showed task checkboxes stretched by global form styles, fixed with scoped rules before commit. Screenshots kept locally, not committed.
- Notes:
  - Post styles sit after the critical-CSS marker, so they load with the deferred stylesheet like other card styles.
  - Real zenmemes.com posts are checked live in Stage 6 (an existing post must look unchanged).
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 4 - Previews

**Original estimate: 0.10**
**Retrospective assessment: 0.15**

- Changes:
  - New `ForumRewrite\Support\PostText::plainPreview(string $body, ?string $format): string`: plain and legacy posts pass through unchanged; Markdown posts lose heading, quote, task, emphasis, inline-code and link markup, fence and delimiter lines, and table pipes, keeping their words line by line.
  - Read model: thread `body_preview` and activity labels go through it in the builder and the incremental updater, so Board, RSS, Forte sorting and every `body_preview` consumer see plain words.
  - Titles and excerpts built from raw bodies use it too: thread titles (`Application`, `TemplateRenderer`, `BoardPageController`), Forte content titles, the reply composer's thread title, Recent excerpts and context titles (Recent now selects the post and root formats), and profile post lists (`AuthoredContentRepository`).
  - Templates: `$plainText($record, $text, $formatKey)` helper used for the Board card preview, quote cards, the QDB welcome news body, the QDB quote root body and the reply composer's parent.
- Verification:
  - New `tests/PostTextTest.php` (registered): passthrough for plain/legacy, every markup element, fence-only bodies.
  - New `WriteApiSmokeTest::testPreviewsShowMarkdownPostsAsPlainWords`: all-threads Board, Recent and the reply composer show `Release notes for v3` for a `## Release **notes** for \`v3\`` post and never the markup; a plain post's literal `## ... **stars**` text stays as typed.
  - Related suites (Board x2, Forte x2, Qdb x7, RecentPost, ThreadTitle, PostText, PostMarkdownRenderer, Profile, Activity, TagPagination): 107 run, 107 passed. Full `WriteApiSmokeTest`: 203 run, 203 passed.
- Notes:
  - Retrospective above the original: more title/excerpt paths derive text from raw bodies than the Step 3 grep found (compose title, Forte titles, Recent context titles, activity labels).
  - Board search (`?q=`) still matches against the raw body, so searching for `**` or `##` can match Markdown posts; left as is.
- Estimate details: original scope unchanged; scope of call sites larger than understood at Step 3 (new understanding, not changed scope).

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
