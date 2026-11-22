# Quill in Perl and PHP

Cycle: `v3/quill_ports`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_ledger/quill_ports.md). Standing P2 enrollment. Follows [quill_rename](../quill_rename/quill_rename_one_shot.md), which filed this work as a follow-up; no parent cycle.

Extended estimates: disabled (one approach; the two ports are not alternatives and are not staged)

**Cycle estimate: 0.75 (execution entry; intake 0.60)**

Status: implemented and verified locally; PR open, awaiting maintainer review, merge and deploy. Production check pending.

## Original request

Maintainer, 2026-11-22: "do we have multiple versions o quill yet?" (answer: no, only `public/quill.py`). Then: "can you please write pl and php versions? for perl, please use pollyanna as style guide".

## Authorization

Maintainer, 2026-11-22, the request above. It asks for the two ports to be written; with `AGENTS.md` (every committed change runs as Railspike, one-shot by default) it authorizes a one-shot cycle through an open PR. It does not cover merging, deploying or production access.

## Feedback item

[PF-20261116-F04](../../feedback/items/PF-20261116-F04.txt), "PHP and Perl versions of Quill", filed by quill_rename. Its note: each version should send its own User-Agent so the update notice can tell them apart.

## Outcome and work type

Application change. Quill becomes a [multi-language set](../../reference/multi_language_scripts.md):

- `public/quill.pl` and `public/quill.php` do what `public/quill.py` does, with the same commands, options, output lines, saved-operation files and exit codes (0, 1 missing, 2 error, 3 staged): `index`, `read`, `check`, `thread`, `reply` (signed and prepared protocols, `--resume`, `--replace`, `--image`, trouble reports), `pending`, `approve`, `checkin`, `caught-up`.
- All three share the state directory (`~/.local/state/quill`, or `~/.local/state/frm` when only that exists) and its file formats, so a key made by one version posts from another, and an operation saved by one can be resumed by another.
- The site serves `/quill.pl` and `/quill.php` beside `/quill.py`.
- Each version sends its own User-Agent (`quill/…`, `quill-pl/…`, `quill-php/…`), and the update notice compares a copy against the file of the same language and names that file.
- The Perl version follows Pollyanna's Perl style (`doc/style.txt` and `default/template/perl/` in gulkily/pollyanna): tab indents, CamelCase subs declared as `sub Name { # $arguments ; description`, one `shift` per argument, `} # Name()` closers, `WriteLog` tracing, sanity-check regexes on paths and IDs. The PHP version follows this repository's PHP scripts (`scripts/publish_text.php`).

Exclusions:

- No Pillow equivalent. Without Pillow, `quill.py` sends an already-prepared PNG or JPEG with its metadata removed; the ports do the same and nothing more (no resizing or format conversion). Their messages point to `quill.py` with Pillow for that.
- No new dependencies. Perl uses core modules, curl and gpg. PHP needs no extension beyond core; the `intl` extension is optional (see risks).
- No change to `quill.py` behavior or version.

## Approach and risks

Port `quill.py` function by function, keeping its names recognizable in each language, then run the existing HTTP tests against all three.

- **High: the shared state directory.** A port that writes `identity.txt` or `operation.txt` differently could break the Python client's resumes or keys. Validation: a parity test writes with one version and resumes or reuses with another. Mitigation: same `Key: value` files, same names, same site-hash subdirectory.
- **Unicode normalization in PHP.** `quill.py` signs NFC text. PHP's `Normalizer` needs `intl`, which shared hosts may lack. Mitigation: without `intl`, a non-ASCII draft goes through the prepared route, where the server composes the canonical bytes and the client signs them exactly (the route `quill.py` already uses on older servers). ASCII drafts need no normalization. `--replace` needs the signed route, so without `intl` a non-ASCII fix says so. Perl has `Unicode::Normalize` in core.
- **Perl on Windows.** Running a process with separate output and error capture uses `fork`. The ports are for Unix-like hosts, the same hosts where `quill.py` expects curl and gpg.
- **Taint mode.** Pollyanna runs Perl with `-T`. A client invoked as `perl quill.pl` from any directory, finding curl and gpg on the caller's `PATH`, would have to override `PATH` to run under taint mode. The port keeps Pollyanna's sanity-check style without `-T`.
- **A PHP file in the web root.** `public/.htaccess` and `public/router.php` send every request to `index.php`, so `/quill.php` is never executed by supported hosts. The file also refuses to run outside the CLI.

