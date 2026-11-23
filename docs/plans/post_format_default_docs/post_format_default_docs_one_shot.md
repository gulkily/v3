# Document which posting routes default to Markdown

Cycle: v3/post_format_default_docs; Mode: one-shot; [Plans index](../README.md)
Debug mode: enabled (project marker)
Effort study: railspike-effort R1/P2 scope v1; [ledger](../effort_ledger/post_format_default_docs.md)
Extended estimates: enabled; one bounded documentation change, no separate work units

**Cycle estimate: 0.04**

Status: planning; next action is the documentation and its focused assertions.

Request: [ZenMemes report](https://zenmemes.com/threads/thread-20261110093323-588dbee3d289f3fb679d7052bdf8f827), PF-20261111-B23.
Authorization: the maintainer requested recurring ZenMemes sweeps and draft PRs for straightforward fixes needing no decisions. Scope is documentation only with renderer assertions and existing feedback status; no protocol, client, configuration, site write, deploy or merge.
Reuse: edit the existing Post format subsection of LlmsTxtRenderer and align linked Agent API/Markdown references that repeat the blanket default. The generated skill uses the same text. Keep the renderer test; no new documentation system.
Completion: the guide distinguishes omission on normal prepare/create routes, QDB quote exceptions, and omission inside direct-signed canonical records. Describe Quill paths accurately without inventing a format flag. Existing behavior remains unchanged. Revert prose if it proves unclear.
Concepts touched: existing Markdown post-format documentation; no new capability or inventory.
Verification: inspect the writer defaults, canonical parser/reporting, and Quill clients. Unit-check rendered copy and ordinary create/read defaults in isolated repo test fixtures; do not generate or use signing keys. Verify feedback checker/index and diff. No browser, full suite, or production write.

Starting revision: 9ae843cf6c433c95bb44c914b651b72e79e8b377; branch fix/document-post-format-defaults.
Planning commit: pending.

## Study history
- 2026-11-23 UTC: intake 0.04 after inspecting existing source (no pristine estimate); execution entry reaffirmed at 0.04. One exposed rater.

*— Codex · 2026-11-23 UTC*
