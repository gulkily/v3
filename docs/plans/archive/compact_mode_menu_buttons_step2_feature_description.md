# Compact Mode Cleanup Step 2 Feature Description

## Problem

Compact mode still presents unnecessary surrounding space and vertical side rails around the board controls, thread composer, and thread cards. These remnants make the compact view feel less like one continuous, space-efficient list.

## User stories

- As a board user, I want compact-mode controls to use only the space they need so that more threads remain visible.
- As a board user, I want the compact composer to align flush with the thread list so that it reads as part of the same board surface.
- As a board user, I want compact thread cards to avoid unnecessary left and right rails so that the list does not show stray vertical lines between cards.
- As a user of comfortable mode, I want the existing layout preserved so that compact-mode cleanup does not change my preferred view.

## Core requirements

- Compact-mode controls retain their existing button appearance while losing unnecessary outer card chrome and spacing.
- The compact “Start a thread” field aligns flush with the surrounding thread-list cards without changing its fields or actions.
- Compact thread cards retain horizontal row separators while removing the unwanted left and right edge rails.
- All changes remain scoped to compact mode and the applicable board thread-list surface.
- Comfortable mode, other pages, and existing density-toggle behavior remain unchanged.

## Shared component inventory

- **Board controls container:** existing board navigation and density controls; extend its compact presentation rather than creating a second control component.
- **Compact thread composer:** existing shared thread-composition surface on the board; adjust its surrounding layout only, preserving the canonical compose behavior and fields.
- **Thread-list cards:** existing board/tag thread-card rendering; reuse the current cards and constrain the visual adjustment to the compact thread-list context.
- **Density preference and toggle:** existing compact/comfortable preference mechanism; no new preference, route, API, or persistence surface is needed.

## Simple user flow

1. The user opens a board or tag thread list with compact mode enabled.
2. The user sees the controls, composer, and cards aligned with minimal surrounding space.
3. The user scans the list without vertical rails continuing through the gaps between cards.
4. The user switches to comfortable mode and sees the existing comfortable layout unchanged.

## Success criteria

- Compact-mode controls, composer, and thread cards have no unintended outer spacing visible in the target board layout.
- The compact composer is flush with adjacent list surfaces while its controls remain visually unchanged.
- No left/right border rails appear in the compact thread-list gaps; intended top/bottom separators remain visible.
- Comfortable mode and non-target pages render without visual or behavioral changes.
- Existing focused and full test suites pass, and a manual screenshot review confirms the target spacing and borders.
