# Offline Outbox Step 2 Feature Description

## Problem

Offline votes, replies, and new threads currently cannot be safely retained or
observed, leaving people unable to distinguish local intent from server-accepted
activity.

## User stories

- As a participant, I want one Outbox for my pending offline actions so that I can see and control all work that has not reached the server.
- As a participant, I want to queue a supported vote, reply, or new thread offline so that temporary disconnection does not discard my intent.
- As a participant, I want clear draft, queued, accepted, rejected, and conflict states so that I never mistake local work for published content.

## Core requirements

- Provide a single observable Outbox for supported vote, reply, and new-thread intents, reached from Tools, with safe summaries, timestamps, counts, and state-appropriate controls.
- Preserve local compose work across restart; keep every pending item outside the public offline-reader cache and distinct from published content.
- Require explicit online submission for replies and new threads; never silently replay composed content after reconnection.
- Support stable retries, cancellation, export/discard, and clear explanations for unavailable targets, authorization changes, and server outcomes.
- Exclude flags, tagging, deletion, moderation, attachments, account actions, invitations, and privileged actions from this MVP.

## Shared component inventory

- **Tools navigation:** add Outbox as the MVP entry point under Tools, keeping the main navigation unchanged; show pending-work visibility within the destination rather than a global navigation badge.
- **Offline reader and navigation:** make the Tools → Outbox destination directly reachable offline without expanding the public snapshot boundary.
- **Reaction controls:** reuse the existing vote/reaction intent and server outcome semantics for the first queueable action.
- **Reply and thread compose:** reuse the established prepare, signing, and finalization journey when a person explicitly sends queued content online.
- **Browser identity and storage:** keep pending work in a dedicated local lifecycle, separate from the public service-worker cache and private-key handling.

## User flow

1. Offline, a participant creates a supported vote, reply, or new-thread draft and queues it.
2. The participant opens Tools → Outbox to see every item and its truthful local state.
3. When online, the participant explicitly sends or retries an item and sees its accepted, rejected, or needs-attention result.
4. The participant can recover by editing, cancelling, exporting, or discarding local work.

## Success criteria

- One Outbox lists all MVP action types and their pending/outcome states.
- Offline votes, replies, and new-thread drafts survive the documented local lifecycle without appearing published before acceptance.
- Explicit online send/retry is idempotent and leaves failures recoverable and observable.
