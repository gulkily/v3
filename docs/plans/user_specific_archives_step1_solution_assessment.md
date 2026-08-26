# User Specific Archives Step 1 Solution Assessment

## Problem Statement

Users need downloadable archives of their own visible forum contributions without making bespoke archive generation cheap to spam.

## Option A: Generate user archives synchronously on each download request

Pros:
- Closest to the current repository archive download behavior.
- Simple user experience: click a link and receive a fresh archive.
- Avoids persistent archive storage and invalidation concerns.

Cons:
- Repeated requests can force expensive archive work and amplify DDoS risk.
- Does not establish a useful foundation for future arbitrary or conditional archives.
- Harder to provide predictable failures for large users or busy servers.

## Option B: Prebuild and publish user archives during static/read-model rebuilds

Pros:
- Download requests serve static files instead of doing archive work.
- Strongest protection against repeated download-triggered CPU and disk churn.
- Fits the existing static artifact mindset.

Cons:
- Rebuild cost grows with every active user, even if nobody downloads an archive.
- Requires cache invalidation and storage cleanup as users and posts change.
- Less flexible for future conditional archives that may not map to fixed users.

## Option C: Add a reusable archive request/cache boundary for user archives

Pros:
- Limits expensive work with cached artifacts, freshness metadata, locks, and request throttling.
- Supports the first user-specific archive while leaving room for later conditional archive specs.
- Can start from existing profile/username identity selection and canonical source paths.
- Lets the UI show "available", "building", or "try later" states instead of regenerating repeatedly.

Cons:
- More moving parts than a direct download route.
- Requires clear cache lifetime and invalidation policy.
- First release must keep the archive spec narrow to avoid becoming a general query engine.

## Recommendation

Recommend Option C.

Brief justification:
- The core risk is repeated expensive archive generation, while the likely future direction is arbitrary/conditional archives.
- A narrow request/cache boundary can serve user-specific archives now, protect the site from repeated work, and become the shared contract for future archive filters.
