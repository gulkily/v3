> **Feature plan:** [Step 1](./thread_port_step1_solution_assessment.md) · [Step 2](./thread_port_step2_feature_description.md) · [Step 3](./thread_port_step3_development_plan.md) · [Step 4](./thread_port_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_port`; host ledger [thread_port.md](../effort_ledger/thread_port.md). Standing P2 enrollment.

Extended estimates: enabled. Stage estimates are copied from Step 3, not newly rated.

Cycle estimate: 0.95

Branch: `thread-port`, from `origin/main` `7d9b4b4d`; planning commit `2e97e6ca`.

Step 4 entry (2026-11-19 UTC): Step 3 projection **0.95** reaffirmed after the planning commit, before implementation. Claude, same exposed rater.

## Stage 1 - Ported headers and read-model columns

**Original estimate: 0.12**

**Retrospective: 0.12**

- Changes:
  - New `PortedSource` reads the four `Ported-*` headers, all or none, with Hacker News item addresses only. `PostRecord` gains `ported`.
  - `posts` gains `ported_from`, `ported_author`, `ported_created_at` and `ported_by`, filled by the full rebuild and both incremental insert paths. A retraction clears them. Schema 55 → 56; `read_model_schema_v1.md` updated.
  - Concept entry `docs/concepts/ported_thread.md` created.
- Verification:
  - `./v3 test ThreadPortRecordTest`: 3 passed. These cover parsing, partial and wrong-site rejection, and a schema-55 database rebuilt at startup before the columns are read.
  - `./v3 test --smoke EventWhenRecordTest TagUnlocksTest`: 498 passed, 0 failed.
- Notes: `tests/run.php` lists test files by hand; the new file is added there.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 2 - Ported bylines

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - The read model stores a ported post's `author_label` as "name on Hacker News" in the full rebuild and both incremental paths. Every view that prints a byline shows it with no further change, offline included.
  - `TemplateRenderer::renderAuthorHtml` links the site name to `ported_from`. The 11 queries that select `posts.author_label` also select `posts.ported_from`.
  - `RecentPostRepository` lists a ported thread by its root only.
  - `post_presentation.md` gains a Ported line.
- Verification:
  - `./v3 test ThreadPortRecordTest`: 4 passed. The new test checks both bylines link to Hacker News, no `/user/` or `/profiles/` link appears, and Recent shows the root but not the reply.
  - `./v3 test --smoke ThreadPortRecordTest RecentPostTest`: 489 passed, 0 failed.
- Notes:
  - Profiles needed no change: a ported post has no `Author-Identity-ID`, so `linkPostAuthors` and profile counts never see it. Signal actors count reactions, not authorship, so they are unchanged.
  - The offline snapshot copies `author_label`, so it shows the ported byline without the link.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 3 - One-commit write

**Original estimate: 0.18**

**Retrospective: 0.18**

- Changes:
  - New `Port\PortFile` and `Port\PortItem` read and write the plain-text port file (`hn-v1`). The reader refuses a file over 500 comments, a comment whose parent comes later or is missing, and malformed items, before anything is written.
  - `LocalWriteService::createPortedThread(PortFile, porterIdentityId)` writes all records plus one `ported` thread label in one commit. It then runs `synchronizePostDerivedState` per record and stops early if one falls back to a rebuild. A repeat port returns the existing thread.
  - Refused characters become `?` instead of refusing the port.
  - `Ported-By` now requires the `openpgp:<40 hex>` identity form, the same rule reaction records use.
  - `ported_thread.md` describes the file and the write.
- Verification:
  - `./v3 test ThreadPortWriteTest ThreadPortRecordTest`: 7 passed. These cover one commit for 3 and for 300 comments, every parent pair, the `ported` label, a repeat port, a full rebuild keeping parents and order, a missing parent refused with no commit, `%` escaping round-trips, and an emoji replaced by `?`.
  - Timing probe: 300 comments took 3.6 s in total (read model 3.5 s, about 12 ms per post), incremental path, no rebuild.
- Notes: production may be slower than this workspace. If needed, a later cycle can batch the incremental update.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
