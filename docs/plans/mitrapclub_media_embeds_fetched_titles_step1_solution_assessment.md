> **Feature plan:** [Step 1](./mitrapclub_media_embeds_fetched_titles_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_fetched_titles_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_fetched_titles_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_fetched_titles_step4_implementation_summary.md)

## Original Query

I think I found one issue. If the YouTube link is in the title, the embed is not shown. Let's think through how to best address this (don't code yet, just talk).

## Understood Intent

- This is **Cycle 7**, a bug found while trying out Cycle 6 (inline media player) on top of Cycle 5 (link-preview card). Discussion started from three narrow render-time patches (special-case the title/body duplicate-content suppression in `thread_card.php`/`thread_root_card.php`), but the user wasn't satisfied with any of them and asked to think bigger instead.
- The direction that emerged, and that this Step 1 formalizes: when a thread has no subject and its body is a bare recognized media URL, fetch the linked content's real title (YouTube already has a plain, keyless oEmbed endpoint returning `title`; Cycle 6's Instagram page-scraper already returns one too) and store it as the thread's own `subject` — reusing the field and the existing `ThreadTitle::displayTitle()` logic exactly as-is (`if ($subject !== '') return $subject;`). Once a real subject exists, the title can never again be byte-identical to the body, so the dedup collision that caused the original bug becomes structurally impossible — not patched around, just no longer able to occur. No changes to `thread_card.php`/`thread_root_card.php`'s existing dedup logic are needed at all.
- Before any fetch completes (or if one never succeeds), the no-subject fallback for a body that is *only* a bare recognized media URL becomes the literal string `"Untitled"` instead of echoing the URL — closing the cold-start race window completely, since `"Untitled"` can never collide with body text either. This fallback is scoped narrowly: an ordinary no-subject text post keeps today's excerpt-based title exactly as it is; only the specific bare-URL-with-no-subject case changes.
- The user separately mentioned wanting a future feature letting a person manually retitle a thread, and asked whether this could be made congruent with that rather than a one-off. It can: the piece this feature needs — a way to set a thread's `subject` after creation, which doesn't exist today (subjects are currently write-once, at creation) — is the same underlying capability a retitle feature would need. This Step 1's remaining open question is how general to build that one piece.
- Checked before writing this: YouTube's oEmbed endpoint's continued keyless availability (like Instagram's oEmbed before Cycle 6) could not be confirmed against live traffic from this environment (no outbound network access here) — flagged the same way Instagram's page-scraper was, as something to verify for real once deployed somewhere with connectivity, not a reason to hold up planning.

## Problem Statement

A thread whose only content is a bare recognized media URL and no subject should show the linked content's real title and still render its embed, rather than either showing the raw link as a fake "title" or silently losing the embed to unrelated dedup logic — and the cleanest way to guarantee that is to give such threads a real, fetched title instead of trying to detect and patch the collision at render time.

## Solution Options

- **Option A: Build a general-purpose "update thread subject" write path.** A proper write operation — canonical record update plus read-model projection — for changing a thread's subject after creation, used by this feature's background fetch now and directly reusable, without rework, by a future human-facing retitle feature.
  - Pros: directly the foundation the retitle idea would need later; one coherent mechanism rather than two different ones solving similar problems; avoids building something narrow now that gets thrown away when retitling is actually scoped.
  - Cons: inherently a bigger piece of work now, since a truly general write path raises questions this feature doesn't actually need answered yet (who's authorized to retitle, how a change is audited/shown to users, whether `subject` is covered by any existing record-signing/hashing assumption) — real design questions a future retitle Step 1 should own, not ones to guess at here.
- **Option B: Build the narrowest possible write capability — system-only, one-time, bare-link case only.** A background process may set a thread's subject exactly once, only when it is currently empty and the body is a bare recognized media URL. No general "edit subject" capability or API beyond that.
  - Pros: smallest, fastest, exactly scoped to what this feature needs; defers every "general write path" design question to whenever retitling is actually scoped, when its real requirements are known instead of guessed at now.
  - Cons: a future retitle feature most likely can't reuse this as-is (human-initiated retitling has different concerns — authorization, changing an *existing* subject, visible audit trail) and would probably need its own write path anyway, making this a weaker fit for the congruence the user was hoping for.

## Recommendation

**Option A, deliberately scoped down.** Build the write path generally enough to be reused — an append-only subject-change record plus a read-model projection step, not a single-purpose hack wedged into the background-fetch code — but without speculatively adding retitling-specific concerns (authorization beyond "the system itself may call this," a user-visible audit trail, etc.) that a future retitle Step 1 should design properly when it's actually in scope, not guess at now. This keeps faith with the congruence the user is after without over-building against requirements nobody has written down yet.

**Vertical-slice viability:** Yes. Entry is a `mitrapclub` member posting a thread with no subject whose entire body is a single recognized YouTube/Instagram URL, with both media-embeds flags enabled; outcome is that thread's title reading `"Untitled"` until the background fetch succeeds, then updating to the linked content's real title on a later view, with the embed rendering correctly in the body throughout, start to finish; recovery is graceful (a fetch that never succeeds leaves the title at `"Untitled"` indefinitely — never broken, never a collision); no regression to ordinary no-subject text threads, which keep today's excerpt-based title exactly as-is, or to any thread that already has a human-provided subject.

Waiting for "Approved Step 1" before drafting Step 2.
