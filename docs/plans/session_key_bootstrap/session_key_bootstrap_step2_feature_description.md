> **Feature plan:** [Step 1](./session_key_bootstrap_step1_solution_assessment.md) · [Step 2](./session_key_bootstrap_step2_feature_description.md) · [Step 3](./session_key_bootstrap_step3_development_plan.md) · [Step 4](./session_key_bootstrap_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/session_key_bootstrap`; [host ledger](../effort_estimation_trial.md#cycle-v3session_key_bootstrap). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.70

# Step 2: Feature Description — Session key bootstrap

Step 1 approved 2026-11-16 (Option A with the lost-key notice). Step 2 approved 2026-11-16.

## Problem

A browser can lose its key while keeping a live session. Today it quietly becomes a guest, and the member must restore a backup or ask for a fresh approval.

## Decisions on the Step 1 questions

Ilya answered these on 2026-11-16:

1. **Standing.** A bootstrapped key gets the member powers of the lost key. It never gets root, by any path. An operator can grant root later with Make root.
2. **Mismatch.** A clear fingerprint mismatch leaves the new key pending. Another of the member's keys, or an operator, approves it with the existing tools.
3. **Lost key.** The lost key stays approved. It is marked "replaced" and waits in a queue, where the member or an operator retires it or keeps it.
4. **Fingerprint.** Coarse traits only: browser family, OS family and device class. The site's anti-cookie notice says so in one plain line. A thorough fingerprint is deferred (see Enhancement Requests).

## User stories

- As a member on iPhone Safari, I want a browser that lost its key to keep me signed in as a member, so that I can keep posting without doing anything.
- As a member, I want to see that this browser got a new key and to save a backup, so that the next loss costs less.
- As a member or operator, I want replaced keys listed in one queue, so that I can retire old keys when I'm sure they're gone.
- As an operator, I want a bootstrapped approval to be a visible record I can retract, so that a stolen session's key can be undone.
- As an operator, I want feature flags for the bootstrap and for HTTP sessions, so that each instance picks its own trade-off.

## Core requirements

- A page load with a live session, no key, and the flag on makes a new key under the session's username. The server approves it from the session, at most once per session.
- HTTPS sessions qualify. HTTP sessions qualify only with their own flag on.
- The session stores a coarse fingerprint. A clear mismatch leaves the new key pending; a match grants nothing the session would not.
- The bootstrapped approval is an ordinary visible record. It survives a read-model rebuild, and retracting it removes the key's membership. It never carries root.
- The lost key keeps its approval, shows "replaced by" the new key, and joins the replaced-keys queue. Wherever a Pending pill can appear, the lost key shows a "Replaced" pill instead.

## Delivery scope

- Work type: application change.
- Out of scope: server key copies (Step 1 Options B and C), the thorough fingerprint, the script-free sign-in form, automatic retirement of replaced keys.

## Completion boundary

- Normal entry: any page load in a browser with a session and no key.
- End-to-end outcome: one notice, then the member posts as an approved member.
- Recovery: on a mismatch, the notice says the key waits for approval and where to get it. With the flag off, or over HTTP without the HTTP flag, behaviour stays as today.
- Release condition: both flags exist in the registry; the iPhone Safari replay below passes in production.

## Known user and environment

- User: a member on iPhone Safari whose script-written storage was deleted while the session cookie survived.
- Entry: opening any ZenMemes page in Safari.
- Failure to handle: the page finds a session but no key.
- Next action unaided: none on a match. On a mismatch, the member waits, or approves the key from another device.
- Verify or retry: the notice names the new key, and the member's next post shows as approved. Reloading never mints a second key.
- Evidence: a replay on a real iPhone in Safari, with the key removed and the cookie kept. Desktop Safari or a simulator does not count.

## Risks

- **A stolen session mints a lasting key.** Impact: an impostor member. Validation: check the limits in Step 3 tests. Mitigation: HTTPS, the fingerprint, one bootstrap per session, no root, and a retractable record.
- **A server-made approval breaks rebuilds.** Impact: keys lose or gain membership after a rebuild. Validation: replay a bootstrapped approval through a full rebuild early in Step 3. Mitigation: store it as a repository record beside the existing approval records.
- **PR #274 is still open.** Impact: the member gates may still trust the `identity_hint` cookie. Validation: check its state when Step 3 starts. Mitigation: plan Step 3 on `main` after #274 merges, or rebase onto it.
- **The fingerprint needs new session data.** Impact: a change to the private session store. Validation: Step 3 confirms the store lives in `state/private/` and needs no read-model schema change. Mitigation: sessions with no fingerprint count as a mismatch.

## Shared component inventory

- Sessions: `ViewerSessionStore.php`, `ViewerSessions.php` and `ViewerSessionCookie.php`. Extend them to hold the fingerprint and the bootstrap-used mark.
- Key creation and storage in the browser: `browser_signing.js` and `private_site_auth.js`. Reuse them; the bootstrap only adds the trigger and the call to the server.
- Approval: `ApprovalPolicy.php`, `ApprovalAcceptance.php` and the approval record spec. Extend them with a session source; keep the same-username and root rules.
- Pending keys: `/tools/user-access/pending/` and `paned_user_pending_detail_pane.php`. Reuse them for mismatched keys.
- Retirement: `KeyRetirement.php`, `KeyRetirementPolicy.php` and `templates/partials/key_retirement.php`. Reuse them from the replaced-keys queue.
- Key pages: `public_key.php` and `profile_access.php`. Extend them with the "replaced" and "bootstrapped" marks.
- Status pill: the `chip` component, with its `chip--pending` and `chip--approved` variants, as rendered by `TemplateRenderer.php` and `pending_user_summary.js`. Add a "Replaced" variant beside them rather than a new pill.
- Backup: `key_backup_ui.js` and `key_backup_status.js`. Reuse their prompt in the notice.
- Flags: `FeatureFlagRegistry.php`. Add the two new flags there.
- Notice: reuse an existing page notice. Step 3 names the component.

## User flow

1. The member opens a ZenMemes page. The browser has a session but no key.
2. The page makes a key and sends its public half with the session.
3. The server checks the flags, the scheme and the fingerprint.
4. On a match, the server approves the key and marks the old key replaced. On a mismatch, the key stays pending.
5. The member sees one notice: "This browser lost its key and has a new one", with a backup offer. On a mismatch, the notice says the key waits for approval.
6. Later, the member or an operator retires the old key or keeps it from the replaced-keys queue.

## Success criteria

- On iPhone Safari with the key removed, the member posts as approved after one page load and one notice.
- A full rebuild keeps the bootstrapped approval, and retracting it removes membership.
- A second load in the same session never makes a second key.
- A mismatched fingerprint, or HTTP without its flag, never yields an approved key.
- A bootstrapped key never holds root.

## Concepts touched

- [Viewer session](../../concepts/viewer_session.md): changed (fingerprint, one bootstrap per session).
- [Approval acceptance](../../concepts/approval_acceptance.md): changed (a session-sourced approval).
- [Key retirement](../../concepts/key_retirement.md): used unchanged, from the queue.
- Replaced key (proposed): an approved key marked as replaced, waiting in a queue.
- Feature flags: changed (two new flags).

## Enhancement Requests

- 2026-11-15, Ilya: script-free browsers must not be blocked. A plain sign-in form is its own cycle. Disposition: separate work; not started.
- 2026-11-16, Ilya: put lost keys in a queue for later retirement. Disposition: in scope, requirement 5.
- 2026-11-16, Ilya: later, add a thorough fingerprint as an option each member can choose. Disposition: separate work, after this cycle. Approval: pending.
- 2026-11-16, Ilya: the lost key gets a pill like Pending, with its own word. Disposition: in scope, requirement 5; word "Replaced". Approval: pending with this step.
- 2026-11-16, Ilya: many pages still show "awaiting approval" text where a Pending pill belongs. Disposition: separate work, logged for a later UI copy cycle and tracked in [a ZenMemes thread](https://zenmemes.com/threads/thread-20261116154733-d2e0f837). Places found so far:
  - `templates/pages/account_key.php`: the pill reads "Waiting for approval".
  - `templates/partials/paned_user_pending_detail_pane.php`: "Pending approval" as plain text.
  - `templates/pages/users_pending.php`: the heading "Users Awaiting Approval".
  - `templates/pages/private_message_access.php`: "You're waiting for approval as …".
  - `src/ForumRewrite/Application.php`: the page "Your access is pending approval …".
  - To check: `templates/partials/users_tabs.php` ("Waiting") and `templates/pages/agent.php` ("Waiting for approval", for prompts, not keys).

## Study history

- Step 1 snapshot, 2026-11-16 UTC: **0.60** (preserved in Step 1).
- Step 2 snapshot, 2026-11-16 UTC: **0.70**. Scope v2: adds the replaced-keys queue, the pending path for a mismatch and the disclosure line. Same scope, more defined; not new work. Reference R1, protocol P2, starting revision `9bdd4b3`. R1 hashes not re-verified. Single exposed rater: Claude.

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-16 UTC · Model: claude-opus-5-5* (Replaced pill; "awaiting approval" log)
