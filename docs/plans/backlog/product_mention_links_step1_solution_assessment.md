# Product Mention Links Step 1 Solution Assessment

## Problem Statement

Users want product mentions in posts to link to relevant product pages. Source: `thread-20260826053147-e7ab4e43`, submitted 2026-08-26T05:31:47Z.

## Option A: Auto-detect product names and link to external product pages

Pros:
- Most automatic user experience.
- Can enrich posts without author markup.

Cons:
- High false-positive risk.
- External product source selection is ambiguous.
- Privacy and affiliate implications need policy.

## Option B: Add explicit product-link markup or compose assistance

Pros:
- Keeps author intent clear.
- Avoids guessing which product/page is correct.
- Can start with normal links and better preview text.

Cons:
- Requires user action.
- Less magical than automatic detection.

## Option C: Maintain a local product directory and link only known products

Pros:
- More controlled and auditable.
- Could support community-specific products.

Cons:
- Requires curation and data ownership.
- Larger product surface.

## Recommendation

Recommend Option B.

Brief justification:
- Product linking has correctness and policy ambiguity, so explicit author-controlled links are safer than automatic external matching.
