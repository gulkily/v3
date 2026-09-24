# All Activity Feed

## Problem statement

Users need immediate confidence that every frontend action they take has been durably represented in the content repository and is visible in All Activity, including bootstrap, approval, identity, visible-content, configuration, and other write actions.

## Option A: Broaden the existing typed activity projection

- Represent every successful frontend write as an activity event backed by its canonical repository record or records.
- Include bootstrap, approval, identity, visible content, labels, reactions, configuration changes, and other write actions in the existing activity model.
- Define explicit classifications for state-only files (for example, public keys or current instance metadata), which may be represented by the action that created or changed them rather than shown as standalone events.
- Pros:
  - Preserves one feed, one ordering model, existing filters, RSS, and source links.
  - Supports full rebuild and incremental updates consistently.
  - Makes visibility and display semantics explicit instead of exposing raw files.
- Cons:
  - Requires auditing every canonical record family and its lifecycle.
  - May require new activity labels and rendering rules for non-post records.

## Option B: Build All Activity directly from every repository file

- Treat every file under the content repository as a feed item, using file metadata and parsed headers where available.
- Pros:
  - Broadest interpretation of “everything stored.”
  - Automatically discovers newly added record families.
- Cons:
  - Mixes state, keys, metadata, signatures, and events without reliable user-facing semantics.
  - Risks exposing sensitive or non-activity artifacts.
  - Makes ordering, authorship, visibility, incremental refresh, and RSS behavior ambiguous.

## Option C: Add only the currently missing bootstrap and approval rows

- Change All Activity to include existing hidden identity-related rows while leaving the activity model otherwise unchanged.
- Pros:
  - Smallest change and fastest delivery.
  - Addresses the immediate visible discrepancy.
- Cons:
  - Does not satisfy “anything else stored in the content repo.”
  - Leaves future record families dependent on one-off additions.
  - Keeps the boundary between activity and repository state undefined.

## Recommendation

Choose Option A. The key requirement is action coverage and read-after-write visibility: after a successful frontend write, All Activity should show the corresponding event immediately, and a full rebuild should reproduce it from repository data. This retains the existing read-model architecture while allowing deliberate treatment of state-only files. Step 2 should define the complete frontend-action inventory, the repository evidence for each action, visibility/timing guarantees, and whether state-only artifacts appear directly or through state-changing events.
