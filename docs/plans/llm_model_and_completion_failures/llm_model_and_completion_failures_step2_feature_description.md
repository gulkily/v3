> **Feature plan:** [Step 1](./llm_model_and_completion_failures_step1_solution_assessment.md) · [Step 2](./llm_model_and_completion_failures_step2_feature_description.md) · [Step 3](./llm_model_and_completion_failures_step3_development_plan.md) · [Step 4](./llm_model_and_completion_failures_step4_implementation_summary.md)

Debug mode: disabled

# LLM Model and Completion Failures Step 2 Feature Description

## Problem

An incompatible moderation override and exhausted reply allowance prevent useful results; queue completion can obscure failed fulfillment.

## User Stories

- As an operator, I want predictable model selection and bounded generation so that calls succeed with controlled costs.
- As a reader, I want analysis or meaningful failure feedback so that I know the outcome.
- As an operator, I want deliberate recovery so that corrected requests succeed without duplicate replies.

## Core Requirements

- Moderation inherits the main model when its override is absent or blank. Generated configuration defaults to inheritance; explicit overrides remain intact. Correct only the known incompatible deployment override.
- Agent replies use a configurable positive completion allowance with a finite default and maximum. Reject invalid settings before calls; document cost implications and validate the default against the observed failure.
- Recognize provider-reported token exhaustion, including responses containing partial text, as a distinct failure. Do not publish empty/truncated replies; retain actionable reader and operator feedback.
- Queue results distinguish publication, already-published work, skips, unavailable requests, and failed fulfillment. Preserve useful failure details without exposing secrets.
- After correcting configuration, operators can deliberately retry selected failed moderation work and agent replies, including replies whose queue task previously completed. Retain the response mode and existing eligibility, content-version, and duplicate protections. Configuration and exhaustion failures must not repeatedly incur calls without operator action; no automatic budget escalation or model substitution.

## Delivery Scope

Work type: **application change**, including configuration defaults, the known deployment correction, operator guidance, and affected-request recovery. Scripture prompt revisions, broader scheduling changes, provider migrations, and bulk backlog replay are excluded.

## Completion Boundary

Ordinary moderation and chooser-requested analysis produce a valid moderation result and one published reply through the worker. Failure feedback and deliberate recovery work consistently. Release requires regression checks and live verification on isolated content before targeted recovery of the affected request.

## Risks

- **Override compatibility:** Broad rewriting could break custom gateways. Before Step 3, confirm inheritance and preservation cases; restrict deployment correction to the identified override.
- **Cost and latency:** Larger allowances may still exhaust or time out. Before Step 3, define finite limits and a bounded live-verification plan; measure usable output before release.
- **Duplicate or repeated work:** Recovery could republish or loop on persistent failures. Before Step 3, map both queue and reply failure states; require explicit recovery and duplicate checks.

## Shared Component Inventory

- **Configuration schema, writer, and operator setup:** Extend canonical settings and shared provider resolution.
- **Moderation CLI/status and LLM exchange list/detail pages:** Reuse scoring and diagnostic records.
- **Queue run/status and runtime status views:** Extend shared fulfillment outcomes and recovery.
- **Thread/post cards and request/status API:** Reuse chooser, publication, and failure feedback contracts.

## Simple User Flow

1. Operator corrects the override and configures the bounded reply allowance.
2. Reader requests analysis; the worker processes moderation and reply work with accurate outcomes.
3. If work fails, the operator diagnoses it, corrects the cause, and retries that work once.

## Success Criteria

- Missing/blank overrides inherit correctly; explicit overrides remain unchanged.
- Live moderation returns a valid result and the reproduced analysis case yields one usable reply.
- Exhaustion never publishes partial output; queue, API, and page feedback agree on failure.
- Targeted recovery retains the selected mode and publishes no duplicate reply.
