> **Feature plan:** [Step 1](./youtube_url_coverage_step1_solution_assessment.md) · [Step 2](./youtube_url_coverage_step2_feature_description.md) · [Step 3](./youtube_url_coverage_step3_development_plan.md) · [Step 4](./youtube_url_coverage_step4_implementation_summary.md)

Debug mode: disabled

Status: Superseded as the active proposal by [top five media embeds Step 1](top_five_media_embeds/top_five_media_embeds_step1_solution_assessment.md). Retained for context; its broader YouTube URL suggestions are not automatically included in the new scope.

## Original Query

Please choose the next logical thing and write Step 1

## Understood Intent

- Choose the next bounded improvement from the [media embed checklist](media_embed_support_checklist.md). Recommended next: recognize additional YouTube video URL formats through the existing card, player, and fetched-title experience.
- The [current detector](../../src/ForumRewrite/View/MediaEmbedDetector.php) recognizes watch links and `youtu.be` links only. The existing player already renders a detected video by its identifier, making URL coverage a smaller next slice than adding another provider.
- Existing live-provider and visual checks remain open in the checklist; this assessment does not mark them complete. New work follows [Railspike](../railspike/RAILSPIKE.md).

## Problem Statement

A reader gets different embed behavior for links to the same YouTube video because only two URL formats are recognized.

## Solution Options

**Option A: Extend video URL recognition within the existing YouTube experience.** Add explicitly supported Shorts, live, embed, and mobile watch URL forms, treating each as a link to an individual video.

- Pros: directly closes known coverage gaps; reuses cards, click-to-load playback, and title fetching; introduces no new provider dependency.
- Cons: title fetching must work with the newly accepted forms; Shorts retain the current player layout; timestamp and playlist behavior remains unchanged.

**Option B: Complete a broader YouTube sharing experience.** Add those URL forms together with start-time preservation, playlist playback, and format-specific presentation.

- Pros: addresses more of the YouTube checklist in one cycle.
- Cons: combines URL recognition, playback semantics, and layout decisions; needs substantially more scope and verification.

**Option C: Add SoundCloud first.** Start the highest-priority new-provider candidate with a separately scoped track-sharing experience.

- Pros: expands coverage into music sharing and follows the existing provider backlog's priorities.
- Cons: leaves YouTube inconsistencies in place; requires provider-interface research and new rendering and fallback decisions.

## Recommendation

**Option A.** Support YouTube Shorts, live, and embed paths plus mobile watch links as individual-video links. Use the existing feature flags, player behavior, and fetched-title flow, including a compatible title-fetch target for each accepted form. Keep accepted hosts and paths explicit; reject lookalike hosts and malformed identifiers.

Defer playlist-only links, start-time preservation, redirect resolution, Shorts-specific layout, and new providers to separate slices. Stored post text stays intact; no new schema or provider framework is needed.

**Vertical-slice viability:** A member posts a supported video URL in a thread or reply; readers see the existing card and, when enabled, can expand the player. An untitled thread containing only that URL can receive a fetched title. Metadata failure must leave the post and player usable; unavailable videos must not break the surrounding page. Verify the new forms through shared post views, with regression checks for existing YouTube links, Instagram, and flags disabled.

Status: Step 1 drafted for review; Steps 2–4 have not started.
