> **Feature plan:** [Step 1](./status_frontend_target_step1_solution_assessment.md) · [Step 2](./status_frontend_target_step2_feature_description.md) · [Step 3](./status_frontend_target_step3_development_plan.md) · [Step 4](./status_frontend_target_step4_implementation_summary.md)

Debug mode: enabled

# Status frontend target — Step 1 solution assessment

## Original Query

```text
FAIL  Frontend       connection refused or unreachable (Connection refused) at http://127.0.0.1:8000
```

This test usually fails for one of two reasons:
- I'm on local dev, but I ran e.g. `./v3 start 8001`.
- I'm on prod, and it's on port 80.

Please write Step 1 for fixing this.

## Problem

`./v3 status` assumes port 8000, so a healthy frontend on another address is reported as failed unless the operator supplies `--url` each time.

## Evidence

- `scripts/status.php` uses `--url` or a fixed `http://127.0.0.1:8000`; `v3 start` supports custom ports and listening addresses.
- The existing probe reports HTTP response status, without establishing which application answered; blindly trying port 80 could mistake another site for this instance.
- The earlier [frontend diagnosis cycle](../status_frozen_frontend_diagnosis/status_frozen_frontend_diagnosis_step4_implementation_summary.md) deliberately deferred address discovery.

## Option A — Persistent explicit target

Keep `--url` as the override and add an instance-specific saved target, allowing production to use port 80 or its actual virtual-host URL.

- Pros: predictable, handles HTTPS and virtual hosts, no discovery ambiguity.
- Cons: requires setup; local port changes still need synchronization and can leave stale targets.

## Option B — Automatic discovery

Find this checkout's active development server and consider a bounded set of conventional production endpoints, including port 80, when no override exists.

- Pros: ordinary status works without repeated arguments in both reported cases.
- Cons: stale process information, multiple servers, and virtual hosts make inference uncertain; an open port alone cannot establish instance health.

## Option C — Explicit target plus constrained discovery

Prefer `--url`, then an instance-specific configured target; otherwise identify this checkout's active development server and consider verified conventional local endpoints. Show the selected URL and its source; report an unresolved or ambiguous target separately from a confirmed target's connection failure.

- Pros: convenient locally, dependable production configuration, honest diagnosis when discovery cannot decide.
- Cons: more selection rules and lifecycle checks; production virtual hosts may still require one-time configuration.

## Recommendation

**Option C.** Deliver one slice: plain `./v3 status` checks this checkout on port 8001 after `./v3 start 8001`, and the intended production instance on port 80; explicit overrides stay authoritative. Never fall back to a healthy alternative after an explicit or confidently identified target fails. Avoid port scans and keep discovery plus probing bounded. When the target cannot be established, show a concise configuration action instead of declaring the frontend down.

Step 2 must settle instance identification, stale/multiple development-server handling, configuration location, and production virtual-host behavior. Acceptance should cover both reported environments, stopped and hung servers, unrelated listeners, stale discovery data, and explicit overrides. No application changes are authorized by this assessment.
