> **Feature plan:** [Step 1](./profile_public_details_step1_solution_assessment.md) · [Step 2](./profile_public_details_step2_feature_description.md) · [Step 3](./profile_public_details_step3_development_plan.md) · [Step 4](./profile_public_details_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/profile_public_details; [ledger](../effort_estimation_trial.md#cycle-v3profile_public_details); standing P2 enrollment
Extended estimates: enabled
Status: Step 1 Option A approved; Step 2 prepared; implementation unapproved
Cycle estimate: 0.50

## Original Query

At some point, we lost the section with the actual public key on /profiles/ pages. Example page:
https://zenmemes.com/profiles/openpgp-e4299fae842c36f1404a693215130d67723b8cc1

Please review when this happened and also why this page is now missing the activity for the profile.

Please write Step 1 identifying our objectives.

## Understood Intent

Make each public profile useful for inspecting that particular identity and its contributions. Objectives:

- Display the actual public key on the profile in a collapsed expandable section, readable and selectable when opened, following [progressive disclosure guidance](../../reference/progressive_disclosure.md).
- Show that identity's publicly visible activity, including for unapproved profiles; do not substitute activity aggregated by username or another key sharing the name.
- Make displayed counts and activity agree, with clear empty states and usable navigation for longer histories. Define activity coverage in Step 2; authored threads/posts are the minimum proposed scope, not a new general audit feed.
- Preserve existing content visibility and approval policy, username aggregation, messaging, and Tools access-management functions. Verify the normal profile journey on phone and desktop, including the reported unapproved-profile case.

Problem: Public profiles lost direct key inspection and send readers to an approved-only username activity view that cannot represent an unapproved identity.

Evidence: `0dde33b67` (2026-10-18) moved key details to Tools and introduced the “Member activity” link; approved-only username aggregation dates to `80fd22eba` (2026-04-10). The live example reports one thread and one post but its linked username page has no approved profiles. The key remains available in Tools. No removed inline profile activity list was found; adding one is a proposed improvement, not a claimed restoration of a verified historical list. Deployment dates and the exact counted content remain unverified.

## Option A — Complete public identity profile

**Estimate: 0.50**

Show public key material in collapsed details and identity-specific contributions directly on `/profiles/`, with bounded history navigation and consistent visible-content totals. Keep activity outside technical disclosures and approval review and account management in Tools.

- Pros: Meets both objectives at the existing URL; works for approved and unapproved identities without conflating shared usernames.
- Cons: Requires careful visibility/count alignment and shared-view verification. Driver: identity-scoped history and normal serving paths; uncertainty: existing query reuse and count discrepancies.

## Option B — Profile summary with dedicated public details

**Estimate: 0.60**

Keep profiles compact and link to dedicated public key and identity-activity views, separate from Tools and username aggregation.

- Pros: Gives long histories and key inspection their own space while retaining identity scope.
- Cons: Adds navigation and extra entry/recovery paths; does not meet the direct-on-profile key objective as well. Driver: additional public views and compatibility; uncertainty: whether the extra navigation is acceptable.

## Recommendation and boundaries

Choose A as one releasable slice: open a profile, inspect its key, browse its own visible contributions, and handle empty or paginated histories. Keep copy minimal. Confirm activity coverage and count semantics in Step 2; do not expose hidden content merely because an identity is unapproved. No new dependencies, identity merging, approval-policy changes, general audit feed, or production deployment proposed. Include profile-contract documentation and study ledger upkeep. Any necessary read-model change must follow CONTRIBUTING.md.

## Study snapshots

Scope: v1/A; baseline 93c3ea8132df45fd5b334f05758a144fdb90e78e; distinct corrective follow-up to the member-directory/access split, not a replacement outcome. R1/P2; all three frozen reference hashes verified. Intake: 2026-10-27T01:21:50Z, 0.50 after prior history/source/live-page inspection; pristine pre-inspection intake missing, not reconstructed. Step 1 handoff: same scope/date, 0.50 reaffirmed. Codex single exposed rater; alternatives estimate complete cycles including investigation, planning, implementation, verification, documentation and correction, not hours or additive work. No measured effort, implementation or accepted outcome. Await Approved Step 1.

*— Codex · 2026-10-27 UTC*

## Request follow-up

Request: Document shared disclosure guidance based on existing displays, including collapsed technical details, and link it from CONTRIBUTING.md and this plan.
Disposition: User explicitly authorized this small documentation task; saved [guidance](../../reference/progressive_disclosure.md) and clarified Option A's initial collapsed state. No application implementation or Step 1 approval implied.
Verification: Documentation links and whitespace checked; existing template patterns inspected. No UI changes or runtime verification; zero dependency changes. Cycle estimate 0.50 remains unchanged; this clarification does not change the proposed profile outcome.
Guidance commit: d28ea9165, explicitly requested by the user; includes only CONTRIBUTING.md and the shared reference guide. Profile planning and study records remain uncommitted, awaiting Step 1 approval.

Approval update: User explicitly approved Step 1; Option A carried into Step 2 on 2026-10-27. Earlier awaiting-approval statements above are historical. Planning remains uncommitted; no implementation authorized.

*— Codex · 2026-10-27 UTC*
