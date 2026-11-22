# Quill prints the thread link

Cycle: v3/quill_thread_link; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort, R1/P2; [cycle ledger](../effort_ledger/quill_thread_link.md)
Extended estimates: enabled

**Cycle estimate: 0.15**

Status: locally implemented and verified; deployment and production acceptance pending. Next action: push and open a PR when the maintainer asks.

Original request: "please do f37, f32 as one-shots" (2026-11-22), after choosing from a ranked list of non-import backlog items.
Authorization: 2026-11-22 chat instruction authorizes a bounded one-shot for PF-20261111-F37. No push, PR, merge, deployment or production access is authorized. F32 is a separate cycle (`v3/post_menu_outside_close`).
Feedback: PF-20261111-F37 ("After a write frm.py prints /posts/<id>; people usually want /threads/<thread_id>."). `frm.py` is now Quill (`quill.py`, with `quill.pl` and `quill.php`).
Outcome and work type: application. After an accepted write, all three Quill versions print a `URL:` line pointing at the thread: `/threads/<thread ID>` for a new thread and `/threads/<thread ID>#<post ID>` for a reply. The agent docs stop telling agents to build the thread link by hand.
Boundary: `public/quill.py`, `public/quill.pl`, `public/quill.php` (accepted-write output and `QUILL_VERSION`), `tests/QuillClientTest.php`, `docs/agents/claude_zenmemes_skill.md`, `docs/post_with_an_agent.md`, the posting-client concept entry, this record, the plans index, the cycle ledger and the feedback item. No server, API, template or dependency changes.

Approach: Every successful signed or prepared write response already carries `thread_id` (`LocalWriteService::createSignedPost`, `createPreparedPost`, including replays). In each version's `submit`, build the URL from `result.thread_id`; add `#<post ID>` when the post is not the thread itself. Keep the `URL:` label so anything already reading that line still works. When an older server omits `thread_id`, fall back to the existing `/posts/<post ID>` link. Bump `QUILL_VERSION` in all three to `2026-11-22.1` so saved copies are told to update (same-day releases use `.N`).
Reuse decisions: Thread pages already give each post an element `id` equal to its post ID (`templates/partials/thread_root_card.php`), so `#<post ID>` needs no new anchor. The existing `URL:` output line and the existing update notice (`ClientDownload::notice`) are reused; no new output field.
Risks: (1) An agent or script that parses `URL:` as a `/posts/` link: low impact, since the value is still a working link to the post; the docs already prefer the thread link. (2) An older server without `thread_id` in the response: covered by the fallback. (3) The three versions drifting: the existing cross-version HTTP tests run every client; the new assertion goes in a test that loops over all three.
Completion conditions: Through the ordinary CLI entry (`quill.py|pl|php --site … thread/reply`), the accepted output shows `URL: <site>/threads/<thread ID>` for a thread and `…/threads/<thread ID>#<post ID>` for a reply, in all three versions, against a real local server. Resume of an already accepted operation is unchanged. Docs match. Production acceptance is the maintainer's after deployment.
Concepts touched: [Agent posting client](../../concepts/agent_posting_client.md), changed (scenario mentions the printed thread link). No new concept.
Verification and recovery plan: `./v3 test --smoke QuillClientTest`, plus `python3 tests/quill_client_test.py`; `git diff --check`; perl -c / php -l on the clients. Recovery: an agent with an old copy gets the existing `Update:` notice and refetches; the fallback keeps old servers working. Full suite not run (maintainer-only).
Starting revision and branch: e2c112cabc09eb661ce2ff680ba9d880de6a7ba0 (freshly fetched origin/main); branch `feat/quill_thread_link`.
Planning commit: 59915f7de (confirmed before implementation). It carries a `Feedback: PF-20261111-F37` trailer although it does not address the item; the completion commit is the one that does.

## Study history

2026-11-22 intake: pristine pre-inspection estimate missing; I read the Quill output code, the write responses and the docs while scoping. First exposed scoped estimate **0.15**, scope v1, R1/P2; R1 cached reference hashes verified against the manifest. Claude, single exposed rater; no independent rating or measured labor.
2026-11-22 execution entry: 0.15 reaffirmed for scope v1. No alternatives or numbered stages; none manufactured.
2026-11-22 closeout: retrospective 0.15; technical work complete locally; maintainer fit judgment and acceptance pending.

