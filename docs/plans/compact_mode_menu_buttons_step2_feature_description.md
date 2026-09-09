# Compact Mode Menu Buttons Step 2 Feature Description

## Problem

Compact thread mode reduces content spacing but leaves menu and control buttons at their comfortable-mode prominence. Users need compact mode to feel consistently denser without losing usable controls or accessible touch targets.

## User stories

- As a reader, I want navigation and menu controls to become visually denser in compact mode so that more thread content fits on screen.
- As a reader, I want compact controls to remain readable and tappable so that density does not reduce usability.
- As a maintainer, I want shared controls to follow one compact-mode rule so that future buttons do not require one-off styling.

## Core requirements

- Apply compact-mode styling only when the existing compact density state is active.
- Reduce nonessential button padding, gaps, and visual prominence while preserving labels and focus indicators.
- Preserve minimum usable touch and keyboard targets, especially on mobile layouts.
- Leave comfortable mode and unrelated page controls unchanged.
- Reuse the existing navigation and button classes; do not add a new density preference or persistence model.

## Shared component inventory

- `templates/partials/thread_density_toggle.php` — existing compact/comfortable selector; reuse its state contract and keep the selector usable in both modes.
- `templates/layout.php` and `public/assets/thread_density_toggle.js` — existing density state application through `data-thread-density`; extend styling only, without changing the JavaScript contract.
- `templates/pages/board.php`, `templates/pages/thread.php`, and shared button rows — existing navigation and action controls affected by compact mode; reuse their canonical classes.
- `public/assets/site.css` — canonical responsive/theme stylesheet; add compact-mode rules here rather than page-specific styles.

## Simple user flow

1. The reader opens a thread or board page.
2. The reader selects Compact from the existing density menu.
3. Menu and action controls adopt the compact visual density while remaining usable.
4. The reader selects Comfortable or reloads with the existing preference, restoring normal control density.

## Success criteria

- Compact mode visibly reduces targeted control spacing and padding on representative desktop and mobile views.
- Comfortable mode produces the existing control dimensions.
- Keyboard focus remains visible and controls remain usable at narrow mobile widths.
- Existing density-toggle behavior and unrelated page styles continue to pass their tests.
