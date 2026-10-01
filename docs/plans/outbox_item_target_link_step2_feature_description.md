# Outbox Item Target Link — Step 2 Feature Description

## Problem

Outbox entries describe their local action but do not link to the underlying
thread or reply until a server-accepted post outcome is available. People
cannot quickly inspect the item that a pending, failed, or legacy action targets.

## User stories

- As an Outbox user, I want a link from each item to its forum target so I can
  inspect the thread or reply before deciding what to do with my local action.

## Core requirements

- Show a target link for every Outbox item whose stored target can be resolved
  to an existing canonical forum route.
- Use the item’s original target; do not infer server acceptance from a link.
- Retain the existing accepted-item published-content links and all state,
  timestamp, queue, signing, and retry behavior.
- Make the link understandable for thread and reply targets and safe when a
  legacy item has incomplete target data.

## Shared component inventory

- `outbox.js` renders the compact item listings; extend this canonical item
  presentation rather than adding a second Outbox view.
- Stored Outbox targets identify a thread or post; reuse the existing normal
  thread and post routes.
- Accepted items already expose published-content links; retain that outcome
  presentation separately from the original-target link.

## User flow

1. Open Outbox and expand a pending, failed, or accepted item.
2. Select its target link.
3. Inspect the corresponding forum thread or reply, then return to Outbox to
   manage the local item.

## Success criteria

- Eligible thread and reply items expose a correct target link in their
  listing.
- Items with incomplete legacy target data remain readable without a broken
  link.
- Existing accepted outcome links and Outbox action controls remain unchanged.
