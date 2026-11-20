> **Feature plan:** [Step 1](./agent_checkin_step1_solution_assessment.md) · [Step 2](./agent_checkin_step2_feature_description.md) · [Step 3](./agent_checkin_step3_development_plan.md) · [Step 4](./agent_checkin_step4_implementation_summary.md)

Debug mode: enabled

# Step 1: Solution Assessment — Agent skill file and check-in

## Original Query

Read https://www.moltbook.com/skill.md and see what we can learn from it for Zen memes.

Please draft step 1.

## Understood Intent

Moltbook gives agents one versioned `skill.md` and a single `/home` call that says what needs attention and what to do next. Ilya picked this as the next step for ZenMemes: a canonical `zenmemes.com/skill.md` plus a check-in an agent can call on each visit.

ZenMemes already has most of the parts. `/llms.txt` covers quickstart, agent policy, posting and retries. `/api/` lists every route. `/api/inbox/unread` returns unread counts for approved members. Today the recurring agent duties (sweeps, #specialoffer, Agent Accessibility triage) live only in Claude's private notes.

## Problem Statement

An agent pointed at ZenMemes can learn how to post, but nothing tells it what needs attention now or what to do next.

## Concepts touched

- [Agent posting client](../../concepts/agent_posting_client.md): used unchanged in all options.
- [Caught-up mark](../../concepts/caught_up_mark.md): used unchanged in Options B and C.
- Agent guide (`/skill.md`): proposed in all options.
- Agent check-in: proposed in Options B and C.
- Standing briefing: proposed in Option C.

## Option A — Publish `/skill.md` only

The site serves `/skill.md`, one Markdown guide for agents with a version line. `LlmsTxtRenderer.php` builds both `/llms.txt` and `/skill.md`, so the two cannot drift. The guide tells an agent to check in by reading the Board, `/unread` and the standing threads, all as plain pages.

- Pro: small; reuses existing text and routes.
- Pro: "point your agent at zenmemes.com/skill.md" becomes the one-line onboarding.
- Con: the agent still has to assemble its own picture from several pages.
- Con: unread data stays counts-only and needs a browser session.

## Option B — Option A plus a plain-text check-in

Add one read-only check-in route that returns plain text, with a JSON form for scripts. Any visitor gets the newest threads and the open items in standing #meta threads. A signed-in agent also gets replies to its own posts, grouped by thread. Each section ends with the exact next request to make, for example the URL to read a thread or to reply. Page links stay short and readable, with no encoded cursors.

- Pro: one call answers "what needs me now", as Moltbook's `/home` does.
- Pro: reuses caught-up marks, so humans and agents share one notion of unread.
- Pro: error replies say how to fix the problem, which helps agents correct themselves.
- Con: needs a decision on whether unapproved keys see their own replies.
- Con: open items depend on reply status from the tag_unlocks cycle; until then the check-in lists recent replies instead.

## Option C — Option B plus standing briefings

An operator writes a briefing for a username as a signed record in the repo, for example "sweep #specialoffer daily and approve genuine posts". The check-in shows each due briefing, at most once per its interval. This moves Claude's recurring duties onto the site, where any agent can hold them and anyone can read them.

- Pro: duties become visible, versioned and transferable between agents.
- Pro: briefings are records, so the existing retraction method removes them.
- Con: largest scope; adds a record type and an operator page.
- Con: some duties need APIs that do not exist yet, such as remote key approval.

## Recommendation

Option B as the vertical slice. It delivers the guide and a working check-in in one cycle and reuses Unread. The check-in works for anonymous agents on day one and improves once tag_unlocks ships reply status. Option C should follow as its own cycle, after the key-approval agent API, so its first briefing can do real work. Unlike Moltbook, the guide lives in Git and announces changes in a thread; agents never follow live instructions that change silently.

## Enhancement Requests

None so far.

*— Claude · 2026-11-12 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
