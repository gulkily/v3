> **Feature plan:** [Step 1](./hide_messages_new_message_form_step1_solution_assessment.md) · [Step 2](./hide_messages_new_message_form_step2_feature_description.md) · [Step 3](./hide_messages_new_message_form_step3_development_plan.md) · [Step 4](./hide_messages_new_message_form_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

A user found the "New message" form on the /messages/ page confusing, thinking it is a registration form. I think it is best if we just hide this form for now. Users can still send a message from the user's page. Please write Step 1 of railspike.md.

Use debug mode.

## Problem

The recipient-entry form on the Messages page can be mistaken for registration, confusing users who want to send a private message.

## Option A — Hide the form and its entry points

Hide the “New message” form on the Messages page, including the empty-state button that opens it, while retaining messaging from user pages and replies in existing conversations.

- Pros: Directly meets the request; small, reversible change; removes the confusing interaction and avoids a stranded button.
- Cons: Users must discover the user-page route to start a new conversation.

## Option B — Hide the form and add brief guidance

Make the same change as Option A and show a short instruction on the Messages page explaining that users can start a message from the recipient’s user page.

- Pros: Removes the confusion while making the remaining way to start a message easier to discover, including when the conversation list is empty.
- Cons: Adds copy beyond the requested hiding change; guidance alone does not help users find a particular recipient.

## Recommendation

Choose **Option A** for this temporary change. Hide both the form and its empty-state trigger; preserve conversation browsing, replies, and the existing user-page send flow. Option B is a compatible addition if discovery proves confusing, but is not necessary for the requested scope.

This is a small, complete user-facing slice: users can open a recipient’s page, send a message through the existing composer with its current failure/retry behavior, and return to Messages to find and continue the conversation. Verification should cover that journey and both empty and populated Messages views. No messaging redesign, permission changes, or data migration is needed.
