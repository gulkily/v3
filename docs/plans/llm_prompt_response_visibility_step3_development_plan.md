# LLM Prompt and Response Visibility Step 3 Development Plan

## Stage 1
- Goal: Establish the private exchange database and capture contract.
- Dependencies: Approved Step 2; existing SQLite/configuration conventions.
- Expected changes: Add separate database configuration/initialization, independent recording/UI-visibility feature flags defaulting on, and a shared exchange record contract; keep records outside public artifacts/downloads.
- Verification approach: Initialize a test environment and verify database separation and file boundaries.
- Risks or open questions: Confirm production permissions, retention, and payload redaction rules.
- Canonical components/API contracts touched: SQLite initialization; application configuration; FeatureFlagRegistry/FeatureFlagEvaluator; provider exchange contract.

## Stage 2
- Goal: Capture and persist exact exchanges for all supported LLM calls.
- Dependencies: Stage 1.
- Expected changes: When recording is enabled, record request/response payloads, status, timing, provider, model, request ID, call type, and related post across analysis and direct reply paths, including failures.
- Verification approach: Unit/integration tests cover success, malformed response, transport error, and retry behavior without changing existing results.
- Risks or open questions: Prevent duplicate records for retries or cached analysis; never store credentials.
- Canonical components/API contracts touched: Structured chat providers; direct agent reply provider; PostAnalysisService; AgentReplyFulfillmentService.

## Stage 3
- Goal: Provide an authorized read service for exchange lists and details.
- Dependencies: Stage 2; approved-profile resolution.
- Expected changes: Define flag-aware list, individual lookup, and related-post lookup operations with safe presentation fields and approved-user/operator authorization; UI reads are unavailable when visibility is disabled.
- Verification approach: Test approved, unapproved, anonymous, missing, and unrelated-post access.
- Risks or open questions: Confirm whether operator access equals approved-user access or requires root approval.
- Canonical components/API contracts touched: Viewer authorization conventions; application read services; exchange record contract.

## Stage 4
- Goal: Add the chronological Tools index and individual conversation pages.
- Dependencies: Stage 3.
- Expected changes: Add Tools navigation/routing; when UI visibility is enabled, render bounded chronological metadata, links, and read-only prompt/response conversations with failure and correlation details.
- Verification approach: Manual browser checks for ordering, empty state, links, exact text preservation, large payloads, and denied access.
- Risks or open questions: Choose safe list size and usable structured-payload presentation without truncating stored data.
- Canonical components/API contracts touched: Tools page shell/navigation; page routing/template renderer; exchange read service; shared escaping/layout helpers.

## Stage 5
- Goal: Add contextual links from relevant threads/comments and align operator diagnostics.
- Dependencies: Stage 4.
- Expected changes: Add approved-user-visible per-post links where visibility is enabled and exchanges exist; extend `agent_reply_status.php` to use canonical exchange data; preserve other viewers’ current rendering.
- Verification approach: Check thread and standalone post paths, absent/multiple exchanges, CLI output, and public download surfaces.
- Risks or open questions: Mark historical calls without captured prompts unavailable rather than reconstructing them.
- Canonical components/API contracts touched: Post/thread cards; analysis display data; agent reply summaries; operator CLI.

## Stage 6
- Goal: Complete regression, security, and operational verification.
- Dependencies: Stages 1–5.
- Expected changes: Add focused tests and operational documentation for setup, retention, access control, and private-database handling.
- Verification approach: Run the existing suite; exercise successful/failed calls and all viewer roles; confirm no credentials or private records leak into public responses, backups, or read-model downloads.
- Risks or open questions: Validate deployment behavior when the private database is missing, locked, or unavailable.
- Canonical components/API contracts touched: Existing test harness; backup/download boundaries; exchange recorder/read service; access-control paths.
