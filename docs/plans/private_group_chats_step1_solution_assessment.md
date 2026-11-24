# Private group chats Step 1 solution assessment

> **Feature plan:** [Step 1](./private_group_chats_step1_solution_assessment.md) · [Step 2](./private_group_chats_step2_feature_description.md) · [Step 3](./private_group_chats_step3_development_plan.md) · [Step 4](./private_group_chats_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_group_chats`; [host ledger](./effort_ledger/private_group_chats.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.70 (recommended first slice, Option A1)

## Original Query

It would be really helpful for ZenMemes/v3 to support private group chats. I think a public/open group chat can already be done as a thread, but private ones are not possible yet. Can we implement this, please?

## Understood Intent

Ilya wants a few members to talk together where nobody else can read along. Today v3 has two shapes of conversation. A thread is public: its posts are text files in Git, shown to everyone. A private message (DM) is between exactly two composite users. The browser encrypts each DM to every approved key of the sender and the recipient. The server keeps only the encrypted envelope in a private SQLite store, never in Git, the read model, static pages or the offline snapshot. The [first messaging cycle](./private_messaging/private_messaging_step2_feature_description.md) left group messages out of scope. The [feature priorities](../product/v3_feature_priorities.md#release-gate-for-private-messaging-and-account-ownership) ask to settle recipient-key control before adding group messages.

## Problem

Three or more members cannot hold one private conversation on ZenMemes, because a DM has exactly two parties and every thread is public.

Concepts touched: [Private-message history](../concepts/private_message_history.md) (Options A and D; changed by A, used by D), [Feature flags](../concepts/feature_flags.md) (one new flag in A, B and C), [Thread activity](../concepts/thread_activity.md) and [Thread title](../concepts/thread_title.md) (used by B and C). Option A proposes a "group conversation" concept entry; Option C proposes a "private thread" entry.

Feedback items: none ask for group chats. The release gate in `docs/product/v3_feature_priorities.md` is related and appears under Option A below.

## Option A — Group conversations in Messages, end-to-end encrypted
**Estimate: 1.10** — driver: a membership record, a recipient list of several users, and a group view of the conversation page; uncertainty: how membership changes and history for new members should behave.

Extend Messages. A member starts a group from Messages, gives it a name and picks members. The browser encrypts each message once, to every approved key of every member. The server stores the envelope in the same private store as DMs, with a group ID instead of a counterpart. The Messages list shows groups beside DMs, and the conversation page, composer, saved copies and Unread work for groups too.

- Same privacy promise as DMs: the operator holds only ciphertext and delivery metadata.
- Reuses the composer, reader, saved copies, unread marks and history sync rather than building a second chat.
- Keeps message content out of Git, like DMs. That follows the DM decision, though it departs from "the record is plain text in Git".
- A removed member keeps what they already decrypted; removal only stops future messages, as in any encrypted group chat.
- A new member cannot read older messages unless a current member's browser shares access. The DM history sync does this for new devices, so it can extend to new members later.
- The release-gate risk grows. A same-name key approved into one member's username group would read every group that member is in.
- Needs JavaScript, like DMs. A browser without JavaScript can still reach the page but cannot send.

## Option A1 — Option A, first slice: fixed membership
**Estimate: 0.70** — driver: the group record and the N-user recipient list; uncertainty: the conversation-page changes for several senders.

The same design as Option A, cut to a vertical slice. The creator picks the members once; any member can leave. Adding members and history for new members become the next two cycles.

- Fits one cycle; Option A as a whole sits just over 1.0.
- No history question yet, since everyone is a member from the first message.

## Option B — Encrypted private threads in the public record
**Estimate: 1.60** — driver: decrypting thread pages in the browser across board, thread, static and offline views; uncertainty: high.

Post the thread's root and replies as ordinary Git records whose bodies are encrypted to the member list. The thread page decrypts in the browser for members and shows a locked card to everyone else.

- The conversation moves with the repository, like the rest of the record.
- Reuses threads, replies, tags and reactions.
- Publishes ciphertext, member names and timing to every clone forever. A key leaked years later opens the whole history.
- Breaks the DM release condition that public outputs contain no envelope.
- Every view that renders a post needs a locked state, which is most of the site.

## Option C — Members-only threads guarded by the server
**Estimate: 0.80** — driver: hiding a thread from every public route, export and feed; uncertainty: finding every route that lists posts.

Ordinary threads in plain text, marked members-only. The server keeps them outside Git and shows them only to signed-in members of the list.

- Full thread features, works without JavaScript, and agents can read it with a session.
- The operator and anyone with the server can read everything, which is weaker than DMs.
- One missed route, feed or export leaks the conversation in plain text.
- Plaintext outside Git breaks both the DM privacy model and the plain-text-in-Git rule.

## Option D — Send to several people as separate DMs
**Estimate: 0.30** — driver: a multi-recipient composer; uncertainty: low.

Let the composer pick several recipients and send one ordinary DM to each.

- Very small, with no new storage.
- Not a group chat: each recipient sees a separate two-person conversation and replies go only to the sender.

## Recommendation

Option A, delivered as three cycles: A1 now (fixed membership), then adding members, then history for new members. Option A keeps the privacy promise members already have in DMs and reuses most of the messaging code. Option B leaks too much over time, Option C weakens privacy, and Option D is not a group chat.

The vertical slice for A1: Ilya opens Messages, starts "Rap Club board" with two other members, and sends a message. Both members see the group in Messages and in Unread, read the message and reply. A member who is not in the group cannot list it or fetch its envelopes. A member who leaves stops receiving new messages. One feature flag, `FORUM_GROUP_MESSAGES_ENABLED`, off by default, requires `FORUM_PRIVATE_MESSAGES_ENABLED`.

Questions for Step 2, with proposed defaults:

- Who may start a group: approved members, like DMs.
- Group size: at most 20 members, so encryption stays quick on an iPhone SE.
- Group name: set by the creator; renaming waits for the membership cycle.
- Release gate: show each group's member list with key counts, and accept the same-name key risk for now, as DMs do today. Or block groups until account ownership is settled.
- Agents: no Quill support in A1; the API stays usable by agents with a session.

Enhancement requests: none beyond the original request.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Revision 1 — depends on private key access

Maintainer, 2026-11-24: split approval for public and private use, with private access needing a same-username key or an operator; downgrade existing keys that fail that rule.

That work is its own cycle, [private_key_access](./private_key_access_step1_solution_assessment.md), and ships first. Option A1 then encrypts only to keys with private access. This settles the release-gate question above; the other Step 2 questions stand. Recommendation and estimate unchanged.

*— Revised by Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.70 projected total for the recommended scope. Baseline `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`, scope v1, R1, P2; Claude single exposed rater, 2026-11-24 UTC. R1 hashes not re-verified this session.
- Step 1 handoff: 0.70 for Option A1. Options A 1.10, A1 0.70, B 1.60, C 0.80, D 0.30; not summed. Option A above 1.0 prompted the A1 split.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
