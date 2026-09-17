# Chouse Approved-Members-Only Access — Step 1: Solution Assessment

## Problem

Chouse should be a private approved-members site: unapproved visitors may
reach only a lobby explaining the approval process, while approved users may
use the complete site.

## Option A — Centralized site-wide access gate with an unapproved lobby

Resolve the current viewer identity and approval state once for each request;
allow only the lobby, the account page, and the authenticated user’s own
linked profile to unapproved users. Route every other page, feed, API, backup,
and content document through the gate.

- Pros:
  - One simple policy replaces resident labels, resident topics, and special
    topic filtering.
  - Easy to explain and test: approved or lobby.
  - Reuses the existing approved-user model and approval directory flow.
  - Future private-site features inherit the same boundary automatically.
- Cons:
  - Every public route and static-artifact path must be audited.
  - Authentication must prove key possession; an identity hint alone is not
    sufficient for a production privacy boundary.

## Option B — Gate only the main board and leave supporting routes public

Protect the home/board route while retaining public access to profiles,
threads, APIs, RSS, tags, and informational pages.

- Pros:
  - Smaller initial code change.
- Cons:
  - Violates the “only the lobby” requirement.
  - Leaks content through alternate URLs, feeds, APIs, or static artifacts.
  - Creates an inconsistent and difficult-to-maintain privacy model.

## Recommendation

**Option A.** Make approval the sole membership boundary and add one
dedicated, site-independent feature flag for the access policy. Permit only
the lobby, key/account setup, login/authentication, and the minimum
approval-related flows before approval. Return 404 for protected content to
avoid revealing its existence. Disable public backups independently.

## Confirmed access matrix

- No resident labels or resident-specific topic views.
- No `#resident` access semantics.
- Lobby users can access only:
  - the Lobby page;
  - the Account page; and
  - their own profile linked from Account.
- Lobby users cannot access public backups, other profiles, threads, posts,
  feeds, APIs, tags, activity, tools, or instance pages.
- Approved users can access the entire site.
