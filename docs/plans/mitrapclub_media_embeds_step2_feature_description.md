> **Feature plan:** [Step 1](./mitrapclub_media_embeds_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_step4_implementation_summary.md)

## Problem

`mitrapclub` threads can only ever contain plain escaped text, so a YouTube or Instagram link posted by a member renders as a bare URL instead of a recognizable preview.

## User Stories

- As a `mitrapclub` member, I want a YouTube or Instagram link I post to show as a recognizable card so that other members can tell at a glance what kind of content it is before clicking.
- As a `mitrapclub` member, I want an unrecognized or unreachable link to still render safely as a plain link so that posting never breaks or looks broken.
- As a `zenmemes`/`chouse`/`qdb` operator, I want this feature off by default on my site so that my posts keep rendering exactly as they do today unless I choose to turn it on.

## Core Requirements

- Detect YouTube and Instagram URLs in post body text (root post and replies) via a server-side pattern match only — no third-party embed HTML is ever rendered or stored.
- Render a matched URL as a small, locally-styled card (provider icon/label + the URL) in place of the bare link, following the same "detected pattern → trusted server-rendered block" shape as the existing event block.
- The URL shown on the card has known tracking parameters stripped (e.g. YouTube's `si=`, Instagram's `igshid=`/`igsh=`, generic `utm_*`/`fbclid`) via a fixed per-provider allowlist of params to remove; parameters that affect content (e.g. YouTube's `t=`/`list=`) are left untouched. This is display-only — the stored post body is never rewritten.
- An unrecognized provider, or a URL that isn't a valid http(s) link, falls back to today's plain-link/plain-text rendering — never a broken page or leaked markup.
- The feature is gated by a new site-mutable feature flag, defaulted off on every site, including `mitrapclub`; an operator enables it manually per site (e.g. on the production `mitrapclub` instance) — enabling it is not part of this feature's own release steps.
- Any thumbnail/title enrichment attempted beyond the bare card is strictly best-effort, asynchronous, and cached — it must never block or fail post creation or page rendering.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** A `mitrapclub` member posts or replies to a thread whose body contains a YouTube or Instagram URL.
- **End-to-end outcome:** That post renders, on both the board listing and the thread page, with a locally-rendered media card instead of a bare URL.
- **Needed recovery:** A URL from an unsupported provider, or one that's unreachable, still renders — at minimum as today's plain link — never a blank body or raw HTML.
- **Release condition:** Flag ships defaulted off on every site, including `mitrapclub`; turning it on for any site is a manual operator action taken after release, not a step in this feature's rollout.

## Risks

- **Pattern match is too loose or too tight**, missing real links or false-positiving on unrelated text. Earliest validation: unit tests against known YouTube/Instagram URL shapes plus known non-matches, before Step 3 work starts. Mitigation: match only well-known, documented YouTube/Instagram URL shapes.
- **Thumbnail/title enrichment adds render-path latency or hangs** if it performs a synchronous outbound fetch. Earliest validation: Step 3 must decide whether enrichment ships in this slice at all or is deferred. Mitigation: no synchronous fetch in the render path; any fetch is async/cached/best-effort with a timeout, and the bare card renders instantly without it.
- **Missing the feature-flag gate changes rendering on other sites as a side effect.** Earliest validation: confirm flag defaults to false via existing feature-flag test patterns before Step 3. Mitigation: wire card rendering behind the same `siteMutable` flag mechanism already used for `UNICODE_AUTHORED_TEXT`/`THREAD_DENSITY_TOGGLE_ENABLED`, defaulted false.
- **Tracking-param stripping removes a parameter that actually affects content** (e.g. a timestamp or playlist reference), silently changing what the link points to. Earliest validation: unit test table covering both known-tracking and known-content params per provider, before Step 3 work starts. Mitigation: strip only a fixed, explicit per-provider allowlist of known-tracking param names; everything else passes through untouched.

## Shared Component Inventory

- **Body rendering:** every post/reply body (root thread card, thread card, individual post card, quoted post card, paned board/thread views, compose-reply preview) already routes through the single `$br()` closure defined once in `TemplateRenderer::renderFile()` and consumed by `templates/partials/thread_root_card.php`, `thread_card.php`, `post_card.php`, `quote_card.php`, `paned_thread_reply_tree.php`, `paned_board_content_article.php`, and `templates/pages/compose_reply.php`. This feature extends that single shared rendering path rather than forking a new one.
- **Detected-pattern block precedent:** `templates/partials/event_block.php` already renders a small, trusted, server-controlled block derived from thread data, included from `thread_root_card.php` and `thread_card.php` — the same shape as the proposed media card. Reuse this pattern rather than inventing a new one.
- **Feature gating:** `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php` already provides a `siteMutable` flag mechanism (e.g. `UNICODE_AUTHORED_TEXT`, `THREAD_DENSITY_TOGGLE_ENABLED`). This feature adds a new flag here rather than inventing a separate site-check.
- No existing URL-detection, oEmbed, or card-rendering utility exists anywhere in the codebase — this is new code, but it extends the three surfaces above rather than standing apart from them.

## Simple User Flow

1. Member composes or replies to a thread; the body includes a YouTube or Instagram URL.
2. Post saves exactly as today — plain text, no new validation blocking submission.
3. On render, the shared body-rendering path recognizes the URL pattern and emits a small provider card in place of the bare link.
4. Other members viewing the board listing or the thread page see the card.
5. If the flag is off (any non-`mitrapclub` site, or `mitrapclub` before enabling), the same post still renders as a plain link exactly as today.

## Success Criteria

- Once an operator enables the flag on `mitrapclub`, a YouTube or Instagram URL posted there renders as a recognizable card, not a bare URL, on both the board listing and the thread page, with known tracking parameters stripped from the displayed URL.
- A non-matching or unreachable URL, and all existing posts, keep rendering exactly as before — zero visual regression.
- Every site, including `mitrapclub`, renders unchanged while the flag stays off (its default state after release).
- No raw third-party HTML/markup is ever present in a rendered page.
