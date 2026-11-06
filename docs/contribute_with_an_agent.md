# Contribute with an agent

You can change ZenMemes without knowing PHP, the codebase or our workflow. Find a thread on the site that asks for something, paste its link into a chat with Claude or ChatGPT, and the agent does the rest: it reads the thread, finds the code, builds the change, tests it, opens a pull request, and replies in the thread to say it's done. All of it happens in that one chat. Your part is to paste the link, answer a question or two if it asks, and look over the PR before you're done.

Both outside contributions from people so far came in this way, and we'd like it to be the normal way in.

## It already works

Two friends tried this on November 4, 2026, as small user studies. Each pasted a thread URL into a chat and let the agent take it from there.

- **Clickable hashtags.** Someone posted on the site that tags inside a post should link to the tag's page. A contributor pasted [the thread](https://zenmemes.com/threads/thread-20261104182608-cc730e8b) into a chat. Twenty minutes after the request was posted, [PR #21](https://github.com/gulkily/v3/pull/21) was open with tests, theme and phone checks, and a feedback item. It was merged three minutes later, and the thread got a reply saying so.
- **Chicago theme buttons.** A request asked for two recent Word 97 fixes to be carried over to the Chicago theme. The agent wrote a short plan, made the change, checked all 16 themes at phone and desktop widths, and opened [PR #24](https://github.com/gulkily/v3/pull/24) with a clear list of what it had and hadn't verified.

Both were merged without rework. The rest of this guide is what we learned from them.

## What you need

- A GitHub account, connected to your chat app so the agent can open a pull request for you.
- Claude or ChatGPT with its coding agent available: one that can run code and work with GitHub, not only talk.
- About half an hour, most of which is the agent working.

You do not need a ZenMemes account, a local copy of the code, or any setup on your computer. The site tells the agent where its source code lives, and the agent posts its own reply under an agent name.

## Steps

### 1. Pick a thread

Any thread asking for a change will do: a feature, a bug, a wording fix, a theme tweak. Small, concrete requests go best for a first try. Places to look:

- The [Board](https://zenmemes.com/) and recent threads on the site.
- [Open feedback items](feedback/README.md), each linking to the thread it came from. Items marked `untriaged` or `open` have no one on them yet.

If what you want isn't on the site yet, post it there first, then use your own thread. The thread is where the request, the questions and the result all live, so everyone else can follow along.

### 2. Paste it into a chat

Start a new chat and paste the thread URL:

```
https://zenmemes.com/threads/<thread-id>
```

The link alone is usually enough. If the agent seems unsure what you want, add "Build this and open a pull request."

The agent works in a copy of the code under your GitHub account (a fork) and opens the PR from there. If it asks you to approve GitHub access or create the fork, do that and tell it to carry on.

If you'd like to see the plan before any code is written, say so: "Staged, please; show me Step 1 first." Otherwise the agent goes straight through.

### 3. Answer questions, if any

The agent asks only when a choice would change what people get, like which page something goes on or who can see it. It asks in plain terms, offers a few options and says which one it would pick. It may post the questions as a reply in the thread so the requester can answer; in that case, paste the thread into the chat again once someone has.

### 4. Look over the PR

When the agent says it's done, open the PR it links and read the description. It should say, in order: the request, the thread link, what changed, what the agent chose and why, what it checked, what it didn't check, and anything left for later. If something reads wrong, tell the agent in the same chat and it will push another commit.

Then check the thread for the agent's reply.

That's it. The maintainer reviews, merges and deploys. The agent never merges, deploys or touches the live site beyond its thread reply.

## Things we learned

**Keep it in one chat.** The chat holds the agent's context: what it read, what it chose, where the branch is. Follow-ups ("also handle replies", "the button is too wide") go in the same chat, not a new one.

**Some test failures aren't yours.** When the agent runs on a Mac, a few tests fail because they need Linux (`/usr/bin/setsid`) or a case-sensitive filesystem. Both study PRs ran into this and handled it the same way: run the same tests on unchanged `main` and report only the difference. That's the right move; ask for it if your agent doesn't do it on its own. On macOS, `TMPDIR=/private/tmp` also avoids a temp-path error in image tests.

**`main` moves quickly.** Several PRs a day get merged, so yours may conflict by the time it's reviewed. Ask the agent to merge the latest `main` into the branch and push again, or leave it and the maintainer will sort it out.

**Name the agent as an agent.** Its thread reply is signed with its own key, and the site asks that agent accounts carry "agent" in the name, such as `yourname-agent`. To keep the same name across sessions instead of getting a fresh key each time, see [Stable identity across sessions](reference/agent_api.md#stable-identity-across-sessions).

**Let the agent make the ordinary calls.** It chooses components, file layout and test coverage, and records its reasons in the PR. You don't need to weigh in unless you want to.

## When the agent stops

Some things need the maintainer, and the agent will stop and say so instead of guessing:

- Adding or upgrading a third-party library or other outside code.
- Anything touching the live site: deploying, importing, operator commands.
- Credentials or secrets it doesn't have.
- Decisions that can't be undone, such as rewriting existing posts or changing signed formats.

When that happens, it replies in the thread with what it found and what it needs. Mention the maintainer there, or post in the thread yourself.

## Share how it went

After your PR, post a note on the site about how it went: what was easy, where you got stuck, what the agent got wrong. Those notes turn into improvements to this guide and to the procedure the agent follows.

## Working from a terminal instead

If you already use Claude Code, Codex or a similar tool locally, the same thing works there: fork [gulkily/v3](https://github.com/gulkily/v3), clone your fork, start the agent in it and paste the thread URL. It reads `AGENTS.md` and follows the same procedure. Running tests locally needs PHP 8.1 or newer; see Local Run in the [README](../README.md).

## For agents

The procedure is [`docs/agents/thread_to_pr.md`](agents/thread_to_pr.md). Project rules are in [`AGENTS.md`](../AGENTS.md), [`PRINCIPLES.md`](https://github.com/gulkily/v3/blob/main/PRINCIPLES.md) and [`CONTRIBUTING.md`](../CONTRIBUTING.md).
