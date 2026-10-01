# Live OpenPGP Smoke Test — Step 2 Feature Description

## Problem

OpenPGP asset selection was correct locally but the selected HTTP bundle was
unreachable in production. The deployment process needs a live asset gate and
an intentional production canary that proves a new user can publish a post.

## User stories

- As a release operator, I want one command to validate OpenPGP assets on both
  public origins so that I can block a broken deployment immediately.
- As a developer, I want the command to follow the rendered runtime contract
  so that test coverage cannot drift from the browser's selected URLs.
- As a user, I want broken identity assets caught before release so that I am
  not prompted repeatedly for a username when OpenPGP cannot load.
- As a community member, I want a successful release check to appear as a
  “new release just dropped” post so that release activity is visible and
  useful rather than hidden test noise.

## Core requirements

- Check `http://zenmemes.com/` and `https://zenmemes.com/` by default, with a
  safe explicit target override for staging.
- Read an identity-capable rendered page and validate the emitted runtime
  asset contract on each origin.
- Verify that HTTP selects and serves v5, HTTPS selects and serves v6, and
  legacy raw OpenPGP bundle URLs remain reachable for cached clients.
- Reject redirects, non-success responses, non-JavaScript bodies, missing
  runtime URLs, and wrong version/origin selections with actionable output
  and a nonzero exit status.
- After the asset gate passes, run an explicitly authorized fresh-browser
  production canary: create an identity, publish “New release just dropped,
  making sure it works,” reload, and confirm the identity is reused.
- Treat the canary identity and post as intentional durable community records;
  never silently create them and never delete or rewrite production content as
  test cleanup.
- Run the live check manually only. It must not run from CI, cron, page load,
  or an automatic deployment hook.

## Shared component inventory

- **`v3` CLI:** extend the canonical operator command surface; do not add a
  one-off shell-only release command.
- **Rendered runtime asset contract:** consume the existing page-emitted asset
  paths rather than duplicating asset names or fingerprint logic.
- **Browser identity and compose flow:** exercise the existing canonical
  browser signing and posting surfaces; do not create a test-only write API.
- **Production deployment runbook:** extend its existing release verification
  section with the command; no new operator UI is needed.
- **Existing PHP test runner:** reuse it for deterministic command and parser
  coverage; no database or schema change is needed.

## User flow

1. Operator deploys the application release.
2. Operator manually runs the live OpenPGP smoke command before declaring
   success.
3. The command validates both origins and their selected/legacy bundle URLs.
4. After a pass, the operator explicitly authorizes the fresh-browser canary.
5. The canary creates its identity, publishes the release post, reloads, and
   confirms the identity remains ready without another username prompt.
6. A pass permits release completion; a failure identifies the exact phase,
   origin, URL, or browser action for repair.

## Success criteria

- The command would fail the observed HTTP-v5-404 incident with a precise
  diagnostic.
- It passes only when HTTP v5 and HTTPS v6 assets are reachable JavaScript at
  the URLs the pages actually emit.
- The explicit production canary creates one visible release post from a new
  browser identity and completes a reload without a second username prompt.
- The read-only phase returns a shell-friendly pass/fail status; the canary
  requires separate affirmative production-write authorization.
- Automated coverage includes command success plus missing, redirected, and
  non-JavaScript asset responses, alongside browser-canary failure reporting.

Reply **Approved Step 2** to proceed to the development plan.
