# Private Message Composite-User Composer: Step 1 Solution Assessment

> **Feature plan:** [Step 1](./private_message_composite_user_composer_step1_solution_assessment.md) · [Step 2](./private_message_composite_user_composer_step2_feature_description.md) · [Step 3](./private_message_composite_user_composer_step3_development_plan.md) · [Step 4](./private_message_composite_user_composer_step4_implementation_summary.md)

## Original Query

We want private messages to be encrypted for every approved key in a composed profile, and we want the private message form to be available on the `/user/xyz` page. Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

Treat `/user/<username>` as the composite-user page: sending from it must target the same approved username-group key set as the existing private-message flow.

## Problem Statement

The aggregate `/user/<username>` page has no private-message form, despite representing the approved profile group whose keys must all receive a message.

## Option A: Extend the existing composite-user messaging path

Render the established composer on eligible `/user/<username>` pages and reuse the existing approved username-group key resolution for encryption.

- Pros:
  - Meets both requested outcomes at the aggregate profile's natural entry point.
  - Preserves one canonical all-approved-key recipient contract across profile and user pages.
  - Small, viable vertical slice with focused eligibility and multi-key coverage.
- Cons:
  - The aggregate page needs the same authenticated-viewer context and client assets as the individual profile page.

## Option B: Link from `/user/<username>` to an individual profile composer

Keep the form on individual profile pages and add a link from the aggregate page.

- Pros:
  - Minimal aggregate-page change.
- Cons:
  - Does not make the form available on `/user/xyz` as requested.
  - Makes a composite-user action depend on an arbitrary member profile.

## Option C: Add a separate aggregate-page encryption flow

Give `/user/<username>` its own recipient-key lookup and composer behavior.

- Pros:
  - Can be tailored independently for the aggregate page.
- Cons:
  - Duplicates the existing cryptographic recipient contract.
  - Risks different approved-key coverage between entry points.

## Recommendation

Choose **Option A**. Reuse the existing composite-user key resolver and browser envelope preparation, and add the existing composer to eligible `/user/<username>` pages. This is a releasable vertical slice: an approved member can message another aggregate user from its page, while every approved key in that user's group receives the encrypted envelope.
