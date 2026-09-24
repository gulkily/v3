# Backup Page Freshness Preview Step 1 Solution Assessment

## Problem Statement

Users need the backup page to show backup freshness and recent included items before download. Source: `thread-20260826031531-923617e0`, submitted 2026-08-26T03:15:31Z.

## Option A: Show generated-at metadata only

Pros:
- Smallest reassurance improvement.
- Easy to compare against artifact metadata.
- Low UI complexity.

Cons:
- Does not prove what content is included.
- Less useful when users suspect stale cache.

## Option B: Show generated-at metadata plus a recent-items preview

Pros:
- Directly addresses freshness and contents.
- Helps users verify the archive before download.
- Can reuse existing activity or read-model summaries.

Cons:
- Needs clear rules for which items appear.
- Preview itself must avoid stale-cache confusion.

## Option C: Generate backups only on demand with progress feedback

Pros:
- Strong freshness guarantee.
- Clear user expectation.

Cons:
- More expensive and abuse-prone.
- Slower than serving cached archives.

## Recommendation

Recommend Option B.

Brief justification:
- A timestamp plus recent-items preview answers the user's trust concern while keeping the existing backup model intact.