## Execution and verification

2026-11-22: Planning commit confirmed before any client edit. Added `post_link` / `PostLink` / `postLink` to the three clients and used it for the accepted-write `URL:` line. Bumped `QUILL_VERSION` to `2026-11-22.1` in all three. No other output changed; staged, resume-found and already-accepted paths print what they printed before.
2026-11-22: Docs: the Claude skill source (`docs/agents/claude_zenmemes_skill.md`) now says to give the printed `URL:` and how to recognize an outdated copy; `docs/post_with_an_agent.md` says the link is printed; the posting-client concept mentions it. The installed `zenmemes-posting` skill is not in this repository; reinstalling its text from the source file is the maintainer's step.
2026-11-22: Regenerated `docs/feedback/README.md` with `./v3 feedback index`. Besides F37, the index also gained PF-20261116-F04, whose row was already missing on main; derived file, no item edited for it.

Verification: PASS — `python3 tests/quill_client_test.py`: 41 tests OK, including new `PostLinkTest` (thread, reply, and an older server without `thread_id`).
Verification: PASS — Perl `PostLink` and PHP `postLink` run directly on the same three cases: `S/threads/t`, `S/threads/t#r`, `S/posts/r`. `perl -c`, `php -l` and `py_compile` clean.
Verification: PASS — `QuillClientTest::testVersionsShareKeysAndResumeEachOthersOperations` (real local server through the ordinary CLI): `quill.py` thread prints `URL: <site>/threads/<thread>`; `quill.pl` and `quill.php` replies print `…/threads/<thread>#<reply>`; each lost-response resume by another version prints the thread link too. All other QuillClientTest methods for all three clients pass except the baseline below.
Verification: BASELINE FAILURES — `./v3 test --smoke QuillClientTest`: 506 run, 500 passed, 6 failed. `QuillClientTest::testStagedPostIsReportedAndReplacedUnderItsId` (server published an emoji subject instead of staging it) fails identically on unchanged origin/main e2c112ca. `AgentPostingApiTest::testSignedControlDiagnosticsBeforeGenericParserFailure`, `AgentPostingApiTest::testRulesErrorsAndLegacyCompatibility`, `SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection` and `SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes` are the text-rule failures already replayed on unchanged main in [application_version_source](../application_version_source/application_version_source_one_shot.md). `ViewerSessionStoreTest::testConcurrentRequestsShareOneStoreWithoutErrors` failed only in the combined run; it passes alone (17/17) on this branch and on unchanged main; cause unverified, no session code touched.
Verification: PASS — `git diff --check`; `./v3 feedback check` 355 items, 0 problems.
Verification: NOT RUN — full suite (maintainer-only); production. No UI changed, so the UI copy checklist and theme checks are inapplicable.
Migration/dependencies: inapplicable; no server, format or dependency change. The `URL:` label is kept, so readers of that line keep working.
Release/recovery: after deploy, an agent with an older copy gets the existing `Update:` notice and refetches. Production check: post a reply with the served `quill.py` and confirm the `URL:` opens the thread at the reply. Feedback item stays `blocked` until then.

## Closeout

2026-11-22 retrospective scope assessment: **0.15**, same as the prospective 0.15. Scope v1, R1/P2, Claude single exposed rater. The baseline-failure replay took more of the time than the change itself; no scope change.
Outcome: delivered as planned in all three clients, with tests and docs. No new requests; no enhancements added.
Final review: one small helper per client next to `submit`, no duplicated link building elsewhere (the trouble-report and approval lines build their own unrelated links and are unchanged). Diff limited to the recorded boundary plus the derived feedback index.
Commit/merge/publication status: planning commit 59915f7de; completion commit d2987c290 (confirmed); this hash note is a record-only handoff commit. Not pushed, no PR, not merged or deployed.

*— Claude (claude-opus-5-5) · 2026-11-22 UTC*
