> **Feature plan:** [Step 2](./qdb_regular_thread_presentation_step2_feature_description.md) · [Step 3](./qdb_regular_thread_presentation_step3_development_plan.md) · [Step 4](./qdb_regular_thread_presentation_step4_implementation_summary.md)

## Problem

QDB renders every root post as a quote based on the site profile, even when the root has a regular thread ID. A regular thread's stored subject is therefore hidden, and it receives quote-specific controls.

## User Stories

- As a QDB contributor, I want a regular thread's title to appear on its direct page so that readers can understand the discussion.
- As a QDB reader, I want quote controls to appear only on numbered QDB quotes so that regular threads are not misrepresented as quotes.
- As a QDB quote reader, I want existing quote cards and numeric permalinks to remain unchanged.

## Core Requirements

- Render a QDB root as a quote only when it has a valid QDB quote ID.
- Render a QDB root without a quote ID as a regular thread, including its established title and body presentation.
- Restrict QDB quote score and vote/flag controls to actual QDB quote roots.
- Preserve existing quote rendering, numeric permalink behavior, and quote-only collection filtering.
- Do not alter canonical records, ID allocation, database schema, or list eligibility.

## Delivery Scope

- Work type: application change — QDB root-card classification, regression coverage, static-detail rendering verification, and implementation summary.
- Out of scope: quote/thread authoring behavior, record migration, board-list redesign, generic reaction redesign, and changes to existing IDs.

## Completion Boundary

- Normal entry: a reader opens a direct permalink for a QDB regular thread or numbered quote.
- End-to-end outcome: the regular thread displays its title/body without quote controls; the quote retains its quote card, controls, and numeric permalink.
- Recovery: a subjectless regular thread uses the established title fallback; invalid or legacy IDs remain regular threads rather than becoming quotes.
- Release condition: direct and static-detail coverage proves both classifications and existing quote behavior; focused tests pass and the implementation summary records evidence.

## Risks

- A noncanonical ID could be classified as a quote. Impact: title/control regression for regular content. Earliest validation: regular, malformed, and numbered-ID fixtures. Mitigation: reuse the canonical quote-ID predicate.
- Quote rendering could lose its permalink or controls. Impact: broken core QDB behavior. Earliest validation: existing quote-card and numeric-permalink tests. Mitigation: preserve the existing quote branch for valid quote IDs.
- Static detail pages could diverge from dynamic pages. Impact: readers see inconsistent presentation. Earliest validation: static artifact rendering for both root types. Mitigation: keep classification in the shared root-card template.

## Shared Component Inventory

- `thread_root_card.php`: extend the canonical direct-root renderer to classify quote versus regular roots; do not fork its markup.
- `QdbQuoteNumbers`: reuse its existing canonical ID recognition for quote classification.
- `ThreadAndPostPageController` and `ThreadTitle`: reuse unchanged; they already supply the stored subject or fallback title.
- `qdb_quote_actions.php`: reuse unchanged, but render it only from the valid quote branch.
- `StaticArtifactBuilder`: reuse unchanged because it renders the same thread-detail template.

## Simple User Flow

1. A contributor creates a regular QDB thread with a subject.
2. A reader opens its direct permalink and sees the title and body with no quote controls.
3. A reader opens a numbered QDB quote and sees the existing quote card, controls, and numeric link.
4. Static detail pages present the same distinction.

## Success Criteria

- A QDB regular-thread detail page renders its stored subject in a heading and omits quote-card controls.
- A numbered QDB quote detail page retains its quote card, score/actions, and numeric permalink.
- Malformed or legacy non-quote IDs use regular-thread presentation.
- Dynamic and static detail output agree for both root types; existing quote-list filtering remains unchanged.
