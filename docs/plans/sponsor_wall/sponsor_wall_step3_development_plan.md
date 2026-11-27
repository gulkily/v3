> **Feature plan:** [Step 1](./sponsor_wall_step1_solution_assessment.md) · [Step 2](./sponsor_wall_step2_feature_description.md) · [Step 3](./sponsor_wall_step3_development_plan.md) · [Step 4](./sponsor_wall_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./sponsor_wall_debug_log.md))

Effort study: `railspike-effort` / `v3/sponsor_wall`; ledger `../effort_ledger/sponsor_wall.md` (written with the planning commit). Standing P2 enrollment. Parent: the pledge epic, [pledges_checklist.md](../pledges_checklist.md).

Extended estimates: enabled

Status: Steps 1 (Option A) and 2 approved by the maintainer 2026-11-27. Step 3 approved by the maintainer 2026-11-27 ("approved step 3").

**Cycle estimate: 0.60**

# Step 3: Development Plan — Support wall

Base: `origin/main` at `a542d1e`.

## Completion Contract

- Entry: `/support/` with `FORUM_PLEDGES_ENABLED` on and a `supportThread` in the site profile, on a phone and desktop, signed in or not, with and without JavaScript.
- End-to-end outcome: the page shows the opening post as the pitch, the totals line, the wall (one block per pledge, N squares wide, badge, name and a link to the pledge; delivered filled in, open outlined, pending dashed after the counted squares, withdrawn left out, "Also:" for parts of a square, rows added past the goal), and the pledge form with 1/3/5/10 presets. Posting returns to `/support/`.
- Recovery: a failed post shows the composer's usual error with the amount kept. With no `supportThread` or the flag off, the page is not found and nothing else changes. A named thread that doesn't exist yet shows an empty wall.
- Deployment: no schema change, no dependency, no new flag. zenmemes.com needs the thread posted and its ID set in the `zenmemes` profile, which is a separate change once the thread exists.
- Release condition: Ilya pledges 3 squares from `/support/` on zenmemes.com on his iPhone SE and sees his block. That can't be done from this session, so it stays pending in Step 4.

## Findings since Step 2

- **No stale pages.** Pages are baked only when a route is listed in `StaticArtifactBuilder` (as `/about/` is). `/guestbook/` isn't listed and renders live through `sendHtml`. `/support/` works the same way, so the Step 2 risk about cache clearing goes away, and so does its share of the estimate (0.65 → 0.60).
- **Names aren't in the campaign data.** `PledgeCampaign::fromPosts()` keys pledges by post ID with state and amount, not author name. The wall reads names from the same posts the page loads. That needs no new query.

## Key Risks

- **Wide blocks on a phone.** Impact: an 11-square pledge breaks across rows. Early validation: in Stage 2, a fixture with pledges of 11 and 0.5, screenshotted at 375 pixels. Mitigation: a block wraps, and its name sits on its first square.
- **Themes.** Impact: the wall is unreadable in a dark theme. Early validation: in Stage 2, the default theme, Underground and one dark theme. Mitigation: theme variables only.

## Stage 1

**Estimate: 0.15**

- Goal: `/support/` exists and shows the pitch and totals for the named thread.
- Dependencies: none.
- Expected changes: `SiteProfileRegistry` gets a `supportThread` key (null by default) and `supportThread(): ?string`. A new `SupportPageController::page(string $threadId): ?string` loads the thread's posts and runs `PledgeCampaign::fromPosts()`. A route in `Application.php` sits beside `/guestbook/` and returns not found when the key is null or `FORUM_PLEDGES_ENABLED` is off. A new `templates/pages/support.php` titled "Support ZenMemes" holds the opening post's text, the existing totals partial and a link to the thread. The campaign thread links to `/support/` when it is the support thread.
- Verification approach: a new `SupportPageTest` modeled on `GuestbookPageTest`. It checks not found with no key and with the flag off, an empty wall for a missing thread, and a totals line that matches the thread page. Smoke set.
- Estimate details: driver: copying the guestbook's route and controller shape; uncertainty: low.
- Risks or open questions:
  - Impact: the thread page's link to `/support/` is baked, so it appears only after a rebuild. Mitigation: the same as the guestbook's "Open as a book", noted in the concept.
- Canonical components/API contracts touched: `SiteProfileRegistry` (one key, following `guestbookThread`), the guestbook route shape (reused), `PledgeCampaign::fromPosts()` (reused unchanged), `pledge_totals.php` (reused).

