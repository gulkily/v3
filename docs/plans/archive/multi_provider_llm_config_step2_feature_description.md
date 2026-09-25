# Multi Provider LLM Config Step 2 Feature Description

## Problem

The application currently exposes LLM setup through Dedalus-specific config, which makes production recovery difficult when Dedalus is unavailable and obscures support for direct OpenAI, Anthropic, OpenRouter, or gateway-backed providers.

## User Stories

- As an instance operator, I want to select an LLM provider in private config so that I can use the account and reliability profile that fits my deployment.
- As an instance operator, I want direct OpenAI, direct Anthropic, OpenRouter, Dedalus, and OpenAI-compatible gateway options so that I am not locked into one vendor.
- As an instance operator, I want provider failures to retain redacted request/response diagnostics so that production issues can be debugged without leaking secrets.
- As an approved user requesting a reply, I want provider changes to preserve existing analysis and reply behavior so that the site workflow stays predictable.
- As a maintainer, I want provider-specific differences hidden behind one shared contract so that future providers can be added without forking analysis and reply features.

## Core Requirements

- Private config must support a provider-neutral LLM selection while preserving existing Dedalus config compatibility for current installs.
- OpenAI-compatible providers must cover OpenAI, OpenRouter, Dedalus, LiteLLM, and local/proxy gateways through common base URL, model, key, and optional header settings.
- Direct Anthropic must be supported as a first-class provider, including structured analysis output compatible with the existing stored analysis contract.
- Provider failures must continue to store redacted request/response diagnostics and surface useful operator-facing status through existing diagnostic commands.
- Missing or disabled provider config must keep the current graceful behavior: deterministic analysis still runs where available, and post/reply rendering must not break.

## Shared Component Inventory

- Private config file and `./v3 private-config`: extend the canonical operator configuration surface; no new config UI is needed.
- `PrivateConfig` loading and environment overrides: reuse as the single source of private provider settings.
- Post analysis service and stored analysis rows: reuse the existing analysis contract, including provider metadata, raw diagnostics, and failure state.
- Agent reply fulfillment and generated-response rows: reuse stored analysis and existing reply gates; provider switching must not create a separate reply workflow.
- `./v3 agent-reply status`: extend the current diagnostic command output only as needed for provider-neutral labels.
- Production deploy runbook: update the existing operator documentation instead of adding a parallel provider guide.
- Browser post-analysis and reply feedback UI: reuse unchanged result states unless provider-neutral wording is required.

## Simple User Flow

1. Operator chooses a provider family: OpenAI-compatible, Anthropic, or stub.
2. Operator writes private config with the provider, API key, model, timeout, and provider-specific optional settings.
3. Operator runs the existing config view or diagnostic command to confirm the app sees the intended provider without printing secrets.
4. A post analysis or reply request runs through the selected provider.
5. The app stores the same analysis/reply outcomes and diagnostics regardless of provider.
6. If the provider fails, the operator uses existing status output to inspect status code, request ID, response body, and selected provider/model.

## Success Criteria

- A fresh or existing install can run with direct OpenAI by changing private config only.
- A fresh or existing install can run with direct Anthropic by changing private config only.
- A fresh or existing install can run with OpenRouter or another OpenAI-compatible gateway by changing private config only.
- Existing Dedalus installs continue to work without immediate config rewrites.
- Provider failures show provider-neutral diagnostics with redacted secrets in stored rows and `./v3 agent-reply status`.
- Existing post analysis, agent reply request, fulfillment, and rendering tests continue to pass.
