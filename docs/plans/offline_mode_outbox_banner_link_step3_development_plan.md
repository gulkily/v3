# Offline Mode Outbox Banner Link — Step 3 Development Plan

## Stage 1
- Goal: Give offline readers a direct, discoverable path from the displayed offline-mode banner to their local Outbox.
- Dependencies: Approved Step 2 description; the existing `offline-mode-bar` shell; the existing `/tools/outbox/` route and its offline navigation support.
- Expected changes: Extend the offline-reader banner with an Outbox link to the canonical route; make any minimal responsive presentation adjustment needed to retain the banner indicators; add focused shell coverage for the link and preserve existing Outbox/offline-reader behavior checks.
- Verification approach: Run PHP syntax checks for changed templates/tests, then focused offline-reader shell and offline-navigation worker tests; manually inspect the rendered offline-reader shell for the visible Outbox route.
- Risks or open questions: The link must not displace or obscure the existing archive and reader indicators on narrow viewports.
- Canonical components/API contracts touched: `templates/pages/offline_reader.php` `offline-mode-bar`; `/tools/outbox/` route; existing offline navigation service-worker route policy.
