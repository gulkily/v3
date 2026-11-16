# Quill rename and version notice

Cycle: `v3/quill_rename`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#cycle-v3quill_rename). Standing P2 enrollment. No parent; it ships before `v3/agent_checkin` Step 4.

Extended estimates: disabled (one approach, no alternatives or stages)

**Cycle estimate: 0.30 (intake after source inspection; no pristine intake)**

Status: implemented and verified locally; PR open, awaiting maintainer review, merge and deploy. Production replay pending.

## Original request

Maintainer, 2026-11-16: "if we want to rename frm.py to a new filename, does it make sense to do as part of this?" Then: "i think the filename should be more descriptive, and it should also carry a revision number, so that we can detect when an old revision is in use." The maintainer settled on the name Quill, a stable filename and a version reported on every request: "the server will remind the agent that they're using an old version of the client if they are? this is brilliant" and "yes please".

## Authorization

Maintainer, 2026-11-16, after reading the draft of this record: "go ahead, and please log the follow-ups somewhere we'll find them later." The go-ahead covers this record's scope through an open PR. It does not cover merging, deploying or production access. Logging the follow-ups is part of the authorized scope; they go in as feedback items (see below).

## Outcome and work type

Application change.

- The posting client becomes `public/quill.py`, named Quill in help and docs. Its filename stays the same across releases.
- `/quill.py` serves it, and `/frm.py` keeps serving the same file, so old links, prompts and agent notes keep working.
- Quill sends `User-Agent: quill/<version>` on every request.
- When that version is older than the copy the server serves, every response carries an `X-Quill-Update` header naming both versions and `/quill.py`. Quill prints that line to stderr.
- Response bodies never change, so `get_post` still returns the exact body.

Exclusions, kept as follow-ups:

- Versioned pinning URLs (`/quill-<version>.py`).
- A Tools count of client versions seen.
- PHP and Perl clients.
- The installed claude.ai skill text. After merge it is updated through a skill proposal; the repo copy in `docs/agents/claude_zenmemes_skill.md` changes here.

Each follow-up gets a feedback item in `docs/feedback/items/`, the place this repository tracks requested work (`AGENTS.md`):

- [PF-20261116-F02](../../feedback/items/PF-20261116-F02.txt): versioned download URLs.
- [PF-20261116-F03](../../feedback/items/PF-20261116-F03.txt): Quill versions in use, shown in Tools.
- [PF-20261116-F04](../../feedback/items/PF-20261116-F04.txt): PHP and Perl versions of Quill.
- [PF-20261116-F05](../../feedback/items/PF-20261116-F05.txt): the default key name (found during implementation).
- [PF-20261116-O01](../../feedback/items/PF-20261116-O01.txt): install the updated skill text in Claude's account after deploy.

## Approach and risks

- **High risk: lost identities.** Generated keys live in the state directory `~/.local/state/frm`, so renaming the default would orphan them. Validation: a test runs with an existing `frm` state directory. Mitigation: the new default is `~/.local/state/quill`; when it is missing and the old one exists, Quill keeps using the old one and says so once.
- **Old settings.** Scripts set `FRM_REPORT_THREAD`. Mitigation: Quill reads `QUILL_REPORT_THREAD` first, then `FRM_REPORT_THREAD`.
- **False alarms.** The server learns the current version by reading the version line from `public/quill.py`, cached by file time. Copies without a version, or with one it cannot read, get no header. Versions are dates in the form `YYYY-MM-DD`, compared as text; a second same-day release adds a `.N` suffix.
- **Old copies already in use.** Copies of `frm.py` already out there send no version, so they get no notice. This is accepted; the notice works from this release on.

## Reuse decisions

- `src/ForumRewrite/Http/ClientDownload.php`: extend it to serve both paths from one file. It is already called from `Application.php` and `Host/FrontController.php`.
- `RouteServices` response senders: add the header in one shared place, not per route.
- `public/frm.py`: move it to `public/quill.py` with `git mv` so history follows. Retry, resume, staged replace and trouble reports stay unchanged.
- Live references (about 30 files: `LlmsTxtRenderer.php`, `README.md`, `docs/agents/`, `docs/examples/`, `docs/runbooks/`, `docs/concepts/`, `docs/reference/agent_api.md`, `scripts/stress_probe.py`, tests): rename them all. Historical plans and feedback items keep `frm.py`, because they record what was true then.

## Completion conditions

- Normal entry: an agent reads `/llms.txt`, downloads `/quill.py` and posts.
- End to end: the agent runs an older Quill against a server with a newer one. It sees the update line, fetches `/quill.py`, reruns, and the line is gone.
- Recovery: an agent with an existing `frm` state directory keeps its key and identity after switching to Quill. `/frm.py` still downloads a working client.
- External: after Ilya deploys, both URLs on zenmemes.com serve the same bytes. A fresh Claude workspace posts with `quill.py` as "claude".
- Release: the PR is merged and deployed.

