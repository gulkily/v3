# Scripts

Every file in `scripts/` and what runs it. Most of these are reached through `./v3`, which calls them by `${ROOT}/scripts/<name>` path, so a search for the bare file name can make a live script look unused. Check this table first. `ScriptsIndexTest` fails when a script is added without a row here.

Run by: a `./v3` command, another script or class, or "you" (run it by hand).

## Behind `./v3` commands

| Script | Run by | What it does |
|---|---|---|
| `agent_reply_status.php` | `./v3 agent-reply status` | Show agent reply generation state for posts |
| `agent_response_format_readiness.php` | `./v3 agent-reply format-readiness` | Check stored agent responses against the current format |
| `test_agent_reply_provider.php` | `./v3 agent-reply test`, `test-local` | Send a test request to the configured reply provider |
| `inject_approval.php` | `./v3 approve`, `./v3 approval seed`, `./v3 approval approve` | Seed or grant key approval from the server ([approval](../docs/concepts/approval_acceptance.md)) |
| `approval_receipts.php` | `./v3 approval receipts` | Inspect or convert approval receipts |
| `audit_approvals.php` | `./v3 approval audit` | Audit who approved whom |
| `archive_thread.php` | `./v3 archive-thread` | Archive one thread out of the live repository |
| `bake_legacy_timestamps.php` | `./v3 bake-timestamps` | Write explicit timestamps into legacy records |
| `build_static_artifacts.php` | `./v3 build-static` | Build static HTML pages |
| `refresh_static_shared_artifacts.php` | `./v3 build-static --shared-only` | Refresh only the shared static artifacts |
| `checkouts.php` | `./v3 checkouts` | Manage several checkouts on one host |
| `delete_record.php` | `./v3 delete-record` | Remove one record quickly ([progress output](../docs/concepts/operator_command_progress.md)) |
| `delete_thread.php` | `./v3 delete-thread` | Remove a thread root and all its replies |
| `deploy.php` | `./v3 deploy` | Pull and deploy an update ([application update](../docs/concepts/application_update.md)) |
| `docs.php` | `./v3 docs publish` | Publish the platform docs |
| `fast_score.php` | `./v3 fast-score` | Fast post scoring status, audit and maintenance |
| `feedback.php` | `./v3 feedback` | Check and index feedback items ([feedback items](../docs/concepts/feedback_items.md)) |
| `high_traffic.php` | `./v3 high-traffic` | Enable, inspect and refresh high-traffic mode |
| `restore_images.php` | `./v3 images` | Verify or restore an image backup |
| `import_instance.php` | `./v3 import-instance` | Import content from another instance |
| `import_repository_archive.php` | `./v3 import-repository` | Import a repository archive |
| `local_server_dependencies.sh` | `./v3 check-dependencies`, `./v3 start` | Read-only local server dependency checks |
| `local_server_dependency_install.sh` | `./v3 check-dependencies --install` | Opt-in dependency installer |
| `local_server_start.sh` | `./v3 start` | Start the local development server |
| `diagnose_offline_reading.php` | `./v3 offline diagnose` | Check the offline snapshot |
| `publish_offline_snapshot.php` | `./v3 offline publish` | Publish the offline reading snapshot |
| `openpgp_smoke.php` | `./v3 openpgp smoke` | Read-only check of the served OpenPGP bundles ([OpenPGP](../docs/reference/openpgp.md)) |
| `openpgp_production_canary.mjs` | `./v3 openpgp canary` | **Writes to production**: browser signing canary over HTTP |
| `purge_history.php` | `./v3 purge-history` | Purge history ([history purge](../docs/concepts/history_purge.md)) |
| `railspike_sync.sh` | `./v3 railspike sync` | Sync the Railspike workflow files ([Railspike](../docs/reference/railspike_workflow.md)) |
| `rebuild_read_model.php` | `./v3 rebuild` | Rebuild, diagnose or recover the read model |
| `retraction_sweep.php` | `./v3 retraction-sweep` | Apply pending retractions in batches |
| `status.php` | `./v3 status` | Health summary, including the `gpg` check |
| `task_queue.php` | `./v3 task-queue` | Enqueue, run and inspect background tasks ([task queue](../docs/concepts/internal_task_queue.md)) |
| `terminal_operator_ui.sh` | `./v3 tui` | Terminal operator menu |
| `thread_attributes.php` | `./v3 thread-attributes` | Print a thread's canonical and derived attributes |
| `backfill_unicode_risk.php` | `./v3 unicode-risk-backfill` | Recompute Unicode-risk analysis. Rerun when the Unicode-risk schema version changes |
| `write_private_config.php` | `./v3 private-config` | View or edit the private config file |

