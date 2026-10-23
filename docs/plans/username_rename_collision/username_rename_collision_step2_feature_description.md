> **Feature plan:** [Step 1](./username_rename_collision_step1_solution_assessment.md) · [Step 2](./username_rename_collision_step2_feature_description.md) · [Step 3](./username_rename_collision_step3_development_plan.md) · [Step 4](./username_rename_collision_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/username_rename_collision`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3username_rename_collision).

## Problem

Choose username saves any well-formed name. A rename into a name that someone else holds therefore joins their composite user without consent. It gains that name's new private messages and its "same username" approval authority. A member's own second key also has no sanctioned way to join their existing name.

## User stories

- As a member who started as `guest`, I want to choose my own name so that I'm known by it. This already works when the name is free and must keep working.
- As a member with a second device, I want that key to ask to join my existing name so that both keys act as one user.
- As the holder of a name, I want to accept or decline join requests so that nobody joins my user without consent.
- As an operator, I want to accept a join request, or grant one in advance, so that I can help members who have lost access to their other key.

## Core requirements

- Renaming to a name no other profile holds saves immediately, as today. `guest` is shared by design and always counts as free.
- Renaming into a held name never saves directly. The signed rename is held as a pending join request, and the requester's name stays unchanged.
- An approved holder of that name, or an operator, can accept or decline. Accepting signs a grant scoped to that one key and name. The grant and the held rename are saved together, and the requester does nothing more. A grant can't be reused.
- Requests expire after 7 days, and the requester can cancel. Each key has at most one pending request; a new request replaces the old one.
- Every check happens on the server when records are written, under the existing write lock. Approval rules, keys and identity IDs are unchanged, and the whole feature stays behind `FORUM_USERNAME_CHANGES_ENABLED`.

## Delivery scope

**Work type:** Application change. Out of scope: private-message history after a rename, squatting a vacated name, and Options B and C.

## Completion boundary

**Entry:** Account → Choose username, with the flag on. **Outcome:** free names save at once. A held name shows as requested, and the holder's Account lists the request; after acceptance, the requester's name has changed. **Recovery:** decline, expiry or cancel leaves the requester's name as it was, and the requester can request again. A server error before saving leaves nothing half-written. **Release:** automated and two-key browser checks pass, and the flag still ships off.

## Known user

The maintainer, who has their main key named `ilya` and a second browser or device that started as `guest`. Ordinary entry is Choose username on the second key. Failure to handle: the request is silently lost, or the name changes without acceptance. The next step they can take unaided is accepting from the `ilya` key's Account page. To verify, they see the new name on the second key's profile and on `/user/ilya` listing both profiles. Evidence: a two-browser run on this WSL setup.

## Risks

- **New canonical grant record:** it needs a parser and must not change approval derivation or the read-model schema. Earliest validation: a full rebuild with a grant present gives identical approval state. Mitigation: Step 3 makes the grant an audit record that the read model ignores.
- **Who counts as holder when the name has only pending holders:** proposal: such a name counts as held, and only an operator can accept. Confirm now.
- **Request storage:** requests are server-side private state, not canonical records, so a data reset loses them. Mitigation: losing a request is harmless, because the requester asks again.

## Shared component inventory

- Account UI: extend `templates/pages/account_key.php` (Choose username, plus a join-requests list) and the rename handler in `browser_signing.js`.
- Write path: extend the existing prepare/finalize endpoints in `LocalWriteService` and `WritePostAndIdentityApiController`. Reuse the prepared-record store with a longer expiry for held requests.
- Trust rule: reuse `ApprovalPolicy`'s holder/operator classification for who may accept; no parallel policy.
- Signing: reuse the existing detached-signature flow used by approvals and renames.
- Docs: `profile_read_contract_v1.md` (Composite User), `invitation_entry.md` (choosing a username).

## Simple user flow

1. On the second key, the member chooses `ilya` and signs. The page says a request was sent to `ilya`.
2. On the `ilya` key, Account lists the request, and the member accepts and signs.
3. The second key is now `ilya`, and `/user/ilya` lists both profiles.

## Success criteria

- In tests, a rename into a held name never changes a username without an accepted grant, including through direct endpoint calls.
- A free-name rename, including `guest` → a name, behaves exactly as before.
- Accept, decline, expiry, cancel and replacement each end in the stated state in tests. The two-browser journey passes.
- A full rebuild before and after a grant gives the same approval state.

## Study checkpoint

2026-10-23 UTC; scope v3 (Step 1 v3 approved); start `8a2d59c1`; Claude (Opus 5.5), single exposed rater; R1/P2, with hashes verified as recorded in Step 1. Step 2 handoff: **0.65 reaffirmed**. The defined outcome and recovery match the approved Option D; the new detail (7-day expiry, one request per key, operator-only acceptance for names with only pending holders) doesn't change the size. Not measured effort.

## Enhancement Requests

Carried from Step 1: request-first join flow and `guest` → own name, both in scope and approved with Step 1.

Status: Step 2 drafted for review. Decisions to confirm: 7-day expiry; names held only by pending profiles need an operator to accept.
