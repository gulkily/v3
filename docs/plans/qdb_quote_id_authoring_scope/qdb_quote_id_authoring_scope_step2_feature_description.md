> **Feature plan:** [Step 1](./qdb_quote_id_authoring_scope_step1_solution_assessment.md) · [Step 2](./qdb_quote_id_authoring_scope_step2_feature_description.md) · [Step 3](./qdb_quote_id_authoring_scope_step3_development_plan.md) · [Step 4](./qdb_quote_id_authoring_scope_step4_implementation_summary.md)

## Problem

On QDB, the active site profile currently determines every new thread's ID format. As a result, ordinary threads created through `/compose/thread` or the generic thread API are incorrectly assigned numbered QDB quote IDs.

## User Stories

- As a QDB contributor, I want Add Quote submissions to receive sequential quote IDs so that quotes retain their numeric permalinks and listing behavior.
- As a QDB contributor, I want a `/compose/thread` submission to create a regular thread so that non-quote discussion is not misclassified as a quote.
- As a browser-signing user, I want signed Add Quote and regular-thread submissions to keep the same ID semantics as their unsigned equivalents.

## Core Requirements

- The QDB Add Quote authoring path is the sole path that allocates a QDB quote ID.
- QDB `/compose/thread` and the generic create/prepare-thread API paths allocate regular thread IDs by default.
- Signed preparation and finalization preserve the originating authoring path's ID type.
- Existing quote IDs, numeric permalink resolution, quote listings, and non-QDB thread creation remain unchanged.
- No database migration or reassignment of existing records is permitted.

## Delivery Scope

- Work type: application change — QDB authoring-route separation, browser submission coordination, regression coverage, and implementation summary.
- Out of scope: changes to existing records, quote-list eligibility or presentation, database schema, QDB navigation redesign, and a general API redesign.

## Completion Boundary

- Normal entry: a contributor submits either QDB Add Quote or `/compose/thread`.
- End-to-end outcome: Add Quote produces the next numbered QDB quote; the general composer produces a regular thread; signed and unsigned flows agree.
- Recovery: a failed submission retains its current validation/error behavior, and a normal thread remains reachable at its regular thread permalink even though quote collections exclude it.
- Release condition: focused direct-create and signed-prepare/finalize coverage proves both ID formats and existing QDB quote behavior; the relevant suite passes and the implementation summary records evidence.

## Risks

- Signed preparation and finalization could select different ID types. Impact: a valid signature cannot be finalized or a post is misclassified. Earliest validation: signed Add Quote and regular-thread flows. Mitigation: give both preparation and creation paths the same explicit authoring contract.
- A generic QDB API call could still allocate a quote ID. Impact: non-quote threads pollute quote numbering and listings. Earliest validation: direct create and prepare API coverage without quote-path context. Mitigation: make regular-thread allocation the default and reserve quote allocation for its dedicated path.
- A regular QDB thread may be mistaken for a missing quote in QDB collections. Impact: confusing post-submit navigation. Earliest validation: create one of each ID type and verify their established destinations. Mitigation: preserve direct thread permalinks and existing quote-only collection rules.

## Shared Component Inventory

- `qdb_add.php` and `QdbExperience`: extend the canonical Add Quote entry to select quote authoring; retain its current compact presentation.
- `thread_compose_form.php`: reuse the shared thread form while allowing its caller to select the appropriate authoring submission path; do not fork the form.
- `browser_signing.js`: extend the shared signed and unsigned thread submission contract so it follows the form's authoring path.
- `Application` and `WritePostAndIdentityApiController`: extend the canonical HTTP/API routing surface for the dedicated quote operations; keep generic thread operations regular.
- `LocalWriteService` and `QdbQuoteNumbers`: reuse the existing ID allocation and prepared-post lifecycle, scoped by authoring operation rather than the site profile alone.
- `QdbBoardPolicy`, `QdbExperience` numeric resolution, and quote-card templates: reuse unchanged; they remain the canonical consumers of existing quote IDs.

## Simple User Flow

1. A contributor opens QDB Add Quote and submits a quote.
2. The quote authoring path creates the next numbered QDB quote and returns its normal quote/thread destination.
3. A contributor opens `/compose/thread` on QDB and submits a regular thread.
4. The regular authoring path creates a normal thread ID and returns its normal thread permalink; quote collections retain only numbered quotes.

## Success Criteria

- An unsigned QDB Add Quote receives a sequential `-qdb-<number>` ID, while an unsigned QDB generic thread does not.
- Signed preparation and finalization produce the matching ID type for each authoring path.
- Generic QDB create/prepare API requests without quote-path context produce regular thread IDs.
- Existing numbered quote permalink resolution and quote-list filtering continue to pass.
- Non-QDB thread ID behavior remains unchanged.
