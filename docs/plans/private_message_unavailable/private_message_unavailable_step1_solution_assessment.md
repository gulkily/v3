# Compact unavailable private messages Step 1 solution assessment

> **Feature plan:** [Step 1](./private_message_unavailable_step1_solution_assessment.md) · [Step 2](./private_message_unavailable_step2_feature_description.md) · [Step 3](./private_message_unavailable_step3_development_plan.md) · [Step 4](./private_message_unavailable_step4_implementation_summary.md)

## Original Query

Amazing work, please merge into main and then continue to the next one.

Follow-up: Combine Options B and C, please.

## Understood Intent

Cycle 4 is merged into local `main`, completing the original four-cycle program. The proposed next cycle addresses the earlier request to reduce the large, distracting decryption-error/retry widget in the [master checklist](../private_messaging_usability_master_checklist.md). Historical-key recovery remains a separate, deferred assessment.

## Problem Statement

The [shared reader](../../../public/assets/private_message_reader.js) gives every unsuccessful read a visible error and retry button, so repeated unreadable messages overwhelm history even when retrying cannot help.

## Option A Reduce spacing only

Keep every error and retry visible but make their presentation smaller.

- Pros: smallest change; recovery remains immediately discoverable.
- Cons: repeated explanations and retry actions still dominate unreadable history; suggests retry is equally useful for every failure.

## Option B Compact placeholders with optional details

Represent each unavailable message with a short, neutral placeholder; reveal its explanation and manual retry through an accessible details control, while keeping signature warnings visible.

- Pros: preserves message order, sender, and time; reduces repeated clutter; retains recovery without promising it will succeed.
- Cons: recovery takes another interaction; transient loading failures need a distinct, visible retry so recoverable problems are not buried.

## Option C Collapse consecutive unavailable messages

Combine adjacent unreadable messages into one expandable summary showing how many are hidden.

- Pros: greatest space reduction for long unreadable stretches.
- Cons: hides individual chronology; complicates pagination, reading position, and latest-message visibility used by unread acknowledgment.

## Recommendation

Combine **Options B and C** as requested: compact individual placeholders inside expandable groups of consecutive unavailable messages. Approved as the direction for this bounded fifth cycle:

- Show an isolated unavailable message as a compact placeholder. Collapse consecutive unavailable messages from the same sender on the same local date into a summary with count, sender/direction, and time range; never group across readable messages, security warnings, or date separators.
- Expand a group into compact individual placeholders retaining each message's time, with optional explanation and manual retry. Keep both levels keyboard accessible, preserve focus and expanded state during history changes, and avoid automatic retry loops.
- Keep known loading failures outside collapsed groups with direct retry. Do not infer a wrong or newly added key from a generic failure; explain that unchanged ciphertext and an unsuitable key cannot become compatible through repeated retries. A successful retry restores the readable message in order and updates surrounding groups without concealing it.
- Keep missing/invalid-signature warnings prominent and outside groups; never expose unverified plaintext or imply verification. Keep list previews consistent without adding transcript grouping to the Messages list.
- Preserve older-history loading, reading position, drafts, and sending. A visible summary represents its settled unavailable messages for Cycle 4's existing presented-window acknowledgment, including the latest message; expansion is not required to acknowledge that same boundary and cannot broaden it or bypass focus/visibility gates.
- Verify Messages → conversation → expand group → details/retry, including mixed results, successful recovery, groups meeting at loaded-history boundaries, and the latest message inside a collapsed group on desktop, mobile, and keyboard navigation.

This is a releasable reading-and-recovery improvement targeted within one day or eight stages; validate grouping and scroll/acknowledgment risks before implementation and rescope if needed. No key-management redesign, historical re-encryption, schema changes, live delivery, or new read-state semantics are included. Step 2 should settle presentation and failure categories.
