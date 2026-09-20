# Forte Session-Recovery Routes: Step 3 Development Plan

## Stage 1
- Goal: Recognize the complete Forte URL space during members-only access checks.
- Dependencies: Approved Step 2 and the existing authentication-resume gate.
- Expected changes: Add a Forte route classifier, conceptually `isForteApplicationRoute(string $path): bool`, that accepts `/forte` and its descendants plus the existing Forte API naming space; use it from the canonical application-route classifier.
- Verification approach: Exercise expired-session Forte HTML and API requests through the application, confirming they no longer reach the false 404 branch.
- Risks or open questions:
  - Per approved Option C, an invalid Forte descendant may authenticate before normal dispatch returns 404.
- Canonical components/API contracts touched: `Application::isApplicationRoute()`, members-only access gate, existing Forte API access outcome.

## Stage 2
- Goal: Lock the recovery behavior against regressions without changing authenticated Forte routing.
- Dependencies: Stage 1.
- Expected changes: Add local smoke coverage for the Forte board, users, activity, profile, username, and Forte API URL families with no session; cover a valid authenticated route and an invalid Forte route.
- Verification approach: Run the focused Forte/session smoke tests and the relevant authentication regression suites.
- Risks or open questions:
  - Existing client-side Forte API callers must continue handling their established authorization response.
- Canonical components/API contracts touched: Forte HTML handlers, `/api/forte_*` and `/api/get_forte_*` routes, authentication-resume page contract.
