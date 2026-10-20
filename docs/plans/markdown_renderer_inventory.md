# Markdown formatting inventory

## Findings

The existing docs renderer cannot faithfully display the formatting already used in this repository. A replacement needs a general Markdown baseline plus tables, task lists, strikethrough, heading anchors and reference links. Mermaid, the single HTML logo, and the single Marp slide deck need explicit handling outside that baseline.

The inventory supports evaluating an established parser rather than growing a collection of line-by-line patches. No renderer, dependency, deployment configuration or pending Docs Discovery Step 1 was changed.

## Scope and method

- Source snapshot: tracked Markdown at `45308c3421f8e9e9df6f4068379d47924b04be4f`, using current working-tree contents. Untracked planning notes and this report are excluded.
- Scanned **1,207 files**: **1,203 under docs/** plus root README, CONTRIBUTING, AGENTS and BLESSING. The docs subtree contains 1,136 plan/archive files, 15 vendored Railspike files, and 52 other documentation files. Inventorying root instruction files does not make them eligible for publication.
- The scan skips fenced code bodies and masks same-line inline code for prose matching. Counts are documents containing syntactic matches, not semantic AST counts. Continuations, loose lists and Setext headings are candidates; examples were manually checked. “Matching lines” in the CSV can contain multiple uses.
- Largest tracked Markdown file: 45,678 bytes (`codebase_cleanup_audit_plan_v1.md`), below the existing reader’s 1 MiB limit.
- [Per-document CSV](markdown_renderer_inventory.csv) records matching-line counts, grouped by document. Zero means no match from this scan, not proof that arbitrary syntax is impossible.

## Formatting and renderer coverage

“Other docs” counts the 52 files outside plans and vendored workflow docs; root files are included only in the total column.

| Formatting | Documents | Other docs | Current renderer |
| --- | ---: | ---: | --- |
| ATX headings | 1206 | 52 | Basic headings work; no generated IDs |
| Inline code | 1073 | 48 | Basic support; backtick edge cases need compatibility checks |
| Asterisk strong emphasis | 794 | 26 | Basic support; wrapped list boundaries can break it |
| Asterisk emphasis | 78 | 6 | Basic support |
| Underscore emphasis | 14 | 0 | Unsupported |
| Bullet lists | 1184 | 41 | Single-line items only |
| Numbered lists | 363 | 28 | Single-line items; ignores source start number |
| Indented list continuation lines | 153 | 21 | Broken: continuation escapes the list |
| Nested list items | 435 | 6 | Flattened rather than nested |
| Loose-list candidates | 32 | 2 | Blank lines close the list |
| Task checkboxes | 42 | 2 | Literal checkbox syntax |
| Pipe tables | 46 | 13 | Unsupported; rendered as pipe text |
| Inline links | 637 | 26 | Basic safe links; source-relative routing needs policy |
| Links containing section fragments | 47 | 12 | Links survive but destination headings have no IDs |
| Reference-link definitions | 1 | 0 | Definitions and shortcut uses remain literal |
| Angle-bracket URL autolinks | 1 | 1 | Escaped text rather than clickable link |
| Strikethrough | 1 | 0 | Unsupported |
| Fenced code blocks | 82 | 31 | Plain code works; language metadata discarded |
| Mermaid fenced diagrams | 2 | 1 | Readable code fallback only; no diagrams |
| Blockquotes | 554 | 0 | Each line becomes a separate quote block |
| Explicit hard line breaks | 1 | 1 | Not converted to explicit line breaks |
| HTML tags outside fences | 1 | 0 | Escaped, intentionally; only observed tag is img |
| HTML comments | 2 | 1 | Escaped/rendered rather than suppressed |
| Setext-heading candidates | 2 | 1 | Unsupported; manual interpretation needed |

## Representative compatibility cases

- **Wrapped ordered lists and tables:** [complete retraction workflow](../reference/author_post_retraction.md), particularly Workflow at a glance, status, coverage, recovery and API sections. Direct probes confirm restarted numbering, separated continuation paragraphs and zero table elements.
- **Nested and loose lists:** [QDB archive import](../runbooks/qdb_archive_import.md) and [research slide outline](../research/sigtbd_fault_tolerant_governance_slides_outline.md). Preserve nesting and item-contained paragraphs.
- **Task checkboxes:** [site specification style guide](../architecture/new_site_spec_style_guide.md) and [theme-menu specification](../specs/theme_menu_representative_options_spec_v1.md). Render non-interactive checked/unchecked state rather than literal `[x]`.
- **Reference links:** [Thywill review](thywill_site_option_review.md) has **26 definitions and 58 shortcut-reference uses** such as `[T1]`; this form was manually counted after the initial scan. Some targets point outside the application checkout, so parser support alone cannot make those destinations available on the website.
- **Section links:** [root README](../../README.md) and [private messaging architecture](../architecture/private_messaging.md). Generate stable, duplicate-safe heading IDs and verify fragment compatibility, including punctuation and code in headings.
- **Underscore emphasis:** [Railspike Step 1 instructions](../railspike/docs/workflow/step1_solution_assessment.md). Preserve emphasis consistently with asterisk syntax.
- **Strikethrough:** [chouse hosting plan](chouse_club_hosting_plan_v1.md), resolved migration item.
- **URL autolink:** [phone development walkthrough](../runbooks/phone_site_development_live_walkthrough.md), live test-thread link.
- **Mermaid:** [private messaging architecture](../architecture/private_messaging.md) and [Railspike README](../railspike/README.md) contain **6 diagrams across 2 documents**. A Markdown parser alone will not draw them; retain an accessible code fallback or add a separately scoped diagram renderer.
- **HTML image:** [Railspike README](../railspike/README.md) contains one remote logo via an HTML img tag. Keep arbitrary raw HTML disabled/escaped; either convert that logo to supported image syntax or narrowly define safe image handling.
- **Marp metadata:** [research slide deck](../research/sigtbd_fault_tolerant_governance_slides_5min_marp.md) begins with YAML front matter containing presentation/CSS settings, followed by slide separators and comments. Hide metadata or provide a clearly labeled source/download view; do not apply embedded CSS as general docs HTML.

## Code and uncommon syntax

- Detected **318 fenced blocks across 82 documents**, all using backticks; no tilde or longer fences were detected. There were no deeply indented fences to account for in this corpus.
- Fence labels: `(none)` (39), `apache` (1), `bash` (103), `cron` (2), `css` (8), `html` (5), `js` (2), `json` (10), `markdown` (3), `md` (2), `mermaid` (6), `php` (33), `sh` (9), `sql` (2), `text` (93). Preserve language labels even if syntax highlighting is deferred.
- Standard Markdown image syntax, footnotes, GFM alert blocks, nested blockquotes and underscore strong emphasis had no detected uses outside fenced code. Image handling still matters because of the HTML logo.
- Dollar-sign matching produced eight candidate lines; manual review found prices and PHP variables, **no confirmed mathematical notation**. A math engine is not justified by this inventory.
- Two Setext-heading candidates need manual treatment: one underlined heading in a backlog plan, and one Marp separator that can resemble a heading underline. A parser upgrade may change ambiguous source interpretation.
- Ten HTML-comment lines occur in two documents. Preserve comments as non-visible content rather than showing markup to readers.
- Non-Markdown documentation includes Apache/environment/private-config examples and a research PDF. These need safe text/download/document handling, not additional Markdown syntax.

## Dependency and deployment constraints

- Runtime baseline is **PHP 8.1+**; setup documentation requires PDO SQLite and mbstring. The inspected local CLI is PHP 8.1.2. See [deployment](../runbooks/production_deploy.md) and [setup](../../README.md#php-setup).
- No tracked `composer.json`, `composer.lock` or `vendor/` tree exists. [autoload.php](../../autoload.php) loads only the project’s `ForumRewrite` namespace. The Node package currently declares only Playwright Core, with no Markdown parser.
- Adopting a PHP library requires a chosen dependency/autoload mechanism, pinned distribution and license review, and a release package containing dependencies. A Composer-based development/build step need not imply running Composer on the production shared host; the exact packaging choice remains open.
- Both [dynamic docs](../../src/ForumRewrite/Http/PlatformDocsController.php) and [static docs builds](../../src/ForumRewrite/Host/StaticArtifactBuilder.php) must use the same rendering contract. Static output should retain readable content without JavaScript; interactive additions need separate asset/publication checks.
- Preserve the current renderer’s escaping and unsafe-link rejection when expanding syntax. Define safe relative-document, source-code, image and fragment destinations explicitly; do not serve arbitrary repository files to make links work.

## Suggested support boundary

1. **Core compatibility:** headings, stable anchors, paragraphs and line breaks, strong/emphasis variants, inline code, fenced code with language labels, thematic breaks, multiline/nested/loose lists, blockquotes, inline/reference/autolinks, tables, task lists and strikethrough.
2. **Explicit special cases:** Mermaid fallback/optional diagram display, safe image handling, Marp/front-matter treatment, and non-Markdown asset viewing/downloads. These should not require a general raw-HTML or script execution mode.
3. **Docs UI work:** table of contents, copy buttons, breadcrumbs/back-to-results, mobile table/code behavior and search are reader-interface requirements rather than parser syntax.
4. **Selection checks:** evaluate a parser against these real examples, PHP 8.1 compatibility and packaged-dependency delivery. No particular library/version has been selected or installed.

## Verification performed

Read-only scanner across the full tracked corpus, manual review of uncommon matches, source inspection of the renderer/autoloader/deployment guides, and direct renderer probes for tables, wrapped/nested lists, checkboxes, underscore emphasis, strikethrough, reference links, autolinks, heading anchors, fence languages and HTML images. The previously inspected local retraction page corroborates the table/list/anchor defects. This is an inventory, not an implementation or library compatibility result.
