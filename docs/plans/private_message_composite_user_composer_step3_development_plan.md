# Private Message Composite-User Composer: Step 3 Development Plan

> **Feature plan:** [Step 1](./private_message_composite_user_composer_step1_solution_assessment.md) · [Step 2](./private_message_composite_user_composer_step2_feature_description.md) · [Step 3](./private_message_composite_user_composer_step3_development_plan.md) · [Step 4](./private_message_composite_user_composer_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an approved authenticated member visits a different approved composite user's `/user/<username>` page.
- End-to-end outcome: the page's composer sends an envelope encrypted to every distinct approved recipient key through the established private-message flow.
- Required recovery: encryption or delivery failure retains the recipient-scoped local draft and reports the existing actionable error; plaintext is never submitted.
- Deployment/external verification: confirm authenticated aggregate pages load the required browser assets while unauthenticated/static/offline output exposes neither a working form nor message plaintext.
- Release condition: aggregate eligibility, multi-key encryption coverage, recovery, and the existing individual-profile composer regression checks pass.

## Key Risks

- **High risk: aggregate-page eligibility differs from the existing profile policy.** Early validation: render self, unauthenticated, pending-only, eligible, and multi-profile recipient cases. Mitigation: derive one aggregate eligibility flag from the existing approved viewer and approved recipient-group contract.
- **High risk: a duplicated composer drifts from the encryption contract.** Early validation: inspect both entry points and their submitted recipient token. Mitigation: share one composer presentation and retain the existing browser helper and recipient-key API.
- **High risk: one approved key is omitted.** Early validation: encrypt a fixture for multiple approved and one pending recipient profile. Mitigation: preserve `ApprovedUserKeyResolver` as the sole key-set source.

## Stage 1 - Add the aggregate-user private-message vertical slice

- Goal: Let an eligible approved member send a private message directly from `/user/<username>` with the same all-approved-key coverage as an individual profile.
- Dependencies: Approved Step 2; existing private-message composer, recipient-key API, and `ApprovedUserKeyResolver` tests.
- Expected changes: Extend the aggregate-user controller/template data with recipient eligibility and the established identity/composer assets; extract or reuse one canonical composer presentation for both profile surfaces; keep the existing recipient username token, browser envelope preparation, draft, and API contracts unchanged; add aggregate-page rendering and multi-key regression coverage.
- Verification approach: Render eligible and ineligible aggregate-user pages; assert only the eligible page has the canonical form, recipient token, and required assets; run the focused composer, envelope, resolver, and mailbox/API tests; verify a multi-approved-key recipient decrypts while a pending key is excluded; confirm request payloads contain no plaintext and failed send retains the draft.
- Risks or open questions:
  - Impact: An incorrect page state can expose a broken or unauthorized form.
  - Early warning / validation: Any self, pending-only, or unauthenticated fixture renders the composer, or an eligible fixture lacks its assets.
  - Mitigation: Reuse the existing approved-viewer policy and conditionally attach the composer asset set only with the form.
- Canonical components/API contracts touched: `ProfilePageController::username()`, `username.php`, the shared private-message composer presentation, `profile.php`, identity script loading, `ForumPrivateMessageComposer`, `/api/private_messages/recipient_keys`, and `ApprovedUserKeyResolver`.
