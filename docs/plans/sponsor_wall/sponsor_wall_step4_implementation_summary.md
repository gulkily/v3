> **Feature plan:** [Step 1](./sponsor_wall_step1_solution_assessment.md) · [Step 2](./sponsor_wall_step2_feature_description.md) · [Step 3](./sponsor_wall_step3_development_plan.md) · [Step 4](./sponsor_wall_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./sponsor_wall_debug_log.md)), inherited from Step 3.

Effort study: `railspike-effort` / `v3/sponsor_wall`; ledger `../effort_ledger/sponsor_wall.md` (written at closeout). Standing P2 enrollment.

Extended estimates: enabled

Status: in progress on `feat/sponsor-wall`. Planning commit `6bbd94c`.

**Cycle estimate: 0.60**

Step 4 entry: the Step 3 projection of 0.60 is reaffirmed, unchanged, before implementation. Claude, 2026-11-27 UTC.

# Step 4: Implementation Summary — Support wall

## Stage 1 - The page, the pitch and the totals

**Original estimate: 0.15**

**Retrospective: 0.12**

- Changes:
  - `SiteProfileRegistry`: a `supportThread` key on the zenmemes profile (null), and `supportThread()`.
  - New `SupportPageController::page()` loads the thread's posts through `fetchThreadPosts` (the thread page's loader, so it has the same approval columns) and runs `PledgeCampaign::fromPosts()`. The page is rendered live, as `/guestbook/` is.
  - `Application`: the `/support/` route sits beside `/guestbook/`. It returns not found with no `supportThread` or with `FORUM_PLEDGES_ENABLED` off. Added to the application route list.
  - New `templates/pages/support.php`: the title "Support <appName>", the opening post as the pitch with its goal line left out (`PledgeLines`, as on the thread), the existing totals partial, and "Open as a thread".
  - `TemplateRenderer`: stylesheets for `support.php` and a `siteSupportThreadId` page variable. On the support thread, `thread.php` shows "Open the wall".
  - Usage reports: the `support` path word and the "Support" name.
  - New `public/assets/support.css` (layout only so far).
- Verification:
  - New `SupportPageTest`: not found with no key and with the flag off; the pitch without the goal line; "5.5 of 50 squares pledged" on both the page and the thread; links both ways; an empty page for a named thread that doesn't exist yet. 3 of 3 pass.
  - `./v3 test --smoke SupportPageTest UsageNamesTest GuestbookPageTest`: 502 of 502 pass. The full suite was not run.
- Notes:
  - The title uses the profile's `appName` ("ZenMemes") when it has one, otherwise `displayName`.
- Estimate details: original scope as planned; uncertainty was low, and so it went. No revisions.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*
