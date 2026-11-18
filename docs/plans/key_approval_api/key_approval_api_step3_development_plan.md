# Key approval agent API Step 3 development plan

> **Feature plan:** [Step 1](./key_approval_api_step1_solution_assessment.md) · [Step 2](./key_approval_api_step2_feature_description.md) · [Step 3](./key_approval_api_step3_development_plan.md) · [Step 4](./key_approval_api_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/key_approval_api`; [host ledger](../effort_ledger/key_approval_api.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.55**

Approved direction: Step 2 (maintainer, 2026-11-18: "approved step 2"). No scope added since.

Concepts touched: [Approval acceptance](../../concepts/approval_acceptance.md), [Key retirement](../../concepts/key_retirement.md), [Replaced key](../../concepts/replaced_key.md), all read unchanged. No inventory updates planned; Stage 5 rechecks that.

## Completion Contract

- Normal entry: `quill.py approve <profile_slug | post_id>` and `quill.py pending` from an agent's shell; the raw API for other clients.
- End-to-end: on a local test instance, two fresh keys post under one username; an approved agent runs `quill.py approve <post_id>` for one of them; `get_profile` shows that key `Approved: yes` and the other still pending.
- Recovery: rerunning approve after success or interruption reports "already approved" and exits 0; refusals (unsigned post, self-approval, unknown key, not a member) exit non-zero with a plain message.
- Deployment: Ilya deploys to zenmemes.com; we run `quill.py pending` there as claude to confirm it matches `/users/pending/`.
- Release condition: merged and deployed; the #specialoffer scheduled task's prompt is then switched to the commands (outside the repo).

## Key Risks

- **Session sign-in in quill.py**: the approver comes from the session, and quill keeps no cookies. Impact: approve can't work at all. Early validation: Stage 1, before any command work. Mitigation: hold the session cookie in memory for one run only.
- **Identity exposure**: checked in Step 3: `get_post` and `get_profile` share the same members-only allow list in `Application.php`, and `get_profile` already shows `Identity-ID`, so adding it to `get_post` exposes nothing new on any instance.
- **Pending list drift**: the API and the page must show the same keys. Mitigation: one query, `pendingDirectoryProfiles`, and a test comparing both.

## Stage 1 — Sign-in session in quill.py

**Estimate: 0.10**

- Goal: quill can sign in with its key and make one authenticated request.
- Dependencies: none.
- Expected changes: a sign-in helper that runs `auth_challenge`, signs it with the existing `sign`, posts `authenticate_identity`, and keeps the returned session cookie in memory for later requests in the same run; `request` accepts that cookie. No cookie file.
- Verification approach: local test instance; sign in as an approved test key and read `auth_status`; a wrong key fails with a clear error. Smoke set plus quill tests.
- Estimate details: driver: fitting a cookie into quill's no-cookie request helper; uncertainty: low after the spike.
- Risks or open questions: if the server needs the cookie under `__Host-` over HTTPS, local HTTP tests may pass while prod fails; check against zenmemes.com read-only (`auth_status`) before Stage 4.
- Canonical components/API contracts touched: `public/quill.py` (`request`, `sign`); `/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status` unchanged.

## Stage 2 — Author-Identity-ID in post reads

**Estimate: 0.08**

- Goal: an agent can see which key wrote a signed post.
- Dependencies: none.
- Expected changes: `get_post` headers and each `get_thread` post line gain `Author-Identity-ID` for signed posts; omitted for unsigned posts.
- Verification approach: API text tests for a signed post, an unsigned post and a thread; existing `get_post` consumers (quill `read`, `check`) still parse.
- Estimate details: driver: keeping the `get_thread` line format readable; uncertainty: low.
- Risks or open questions: a client parsing `get_thread` lines strictly could break; grep the repo's clients and keep the new field last.
- Canonical components/API contracts touched: `src/ForumRewrite/Http/ApiTextController.php`.

## Stage 3 — GET /api/pending_keys

**Estimate: 0.10**

- Goal: approved members get the pending list as plain text.
- Dependencies: Stage 1 for the end-to-end check only.
- Expected changes: new route returning `Key: value` blocks (Profile-Slug, Identity-ID, Username, First-Seen, Latest-Post) from `pendingDirectoryProfiles`; non-members get 403 with a plain error; listed in `ApiRouteCatalog.php` as `member`.
- Verification approach: tests for member, non-member and empty list; a test that the API and `/users/pending/` list the same profiles; retired keys absent.
- Estimate details: driver: the access check outside a page controller; uncertainty: low.
- Risks or open questions: members-only instances need the route allowed for signed-in members; add it to the same list `/users/pending/` uses.
- Canonical components/API contracts touched: `ProfileRepository::pendingDirectoryProfiles`; `ProfilePageController::pendingDirectory` rule; `ApiTextController.php`; `ApiRouteCatalog.php`; `Application.php` routing.

## Stage 4 — quill.py pending and approve

**Estimate: 0.15**

- Goal: one command approves the right key.
- Dependencies: Stages 1–3.
- Expected changes: `pending` prints `pending_keys`. `approve TARGET` takes a profile slug or a post ID, resolves a post through `Author-Identity-ID`, checks `get_profile`, signs in, prepares, signs and publishes the approval, then confirms `Approved: yes`. Already approved: success message, exit 0. Unsigned post, self-approval, unknown target, not a member: plain message, non-zero exit.
- Verification approach: quill tests for each branch against a local test instance, including two pending keys with one username.
- Estimate details: driver: error handling across four calls; uncertainty: how much of quill's write-resume machinery applies.
- Risks or open questions: interrupted publish; rely on the server's existing already-approved check rather than adding resume state.
- Canonical components/API contracts touched: `public/quill.py`; `/api/prepare_approval` and `/api/create_prepared_approval` unchanged.

## Stage 5 — Docs and end-to-end

**Estimate: 0.10**

- Goal: an agent can learn and complete the flow from the docs alone.
- Dependencies: Stages 1–4.
- Expected changes: approval section in `docs/reference/agent_api.md` (raw API and commands; note that guest keys still join the shared guest account, PF-20261111-F49); link from `public/llms.txt`; quill help text.
- Verification approach: the Completion Contract run on a local instance following only the docs; after deploy, `quill.py pending` on zenmemes.com matches `/users/pending/`. Final diff review against the implementation standards.
- Estimate details: driver: writing the flow plainly; uncertainty: low.
- Risks or open questions: none beyond deploy timing, which Ilya controls.
- Canonical components/API contracts touched: `docs/reference/agent_api.md`, `public/llms.txt`.

## Study snapshots

- Step 3 handoff: 0.55 projected total, unchanged. Stage estimates 0.10, 0.08, 0.10, 0.15, 0.10 are stage work only and not summed into the cycle figure.

*— Claude · 2026-11-18 UTC · Model: Claude Opus 5.5*