## Reuse decisions

- `public/quill.py` is the reference; nothing in it is reimplemented differently on purpose.
- `src/ForumRewrite/Http/ClientDownload.php`: extend `PATHS`, `FILE`-by-path and the version reader to cover the three files, instead of a second download handler.
- `tests/QuillClientTest.php`: its `run()` already takes the interpreter as an argument. Extend the HTTP tests to loop over the three versions (the parity rule in `multi_language_scripts.md`) rather than writing a separate suite.
- Process and encoding idioms come from `scripts/publish_text.pl` and `scripts/publish_text.php` (list-form processes, curl with `--disable`, byte-level form escaping).

## Completion conditions

- Normal entry: an agent on a host with Perl or PHP and no Python downloads `/quill.pl` or `/quill.php` from the site and posts.
- End to end: each version publishes a signed thread and reply, resumes a lost response without a duplicate, reports a staged post and replaces it, approves a key, and shows the check-in, against a local test server.
- Recovery: a write interrupted in one version resumes in it; a key made by `quill.py` signs from the ports.
- External: after deploy, `/quill.pl` and `/quill.php` on zenmemes.com serve the files, and a post made with each shows up as the `claude` identity. Pending until the maintainer deploys.
- Release: PR merged and deployed.

## Concepts touched

- [Agent posting client](../../concepts/agent_posting_client.md): changed (three versions, per-language update notice).

## Verification and recovery plan

- Run `QuillClientTest` over all three interpreters, plus `quill_client_test.py`, `LlmsTxtRendererTest` and the smoke set. Full suite not run (`AGENTS.md`).
- `perl -c public/quill.pl`, `php -l public/quill.php`.
- A cross-version test: key from Python, post from Perl and PHP under the same identity.
- A live read-only check against zenmemes.com (`index`, `read`, `check`) from each version.
- Search the docs for places that list the client and add the siblings: `multi_language_scripts.md`, `agent_api.md`, the concept entry, `llms.txt` renderer.
- `git diff --check` and a diff review.

## Starting revisions and branch

Starting revision `cc6680c9` (main). Branch `feat/quill-ports`.

## Planning commit

`d4f5ec3c` (record and plans index only). It carries a `Feedback: PF-20261116-F04` trailer without updating the item; the item is updated in the completion commit, which also carries the trailer.

## Study history

- 2026-11-22: intake **0.60**, rated after listing the files and their sizes and reading the feedback item, before reading `quill.py`. Single exposed Claude rater (the same session wrote the earlier Python-side posts). Baseline `cc6680c9`.
- 2026-11-22: execution entry **0.75**, revised up after reading `quill.py` (1,179 lines, nine commands, two protocols, image handling) and `QuillClientTest.php`: two full ports plus parity tests is more than the intake assumed. Scope unchanged.
- 2026-11-22: retrospective **0.75**. Scope unchanged; three additions inside it (the no-`intl` fallback test, the CLI-only guard test, the cross-version test). No split. Maintainer fit judgment pending; no accepted-count change.

## Dated execution history

