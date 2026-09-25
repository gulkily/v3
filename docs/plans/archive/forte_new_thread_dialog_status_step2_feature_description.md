# Forte New Thread Dialog Status — Step 2: Feature Description

## Problem
The New Thread dialog now runs the full compose JS, but it has no status line to render into, so signing/identity progress and error feedback are silently dropped and the user gets no indication of what's happening.

## User Stories
- As a Forte user opening the New Thread dialog, I want to see a status line so I know the dialog is ready to use.
- As a Forte user submitting a thread, I want to see progress feedback so I know my submission is being processed.
- As a Forte user whose submission fails, I want to see an error message in the dialog so I know what went wrong and can retry.

## Core Requirements
- The dialog always shows a visible status line, not hidden-by-default.
- Before any action, the status line reads "Ready".
- The status line updates through the existing identity/signing progress and error messages during submission.
- Styling matches Forte's Windows-chrome dialog look.
- The reply panel's existing (hidden-by-default) status behavior is unchanged.

## Shared Component Inventory
- `[data-role="compose-identity-status"]` + `setStatus()` (in `browser_signing.js`) is the existing canonical status-rendering mechanism, already used by the reply panel and every other compose form.
- This feature reuses that mechanism as-is. It only adds the missing status-line element to the dialog's markup, with a default visible/"Ready" state instead of the reply panel's hidden-by-default pattern. No new JS status API is introduced.

## Simple User Flow
1. User opens the New Thread dialog.
2. Status line reads "Ready".
3. User fills in subject/body and submits (click or Ctrl+Enter).
4. Status line updates to reflect in-progress state (e.g., "Preparing signed thread...").
5. On success, the dialog navigates to the new thread in Forte.
6. On failure, the status line shows the error and stays visible so the user can retry.

## Success Criteria
- Status line is visible at all times, including immediately on open (default "Ready").
- Existing `setStatus()` progress/error messages become visible in the dialog instead of being silently dropped.
- No regression to the reply panel's existing status behavior.
- Manually verified: opening the dialog shows "Ready"; submitting shows progress text; a forced failure shows an error message in the dialog.
