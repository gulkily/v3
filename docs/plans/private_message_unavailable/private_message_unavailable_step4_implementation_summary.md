# Compact unavailable private messages Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_unavailable_step1_solution_assessment.md) · [Step 2](./private_message_unavailable_step2_feature_description.md) · [Step 3](./private_message_unavailable_step3_development_plan.md) · [Step 4](./private_message_unavailable_step4_implementation_summary.md)

## Stage 1 - Compact individual recovery

- Changes: separated loading/support, unreadable-key, decryption, and signature outcomes; added neutral unavailable details with manual retry in the canonical message item. Loading and security failures retain visible recovery/warnings. Reader state remains ephemeral; verification still gates plaintext. Grouped FDP artifacts and repaired navigation.
- Verification: `php tests/run.php PrivateMessageReaderTest PrivateMessageListTest PrivateMessagePageControllerTest` — 8 passed; reader syntax and whitespace passed. Covers real encrypted/unsigned/tampered messages, category boundaries, deduplicated retry, recovery, and no plaintext persistence.
- Notes: planning-only commit `c479177b`. No schema/API changes, deployment, merge, or push. Group presentation follows in Stage 2.

## Stage 2 - Collapsed initial history

- Changes: added same-sender/local-date summaries for consecutive unavailable messages, native button expansion with accessible state/control relationships, and shared visible-representation lookup for Latest and seen visibility. Individual cards remain canonical and retain order/identity; warnings and pending reads cannot enter groups.
- Verification: reader/composer/page suites — 11 passed; JavaScript syntax and whitespace passed. Full encrypted browser journey passed (`/tmp/private-message-browser-GxgvmW/`), including normal Messages entry, twenty-to-one collapse, exact expansion order, keyboard activation, details, latest-summary acknowledgment, date/signature boundaries, and 375px screenshot/no overflow. Existing retry tests now use the details entry point.
- Notes: cross-page regrouping and asynchronous retry reconciliation follow in Stages 3–4; initial grouping does not change server receipts or read semantics.

## Stage 3 - Stable history joins and restart

- Changes: reading anchors resolve through visible summaries while retaining underlying message identity; history regroups after reading attempts settle and corrects position. Joined runs preserve an open choice from either side, and reconciliation removes obsolete summaries. Connected focused controls are restored without scrolling after a summary moves.
- Verification: reader/store/page suites — 17 passed. Full browser journey passed (`/tmp/private-message-browser-3e8Atn/`), including a sixty-message run across three pages, exact identity/order coverage, open and collapsed joins, summary anchoring within 5px, stale history/restart, retained drafts, and existing delayed-read/navigation/concurrent-send recovery.
- Notes: grouping remains presentation-only. Individual retry/send reconciliation follows in Stage 4.
