# Thread List Card: Duplicate Preview Line — Step 1 Solution Assessment

## Problem Statement

On the board/tag listing pages, each thread's preview line is literally the body's first line (`strtok($body, "\n")`), so it repeats the title whenever a poster's first body line matches their subject — the same duplication just fixed on the thread page itself.

## Option A: Remove the preview entirely (original plan)

Drop `<p class="thread-card__preview">` from `thread_card.php` unconditionally, as originally proposed.

Pros:

- Simplest possible change; zero duplication, ever.
- No new comparison logic, nothing to get subtly wrong.

Cons:

- Loses genuinely useful preview text on the (likely more common) threads where the first line does *not* duplicate the title — that's a real feature regression, not just a fix.

## Option B: Dedupe in the template, mirroring the thread-root-card fix

In `thread_card.php`, compare the preview (trimmed) to the title (trimmed); render the `<p class="thread-card__preview">` line only when they differ, otherwise omit it — same technique, same file layer, as the fix just shipped for `thread_root_card.php`.

Pros:

- Consistent pattern with the fix already shipped and tested elsewhere in this session; low risk, presentation-layer only.
- Keeps the preview for the majority of threads where it adds real information; only suppresses it when it's pure duplication.

Cons:

- One more template-level comparison to maintain (small, same shape as the existing one).

## Option C: Change what "preview" means at the data layer

Change `preview()` in `ReadModelBuilder`/`IncrementalReadModelUpdater` to skip the first line when it matches the subject, falling back to the second line instead of hiding the preview.

Pros:

- Every thread gets *some* preview text, even when the first line duplicates the title.

Cons:

- Touches the read-model data layer (two call sites to keep in sync) instead of presentation only, and changes `body_preview`'s meaning for every other consumer (title-fallback excerpt logic, API/export), not just this one listing card — larger blast radius for what's a display concern on one page.
- Conflicts with the "avoid touching shared data fields when a presentation-layer fix will do" preference already applied for the thread-root-card fix.

## Recommendation

**Option B.** It's the same low-risk, presentation-only pattern already validated for `thread_root_card.php` this session, and it avoids Option A's real content loss and Option C's larger data-layer footprint. The one difference from the root-card version: there's no multi-line remainder to fall back to here (the preview *is* just the first line), so a match simply means "omit the line," not "show what's left."

Reply **Approved Step 1** to proceed to the feature description.
