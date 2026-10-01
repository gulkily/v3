# Live OpenPGP Smoke Test — Step 1 Solution Assessment

## Problem

The local suite verified loader selection but did not verify that the selected
OpenPGP asset was actually reachable on the live HTTP or HTTPS origin.

## Options

### Option A — Fixed-URL HTTP probe

- Fetch the known HTTP v5 and HTTPS v6 bundle URLs after deployment.
- Pros: smallest possible check; fast; no browser dependency.
- Cons: can drift from emitted fingerprinted URLs; does not verify the page's
  loader/runtime contract.

### Option B — Contract-aware live smoke command

- Fetch an identity-capable page on each origin, read its emitted runtime asset
  contract, and fetch the selected bundle; also retain a raw-URL compatibility
  probe for old cached pages.
- Pros: catches wrong version selection, missing deployment assets, redirects,
  non-JavaScript error pages, and fingerprint/runtime drift; suitable for a
  post-deploy gate and cron monitoring.
- Cons: requires outbound network access from the command runner; does not
  create or sign an identity.

### Option C — Browser end-to-end production canary

- Drive a fresh browser through identity setup and a visible “new release just
  dropped, making sure it works” post on production.
- Pros: verifies prompting, key persistence, signing, and real posting while
  adding an intentional release announcement to the community.
- Cons: creates durable production identity/post records; needs an explicit
  production-write opt-in and browser infrastructure.

## Recommendation

Choose **Option B + Option C**. Run the read-only contract check first on every
release, then run the explicitly authorized production canary post. This would
have caught the asset incident and proves the complete new-user posting path
without requiring a staging environment.

## Additional regression coverage

- Keep the in-repo checks for raw-asset fallback, emitted fingerprinted runtime
  paths, and clean/static-shared release asset presence.
- Keep failure-path checks: a missing bundle must show zero username prompts,
  preserve a draft, and offer anonymous posting.
- Keep concurrent identity-preparation coverage: two callers produce one prompt
  and one keypair.
- Add a cached-client compatibility check: an old raw loader request still
  resolves after a deploy.

Reply **Approved Step 1** to proceed to the feature description.
