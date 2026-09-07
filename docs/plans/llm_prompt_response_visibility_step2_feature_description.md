# LLM Prompt and Response Visibility Step 2 Feature Description

## Problem

LLM activity is currently only partially observable: approved users can see selected post-analysis results, and operators can inspect limited diagnostics, but neither audience has a web view of the exact prompt and response for every call. The feature should make those exchanges inspectable through a private, separate SQLite data store without exposing credentials or making the records part of publicly available data.

## User stories

- As an approved user, I want to view the exact prompt and response for an LLM call so that I can understand how an analysis or generated reply was produced.
- As an operator, I want to inspect successful and failed LLM exchanges in the web UI so that I can troubleshoot provider behavior and application decisions.
- As an operator, I want each exchange tied to its post, provider, model, and time so that I can correlate it with visible forum activity.
- As a privacy-conscious administrator, I want exchange visibility restricted and credentials excluded so that observability does not become an information leak.
- As an operator, I want independent controls for recording exchanges and displaying them so that capture and visibility can be managed separately.

## Core requirements

- Record and display the exact outbound prompt and inbound provider response for supported LLM calls, including successful, failed, and malformed exchanges, in a separate private SQLite database excluded from public data surfaces.
- Provide separate feature flags for conversation recording and conversation UI visibility, both enabled by default.
- Provide an approved-user/operator-only read-only web UI with a chronological Tools listing, individual conversation pages, and per-post links; unauthorized viewers receive no exchange contents.
- Show call metadata for correlation and preserve prompt/response content faithfully while excluding API credentials and prohibited secrets.
- Keep existing operator diagnostics as a complementary troubleshooting surface.

## Shared component inventory

- Thread/post analysis panel (`templates/partials/post_card.php` and `thread_root_card.php`): already renders selected analysis results for approved users. Extend its call-level link or summary to the individual exchange page rather than duplicating analysis markup.
- Post-analysis API response (`Application.php` analysis endpoint): already returns viewer-filtered analysis data. Reuse its authorization convention, but add a dedicated exchange representation rather than exposing raw diagnostics through the existing general response.
- Agent reply status CLI (`scripts/agent_reply_status.php`): already renders provider diagnostics for operators. Extend or link it to the same canonical exchange data; it remains a secondary operator surface.
- Tools navigation and page shell (`templates/pages/tools.php`, `Application.php` tool routing): canonical home for operator/approved-user inspection tools. Add a chronological exchange listing here, linked to individual conversation pages.
- Feature-flag registry/evaluator and Feature Flags page: canonical site-wide flag definition, default, override, and display patterns; add independent recording and UI-visibility flags.
- SQLite Viewer/backup surfaces: remain separate from the private exchange database and must not expose it through generic downloads or public inspection.

## Simple user flow

1. An operator configures the independent recording and UI-visibility flags, which default to on.
2. An approved user opens the LLM Exchanges tool or a relevant thread/comment.
3. The Tools page lists exchanges chronologically, showing call type, related content, provider, model, status, and time.
4. The user opens an individual exchange page and views the exact prompt and response in a read-only conversation view; a per-post link opens the same page.
5. An unauthorized viewer receives no exchange contents, and public data/download surfaces do not include the private records.

## Success criteria

- Every supported LLM call produces one inspectable exchange record in the separate private SQLite database regardless of success or failure state.
- Recording and UI-visibility flags are independently controllable and default to enabled.
- An approved user can open a web page and retrieve the exact prompt and response for a selected exchange.
- The Tools page presents exchanges in chronological order and links to individual conversation pages.
- Each relevant thread/comment provides a link to its associated conversation page.
- An unapproved or anonymous request cannot retrieve exchange content.
- Displayed exchange data contains no API key or authorization credential.
- Operators can correlate an exchange with its provider request identifier and related post/call type.
