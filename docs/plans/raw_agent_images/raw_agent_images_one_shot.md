# Document raw agent image attachments

Cycle: v3/raw_agent_images; Mode: one-shot; [Plans index](../README.md)
Debug mode: enabled (project marker; [log](raw_agent_images_debug_log.md))
Effort study: railspike-effort R1/P2 scope v1; [ledger](../effort_ledger/raw_agent_images.md)

Cycle estimate: 0.04.

Request: [ZenMemes bug report](https://zenmemes.com/posts/reply-20261123060154-9e0513d9). No matching feedback item found; PF-20261111-F38 was the prior Quill option, not this documentation gap.
Authorization: recurring sweep for straightforward tested fixes and draft PRs only. No helper option, runtime change, site claim, upload to production, merge or deploy.
Reuse: the Agent API prepared flow and Signing section, ImageReference and createPreparedPost contract, and Quill multipart transport. No new API or concept.
Completion: a caller without Quill can prepare and upload one attached image using the existing approved-key workflow. Explain exact hash inputs and canonical bytes, multipart fields, format/limits, retrieval, and safe handling if the reply is lost.
Verification: compare synthetic reference and multipart against the existing client/server tests (test fixtures only, no user credential or public writes). Record any coverage gaps and smoke results.

Base: 2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b; branch docs/raw-agent-image-attachments.
Planning commit: pending.

## Study history
- 2026-11-24 UTC: intake and execution entry 0.04, one exposed rater; no pristine intake.

*— Codex · 2026-11-24 UTC*
