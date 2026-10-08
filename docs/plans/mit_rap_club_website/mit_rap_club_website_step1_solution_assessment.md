> **Feature plan:** [Step 1](./mit_rap_club_website_step1_solution_assessment.md) · [Step 2](./mit_rap_club_website_step2_feature_description.md) · [Step 3](./mit_rap_club_website_step3_development_plan.md) · [Step 4](./mit_rap_club_website_step4_implementation_summary.md)

## Original Query

I would like to create a website for the MIT Rap Club. Please use these as guidance:

https://cypherposium.com/

https://www.youtube.com/@MITRAPCLUB

https://www.instagram.com/mitrapclub/

https://lit.mit.edu/event/63499/

https://www.facebook.com/photo/?fbid=1560725412089676&set=a.290414092454154

Please write Step 1 of @docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md once you've done some research.

## Understood Intent

- Build a public presence for the MIT Rap Club that gives the club a central home online, pulling together what today lives only on YouTube, Instagram, Facebook, and MIT event listings.
- `cypherposium.com` is design/tone inspiration (minimalist, grassroots cypher aesthetic); the other links are content sources: channel/profile presence, a past event (Lupe Fiasco's "Rap Theory & Practice," tied to the CMS/W-affiliated "Code Cypher" program), and event photo documentation.
- Research findings: the club runs recurring cyphers/salons and hackathon-style events ("Code Cypher"), has an active Instagram (942 followers, Fall 2026 event already teased) and a YouTube channel, and has hosted high-profile guests (Lupe Fiasco/Wasalu Jaco). This is a content-light, low-traffic club community — not a heavy application.
- **Direction update:** rather than a standalone static site, build MIT Rap Club as a new branded site instance of this repo's existing multi-site forum app — a new `SiteProfileRegistry` entry (site_id) plus a new design/theme, the same pattern already used for `chouse` and `qdb`. Per `docs/plans/chouse_club_hosting_plan_v1.md`, each site is a separately deployed instance (own repo root/database/static-artifact root via `FORUM_SITE_ID`/`FORUM_REPOSITORY_ROOT`/`FORUM_DATABASE_PATH`) sharing one application codebase — not a separate project.

## Problem Statement

MIT Rap Club has no online home; the app already supports spinning up new branded communities (`chouse`, `qdb`), but has no site_id, theme, or content tailored to a rap-cypher club yet.

## Solution Options

- **Option A: New site_id on the existing `forum` experience (chouse's pattern).** Add a `mitrapclub` profile with a new theme + about/editorial content + composer prompt, reusing the generic thread/reply board as-is — no new experience code.
  - Pros: zero new experience/routing code; fastest; proven pattern (`chouse` is exactly this); discussion, event announcements, and media links all work immediately as ordinary threads/replies.
  - Cons: no structured "Events" list or card layout — an upcoming-event view would just be a pinned or tagged thread, not a dedicated model.
- **Option B: New custom experience (qdb's pattern), e.g. an "events" board.** Build a dedicated experience (own board/card/compose shape, like `QdbExperience`) so events render as structured entries (date, location, link) instead of generic threads.
  - Pros: tailored presentation for what a club cares most about — "what's next"; room for event-specific fields later.
  - Cons: materially more code (new experience class, routes, policy) than Option A; speculative before knowing whether the club needs more than posts.
- **Option C: Start on Option A, defer Option B.** Ship the `forum`-experience site_id + theme now; model events as tagged/pinned threads; revisit a dedicated events experience only if that limitation is actually felt.
  - Pros: matches "similar to chouse" literally, lowest cost to a live vertical slice, keeps the door open to Option B without committing to it speculatively.
  - Cons: "Events" is a filtered thread list at first, not a bespoke page — a minor, temporary limitation.

## Recommendation

**Option C.** Stand up `mitrapclub` as a new `SiteProfileRegistry` entry on the existing `forum` experience — new theme, about/editorial content, and composer prompt — exactly mirroring how `chouse` was added. Treat upcoming events as tagged/pinned threads for now. Only build a qdb-style dedicated events experience (Option B) later if that turns out not to be enough.

**Vertical-slice viability:** Yes. Entry is a visitor hitting the new site_id's deployment; the outcome is a live, branded board (home/about reflecting the club, a working composer, threads for discussion/events/media links, out-links to the existing YouTube/Instagram/Facebook accounts); recovery is the same as any other site instance (falls back to `zenmemes` if `FORUM_SITE_ID` is unset/unknown, per `SiteProfileRegistry::active()`).

Waiting for "Approved Step 1" before drafting Step 2.
