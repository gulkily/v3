> **Feature plan:** [Step 1](./theme_directive_step1_solution_assessment.md) · [Step 2](./theme_directive_step2_feature_description.md) · [Step 3](./theme_directive_step3_development_plan.md) · [Step 4](./theme_directive_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/theme_directive`; [host ledger](./effort_estimation_trial.md#cycle-v3theme_directive). Standing P2 enrollment. Split from [`v3/engagify_theme`](./engagify_theme/engagify_theme_step1_solution_assessment.md); builds on its page themes.

Extended estimates: enabled

Cycle estimate: 0.75

Concepts touched: [page theme](../concepts/page_theme.md) (extended in Stage 1, entry updated in Stage 6); [thread title](../concepts/thread_title.md) and [calendar](../concepts/calendar.md) (precedents, unchanged).

## Completion Contract

- Normal entry: the thread's author or an operator posts a `theme:` line, or picks a theme in the composer, on a new thread or a reply, from the browser or the signed-post API.
- End-to-end outcome: the thread page shows that theme to every reader before first paint; the hidden form leaves no line in the text; other pages are unchanged.
- Recovery: retracting the post, or `theme: none`, restores the previous state. Exit works as for any page theme. Records without the header read as before.
- Deployment: the release rebuilds the read model for the schema bump and the baked thread pages.
- Release condition: smoke and changed tests pass; a browser check covers both forms on a new thread and a reply, retraction, `theme: none`, a non-author's line and Exit. The full suite is not run.

## Key Risks

- **High risk:** **Signed record format.** The client-signed subset rejects unknown or out-of-order headers. Impact: a half-finished change rejects valid posts. Early validation: Stage 3 posts signed threads and replies with and without `Theme`. Mitigation: place `Theme` right after `Body-Format`, optional, and change parser, validator and browser builder together.
- **High risk:** **Read-model schema bump.** Impact: a deploy that skips the rebuild serves without the theme column. Early validation: Stage 4 rebuilds from scratch and through the incremental updater. Mitigation: bump the schema version as earlier columns did, so the existing version check forces a rebuild.
- **Baked composer.** Thread pages are prebuilt, so the page cannot know whether the viewer is the author. Impact: the theme choice shows to people it will not affect. Early validation: Stage 5 checks what the baked composer can know about the viewer. Mitigation: reveal the choice in the browser for the author or an operator if the page knows the viewer; otherwise show it with a short "author or operator only" note.

## Stage 1

**Estimate: 0.15**

- Goal: a visible `theme:` line from the thread's author or an operator sets the thread page's theme.
- Dependencies: none.
- Expected changes: new `Support/ThemeDirective` with `extract(string $body): ?string` (a theme name or `none`; quoted lines never count). New `ThreadTheme::forThread(PDO, string $repositoryRoot, string $threadId): ?string`, following `EventStatus`: latest valid line from an unretracted, verified post by the root's author or a current operator. The thread page controller passes it to the renderer, which uses it ahead of `SiteProfileRegistry::pageThemeFor()` and drops names the site does not permit.
- Verification approach: unit tests for the directive reader; smoke tests for author, operator, other user, `none`, unknown name, and precedence over the site list.
- Estimate details: driver: authority rule and precedence in the renderer; uncertainty: low.
- Risks or open questions:
  - Impact: other directive readers might match `theme:` text in ordinary posts.
  - Early warning / validation: the test set includes quoted lines and mid-sentence mentions.
  - Mitigation: whole-line match only, as `event:` does.
- Canonical components/API contracts touched: `EventStatus.php` and `EventStatusDirective.php` (pattern reused); `TitleDirectiveAuthority.php` (author-or-operator rule reused); `SiteProfileRegistry.php` and `TemplateRenderer.php` (extended: thread theme precedes the site list); `layout.php` and `theme_toggle.js` (reused unchanged).

## Stage 2

**Estimate: 0.07**

- Goal: baked thread pages pick up a theme change and a retraction.
- Dependencies: Stage 1.
- Expected changes: confirm reply and retraction paths call `invalidateBoardThread()`; add the call wherever a theme change could skip it.
- Verification approach: build the thread's static file, post a theme reply, rebuild, and read the `data-page-theme` mark; repeat after retraction.
- Estimate details: driver: tracing write paths; uncertainty: low.
- Risks or open questions: none beyond the Key Risks.
- Canonical components/API contracts touched: `StaticArtifactInvalidator.php` (reused); `LocalWriteService.php` (inspected, extended only if a path is missing).

## Stage 3

**Estimate: 0.15**

- Goal: posts can carry a hidden `Theme:` header, from the server and the signed-post API.
- Dependencies: none; can run beside Stage 1.
- Expected changes: canonical post record spec gains optional `Theme` on threads and replies, after `Body-Format` in the signed subset. `PostRecord` gains `?string $theme`; `PostRecordParser` and `SignedPostBundleValidator` read and order it. `LocalWriteService` and the `create_*`/`prepare_*` routes accept a `theme` input. Value: lowercase theme name or `none`; site permission is checked on read.
- Verification approach: parser and validator tests for present, absent, misplaced and repeated headers; a signed reply round trip.
- Estimate details: driver: header order through four components; uncertainty: medium.
- Risks or open questions: see the signed record format risk.
- Canonical components/API contracts touched: `docs/specs/canonical_post_record_v1.md` (extended); `PostRecord.php`, `PostRecordParser.php`, `SignedPostBundleValidator.php`, `LocalWriteService.php` (extended, following `Body-Format`).

## Stage 4

**Estimate: 0.10**

- Goal: the hidden form sets the thread theme, and the latest setting across both forms wins.
- Dependencies: Stages 1 and 3.
- Expected changes: `posts` gains a nullable `theme` column; schema version 50 → 51; `ReadModelBuilder` and `IncrementalReadModelUpdater` fill it. `ThreadTheme` reads the column and the body line from the same rows, ordered as now.
- Verification approach: full rebuild and incremental tests; smoke tests mixing both forms and retraction.
- Estimate details: driver: schema bump and two write paths into the read model; uncertainty: low.
- Risks or open questions: see the schema bump risk.
- Canonical components/API contracts touched: `ReadModelSchema.php`, `ReadModelMetadata.php`, `ReadModelBuilder.php`, `IncrementalReadModelUpdater.php` (extended like `body_format`).

## Stage 5

**Estimate: 0.12**

- Goal: posters pick a hidden theme in the browser composer.
- Dependencies: Stage 3.
- Expected changes: a theme choice in the compose action menu beside the Markdown toggle, listing the site's permitted themes plus "None". `browser_signing.js` and `outbox_compose.js` add the header after `Body-Format`; the server composer path passes `theme`.
- Verification approach: browser check at desktop and 380px: new thread and reply with a hidden theme, then the thread page in a fresh browser.
- Estimate details: driver: two client record builders and the visibility rule; uncertainty: medium.
- Risks or open questions: see the baked composer risk.
- Canonical components/API contracts touched: `templates/partials/composer.php`, `compose_action_menu.php` (extended); `browser_signing.js`, `outbox_compose.js` (extended).

## Stage 6

**Estimate: 0.06**

- Goal: document thread themes and close out.
- Dependencies: Stages 1–5.
- Expected changes: update `docs/concepts/page_theme.md`, the theme guide's page themes section, and `docs/reference/agent_api.md` beside the `event:` line; final diff review.
- Verification approach: link check on changed docs; smoke tests and changed tests; the Contract's browser check.
- Estimate details: driver: three docs; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/page_theme.md`, `docs/runbooks/theme_development_guide.md`, `docs/reference/agent_api.md` (updated).

## Study checkpoint

2026-11-07T15:16:39Z, `railspike-effort` / `v3/theme_directive`, Step 3 handoff. Step 2 explicitly approved, including `theme: none`. Scope v2 unchanged. Starting point `feat/engagify_theme` at `9276410b`. Reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total **0.75** reaffirmed; stage estimates 0.15, 0.07, 0.15, 0.10, 0.12 and 0.06 are stage work, not a sum or remaining effort. No implementation, measured labor or accepted outcome.

## Enhancement Requests

Carried from Step 2: `theme: none` approved with Step 2. No new requests.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