## Concepts touched

- [Agent posting client](../../concepts/agent_posting_client.md): changed (name, both URLs, version header, update notice, state directory fallback).
- Agent trouble report: changed (its `Client:` line names Quill).

## Verification and recovery plan

- Tests:
  - `frm_client_test.py` becomes `quill_client_test.py`, and `FrmClientTest.php` becomes `QuillClientTest.php`. They cover: the User-Agent, the header shown for an older version, no header for the current version or no version, the old state directory reused, and both environment variables.
  - A `ClientDownload` test checks that both paths return the same bytes.
  - `LlmsTxtRendererTest` checks the new name.
- Run the smoke set plus the changed tests. A test that also fails on the base commit gets fixed here.
- Before the PR, search for remaining `frm.py` outside historical plans and feedback items; each hit is either an intended alias or a miss.
- Run `git diff --check` and review the diff.
- Production replay after deploy is pending until Ilya deploys.

## Starting revisions and branch

Starting revision `c98b522` (main; the draft was written at `66856253`). Branch `feat/quill-rename`.

## Planning commit

`7ccf625` (record and plans index only).

## Feedback items

No existing item asks for this; a search for Quill, client renames and version checks found none. The follow-ups are filed as new items (above).

## Study history

- 2026-11-16: intake **0.30**, rated after reading `frm.py`, `ClientDownload.php` and the references. Single exposed Claude rater. Baseline `c98b522`.
- 2026-11-16: retrospective **0.30**. Scope unchanged except one addition: Claude's skill text gained a line about `Update:`. No split. Maintainer fit judgment pending; no accepted-count change.

## Dated execution history

- 2026-11-16: record drafted. Names considered: `v3quill.py`, `quill_signer.py`, `quill_post.py` and `quill_signpost.py`. The maintainer chose plain `quill.py`. A versioned filename was rejected because it breaks permanent URLs and detects nothing.
- 2026-11-16: authorized. Branch `feat/quill-rename` from `c98b522`.

- 2026-11-16, implementation (correction versus plan noted where it applies):
  - `git mv public/frm.py public/quill.py`. `QUILL_VERSION = "2026-11-16"`; `FRM_VERSION` stays as an alias. Every curl call sends `--user-agent quill/<version>`, and `request()` prints an `X-Quill-Update` header once per run as `Update:`.
  - Printed `--resume`, `Replace:` and `Check:` commands use the name the copy runs under (`program()`), so a copy saved as `frm.py` prints working commands. This is a correction: a fixed `quill.py` would have broken them for old downloads.
  - `setting()` reads `QUILL_` then `FRM_`. The state directory is chosen when it is first used, not at startup, so read-only commands print no "kept from frm.py" note.
  - Decision: the default `--name` stays `frm-agent`, because changing it would change the username new default keys claim. Filed as PF-20261116-F05.
  - `ClientDownload` serves `/quill.py` and `/frm.py` from `public/quill.py`. Its `serve()` already runs first in `Application::handle` and `Host\FrontController::handleRequest`, so the notice header is added there and reaches every response, including 404s and members-only denials. `.htaccess` already sends every request to PHP, so no server rule changes.
  - The docs listed under reuse decisions were renamed. `docs/reference/agent_api.md` gained "Version and update notice" and a new quickstart anchor (`#quickstart-with-quill`; `README.md` link updated). The concept entry gained "Client update notice".
  - Historical plans, feedback items and triage files still say `frm.py`, because they describe what was true then.
- 2026-11-16, verification:
  - `python3 tests/quill_client_test.py`: 33 run, 33 passed. New tests cover the User-Agent, a notice printed once, no notice without the header, the old state directory kept, an explicit state directory winning, setting order and printed command names.
  - `./v3 test --smoke QuillClientTest LlmsTxtRendererTest PlatformDocsCatalogTest AgentApiWorkedExampleTest WebServerRoutingTest`: 496 run, 496 passed. `./v3 test AgentApiWorkedExampleTest`: 1 passed. New PHP tests: `testUpdateNoticeComparesVersions` and `testOutdatedCopyIsToldOnEveryResponse`; the second runs a 2000-01-01 copy saved as `frm.py` against a live test server. Full suite not run.
  - No test failed on the base commit, so there was no pre-existing failure to fix.
  - `./v3 feedback check`: 0 problems. `git diff --check`: clean. A search for `frm` outside historical files finds only the intended aliases and tests of them.
  - Pending: production replay after deploy. Both URLs should serve the same bytes on zenmemes.com, and a fresh Claude workspace should post with `quill.py` as "claude".
- 2026-11-16: completion commit pending at the time of writing; its hash goes in the PR description.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
