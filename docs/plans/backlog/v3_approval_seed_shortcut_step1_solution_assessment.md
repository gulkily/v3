# V3 Approval Seed Shortcut Step 1 Solution Assessment

## Problem Statement

Operators need an easier CLI shortcut from `./v3 approve` to the existing approval seed command. Source: `thread-20260826041041-95b7c03b`, submitted 2026-08-26T04:10:41Z.

## Option A: Add `./v3 approve` as an alias for `./v3 approval seed`

Pros:
- Directly matches the requested command.
- Low product ambiguity.
- Preserves the existing implementation path.

Cons:
- "Approve" may sound broader than seeding approval.
- Could conflict with future approval subcommands.

## Option B: Add a clearer shortcut such as `./v3 seed-approval`

Pros:
- More explicit operator intent.
- Avoids overloading "approve".

Cons:
- Does not match the user's requested spelling.
- Adds another top-level command name.

## Recommendation

Recommend Option A with explicit usage text.

Brief justification:
- The request is a CLI ergonomics issue, and an alias can remain safe if help text makes clear that it seeds approval.
