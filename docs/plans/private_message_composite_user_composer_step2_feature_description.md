# Private Message Composite-User Composer: Step 2 Feature Description

> **Feature plan:** [Step 1](./private_message_composite_user_composer_step1_solution_assessment.md) · [Step 2](./private_message_composite_user_composer_step2_feature_description.md) · [Step 3](./private_message_composite_user_composer_step3_development_plan.md) · [Step 4](./private_message_composite_user_composer_step4_implementation_summary.md)

## Problem

`/user/<username>` represents an approved composite user but does not offer the private-message form available from an individual approved profile. Sending must continue to encrypt to every distinct approved key in the recipient composite user.

## User Stories

- As an approved member, I want to message another approved composite user from `/user/<username>` so that I can use their aggregate profile as the normal contact point.
- As a recipient with several approved profiles, I want each approved key to receive the message so that any of my approved browser identities can decrypt it.

## Core Requirements

- Render the established private-message form on an eligible recipient's `/user/<username>` page.
- Reuse the existing recipient username-group key contract so encryption includes every distinct approved recipient key and excludes pending keys.
- Preserve existing eligibility: only an approved authenticated viewer may message a different approved username group.
- Preserve existing browser-side encryption, signing, private-envelope submission, draft recovery, and individual-profile composer behavior.

## Delivery Scope

- Work type: application change.
- This cycle excludes changes to mailbox storage, envelope format, key approval, message-reader behavior, and Forte profile routes.

## Completion Boundary

- Normal entry: an approved authenticated member visits another approved user's `/user/<username>` page.
- End-to-end outcome: the member submits the form; the established flow encrypts to the recipient's complete approved key set and sends the private envelope.
- Recovery: unavailable identity, recipient keys, encryption, or delivery retains the local draft and reports the existing actionable error without submitting plaintext.
- Release condition: aggregate-page eligibility, all-approved-key coverage, and existing individual-profile behavior are verified.

## Risks

- **Incorrect aggregate eligibility** could expose the form for self, pending, or unauthenticated cases. Validate each page state early; reuse the existing eligibility policy.
- **Incomplete key coverage** could leave a recipient device unable to decrypt. Validate an aggregate user with multiple approved and one pending profile; retain the canonical resolver.
- **Missing browser assets on the aggregate page** could render a nonfunctional form. Validate a real submission from `/user/<username>` before release; use the established composer asset set.

## Shared Component Inventory

- `/user/<username>` controller and `username.php`: extend the canonical aggregate-user page with the composer and its eligibility data.
- Individual `profile.php` composer: reuse its presentation and behavior; retain it as an existing entry point.
- Approved composite-user key resolver and recipient-key API: reuse unchanged as the sole recipient-key source.
- Browser private-message composer and envelope helper: reuse unchanged for encryption, signing, submission, and draft recovery.

## Simple User Flow

1. An approved member opens another user's `/user/<username>` page.
2. The page presents the private-message form for that eligible composite user.
3. The member writes and sends a message.
4. The browser encrypts to every approved recipient key and submits the encrypted envelope; the sender receives existing success or recovery feedback.

## Success Criteria

- An eligible `/user/<username>` page renders a working private-message form; self, pending-only, and ineligible views do not.
- A recipient with multiple approved profiles can decrypt the message with each approved key, while a pending-profile key is not included.
- The request contains no plaintext, a send failure preserves the draft, and the existing individual-profile flow remains functional.
