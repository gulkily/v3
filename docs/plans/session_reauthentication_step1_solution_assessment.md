# Session Reauthentication: Step 1 Solution Assessment

## Problem statement

An expired PHP session sends approved members to Lobby or a non-recovering access page and loses the URL they intended to visit.

## Option A — Extend and configure PHP sessions

Pros:
- Smallest change and fastest mitigation.
- Reduces avoidable expiry caused by inherited defaults.

Cons:
- Does not restore the original destination or address cold-navigation UX alone.
- Retains dependence on PHP/file-session cleanup behavior.

## Option B — Resume flow on top of PHP sessions

Pros:
- Preserves a validated original destination.
- Silently restores a session for safe HTML GETs; no Lobby flash or reload.
- Supports an in-page navigation guard for truly background recovery.
- Limits scope while retaining the current authentication proof.

Cons:
- Cold navigations still require a brief resume document before protected content can load.
- Does not give the application full control of session retention.

## Option C — Application-owned opaque authentication sessions

Pros:
- Explicit idle/absolute expiry, rotation, revocation, and cleanup policy.
- Removes authentication dependence on PHP's file-session lifecycle.

Cons:
- Larger security-sensitive change with persistence and migration work.
- Does not by itself fix destination recovery; it still benefits from Option B.

## Recommendation

Choose **Option B** first, with explicit PHP retention settings from Option A. It resolves the user-visible failure safely and establishes the recovery contract. Reassess Option C after the intended expiration policy and recovery telemetry are available; do not adopt a stateless client credential.
