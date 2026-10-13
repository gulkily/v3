> **Feature plan:** [Step 1](./signed_thread_label_records_step1_solution_assessment.md) · [Step 2](./signed_thread_label_records_step2_feature_description.md) · [Step 3](./signed_thread_label_records_step3_development_plan.md) · [Step 4](./signed_thread_label_records_step4_implementation_summary.md)

Debug mode: enabled

# Signed labels and reactions implementation

Branch: `feature/signed-thread-label-records`. Planning commit: `2cbbe624`.

## Stage 1 - Signed thread actions
- Changes: browser builds/signs canonical thread labels; writer verifies exact action/author/bytes, safe IDs, and signatures before duplicate handling; commits record/signature together. Documented optional signatures in both format specs.
- Verification: PHP lint, Node syntax check; `php tests/run.php WriteApiSmokeTest::testSignedThreadReactionPreservesBytesAndRejectsTampering ReadModelThreadLabelsTest` (3 passed). Real GPG signing verifies exact persistence and rejects altered bytes, unsafe IDs, mismatched actions, and incomplete/invalid signatures.
- Notes: full browser/QDB coverage is scheduled in Stage 8; clock fallback, historical validation, and interruption recovery remain scheduled stages. Existing unsigned path is retained.
