> **Feature plan:** [Step 1](./signed_thread_label_records_step1_solution_assessment.md) · [Step 2](./signed_thread_label_records_step2_feature_description.md) · [Step 3](./signed_thread_label_records_step3_development_plan.md) · [Step 4](./signed_thread_label_records_step4_implementation_summary.md)

Debug mode: enabled

# Signed labels and reactions implementation

Branch: `feature/signed-thread-label-records`. Planning commit: `2cbbe624`.

## Stage 1 - Signed thread actions
- Changes: browser builds/signs canonical thread labels; writer verifies exact action/author/bytes, safe IDs, and signatures before duplicate handling; commits record/signature together. Documented optional signatures in both format specs.
- Verification: PHP lint, Node syntax check; `php tests/run.php WriteApiSmokeTest::testSignedThreadReactionPreservesBytesAndRejectsTampering ReadModelThreadLabelsTest` (3 passed). Real GPG signing verifies exact persistence and rejects altered bytes, unsafe IDs, mismatched actions, and incomplete/invalid signatures.
- Notes: full browser/QDB coverage is scheduled in Stage 8; clock fallback, historical validation, and interruption recovery remain scheduled stages. Existing unsigned path is retained.

## Stage 2 - Signed post Likes and Flags
- Changes: post reactions reuse the same browser/server signature envelope; existing classic, Forte, and QDB handlers require no separate markup. Registered the existing post-reaction read-model tests, previously absent from the runner.
- Verification: real-GPG API regression covers root/reply Like and Flag, exact signature persistence, moderation count, and duplicate submission; passed. Node syntax passed. Existing ReadModelPostReactionsTest passed (1 test).
- Notes: inspected existing lazy-signing assets and both action contracts; theme/browser interaction coverage remains Stage 8.

## Stage 3 - Bounded clock fallback
- Changes: signed requests outside ±300 seconds return prepared bytes/token with `clock_skew`; browser validates unchanged action fields, signs once more, and finalizes through the same endpoint. Tokens reuse existing ten-minute storage/expiry and bind exact bytes and author.
- Verification: three signed-reaction regression methods passed, including real-GPG HTTP fallback for both families, expired tokens, successful finalization, and duplicate finalization. PHP/Node syntax passed.
- Notes: correction to Stage 2 count: ReadModelPostReactionsTest ran two tests, both passed. Historical signature verification in Stage 4 will not enforce the live clock window.

## Stage 4 - Canonical and imported verification
- Changes: both canonical loaders verify optional signature companions against author keys; full/incremental reducers therefore reject invalid signed input. Import planning resolves source keys with destination fallback. Viewer/duplicate scans skip invalid records. Added invalid post-reaction diagnostics and bounded content-keyed verification caching.
- Verification: 15 write/import/read-model tests passed; additional real-GPG historical test passed, proving old valid records import/rebuild while corrupted companions are rejected and removed from derived scores.
- Notes: no live timestamp cutoff in historical verification; unsigned records continue through existing reducers. Missing keys cannot produce verified status.

## Stage 5 - Per-record evidence and inspection
- Changes: schema 17 adds `reaction_signatures` for all records, including activity-deduplicated labels. Full/incremental refreshes maintain it; shared activity/source metadata reports verified/unsigned/invalid and current raw-source responses add a status header without changing bytes.
- Verification: schema and signed-record tests passed (4); version-16 normal-page startup rebuild and historical evidence tests passed (2). `./v3 rebuild tests/fixtures/parity_minimal_v1 /tmp/signed-reactions-stage5b.sqlite3` succeeded. Fixed a test-only open SQLite cursor that blocked a rebuild before rerunning.
- Notes: schema reference updated; aggregate scores are not marked universally signed. Production data was not rebuilt.

## Stage 6 - Signature lifecycle preservation
- Changes: thread archives now collect/remove signature companions alongside records and retain supporting author keys. Legacy repository archive import uses the shared reaction plan for signature validity and paired conflict decisions; modern sync/import already uses that planner. Whole-repository downloads include companions without extension filtering.
- Verification: archive, legacy import, and import planner tests passed (13). Real-GPG round trip passed: archive both signed families, confirm both companions leave the live repository, extract/restore exact bytes, rebuild, and recover two verified evidence rows.
- Notes: intentionally stripped signatures remain indistinguishable from legacy unsigned records; supported archive paths now preserve evidence.
