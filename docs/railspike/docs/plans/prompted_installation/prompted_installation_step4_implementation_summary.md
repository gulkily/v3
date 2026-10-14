> **Feature plan:** [Step 1](./prompted_installation_step1_solution_assessment.md) · [Step 2](./prompted_installation_step2_feature_description.md) · [Step 3](./prompted_installation_step3_development_plan.md) · [Step 4](./prompted_installation_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Prompted installation

- Changes: replaced manual quick-start commands with the chosen installation sentence; framed existing checks as agent guidance and documented inspection ordering, stop conditions, limitations, and result reporting.
- Verification: `python scripts/check-markdown-links.py` passed (62 files); `bash scripts/check-railspike-git-workflow.sh` passed; `git diff --check` passed. Compared installation command blocks and the update procedure against the planning commit: unchanged. Reviewed inspection-to-installation flow and confirmed only README and this summary changed.
- Notes: welcome handoff follows in Stage 2. Runtime, UI, deployment, migration, and release checks are not applicable to this documentation-only change.

## Stage 2 - Welcome and optional reminders

- Changes: added `WELCOME.md` with installation reporting, a brief workflow explanation, approval points, an installed-path first-task example, optional host `AGENTS.md` guidance, and a next-task invitation. Appended the exact welcome sentence to the prompt and linked the welcome from installation guidance and the repository map.
- Verification: `python scripts/check-markdown-links.py` passed (63 files); `bash scripts/check-railspike-git-workflow.sh` passed; `git diff --check` passed. Compared the complete prompt verbatim with Step 2 and confirmed installation command blocks and update procedure remain unchanged. Reviewed the success path and the suspicious-content, failed-check, accepted/declined-reminder, and first-task cases: welcome requires success; host edits require consent and preserve existing guidance; accepting reminders does not start a task. All edits are within the approved documentation scope.
- Notes: paths resolve within installed Railspike; reminder suggestions stop after a decline for the same task. This is a review of instruction behavior, not an end-to-end test of every coding agent. Runtime, UI, deployment, migration, external-system, role, and release checks are not applicable because no executable or deployed behavior changed. ANSI art remains out of scope.

## Handoff

- Both planned stages are complete; no host-project `AGENTS.md` was edited.
- The planning commit precedes the two stage commits, each of which includes its summary update.
- Review the README installation prompt and WELCOME.md; all local Markdown links resolve.
