# Markdown syntax

The site has one small Markdown renderer of its own. It formats the documentation library and, when a post is marked Markdown, post bodies. This page lists what it understands. Anything else stays as the text you typed.

## Posts: Markdown or plain text

In the composer, the **Markdown** box in the ··· menu decides. It starts unticked, and ticks itself when your draft looks like Markdown (a heading, list, quote, code, table, link or emphasis); once you tick or untick it yourself, it stays as you set it. Through the API, posts are Markdown unless you send `body_format=plain`. Posts written before this option existed are plain text. Plain-text posts show exactly what you typed, with line breaks kept.

You can change your mind later: a post's ··· menu (beside Retract) offers **Show as Markdown** or **Show as plain text** to its author, and to operators for any post, including posts written before this option existed. The text itself never changes.

The signed record keeps your original text either way. Only the page display changes, and an operator can switch Markdown display off for the whole site.

In both formats, hashtags such as `#meta` and `#needs-review` link to their tag pages. Tags use lowercase letters, numbers and single internal hyphens. Code examples, existing links and URL fragments stay intact.

## What renders

| You type | You get |
| --- | --- |
| `# Heading` (up to `######`) | A heading. In posts, headings sit one level inside the post. |
| A blank line | A new paragraph |
| A single line break | A line break (posts); a space (docs) |
| `**bold**`, `*italic*` | **bold**, *italic* |
| `` `code` `` | Inline code |
| Three backticks on their own line, before and after | A code block, shown exactly as typed |
| `- item`, `* item`, `+ item` | A bulleted list |
| `1. item` or `1) item` | A numbered list; the numbers you type are kept |
| `- [ ] task`, `- [x] done` | A checkbox (display only) |
| `> quote` | A quoted line |
| `---` on its own line | A horizontal rule |
| `[label](https://example.com)` | A link |
| A pipe table with a `---` separator row | A table |

In posts, a bare `https://` address becomes a link too, and asterisks need text right next to them to count as emphasis, so `2 * 3 * 4` stays as typed.

## What stays as typed

- Raw HTML: it is shown as text, never run.
- Images: `![alt](url)` shows the alt text. Post images use the image attachment instead.
- Underscore emphasis (`_x_`), strikethrough, reference-style links, footnotes and math.
- In posts, links that are not `http://` or `https://` (for example `javascript:`, relative paths or `/docs/...`).
- In docs only, HTML comments are hidden; in posts they stay visible.

Lists nested deeper than eight levels, tabs in lists and other ambiguous shapes fall back to showing their source rather than guessing. The [renderer runbook](../runbooks/markdown_renderer.md) has the full rules for documentation.
