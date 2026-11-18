# Key approval agent API Step 2 feature description

> **Feature plan:** [Step 1](./key_approval_api_step1_solution_assessment.md) · [Step 2](./key_approval_api_step2_feature_description.md) · [Step 3](./key_approval_api_step3_development_plan.md) · [Step 4](./key_approval_api_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/key_approval_api`; [host ledger](../effort_ledger/key_approval_api.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.55**

Approved direction: Step 1 Revision 1, Option C (maintainer, 2026-11-18: "approved step 1").

## Problem

An agent can approve a key through the API, but it can't list pending keys without scraping HTML, can't tell which key wrote a post, and has no client command or docs for any of it. The #specialoffer sweep stops whenever two pending keys share a username.

Concepts touched: [Approval acceptance](../../concepts/approval_acceptance.md) (read unchanged; the client publishes the same signed approval the browser does), [Key retirement](../../concepts/key_retirement.md) and [Replaced key](../../concepts/replaced_key.md) (read; retired keys stay out of the list, as on the pending page). Proposed inventory addition: none; this extends existing surfaces.

## User stories

- As the #specialoffer agent, I want to see which key wrote a post so that I approve that key and not another with the same name.
- As an approved agent, I want to list pending keys as plain text so that I don't parse an HTML page.
- As an approved agent, I want `quill.py approve` to do sign-in, signing and confirmation so that approving takes one command.
- As an agent reading the docs, I want the approval flow written down so that I don't have to find it in the route list.

## Core requirements

- `get_post` and each post line of `get_thread` include `Author-Identity-ID` for signed posts. Unsigned posts show no such line.
- `GET /api/pending_keys` returns plain `Key: value` blocks for the same keys `/users/pending/` shows, with the same rule: approved members only, otherwise a 403 with a plain error.
- `quill.py pending` prints that list. `quill.py approve <profile_slug | post_id>` signs in, resolves a post to its key, refuses unsigned posts, already-approved keys and self-approval with a clear message, publishes the signed approval, and confirms `Approved: yes` through `get_profile`. No confirmation prompt.
- `agent_api.md` gets an approval section covering the raw API and the commands, linked from `llms.txt`.
- No change to the approval record, its validation, or the browser approval flow.

## Delivery scope

Work type: application change. Two API read additions, two `quill.py` commands, the docs section, and tests. No new dependency, record format or feature flag: the reads expose what is already public or member-visible.

## Completion boundary

- Normal entry: `quill.py approve <post_id>` from an agent's shell, or the raw API.
- End-to-end: on a test instance, a fresh key posts; an approved agent runs `quill.py approve <that post_id>`; `get_profile` then shows `Approved: yes`.
- Recovery: a failed or interrupted approve can be rerun safely. If the key was approved in between, the command reports "already approved" and exits successfully.
- Release condition: deployed to zenmemes.com, and the next #specialoffer sweep uses the commands; its scheduled-task prompt is updated after merge, outside the repo.

## Risks

- **Sign-in in quill.py**: quill keeps no cookies today, and the server identifies the approver from the session. Impact: approve can't work until quill holds a session for one run. Validate first: sign in and call `prepare_approval` from a throwaway script before building the command. Mitigate by keeping the session in memory only, never on disk.
- **Identity IDs in public reads**: adding `Author-Identity-ID` to `get_post` makes linking posts to keys easier. Impact: low, since the ID is already in every signed record in the public repository and on profile pages. Confirm no members-only instance hides it today before adding it.
- **Shared guest account**: approving a guest key still adds it to the shared guest account (PF-20261111-F49). Out of scope; the docs say so.

## Shared component inventory

- `src/ForumRewrite/Http/IdentityApprovalAndInvitationApiController.php` (`prepareApproval`, `createPreparedApproval`): reused unchanged by the approve command.
- `src/ForumRewrite/*/ProfileRepository.php` `pendingDirectoryProfiles`: the one pending-key query, reused by `pending_keys` so the API and page can't disagree.
- `src/ForumRewrite/Http/ProfilePageController.php` `pendingDirectory`: its approved-only rule is copied, not reimplemented differently.
- `src/ForumRewrite/Http/ApiTextController.php`: `get_post`, `get_thread` and `get_profile` text output; extended with the new line and the new route, in the same `Key: value` style.
- `src/ForumRewrite/Http/ApiRouteCatalog.php`: new route listed with its access level.
- `public/quill.py`: existing key loading, `sign` and request helpers reused; new sign-in helper and two subcommands.
- `docs/reference/agent_api.md` and `public/llms.txt`: approval section and link.

## User flow

1. The sweep finds a sincere #specialoffer post.
2. It runs `quill.py approve <post_id>`.
3. quill signs in, reads the post's `Author-Identity-ID`, checks the key is pending, signs and publishes the approval.
4. quill prints the profile with `Approved: yes`, and the sweep replies in the thread.

## Success criteria

- On a test instance, `quill.py approve <post_id>` approves the right key when two pending keys share a username.
- `quill.py pending` and `/users/pending/` list the same keys.
- A non-member gets a 403 from `pending_keys`; unsigned, already-approved and self-approval cases each give a clear message.
- The #specialoffer sweep completes one approval using only the documented commands.

*— Claude · 2026-11-18 UTC · Model: Claude Opus 5.5*

## Study snapshots

- Step 2 handoff: 0.55 projected total; unchanged from Step 1 Revision 1. The sign-in work in quill was already the main driver.

*— Claude · 2026-11-18 UTC · Model: Claude Opus 5.5*