## Stage 2

**Estimate: 0.20**

- Goal: the wall, drawn by the Step 1 rules.
- Dependencies: Stage 1.
- Expected changes: a new `SupportWall::build(array $campaign, array $posts, int $perRow = 10): array` returns blocks in thread order (post ID, name, whole squares, delivered squares, counted or pending), the "Also:" names, the row count and the square where the goal ends. Withdrawn, unnamed and unreadable pledges are left out. A new `templates/partials/support_wall.php` draws the blocks: each claimed square gets the badge, and the name and link go on the block's first square. Pending blocks reuse the campaign rows' Pending chip. `public/assets/support_wall.css` uses theme variables only.
- Verification approach: `SupportWallTest` with fixtures for counted, part-filled, pending, withdrawn, a 0.5 remainder, a 2.5 pledge (2 squares plus "Also:") and a total past 50 (a sixth row, goal line after square 50). A render check in `SupportPageTest`. Screenshots at 375 and 1280 pixels in three themes. Smoke set.
- Estimate details: driver: the square layout and its wrapping; uncertainty: how blocks wrap in CSS grid.
- Risks or open questions:
  - Impact: a block wraps badly. Early warning: the 11-square screenshot. Mitigation: see Key Risks.
- Canonical components/API contracts touched: `PledgeCampaign` states (`COUNTED`, `WAITING`, `WITHDRAWN`; read, not changed), the Pending chip from campaign rows (reused), the badge (new: no existing mark fits a wall square).

## Stage 3

**Estimate: 0.15**

- Goal: pledge from the page, with presets, and land back on it.
- Dependencies: Stage 2.
- Expected changes: `support.php` renders the existing pledge form (`reply_form.php` with pledge placement) as the guestbook renders its reply form, with `return_to=/support/`. `resolveComposeReplyReturnTo` gets a `/support/` case beside `/guestbook/`. Preset buttons (1, 3, 5, 10) only fill the amount field. They live in `pledge_field.js`, are drawn only when JavaScript runs, and are named for squares. Without JavaScript the field is still there.
- Verification approach: a browser check `tests/browser/support_wall.mjs`. Press "3" and post; the page returns to `/support/` with a new three-square block. Then a failed post, which keeps its amount, and a post without JavaScript. `ComposeReplyReturnTest` (or the guestbook's equivalent): `/support/` is accepted only for the support thread. Smoke set.
- Estimate details: driver: the browser check; uncertainty: the composer's draft scope on a second page.
- Risks or open questions:
  - Impact: the pledge draft saved on the thread page shows up on `/support/`. Early warning: the browser check. Mitigation: both are the same thread's pledge, so it's the same draft on purpose.
- Canonical components/API contracts touched: `reply_form.php` pledge placement (reused), `pledge_field.js` (extended with presets), `resolveComposeReplyReturnTo` (extended).

## Stage 4

**Estimate: 0.10**

- Goal: checked at the reported size and documented.
- Dependencies: Stage 3.
- Expected changes: a new [support wall](../../concepts/support_wall.md) concept, and one line in the pledge campaign concept linking to it; a pledges checklist entry for this cycle and for the next one (the guest username prompt); the ledger, the debug log and the Step 4 closeout.
- Verification approach: a local replay at 375 by 667 pixels, with and without JavaScript, of the Step 2 fixture: 3, 1 and 2.5 squares, one delivered, one pending. Smoke set plus `SupportPageTest`, `SupportWallTest`, `PledgeCampaignTest` and `GuestbookPageTest`; `git diff --check`; review the whole diff. The full suite isn't run. The iPhone SE check stays pending for the maintainer.
- Estimate details: driver: the replay; uncertainty: low.
- Risks or open questions:
  - Impact: the headless pass is taken as the release check. Mitigation: the closeout lists the iPhone SE check and setting `supportThread` on zenmemes.com as pending.
- Canonical components/API contracts touched: concept inventory (one new entry).

Concepts touched: pledge campaign (one link added); support wall (new). No matching item in `docs/feedback/items/`.

## Study snapshots

- Step 3 handoff: **0.60** (Step 2: 0.65). New understanding, not changed scope: the page isn't baked, so no cache work. Stage estimates: 0.15, 0.20, 0.15, 0.10, not summed. R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-27 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*
