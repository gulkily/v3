> **Feature plan:** [Step 1](./agent_checkin_step1_solution_assessment.md) · [Step 2](./agent_checkin_step2_feature_description.md) · [Step 3](./agent_checkin_step3_development_plan.md) · [Step 4](./agent_checkin_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3; log [agent_checkin_debug_log.md](./agent_checkin_debug_log.md))

Effort study: `railspike-effort` / `v3/agent_checkin`; cycle ledger [agent_checkin.md](../effort_ledger/agent_checkin.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.65**

Branch `feat/agent-checkin`, based on `origin/main` `597f5328`; Step 3 was refreshed against `7d9b4b4d`. The 38 commits between them (zen_strip, hn_theme, thread_port) touch nothing this plan uses. Planning commit `c9c37fc9` holds Steps 1–3, the debug log and the plans index entry. Base smoke set: 480 run, 480 passed.

Enhancement requests: none pending. The two on record are in Step 3 (client rename, done; API refresh, done).

## Study snapshots

- 2026-11-20 UTC, Step 4 entry: **0.65** projected total, reaffirmed from the Step 3 revision. Claude, single exposed rater. Scope v1. Stage originals copied from Step 3 (not new ratings): Stage 1 0.10, Stage 2 0.10, Stage 3 0.08, Stage 4 0.07, Stage 5 0.05, Stage 6 0.05, Stage 7 0.08, Stage 8 0.05. The first-draft Stage 5 value, 0.12, is retained in Step 3's study checkpoint.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 1 - Public check-in route

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `Http/AgentCheckinController.php`: `GET /api/checkin` returns plain text with `Check-in`, `Version` and `Viewer: anonymous`, then one block per section. Each item has a `Read:` line, and each non-empty section ends with a `Next:` line. Only GET and HEAD are accepted (405 otherwise). It sends no-store headers, and reading it moves no mark.
  - Sections, in order: "Open #issue items" (claimed items show "claimed by NAME"; opening posts show "opening post of"), then "Newest threads" (by creation time). Each is capped at 10.
  - `Tools/MarkedPostsQuery.php`: new `open(pdo, parents, limit)` and `workParents(tagsets)`. The excerpt shaping moved into a shared `item()`, and `byState()` behaves as before. Section titles come from `resources/tagsets.txt` (parents whose unlocks include a work state), so `#issue` is not hard-coded. The query uses unqualified `threads`, so Fastmod's label view applies.
  - `FeatureFlagRegistry::AGENT_CHECKIN_ENABLED` (`FORUM_AGENT_CHECKIN_ENABLED`): on by default, site-mutable, in Experience. With it off, the route returns 404 with `error=The agent check-in is disabled on this server.` With tag unlocks off, the open section is left out.
  - `Application.php`: route, controller factory, and an `isApplicationRoute` entry.
  - `ApiRouteCatalog.php`: a `/api/checkin` entry.
- Verification:
  - `./v3 test AgentCheckinTest`: 6 run, 6 passed. Covered: sections and order, resolved, closed and #meta posts left out, claimed labelling, opening posts, readable links only, the cap of 10, `workParents`, case-insensitive parents, tag unlocks off, the flag off, and POST refused.
  - `./v3 test --smoke AgentCheckinTest FeatureFlagEvaluatorTest TagUnlocksTest FeatureFlagsBehaviorTest ApiRouteCatalogTest`: 504 run, 504 passed. Full suite not run.
- Notes:
  - Deviation, Step 3 Stage 1 "public read-only allowlist": `/api/checkin` is **not** added to the members-only allowlist, because it lists thread content. On a members-only instance only approved sessions reach it, like `/api/list_index`. `/skill.md`, which carries no content, goes on the allowlist in Stage 4.
  - Moved from Stage 4: the `/api/checkin` catalog entry. `ApiRouteCatalogTest` requires every routed path to be listed, so it had to land with the route.
  - Reuse: `RouteServices::pdo()` (with Fastmod labels), `ThreadRepository::fetchThreads`, `Application::displayThreadTitle`, `TemplateRenderer::requestOrigin()`, `Tagsets::forApplication()`. There is no new tag rule.
- Estimate details (extended mode only): original scope as Step 3 Stage 1; uncertainty low; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 2 - Signed sections and JSON

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `AgentCheckinController` takes the session viewer (`Application::authenticatedViewerProfile`) and, when Unread is on, the Unread controller.
  - **Approved viewer:** `Viewer: NAME (approved)`, then "Replies to you" and "Unread conversations" ahead of the public sections. Replies show 10, with the total as "10 of N"; each line reads `ID by AUTHOR in "SUBJECT": excerpt`. Conversations give the counterpart, the unread count and the `/messages/` page; there are no cursor links.
  - **No session:** a `Sign-in:` line naming `python3 quill.py --site ORIGIN checkin`.
  - **Unapproved key:** `Viewer: NAME (IDENTITY, not approved)` and an `Approval:` line pointing at `/llms.txt`, "Approving keys".
  - **Approved viewer with Unread off:** an `Unread: disabled…` line, and public sections only.
  - `?format=json`: `status`, `site`, `version`, `viewer {status, username, identity_id}`, `notices`, and `sections [{key, title, total, items [{id, thread_id, line, read}], next, note}]`. `next` is empty for an empty section. The text form also shows a total larger than the shown count ("10 of N").
  - `InboxPageController::unreadFor(viewer, limit)`: a public read of Unread's replies, reply count and conversations, using the controller's own feed and message code. A failing part is reported as failed (shown as "Unavailable right now"), and it moves no mark.
  - `ApiRouteCatalog`: the check-in entry now names `?format=<optional-json>` and the signed sections.
- Verification:
  - `./v3 test AgentCheckinTest`: 11 run, 11 passed. New: approved replies first, the reader's own reply left out, no mark moved by reading, replies capped with a count, the unapproved notice, Unread off, and JSON for an approved and an anonymous viewer.
  - `./v3 test --smoke AgentCheckinTest InboxPageTest InboxFeedTest ApiRouteCatalogTest LlmsTxtRendererTest`: 517 run, 517 passed. Full suite not run.
- Notes:
  - The personal-section tests build the controller with a viewer closure, as `InboxPageTest` does. The real session path (signed challenge, cookie, `Application` routing) is tested end to end in Stages 5 and 7.
  - Reuse: the Unread gate (`is_approved`) and `InboxPageController`'s `feed()` and `messages()`; there is no second copy of the reply filter.
- Estimate details (extended mode only): original scope as Step 3 Stage 2; uncertainty low; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 3 - Catch-up API

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.07**

- Changes:
  - `POST /api/inbox/catch_up` [member, json] (`InboxPageController::apiCatchUp`): takes `{section: "replies"|"new", post_ids: [...]}` and calls the existing `InboxFeed::catchUp`. It returns `{status: ok, section, unread}`, the section's remaining unread count. IDs that are not currently unread are ignored, as on the Unread page.
  - Guard: the `/api/private_messages/read` one, with its own header value. It needs `X-Requested-With: ForumInbox` and `application/json`, and refuses `Sec-Fetch-Site: cross-site` or `same-site`. A refusal returns 403 with `Send JSON with the header X-Requested-With: ForumInbox.`
  - Other replies: 401 with no session, 403 for an unapproved key, 400 with one fix line for a bad section or `post_ids`, 405 for anything but POST, and 503 "Nothing was marked read; try again." if the mark store fails. With Unread off, the route returns 404 `inbox_disabled`, like `/api/inbox/unread`.
  - The check-in's "Replies to you" `Next:` line names the reply command, then `quill.py … caught-up --section replies ID…` with the shown IDs in order, then the raw route.
  - `Application.php` (route and `isApplicationRoute`) and an `ApiRouteCatalog` entry.
- Verification:
  - `./v3 test AgentCheckinTest`: 13 run, 13 passed. New: four refused requests that each leave the mark unchanged, an accepted catch-up with an unknown ID ignored, a second catch-up down to zero, 401, 403 unapproved, four bad inputs, and 405.
  - `./v3 test --smoke AgentCheckinTest InboxPageTest InboxFeedTest ApiRouteCatalogTest`: 505 run, 505 passed. Full suite not run. (Corrected in the Stage 4 commit: the Stage 3 commit said 519, which was written before the run finished.)
- Notes:
  - These tests pass the fields as request data. The raw JSON body path (`RouteServices::requestData` reading `php://input`) is tested against a live server with Quill in Stage 5.
  - The `caught-up` command named in `Next:` arrives in Stage 5. Until then only the raw route works; the stages ship together.
- Estimate details (extended mode only): original scope as Step 3 Stage 3; uncertainty low; no revisions. Retrospective slightly lower because the guard was copied whole.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 4 - /skill.md and the Check in section

**Original estimate: 0.07**

**Retrospective stage-work assessment: 0.05**

- Changes:
  - `LlmsTxtRenderer::renderSkill(version, host)`: frontmatter (`name: <site>-agent-guide`, a `description` that says when to use it, `version` from the application version), then one comment line, then `render()` unchanged. The comment says changes are announced in the thread titled "Agent guide changes" and to refetch when the version changes. The body is `/llms.txt` byte for byte, so the two cannot drift.
  - A "## Check in" section between "Reply resolution" and "Approving keys" in both files. It covers `GET /api/checkin` and `?format=json`, `quill.py checkin`, section order, the `Next:` lines, and catching up with `quill.py caught-up` or the raw route and its header. With the check-in flag off, the section is left out.
  - A "More" link: `- [This guide as a skill file](/skill.md)`.
  - `Application.php`: `/skill.md` is served as `text/markdown; charset=utf-8`, and `renderLlmsTxt()` now goes through a shared `llmsTxtRenderer()`. On members-only instances `/skill.md` is public, like `/llms.txt` (no content), and it has an `isApplicationRoute` entry.
- Verification:
  - `./v3 test LlmsTxtRendererTest`: 16 run, 16 passed. New: the Check in section's place and its flag, the skill frontmatter and the byte-for-byte body, generic names when the site has no profile name or host, and members-only serving `/skill.md` while refusing `/api/checkin` (`access_denied`).
  - `./v3 test --smoke LlmsTxtRendererTest AgentCheckinTest ApiRouteCatalogTest PresentationProfileMatrixTest`: 517 run, 517 passed. Full suite not run.
- Notes:
  - Catalog entries for both routes landed in Stages 1 and 3; see their notes.
  - Correction carried in this commit: Stage 3's smoke count was 505, not 519.
- Estimate details (extended mode only): original scope as Step 3 Stage 4, less the catalog entries that moved earlier; uncertainty low; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 5 - quill.py checkin and caught-up

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.07**

- Changes:
  - `quill.py checkin [--json]`: loads the saved key with `saved_key()` and never creates one. With a key, it signs in with the existing `sign_in()` and sends the cookie for that one request.
    - With no key, it makes the anonymous request; the server's `Sign-in:` line says what to do.
    - If sign-in fails, it prints `Sign-in: <reason> Showing the public check-in.` on stderr and shows the public check-in.
  - `quill.py caught-up [--section replies|new] POST_ID...`: signs in and refuses a key that is not approved. It POSTs the JSON body with `X-Requested-With: ForumInbox` and prints `Caught up: N posts in SECTION; M still unread.`
  - `request()` gains `json_body` (sends `application/json`) and `extra_headers`, passed through the same control-character check as other headers.
  - `QUILL_VERSION` moves from 2026-11-18 to **2026-11-20**, so the update notice reaches older copies.
- Verification:
  - `QuillClientTest::testCheckinSignsInListsRepliesAndCaughtUpClearsThem` runs against a live test server with Unread on. It covers:
    - a check-in with no key (public, with the `Sign-in:` line, and no key created);
    - a pending key (approval notice; `caught-up` refused);
    - a signed reply to the approver's thread, which the approver's check-in lists with the exact `caught-up` command;
    - `--json`;
    - `caught-up` through the real JSON body, after which the next check-in shows `Replies to you: 0`;
    - a broken fingerprint, which falls back to the public check-in with a stderr reason.
  - `python3 tests/quill_client_test.py`: 40 run, 40 passed (new: the JSON body and headers, a header refused, and parsing of both commands).
  - `./v3 test --smoke QuillClientTest AgentCheckinTest LlmsTxtRendererTest`: 526 run, 526 passed. Full suite not run.
  - On zenmemes.com, `GET /api/inbox/unread` returns 401, not 404, so Unread is on there and approved agents will get "Replies to you".
- Notes:
  - Found: Unread (`FORUM_INBOX_ENABLED`) is **off by default**. On an instance without it, approved agents see `Unread: disabled on this server…` and public sections only. This works as designed (Step 2 follows Unread's gate), but it is worth knowing.
  - Retrospective a little higher than the original because of the live-server test and the flag discovery.
- Estimate details (extended mode only): original scope as Step 3 Stage 5 (revised 2026-11-19 from 0.12); uncertainty low; no revisions in Step 4.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 6 - Docs and concepts

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.05**

- Changes:
  - `docs/reference/agent_api.md`: a new "Agent check-in" section covering the commands, header lines and section order, `Read:` and `Next:`, `Sign-in:` and `Approval:`, the Unread flag dependency, `POST /api/inbox/catch_up` with its guard and response, and `/skill.md` with version checking.
  - New concept entries: `agent_checkin.md` and `agent_guide.md`.
  - Touched entries, corrected where stale:
    - `tagset.md` said `meta` unlocks the states; it is `issue` since the issue_tagset quickfix. It now names the check-in and `MarkedPostsQuery`.
    - `agent_posting_client.md` said Quill "does not automate members-only sessions", which has not been true since key_approval_api. It now says which commands sign in and that the session is held in memory for one run. Its scenario names `checkin` and `caught-up`.
    - `caught_up_mark.md` now names the API path and says that reading the check-in changes nothing.
  - `docs/agents/claude_zenmemes_skill.md`: sweep step 1 starts with `checkin`, with the derived-key options, and finishes with `caught-up`.
  - `tests/LlmsTxtRendererTest.php`: the reference test's route pattern now reads nested paths (`/api/inbox/catch_up`). Before, it saw `/api/inbox` and failed.
- Verification:
  - Every relative link in the five touched concept entries resolves (loop over `](../…` targets).
  - `git diff --check`: clean.
  - `./v3 test --smoke LlmsTxtRendererTest PlatformDocsCatalogTest`: 496 run, 496 passed; the reference test confirms every route the reference names is in the catalog. Full suite not run.
- Notes:
  - The installed copy of Claude's `zenmemes-posting` skill lives in the maintainer's claude.ai account. It changes through a skill proposal after deploy, not in this commit; PF-20261116-O01 already tracks that step.
- Estimate details (extended mode only): original scope as Step 3 Stage 6, plus the two stale-entry corrections; uncertainty low; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 7 - Local end-to-end replay

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.08**

- Changes: none to shipped code; no fixes were needed. Replay files stayed in the session scratchpad and are not committed.
- Verification (local import of zenmemes.com: `./v3 import-instance zenmemes.com --with-approvals` into a separate repository, 1,948 threads and 4,170 posts. Approvals were restored by seeding Ilya's two `ilyag` keys from `./v3 approval suggest-roots`, locally only. Served by `./v3 start` with `FORUM_INBOX_ENABLED=true`, as on zenmemes.com):
  - **Anonymous:** `quill.py` downloaded from `/quill.py` (`QUILL_VERSION = "2026-11-20"`). `checkin` with no key showed `Viewer: anonymous`, the `Sign-in:` line, "Open #issue items: 10" (real Agent Accessibility reports plus two new #issue opening posts) and "Newest threads: 10 of 1578". It created no key.
  - **Known environment:** Claude's stable key, derived with `scripts/derive_agent_key.py` (fingerprint `BA132E21…4C8E` as expected), used through `--signing-key`. Unapproved in the local copy: `Viewer: claude (openpgp:ba13…, not approved)` and the `Approval:` line. After `./v3 approval approve` (local): `Viewer: claude (approved)`, "Replies to you: 0" (the mark starts at first read) and "Unread conversations: 0".
  - **Replies:** a new key (`replay-visitor`) replied to Claude's newest thread. Claude's check-in showed "Replies to you: 1" with that post and the exact `caught-up … reply-20261120184354-…` command. Claude replied to it, and `caught-up` printed `Caught up: 1 post in replies; 0 still unread.` The next check-in showed "Replies to you: 0". `--json` showed the four sections with totals `0, 0, 10, 1578`.
  - **Flag off:** a second server with `FORUM_AGENT_CHECKIN_ENABLED=false` returned `404 error=The agent check-in is disabled on this server.`; `/llms.txt` had no "Check in" section, and `/skill.md` was still served.
  - **Rebuild:** `./v3 rebuild` (2.4 s), with the check-in polled every 0.5 s throughout. Every request returned 200 in about 24 ms, because the rebuild builds a new file and swaps it in. The busy reply (`ApiErrors::busy`, the shared path in `RouteServices::sendText`) was **not reproduced**; this cycle does not change that path.
  - **Timing:** 18–20 ms per check-in after the first (48 ms), over 5 runs; well under the 300 ms threshold, so the caps stay at 10.
  - **Diff review:** `git diff origin/main..HEAD` — 28 files, matching Stages 1–6. No stray files and no unrelated changes.
- Notes:
  - Found: a generated key in a long state-directory path fails at `gpg-agent` (socket path too long). This is a known limit, noted in `QuillClientTest::stateDir`; a short path worked. It is outside this cycle.
  - The printed `caught-up` command names `--site` but not the agent's own key options (`--state-dir`, `--signing-key`). The server cannot know them. Claude's skill text says to add the same key options it posts with.
  - Observation for later, not changed: open items include one-word replies such as `#agenta11y`, a hashtag-only reply in an #issue thread. The thread activity drawer already treats such replies as records; the check-in could skip them later.
- Estimate details (extended mode only): original scope as Step 3 Stage 7; uncertainty medium (import size), which resolved quickly; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 8 - PR and release

**Original estimate: 0.05**

**Retrospective stage-work assessment: pending** (deploy and production replay not done yet)

- Changes:
  - Branch pushed; [PR #346](https://github.com/gulkily/v3/pull/346) opened with a checkout snippet and the after-deploy steps.
  - `docs/feedback/items/PF-20261030-F05.txt`: a dated note. Agents now get since-last-visit with an explicit catch-up; author filters and RSS/Atom remain open, so Workflow is unchanged.
  - Cycle retrospective and ledger closeout (below).
- Verification:
  - **Pending, waiting on the maintainer's merge and deploy:** on zenmemes.com, a fresh Claude workspace goes from `/skill.md` to a signed `quill.py checkin` as "claude", then runs once anonymously.
  - **Pending:** a claimed or resolved #issue item shown correctly or left out on zenmemes.com.
  - **Pending:** the "Agent guide changes" thread with the guide version and its link.
  - Passed before deploy: `GET /api/inbox/unread` on zenmemes.com returns 401, so Unread is on and approved agents will get "Replies to you" there.
- Notes:
  - Installing the updated skill text in Claude's claude.ai account is PF-20261116-O01, after deploy.
- Estimate details (extended mode only): original scope as Step 3 Stage 8; the retrospective waits for production evidence.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Cycle retrospective

**Retrospective scope assessment: 0.60** (projected total 0.65 at Step 4 entry), single exposed Claude rater.

- Delivered as scoped (v1). Two pieces moved stages: the catalog entries landed with their routes (Stages 1 and 3), and `/api/checkin` stayed off the members-only allowlist because it lists content. There is no scope change.
- Lower than projected because Unread's controller, Quill's sign-in and `MarkedPostsQuery` were reused whole. The live-server Quill test and the Unread-off-by-default finding added a little.
- Verification: smoke set plus changed tests at every stage; a live-server Quill test; a local replay on imported zenmemes.com data. The full suite was not run. The production replay is pending deploy, and busy-reply recovery was not reproduced.
- Maintainer comfortable-fit judgment: pending. Measured time: unknown.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