## Called by other code

| Script | Run by | What it does |
|---|---|---|
| `lib/record_files.php` | `archive_thread`, `delete_record`, `thread_attributes`, `import_repository_archive`, `audit_post_signatures` | Shared record-file helpers |
| `check_local_server_dependencies.php` | `local_server_dependencies.sh`, `local_server_dependency_install.sh` | PHP side of the dependency check |
| `php_setup_guidance.sh` | `local_server_dependencies.sh` | Prints PHP install guidance when PHP is missing or too old |
| `dev_server.php` | `local_server_start.sh`, `public/router.php` | PHP built-in server with a readable request log |
| `high_traffic_render.php` | `Host/HighTrafficCache` | Worker that renders one cached page in a separate process |
| `terminal_operator_ui_lib.sh` | `terminal_operator_ui.sh` | Shared helpers for the terminal menu |
| `terminal_dialog.php` | `terminal_operator_ui_lib.sh` | Dialog prompts for the terminal menu |
| `terminal_llm_editor.php` | `terminal_operator_ui.sh` | LLM settings editor in the terminal menu |
| `markdown_render_probe.php` | `markdown_metrics.py` | Renders documents for the Markdown metrics |

## Run by hand

| Script | What it does | Docs |
|---|---|---|
| `publish_text.py`, `publish_text.php`, `publish_text.pl` | Publish a text file through the write API. One of the [multi-language sets](../docs/reference/multi_language_scripts.md) | [agent API](../docs/reference/agent_api.md#text-file-publisher-scripts) |
| `stress_probe.py`, `stress_probe.php`, `stress_probe.pl` | Stepped read-load probe. Another multi-language set | [runbook](../docs/runbooks/server_stress_probe.md) |
| `derive_agent_key.py` | Derive a stable agent signing key from a seed | [agent API](../docs/reference/agent_api.md); linked from `llms.txt` |
| `audit_post_signatures.php` | Count posts by signature state | [OpenPGP](../docs/reference/openpgp.md#checking-it-works) |
| `init_local_repository.php` | Create an empty local content repository | [README](../README.md) |
| `check_static_artifacts.php` | Check a built static artifact tree | [CLI reference](../docs/reference/v3_cli.md) |
| `build_sqlite_query_catalog.php` | List the read model's SQL queries | [CLI reference](../docs/reference/v3_cli.md) |
| `markdown_metrics.py` | Measure Markdown syntax and renderer output over a frozen corpus | [render metrics](../docs/plans/markdown_dependency_removal/render_metrics.md) |
| `markdown_list_corpus_audit.py` | Audit nested-list rendering. Rerun when list rendering changes | [list acceptance](../docs/plans/markdown_list_structure/list_render_acceptance.md) |
| `markdown_task_corpus_audit.py` | Audit task-list rendering. Rerun when task-list rendering changes | [task acceptance](../docs/plans/markdown_task_lists/task_render_acceptance.md) |
| `benchmark_profile_registration.php` | Benchmark profile registration in a scratch repository | [baseline](../docs/plans/profile_registration_baseline.md) |
| `qdb_archive_import_run.php` | One-shot QDB archive import; runs the next three | [QDB import runbook](../docs/runbooks/qdb_archive_import.md) |
| `qdb_archive_import_extract.php`, `qdb_archive_import_normalize.php`, `qdb_archive_import_write_posts.php` | Stages of the QDB import | [QDB import runbook](../docs/runbooks/qdb_archive_import.md) |
| `qdb_capacity_probe.js` | k6 load probe for the QDB shared host | [runbook](../docs/runbooks/qdb_shared_host_capacity.md) |
| `import_scripture_source.py` | Rebuild the pinned KJV corpus from its archive | [scripture README](../resources/scripture/README.md) |
