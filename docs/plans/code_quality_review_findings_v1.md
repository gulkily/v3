# Code Quality Review — Findings v1

Raw results from the first tooling pass of the [code quality review checklist](code_quality_review_checklist.md). Snapshot: `main` at `b50af91` (2026-11-10). **No code was changed in this pass.** Every finding has a *proposed* decision; nothing gets removed until the maintainer confirms it.

Decisions: **fix** (a real bug), **remove** (confirmed dead), **review** (probably dead or worth changing; needs a judgment call), **keep** (intentional; add a note per the intentional-duplicate rule), **false positive** (the tool got it wrong).

The earlier [cleanup audit findings](codebase_cleanup_audit_findings_v1.md) were read first. Nothing below repeats an item it already closed.

## Summary

- **1 real bug:** `DedalusPostAnalyzer` throws a class that doesn't exist.
- **~25 dead methods, properties, and constants in `src/`**, plus 1 dead template. All checked by hand for string-based and dynamic uses.
- **~30 methods in `src/` reached only by tests.** Some are test hooks (keep); others are leftover API (review).
- **Duplication is low:** 0.88% of lines (30 clones of 15+ lines). Two clusters are worth merging.
- **Size:** `Application::handle` is a single 1,083-line method. 32 methods are over 100 lines.
- **Security spot checks found no problems.** Every shell command checked escapes its arguments, and no unescaped template output carries user text. One inconsistency is noted.
- **Feature flags:** all 36 are read somewhere. Which ones are permanently on can only be decided from production settings (maintainer input needed).

## 1. Fix

| Where | Problem | Proposed fix |
|---|---|---|
| `src/ForumRewrite/Analysis/DedalusPostAnalyzer.php:115,120` | `new RuntimeException(...)` inside `namespace ForumRewrite\Analysis` with no `use RuntimeException;`. It resolves to `ForumRewrite\Analysis\RuntimeException`, which doesn't exist. When the post-analysis prompt file is missing or empty, PHP raises a fatal "class not found" `Error` instead of the intended message, and `catch (RuntimeException)` blocks won't catch it. | Add `use RuntimeException;`. One line. |

## 2. Dead code in `src/`: no callers anywhere

Each was confirmed with a repo-wide search for the name, including string uses, templates, and JS. The search found no variable-method calls (`$obj->$name()`) or `call_user_func` in `src/`.

| Where | What | Proposed |
|---|---|---|
| `Http/BoardPageController.php:184` `welcome()`, `:205` `random()` | QDB pages, superseded by `Qdb/QdbExperience::welcome()/random()`, which render the same templates | remove |
| `Http/ComposeAndAccountKeyController.php:73` `composeThreadCompact()` | no route calls it | remove |
| `Http/ProfilePageController.php:284` `identityScripts()` (private) | copy of `Application::identityScripts()`, never called here | remove |
| `Activity/ActivityService.php:888` `primeCommitEvidence()` | no callers | remove |
| `Docs/DocsPublication.php:295` `assertPublicationInputs()` | no callers | remove |
| `Host/HighTrafficRenderInput.php:35` `sharedPath()` | no callers | remove |
| `ReadModel/ProfileRepository.php:155` `hasPendingDirectoryProfiles()` | no callers | remove |
| `ReadModel/ThreadRepository.php:95` `allReplyPostsByThreadId()` | no callers | remove |
| `ReadModel/VerifiedAttribution.php:171` `effectiveProfileSlug()` | no callers | remove |
| `ReadModel/ViewerTagLookup.php:71` `resetMemo()` | no callers, not even tests | remove |
| `Retraction/CommitMap.php:212` `isRecordPath()` | no callers. A different `ArchiveRecordCatalog::isRecordPath()` is the one in use. | remove |
| `Scoring/SqliteFastScoreStore.php:538` `workCounts()` | no callers | remove |
| `Scoring/FastScoringConfig.php:81` `headers()` (private) | never called | remove |
| `Scoring/FastScoreStore.php` `find`, `latestScoredForPostContent`, `save` | interface methods never called through the interface | review: callers may use the concrete class; decide whether the interface is still needed |
| `Agent/AgentReplyGenerationStore.php:32–57` (5 methods) | same pattern: interface methods with no callers through the interface | review, same question |
| `SiteConfig.php:27,32,37` `unicodeAuthoredTextEnabled`, `emojiAuthoredTextEnabled`, `appVersionNotificationEnabled` | static helpers, unused. The same-named values elsewhere come from feature flags. | remove |
| `ReadModel/TagPage.php:12`, `ReadModel/ThreadSearchPage.php:16` `SIZE` | constants never read. Both alias `ThreadKeysetPage::SIZE`. | remove |
| `Agent/AgentReplyFulfillmentService.php:28` `$featureFlags` | constructor property never read | review (also simplifies the constructor) |
| `Http/ForteBoardController.php:39`, `Llm/ExchangeVisibility.php:9`, `Llm/LlmExchangeRecorder.php:14`, `Qdb/QdbBoardPolicy.php:19` `$repositoryRoot` | constructor property never read | review |
| `Feedback/FeedbackItem.php:25` `$sections` | written, never read | review |
| `Canonical/ThreadLabelRecord.php:21–22`, `ThreadSubjectRecord.php:18–19` `$actionAt`, `$intentId` | parsed record fields never read | **keep**: parsers keep every header of a canonical record on purpose. Add a one-line comment on the record classes. |

