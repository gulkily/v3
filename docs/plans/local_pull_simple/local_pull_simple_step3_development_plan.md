> **Feature plan:** [Step 1](./local_pull_simple_step1_solution_assessment.md) · [Step 2](./local_pull_simple_step2_feature_description.md) · [Step 3](./local_pull_simple_step3_development_plan.md) · [Step 4](./local_pull_simple_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_simple`; [cycle ledger](../effort_ledger/local_pull_simple.md). Standing P2 enrollment; parent `v3/private_message_local_pull`.

Extended estimates: enabled

**Cycle estimate: 0.85**

## Completion Contract

- **Normal entry:** `/local/` from `./v3 start`, in the browser where you use zenmemes.com.
- **End-to-end outcome:** press **Connect to zenmemes.com**, then **Use it there** in the popup. Import, trust, pull and saved copies follow without further clicks. Messages are readable on the first load and on reloads. **Pull** fetches new mail.
- **Required recovery:** each case in Step 2 is covered: popup blocked, closed or unanswered; signed out or no key on zenmemes.com; a different key in this browser; import, trust or pull failing. A reload in the middle of Connect resumes it.
- **Deployment:** the handoff page must be deployed to zenmemes.com before Connect works against production. The local side ships in the same PR.
- **Release condition:** Stage 6 replays on Ilya's laptop, in Firefox, against production.

## Key Risks

- **High risk: security.** The key could reach a page that isn't yours. Validation in Stage 1, before anything depends on it:
  - browser tests from a non-loopback opener, an https opener and a different port;
  - a test that nothing is sent without a click.

  Mitigation: loopback-only target pattern, an exact `targetOrigin`, sending only to `window.opener`, and a click required.
- **The import reloads the page.** `import_control.js` reloads `/local/` when the import ends, which would cut Connect short. Mitigation: Connect keeps its stage in `sessionStorage` and resumes on load. Validated in Stage 3.
- **Firefox popup behavior.** Validated in Stage 2 with Firefox in the browser check, if available; otherwise in the Stage 6 replay, with the gap recorded.
- **Rollback:** the handoff page is additive. Old local flows are removed in the same PR, so reverting the PR restores them.

## Stage 1

**Estimate: 0.20**

- Goal: zenmemes.com offers a confirmed, loopback-only key handoff.
- Dependencies: none.
- Expected changes:
  - New route `/account/key/share?to=<origin>` in `Application`, rendering `templates/pages/account_key_share.php`.
  - New script `public/assets/key_handoff.js`. It validates `to` against `^http://(127\.0\.0\.1|localhost|\[::1\]):[0-9]{1,5}$` and needs `window.opener`. It shows the username and short fingerprint with **Use it there**. On click it posts `{type: 'forum-key-handoff', version: 1, privateKey, fingerprint, username}` with `targetOrigin = to`, then closes.
  - Refusals are short messages with Details: no key here (link to Account), a bad target, or no opener.
- Verification approach:
  - PHP route test.
  - New `tests/browser/key_handoff.mjs` with real pages on two loopback servers. It checks:
    - an accepted loopback opener;
    - refusals for an https `to`, a non-loopback `to` and a missing opener;
    - that no message arrives before the click.
- Estimate details: driver: the refusal matrix; uncertainty: low.
- Risks or open questions:
  - Impact: key exposure. Early warning: the refusal tests. Mitigation: the checks above.
- Canonical components/API contracts touched: `/account/key/` page styles, `browser_signing.js` (reads the stored key), `TemplateRenderer` scripts.

## Stage 2

**Estimate: 0.15**

- Goal: `/local/` receives and installs the key.
- Dependencies: Stage 1.
- Expected changes:
  - Expose `importPrivateKey(text, fingerprint)` on `__forumBrowserIdentity`, sharing `importAccountPrivateKey()`'s core. Load `browser_signing.js` on `/local/`.
  - **Connect** opens the popup on click. It accepts one message whose `event.origin` is the source and whose `event.source` is the popup, with a 2-minute time limit.
  - When the popup is blocked, a link with `rel="opener"` is offered instead.
  - A different existing key gets an explicit **Replace** prompt that names both keys.
- Verification approach: extend `key_handoff.mjs`: install, replace prompt, wrong-origin message ignored, timeout message.
- Estimate details: driver: the opener link fallback; uncertainty: Firefox.
- Risks or open questions: covered under Key Risks.
- Canonical components/API contracts touched: `browser_signing.js`, `LocalDevelopmentPageController` scripts, `local_message_pull.js`.

## Stage 3

**Estimate: 0.25**

- Goal: Connect runs import, trust, pull and saved copies in one pass, resumable.
- Dependencies: Stage 2.
- Expected changes:
  - `local_message_pull.js` drives `/local/`'s existing JSON import (`import`, then `status`) with its own progress line.
  - Its stage is kept in `sessionStorage` and resumed after `import_control.js`'s reload.
  - After the import: `trust_candidates`, then `trust` per key, with no list shown. Then the pull.
  - New relay action `saved_copies` calls `LocalWriteService::setFeatureFlag(FORUM_PRIVATE_MESSAGE_CACHE_ENABLED, true)`. An environment override is reported, not forced.
- Verification approach: PHP test for `saved_copies`, including the environment override. The browser check comes in Stage 5.
- Estimate details: driver: resuming across the reload; uncertainty: the timing of the import task.
- Risks or open questions:
  - Impact: Connect stops halfway. Early warning: a reload test. Mitigation: resume from `sessionStorage`; each stage is safe to repeat.
- Canonical components/API contracts touched: the `/local/` import JSON (unchanged), `trust_candidates` and `trust` (unchanged), `setFeatureFlag()`.

## Stage 4

**Estimate: 0.15**

- Goal: retire the old steps.
- Dependencies: Stage 3.
- Expected changes:
  - Remove from the card: register, approve, the import step text, the trust list, verify and confirm.
  - Remove relay actions `prepare_identity`, `create_identity`, `verify_list` and `confirm`, with their `PATHS`.
  - `LocalMessagePull` stops importing transfers.
  - Update tests that covered the removed actions.
  - Rewrite the concept entry. Note the handoff in `key_backups.md`. Create a *key handoff* concept.
- Verification approach: smoke set plus `LocalDevelopmentImportTest` and `LocalMessagePullTest`. `git grep` finds no leftovers. Link check.
- Estimate details: driver: test rewrites; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: the relay, `LocalMessagePull`, concepts.

## Stage 5

**Estimate: 0.15**

- Goal: the two-instance browser check covers the new flow.
- Dependencies: Stages 1–4.
- Expected changes: rewrite `tests/browser/local_message_pull.mjs`. The source browser context holds Alice's key; the local context has no key.
  1. Press Connect, then **Use it there** in the popup.
  2. Wait until done; the import's page reload falls in the middle.
  3. Messages are readable, and stay readable on three reloads from saved copies.
  4. **Pull** adds 0.
  5. The relay log never holds a private key.

  Also take screenshots at 390 and 1100 px.
- Verification approach: the check itself, plus `local_development.py`.
- Estimate details: driver: the reload in the middle; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: none new.

## Stage 6

**Estimate: 0.10**

- Goal: release and replay.
- Dependencies: merge and deploy.
- Expected changes: none, unless the replay finds a defect.
- Verification approach: on Ilya's laptop in Firefox, with a fresh clone against production:
  - Connect, then Messages readable; three reloads.
  - Pull again adds 0.
  - His existing clone shows the Replace prompt.
  - Close the popup without choosing, and get the timeout message.
- Estimate details: driver: Ilya's time.
- Risks or open questions: none.
- Canonical components/API contracts touched: none.

## Concepts Touched

[Local development import](../../concepts/local_development_import.md) (rewritten), [browser key backups](../../reference/key_backups.md) (note), *key handoff* (created in Stage 4).

## Study Checkpoint

2026-11-27T22:00Z; scope v2; starting revision `1fc53a90`; R1; P2 standing enrollment. Claude, single exposed rater.

- Step 3 handoff: **0.85 projected total**, up from 0.80. Reason: new understanding. `import_control.js` reloads the page when the import ends, so Connect needs a resumable stage. Stage estimates are not additive. No approval to advance.
- Ledger update pending, with the planning commit.

Status: Step 2 approved by the maintainer ("approved step 2", 2026-11-27T20:19Z). Step 3 approved ("approved step 3", 2026-11-27T20:56Z).

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
