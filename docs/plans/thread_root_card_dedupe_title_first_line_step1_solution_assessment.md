# Thread Root Card: Omit Duplicate Title Line — Step 1 Solution Assessment

## Problem Statement

When a thread's title matches the first line of its root post body, the thread page shows that line twice — once as the `<h1>` and again as the first line of the body.

## Option A: Compare and strip inline in `thread_root_card.php`

Split the root post's body on its first line break, trim it, and compare it to the title; if they match, render the body starting from the second line instead.

Pros:

- Fully localized to the one template that pairs a title with a body (`post.php`/`post_card.php` show replies with no title, so there's exactly one call site today).
- No new shared surface area to review or maintain.

Cons:

- If a second view later needs the same de-duplication (e.g. an RSS/API export of the thread), the comparison logic would need to be duplicated rather than reused.

## Option B: New shared `TemplateRenderer` helper

Add a closure alongside the existing `$br`/`$e`/`$author` helpers (e.g. `$bodyWithoutDuplicateTitle($body, $title)`) that any template can call.

Pros:

- Matches the existing pattern of centralizing presentation-transform logic (author links, timestamps) in `TemplateRenderer`.
- Ready to reuse immediately if a second call site appears.

Cons:

- Adds a new closure to `renderFile()` for a need that currently has exactly one call site — more surface area than the problem currently requires.

## Recommendation

**Option A.** Only `thread_root_card.php` pairs a title with a body today, so a shared helper (Option B) would be speculative reuse. Keep the comparison local; it's a small, isolated change that's easy to lift into a shared helper later if a second view needs it, with no behavior change required at that point. Comparison should trim leading/trailing whitespace on both sides before comparing; exact (case-sensitive) match after trimming, since a title that only differs in case from the body's first line is unusual enough not to guess-normalize.

Reply **Approved Step 1** to proceed to the feature description.
