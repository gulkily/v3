> **Feature plan:** [Step 1](./hide_messages_new_message_form_step1_solution_assessment.md) · [Step 2](./hide_messages_new_message_form_step2_feature_description.md) · [Step 3](./hide_messages_new_message_form_step3_development_plan.md) · [Step 4](./hide_messages_new_message_form_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- **Outcome:** Through normal site navigation, an eligible sender opens a recipient’s user page, sends a message, returns to Messages, and continues the conversation. Empty and populated Messages pages expose neither the new-message form nor its trigger, including by keyboard; existing Messages aliases and legacy redirects behave consistently.
- **Recovery:** Preserve list-loading retry, send-error/draft recovery, unread status, pagination, and eligibility restrictions.
- **Release condition:** Focused PHP checks and the existing local browser harness pass the retained journey and hidden-control checks, with desktop/mobile inspection and no browser errors. Verify in isolated local state; no production deployment or external service change is required or authorized. No schema, API, permission, or function-signature changes.

## Key Risks

- **High risk: Discoverability (usability).** Removing the shortcut makes user pages the route to starting messages. Early inspection confirmed the users directory links to username pages, profile pages link to username pages, and both reuse the composer. Preserve those paths; reduced discoverability is the accepted Step 2 tradeoff.
- **High risk: Stranded controls (usability).** The empty-state button opens the same form, and existing browser tests depend on it. Inspection confirmed recipient binding exits when the form is absent. Remove both controls together and adapt the browser journey before release; preserve shared list and composer behavior.

## Stage 1

- **Goal:** Hide the confusing Messages entry form while proving the remaining end-to-end messaging flow works; approximately one hour.
- **Dependencies:** Approval of this completed Step 3 plan; then create the feature branch, organize this feature’s Steps 1–3 and debug log into `docs/plans/hide_messages_new_message_form/`, update the planning index/navigation, and commit only those planning changes. Preserve the unrelated staged `todo.txt` change. Create the Step 4 summary after the planning commit.
- **Expected changes:** Omit the form and empty-state trigger from the list template; remove only recipient-selection wiring and suggestion loading made unused by that omission. Adapt existing browser coverage to reach the recipient through normal user navigation, send, return through Messages, and reply; replace obsolete picker checks with empty/populated hidden-control checks. Add no replacement copy or new component.
- **Verification approach:** Run `php tests/run.php PrivateMessagePageControllerTest PrivateMessageListTest PrivateMessageComposerTest PrivateMessageApiRoutingTest PrivateMessageMailboxServiceTest` and `node tests/browser/private_message_list_browser.mjs`. Use the harness’s isolated identities/state to check both Messages URL spellings, legacy redirects, empty/populated views, keyboard access, user/profile composers, send/list recovery, previews, unread state, paging, and eligibility. Inspect desktop/mobile output; run syntax checks for changed PHP/JavaScript and `git diff --check`.
- **Risks or open questions:** Removing picker checks must not remove meaningful eligibility coverage; retain service/API rejection checks and shared-composer recovery checks. Resolve browser prerequisites before editing; failing or unavailable required verification blocks completion. No product-scope questions remain.
- **Canonical components/API contracts touched:** Existing `private_message_list.php`, its page controller and `private_message_list.js`; existing page/controller and browser checks as needed. Reuse conversation rows, user/profile composer, and conversation reader; messaging API contracts remain unchanged.
- **Handoff:** Record changes and actual verification in the Step 4 summary, append the completed-step debug report, and commit this stage with its summary/log updates. Expected minimum: one planning commit plus one implementation-stage commit; no deployment.
