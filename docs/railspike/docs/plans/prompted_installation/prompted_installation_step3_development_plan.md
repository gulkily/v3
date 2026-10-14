> **Feature plan:** [Step 1](./prompted_installation_step1_solution_assessment.md) · [Step 2](./prompted_installation_step2_feature_description.md) · [Step 3](./prompted_installation_step3_development_plan.md) · [Step 4](./prompted_installation_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Scope: documentation-only; `README.md` installation/first-use guidance, related links and repository map, plus new `WELCOME.md`. Allow this feature's plans, summary, and required plan-index/navigation maintenance. Do not edit host-project `AGENTS.md`, workflow instructions, scripts, website, application, CI, or deployment files. ANSI art is a separate feature.
- Audience/outcome: maintainers copy the exact chosen prompt, ending with “Then follow WELCOME.md.”; their agent inspects, installs using existing checks, then follows the installed Railspike welcome for first-use guidance and optional persistent reminders.
- Completion/recovery: suspicious content or failed checks cause a report and pause; optional reminders require consent and preserve existing guidance; neither installation nor the welcome automatically begins a Railspike task.
- Evidence/release: verify wording against Step 2, relative links, preserved installation safeguards, and authorized file scope. Run one Markdown-link checker, the existing Git-workflow fixture, and whitespace checks. These verify documentation and existing Git behavior, not the safety of arbitrary upstream content or compliance by every agent. Runtime, UI, deployment, and migration checks are not applicable.
- Commit boundary: after approval, create a feature branch, organize and commit approved plans under `docs/plans/prompted_installation/`, then create the Step 4 summary. Commit each verified stage with its summary update before proceeding.

## Key Risks

- Inspection mistaken for execution or a guarantee: review ordering before edits; retain the exact short prompt and put untrusted-content handling and stop-on-concerns guidance in supporting prose.
- Welcome missed or reminders become intrusive: trace the successful-installation handoff and review opt-in wording before stage completion; link directly to one welcome source and forbid automatic workflow entry or unapproved host-file changes.

## Stage 1

- Goal: provide a complete prompted-installation path in the README (about 30 minutes).
- Dependencies: approved plan and initial planning commit.
- Expected changes: replace manual quick-start installation with the user's installation sentence, adding the welcome sentence in Stage 2 when its target exists; reframe Installation checks for agents; retain existing commands, stop conditions, review/commit handling, and first-task transition. Explain inspection handling outside the prompt.
- Verification approach: compare the installation sentence verbatim to Step 2; review the path from inspection through installation and a result report; confirm commands and update procedure are unchanged; run documentation checks, allowing only the not-yet-created Step 4 link before its summary exists.
- Risks or open questions: supporting guidance must not expand the short prompt or imply guaranteed safety; validate those boundaries in the diff before committing.
- Canonical components/API contracts touched: README Quick start and Installation checks; no application contracts.

## Stage 2

- Goal: complete first-use onboarding and optional future reminders (about 45 minutes).
- Dependencies: Stage 1 verified and committed.
- Expected changes: add compact `WELCOME.md` instructions covering installation location, workflow and approvals, a first-task example using the installed path, optional `AGENTS.md` wording for offering Railspike, and a next-task invitation. Append “Then follow WELCOME.md.” to the installation prompt; supporting guidance resolves it within the installed Railspike directory after successful installation. Link it from installation guidance and the README repository map. Describe consent and preservation of host guidance.
- Verification approach: compare the full prompt verbatim to Step 2; walk through successful installation, failed checks, suspicious content, reminder acceptance/decline, and choosing a first task; verify no automatic workflow entry, repeated declined suggestion for the same task, or unapproved host-file edit. Run all documentation checks with no missing links once the summary exists; verify final file scope and summary evidence.
- Risks or open questions: stale paths or duplicate instructions could confuse first use; require the actual installed path in examples and keep the welcome instructions in one file.
- Canonical components/API contracts touched: README installation handoff/repository map and new WELCOME.md; optional host guidance is a proposed snippet only.
