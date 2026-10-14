> **Feature plan:** [Step 1](./installation_process_improvements_step1_solution_assessment.md) · [Step 2](./installation_process_improvements_step2_feature_description.md) · [Step 3](./installation_process_improvements_step3_development_plan.md) · [Step 4](./installation_process_improvements_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

Please review installation_log.txt, identify failure modes and save them to a checklist file, and write Step 1 of railspike for improving our installation process.

## Revised Direction

Copy Railspike into the project by default after unpacking and reviewing it in a temporary directory; use a submodule only when explicitly requested. Treat the first suitable task supplied during welcome as a request to use Railspike, with an opt-out and existing step approvals preserved.

## Problem

The [installation log](../../../installation_log.txt) shows unnecessary Git-state prerequisites disrupting adoption and welcome failing to carry installation intent into the first substantial task (see the [failure modes checklist](./installation_process_improvements_failure_modes_checklist.md)).

## Option A — Copy the reviewed snapshot

Review an isolated source snapshot, then copy it into a dedicated project directory, preserving the upstream structure and identifying its revision; make Railspike the announced default for the first suitable task.

- Pros: Removes submodule setup, clean-tree requirements, and installation-driven stashing; minimizes missing-file and broken-link risks.
- Cons: Copies repository material beyond the workflow essentials; updates must account for local edits and source provenance.

## Option B — Copy a minimal distribution

Use the same review-and-copy flow and onboarding default, but install only a defined, self-contained set of workflow files.

- Pros: Smaller project footprint and less irrelevant material for agents to encounter.
- Cons: Requires a maintained file list and link/dependency validation; new upstream references can make the installed subset incomplete.

## Recommendation

Choose **Option A** first. Download and safely unpack once, inspect without executing or adopting source instructions, and copy that same reviewed snapshot without Git metadata. Keep unrelated project work and staging intact. Verify destination contents before welcome; report the installed path, source revision, and any remaining issue. Submodules remain an explicit alternative; existing installations require recognition rather than automatic conversion.

Welcome should announce that the next suitable task will use Railspike unless the user opts out. Starting planning does not approve later steps or implementation; retain the small-task exception. Persistent project guidance is a separate choice and must preserve existing instructions.

This is a small vertical slice from source acquisition through verified copying, failure recovery, and the first planning artifact. Remaining decisions cover safe extraction and destination conflicts, copied content and licensing, incomplete-copy recovery, revision recording and manual updates, and persistence across sessions. Target one day or at most eight implementation stages; defer a packaged minimal distribution and automatic updater. Application deployment and changes to step approval gates remain outside scope.
