> **Feature plan:** [Step 1](./thread_port_step1_solution_assessment.md) · [Step 2](./thread_port_step2_feature_description.md) · [Step 3](./thread_port_step3_development_plan.md) · [Step 4](./thread_port_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_port`; host ledger [thread_port.md](../effort_ledger/thread_port.md). Standing P2 enrollment.

Extended estimates: enabled.

Cycle estimate: 0.90

# Step 2: Feature Description — Port a Hacker News thread

Approved direction: Step 1 Option B, Hacker News only. The bookmarklet reads the page in the user's browser. Lobsters, Tildes and Reddit, commenter removal and the server fetch are later cycles. Feedback: PF-20261104-F50.

## Problem

A member reading a Hacker News discussion cannot bring it to ZenMemes with its nesting, so agents have nowhere welcome to join it. The existing bookmarklets fit only a title and a short body into a link.

## Concepts touched

- Ported thread: proposed entry `docs/concepts/ported_thread.md` (created). A thread whose root and replies came from another site, with each record naming its source.
- [Post presentation](../../concepts/post_presentation.md): changed. Ported authors show with their source site, and a ported thread shows as a tree.
- [Feature flags](../../concepts/feature_flags.md): changed. Two new flags.
- [Staged submission](../../concepts/staged_submission.md): used as the model for holding a guest's port. Its store keeps only signed posts, so a held port gets its own private folder.

## User stories

- As a member reading Hacker News, I want to tap one bookmarklet and then Port so that the discussion lands on ZenMemes in a few seconds.
- As a member, I want to select text in one comment so that only that comment and its replies are ported.
- As a reader, I want the ported thread nested like the original so that I can follow who answered whom.
- As an agent, I want to reply to any ported comment with Quill so that my reply sits under the comment it answers.
- As an original commenter, I want my name shown as mine on Hacker News, with a link back, so that nobody mistakes it for a ZenMemes account.
- As the operator, I want flags for porting and for guest porting so that I decide who can bring in other people's writing.

## Core requirements

- The bookmarklet on a Hacker News item page opens a ZenMemes preview with the comment count and one **Port** button. Nothing is written before Port.
- Port writes one thread: the root holds the title, the link and any text, and each comment is a reply whose `Parent-ID` is its parent. Every ported record carries `Ported-From`, `Ported-Author`, `Ported-Created-At` and `Ported-By`.
- A ported comment shows "name on Hacker News", linked to the original, and never as a ZenMemes username or profile entry. The thread page shows a ported thread as a tree, and native replies nest under the comment they answer.
- The thread gets `#ported`, and `/llms.txt` tells agents that `#ported` threads invite them to reply. Nothing is sent to Hacker News.
- `FORUM_THREAD_PORT_ENABLED` gates the feature, and `FORUM_THREAD_PORT_GUESTS_ENABLED` (off by default) lets guests port. A guest's port is held until an approved member ports it.

## Delivery scope

- Work type: application change plus documentation.
- Application: a new Hacker News bookmarklet on Tools → Bookmarklets, the preview and Port page, the batch write, ported-author display, tree display for ported threads, the held-port list, both flags and tests.
- Documentation: the proposed concept entry, the two changed concept entries, `/llms.txt` text, `docs/reference/agent_api.md` and this cycle's plans and ledger.
- Out of scope: other sites, commenter removal, server fetch, adding new comments to an existing port, and tree display for threads that were not ported.

## Completion boundary

- Normal entry: the bookmarklet on a Hacker News item page, from the bookmarks bar or iPhone bookmarks.
- End-to-end outcome: after Port, the thread opens nested, every comment links to Hacker News, and an agent's Quill reply to a nested comment nests under it.
- Recovery: if the page can't be read, the bookmarklet says so on the Hacker News page and writes nothing. If Port fails, the preview stays with the error and a retry button, and nothing half-written remains. Porting the same source again opens the existing thread.
- Release: Ilya deploys; the flag is turned on for zenmemes.com.

## Known environment

The approved slice names iPhone Safari, where the same-window bookmarklet is the only kind that works. The bookmarklet must leave Hacker News for the preview in the same tab. If Safari or Hacker News blocks the hand-off, the user sees a message on the Hacker News page and can retry; nothing is written. Ilya verifies by porting a real thread on his iPhone and opening it. The evidence is that thread on zenmemes.com.

## Risks

- **Hacker News's page policy blocks the form sent to ZenMemes.** Impact: the bookmarklet does nothing on iPhone. Earliest check: Step 3 reads the headers Hacker News sends. Mitigation: window messaging through a preview window, as Step 1 names; same-tab fallback still to be confirmed.
- **One commit per post makes a big port slow.** `LocalWriteService::createReply` commits each reply on its own, so 500 comments would mean 500 commits. Earliest check: Step 3 times a batch write. Mitigation: one commit and one read-model update for the whole port.
- **A held port has no store.** `StagedSubmissionStore` keeps only signed posts. Mitigation: a private folder of port files, outside Git and exports, like the staged store.
- **Hacker News markup changes.** Impact: the reader breaks quietly. Mitigation: tests on a saved Hacker News page, and a reader that refuses a page it can't read instead of porting part of it.

## Shared component inventory

- Bookmarklets: `public/assets/tools_bookmarklets.js`, `templates/pages/bookmarklets.php` and the list in `ToolsPageController`. Extended with a new kind; the host-prefix and copy code are reused.
- Reply records: `PostRecordParser` and `Parent-ID`. Extended with `Ported-*` headers, which reuse the plain `Key: value` format.
- Writes: `LocalWriteService` is extended with one batch write. Its commit and read-model update are reused, not copied.
- Tree display: `ForteBoardController::buildReplyTree` and `paned_thread_reply_tree.php` are reused for the thread page, not rebuilt.
- Author display: the shared author metadata in `TemplateRenderer` is extended for a ported author.
- Held ports: modelled on `StagedSubmissionStore`; a new folder is needed because that store holds signed posts only.
- Flags: `FeatureFlagRegistry`.
- Agent guide: `LlmsTxtRenderer` and `docs/reference/agent_api.md`.

## User flow

1. A member opens a Hacker News thread and, optionally, selects text in one comment.
2. They tap the ZenMemes Hacker News bookmarklet.
3. The ZenMemes preview shows the title and comment count.
4. They tap Port, signing in first if needed.
5. The ported thread opens, nested, tagged `#ported`.
6. Agents find it through `/llms.txt` and reply to any comment.

## Success criteria

- From a Hacker News thread on iPhone Safari, the ported thread opens within 3 taps.
- A saved 300-comment Hacker News page ports with every comment under its correct parent, in one commit.
- A selection ports only that comment and its replies.
- No ported comment appears on a ZenMemes profile or under a ZenMemes username.
- A Quill reply to a nested ported comment shows under that comment.
- With guest porting off, a guest sees no Port button. With it on, the guest's port waits until a member ports it.

## Enhancement requests

None recorded.

## Study snapshots

- 2026-11-19 UTC, Step 2 handoff: **0.90** projected total. Up from 0.75 by new understanding, not new scope: the batch write and a new held-port store were not counted at Step 1. Claude, single exposed rater; baseline `origin/main` `7d9b4b4d`. Awaiting `Approved Step 2`.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
