> **Feature plan:** [Step 1](./profile_public_details_step1_solution_assessment.md) · [Step 2](./profile_public_details_step2_feature_description.md) · [Step 3](./profile_public_details_step3_development_plan.md) · [Step 4](./profile_public_details_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/profile_public_details; [ledger](../effort_estimation_trial.md#cycle-v3profile_public_details); standing P2 enrollment
Extended estimates: enabled
Status: Steps 1–2 approved; Step 3 prepared; implementation unapproved

**Cycle estimate: 0.50**

## Problem and user stories

Standard public profiles omit key material and send readers to approved-only username activity, which cannot represent an unapproved identity or distinguish identities sharing a username.

- As a visitor, I want to inspect and copy a profile's public key without leaving its page so that I can identify that key precisely.
- As a reader, I want that identity's visible contributions and matching totals so that approval status or a shared username does not obscure who authored them.

## Core requirements

- Show the complete public key on `/profiles/<slug>` in initially collapsed, keyboard-accessible details; retain formatting, selection and a useful Copy control, with manual-copy recovery and an honest unavailable state. Follow [progressive disclosure](../../reference/progressive_disclosure.md); keep activity and primary actions outside technical disclosures.
- Provide identity-specific Threads and Posts lists for approved and unapproved profiles. Threads are authored roots; Posts include authored roots and replies. Exclude approval events, reactions and other audit events from this cycle. Resolve authorship by identity, never by matching username or bootstrap ownership alone.
- Apply existing public-content visibility rules to both lists and totals, including hidden/internal bootstrap and retracted-content exclusions. Counts represent all eligible results, not just the current page; zero visible contributions is valid even when the identity has a bootstrap record. This does not change publication or retraction policy.
- Bound each history using the [shared pagination contract](../../reference/pagination.md), stable ordering and identity-scoped positions. Preserve useful navigation for empty pages; invalid or expired positions offer First page. Reload/Back remain available after failed navigation. Missing profiles retain ordinary not-found behavior.
- Preserve username aggregation, approval policy, messaging, Tools review/history and existing profile URLs. A username link may remain supplementary but cannot substitute for this identity's history. No new dependencies or general audit feed.

## Delivery and completion boundary

Work type: Application change to standard profile reads and presentation, with focused tests, profile read-contract updates, verification records and study-ledger upkeep. Shared consumers must remain compatible; Forte expansion and a site-wide disclosure retrofit are excluded. The separately authorized shared guide is already committed in `d28ea9165`.

Flow: Open a profile through an author link or direct URL → inspect visible totals and contributions → open a contribution or navigate history → expand and copy the public key when needed. Completion requires that normal journey, empty/unavailable states and recovery through both direct application serving and supported host/static serving, with phone/desktop and affected-theme evidence. No production deployment is authorized.

Known environment: On zenmemes.com, open the reported [testing1 profile](https://zenmemes.com/profiles/openpgp-e4299fae842c36f1404a693215130d67723b8cc1) directly or through its author link. Its unapproved status must not route readers into an empty approved-user aggregate to find its own content. Readers can expand the key, follow eligible contributions, or see accurate zero totals; on navigation/copy failure they can use First page, Reload/Back or manual selection as applicable. Determine which content produced the reported 1/1 counts rather than promising those values persist. After an independently authorized deployment, capture that same URL's counts/list agreement, key and navigation on zenmemes.com; local fixtures establish local acceptance only, and production acceptance stays pending until checked there.

## Risks and shared components

- Visibility/count disagreement could leak hidden content or invent activity: validate approved/unapproved, shared-name, hidden bootstrap and retracted-content fixtures before selecting the history approach; use one eligibility definition for lists and totals.
- Unbounded histories or serving-path differences could break large profiles: validate bounded navigation and normal host/static behavior early; preserve existing freshness policy and recovery.
- Long key blocks or disclosure changes could obscure primary actions: validate real long keys, keyboard/copy failure and phone layouts early; reuse existing themed presentation and keep warnings visible.

Inventory: Reuse/extend profile identity lookup and authored-content reading, standard profile and username thread/post presentation, and shared pagination controls; new identity-scoped history on the standard profile is needed because the username view aggregates approved identities. Reuse existing key-display conventions from Account, Tools profile details, Forte profiles and bootstrap-post details; preserve their callers and private-key safeguards. Preserve Tools account/key approval-history views, Forte username/profile views and profile-summary/detail API consumers. Include post evidence/source key links in compatibility review; do not replace their navigation with raw-key disclosures. Reuse existing profile/static materialization paths rather than adding a second public profile route.

## Success criteria and checkpoint

Acceptance: Exact key text is available/copyable after expansion; approved and unapproved identities show only their own eligible content; same-name identities do not mix; totals match full eligible histories; stable multi-page navigation and empty/invalid-position recovery pass; hidden content remains excluded. Verify shared views, messaging/access links and initial/expanded/error states across affected themes on phone/desktop, recording limits separately from passed checks.

Study checkpoint: 2026-10-27T01:47:19Z; v1/A, original baseline 93c3ea8132df45fd5b334f05758a144fdb90e78e, R1/P2, Codex single exposed rater. Frozen hashes reverified; prior history/live-page and current reader/pagination exposure retained. Whole-cycle 0.50 reaffirmed with authored-content scope and existing shared components; earlier intake/handoff preserved. No measured labor, accepted outcome or implementation; ledger upkeep included. Await Approved Step 2.

*— Codex · 2026-10-27 UTC*

Approval update: User explicitly approved Step 2; Step 3 prepared on 2026-10-27. Earlier awaiting-approval checkpoint remains historical.
