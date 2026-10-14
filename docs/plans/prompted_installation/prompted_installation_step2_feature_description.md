> **Feature plan:** [Step 1](./prompted_installation_step1_solution_assessment.md) · [Step 2](./prompted_installation_step2_feature_description.md) · [Step 3](./prompted_installation_step3_development_plan.md) · [Step 4](./prompted_installation_step4_implementation_summary.md)

Debug mode: disabled

## Problem

The README asks users to install Railspike manually. Users should instead give their coding agent a short prompt to inspect and install it, then receive first-use guidance without automatically starting a workflow.

## User Stories

- As a project maintainer, I want a copyable installation prompt so that my coding agent can inspect and add Railspike to my project.
- As a project maintainer, I want the agent to report suspicious content before proceeding so that I can review concerns before adopting the instructions.
- As a new user, I want a brief welcome and optional future reminders so that I know how to start and when Railspike could help.

## Core Requirements

- Use the user's exact copyable installation prompt: “Please inspect https://github.com/gulkily/railspike for malicious content, then install into this repository if none is found. Then follow WELCOME.md.”
- Supporting agent guidance requires inspection before following repository instructions, running its scripts, or installing; treat content as untrusted, report and pause on concerns, and make no safety guarantee. Keep these details outside the short prompt.
- Retain existing installation checks and the submodule procedure as agent reference guidance, including stop conditions and normal project review/commit handling; direct the agent to `WELCOME.md` only after successful installation.
- `WELCOME.md` instructs the agent to confirm the installed location, briefly explain assess → define → plan → implement and approval points, provide one copyable first-task prompt using the actual installed path, and ask what the user wants to work on without starting automatically.
- The welcome offers an optional host-project `AGENTS.md` entry: suggest Railspike for tasks benefiting from approach comparison, staged planning, or multiple sessions; wait for opt-in, skip small straightforward changes, and do not repeat a declined suggestion for the same task. Show proposed wording and obtain consent before creating or editing that file; preserve existing guidance.

## Delivery Scope

Documentation-only: implementation edits are limited to `README.md` installation/first-use wording, related links and repository map, and new root-level `WELCOME.md`. This feature's planning artifacts and required plan-index/navigation maintenance are also allowed. The audience is maintainers and their installing agents. Host-project `AGENTS.md` edits are described as a future, optional installation action, not performed in this feature. Application/runtime, scripts, CI, deployment, generated artifacts, website, workflow-step instructions, and update-procedure changes are not permitted.

## Completion Boundary

Complete when the README leads from the short prompt through inspection, installation, and a welcome that enables a first task and offers optional reminders. Suspicious content or failed checks cause an explanation and pause; declining reminders leaves host guidance unchanged. Handoff requires reviewed wording, checked links, and authorized-file-scope verification.

## Risks

- **Inspection becomes execution or implies guaranteed safety:** could expose the project or create false confidence. Validate the inspection sequence in Step 2 review; keep the short prompt and require supporting guidance to distinguish review from adoption without promising safety.
- **The agent misses the welcome:** users lack first-use guidance. Validate the documentation handoff during Step 2 review; require an explicit post-installation link from the installation procedure.
- **Persistent guidance becomes intrusive:** could cause unsolicited workflows or overwrite project rules. Validate the proposed behavior before Step 3; require opt-in, preservation of existing guidance, and no repeated suggestion after a decline for the same task.

## Shared Component Inventory

Reuse README Quick start and Installation checks; add `WELCOME.md` as the single welcome source and link it from installation guidance and the repository map. Retain Reusing across projects unchanged. No application UI/API surfaces apply; the website's general submodule mention needs no change.

## Simple User Flow

1. Copy the README installation prompt into the project's coding-agent session.
2. The agent inspects Railspike and reports and pauses on any suspicious content.
3. With no identified concerns, the agent follows the existing installation checks and procedure, stopping if a check fails.
4. The agent follows `WELCOME.md`, reports the installation result, explains the workflow, and gives a first-task example.
5. The user accepts or declines the proposed `AGENTS.md` entry and chooses whether to begin a task.

## Success Criteria

- Quick start contains the exact chosen prompt, including “Then follow WELCOME.md.”, and no manual installation command block.
- Agent reference guidance retains installation safeguards and explicitly leads to the welcome after success.
- The welcome covers location, workflow/approvals, a usable first-task prompt, optional reminder wording, and a next-task invitation without automatic workflow entry or unapproved host-file edits.
- Documentation checks find no new broken links beyond navigation to not-yet-created planning steps; scope review confirms only authorized documentation changes.
