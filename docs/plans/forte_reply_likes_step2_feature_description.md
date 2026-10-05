# Forte Reply Likes — Step 2: Feature Description

## Problem

Forte renders Flag for every comment and nested reply but not Like, unlike the classic post interface. Readers need to endorse an individual reply without applying a Like to its parent thread.

## User Stories

- As a Forte reader, I want to Like a comment or nested reply so that I can endorse that specific contribution.
- As a returning Forte reader, I want replies I already Liked to show their applied state so that I do not submit a duplicate reaction.

## Core Requirements

- Every rendered Forte reply, at every nesting depth, shows Like beside Flag.
- A reply Like is a post-level reaction and has the same identity, pending, success, failure, and duplicate-prevention behavior as an existing post Like.
- A persisted reply Like renders as Liked, disabled, and pressed on a later Forte visit.
- The existing root-thread Like remains a distinct thread-level reaction; root Flag and all reply Flag behavior remain unchanged.
- Anonymous and unprepared-identity readers retain the established on-demand identity-preparation flow before a reaction is written.

## Shared Component Inventory

- **Forte reply tree:** extend the canonical Forte reply action row; no new reply UI surface.
- **Classic post/reply action row:** retain as the established post-level Like/Flag behavior reference; do not reuse its markup because Forte has its own paned presentation.
- **Post reaction interaction and identity flow:** reuse unchanged for reply Like requests, feedback, and applied state.
- **Post-reaction API and persisted reaction records:** reuse unchanged for reply Likes.
- **Viewer post-reaction lookup:** extend Forte's existing use to include Like state for all rendered replies; no new state store.

## Simple User Flow

1. A reader opens a Forte thread and locates a comment or nested reply.
2. The reader selects Like; if needed, the established identity flow completes first.
3. Forte confirms the reply as Liked in place.
4. On a later visit, the reply remains visibly Liked and unavailable for a duplicate reaction.

## Success Criteria

- Every visible Forte reply has both Like and Flag controls.
- Liking a reply produces the same durable post-level outcome as a classic reply Like.
- A reloaded Forte page shows a prior reply Like as Liked, disabled, and `aria-pressed="true"`.
- Root-thread reactions, reply Flag actions, and the existing identity setup flow continue to work.

Reply **Approved Step 2** to proceed to the development plan.
