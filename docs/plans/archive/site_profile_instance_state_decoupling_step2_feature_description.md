# Site Profile and Instance State Decoupling: Step 2 Feature Description

## Problem

Changing the local site profile currently changes the repository, approval state, and read-model database. Presentation selection must not create a separate instance or alter which canonical data is active.

## User stories

- As an operator, I want one canonical repository and database per instance so that identities, approvals, and content remain consistent across themes.
- As a developer, I want to test Chouse presentation against existing local data so that theme testing does not require rebuilding account state.
- As a multi-instance operator, I want explicit instance-path overrides to remain available so that genuinely separate sites stay isolated.
- As a developer, I want presentation caches to remain safe across site profiles so that cached HTML does not display the wrong branding.

## Core requirements

- Site-profile selection affects branding, copy, and default theme, but not the active canonical repository or read-model database.
- Local defaults resolve to one repository and one database regardless of the selected site profile.
- Explicit repository and database configuration continues to define genuinely separate instances.
- Profile-specific static caches may remain separate because they are disposable presentation artifacts.
- Existing default local data remains authoritative; duplicate Chouse sandbox records are not merged into it.

## Shared component inventory

- **Site-profile selection:** reuse the existing selector as the canonical presentation choice; narrow its responsibility to presentation only.
- **Instance path configuration:** reuse the existing repository and database overrides as the canonical instance boundary; no new configuration surface is needed.
- **Application routes and APIs:** reuse the existing shared read/write services; every route must continue using the instance-selected repository and database.
- **Approval CLI:** extend the existing command to use the same instance defaults and overrides as the server; no separate Chouse command is needed.
- **Static artifact generation and serving:** reuse the existing builder and front controller with profile-safe cache selection; no new user-facing UI is needed.

## Simple user flow

1. The operator starts an instance with its desired site profile and access feature flags.
2. The server opens the instance's existing canonical repository and database.
3. The user signs in and sees the same identity, approval, and content state under either presentation.
4. If the profile changes, presentation artifacts are selected or refreshed without changing instance data.
5. A separate instance is created only when the operator supplies different instance paths.

## Success criteria

- Starting locally with either the default or Chouse profile resolves to the same repository and database unless explicit overrides differ.
- The D0EE identity remains approved and can access the Board after switching profiles without another approval operation.
- Changing the site profile creates no additional repository or database.
- Approval commands and web requests resolve the same instance state.
- Generated pages use the selected presentation without leaking stale branding, and the complete test suite passes.
