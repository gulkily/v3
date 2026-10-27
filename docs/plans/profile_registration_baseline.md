# Registration baseline: REG-01 progress

Work resumed on `fix/profile-registration-cost` on 2026-10-24, without Railspike, following the user's instruction to commit each item with its checklist update. The initial partial runner was committed as `56cbe951d`. The user subsequently supplied the complete repository archive, resolving the source-data blocker; the results below supersede the initial unavailable-data status.

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
| User-supplied archive | `repository.tar.gz.1`, 937,403,333 bytes; gzip CRC/integrity passed. |
| Archive SHA-256 | `537c3dc3d21ec5dc1dbded9bf8cecb3cef6815f0ff784d9cf4c8864129774941` |
| Archive HEAD | `5661621eb648be28fc13884fac0145194e5eaa5b`; the database's older head is present in its history. |

Inputs, download headers, inventory, extraction details, and the updated acquisition manifest are retained under ignored `state/benchmarks/qdb-20261024/`; the original archive is preserved at the project root and is not committed. Extraction accepted regular files and safe paths only, excluded downloaded Git configuration/hooks/index/reflogs/alternates/replace refs, and supplied a minimal local configuration. A clean clone at `matched-repository/` checks out the database's exact canonical head. Git connectivity validation passed; canonical files match the 18,760 post and 3,845 identity database counts. The benchmark checks matching heads and clean inputs again on every run.

The archive's size is mostly Git storage: 924,958,268 bytes in `.git/objects`, compared with 15,302,484 bytes in current canonical records. The extracted repository contains 70,943 loose objects and no packs. Its saved `gc.log` reports `fatal: unable to create thread: Resource temporarily unavailable` and `fatal: failed to run repack`. A local clone packed the same 70,943 objects into 20,445 KiB. This is a separate maintenance finding; no production cleanup or history rewrite was performed. Benchmark clones use packed history, so Git timings do not reproduce production's loose-object layout.

Validation performed:

- `php tests/run.php RegistrationBenchmarkTest`: 1 integration test passed, covering both real write flows, independent clones, input preservation, key cleanup, refusal to overwrite an existing run, and rejection of mismatched repository/database heads before writes.
- PHP syntax and `--help` checks passed.
- A small fixture run completed with `write_incremental` for both cases: fresh signed registration 114.1 ms and historical linking 36.6 ms. These are local harness checks, **not QDB-scale measurements or production performance estimates**. The run is retained at `state/benchmarks/registration-fixture-run-01/report.json`.
- No new dependencies, production schema changes, or registration behavior changes.

Controlled inputs are the checked-in `tests/fixtures/parity_minimal_v1` small fixture (3 posts, 2 threads, 1 profile, 5 activity rows) and the pinned QDB snapshot above. Both real registration flows run on independent, fresh copies. Later scaling checks must vary unrelated counts independently and preserve a mapping of intentionally added/removed records; the two baseline sizes alone do not establish an asymptotic performance claim. Benchmark timing excludes clone/key-generation setup and does not include HTTP, background workers, or production scheduling/load. No application behavior has been optimized yet.

The [retraction simplification handoff](retraction_simplification_handoff.md) has been read. REG-17, REG-20 and REG-F01/F02 now distinguish authorization/transaction correctness from the stronger immediate-suppression contract being reconsidered. No retraction guards were changed.

Repeated QDB baseline runs (milliseconds):

| Run | Fresh signed total | Fresh profile refresh | Historical total | Historical profile refresh |
| --- | ---: | ---: | ---: | ---: |
| 1 | 13463.5 | 12997.8 | 18325.1 | 17987.3 |
| 2 | 20204.7 | 19657.5 | 22138.7 | 21698.8 |
| 3 | 18910.4 | 18436.1 | 17455.9 | 17067.1 |

All six writes completed incrementally with expected profile increments, matching resulting commit metadata, successful SQLite quick checks, and unchanged source inputs. Median total times were 18910.4 ms (fresh) and 18325.1 ms (historical). Full timings and fixture metadata are retained in [baseline results](profile_registration_baseline_results.json). Run-to-run variance is substantial; compare repeated results, not a single fastest run. REG-01 is complete as a baseline, not as a performance fix.
