> **Feature plan:** [Step 1](./local_pull_simple_step1_solution_assessment.md) · [Step 2](./local_pull_simple_step2_feature_description.md) · [Step 3](./local_pull_simple_step3_development_plan.md) · [Step 4](./local_pull_simple_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_simple`; [cycle ledger](../effort_ledger/local_pull_simple.md). Standing P2 enrollment; parent `v3/private_message_local_pull`.

Extended estimates: enabled

**Cycle estimate: 0.85**

Branch `feature/local_pull_simple` from `origin/main` `fd500820`. Planning commit `9457c988`.

## Step 4 Entry

2026-11-27T21:00Z: Step 3 projection reaffirmed, **0.85 projected total**. Stage estimates copied from Step 3: 0.20, 0.15, 0.25, 0.15, 0.15, 0.10. Claude, single exposed rater.

## Stage 1 - Key handoff on zenmemes.com

**Original estimate: 0.20** (unchanged)

- Changes:
  - New route `/account/key/share?to=<origin>` in `Application`: `GET`/`HEAD`, allowed in members-only mode like `/account/key/`, and listed among the application routes. It is served by `ComposeAndAccountKeyController::accountKeyShare()`.
  - The server accepts `to` only if it matches `^http://(127\.0\.0\.1|localhost|\[::1\]):[0-9]{1,5}$`, and otherwise renders "This link has no valid local address."
  - New `templates/pages/account_key_share.php` and `public/assets/key_handoff.js`. The script checks the target pattern again and requires `window.opener`. It shows the key's username and short fingerprint, and **Use it there** posts `{type: 'forum-key-handoff', version: 1, privateKey, fingerprint, username}` to the opener with `targetOrigin` set to the target, then closes. The refusals (bad target, no opener) are a short message plus closed Details. A browser without a key gets a link to Account.
- Verification:
  - New `node tests/browser/key_handoff.mjs`: passed. It uses a real instance and two plain loopback "local copy" pages, and checks:
    - nothing arrives before the click;
    - after the click, exactly one message arrives from the source origin with the right key, and the popup closes;
    - `?to=` naming the other local page delivers nothing to the opener;
    - `https://127.0.0.1`, `http://evil.example`, `http://127.0.0.1.evil.example` and `javascript:` targets show no button;
    - opened without an opener: refused with Details naming `key handoff: opener`;
    - no key in the browser: an Account link, nothing sent.
  - Smoke set: 497 run, 497 passed. The full suite was not run.
  - Screenshot at the popup size checked, and the heading copy trimmed.
- Notes:
  - Retrospective stage-work assessment: **0.20**.
