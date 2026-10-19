> **Feature plan:** [Step 1](./second_brain_step1_solution_assessment.md) · [Step 2](./second_brain_step2_feature_description.md) · [Step 3](./second_brain_step3_development_plan.md) · [Step 4](./second_brain_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Please review second_brain.txt and write an outline of features we have and don't have that would make this possible.

## Understood Intent

Compare the second-brain pattern in [second_brain.txt](../../../second_brain.txt) with this application. We have useful infrastructure, but the source-backed, self-maintaining knowledge system remains to be built.

## Feature outline

| Capability | What we have | What we still need |
| --- | --- | --- |
| Shared conversation | Threads, replies, identities, and tags can provide a shared topic workspace. | Reader, librarian, and checker roles with coordinated work and separate persistent memory. Private messaging currently supports two users, not group chat. |
| Reader and raw archive | Git-backed canonical text records and image attachments provide storage foundations. | URL fetching and snapshots, PDF extraction, audio upload/transcription, original-file preservation, source metadata, deduplication, and a rule preventing agents from editing originals. |
| Librarian and wiki | Durable posts, generated replies, and summary prompts. | Maintained topic pages, revisions, source-to-page dependencies, backlinks, and updates to every affected page. Posts and replies do not currently constitute this wiki lifecycle. |
| Answering from memory | Lexical related-post lookup and LLM post analysis. | Retrieval across sources and wiki pages, cross-session context assembly, source-grounded answers, and role-specific memory. Related-post matching alone does not provide these. |
| Saving useful answers | Agent responses can be published as forum replies. | A workflow that identifies useful answers and creates or updates cited wiki pages without duplicating existing knowledge. |
| Claim provenance | Post timestamps, record paths, Git history, and LLM exchange/dependency records provide audit foundations. | Every wiki claim linked to its evidence and a last-confirmed date; source publication, ingestion, and confirmation dates kept distinct. A page edit must not automatically reconfirm its claims. |
| Nightly checker | A background task queue, worker, retries, and cron setup. | Scheduled contradiction, stale-claim, and orphan-page checks; durable findings; a 30-day unconfirmed rule based on fresh supporting evidence. Worker scheduling alone does not implement these checks. |
| Human review | Signed identities, approval controls, and private-site access are available foundations. | Contradiction alerts directed to the owner, proposed changes, and resolution history that preserves competing evidence rather than silently replacing it. Membership approval is separate from knowledge-change review. |
| Reliable operation | Queue recovery and generation tracking exist. | Safe retry of multi-page wiki updates, visible partial failures, scoped bot write permissions, and enforcement that ingested instructions cannot authorize actions. |

Existing code anchors: [agent prompts and context](../../../src/ForumRewrite/Agent/AgentResponseGenerator.php), [reply publication](../../../src/ForumRewrite/Agent/AgentReplyFulfillmentService.php), [agent identity](../../../src/ForumRewrite/Agent/AgentIdentityService.php), [related-post lookup](../../../src/ForumRewrite/Analysis/RelatedContentSearchService.php), [worker](../../../src/ForumRewrite/TaskQueue/TaskQueueWorker.php), [read-model schema](../../../src/ForumRewrite/ReadModel/ReadModelSchema.php), and [private messaging limits](../../architecture/private_messaging.md). Status reflects source inspection, not a runtime acceptance test; missing entries mean no corresponding implemented workflow was found.

## Approaches

**Option A — Extend the forum.** Use threads as the intake and discussion surface, adding source records, wiki pages, and three role-specific workflows behind the existing worker.

- Pros: Reuses identities, publishing, access controls, history, and background execution.
- Cons: Requires new knowledge models and workflows; forum posts alone cannot express evidence and confirmation state reliably.

**Option B — Add a separate knowledge service.** Keep raw sources and the wiki in a dedicated service, with the forum providing conversation and review.

- Pros: Gives ingestion, retrieval, and wiki maintenance a dedicated boundary.
- Cons: Adds deployment, synchronization, permission, and recovery complexity.

## Recommendation

Prefer Option A, beginning with one topic: submit pasted source text, preserve it, produce one cited wiki page, ask a question and save a useful answer, then flag a conflicting source for review. Include a visible confirmation date and stale-status check. This is a complete initial user journey; PDF/audio ingestion and broad multi-page propagation can follow. Three logical roles can share one provider and worker; separate bot infrastructure is optional.

Backlogged at the user’s request on 2026-10-19. Step 1 remains unapproved; no further planning or implementation is authorized. The user explicitly authorized committing this assessment to main. Later navigation targets will exist if subsequent steps are approved.
