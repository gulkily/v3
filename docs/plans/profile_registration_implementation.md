# Registration checklist implementation evidence

The user authorized sequential implementation without Railspike on `fix/profile-registration-cost`, with a checklist update in each item commit. Retraction behavior follows the coordination handoff; no retraction simplification is implemented by this work.

REG-01 completed in `517844c7e`, following initial runner commit `56cbe951d`. See the [baseline](profile_registration_baseline.md) and retained results.

REG-02 adds duration breakdowns for profile/post loading, approval derivation, count derivation, profile updates, activity refresh and transaction commit. Both signed registration and historical linking retain existing timings and add lock hold duration. Identity-update metrics report profile/post counts, profile rows updated, activity statement count, activity row updates and process peak memory. These integer counts are returned separately from Server-Timing durations. They count SQL operations/matched rows, including repeat updates, rather than distinct semantic changes.

Full-rebuild selection reports the bounded reason `incremental_unavailable` or `incremental_update_failed`; diagnostics do not copy raw exception text or private material. Fallback behavior itself is unchanged. Benchmark reports retain these metrics, with existing success/integrity/source-preservation checks.

Verification: six focused benchmark/server-timing/identity fallback/parity checks passed, plus the execution-lock test covering successful return, timing fields, and release after callback failure. Both real-data benchmark cases passed. Fresh registration updated 3,846 profile rows and issued 3,847 activity updates; the activity loop took 15,512.7 ms of a 16,355.3 ms lock hold. Historical linking issued the same number of updates; its activity loop took 16,420.7 ms of a 17,185.4 ms lock hold. Approval derivation took 326.2/299.1 ms. [Full measured results](profile_registration_instrumented_results.json) retain counts, memory, and all durations. These are local runs on the matched QDB snapshot, not production-host measurements.

No schema identity, query semantics, dependencies, or public UI changed in REG-02. Whole-suite and production verification are not claimed.
