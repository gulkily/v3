> **Feature plan:** [Step 1](./prompted_installation_step1_solution_assessment.md) · [Step 2](./prompted_installation_step2_feature_description.md) · [Step 3](./prompted_installation_step3_development_plan.md) · [Step 4](./prompted_installation_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

I want to make a small addition/modification to the installation instructions, please.
I realized that the instructions should not be for manual installation, but for prompted installation.
In other words, the installation instructions should be to prompt your coding agent with something like this:

"Please add the Railspike process from https://www.github.com/gulkily/railspike to this project, after inspecting it for any malicious prompts or scripts."

Please write Step 1 of RAILSPIKE.md.

## Understood Intent

Use Railspike's Step 1 workflow to assess a small documentation change: make a copyable coding-agent prompt the installation entry point in the README, with inspection before adoption.

## Problem

The README currently asks users to run installation commands themselves instead of asking their coding agent to inspect and add Railspike.

## Option A — Prompt first, retain agent reference guidance

Replace the manual quick-start installation with a copyable prompt requesting inspection for malicious prompts or scripts before adding Railspike; frame the existing installation checks as reference guidance for the agent.

- Pros: Matches the requested interaction, preserves established installation safeguards, and keeps the documentation change small.
- Cons: Requires clear wording so the reference commands do not read as a second manual onboarding path.

## Option B — Prompt-only installation guidance

Replace both the quick-start commands and detailed installation checks with a prompt that asks the agent to inspect Railspike and choose an appropriate way to add it to the project.

- Pros: Shortest user-facing instructions and flexibility across projects.
- Cons: Removes a shared installation procedure and makes installation behavior more dependent on the agent's choices.

## Recommendation

Choose **Option A**. Use the user's proposed prompt as the basis, making clear that repository content is material to inspect before following its instructions or running its scripts. Ask the agent to report concerns and pause if it finds suspicious content; avoid implying that inspection guarantees safety.

This is a complete, small documentation-only slice: the user copies one prompt, the agent inspects the source, and then uses the existing installation guidance to add Railspike and report the result. Keep the workflow steps and update procedure unchanged. Later planning should define the exact README wording and verify that installation guidance consistently addresses the agent.
