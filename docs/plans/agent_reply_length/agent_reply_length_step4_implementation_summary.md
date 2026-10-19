> **Feature plan:** [Step 1](./agent_reply_length_step1_solution_assessment.md) · [Step 2](./agent_reply_length_step2_feature_description.md) · [Step 3](./agent_reply_length_step3_development_plan.md) · [Step 4](./agent_reply_length_step4_implementation_summary.md)

Debug mode: disabled

# Scripture Response Guidance and Expansion Implementation Summary

## Stage 1 - Initial response guidance

- Changes: Editable scripture guidance asks for direct, complete answers, actual relevant quotations, meaningful nuance, and natural stopping without quotas or templates.
- Verification: Generator suite: 7 passed; prompt routing and technical exhaustion behavior preserved. Qualitative live review follows in Stage 6.
- Notes: Initial answers must stand alone; no essential detail is withheld for expansion.

## Stage 2 - Canonical expansion provenance

- Changes: Added optional validated canonical Agent-Response metadata and derived JSON column; schema version 18, full/incremental replay, deterministic expansion publication IDs, and recovery of canonical writes interrupted before commit/read-model refresh. Metadata is passed through a server-only writer argument, not public input fields.
- Verification: Parser/schema/startup suites: 47 passed, including prior-version startup rebuild before page access. Writer round-trip/rebuild/replay test passed; an initial test cursor held a SQLite read lock and was corrected before the successful rerun. Isolated `./v3 rebuild` and normal page access passed. Diff check passed.
- Notes: Existing records need no migration; only the disposable read model changes. Existing expansion publication is reconciled under the canonical write lock.

## Stage 3 - Expansion request and fulfillment

- Changes: Added an internal expansion task, dedicated editable prompt with both source texts, request/status endpoints, verified scripture eligibility (including trusted historical request provenance), shared request deduplication, and targeted operator recovery. New scripture responses carry canonical provenance. Expansion rechecks source versions, visibility, and requester approval before and after generation and under the write lock; interrupted posting reconciles its stable canonical identity.
- Verification: Task/generator/store/worker and isolated expansion API tests: 43 passed. Covered duplicate claims/requests, canonical replay/rebuild, source edits/hiding, requester revocation during generation, exhaustion and explicit retry, and recursion rejection. Diff check passed.
- Notes: No separate LLM provider, automatic expansion, or response-length quota was introduced. Failed provider calls remain failures; stale source work is skipped without publication.

## Stage 4 - Shared reader controls

- Changes: Thread/post cards offer Expand only to eligible members, poll durable status without automatically retrying generation, open completed content for the requester, and retain accessible collapsed shared results. Expansion text is never injected as HTML. Canonical expansion children are presented once as supplementary content; their direct post route resolves through the validated initial response.
- Verification: Request/publication/rebuild rendering test passed. Chromium checks passed on thread/post pages at 1280px and 375px: keyboard request, polling/progress, requester auto-open, collapse/reopen, shared completed display, safe text, and no viewport overflow. JS syntax/diff checks passed.
- Notes: Native details/summary provides keyboard semantics. Fixed an existing long agent-feedback label overflowing narrow action rows while validating this experience.

## Stage 5 - Cached and offline delivery

- Changes: Expansion publication invalidates the initial response artifact. Snapshot schema 4 includes provenance and unavailable-expansion IDs; exports omit stale/hidden/retracted source expansions, and updates remove them from older caches outside the recent-thread window. Offline rendering groups supplementary content once beneath its initial answer and supplies no generation controls. Legacy snapshot columns upgrade during update.
- Verification: Snapshot/export/reader and visibility suites: 18 passed after correcting an uncommitted test-only source mutation. Actual static detail generation and initial-artifact invalidation passed. Browser checks passed for stored offline content, keyboard expansion, schema 3 compatibility/update, and source-revocation removal, alongside the four online viewport/surface cases. Diff/JS syntax checks passed.
- Notes: Offline users can read stored material; generation still needs connectivity. Public snapshots contain no request identities or private diagnostics. Existing membership-only publication restrictions remain in force.

## Stage 6 - Complete verification and guidance

- Changes: Finished operator/API guidance. Live review prompted stronger exact-translation, conversational-focus, and added-value instructions, without numeric length/verse targets. Expansion context now supplies the original concern and prior agent answer once each, with explicit authorship. Requester auto-open also works when session storage is blocked.
- Verification: Isolated detached-worktree regression run: 290 passed, including the full write API suite, schema upgrade, parsers, generation, queue/recovery, snapshots, and offline presentation. After final refinements, targeted generator/publication/source-race checks: 10 passed. All changed PHP passed lint; JS syntax and diff checks passed.
- Verification: Final Chromium checks passed on thread/post pages at desktop/mobile widths, including blocked-storage auto-open, safe generated-text rendering, keyboard collapse/reopen, shared results, offline stored content, legacy schema/update compatibility, and source-revocation removal. Actual static detail generation and isolated CLI rebuild were verified earlier.
- Live review: Eight provider calls total, all published only in temporary canonical repositories. Reviewed simple fatigue, forgiveness/boundaries, supplied quotation, and doubtful-reference cases with initial/expanded answers. Baseline samples mixed translation wording and repeated some material; review stopped for prompt refinement. The remaining pair corrected the fictitious Hezekiah reference and used identifiable KJV passages. Translation/quotation guidance and duplicate-context cleanup are editorial improvements, not a scripture-verification engine; repeated phrasing and verbosity remain model-quality considerations rather than hard length gates.
- Notes: No live production threads were modified, no remote deployment was performed, and no provider/token allowance escalation was used. Separate temporary queue/scoring/exchange databases kept verification away from deployment state. Application startup upgrades the disposable read model from schema 17 to 18 automatically.
- Final visibility checks: Supplementary records are excluded from ordinary profile post lists and thread RSS bodies; hidden thread roots also emit offline expansion-removal IDs when the original concern is a nested reply. Two targeted surface/privacy tests passed after these checks were added.
- Quotation spot-check evidence: Baseline translation labeling was compared with [Mark 6:31 NIV](https://www.biblegateway.com/passage/?search=Mark+6%3A31&version=NIV). Revised-sample wording was compared with [Galatians 6:2 KJV](https://www.biblegateway.com/passage/?search=Galatians6%3A2&version=KJV) and [Philippians 2:12–13 KJV](https://www.biblegateway.com/passage/?search=Phil.+2%3A12%E2%80%9313&version=KJV); these checks support the review, not an automated accuracy guarantee.
