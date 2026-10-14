> **Feature plan:** [Step 1](./hide_messages_new_message_form_step1_solution_assessment.md) · [Step 2](./hide_messages_new_message_form_step2_feature_description.md) · [Step 3](./hide_messages_new_message_form_step3_development_plan.md) · [Step 4](./hide_messages_new_message_form_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Hide the Messages recipient form

- Changes: Removed the Messages recipient form and empty-state trigger, their JavaScript binding/export, and the unused recipient-suggestion query. Retained the shared composer, conversation list, and messaging API behavior.
- Changes: Updated existing controller and browser checks for absent controls in empty/populated lists and both Messages URL spellings. The browser journey now follows Users → bob’s user page → send → Messages → conversation reply. Removed obsolete picker-only checks; eligibility coverage remains in the service and composer checks.
- Verification: `php tests/run.php PrivateMessagePageControllerTest PrivateMessageListTest PrivateMessageComposerTest PrivateMessageApiRoutingTest PrivateMessageMailboxServiceTest` — 17 passed, 0 failed.
- Verification: `node tests/browser/private_message_list_browser.mjs` — passed with isolated local identities/state and no browser errors. Covers user-page first send, replies, draft/send recovery, list/history pagination and retry, unread/seen behavior, eligibility, legacy redirects, and shared username/profile composers. Controls are absent from the DOM, so cannot enter keyboard navigation; retained conversation links remain focusable.
- Verification: Inspected `messages-desktop.png`, `messages-mobile.png`, and `messages-empty.png` from `/tmp/private-message-browser-kbpKrV/`; the form and trigger are absent, and the mobile list fits its viewport. Full report and screenshots remain in that temporary directory.
- Verification: Changed PHP/JavaScript syntax checks, `git diff --check`, and all four local plan-navigation links passed. Initial checks caught an obsolete JavaScript export and old form expectations; these were corrected before the successful reruns.
- Notes: Completed the approved application scope on `hide-messages-new-message-form`, following planning commit `cd94b661`. No deployment, migration, or external service verification applies. Starting a new conversation now requires the existing user-page route, as approved. The unrelated staged `todo.txt` edit is excluded from feature commits.
