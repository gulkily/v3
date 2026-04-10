# PHP Slice-Based Development Note V1

This project is being built in slices.

A slice is a small, end-to-end piece of work that crosses the layers needed to make one thing real:

- spec or contract updates
- backend logic
- rendering or UI where needed
- tests
- operational behavior when relevant

We prefer slices over large horizontal phases because they keep the project reviewable and keep progress real. Each slice should leave the repo in a working state.

An important part of this approach is being explicit about when a slice is:

- a real retained product behavior
- a technical bridge toward a better later slice

For example, the account/bootstrap work moved through a bridge phase where the flow exposed `bootstrap_post_id` directly. That bridge was technically valid because identity bootstrap records store a bootstrap post and thread reference, but it was not the intended long-term user experience. In slice-based development terms, that means:

- the current slice is valid and working
- the current slice is not the final product shape
- a later slice should replace manual bootstrap-post selection with automatic bootstrap-post creation

## What A Good Slice Looks Like

A good slice:

- has one clear goal
- is small enough to implement and verify in one focused pass
- ends in working code, not just notes
- includes verification
- is committed cleanly before the next slice starts

It should also be honest about scope:

- if something is still technical or placeholder-like, say so
- if the slice is intentionally incomplete from a product perspective, record the next improvement slice

## Why We Use This Approach

This rewrite has a lot of connected concerns:

- canonical record formats
- write orchestration
- read-model rebuilds
- Apache/static behavior
- browser identity flow
- user-facing templates

If those are developed in isolation for too long, risk builds up quickly. Slice-based delivery reduces that risk by forcing integration early.

It also helps us separate two questions that are easy to confuse:

1. Does the current implementation work correctly?
2. Is the current implementation the final product UX we want?

Sometimes the answer is:

- yes, it works correctly
- no, it is still a technical slice

That is acceptable as long as the limitation is documented and the next slice is clear.

## Practical Rule

For this repo, a slice usually means:

1. define or confirm the contract
2. implement the smallest complete behavior
3. add or update tests
4. verify the repo still passes
5. commit the slice

When needed, add a sixth step:

6. record what still feels technical, temporary, or too exposed for end users

## Result

This keeps the project understandable, keeps regressions smaller, and makes it easier to pause, review, or deploy with confidence.

It also gives us a disciplined way to move from:

- technically correct

to:

- technically correct and product-appropriate

without pretending those are always achieved in the same slice.
