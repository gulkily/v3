# Resource measurement

Purpose: Associate workload, environment and recovery evidence with resource observations without turning a local sample into a host guarantee.

Scenario: An operator reproduces a pinned canonical fixture through ordinary HTTP/CLI entries. The disposable collector records PHP allocations, sampled process/child RSS, physical I/O, time, result parity and failures separately. A failed candidate retains its prior live output; the report records the retry and marks untested roles, shapes and deployment environments explicitly.

Sources: [measurement contract](../plans/application_capacity_study/measurement_contract.txt), [archived collector](../plans/application_capacity_study/collector.py.txt), [reproduction](../plans/application_capacity_study/reproduction.txt), [evidence and limits](../plans/application_capacity_study/capacity_report.txt).

Boundary: This is an isolated study workflow, not permanent application telemetry or a benchmark CLI product. Process samples miss short-lived children; selected rows do not establish loaded rows; warm-cache zero physical reads do not mean zero I/O work. Earlier process-group-only samples and harness failures retain their original limitations. No production load or dependency installation is implicit.

*— Codex · 2026-11-01 UTC*
