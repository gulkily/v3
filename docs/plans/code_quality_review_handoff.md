# Code quality review — handoff (2026-11-17)

This is where the v3 code quality review stands, for whoever continues it. The checklist and findings are the full record. This page is the short version, plus the one thing they don't contain: how to rerun the analysis tools.

- [Checklist](code_quality_review_checklist.md): what to review, ground rules, search rules, and status per item.
- [Findings](code_quality_review_findings_v1.md): every finding, with [decisions and outcomes](code_quality_review_findings_v1.md#decisions-and-outcomes-2026-11-14) and [lessons for the next pass](code_quality_review_findings_v1.md#lessons-for-the-next-pass).
- Discussion: [ZenMemes review thread](https://zenmemes.com/threads/thread-20261111012557-b02a1583). Each decision is its own reply, and the maintainer's answers are nested under them.

## Done (all merged)

Dead code removed (#146, #223, #227). Four real bugs fixed: a missing exception import (#143), high-traffic pages not refreshing after a reply (#147), a test that never ran (#158), and history purge ignoring failed journal writes (#162). Kept code is documented where people look: OpenPGP bundle provenance (#139), the scripts index plus a `./v3` command line in every script (#158, #159), multi-language script sets (#153). Guard tests stop the same misses from coming back: `TestFileRegistrationTest`, `ScriptsIndexTest`, and the theme-asset check in `ThemeRegistryTest`. The correctness pass (SQL, request paths, git/gpg failures, ignored writes) came back clean apart from #162.

The two test failures recorded in the findings as baseline, `testLlmExchangeLinksAppearOnlyOnTheirPostDetailPages` and `testThreadCardWrapsMediaEmbedPreviewInDiv`, pass on `main` as of `96462cfd`.

## Open

1. **Waiting on the maintainer:** `Host/StaticArtifactBuilder::buildSingleRoute` and `PresentationSlotRegistry::all`. Removal was approved, but both turned out to be test seams: `buildSingleRoute` renders single pages for 9 test files, and `all()` drives a slot-consistency test. The recommendation is to keep both and mark them `@internal` test hooks.
2. **Waiting on the maintainer:** Unicode version drift between server and browser. The server's PCRE knows Unicode 14, browsers know 16 or 17, so newer characters such as 🫨 pass the browser and fail on the server. See [findings](code_quality_review_findings_v1.md#server-and-browser-text-rules-2026-11-17).
3. **Checklist areas not started**, cheapest first:
   - sweeps: unused imports repo-wide, commented-out code, unreachable branches, routes with no links, `queries/` `prompts/` `resources/` usage
   - pairs that must agree: incremental updater vs full rebuild (text rules done: ASCII gap fixed, `UnicodeTextRuleParityTest` added)
   - database: read-model tables, columns and indexes nothing queries; very old schema upgrade code; record types nothing writes
   - tests: ones that assert nothing; slowest tests; smoke-set coverage
   - JS duplication (`outbox_*`, `private_message_*`) and `Sqlite*Store` boilerplate
   - large refactors, each its own Railspike cycle: `LocalWriteService` (5,073 lines), `Application::handle` (1,083 lines), shared card templates, shared LLM provider code

## Rerunning the analysis

Run the tools ad hoc from a scratch directory, so the repository gains no dependencies. Composer couldn't reach GitHub from the agent sandbox, so the tools were cloned directly.

```bash
mkdir -p ~/qa && cd ~/qa
git clone --depth 1 https://github.com/phpstan/phpstan phpstan-src             # provides phpstan.phar
git clone --depth 1 https://github.com/shipmonk-rnd/dead-code-detector dcd
V3=/path/to/v3
cat > dcd_autoload.php <<EOF
<?php
spl_autoload_register(function (\$c) { \$p = 'ShipMonk\\\\PHPStan\\\\DeadCode\\\\';
    if (str_starts_with(\$c, \$p)) { \$f = '$HOME/qa/dcd/src/' . str_replace('\\\\', '/', substr(\$c, strlen(\$p))) . '.php'; if (is_file(\$f)) require \$f; } });
require '$V3/autoload.php';
EOF
cat > dead.neon <<EOF
includes: [$HOME/qa/dcd/rules.neon]
parameters:
    level: 0
    tmpDir: $HOME/qa/tmp
    paths: [$V3/src, $V3/tests, $V3/templates, $V3/scripts, $V3/public]
    excludePaths: { analyseAndScan: [$V3/public/assets] }
    customRulesetUsed: true
    shipmonkDeadCode:
        detect: { deadMethods: true, deadConstants: true, deadEnumCases: true }
        usageExcluders: { tests: { enabled: true, devPaths: [$V3/tests] } }
EOF
# -a, not bootstrapFiles: the extension's services load before bootstrap files run.
php -d memory_limit=6G phpstan-src/phpstan.phar analyse -c dead.neon -a dcd_autoload.php --no-progress --error-format=raw \
  | grep -v "^$V3/tests/" > dead.txt
npx jscpd@4 "$V3/src" "$V3/public/assets" "$V3/templates" "$V3/scripts" --min-lines 15 --min-tokens 100 \
  --ignore "**/openpgp*.js,**/qrcode-generator.js,**/*.map" --reporters json --output jscpd
```

The detector flags every `tests/*Test.php` method, because it doesn't know v3's test runner, so filter out `tests/` as above. To see what a change did, run it on the base commit (a `git worktree`) and on your branch, strip line numbers, sort, and compare with `comm`. Before acting on any hit, follow the checklist's search rules: plans, the scripts index, templates, provenance notes, reflection strings, `git log -S`, and test callers.

_Claude (Opus 5.5), 2026-11-17_
