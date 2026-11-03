> **Feature plan:** [Step 1](./post_markdown_step1_solution_assessment.md) · [Step 2](./post_markdown_step2_feature_description.md) · [Step 3](./post_markdown_step3_development_plan.md) · [Step 4](./post_markdown_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 2)
Effort study: `railspike-effort` / `v3/post_markdown` ([ledger](../effort_estimation_trial.md#cycle-v3post_markdown)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 1.20** (revised from 0.85; see Revision)

## Completion Contract

- Normal entry: New post or Reply in the composer, or `prepare_thread` / `prepare_reply` / direct-signed records through the API.
- End-to-end outcome: a Markdown post renders formatted on its thread page and as markup-free text in previews; a plain post shows exactly as typed; posts without a recorded format render as today.
- Recovery: retract and repost in the other format; the site-wide flag returns every post to plain text without data loss.
- Deployment/external verification: deploy to zenmemes.com through Tools -> Deploy, rebuild the read model if the stage requires it, then make one Markdown composer post, one plain composer post and one agent API post, and confirm an existing post is unchanged.
- Release condition: all stage tests and the docs/renderer suites pass; the live checks above pass.

## Key Risks

- **High risk:** unsafe HTML or links from untrusted post bodies. Validate in Stage 2 with hostile-input tests before any template uses the renderer; escape first and use an http(s)-only policy separate from the docs catalog.
- **High risk:** record compatibility. Existing records and direct-signed client records must parse unchanged. Validate in Stage 1 against the existing fixture corpus and a direct-signed record with and without the header.
- **High risk:** existing posts change appearance. Validate in Stage 3 by rendering every local fixture post before and after and requiring byte-identical output for posts without the header.
- Read-model rebuild on deploy. Confirm in Stage 1 whether adding the field triggers an automatic rebuild or needs a documented step; Stage 6 depends on it.

## Stage 1

**Estimate: 0.20**

- Goal: posts can record a format, and it reaches the read model and the API.
- Dependencies: none.
- Expected changes: optional `Body-Format: markdown|plain` header in the canonical post record spec; `PostRecord` gains a nullable `bodyFormat` (absent means plain); invalid values rejected; write service accepts a `body_format` input on create/prepare for threads and replies, defaulting to `markdown`; direct-signed records keep whatever they carry; read-model `posts` gains a nullable format field; `get_post` shows `Body-Format`.
- Verification approach: parser tests (absent, both values, invalid, on replies); write and prepare tests for default and explicit plain; direct-signed record with and without the header; read-model rebuild over the fixture corpus; existing write/API suites pass.
- Estimate details: driver: one header threaded through spec, parser, write paths, read model and API; uncertainty: medium, mainly the read-model rebuild path.
- Risks or open questions:
  - Impact: a parser change could reject existing records.
  - Early warning / validation: run the full fixture corpus through the parser first.
  - Mitigation: header is optional and ignored when absent.
- Canonical components/API contracts touched: `canonical_post_record_v1.md`, `PostRecordParser`, `LocalWriteService` create/prepare/signed paths, `ReadModelSchema`/builder/incremental updater, `get_post`.

## Stage 2

**Estimate: 0.20**

- Goal: a post-safe rendering mode in the shared renderer, proven against hostile input.
- Dependencies: none (parallel to Stage 1).
- Expected changes: `PublicMarkdownRenderer::renderPost(string $markdown): string` sharing the block/inline parser; no front matter, no docs link resolution, http(s) links only with `rel="nofollow ugc"`, bare http(s) URLs linked, headings demoted to fit inside a post card without page-level ids; docs mode unchanged.
- Verification approach: hostile-input tests (raw HTML, `javascript:`/`data:` links, attribute injection, nested emphasis, unclosed fences); feature tests for headings, lists, bold, inline code, fenced code, tables, bare URLs; docs renderer suites unchanged.
- Estimate details: driver: separating link policy and heading ids from docs-only behavior without forking the parser; uncertainty: medium.
- Risks or open questions:
  - Impact: a shared change could alter docs output.
  - Early warning / validation: existing `PlatformDocs*` and Markdown list/table tests.
  - Mitigation: post mode is an explicit option; docs paths untouched.
- Canonical components/API contracts touched: `PublicMarkdownRenderer`.

## Stage 3

**Estimate: 0.20**

- Goal: thread pages render Markdown posts, behind a flag, with embeds and images intact.
- Dependencies: Stages 1 and 2.
- Expected changes: the body helper in `TemplateRenderer` picks Markdown or plain per post; image reference and media embed lines are handled first and the text between them rendered in the post's format; full-post templates pass the format; new `POST_MARKDOWN_ENABLED` flag, on by default; post-body CSS for headings, lists, code and tables in default, Word97, Chicago and Midnight.
- Verification approach: before/after render of every fixture post (no header: byte-identical); Markdown post with an embed and an image; flag off shows plain; browser check of one Markdown post in four themes at desktop and 390px widths.
- Estimate details: driver: embed/image interleaving and theme CSS; uncertainty: medium.
- Risks or open questions:
  - Impact: old posts change appearance.
  - Early warning / validation: the byte-identical corpus comparison.
  - Mitigation: plain path stays the existing code.
- Canonical components/API contracts touched: `TemplateRenderer` body helper, `MediaEmbedRenderer`, `post_card`, `thread_root_card`, `paned_thread_reply_tree`, `paned_board_content_article`, feature flag registry, theme CSS.

## Stage 4

**Estimate: 0.10**

- Goal: previews and quotes show Markdown posts as clean plain text.
- Dependencies: Stage 1.
- Expected changes: shared `PostText::plainPreview(string $body, ?string $format): string` removing markup; used for `body_preview`, Board/Recent previews, quote cards, reply-composer parent and QDB welcome; plain posts unchanged.
- Verification approach: unit tests for each syntax element; preview templates with a Markdown and a plain post.
- Estimate details: driver: several call sites sharing one helper; uncertainty: low.
- Risks or open questions:
  - Impact: a missed preview path shows raw markup.
  - Early warning / validation: grep for every body/preview render site.
  - Mitigation: one helper at the read-model preview plus templates.
- Canonical components/API contracts touched: read-model preview, `thread_card`, `quote_card`, `recent_entry`, `compose_reply`, `qdb_welcome`.

## Stage 5

**Estimate: 0.15**

- Goal: authors and agents can choose the format and find the syntax.
- Dependencies: Stages 1 and 3.
- Expected changes: "Plain text" checkbox (unchecked) and syntax link in the shared composer for threads, replies and the paned composer; `body_format` documented in llms.txt and `agent_api.md`; new `docs/reference/markdown_syntax.md`, linked from the renderer runbook; concept entry `post_body_formatting.md`; updates to `post_presentation.md` and `shared_composer.md`.
- Verification approach: composer tests for both checkbox states; `LlmsTxtRendererTest` covers the field; docs link and render checks.
- Estimate details: driver: three composer surfaces plus documentation; uncertainty: low.
- Risks or open questions:
  - Impact: the checkbox missing from one composer path.
  - Early warning / validation: test every composer entry point.
  - Mitigation: add it in the shared composer partial.
- Canonical components/API contracts touched: `composer.php`, `thread_compose_form.php`, reply composer, `LlmsTxtRenderer`, `agent_api.md`, concept inventory.

## Stage 6

**Estimate: 0.10**

- Goal: replay the Step 2 known-user checks on zenmemes.com.
- Dependencies: Stages 1–5 merged.
- Expected changes: none to code; implementation summary records evidence; this cycle's ledger closeout.
- Verification approach: deploy via Tools -> Deploy; rebuild read model if Stage 1 requires; post one Markdown and one plain thread from the composer and one agent API post with no format field; confirm an existing post renders unchanged; retract test posts unless the maintainer keeps them.
- Estimate details: driver: live deploy and posting; uncertainty: low-medium (host rebuild time).
- Risks or open questions:
  - Impact: live rendering differs from local.
  - Early warning / validation: the Deploy page result and one reload.
  - Mitigation: turn the flag off and fix forward.
- Canonical components/API contracts touched: none new.

## Revision 2026-11-03 (after Step 2 revision)

Step 2 was revised and re-approved for three enhancement requests (Markdown checkbox, naive detector, post-factum format changes). Stages 1–5 are complete and unchanged. Stage 6 (live checks) now runs last and also covers the new behavior. New stages 7–10 follow. The cycle now has ten stages, past Railspike's guide of eight; the maintainer chose one cycle over a split.

**Completion Contract additions**

- Entry: the composer's Markdown box; a post's action row (beside Retract) for its author or a root-approved operator; `prepare_post_format` / `create_prepared_post_format` for signed clients.
- Outcome: composer posts are plain unless the box is ticked (by hand or by the detector); a switched post shows its new format on thread pages, previews and `get_post`.
- Recovery: switch back the same way.

**Key Risks additions**

- **High risk:** switching someone else's post. Only the author or a root-approved operator; validate in Stage 9 with author, operator, other member, unsigned and forged-signature cases before the UI exists.
- **High risk:** rebuild and incremental read models disagreeing about a post's format. Validate in Stage 8 by comparing a full rebuild with incremental updates after several switches.

## Stage 7

**Estimate: 0.10**

- Goal: the composer offers a **Markdown** box, unchecked, that a small detector ticks.
- Dependencies: Stage 5.
- Expected changes: composer menu checkbox `body_format=markdown` (label "Markdown"), with a hidden `body_format=plain` fallback so an unticked box sends plain; `PostFormatDetector` in a small script: heading, list, emphasis, link, fence, quote or table lines tick the box until the author changes it by hand; signed, unsigned and Outbox paths send the value; the "Plain text" wording is removed. API default unchanged.
- Verification approach: Node tests for the detector (positive and negative drafts, manual override sticks); PHP tests that an unticked unsigned submission records `plain` and a ticked one `markdown`; existing JS field-shape tests; browser check of the menu.
- Estimate details: driver: detector heuristics and keeping the manual override; uncertainty: low.
- Risks or open questions:
  - Impact: false positives tick the box on ordinary text.
  - Early warning / validation: negative cases such as `2 * 3`, a lone `-`, hashtags and URLs.
  - Mitigation: require clear Markdown shapes; the box is visible before posting.
- Canonical components/API contracts touched: `composer.php`, `browser_signing.js`, `outbox_compose.js`, new detector script, `compose.css`.

## Stage 8

**Estimate: 0.25**

- Goal: a post's format can change through a side record that the read model applies.
- Dependencies: Stage 1.
- Expected changes: `post_format_record_v1` spec (`Record-ID`, `Created-At`, `Post-ID`, `Body-Format`, `Author-Identity-ID`, optional `Actor-Identity-ID` for operators, detached signature); parser and repository path `records/post-formats/`; read model applies the latest record per post after posts load (rebuild and incremental), updating `posts.body_format` and the thread `body_preview`.
- Verification approach: parser tests; rebuild versus incremental equality after several switches, including an old post with no header and a retracted post (ignored).
- Estimate details: driver: ordering in both read-model paths and preview refresh; uncertainty: medium.
- Risks or open questions:
  - Impact: formats differ between rebuild and incremental.
  - Early warning / validation: the equality test.
  - Mitigation: one shared apply function used by both paths.
- Canonical components/API contracts touched: new record spec and parser, `CanonicalRecordRepository`, `ReadModelBuilder`, `IncrementalReadModelUpdater`.

## Stage 9

**Estimate: 0.20**

- Goal: authors and operators can sign a format change through the API.
- Dependencies: Stage 8.
- Expected changes: `LocalWriteService::preparePostFormat(array $input): array` and `createPreparedPostFormat(array $input): array`, reusing the retraction flow's prepared-token, signature and author-or-operator checks; routes `prepare_post_format` and `create_prepared_post_format`; `get_post` reflects the change; no change for retracted or authority posts.
- Verification approach: signed tests for author, root-approved operator, other member, unsigned and forged signatures, retracted and identity/approval posts; replay of the same signed request is safe.
- Estimate details: driver: authority checks and replay; uncertainty: medium.
- Risks or open questions:
  - Impact: a member switches someone else's post.
  - Early warning / validation: the authority tests above.
  - Mitigation: reuse the retraction authority rule rather than a new one.
- Canonical components/API contracts touched: `LocalWriteService`, `WritePostAndIdentityApiController`, `ApiRouteCatalog`.

## Stage 10

**Estimate: 0.15**

- Goal: the switch is a visible action and is documented.
- Dependencies: Stages 7 and 9.
- Expected changes: "Show as Markdown" / "Show as plain text" in the post's more-actions panel beside Retract, for the author or an operator, signing in the browser like retraction; llms.txt, `agent_api.md`, the syntax reference and the concept entry describe the switch.
- Verification approach: template tests for who sees the control; a browser check of the panel; `LlmsTxtRendererTest`.
- Estimate details: driver: browser signing reuse; uncertainty: low-medium.
- Risks or open questions:
  - Impact: the control appears for people who cannot use it.
  - Early warning / validation: template tests per viewer.
  - Mitigation: same visibility rule as Retract.
- Canonical components/API contracts touched: `retract_post_control.php` (or a sibling partial), `browser_signing.js`, `LlmsTxtRenderer`, docs.

## Stage 6 (revised)

**Original estimate: 0.10**
**Revised estimate: 0.15**

- Goal unchanged, plus: a composer post with the detector ticking the box, one with it unticked, and switching an existing post (an old one) to Markdown and back, on zenmemes.com.
- Dependencies: Stages 1–5 and 7–10 merged and deployed.

*— Revised by Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 revision | 2026-11-03T18:30Z | 1.20 | v3 / R1 / P2, original baseline `fd4d0e0227c18ed840c166e2ce4948233d148769` | Claude (claude-opus-5-5), single exposed rater; inspected retraction record, control and authority rule | Reaffirms the Step 2 revision; new stage-work estimates S7 0.10, S8 0.25, S9 0.20, S10 0.15, S6 revised 0.15, independent annotations, not summed |

## Concepts touched

- [Post presentation](../../concepts/post_presentation.md): updated in Stage 5.
- [Shared composer](../../concepts/shared_composer.md): updated in Stage 5.
- Post body formatting: new entry in Stage 5.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Rewrite the Nov 2 changelog post | Separate: content change | Requested by maintainer | Done |
| Make Markdown optional | Changed scope in Step 1 | Approved | Stages 1, 3, 5 |
| Syntax reference doc | In-scope refinement | Approved | Stage 5 |

## Study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-11-03T17:05Z | 0.85 | v2 / R1 / P2, original baseline `fd4d0e0227c18ed840c166e2ce4948233d148769` | Claude (claude-opus-5-5), single exposed rater; additionally inspected `LocalWriteService` write paths and read-model preview code | Reaffirmed: six stages cover the contract including live checks; stage estimates are independent annotations, not summed |

Earlier snapshots are preserved in Steps 1 and 2. Frozen R1 hashes verified 2026-11-03. No implementation, measured labor or accepted outcome; planning remains uncommitted.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
