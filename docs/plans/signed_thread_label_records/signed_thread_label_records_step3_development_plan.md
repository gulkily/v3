> **Feature plan:** [Step 1](./signed_thread_label_records_step1_solution_assessment.md) · [Step 2](./signed_thread_label_records_step2_feature_description.md) · [Step 3](./signed_thread_label_records_step3_development_plan.md) · [Step 4](./signed_thread_label_records_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- All online thread/reply Like, Flag, QDB vote, and tag controls sign their existing record format; source/activity detail exposes verified/unsigned status. Preserve eligible unsigned clients, guests, hashtags, and legacy offline intents. Unified storage is separately tracked in `todo.txt`.
- Recover clock skew, retries, interrupted writes, and stale derived state; reject invalid signatures without downgrade. Require targeted/full tests, real-browser signing across classic/Forte/QDB, isolated rebuild, and archive/import round trips. Production deployment is excluded; unavailable checks block completion.
- Eight approximately one-hour stages using existing infrastructure; resolve risks before dependents. After approval, branch, organize/commit plans, then commit each verified stage with its summary. Keep the separate todo edit outside the planning-only commit. Rescope before implementation if stage sizing fails.

## Key Risks
- **High risk: false attribution.** First compare browser/server bytes and identity resolution; verify exact bytes, author, target, and safe identifiers before writes or duplicate shortcuts.
- **High risk: lost evidence/invalid moderation.** First trace loaders and lifecycle companions; preserve pairs, reject invalid records, compare full/incremental results. Rollback retains canonical pairs and suspends writes until compatible verification returns.
- **High risk: duplicate votes/unusable fallback.** First inspect locking, prepared tokens, and stale-state recovery; bind preparation to identity/action and allow one automatic fallback. Verify both handlers and every theme’s controls.

## Stage 1
- Goal: signed online thread votes (~1h).
- Dependencies: approval; validate shared signer/writer reuse.
- Expected changes: optional exact record/signature envelope, safe client IDs, author/target/tag validation, paired commit; preserve unsigned contract.
- Verification approach: real signed Like/QDB vote; unsigned, malformed ID, wrong signer, tampering.
- Risks or open questions: inspect incomplete envelopes early; reject without unsigned downgrade.
- Canonical components/API contracts touched: shared browser signer, `thread_reactions.js`, `TagApiController`, `applyThreadTag(array): array`, format specs.

## Stage 2
- Goal: signed post/reply Like and every Flag (~1h).
- Dependencies: Stage 1.
- Expected changes: reuse envelope for post reactions; wire classic/Forte/QDB controls and assets.
- Verification approach: root/reply reactions, QDB Flag, approval-based scores/hiding, duplicates.
- Risks or open questions: inspect adjacent buttons’ different targets; enforce explicit family/ID.
- Canonical components/API contracts touched: `applyPostTag(array): array`, shared handlers, card/QDB/Forte markup.

## Stage 3
- Goal: recover clock skew (~1h).
- Dependencies: Stages 1–2.
- Expected changes: ±300-second tolerance; skew response supplies prepared bytes/token; second request finalizes. Reuse preparation/expiry conventions.
- Verification approach: boundaries, expired/altered token, identity switch; one normal/two skew requests.
- Risks or open questions: inspect token binding early; server expiry and bounded fallback prevent loops.
- Canonical components/API contracts touched: shared `prepareSignedReaction(string $kind, array $input): array`; existing endpoints retain success fields.

## Stage 4
- Goal: trustworthy imported/rebuilt reactions (~1h).
- Dependencies: Stages 1–3.
- Expected changes: shared optional-signature verification in both loaders and derived-state consumers; invalid-record diagnostics.
- Verification approach: valid/unsigned/invalid/missing-key/historical fixtures; full/incremental parity.
- Risks or open questions: inspect imported key availability first; resolve staged/local keys, never apply live clock limits to history.
- Canonical components/API contracts touched: canonical repository, signature verifier, builders, import planner.

## Stage 5
- Goal: inspect every record’s attribution (~1h).
- Dependencies: Stage 4.
- Expected changes: per-record derived status, shared detail/signature links; schema-version bump, schema documentation, startup rebuild regression per `CONTRIBUTING.md`.
- Verification approach: classic/Forte detail, repeated labels, mixed aggregates, prior-schema startup, raw-source byte equality.
- Risks or open questions: inspect activity deduplication first; retain status for records absent from activity.
- Canonical components/API contracts touched: schema/metadata/builders, activity readers, source metadata/controller.

## Stage 6
- Goal: preserve evidence through lifecycle operations (~1h).
- Dependencies: Stages 4–5.
- Expected changes: extend existing pair handling for archive/backup/restore/sync; paired conflict decisions.
- Verification approach: round trips, tampered companion, missing key, destination conflict; compare bytes/counts/status.
- Risks or open questions: inventory companion selection first; prevent tool-driven loss. Unsigned compatibility cannot detect deliberate signature removal.
- Canonical components/API contracts touched: archive command/catalog, import planner/publisher/recovery, backup paths.

## Stage 7
- Goal: safe interrupted-action retries (~1h).
- Dependencies: Stages 1–6.
- Expected changes: locked paired-write recovery, collision rejection, lost-response/stale-model completion; preserve unsigned duplicates.
- Verification approach: injected write/commit/refresh failures, restart/retry; one counted event, no overwrite.
- Risks or open questions: inspect duplicate shortcuts first; verify signatures and recover derived state before reporting completion.
- Canonical components/API contracts touched: both writers, commit/lock helpers, stale marker, optimistic feedback.

## Stage 8
- Goal: verify release boundary (~1h).
- Dependencies: Stages 1–7.
- Expected changes: finish integration wiring and operator/recovery documentation; record verification evidence.
- Verification approach: targeted then full `php tests/run.php`; isolated `./v3 rebuild`; real-browser/OpenPGP classic/Forte/QDB button matrix, guest/keyless/skew flows, fresh/cached assets, lifecycle round trip.
- Risks or open questions: establish browser prerequisites early; unavailable or failed checks block release.
- Canonical components/API contracts touched: existing test/browser harness, record/operator docs, implementation summary.

Waiting for "Approved Step 3" before branching, organizing/committing plans, or implementation.
