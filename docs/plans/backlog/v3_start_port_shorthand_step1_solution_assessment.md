# V3 Start Port Shorthand Step 1 Solution Assessment

## Problem Statement

Developers need `./v3 start 8001` and `./v3 start :8001` to start the local server on that port without address errors. Source: `thread-20260826045505-e2bb5637`, submitted 2026-08-26T04:55:05Z.

## Option A: Accept port-only arguments and normalize to `127.0.0.1:PORT`

Pros:
- Directly fixes the reported commands.
- Keeps the default local-only binding.
- Low product ambiguity.

Cons:
- Requires clear validation for invalid ports.
- `:PORT` semantics may surprise users expecting all-interface binding.

## Option B: Accept `:PORT` as all-interface binding

Pros:
- Matches some server conventions.
- Useful for testing from other devices.

Cons:
- Less safe as a default.
- Conflicts with the local-only expectation of `start`.

## Option C: Only improve error messages

Pros:
- Minimal change.
- Avoids changing accepted command forms.

Cons:
- Does not make the requested commands work.

## Recommendation

Recommend Option A.

Brief justification:
- Port-only shorthand should remain safe and local by default while removing the current ergonomics trap.
