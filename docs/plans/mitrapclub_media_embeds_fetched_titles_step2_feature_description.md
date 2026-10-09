> **Feature plan:** [Step 1](./mitrapclub_media_embeds_fetched_titles_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_fetched_titles_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_fetched_titles_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_fetched_titles_step4_implementation_summary.md)

## Problem

When a `mitrapclub` thread has no subject and its body is a bare recognized YouTube/Instagram URL, it should show the linked content's real title and still render its embed in the body — today it instead shows the raw URL as a fake title and silently loses the embed to unrelated title/body duplicate-content suppression.

## User Stories

- As a `mitrapclub` member, when I post a thread with no subject that's just a link, I want the thread to end up titled with the linked content's real name, so the board and thread page read like a real post, not a bare URL.
- As a `mitrapclub` member, I want the embed in my post's body to always render, even when I didn't type a subject, so I don't need filler text to work around a rendering quirk.
- As a `mitrapclub` member, I want a thread without a fetched title yet to show something sensible ("Untitled") rather than the raw link, so it never looks broken while a background fetch is in flight or fails.
- As a site operator, I want this kept behind the existing media-embeds flag, so sites that haven't opted in see no change at all.

## Core Requirements

- A thread with no subject whose body is a bare recognized media URL displays `"Untitled"` as its title until a background fetch succeeds — never the raw URL text. Ordinary no-subject text threads keep today's excerpt-based title unchanged.
- A new, append-only canonical record type (mirroring the existing `ThreadLabelRecord` pattern) lets the system set a thread's subject after creation; applied in the read model the same way thread labels already are, writing into the existing `threads.subject` column — no schema change.
- Once a background fetch of the linked content's title succeeds (reusing/extending Cycle 6's Instagram page-scraper, plus a new YouTube oEmbed fetcher, both backed by the already-generic `MediaEmbedPreviewCacheStore`), the system write path sets the thread's subject to that title — but only when the subject is still empty, so a human-provided subject (now, or from a future retitle feature) is never overwritten.
- The fetch-and-write trigger follows the same client-beacon pattern Cycle 6 already established — never a synchronous fetch, never blocking a page render.
- Gated behind the existing `FORUM_MEDIA_EMBEDS_ENABLED` flag alone, not the inline-player flag — this is a thread-title concern, not specific to whether the embed plays inline, so it applies wherever Cycle 5's card already does.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** A `mitrapclub` member posts a thread with no subject whose body is a single recognized YouTube or Instagram URL, with the media-embeds flag enabled.
- **End-to-end outcome:** The thread shows `"Untitled"` immediately, then updates to the linked content's real title once a background fetch succeeds, visible on a later view; the embed renders in the body throughout, from the very first view.
- **Needed recovery:** A fetch that never succeeds leaves the title at `"Untitled"` indefinitely — never broken, never reverting to showing the raw link as a title.
- **Release condition:** Same posture as Cycles 5/6 — the mechanism ships, but enabling the flag anywhere it isn't already on stays a manual operator action outside this feature's rollout.

## Risks

- **New write path** — the first time a canonical record changes something about an already-published thread after creation. Earliest validation: Step 3 defines the record format and confirms it mirrors `ThreadLabelRecord`'s already-proven shape exactly. Mitigation: model it directly on that precedent rather than inventing new conventions; the system write only ever fires when `subject` is currently empty, so it can't clobber existing content.
- **YouTube oEmbed's continued keyless availability is unverified against live traffic** — same caveat as Instagram's page-scraper in Cycle 6. Earliest validation: a real check once this is deployed somewhere with outbound network access. Mitigation: identical graceful-degradation posture as every other fetch in this feature — any failure just leaves `"Untitled"`, never a broken page.
- **Scope boundary with the future retitle feature** — building this write path too narrowly forces rework later; too broadly guesses at retitling's real requirements now. Earliest validation: Step 3's Completion Contract states plainly what this write path does and does not include. Mitigation: per Step 1's recommendation, build the mechanism generally (a reusable shape) without speculative extras — authorization UI, a visible audit trail — that a future retitle Step 1 should own.

## Shared Component Inventory

- **Record precedent:** `ForumRewrite\Canonical\ThreadLabelRecordParser`/`ThreadLabelRecord` is the direct model for the new subject-change record — the same append-only shape (`Record-ID`, `Created-At`, `Thread-ID`, `Operation`, optional `Author-Identity-ID`, `Reason`), extended rather than forked.
- **Read-model projection:** `IncrementalReadModelUpdater`'s existing thread-label loading/application, which already writes into the `threads` table's existing `subject` column — reused as the pattern, not a new column or table.
- **Provider fetch/cache:** `MediaEmbedPreviewCacheStore` (Cycle 6) is already generic by `(provider, embedId)` — reused as-is for a new `youtube` provider row. `InstagramPagePreviewFetcher` is the direct model for a new YouTube oEmbed fetcher.
- **Beacon/trigger:** `MediaEmbedPreviewController`'s warm-cache endpoint (Cycle 6) is the model for triggering this fetch — extended to accept `youtube`, or a parallel endpoint following the identical shape.
- **Title generation:** `ThreadTitle::displayTitle()` gains the narrow "Untitled" shape-check for a bare recognized media URL; `MediaEmbedDetector::classify()` (already public, Cycle 6) is reused for that check rather than new detection logic.

## Simple User Flow

1. Member posts a thread with no subject; the body is a single recognized YouTube or Instagram URL; the flag is enabled.
2. The thread renders immediately: title `"Untitled"`, body embed rendering normally (card or inline player, per the inline-player flag).
3. A viewer's page load triggers the same kind of background beacon Cycle 6 already uses; the system fetches the linked content's title and, finding the subject still empty, writes a new subject-change record.
4. The read model picks that up; the next view of the thread (board or detail page) shows the real title instead of `"Untitled"` — the embed keeps rendering in the body exactly as before.
5. With the flag off, or for any thread that already has a subject, nothing about this feature applies — behavior is unchanged.

## Success Criteria

- A bare-link, no-subject thread never shows the raw URL as its title — only `"Untitled"` or, once fetched, the real title.
- The embed renders in the body from the very first view, every time — the original bug cannot recur, by construction.
- A human-provided subject, now or from a future retitle feature, is never overwritten by this mechanism.
- With the flag off, every behavior described here is absent — zero change from today.
