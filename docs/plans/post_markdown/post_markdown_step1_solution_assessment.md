> **Feature plan:** [Step 1](./post_markdown_step1_solution_assessment.md) · [Step 2](./post_markdown_step2_feature_description.md) · [Step 3](./post_markdown_step3_development_plan.md) · [Step 4](./post_markdown_step4_implementation_summary.md)

Debug mode: enabled (`.railspike_debug_on` present at the project root; new workflow)
Effort study: `railspike-effort` / `v3/post_markdown` ([ledger](../effort_estimation_trial.md#cycle-v3post_markdown)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

## Original Query

(Screenshot of the Nov 2 changelog thread.) This is how it is currently presented, a wall of text. Let us improve on this.

Direction chosen in the same session, 2026-11-03: "Markdown in post bodies" (render a safe subset with the in-house renderer; raw records stay readable; Railspike plan first), together with rewriting that post. Follow-up: "we have a basic markdown renderer that we already use for docs".

## Revised direction (2026-11-03)

"I do want it to be optional, maybe if not opt-in then opt-out. I'm sometimes annoyed with unwanted markdown rendering and want to avoid that annoyance on this site."

## Understood Intent

Long posts should be able to read as structured text (paragraphs, headings, bold lead-ins, lists, clickable links, code) using the existing docs Markdown renderer, but an author must be able to keep a post as plain text so `*`, `#` or `1.` in it is never reinterpreted. Signed records stay plain text that reads fine unrendered.

## Problem

Post bodies are escaped and rendered with `<br>` only, so URLs are not clickable and long posts have no visible structure; rendering Markdown everywhere would mangle text written without it in mind.

## Concepts touched

- [Post presentation](../../concepts/post_presentation.md): changed in all options (how a full post body is rendered).
- Post body formatting (proposed, all options): which syntax a post supports, who chooses it, and where it is rendered versus shown as plain text.
- [Shared composer](../../concepts/shared_composer.md): changed in A (format checkbox and syntax link).

## Option A: Per-post format, Markdown by default for new posts

**Estimate: 0.85**

New post records carry a format header (`markdown` or `plain`). The composer has a "Plain text" checkbox, unchecked by default, and the API takes an optional format field defaulting to Markdown, documented in llms.txt. Records without the header, including every existing post, stay plain, so nothing already posted changes. Rendering uses a post mode of `PublicMarkdownRenderer`: http(s) links only with `rel="nofollow ugc"`, bare URLs linked, docs-only features off, media embeds and images unchanged. Previews and quotes stay plain text with markup removed. A short `docs/reference/markdown_syntax.md` documents the syntax for both posts and docs, linked from the composer. A feature flag turns rendering off site-wide.

- Pros: the author decides per post; old posts are untouched; agents and people get structure without asking for it.
- Cons: record spec, signing, API and composer all change; an author who forgets the checkbox can still be surprised.
- Driver: format field across the composer, API, record spec and read model, plus the renderer post mode. Uncertainty: medium.

## Option B: Render every post, with a reader opt-out

**Estimate: 0.70**

Render all posts, old and new, as Markdown. A reader preference (like the theme setting) switches the whole site back to plain text, and each post gets a "Show source" link.

- Pros: no record or API change; every existing post gains links and paragraphs.
- Cons: the author cannot protect a post, so other readers still see it mangled; old posts can change look overnight.
- Driver: renderer post mode, preference cookie, source view. Uncertainty: medium, mainly how the existing corpus renders.

## Option C: Typographic polish, no syntax

**Estimate: 0.30**

Keep plain text everywhere but render blank-line paragraphs as `<p>`, link bare URLs and set a comfortable line length.

- Pros: nothing can be misread as Markdown.
- Cons: no headings, bold, lists or code, so long posts still read as walls of paragraphs.
- Driver: renderer tweak and CSS. Uncertainty: low.

## Recommendation

Option A. It puts the choice with the author and leaves old posts alone, which is what avoids the annoyance. Whether the default is Markdown (opt-out) or plain (opt-in) is one setting, so it can be flipped later without rework; opt-out is recommended so agents' changelogs get structure by default. Releasable slice: entry is the ordinary composer or API; outcome is a formatted or plain post as chosen; recovery is posting with "Plain text" checked, or the site-wide flag. C's link and paragraph handling could also apply to plain posts later as a separate cycle.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Rewrite the Nov 2 changelog post so it is scannable | Separate: content change, not code | Requested by maintainer | Done: new thread posted, old one retracted |
| Make Markdown optional (opt-in or opt-out) | Changed scope within Step 1: options revised | Requested by maintainer | Reflected in revised options; A recommended |
| Syntax reference doc (renderer runbook is hard to find) | In-scope refinement of A: needed for the composer link | Proposed, not yet approved | Included in Option A |

## Effort study

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-11-03T16:50Z | 0.60 | v1 / R1 / P2, baseline `main` `fd4d0e0227c18ed840c166e2ce4948233d148769` | Claude (claude-opus-5-5), single exposed rater; read `PublicMarkdownRenderer`, `MediaEmbedRenderer` and the body call sites before rating. No pristine pre-inspection intake. | Post mode in a shared renderer, about ten call sites, embed interplay, corpus check and flag |
| Step 1 handoff | 2026-11-03T16:50Z | 0.60 (Option A v1) | v1 / R1 / P2 | Same | Recorded together with intake; no change |
| Step 1 revision | 2026-11-03T16:55Z | 0.85 (Option A v2) | v2 / R1 / P2, same baseline | Same | Scope change: maintainer wants rendering optional per post; adds a record format field, composer checkbox, API field and syntax reference |

Original option estimates (v1: A 0.60, B 0.85, C 0.30) are retained above in history; v2 options are A 0.85, B 0.70, C 0.30. Alternatives are not summed. All three frozen R1 hashes verified 2026-11-03. No implementation, measured labor or accepted outcome; planning remains uncommitted.
