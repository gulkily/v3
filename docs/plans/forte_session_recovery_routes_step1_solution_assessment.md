# Forte Session-Recovery Routes: Step 1 Solution Assessment

## Problem statement

When a PHP session is absent, valid Forte routes are rejected as nonexistent before their handlers or the shared reauthentication-resume flow can run.

## Option A — Complete the existing route classifier

Pros:
- Small, low-risk correction to the established members-only gate.
- Restores recovery for every current Forte HTML route and gives Forte APIs an explicit authorization response.
- Requires no database, authentication, or interface redesign.

Cons:
- The classifier remains a manually maintained duplicate of route dispatch.
- Future Forte routes need corresponding classifier coverage.

## Option B — Share a route-matching contract between the gate and dispatcher

Pros:
- Prevents dispatch/classifier drift across Forte and other future routes.
- Makes every recognized route consistently eligible for the correct access outcome.

Cons:
- Broadens a targeted recovery fix into routing architecture work.
- Carries higher regression risk across unrelated routes.

## Option C — Treat every `/forte` and Forte-API prefix as an application route

Pros:
- Very small change that protects new Forte subroutes automatically.
- Avoids enumerating current Forte route shapes.

Cons:
- A mistyped Forte URL may unnecessarily authenticate before correctly returning 404.
- Weakens the route classifier's role as an exact route inventory.

## Recommendation

**Approved direction: Option C.** Treat every `/forte` and Forte-API prefix as an application route so an expired session reaches the established recovery or authorization outcome instead of a false 404. The accepted trade-off is that a mistyped Forte URL may authenticate before the normal router returns its 404.
