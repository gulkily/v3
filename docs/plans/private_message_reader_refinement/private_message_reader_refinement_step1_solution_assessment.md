# Private Message Reader Refinement: Step 1 Solution Assessment

> **Feature plan:** [Step 1](./private_message_reader_refinement_step1_solution_assessment.md) · [Step 2](./private_message_reader_refinement_step2_feature_description.md) · [Step 3](./private_message_reader_refinement_step3_development_plan.md) · [Step 4](./private_message_reader_refinement_step4_implementation_summary.md)

## Original Query

Please make decrypt and verify an automatic action, get rid of the “decrypt and verify” button below it, delete the status below it as well, put “signature verified” message after the username in the From field, hide the “Encrypted message” module for now (we'll come back to it later). Write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md

## Problem Statement

The private-message inbox exposes manual and diagnostic reader UI where a completed automatic read and concise sender-trust signal are wanted instead.

## Option A — Automatically read each inbox message when its card loads

Use the existing decrypt-and-verify behavior automatically for eligible inbox cards, show the verified signature beside the sender in the From field, and remove or hide the specified manual, status, and encrypted-message UI.

Pros:
- Delivers the requested interaction with no extra user action.
- Reuses the established browser-side decryption and signature-verification path.
- Keeps the scope to the inbox reader's presentation and invocation.

Cons:
- Multiple visible messages may trigger several reader operations on page load.
- Failure feedback needs a separate, intentional recovery treatment in later planning.

## Option B — Automatically read only after a card becomes visible or is selected

Defer the existing reader action until the user views or selects an inbox card, then show the same concise verification signal and remove the specified UI.

Pros:
- Avoids decrypting unread or off-screen messages immediately.
- Can reduce work for long inboxes.

Cons:
- Adds interaction or viewport-state complexity not requested for this small refinement.
- May make the reader appear less automatic to users.

## Recommendation

Choose **Option A** as a small vertical slice: opening Inbox automatically reads and verifies its displayed messages, removes the redundant reader controls and diagnostics, places “Signature verified” after the From username on success, and hides the encrypted-message module for this cycle. Preserve an explicit failure/recovery path in the next planning step.