### Dead template

| Where | What | Proposed |
|---|---|---|
| `templates/pages/private_messages.php` | not rendered anywhere. The mailbox page now uses `private_message_list.php` and `private_message_conversation.php` (see `PrivateMessagePageController`). | remove |

## 3. Reached only by tests

PHPStan was run with a "tests" exclusion, so these have callers only under `tests/`.

**Keep (test hooks, named as such):** `SiteProfileRegistry::setProfilesForTesting`, `DeploymentSettings::resetForTesting`, `AssetFingerprint::resetHashCache`, `SignatureCheckCache::disable`. Suggestion: add `@internal for tests` so the next pass skips them.

**Review (look like leftover API that only tests still exercise):**

- `Application::noStoreTimingHeaders`, `mergeResultTimings`, `activityCommitManifest`: probably left behind when routes were extracted into controllers in Phase 2 of the earlier audit.
- `LocalWriteService::setFeatureFlag`: the real path is `ToolsPageController` plus signed records.
- `StaticArtifactBuilder::buildSingleRoute`, `StaticArtifactInvalidator::invalidateProfile`
- `PrivateMessageStore::markSeenThrough`, `conversationFor`; `PrivateMessageMailboxService::conversation`
- `ImageBackup::cachedBytes`, `copyTree`; `ContentImportPlanner::dependencyVisits`
- `PostRecord::rendersMarkdown`, `ViewerSession::isSignedIn`, `TagGrouping::limitPreview`, `PresentationSlotRegistry::all`, `ApiRouteCatalog::paths`, `PlatformDocsCatalog::resolvePath`, `PrivateConfigSchema::keysOmittedFromTemplate`
- `AgentResponseTask::defaultGenerationInput`, `SqliteAgentReplyGenerationStore::claimNextRequested`, `DedalusPostAnalyzer::decodeCompletionPayload`, `CommitEvidenceLookup::manifestReads/processes`, `FastScoreResult::scored`, `SqliteFastScoreStore::latestScoredForPostContent`
- Record fields read only by tests: `OperatorGrantRecord::$targetUsername`, `PostReactionRecord::$operation/$reason/$actionAt/$intentId`, `ThreadSubjectRecord::$operation/$reason`, `PublicKeyRecord::$fingerprint`, `SiteFeatureFlagsRecord::$updatedAt`. These are **keep** for the same reason as above.

For each "review" item, the choice is: remove the method and its test, or keep it with a note saying why (for example, "diagnostic accessor used by benchmarks").

