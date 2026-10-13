> **Feature plan:** [Step 1](./authentication_modes_documentation_step1_solution_assessment.md) · [Step 2](./authentication_modes_documentation_step2_feature_description.md) · [Step 3](./authentication_modes_documentation_step3_development_plan.md) · [Step 4](./authentication_modes_documentation_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Scattered authentication guidance conflates browser identity, session authentication, and membership approval. Existing access and recovery descriptions also disagree with current behavior.

## User Stories

- As a visitor, I want to understand identity choices and access recovery so that I can participate or resolve a blocked visit.
- As an operator, I want an accurate account of access settings and permissions so that I can explain my instance's behavior.
- As a maintainer, I want one evidence-backed reference so that future authentication changes do not leave contradictory guidance.

## Core Requirements

- Provide one current reference distinguishing anonymous participation, guest/named browser keys, public-key publication, signed actions, authenticated sessions, and membership approval; identity hints must not imply proof of identity.
- Include an access/state matrix for public and approved-members-only sites, with automatic guest preparation on/off, verified route exceptions, and action-specific permission limits, including invitations.
- Explain session persistence and recovery for expired sessions, absent browser keys, pending approval, and authentication/network failures; distinguish safe navigation recovery from write/API handling.
- Reconcile README access claims and session-reference status claims, including the existing navigation guard; label historical alternatives and proposals without presenting them as supported modes.
- Link material claims to current source and relevant existing tests, distinguish inspection from executed verification, and explicitly identify any unresolved behavior.

## Delivery Scope

**Work type: documentation-only.** Allowed product documentation: `docs/reference/authentication_modes.md` (new), `README.md`, `docs/architecture/public_architecture_and_trust.md`, and `docs/plans/session_reauthentication_reference.md`. Audience: visitors, operators, and maintainers. Application/runtime, test, CI, deployment, and generated-artifact changes are not permitted. This feature's planning artifacts/debug log and required plan-index/link organization follow Railspike's separate workflow allowance; other historical plans remain unchanged.

## Completion Boundary

Deliver a linked reference and reconciled entry points that let readers identify their access state, understand permitted actions, and find recovery guidance. Preserve historical context with clear labels and a link to current guidance. Handoff requires evidence/link checks and disclosure of unresolved claims; any needed runtime fix or wider documentation scope returns for approval.

## Risks

- **Permission confusion:** Could imply keys or sessions grant approval. Early source inspection confirms separate gates; mitigate with explicit state definitions and a reviewed matrix.
- **Stale recovery claims:** Could send readers down the wrong recovery path. Early inspection found a navigation guard and tests contradicting the session reference; mitigate by checking each recovery claim against current behavior.
- **Scope expansion:** A documentation audit could become an authentication redesign. Validate the four-file boundary before Step 3; record defects as follow-up findings and obtain approval for expanded work.

## Shared Component Inventory

Reuse the README, architecture page, session reference, and existing `/docs/` Markdown renderer; the architecture page provides in-app discovery without a new catalog entry. Account Key, Lobby, profiles/identity toolbar, compose/signing, invitations, authentication resume/navigation, and identity/authentication/approval APIs are evidence surfaces only. No UI/API component is added or changed.

## Simple User Flow

1. Follow the README or architecture link to the authentication reference.
2. Match site settings and identity/session/approval state to the access matrix.
3. Follow supported participation or recovery guidance for that state.

## Success Criteria

- All four scoped documents agree on access exceptions, approval, and current recovery behavior.
- Every matrix/recovery claim has supporting evidence or an explicit unresolved label; no historical proposal is described as implemented.
- Reference links resolve from repository and rendered documentation entry points; representative public, unapproved, approved, expired-session, and missing-key scenarios can be traced to an outcome.
- Verification records distinguish inspected versus executed checks; changes remain within approved documentation and workflow scope.
