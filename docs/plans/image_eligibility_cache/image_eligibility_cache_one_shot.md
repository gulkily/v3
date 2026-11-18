# Attach image shows right away when the app resumes

Cycle: `v3/image_eligibility_cache`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/image_eligibility_cache.md)

Extended estimates: disabled (no real alternatives or work units)

**Cycle estimate: 0.05 (intake)**

Status: implemented and verified locally; PR open for review. Real-device iPhone confirmation and deployment are the maintainer's.

## Original request

2026-11-18, ilyag in chat: "On iPhone whenever I open my owa after some time away from it and go to new post, the attach image option is not immediately available, but appears later". After Claude traced it to the eligibility check and suggested remembering the last answer: "Please oneshot pr".

Feedback items: none match (searched `docs/feedback/items/` for attach, image upload, eligibility; PF-20261111-B32 is about slow posting with an image, not the menu item).

## Authorization

"Please oneshot pr" (2026-11-18) authorizes this one-shot through an open PR. Exclusions: server-rendered eligibility, any change to what the server allows, merge and deployment.

## Outcome and work type

Application change, `public/assets/image_upload.js` plus one new browser test. After a successful check, the browser remembers which identity may attach images. On the next page load or app resume, Attach image shows as soon as the identity is known, and the usual `/api/get_profile` check runs in the background. A "no" answer hides the button and forgets the identity.

## Approach and risks

- Cause: `refreshEligibility()` keeps `__imageEligible` false until a `cache: 'no-store'` fetch of `/api/get_profile` returns (8 s timeout). After iOS suspends the home-screen app, that request waits for the radio and a new TLS connection, so the menu item appears seconds later.
- Fix: a localStorage entry `forum_image_eligible_identity` holds the identity last confirmed allowed. When the identity read back matches it, show eligibility before the fetch. On a "no" answer, remove the entry; on a "yes", write it. A network error keeps the entry but hides the button as today (an upload would fail anyway).
- The key must not start with `forum_pki_`, or other tabs' `storage` listeners would re-check on every write.
- Risk: the button shows for a key that has lost upload rights since the last visit. It is hidden again when the check answers, and `assertEligible()` re-checks before any upload, so nothing is sent. Uploads stay server-enforced.
- localStorage access is wrapped in try/catch.

## Reuse decisions

- Reuses `refreshEligibility()`, `displayEligibility()` and the existing `focus`/`visibilitychange`/`online` re-checks; no new events or endpoints.
- The test follows `tests/browser/image_photo_details.mjs` (partial loaded from the template, Playwright with system Chrome).

## Completion conditions

Normal entry: opening New post (or a reply) in a session where this key was confirmed allowed before. Result: Attach image is offered before the profile request answers. Recovery: an ineligible answer hides it and clears the memory; the next load waits for the check as today.

Concepts touched: none.

## Verification and recovery plan

New `tests/browser/image_upload_eligibility.mjs` with a delayed `/api/get_profile`: no memory → hidden until the answer; remembered identity → shown before the answer; "Approved: no" → hidden and memory cleared; different identity → not shown early. Re-run `image_photo_details.mjs`. `./v3 test --smoke`. Full suite not run (maintainer runs it). The iPhone resume condition itself cannot be replayed here; the delayed response stands in for it and real-device confirmation is the maintainer's.

Starting revisions and branch: `origin/main` `df0ac8c5`; branch `feat/image-eligibility-cache`.

Planning commit: `6947166` (record and index only; confirmed with `git log` before implementation).

## Dated execution history

- 2026-11-18: record written; planning commit `6947166`.
- 2026-11-18: `image_upload.js`: added `remembered()`/`remember()` around `forum_image_eligible_identity`; `refreshEligibility()` shows eligibility before the fetch when the identity matches, writes it on a yes, and clears it on an OK "no" response (a failed request keeps it). As planned; no corrections.
- 2026-11-18: added `tests/browser/image_upload_eligibility.mjs`. Confirmed it fails against the old `image_upload.js` (nothing remembered) and passes with the change.

## Study history

- 2026-11-18 intake: **0.05**, scope v1, starting revision `df0ac8c5`. Not pristine (the rater traced the cause first). Single exposed Claude rater.
- 2026-11-18 execution entry after planning commit `6947166`: 0.05 reaffirmed.
- 2026-11-18 closeout: delivered scope v1; retrospective **0.05**. Maintainer fit and acceptance pending.

## Outcome

Delivered as recorded: a key confirmed allowed is offered Attach image as soon as the page knows the identity; the check still runs and a "no" hides it and forgets the key. Not delivered (by authorization): server-rendered eligibility.

## Verification

- Passed: `node tests/browser/image_upload_eligibility.mjs` (new; first visit waits, a confirmed key is offered before the answer, another key is not, "no" hides and forgets, the next visit waits again). Fails on the old code.
- Passed: `node tests/browser/image_photo_details.mjs`, `image_prepare.mjs`, `image_compose_transport.cjs`.
- Passed: `./v3 test --smoke` (478 run, 478 passed).
- Not run: the full suite (maintainer runs it). Not replayed: an actual iPhone home-screen app resumed after time away; the test's held `/api/get_profile` response stands in for the slow request.

## Final review

Diff checked: one JS file, one new test, this record, the index, the ledger and the debug log. No new component; no UI text added or changed.

## Commit/merge/publication status

Implementation and this record committed together on `feat/image-eligibility-cache` (hash in the PR); pushed; PR open. Not merged or deployed.

_Claude Opus 5.5, 2026-11-18_
