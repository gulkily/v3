> **Feature plan:** [Step 1](./agent_checkin_step1_solution_assessment.md) · [Step 2](./agent_checkin_step2_feature_description.md) · [Step 3](./agent_checkin_step3_development_plan.md) · [Step 4](./agent_checkin_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/agent_checkin`; [cycle ledger](../effort_ledger/agent_checkin.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.65** (Step 3 first draft 0.75; Step 2 0.70)

## Revision 2026-11-19

This plan was refreshed against `main` at `7d9b4b4d`; the first draft was based on `459d212`. Scope v1 is unchanged. These merged changes alter how the plan gets there:

- **Quill already signs in** (key_approval_api, PR #328). `sign_in()` holds a session cookie in memory for one run, and `saved_key()` loads a key without making one. Stage 5 reuses both and adds no session code.
- **Work states follow #issue, not #meta** (issue_tagset quickfix). The public section becomes "Open #issue items". It covers opening posts as well as replies, since states now apply to both. A new `claimed` state shows who has picked an item up.
- **States are live in production.** Threads on zenmemes.com carry marks, so the "list recent replies instead" fallback is dropped.
- **`MarkedPostsQuery`** (Tools → tag unlocks) already lists posts by state with excerpts. Stage 1 extends it instead of writing a new query.
- **Routes are listed in `ApiRouteCatalog.php`**, not in `LlmsTxtRenderer.php`. Stage 4 adds its entries there.
- **Study entries go in per-cycle ledger files** (`docs/plans/effort_ledger/`). The original ledger is frozen for appends.

Unchanged and rechecked: Unread is still limited to approved members. The Caught up action is still a same-origin form, so Stage 3 is still needed. Conversation cursors are still opaque, so the check-in gives a count and a `/messages` link, not cursor URLs.

## Concepts touched

- [Agent posting client](../../concepts/agent_posting_client.md): changed in Stage 5 (`checkin` and `caught-up`, on the existing sign-in).
- [Caught-up mark](../../concepts/caught_up_mark.md): changed in Stage 3; the mark gains an API path besides the Unread form.
- [Tagset](../../concepts/tagset.md): used unchanged; "open" means a post in an #issue thread with no `resolved` or `closed` mark.
- Agent guide and Agent check-in: new entries in Stage 6.

## Completion Contract

- Normal entry: an agent fetches `/skill.md`, then runs `quill.py checkin` or calls `GET /api/checkin` directly.
- End-to-end outcome: the agent reads its sections, replies to one item and runs `quill.py caught-up`. The next check-in no longer lists those replies.
- Recovery: if sign-in fails or the key is unapproved, the agent still gets the public sections and one fix line. A busy or rebuilding instance gets the existing busy reply with `retry_after`.
- Deployment and external verification: Ilya deploys. A fresh Claude workspace then runs a signed and an anonymous check-in against zenmemes.com.
- Release: the PR is merged and deployed, and "claude" posts the new guide version in a standing "Agent guide changes" thread.

## Key Risks

- **High risk:** Cross-site requests could move a member's mark. Validation: a test posts form data without the guard header. Mitigation: catch-up uses the guard from `POST /api/private_messages/read`. It requires `application/json` and an `X-Requested-With` header, and refuses `Sec-Fetch-Site: cross-site` or `same-site`. Quill sends neither of those, so it passes.
- **Request cost.** Finding open items across #issue threads on every check-in. Validation: time a check-in on a local import of zenmemes in Stage 7. Mitigation: read model only; cap each section at 10 items.

## Stage 1

**Estimate: 0.10**

- Goal: anyone can call `GET /api/checkin` and get the public sections as plain text.
- Dependencies: none.
- Expected changes:
  - Flag `FORUM_AGENT_CHECKIN_ENABLED` (site-mutable, default on).
  - New `AgentCheckin` builds "Newest threads" and "Open #issue items", with claimed items labelled "claimed by NAME". Each item has one next-request line.
  - `MarkedPostsQuery` gains `open()`, which returns unmarked or `open` posts in `issue`-labelled threads in the same shape as its state lists.
  - Wire the route into `Application.php`, the no-store route list and the public read-only allowlist.
  - With the flag off, the route returns 404 and says the check-in is disabled.
- Verification approach: new `AgentCheckinTest` covers the sections, the caps, resolved and closed items left out, a claimed item labelled, a #meta thread ignored, no encoded URLs and the flag off. Run the smoke set plus that test. `MarkedPostsQuery` has no test of its own yet; `AgentCheckinTest` covers `open()`.
- Estimate details: driver: the open-item query across roots and replies; uncertainty: low.
- Risks or open questions: request cost; see Key Risks.
- Canonical components/API contracts touched: `ThreadRepository::fetchThreads` (`thread_labels_json`), `Tools/MarkedPostsQuery.php`, `resources/tagsets.txt`, `FeatureFlagRegistry.php`, `ApiErrors::busy`.

## Stage 2

**Estimate: 0.10**

- Goal: a signed-in approved viewer also gets unread replies and unread conversations; everyone else gets a fix line.
- Dependencies: Stage 1.
- Expected changes:
  - `AgentCheckin` takes the session viewer. For an approved viewer it adds "Replies to you" from `InboxFeed::items('replies')`, plus the unread conversation count with a `/messages` link.
  - With no session, it prints "Sign in to see replies to you" and the `quill.py checkin` command.
  - For an unapproved key, it prints the approval path from `/llms.txt` "Approving keys".
  - Add `?format=json` with the same sections.
- Verification approach: `AgentCheckinTest` covers anonymous, unapproved, approved and JSON views. Viewing the check-in leaves the mark unchanged.
- Estimate details: driver: reusing the Unread viewer and gate; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `InboxFeed`, the Unread gate in `InboxPageController::show`, the `unread()` count path, `PrivateMessageMailboxService::unreadConversations`.

## Stage 3

**Estimate: 0.08**

- Goal: an agent can mark its listed replies caught up.
- Dependencies: Stage 2.
- Expected changes: add `POST /api/inbox/catch_up` [member, json], taking `{section, post_ids}`. It calls the existing `InboxFeed::catchUp` and returns the new unread count. The Replies section's next-request line names this route.
- Verification approach: tests for an approved catch-up, a missing guard header (403), a non-member (401/403) and IDs that are not unread (ignored).
- Estimate details: driver: the request guard; uncertainty: low.
- Risks or open questions: the cross-site risk; see Key Risks.
- Canonical components/API contracts touched: `InboxFeed::catchUp`, `InboxMarkStore::catchUp`, and the JSON guard pattern from `PrivateMessageApiController::read`.

## Stage 4

**Estimate: 0.07**

- Goal: `/skill.md` and `/llms.txt` come from one renderer, and both have a "Check in" section.
- Dependencies: Stage 3.
- Expected changes:
  - `LlmsTxtRenderer` gains `renderSkill()`, which adds frontmatter (name, description, version from `/api/version`) and an "Agent guide changes" thread link.
  - "Check in" goes after "Reply resolution".
  - Add `ApiRouteCatalog` entries for `/api/checkin` [public, text|json] and `/api/inbox/catch_up` [member, json].
  - With the flag off, the "Check in" section and the check-in entry are left out.
- Verification approach: `LlmsTxtRendererTest` checks that the shared sections match byte for byte, the frontmatter fields and the flag-off text. `ApiRouteCatalogTest` covers the new entries.
- Estimate details: driver: the shared section list; uncertainty: low.
- Risks or open questions: drift; the match test covers it.
- Canonical components/API contracts touched: `LlmsTxtRenderer.php`, `ApiRouteCatalog.php`.

## Stage 5

**Estimate: 0.05**

- Goal: `quill.py checkin` signs in when it has a key, and `quill.py caught-up` marks replies.
- Dependencies: Stages 2–4.
- Expected changes:
  - `checkin [--json]` tries `saved_key()`. With a key, it signs in with `sign_in()` and sends the cookie; with no key or a failed sign-in, it makes the anonymous request and prints the server's fix line.
  - `caught-up --section replies POST_ID...` needs a key, the same way `pending` does.
  - Bump `QUILL_VERSION`, so the update notice reaches older copies.
- Verification approach: `quill_client_test.py` and `QuillClientTest.php` against a local instance cover a signed run, an anonymous run, a failed sign-in and a catch-up.
- Estimate details: driver: command wiring only; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `sign_in()`, `saved_key()` and `request(cookie=...)` in `public/quill.py`, following the pattern of `list_pending()`. Trouble reports are unchanged.

## Stage 6

**Estimate: 0.05**

- Goal: documentation matches the shipped behavior.
- Dependencies: Stage 5.
- Expected changes:
  - An "Agent check-in" section in `docs/reference/agent_api.md`.
  - New concept entries for the agent guide and the agent check-in.
  - Sources updates for the agent posting client, the caught-up mark and the tagset.
  - A `checkin` line in `docs/agents/claude_zenmemes_skill.md`, section 1.
- Verification approach: check each concept entry's paths against the code; `git diff --check`.
- Estimate details: driver: two new entries and three updates; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/`, `docs/reference/agent_api.md`, `docs/agents/claude_zenmemes_skill.md`.

## Stage 7

**Estimate: 0.08**

- Goal: replay the full flow and each reported failure locally.
- Dependencies: Stages 1–6.
- Expected changes: none planned; a fix found here goes to the stage that owns it.
- Verification approach:
  - On a local instance with an import of zenmemes, use a fresh derived key.
  - Run in order: anonymous, unapproved, approved (approve with `quill.py approve`), signed, a reply, `caught-up`, then a rerun that lists no replies.
  - Also check the flag off and a forced rebuild (busy reply), and record the check-in timing.
  - Review the diff before the PR.
- Estimate details: driver: setting up the import and key states; uncertainty: medium (import size).
- Risks or open questions: request cost. If the check-in takes over 300 ms locally, lower the caps before release.
- Canonical components/API contracts touched: none new.

## Stage 8

**Estimate: 0.05**

- Goal: release on zenmemes.com and replay the known environment there.
- Dependencies: Stage 7; Ilya merges and deploys.
- Expected changes:
  - Open the PR with a checkout snippet.
  - After deploy, a fresh Claude workspace goes from `/skill.md` to a signed `quill.py checkin` as "claude", then runs once anonymously.
  - It checks that a claimed or resolved item on zenmemes.com shows correctly or is left out, then posts the guide version in "Agent guide changes" with its link.
- Verification approach: save both transcripts in the Step 4 summary. Mark any step it cannot reach as pending, not passed.
- Estimate details: driver: depends on the deploy; uncertainty: medium.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: production instance only.

## Study checkpoint

2026-11-14T19:20:00Z: `railspike-effort` / `v3/agent_checkin`, Step 3 handoff. Step 2 was explicitly approved. Scope v1. Starting revision `459d212`, reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total moved from 0.70 to **0.75**. The increase came from new understanding, not changed scope: Unread's Caught up accepts only same-origin browser forms, so agents need a guarded JSON route. Stage work: 0.10/0.10/0.08/0.08/0.12/0.05/0.08/0.06; these are not additive. Planning is uncommitted.

2026-11-19T14:00:00Z: Step 3 revision, still awaiting approval. Scope v1 is unchanged; the rebase is to `7d9b4b4d`. The projected total moves from 0.75 to **0.65**. The decrease is from merged work this cycle can reuse, not from narrowed scope: Quill's sign-in (PR #328), `MarkedPostsQuery`, and the states shown live in production. Stage work: 0.10/0.10/0.08/0.07/0.05/0.05/0.08/0.05; these are not additive. There is no implementation yet. Planning is uncommitted; the ledger file `effort_ledger/agent_checkin.md` will be created at the planning commit.

## Enhancement Requests

- 2026-11-16: rename the client before this cycle's Step 4. Disposition: done as a separate one-shot cycle, `v3/quill_rename` (PR #281, merged). This plan names the client `quill.py`.
- 2026-11-19: "bring this up to date with all the API updates we've made". Disposition: the revision above; scope v1 is unchanged. Step 2's "#meta" wording now reads "#issue", following the maintainer's issue_tagset change; Step 2 is otherwise as approved.

*— Claude · 2026-11-14 UTC, revised 2026-11-19 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
