# Extension/Improvement Cookbook Step 2 Feature Description

## Problem

The site’s extension facilities are capable but not discoverable through concrete examples. Developers need a concise cookbook that turns the existing agentic and workflow capabilities into safe, reusable feature patterns.

## User Stories

- As a developer, I want to find a proven extension pattern so that I can build a feature without recreating safety and operational conventions.
- As a developer, I want examples of post-triggered and scheduled agent workflows so that I can choose an appropriate scope.
- As an operator, I want every example to state its review, audit, cost, and disable boundaries so that experimentation remains controllable.

## Core Requirements

- Provide a short map of the existing extension facilities and when to use each.
- Teach one shared lifecycle: trigger, eligibility, durable work, bounded agent task, guarded outcome, and audit/disable path.
- Include recipes for moderation-signal NVC replies, high-signal synthesis, feature-proposal handoff, and structured bug-report follow-up.
- Mark the duplicate/related-post concierge, decision log builder, and FAQ candidate generator as recommended next recipes.
- Describe classifications as advisory and require provenance and an explicit review or publishing guard for canonical output.

## Shared Component Inventory

- `README.md` examples and documentation index: extend as the canonical entry point for the cookbook.
- Agent-reply analyze/publish contract and post-analysis APIs: reuse as the canonical per-post agent-work example.
- Fastmod reference and task-queue/CLI references: reuse for scoring, bounded asynchronous work, and operator controls.
- Codex-handoff workflow and CLI reference: reuse for developer-workflow examples.
- Production LLM/agent-reply runbook and private LLM-exchange UI: reuse for configuration, audit, and operational guidance.

## Simple User Flow

1. A developer chooses a recipe whose trigger and output match the desired feature.
2. The recipe identifies the existing site facilities to reuse and its required safeguards.
3. The developer adapts the recipe into a scoped feature plan.
4. An operator can inspect, limit, disable, or review resulting agent work using the documented controls.

## Success Criteria

- A new developer can select an appropriate extension approach from the map and recipes without a separate architecture walkthrough.
- Each core recipe identifies its trigger, output, safety boundary, and relevant existing facilities.
- The cookbook distinguishes per-post, queued, and cross-post workflows and distinguishes draft/reviewed from guarded publishing.
- Every recipe directs readers to existing audit and disable controls.
