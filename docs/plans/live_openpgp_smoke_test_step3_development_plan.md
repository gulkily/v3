# Live OpenPGP Smoke Test — Step 3 Development Plan

## Stage 1 — Read-only live asset contract probe

- Goal: provide one manual CLI check for Zenmemes HTTP/HTTPS runtime assets.
- Dependencies: approved Step 2; existing `v3` CLI and emitted runtime asset contract.
- Expected changes: add an `openpgp smoke` operator command and read-only probe service; default to Zenmemes with an explicit staging-origin override; report selected URL, status, content type, redirects, and failure reason.
- Verification approach: deterministic response-fixture tests for successful HTTP-v5/HTTPS-v6 checks and missing, redirected, and non-JavaScript responses.
- Risks or open questions:
  - The runner needs outbound DNS/HTTP access.
  - A remote response may return `200` with an error body.
- Canonical components/API contracts touched: `v3` CLI; page-emitted `window.__forumAssetPaths`; production deployment runbook.

## Stage 2 — Fresh-browser production canary

- Goal: manually prove new-user HTTP identity creation, signed posting, reload persistence, and no repeat prompt.
- Dependencies: Stage 1 pass; a local browser automation dependency; existing browser identity and compose flows.
- Expected changes: add an explicit production-write canary mode that launches an isolated browser context, uses HTTP, creates a new identity, posts the release announcement, reloads, and verifies identity reuse; require affirmative production-write intent.
- Verification approach: local browser-harness tests for success and structured failure reporting; manually run once against production only after Stage 1 passes.
- Risks or open questions:
  - Each successful run creates a durable production identity and post.
  - Retrying after an ambiguous failure can create another announcement.
- Canonical components/API contracts touched: `browser_signing.js`; compose thread route/API; browser-local identity storage; new operator canary interface.

## Stage 3 — Regression and release-artifact coverage

- Goal: make local tests catch the asset-contract failures that preceded the live incident.
- Dependencies: Stage 1 runtime contract and Stage 2 canary result format.
- Expected changes: extend tests for raw cached-asset compatibility, emitted fingerprinted v5/v6 paths, complete/shared static releases, loader failure without a username prompt, and concurrent preparation deduplication.
- Verification approach: targeted PHP/Node test run plus a clean static-release build that resolves every declared runtime URL.
- Risks or open questions:
  - Local loopback is a secure context and cannot substitute for the public-HTTP browser canary.
- Canonical components/API contracts touched: `FrontController`; `StaticArtifactBuilder`; `TemplateRenderer`; OpenPGP loader and signing assets; existing test runner.

## Stage 4 — Manual operator handoff

- Goal: make safe manual invocation and diagnosis obvious after every release.
- Dependencies: Stages 1–3.
- Expected changes: document the exact read-only command, explicit canary command, expected visible post, pass/fail meanings, and no-rerun guidance after an ambiguous production post.
- Verification approach: command help/output assertions and runbook review against the production checklist.
- Risks or open questions:
  - Operators may mistake the canary for automatic deployment work.
- Canonical components/API contracts touched: production deployment runbook; OpenPGP recovery checklist; `v3` command help.

Reply **Approved Step 3** to begin implementation.
