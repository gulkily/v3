# Registration baseline: REG-01 progress

Work resumed on `fix/profile-registration-cost` on 2026-10-24, without Railspike, following the user's instruction to commit each item with its checklist update. REG-01 remains **partial**: the benchmark runner is verified, but the matching production canonical repository is unavailable.

The [benchmark runner](../../scripts/benchmark_profile_registration.php) accepts a local Git checkout and a standalone SQLite database with the same recorded repository head. It creates separate clones/database copies for two operations: fresh signed registration through prepare/sign/finalize, and linking a fresh identity to an existing anonymous root post. It uses the real `LocalWriteService`, writes per-case counts and timing results to `report.json`, checks SQLite integrity, and verifies that the input database hash, canonical HEAD and clean source status remain unchanged. Generated test private keys are cleaned up. It does not call production write endpoints, execute task workers, or modify the downloaded database.

Run it with a new output directory whose parent already exists:

```bash
php scripts/benchmark_profile_registration.php \
  --repository-root=/path/to/matching/local/repository \
  --database-path=/path/to/read_model.sqlite3 \
  --output-dir=/path/to/benchmarks/new-run \
  --site=qdb
```

For later schema comparisons, `--rebuild-copy` rebuilds only the isolated working databases before measurement. Keep the source pair at matching heads. Archive extraction and checkout preparation are separate from this runner; never execute downloaded repository hooks/configuration as part of setup.

Current source acquisition:

| Input | Status |
| --- | --- |
| `https://qdb.us/downloads/read_model.sqlite3` | Downloaded; quick_check passed; 31,850,496 bytes. |
| SHA-256 | `7b93d656a31eb0aec723147eedac5ec0b6123821bb15338c7babb9c7762c753e` |
| Database canonical head | `f13b31c925715d0f3fcf06dfebcd9a48ab04714f` |
| Database counts | 18,760 posts; 18,739 threads; 3,845 profiles; 18,863 activity rows. |
| `https://qdb.us/downloads/repository.tar.gz` | Earlier attempts returned 503. On resume, one request timed out after 30 seconds and the final request returned 503 after 50.38 seconds. |
| Archive request with `?revision=f13b31c925715d0f3fcf06dfebcd9a48ab04714f` | Returned 409 on resume. The endpoint checks the current head; it does not export arbitrary historical revisions. |

Inputs, download headers, and the acquisition manifest are retained under ignored `state/benchmarks/qdb-20261024/`. A complete current archive with Git history may allow an isolated checkout of the database's older recorded head; that match must be verified rather than assumed. A filtered offline snapshot cannot substitute for the complete canonical dataset.

Validation performed:

- `php tests/run.php RegistrationBenchmarkTest`: 1 integration test passed, covering both real write flows, independent clones, input preservation, key cleanup, refusal to overwrite an existing run, and rejection of mismatched repository/database heads before writes.
- PHP syntax and `--help` checks passed.
- A small fixture run completed with `write_incremental` for both cases: fresh signed registration 114.1 ms and historical linking 36.6 ms. These are local harness checks, **not QDB-scale measurements or production performance estimates**. The run is retained at `state/benchmarks/registration-fixture-run-01/report.json`.
- No new dependencies, production schema changes, or registration behavior changes.

Still required before closing REG-01: acquire and verify the matching canonical records/history, run both cases at QDB scale, retain repeated-run results and representative fixture characteristics, and establish the controlled scaling inputs. Further checklist items have not begun.

The [retraction simplification handoff](retraction_simplification_handoff.md) has been read. REG-17, REG-20 and REG-F01/F02 now distinguish authorization/transaction correctness from the stronger immediate-suppression contract being reconsidered. No retraction guards were changed.