## 4. Assets, templates, scripts

- **Theme CSS (`theme-*.css`):** **false positive.** `View/ThemeRegistry` builds `/assets/theme-<name>.css` from the registry, so a name search can't find them. Suggestion: a comment in `public/assets/` or a test asserting every `theme-*.css` has a registry entry, so this doesn't come up again.
- **OpenPGP bundles:** **keep**, documented in PR #139.
- **Scripts with no reference in `./v3`, code, or reference docs:**
  - `markdown_list_corpus_audit.py`, `markdown_task_corpus_audit.py`: **keep**. Markdown renderer audit tools, still in use while the renderer is extended (maintainer, 2026-11-10); documented in their plan acceptance docs, which this scan skipped. Docstrings now say so (#156).
  - `import_scripture_source.py`: documented in `resources/scripture/README.md`. **Keep.**
  - `qdb_archive_import_run.php`: documented in `docs/runbooks/qdb_archive_import.md`. **Keep.**
- **Script triplets** `publish_text.{php,pl,py}` and `stress_probe.{php,pl,py}`: **keep**. Intentional (maintainer, 2026-11-10) and expected to grow; see `docs/reference/multi_language_scripts.md`.

## 5. Duplication (jscpd, clones of 15+ lines / 100+ tokens)

Total: 851 duplicated lines out of 96,632 (0.88%).

Worth merging:

- **`templates/partials/post_card.php` ↔ `thread_root_card.php`**: four clones, about 165 lines in total (header, meta, actions, footer). This is the biggest cluster. Shared partials would keep the two cards from drifting apart.
- **`Llm/AnthropicStructuredChatProvider.php` ↔ `OpenAiCompatibleStructuredChatProvider.php`**: three clones, about 110 lines (request, retry, and decode helpers). Candidate for a shared base or trait.

Smaller, lower priority:

- `scripts/delete_record.php` ↔ `thread_attributes.php` ↔ `archive_thread.php`: record-file helpers (55 and 48 lines). **Done:** moved, unchanged, to `scripts/lib/record_files.php`, along with the copies in `import_repository_archive.php` and `audit_post_signatures.php`.
- `public/assets/paned_board_reader.js` ↔ `paned_users_reader.js` ↔ `paned_activity_reader.js`: pane navigation (46 and 23 lines).
- `Agent/SqliteAgentReplyGenerationStore.php` ↔ `Analysis/SqlitePostAnalysisStore.php`: store boilerplate (34 lines).
- Repeats within a single file: `TagApiController` (thread vs post tag, 28+28), `WritePostAndIdentityApiController` (3 × ~20), `ProfilePresentationContent` (30).
- `theme_toggle.js` ↔ `thread_density_toggle.js` (31 lines). **Keep:** sharing ~30 lines of menu keyboard handling would add an asset and a load-order dependency to every page, which costs more than the duplication.

## 6. Size

Longest methods in `src/`:

| Lines | Method |
|---|---|
| 1,083 | `Application::handle` |
| 333 | `FeatureFlagRegistry::all` (data table, fine) |
| 185 | `Import/RepositoryArchive::extract` |
| 172 | `ReadModel/ReadModelSchema::statements` (SQL list, fine) |
| 172 | `Import/ContentImportPlanner::plan` |
| 169 | `Agent/AgentReplyFulfillmentService::publishForPost` |
| 166 | `Host/FrontController::handleRequest` |
| 164 | `Http/ForteActivityController::board` |
| 160 | `ReadModel/ReadModelBuilder::indexActivity` |

`Application::handle` is the route dispatcher left after the Phase 2 extractions. Turning it into a route table is the natural next step for that file. `LocalWriteService.php` (5,073 lines) is large but split into many methods; split it by record type, as its own Railspike cycle.

## 7. Quick quality checks

| Check | Result |
|---|---|
| `declare(strict_types=1)` missing in `src/` | 0 files |
| `TODO`/`FIXME`/`XXX`/`HACK` in `src/` | 0 as whole words. The checklist's 257 was a loose case-insensitive substring count; it was mostly "legacy" (119 whole-word hits, largely the six intentional `Legacy*` compatibility classes) plus substring noise. Nothing to triage. |
| Empty `catch {}` in `src/` | 6. All are best-effort calls such as reporting, cache, or optional image lookup. **Review:** add a comment to each saying the failure is ignored on purpose. |
| `@` error suppression in `src/` | 155, mostly `@file_get_contents`/`@unlink`/`@mkdir` with the return value checked. No action unless one turns up an unchecked result. |
| Shell commands built from strings | All 6 that concatenate a command string were traced: every caller passes literals or `escapeshellarg`ed arguments. **No bug.** The string-argument helpers (`runGit(string $arguments)`, `codebaseGitValue(string $arguments)`) are easy to misuse later; consider array arguments, like `LocalWriteService::runGitCommand(array $args)`. |
| Unescaped template output | 38 bare `<?= $var ?>`. All are numbers, fixed literals, or HTML already built by the renderer. **One inconsistency:** `partials/feedback.php` escapes `$error` but not `$notice`. Every notice today is a server-side literal, so it isn't exploitable, but escaping both would remove the trap. |

## 8. Needs maintainer input

- **Feature flags permanently on everywhere:** **keep**. Flags exist to switch off a buggy feature and to let future instances leave a feature out, so "on everywhere" is not a reason to remove one (Ilya, 2026-11-10). All 36 are read by code.
- **Interface-only methods** (`FastScoreStore`, `AgentReplyGenerationStore`): keep the interfaces?

## Suggested next PRs

1. Add `use RuntimeException;` to `DedalusPostAnalyzer`, and escape `$notice` in `feedback.php`. Tiny, behavior-safe.
2. Remove the "remove" rows in section 2 plus the dead template. One commit per subsystem.
3. Add notes for the "keep" items (record fields, test hooks, theme CSS loading).
4. Go through the "review" items in section 3 together.
5. Later, each as its own Railspike cycle: shared card partials, a shared LLM provider base, and a route table for `Application::handle`.

## Appendix: how this was run

The tools ran from a scratch directory. Nothing was added to the project. Composer couldn't reach GitHub from the sandbox, so the tools were cloned directly.

```bash
git clone --depth 1 https://github.com/phpstan/phpstan            # provides phpstan.phar (2.1.x)
git clone --depth 1 https://github.com/shipmonk-rnd/dead-code-detector
# dead.neon: includes dcd/rules.neon; level 0; paths src, tests, templates, scripts, public (excluding public/assets);
# shipmonkDeadCode.detect deadMethods/deadConstants/deadEnumCases; usageExcluders.tests on tests/
php -d memory_limit=6G phpstan.phar analyse -c dead.neon -a dcd_autoload.php --error-format=raw
npx jscpd@4 src public/assets templates scripts --min-lines 15 --min-tokens 100 \
  --ignore "**/openpgp*.js,**/qrcode-generator.js,**/*.map"
```

`dcd_autoload.php` registers a PSR-4 loader for `ShipMonk\PHPStan\DeadCode\` → `dcd/src/` and requires v3's `autoload.php`. It must be passed with `-a`, not `bootstrapFiles`, because the extension's services are loaded before bootstrap files run.

Known blind spot: the detector treats every `tests/*Test.php` method as unused because it doesn't recognise v3's own test runner. Those ~2,000 hits were ignored.

## Doc review of the open decisions (2026-11-10)

A second pass read every doc, including `docs/plans/`, for each open item and for the code removed in #146.

- **Decision 7 (source map): keep serving it.** `firefox_console_warnings_causes_v1.md` added the map on purpose so DevTools stops logging a 404. The v6 bundle's `sourceMappingURL` points to it, and production serves it (200). Browsers fetch maps only with DevTools open, so visitors don't pay for it. `page_asset_performance_inventory.md` already notes that static releases don't copy it.
- **Decision 8 (one-time scripts): all three are live `./v3` commands.** `inject_approval.php` is `./v3 approval seed|approve` (also used by `tests/browser/calendar.mjs`). `backfill_unicode_risk.php` is `./v3 unicode-risk-backfill`, which `task_queue_candidate_catalog.md` says to rerun when `UNICODE_RISK_SCHEMA_VERSION` changes. `bake_legacy_timestamps.php` is `./v3 bake-timestamps`. Keep all three.
- **Decision 2 (test-only methods):** the plans confirm several ratings. `buildSingleRoute` was the on-miss static build until it was removed on purpose on Sep 21 (`fee91dee`), when builds left the request path. `cachedBytes` was added for the codebase-sizes page and later wrapped by `cachedArchive`. `markSeenThrough` was the Step 3 contract for DM unread. `setFeatureFlag` is described as "the only feature-flag write path" in the flag rebuild fix. No doc changes a rating band.
- **Decision 3 (interfaces):** no doc gives a reason for `FastScoreStore` or `AgentReplyGenerationStore`, and nothing outside `src/` implements them. No change.
- **Decision 4 (unread properties):** no doc mentions them. No change.
- **Removed in #146, still described in older plans:** `qdb_classic_urls_checklist.md` says QDB `/add` renders through `composeThreadCompact()`; it now goes through `QdbExperience`, and the QDB tests pass. `site_feature_flags_slices_v1.md` asked to keep the `SiteConfig` flag helpers as compatibility wrappers during its first slice; later slices moved every caller, so removing them was safe. `ui_messages_step2_feature_description.md` already called `pages/private_messages.php` unreachable. These are history and stay as written.

## Correctness pass (2026-11-10)

Checklist §2.3 and §2.4: read-only scans of `src/` and `scripts/`, then a hand trace of every hit that could reach user input or lose data.

**One real bug, fixed in #162.** `./v3 purge-history` didn't check its journal writes. A failed `refs-before.txt` write let the purge continue, and a later `restore` then restored zero refs, cleared the journal and reported success. A failed phase write could leave a pruned repository marked restorable. Both writes now throw, and `restore` refuses an empty record.

**SQL built from strings: no problems.** 58 queries put PHP values into SQL text. Each value is one of these:
- a placeholder list
- a fixed fragment
- an `int` (enforced by `strict_types`)
- a value checked against an allowed list (`ActivityService::activitySortSql`, sort direction, mailbox column)

Page numbers go through `PageNavigation::pageNumber`, which accepts at most 6 digits. QDB search casts with `(int)` but clamps to the page count before building the offset.

**File paths from requests: no problems.**
- Source viewer: an explicit list of record paths, then the real path must stay inside the repository.
- Docs downloads: refuse `..`, encoded slashes and dot segments.
- Images: a 64-character hash, re-checked against the file's checksum.
- Assets: a strict name pattern, and the real path must sit directly in `assets/`.
- High-traffic cache: only routes that pass `HighTrafficRoute::canonical`.
- Feature-flag records: `[A-Za-z0-9._-]` with no `..`, then the in-repository check.

**git and gpg failures: no problems.** Every call whose result matters checks the exit code (`proc_get_status`, `exec` exit argument or `proc_close` status), throws on short output, or fails closed. For example, `RetractionAuthority` refuses the actor when `git` returns nothing. `SourceLabelRemoval` reads an empty `symbolic-ref` result on purpose, as a detached HEAD.

**Ignored write results:** apart from the purge journal, the unchecked writes are best-effort logs (`@`), temporary files where the next step fails loudly, or lock releases. One is low priority and left as is:
- `ReadModelStaleMarker::mark()` ignores a failed write. Every caller has just made a commit the read model hasn't seen, and `ensureReadModel` also rebuilds on a repository head mismatch, so a lost marker still leads to a rebuild.

Not covered: read-side `false`/`null` returns. Most are deliberate `(string)` casts with a default, so a useful scan needs a type checker (a higher PHPStan level), not grep.

