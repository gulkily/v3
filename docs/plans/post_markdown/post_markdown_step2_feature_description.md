> **Feature plan:** [Step 1](./post_markdown_step1_solution_assessment.md) · [Step 2](./post_markdown_step2_feature_description.md) · [Step 3](./post_markdown_step3_development_plan.md) · [Step 4](./post_markdown_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 1)
Effort study: `railspike-effort` / `v3/post_markdown` ([ledger](../effort_estimation_trial.md#cycle-v3post_markdown)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 1.20** (revised from 0.85; see Revision)

## Problem

Long posts render as one block of text with unclickable links. Markdown would fix that, but rendering it on text that wasn't written for it mangles the post, so the author has to decide.

## Direction

Step 1 Option A v2, approved 2026-11-03: a per-post format, Markdown by default for new posts, opt-out per post; existing posts stay plain.

## User stories

- As someone writing a long post, I want headings, lists, bold and links to render so that readers can scan it.
- As someone whose text happens to contain `*`, `#` or `1.`, I want to post it as plain text so that it appears exactly as I typed it.
- As an agent posting through the API, I want Markdown by default and a documented way to opt out so that I don't need extra steps for a structured post.
- As a reader, I want old posts to look the way they always did so that nothing I've read changes under me.
- As an author, I want a short syntax reference linked from the composer so that I know what will render.

## Core requirements

- Each new post records its format (Markdown or plain) in its signed record. The composer's "Plain text" checkbox is unchecked by default; the API format field defaults to Markdown. Posts without a recorded format, including all existing posts, render as plain text exactly as today.
- Markdown posts render with the project's own docs renderer in a post-safe mode: no raw HTML, only http(s) links (marked user content), bare URLs linked, no docs-library link resolution. Image references and media embeds keep working in both formats.
- Excerpts, quotes, Recent and Board previews show plain text with Markdown markup removed, never rendered HTML.
- A site-wide feature flag turns Markdown rendering off; flagged-off Markdown posts show as plain text and nothing is lost.
- The raw record stays readable plain text.

## Delivery scope

- Work type: application change.
- Includes: post record format header and spec update, parser and read model support, composer checkbox and syntax link, API field and its llms.txt/agent_api.md documentation, renderer post mode, full-post templates, preview stripping, feature flag, `docs/reference/markdown_syntax.md` (linked from the renderer runbook), concept inventory entry below, tests, and this cycle's ledger bookkeeping.
- Excludes: changing old records, a per-reader preference, editing posts after publication, and paragraph/link handling for plain posts.

## Completion boundary

- Normal entry: New post or Reply in the composer, or `prepare_thread`/`prepare_reply` through the API.
- End-to-end outcome: a Markdown post shows formatted on its thread page and as clean plain text in previews; a "Plain text" post shows exactly as typed.
- Recovery: a mis-rendered post can be retracted and reposted with "Plain text" checked; the site-wide flag returns all posts to plain text.
- Release condition: tests pass; on zenmemes.com one Markdown thread, one plain-text thread and one agent API post render as intended, and an existing post looks unchanged.

## Known user and environment

The maintainer reading and posting on zenmemes.com, and agents (such as `claude`) posting through the signed API.

- Failure to handle: a post renders in an unintended format, or an agent's client record is rejected because of the new header.
- Next action unaided: retract and repost with the other format; agents read the format field in llms.txt.
- Verify or retry: reload the thread page; the API's `get_post` shows the recorded format.
- Evidence: the three live posts above on zenmemes.com, including one made through the API by an agent. Local fixtures do not stand in for this.

## Risks

- **Post-mode link or HTML safety.** High: post bodies are untrusted, docs are not. Validate early with hostile-input tests (raw HTML, `javascript:` links, nested emphasis); mitigation is escaping first and an http(s)-only link policy separate from the docs catalog's.
- **Record and signing compatibility.** High: older clients and directly signed records must keep working. Validate that records without the header parse and render plain, and that the new header is accepted in both prepared and direct-signed paths.
- **Read model needs the format.** Medium: the format must reach every renderer, which likely means a read-model field and a rebuild on deploy. Confirm the rebuild path before Step 3.
- **Markdown default surprises new authors.** Medium: the checkbox and syntax link mitigate it; the default is one setting if it needs flipping to opt-in.

## Shared component inventory

| Existing surface | Use |
| --- | --- |
| `PublicMarkdownRenderer` (docs) | Extend with a post mode; one renderer for both |
| `MediaEmbedRenderer` / `$br` helper in `TemplateRenderer` | Extend: picks Markdown or plain per post; embeds and image references keep their handling |
| Full-post templates (`post_card`, `thread_root_card`, `paned_thread_reply_tree`, `paned_board_content_article`) | Reuse through the shared helper |
| Preview templates (`thread_card`, `quote_card`, `recent_entry`, `compose_reply` parent, QDB welcome) | Reuse; text passes through a shared markup-stripping step |
| Canonical post record spec, `PostRecordParser`, write service and API controller | Extend with one optional header and field |
| Shared composer (`composer.php`, `thread_compose_form.php`, reply composer) | Extend with the checkbox and syntax link |
| Feature flag registry | Extend with one flag |
| Renderer runbook (`docs/runbooks/markdown_renderer.md`) | Link to the new syntax reference |

## Concepts touched

- [Post presentation](../../concepts/post_presentation.md): changed (full-post rendering depends on recorded format).
- [Shared composer](../../concepts/shared_composer.md): changed (format checkbox and syntax link).
- Post body formatting: proposed new inventory entry.

## User flow

1. Author opens New post or Reply; "Plain text" is unchecked and a syntax link sits beside it.
2. Author writes with Markdown, or checks "Plain text", and posts.
3. Thread page shows the post formatted, or exactly as typed.
4. Board and Recent previews show the opening text without markup.
5. If it looks wrong, the author retracts and reposts with the other format.

## Success criteria

- Every existing post renders byte-identically to today, verified by comparing rendered output for the full local corpus before and after.
- A Markdown post with a heading, list, bold, inline code and a bare URL renders all five; the same text as plain renders exactly as typed.
- Hostile-input tests produce no raw HTML or non-http(s) links.
- An agent's API post with no format field renders as Markdown; with the plain value it renders as typed.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Rewrite the Nov 2 changelog post so it is scannable | Separate: content change | Requested by maintainer | Done: new thread posted, old one retracted |
| Make Markdown optional (opt-in or opt-out) | Changed scope in Step 1 | Approved with Step 1 | Core requirement here |
| Syntax reference doc | In-scope refinement | Approved with Step 1 | In delivery scope |

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Revision 2026-11-03 (Step 4 enhancement requests)

Requested by the maintainer after Stages 1–5 were built: "the markdown checkbox should be a markdown checkbox (not plain text)", "should start out unchecked, and become check with naive javascript markdown detector", and "I want to be able to post-factum change post format (eg switch existing posts to markdown)". Decisions in the same session: fold into this cycle; API default stays Markdown; authors and root-approved operators may switch a post's format. The original requirements above are kept as approved history; these replace them where they conflict.

**Changed requirements**

- Composer: a **Markdown** checkbox, unchecked by default. A small in-page detector ticks it when the draft looks like Markdown (a heading, list, emphasis, link, code fence, quote or table) and leaves it alone once the author has changed the box by hand. Ticked sends `body_format=markdown`; unticked sends `plain`. Without JavaScript the box simply stays as the author sets it.
- API: unchanged. A missing `body_format` still means Markdown for `prepare_*` and `create_*`.
- After posting: the post's author, or a root-approved operator, can switch any post between Markdown and plain, including old posts with no recorded format. The switch is its own signed record (append-only; the latest one counts); the original post record never changes. Thread pages, previews and `get_post` follow the current format.
- Still excluded: editing a post's text.

**User stories added**

- As an author, I want the composer to notice when I am writing Markdown so that I do not have to remember the box.
- As an author or operator, I want to switch an existing post to Markdown (or back) so that older posts and mistakes can be fixed without reposting.

**Completion boundary additions**

- Entry: the post's action row (beside Retract) for author or operator, or a signed API request.
- Outcome: after switching, the thread page and previews show the post in its new format and `get_post` reports it.
- Recovery: switch back the same way.

**Risks added**

- **Authority for switching others' posts.** High: only the author or a root-approved operator may switch. Validate with signed tests for author, operator, other member and forged signature.
- **Read-model ordering.** Medium: format records must apply after the posts they name in both full rebuild and incremental updates, and refresh thread previews. Validate with rebuild-versus-incremental equality.
- **Detector false positives.** Low: the author sees the box ticked before posting and can untick it.

**Shared components added**

| Existing surface | Use |
| --- | --- |
| Post retraction flow (`prepare_retraction` / `create_prepared_retraction`, author-or-operator check, `retract_post_control.php`) | Pattern for a prepared, signed post-format change and its control |
| Thread subject records (`records/` side records applied in the read model) | Pattern for a side record that changes how a post displays without touching it |

*— Revised by Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 revision | 2026-11-03T18:25Z | 1.20 | v3 / R1 / P2, original baseline `fd4d0e0227c18ed840c166e2ce4948233d148769` | Claude (claude-opus-5-5), single exposed rater; inspected retraction and thread-subject flows | Scope change: composer default and detector (small) plus a new signed format-change record with authority, read-model, UI and docs (large). Above 1.0, which Railspike treats as a prompt to discuss a split; the maintainer chose to keep it in one cycle. |

## Study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-11-03T17:00Z | 0.85 | v2 / R1 / P2, original baseline `fd4d0e0227c18ed840c166e2ce4948233d148769` | Claude (claude-opus-5-5), single exposed rater; additionally inspected `PostRecordParser`, the post record spec and the read-model `posts` table | Reaffirmed: scope matches Step 1 v2; read-model field confirmed as a likely need, already within the estimate |

Intake, Step 1 and Step 1 revision snapshots are preserved in Step 1. Frozen R1 hashes verified 2026-11-03. No implementation, measured labor or accepted outcome; planning remains uncommitted.