- 2026-11-22: record written. Mode one-shot: the scope is fixed by the existing client, and no approach choice needs the maintainer.
- 2026-11-22, implementation:
  - `public/quill.pl`: port of `quill.py` in Pollyanna's Perl style (80 subs, each `sub Name { # $arguments ; description` with a `} # Name()` closer, one `shift` per argument, tab indents, `WriteLog` lines shown with `QUILL_DEBUG=1`). Core modules only (`Digest::SHA`, `Encode`, `JSON::PP`, `Unicode::Normalize`, `File::Temp`, `File::Path`, `Time::Local`, `POSIX`). Processes run by `fork` and `exec` with stderr to a temporary file; no shell. Function names follow `quill.py`'s (`request` → `Request`, `new_post` → `NewPost`), so a search finds the same step in each version. Decision: no taint mode (see risks).
  - `public/quill.php`: port in this repository's PHP script style (`scripts/publish_text.php`). PHP 8.1+ core only; `intl` optional. Refuses to run outside the CLI. Correction during work: a helper named `urlEncode` collided with the built-in `urlencode` (PHP function names ignore case); renamed `formEncode`.
  - Both send their own User-Agent (`quill-pl/2026-11-22`, `quill-php/2026-11-22`), print `Update:` once per run, write the same `identity.txt`, `operation.txt`, `key.txt` and payload files, and print `perl quill.pl …` or `php quill.php …` in `Resume:`, `Check:` and `Replace:` lines.
  - Image messages name the fix that applies to them ("Export a smaller copy, or use quill.py with Pillow…") instead of `quill.py`'s "Install Pillow". Everything else prints the same lines.
  - `ClientDownload`: `PATHS` maps each download path to its file; `AGENTS` maps each User-Agent product to the file it is compared with; `currentVersion()` reads the version line of any of the three files. `/frm.py` still serves `quill.py`.
  - Docs: `llms.txt` (one line naming the siblings; the update-notice line no longer names `/quill.py` only), `agent_api.md` (quickstart paragraph, version section, members-only discovery list), `multi_language_scripts.md` (new set row and the two documented differences), the concept entry, `README.md`.
  - Tests: `QuillClientTest` loops 15 of its HTTP tests over the three versions (`self::each`) and fails with the version's name. The pending-keys test imports `quill.py` as a Python module, so it stays Python-only. New: `testServedPhpClientIsNeverExecuted`, `testVersionsShareKeysAndResumeEachOthersOperations` (a key from `quill.py` posts from the others under one identity; a lost write saved by each version is resumed by another with no new commit), `testPhpWithoutIntlStillPostsUnicode` (runs `php -n`, which drops `intl` here). `testUpdateNoticeComparesVersions` covers each language's version line.
- 2026-11-22, verification:
  - `./v3 test --smoke QuillClientTest LlmsTxtRendererTest PlatformDocsPageTest AgentApiWorkedExampleTest WebServerRoutingTest`: 539 run, 539 passed. `QuillClientTest` alone: 20 passed in 80 s (17 in 28 s before). Full suite not run.
  - `python3 tests/quill_client_test.py`: 40 passed (`quill.py` unchanged).
  - Mutation check: breaking the success output of `quill.pl`, then of `quill.php`, failed `testFirstWriteReuseAndLegacyFallback` with "quill.pl: …" and "quill.php: …". A busy-retry test did not notice the Perl break, since it checks exit codes only; the broader tests did.
  - `perl -c` and `php -l`: clean. Argument errors, bad `--site`, bad `--retries` and missing `--body-file`: same messages and exit code 2 in all three (argparse's own usage line differs from the ports' "Use --help for usage.").
  - Live, read-only, against zenmemes.com from each port: `index`, `read`, `check` (found and missing), `checkin`. No posts were made there.
  - `./v3 feedback check`: 0 problems. No test failed on the base commit.
  - Pending: after deploy, fetch `/quill.pl` and `/quill.php` from zenmemes.com and post once with each.

## Outcome

Delivered as authorized. Limits, all documented: the ports cannot resize or convert images (no Pillow); `quill.php` without `intl` uses the prepared protocol for non-ASCII drafts and cannot `--replace` them; both need a Unix-like host. The Perl port does not run in taint mode.

## Final review

Diff checked for duplication (one download handler, one test suite looping over versions), scope (no change to `quill.py`) and docs. The concept entry is updated. The completion commit carries the implementation, this record, the ledger file, the debug log and the feedback item.

## Commit/merge/publication status

Completion commit: pending at the time of writing; its hash goes in the PR description. Not merged or deployed.

*— Claude · 2026-11-22 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
