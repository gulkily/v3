> **Feature plan:** [Step 1](./personal_inbox_step1_solution_assessment.md) · [Step 2](./personal_inbox_step2_feature_description.md) · [Step 3](./personal_inbox_step3_development_plan.md) · [Step 4](./personal_inbox_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/personal_inbox`; [host ledger](../effort_estimation_trial.md#cycle-v3personal_inbox). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.85** (Step 3 projection reaffirmed at Step 4 entry)

Branch: `feat/personal_inbox`, planning commit `33a76c03`. Starting revision `f343b626`.

## Step 4 entry

2026-11-07, Step 3 approved by the maintainer ("approved step 3"). The Step 3 projection of 0.85 is reaffirmed with no change. Stage identities and original stage-work estimates are copied from the [Step 3 plan](./personal_inbox_step3_development_plan.md): Stage 1 0.12, Stage 2 0.06, Stage 3 0.14, Stage 4 0.06, Stage 5 0.10, Stage 6 0.08, Stage 7 0.05, Stage 8 0.06. Single exposed Claude rater; copying is not a new rating.

Ledger links were repaired to the feature folder in the Stage 1 commit, because the planning commit excludes the ledger.

## Stage 1 - Flag, route and unread conversations

**Original estimate: 0.12**
**Retrospective: 0.10**

- Changes:
  - `FeatureFlagRegistry` registers `FORUM_INBOX_ENABLED` ("Inbox", default off, site mutable), appended to the Forum experience group.
  - `Application` routes `/inbox` and `/inbox/`. With the flag off they answer "not found". The paths join the live application routes.
  - New `InboxPageController` and `templates/pages/inbox.php`. Signed-out and unapproved viewers get `private_message_access.php`, which now takes a `featureName` so its copy reads "Inbox is for approved members". Messages copy is unchanged.
  - `PrivateMessageReadState::unreadConversations`, exposed through `PrivateMessageStore` and `PrivateMessageMailboxService`, lists counterparts with unread messages, newest arrival first, at most 25.
  - The Messages section shows each conversation as a thread row with its name, unread count and latest time, plus an "All messages" link. With private messages off the section is omitted. If private storage fails, the section shows Retry and the page still renders.
- Verification:
  - `./v3 test --smoke InboxPageTest FeatureFlagEvaluatorTest PrivateMessageReadStateTest PrivateMessagePageControllerTest`: 445 run, 444 passed. The one failure, `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, also fails with this stage's changes stashed; the runner reports it as long-standing.
  - `InboxPageTest` (new, registered in `tests/run.php`): flag off gives "Not Found"; signed out gives the Inbox access copy; unread conversations list newest first with counts and no ciphertext; empty, failed-store and messages-disabled states; unapproved viewer.
  - Full suite not run, per project policy.
- Notes:
  - Reuse decision changed from Step 3: Messages rows do not reuse `private_message_conversation_row.php`. That partial needs the decryption scripts to fill its preview, and Inbox lists only who wrote and how many. Rows use the existing thread-row classes instead, so no new CSS was needed.
  - Ledger links now point into the feature folder.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Parent index

**Original estimate: 0.06**
**Retrospective: 0.04**

- Changes:
  - `ReadModelSchema` adds `posts_parent_idx (parent_id)`; `ReadModelMetadata::SCHEMA_VERSION` goes from 50 to 51.
  - `docs/specs/read_model_schema_v1.md` lists the index on `posts` and adds a Version 51 section.
- Verification:
  - `InboxPageTest::testStartupRebuildsAVersion50ReadModelWithTheParentIndex`: a read model set back to version 50 without the index is rebuilt on the next request, and a lookup by parent uses the index.
  - `./v3 rebuild` on a scratch copy of the minimal fixture: 3 posts, 2 threads; the result reports schema 51.
  - `./v3 test --smoke InboxPageTest ActivityProjectionTest` at commit `0eee8731` also failed `ReadModelSchemaTest::testCanonicalStatementsCreateExpectedTablesAndIndexes`, which lists every index. The stage was committed before that failure was read. A follow-up Stage 2 commit adds `posts_parent_idx` to its expected list.
  - After the fix, `./v3 test --smoke InboxPageTest ReadModelSchemaTest`: 438 run, 437 passed; only the baseline `BoardListTest` failure remains. `ActivityProjectionTest` and `BoardSearchTest`, which check the schema version, pass.
  - Full suite not run.
- Notes:
  - Deploying this stage triggers the normal startup rebuild once.
- Estimate details: original scope as planned; uncertainty low; no revision.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
