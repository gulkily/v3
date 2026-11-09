> **Feature plan:** [Step 1](./zm_helper_diagnostics_step1_solution_assessment.md) · [Step 2](./zm_helper_diagnostics_step2_feature_description.md) · [Step 3](./zm_helper_diagnostics_step3_development_plan.md) · [Step 4](./zm_helper_diagnostics_step4_implementation_summary.md)

# Step 3: Development Plan — ZenMemes helper diagnostics

## Completion Contract

- Normal entry: an agent runs `frm.py thread` or `frm.py reply`; Claude gets there through the new skill text.
- End-to-end outcome: a failed write prints the server's error, then one trouble report appears in the standing thread and is printed locally.
- Required recovery: if the report can't be posted, `frm.py` prints why after the original error and saves the report; the next run posts it.
- External verification: Claude posts the standing thread and one live test report from a cloud session.
- Release: Ilya merges, deploys `frm.py` to zenmemes.com and installs the new skill text.

## Key Risks

- **High risk:** a report leaks a key, token, draft or path. Validation: a test feeds secrets into every report field and checks none appear. Mitigation: reports use a fixed field list; the server reply is cut to 300 characters.
- **No identity yet means no signed report.** Validation done in planning: `docs/reference/agent_api.md` documents unsigned `create_reply`, and `llms.txt` says this instance accepts it. Mitigation: post unsigned when no identity exists; queue it if that fails too.
- **Generic agents won't know the thread ID.** Publishing it in `llms.txt` changes `LlmsTxtRenderer.php`, which Step 2 excluded. Mitigation: `frm.py` takes `--report-thread` or `FRM_REPORT_THREAD`; the skill and `thread_to_pr.md` pass it. Agents using only the `llms.txt` quickstart won't report until a follow-up adds that line.
- **Report storms.** A looping agent could post many reports. Mitigation: one report per operation, recorded in the operation's state file.

## Stage 1 — Build redacted reports
- Goal: `frm.py` turns a failure into a short, safe report.
- Dependencies: none.
- Expected changes: new `trouble_report(command, route, status, reply, version) -> str`. Fields: command, route, HTTP status, server reply (trimmed), `frm.py` version, outcome. No body, subject, token, key or path.
- Verification approach: unit tests in `tests/frm_client_test.py`, including the secret-leak test.
- Risks or open questions:
  - Impact: a leaked token or key.
  - Early warning / validation: the secret-leak test.
  - Mitigation: a fixed field list, never a dump of local state.
- Canonical components/API contracts touched: `public/frm.py` `ClientError` and `response_error`, reused as the report's source.

## Stage 2 — Post reports, once per operation
- Goal: a failed write posts its report to the standing thread.
- Dependencies: Stage 1.
- Expected changes: new `--report-thread`, `FRM_REPORT_THREAD` and `--no-report`. New `post_trouble_report(site, thread_id, report, identity)`. It signs a reply when an identity exists and otherwise posts unsigned. The operation state file records that a report went out. A failed report goes to a pending file in the state directory, and the next run posts it. The original error always prints first.
- Verification approach: unit tests with a mocked `request` cover a signed report, an unsigned report, a failed report, a queued report and no duplicate. One case in `tests/FrmClientTest.php` covers the HTTP path.
- Risks or open questions:
  - Impact: a report failure hides the real error, or a report posts twice.
  - Early warning / validation: the tests check output order and count.
  - Mitigation: report code runs after the error prints and catches all its own errors.
- Canonical components/API contracts touched: `create_signed_post` (reused through `frm.py`'s write path) and unsigned `create_reply`.

## Stage 3 — Open the standing thread
- Goal: a trouble thread exists, and its ID is known.
- Dependencies: none; it can run before Stage 1.
- Expected changes: Claude posts "Agent trouble reports" as "claude" with the current `frm.py`. The post explains what reports contain and how to turn them off.
- Verification approach: `frm.py check` on the post, plus its thread link.
- Risks or open questions: none.
- Canonical components/API contracts touched: none changed.

## Stage 4 — Write the new skill text
- Goal: Claude posts through `frm.py`, with the Claude-specific rules kept.
- Dependencies: Stage 3 (thread ID).
- Expected changes: new `docs/agents/claude_zenmemes_skill.md`. It covers downloading `frm.py`, `--name claude`, `--report-thread`, the writing audit, `/api/text_rules` instead of hard-coded character lists, thread links, `--resume`, sweeps, and when Claude reports trouble outside `frm.py`. The same text goes to Ilya as an attached file.
- Verification approach: a fresh cloud session with only this text posts a reply and reports the thread link.
- Risks or open questions: the old skill's text rules are out of date (the site now accepts arrows and currency); the new text defers to the site.
- Canonical components/API contracts touched: the `zenmemes-posting` skill, replaced.

## Stage 5 — Update agent docs and concepts
- Goal: every agent doc points at one client and one trouble thread.
- Dependencies: Stages 2 and 4.
- Expected changes: `docs/agents/thread_to_pr.md` (pass `--report-thread`; Claude uses the same client), `docs/agents/README.md` (list the skill text), `docs/agents/feedback_triage.md` (read the trouble thread), new `docs/concepts/agent_posting_client.md`.
- Verification approach: link and path check; `git diff --check`.
- Risks or open questions: none.
- Canonical components/API contracts touched: the feedback items concept, used unchanged.

## Stage 6 — Replay in the reported environment
- Goal: show the reported failure is now readable and reported.
- Dependencies: Stages 1–5.
- Expected changes: none; this is evidence. From a cloud session with the branch's `frm.py`: reply to a missing thread, and confirm the error prints and one report lands. A mocked `prepare_identity` failure covers the case from the screenshots.
- Verification approach: the report's link in the trouble thread, recorded in the Step 4 summary. Ilya checks it from his phone. Focused tests only; Ilya runs the full suite.
- Risks or open questions: live test reports stay in the thread; each is labeled as a test.
- Canonical components/API contracts touched: none.

## Concepts touched

- Agent posting client (`public/frm.py`): changed; entry `docs/concepts/agent_posting_client.md` created in Stage 5.
- Agent trouble report: created in Stages 1–3; described in that entry.
- Feedback items: used unchanged.
- Claude posting guide: replaced in Stage 4.

*— Claude · 2026-11-09 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
