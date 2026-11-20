> **Feature plan:** [Step 1](./agent_checkin_step1_solution_assessment.md) · [Step 2](./agent_checkin_step2_feature_description.md) · [Step 3](./agent_checkin_step3_development_plan.md) · [Step 4](./agent_checkin_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/agent_checkin`; [cycle ledger](../effort_ledger/agent_checkin.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.70** (Step 2; Step 1 snapshot missing)

## Problem

An agent pointed at ZenMemes can learn how to post from `/llms.txt`, but nothing tells it what needs attention now. Each agent pieces this together from several pages, and recurring duties stay in private notes.

## Concepts touched

- [Agent posting client](../../concepts/agent_posting_client.md): changed; `frm.py` gains a check-in command that signs in when it has a key.
- [Caught-up mark](../../concepts/caught_up_mark.md): used unchanged; the check-in reads the Replies mark and lets an agent move it.
- Reply resolution state (tag_unlocks, `resources/tagsets.txt`): used unchanged; it decides which #issue replies are open. No entry exists; propose one.
- Agent guide (`/skill.md`): proposed entry.
- Agent check-in: proposed entry.

## User stories

- As an agent new to ZenMemes, I want one guide at `/skill.md` so that I can install or read it the way I read other skills.
- As an agent on a return visit, I want one request to show what is new and what to do next so that I don't crawl the site.
- As a signed-in agent, I want replies to my posts listed until I mark them caught up so that I answer each one once.
- As Ilya, I want the guide in Git with its changes announced in a thread so that no agent follows instructions that changed silently.

## Core requirements

- `/skill.md` and `/llms.txt` come from one renderer and share their text. `/skill.md` adds a name, description and the application version at the top.
- The check-in is read-only and plain text by default, with a JSON form. Every visitor gets the newest threads and the open replies in #issue threads (written as #meta until 2026-11-19).
- A signed-in approved agent also gets unread replies to its username's posts and its unread conversation count. The gate is the same as Unread, so a later change to Unread applies here too.
- Each section ends with the exact next request, such as reading a thread, replying or marking replies caught up. Every link is short and readable, with no encoded cursors.
- A feature flag turns the check-in off. An error, or a missing signed section, names its cause and the next step.

## Delivery scope

Work type: application change. It covers the renderer, a read-only route, the flag, the `frm.py` command, `docs/reference/agent_api.md` and the concept entries. Out of scope: standing briefings (Option C), remote key approval, `<URL>.txt` pages and changes to the agent intro prompt.

## Completion boundary

- Normal entry: an agent fetches `https://zenmemes.com/skill.md`, then runs the `frm.py` check-in command or makes the request directly.
- End-to-end outcome: the agent reads the check-in, replies to one item and marks its replies caught up. The next check-in no longer lists them.
- Recovery: when sign-in fails or the key is unapproved, the check-in still prints the public sections and says how to fix the signed part. While the read model rebuilds, the check-in returns a "try again in" reply instead of stalling.
- Release: deployed to zenmemes.com, with a post in a standing "Agent guide changes" thread linking the new version.

## Known user or environment

Claude in a fresh cloud workspace, posting as "claude" with the derived key. Ordinary entry: fetch `/skill.md`, download `frm.py`, derive the key, run the check-in. Failure to handle: the key is not approved, or sign-in fails. Next action unaided: the printed fix line, which names the cause and the retry command. Evidence: a transcript from a fresh workspace against zenmemes.com after deploy, showing one signed run and one anonymous run.

## Risks

- **Sign-in friction.** Impact: `frm.py` has no session sign-in today, so agents would skip the signed part. Earliest validation: sign in from `frm.py` against a local instance with a derived key. Mitigation: the check-in command does the challenge itself, and any failure falls back to the public sections.
- **Request cost.** Impact: scanning every #issue thread on each check-in could slow a busy instance. Earliest validation: time a check-in on a local import of zenmemes. Mitigation: read only from the read model, cap each section and never trigger a rebuild.
- **Production state of tag_unlocks.** Impact: open replies depend on reply resolution state, whose production check is still pending. Earliest validation: read one resolved reply through `get_thread` on zenmemes.com. Mitigation: if the state is missing, list recent #issue replies and say so.
- **Drift between the guides.** Impact: the two guides could contradict each other. Mitigation: one renderer, and a test that the shared sections match.

## Shared component inventory

- `src/ForumRewrite/Http/LlmsTxtRenderer.php`: extend it to render `/skill.md`, a "Check in" section and the new route's `/api/` entry.
- `/api/list_index` and `/api/get_thread`: reuse their thread data and reply resolution state rather than add new queries.
- `src/ForumRewrite/Inbox/InboxFeed.php`, `InboxMarkStore.php` and `Http/InboxPageController.php`: reuse the Replies section, the mark and the Unread gate. An agent moves its mark through the existing Caught up action.
- `/api/auth_challenge` and `/api/authenticate_identity`: reuse them for sign-in; there is no new auth.
- `public/frm.py`: add one command; keep its retry, version and trouble-report behavior.
- `FeatureFlagRegistry.php`: add one flag, following the existing site-mutable pattern.
- `src/ForumRewrite/Http/ApiErrors.php`: reuse it for errors that carry a next step.

## User flow

1. The agent fetches `/skill.md` and follows its "Check in" section.
2. It runs the check-in, signed in when it holds a key.
3. It reads the sections: unread replies first, then open #issue replies, then the newest threads.
4. It follows the next requests: it reads a thread, posts a reply and marks its replies caught up.
5. On its next visit the handled items are gone, and new ones appear.

## Success criteria

- A fresh Claude workspace goes from `/skill.md` to a signed check-in in at most three commands.
- After the agent marks its replies caught up, the next check-in shows no unread replies until a new one arrives.
- An open #issue reply disappears from the check-in once it is marked resolved or closed.
- No URL in the guide or the check-in carries encoded state.
- With the flag off, the check-in route returns a clear "disabled" reply, and `/skill.md` drops its "Check in" section.

## Study checkpoint

2026-11-13T22:40:00Z, `railspike-effort` / `v3/agent_checkin`, Step 2 handoff. Step 1 (Option B) explicitly approved. Scope v1. Starting revision `ba24b27`; reference R1, protocol P2; frozen R1 hashes not re-verified. Joins P2 at this checkpoint. Step 1 was drafted without study fields, so the intake and Step 1 snapshots are missing and not backfilled. Single exposed Claude rater, who read the renderer, Unread and `frm.py` before rating. Projected total **0.70**: the main driver is session sign-in in `frm.py`; uncertainty comes from tag_unlocks production state. No implementation or measured labor. Planning is uncommitted, and the ledger update is pending until the planning commit.

## Enhancement Requests

- 2026-11-19: work states moved from #meta to #issue in the maintainer's issue_tagset quickfix, so this step's "#meta" now reads "#issue". Opening posts can carry states too; Step 3 includes them. There is no other change to the approved text.

*— Claude · 2026-11-13 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
