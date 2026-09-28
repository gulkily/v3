# Step 2: Feature Description — Omit Duplicate Title Line from Thread Root Card

## Problem
When a thread's title is identical to the first line of its root post body, the thread page shows that line twice: once as the `<h1>` title and again as the first line of the body.

## User Stories
- As a reader, I want a thread's title to appear once, not repeated as the first line of the body, so the content reads cleanly.
- As a poster, I don't have to think about this — if I happen to retype my title as the first line of my post, the page won't show it twice.

## Core Requirements
- In the thread page's root post (`templates/partials/thread_root_card.php`), if the root post's body's first line, trimmed of leading/trailing whitespace, exactly matches the displayed title (trimmed, case-sensitive), omit that first line from the rendered body.
- All other lines of the body render unchanged, including their own leading/trailing whitespace handling exactly as today (no other reflow/trimming behavior changes).
- If the body is empty after removing the duplicate first line (i.e. the post was only ever the title, restated), render an empty body element rather than erroring — don't fabricate placeholder text.
- If the title does not match the first line (including when the title came from the automatic body-excerpt fallback used when a thread has no subject — see below), the body renders exactly as today, with no line removed.
- Applies only to the root post on the thread page; replies (`post_card.php`) and the standalone single-post page (`post.php`) don't pair a title with a body today and are unaffected.

## Shared Component Inventory
- `templates/partials/thread_root_card.php` is the only existing template that renders a title alongside a body — confirmed in Step 1. This feature extends that template directly; no new component is created.
- Title display already goes through `ThreadTitle::displayTitle()`, which falls back to a truncated body excerpt when a thread has no subject. In that fallback case the "title" is mechanically derived from the body, so it can share a similar (but not necessarily identical) prefix with the body's first line without being a case of a poster duplicating their own title; the comparison only removes the first line when it matches the title *exactly*, so a truncated excerpt (which ends in `...`) won't spuriously match and won't cause removal.

## Simple User Flow
1. A poster creates a thread with a subject that repeats as the first line of their body (e.g. a poem with its title in both the subject field and the first line of the poem).
2. Reader opens the thread page.
3. The root post shows the title once, followed immediately by the body starting from its second line.
4. For any other thread (title differs from the body's first line), the page is unchanged.

## Success Criteria
- A thread whose subject exactly matches the trimmed first line of its body shows that text once (as the title), not twice.
- A thread whose subject differs from the first line of its body is rendered exactly as it is today, with no missing content.
- A thread with no subject (title derived from a truncated body excerpt) never has its first body line removed, since the excerpt's `...` ending prevents an exact match.
- A root post whose entire body is just the duplicated title line renders with an empty (but present) body element, no error.
