# Approval root suggestions debug log

## 2026-11-16T09:10:00Z closeout (v3/approval_suggest_roots)

- Mode resolution: debug enabled by project marker `.railspike_debug_on`; no explicit request.
- Instructions read: AGENTS.md, RAILSPIKE.md, one_shot.md, effort-estimation one-shot checkpoints, host ledger P2 section.
- Friction: a Python slice ended the builder method at the first `    }\n\n` (an inner `if`), producing broken code; caught by reading the diff and `php -l` before tests. Lesson: anchor extractions on the method's final statement.
- Friction: `add_repo` push access denied by the session's permission classifier; work delivered as a patch.
- Tests passed on the first run; fixture assertions are specific (order, evidence labels, counts), so this is not a vacuous pass.

## 2026-11-16T21:20:00Z follow-up (v3/import_with_approvals)

- Real use reported `approval_edges: 0` for 312 keys: `import-instance` excludes approval posts and receipts, so the analyzer had nothing to read. Missed during the first cycle because the reuse inspection covered the read model but not the importer's exclusions. Lesson: when a tool's input comes from an import, read the importer's exclusion list first.
