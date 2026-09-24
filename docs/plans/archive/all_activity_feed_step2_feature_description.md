# All Activity Feed

## Problem

Users need confidence during demos and daily use that successful frontend actions were durably written to the content repository. All Activity should show the corresponding repository-backed event immediately after the action, including actions currently separated into bootstrap, identity, approval, visible-content, and other categories.

## User stories

- As a user, I want each successful frontend action to appear in All Activity immediately so that I can verify it was recorded.
- As a demo operator, I want bootstrap, identity, approval, and configuration actions visible in one chronological feed so that the system’s behavior is easy to demonstrate.
- As an auditor, I want each activity item tied to its canonical repository evidence so that I can distinguish durable records from transient UI state.
- As a maintainer, I want a rebuilt read model to reproduce the same activity history so that the feed remains trustworthy after recovery or deployment.

## Core requirements

- All Activity includes every successful frontend write that creates or changes canonical repository data, regardless of whether the resulting content is publicly visible.
- Each included action has a chronological activity entry with an understandable type, description, timestamp, and link to the affected content or repository evidence.
- The entry is available immediately after the write completes, including for incremental writes; it must not require a manual rebuild.
- Full read-model rebuilds reproduce the same entries from canonical repository data, with stable classification and ordering.
- Category views remain useful subsets of All Activity, and RSS/backup previews do not silently contradict the feed’s action coverage.

## Shared component inventory

- `templates/pages/activity.php` and `Application::renderActivity()`: extend the canonical activity page and existing item renderer; no parallel feed component.
- `Application::renderActivityRss()`: extend the existing RSS projection so its items follow the same activity contract.
- `Application::fetchBackupSnapshot()` and the backup page: reuse the activity projection for its preview, with an explicit decision about whether the preview remains content-only.
- Tools activity link/navigation: retain the existing route and category navigation, updating labels or descriptions if needed.
- No existing write API exposes a separate activity payload; write services and APIs should continue using the canonical activity projection rather than introducing a second feed contract.

## Simple user flow

1. The user performs an action through the frontend.
2. The action succeeds and the canonical repository record is committed.
3. The read model records the corresponding activity event.
4. The user opens or refreshes All Activity and sees the new event with its time, type, and repository-backed link.
5. A later full rebuild preserves the event and its meaning.

## Success criteria

- For every supported frontend write action, an end-to-end test confirms the action’s event appears in All Activity immediately after success.
- The feed includes bootstrap, identity, approval, visible-content, labels/reactions, configuration, and every other supported write family represented in the repository.
- The same scenarios produce equivalent All Activity results after a full read-model rebuild.
- No supported successful frontend action is absent from All Activity because of an internal/hidden classification.
- Existing category views, RSS output, source links, and backup behavior either remain consistent or have explicitly documented scope.
