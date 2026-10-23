> **Feature plan:** [Step 1](./railspike_workflow_reading_step1_solution_assessment.md) · [Step 2](./railspike_workflow_reading_step2_feature_description.md) · [Step 3](./railspike_workflow_reading_step3_development_plan.md) · [Step 4](./railspike_workflow_reading_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/railspike_workflow_reading`, standing [P2 enrollment](./effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2); split follow-up to `v3/railspike_workflow_visibility`.

## Original Query

Let's choose option A and save another Step 1 document for what remains of option C.

## Understood Intent

Save the remaining proposal for coherent, private, server-rendered workflow discovery and reading. Removal of the detached Activity block belongs to the [separate selected Option A cycle](./railspike_workflow_visibility/railspike_workflow_visibility_step1_solution_assessment.md). This document preserves follow-up work for later review; it does not start implementation or block removal.

## Problem Statement

Operators lack a coherent cross-thread workflow index and depend on JavaScript to read workflow status, questions, assessments and history in existing thread surfaces.

## Option A: Make original threads the readable workflow surface

Render authorized workflow information in its original thread and standalone post, retaining ordinary forum navigation for discovery.

- **Pros:** Preserves discussion context; fixes script-dependent reading with fewer destinations.
- **Cons:** Operators must locate each thread themselves; no consolidated workflow discovery.

## Option B: Add a private workflow index and consistent readable details

Provide an authorized, server-rendered workflow list linking to readable workflow information in the original thread or a detail view, with consistent presentation across affected surfaces.

- **Pros:** Restores cross-thread discovery and reading without scripts; gives workflow state an understandable home.
- **Cons:** Requires navigation, private-session recovery and consistent access rules across index, detail and threads.

## Option C: Add only a private index

Provide server-rendered workflow summaries linking to the existing JavaScript-based thread controls.

- **Pros:** Smaller discovery improvement; retains the existing interaction model.
- **Cons:** Reading still fails without scripts after following a link; incomplete solution to the reported concern.

## Recommendation and Vertical-Slice Viability

Recommend **Option B** as the remaining original Option C: native discovery → readable status, questions, assessment and history → original discussion, with consistent authorized reading in affected thread/post surfaces. Keep signed actions and optional refresh as JavaScript enhancements; script-free signing and later Railspike stages are outside this proposal.

Preserve private authorization and no-store behavior; do not put workflow data in public HTML, RSS, shared caches or static/offline artifacts. Provide usable session/access and unavailable-workflow recovery. Verify authorized, anonymous and revoked access, scripts on/off, empty/failed states, phone/desktop and any new navigation icons in Word97, Chicago and Midnight. Record UI cleanup/verification in the existing audit checklist. Validate authentication feasibility before committing to a single release; propose a split if necessary. This cycle neither reintroduces the Activity block nor builds a general Activity event feed.

## Request Disposition

**Request:** preserve the remainder of original Option C / **Disposition:** separate follow-up assessment / **Approval:** document creation authorized; option, Step 1 advancement and implementation unapproved / **Outcome:** saved for later review, independently of the removal cycle.

## Effort Snapshots

Intake and Step 1 handoff, 2026-10-23T05:30:45Z: scope **v1**, baseline `cd2f84087a6584d2b1cb3ac8ebaa2395fa897ec6`; R1/P2; Codex, single exposed rater. Prior incident investigation, original workflow plans and parent assessment were already read; no independent or pristine intake claim. Total covers planning, private discovery/reading and recovery, implementation, verification and documentation. Projection **unavailable**: exact frozen R1 report/diff absent and current feedback hash differs. Parent v1/v2 retained; this split is not an independent accepted outcome. No measured effort or implementation claimed.

Saved for later review; await **Approved Step 1** explicitly identifying this follow-up before Step 2. The user explicitly authorized committing and pushing these planning documents before the usual planning-commit gate; this does not approve Step 1 or implementation.
