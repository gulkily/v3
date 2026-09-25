# Localhost Codex Task Handoff Step 2 Feature Description

## Problem

Approved users reading the local site need a safe way to turn a forum request into a Codex-ready development handoff. The workflow must draft the handoff, assess implementation confidence, show it for one more explicit approval, and record the handoff lifecycle in activity.

## User Stories

- As an approved local user, I want to convert a request into a user story so that development work starts from a clear user-centered goal.
- As an approved local user, I want the system to draft FDP Step 1 for the request so that competing implementation approaches are assessed before planning.
- As an approved local user, I want to see confidence in Codex's ability to implement the task correctly so that I can decide whether to approve the handoff.
- As an approved local user, I want to approve or reject the prepared Codex handoff in the UI so that local code work never starts from an accidental click.
- As an auditor, I want Codex handoff requests, approvals, rejections, and outcomes visible in activity so that local development automation remains accountable.

## Core Requirements

- Show the Codex handoff action only for approved users on a detected localhost/local-development request.
- Prepare a reviewable handoff draft that includes a user story, FDP Step 1 solution assessment, and implementation-confidence review before any Codex execution.
- Require one explicit UI approval after the handoff draft is shown; rejection or dismissal must not start local Codex work.
- Keep the handoff lifecycle durable enough to survive refreshes and expose requested, draft-ready, approved, rejected, running, failed, and completed states.
- Add activity entries for meaningful lifecycle transitions without exposing private local execution details to users who should not see them.

## Shared Component Inventory

- Post card action area: extend as the primary place to start a handoff from a specific request; keep separate from agent-reply controls so forum replies and code automation are distinct.
- Thread root card action area: extend in parallel with post cards so root-post requests can be handed off without special navigation.
- Existing agent-reply request button: reuse interaction lessons and status feedback patterns, but do not make it the canonical Codex handoff control.
- Existing approved-viewer checks: reuse as the authorization baseline for visibility and API access.
- Existing localhost/local repository runtime behavior: reuse as the environmental boundary for showing and accepting local handoff actions.
- Existing activity page and activity views: extend as the audit surface for Codex handoff lifecycle events.
- Existing FDP planning documents: reuse the Step 1 structure for the generated handoff draft; later FDP steps remain gated by normal approval.
- New Codex handoff preview/approval surface: needed because no existing UI presents a development handoff draft with approve/reject controls.

## Simple User Flow

1. An approved user opens a localhost thread or post page.
2. Eligible human-authored posts show a Codex handoff action distinct from the request-agent-response action.
3. The user starts the handoff for one post.
4. The site prepares a draft containing a user story, FDP Step 1 solution assessment, and implementation-confidence review.
5. The UI presents the draft with approve and reject controls.
6. If rejected, the handoff is recorded as rejected and no Codex work starts.
7. If approved, the handoff is recorded as approved and becomes eligible for local Codex execution.
8. Activity shows the handoff lifecycle and links back to the originating post/request when possible.

## Success Criteria

- Unapproved users and non-localhost visitors cannot see or use Codex handoff controls.
- Approved localhost users can create a handoff draft from an eligible post without starting Codex execution.
- The handoff draft visibly includes the user story, FDP Step 1 assessment, and confidence review before approval.
- Codex execution cannot start unless the prepared draft receives explicit UI approval.
- Refreshing the page preserves and displays the current handoff state.
- Activity includes audit entries for handoff request, draft readiness, approval or rejection, and final outcome.
