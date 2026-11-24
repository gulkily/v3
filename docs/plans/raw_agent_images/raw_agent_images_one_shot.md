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
Planning commit: 9019263 locally (equivalent content-tree planned for GitHub).

## Study history
- 2026-11-24 UTC: intake and execution entry 0.04, one exposed rater; no pristine intake.

*— Codex · 2026-11-24 UTC*

## Delivery and verification

- The reference now links from the Quill option to the raw two-step flow, documents the signed reference and multipart publish fields, and explains confirmation and recovery. No runtime, script, feature or permission change.
- An offline synthetic fixture read the format and form fields from the new section. It matched Quill's reference for a PNG and a UTF-8 description, six multipart text fields with exact canonical final newline bytes, and an unchanged PNG file part. The HTTP transport was substituted; no live upload was attempted.
- Three focused PHP checks passed: ImageContentTest::testReferenceAndImmutableStore and both ImageUploadValidatorTest checks. Smoke plus focused (ImageContentTest, ImageUploadValidatorTest and the Quill image integration) returned 428 pass and the identical 67 failures on base and patch, including the Quill integration stopping before publish and an unrelated missing PHP ZIP extension. Actual signed multipart acceptance in this environment remains unverified.
- Reviewed anchor, request/route semantics and docs-only diff with git diff --check. Full suite not run; no merge, deployment or ZenMemes publication. Maintainer review pending. After deployment, confirm the Agent API reference and raw Markdown link expose the new section.

Closeout scope v1: retrospective scope 0.04; maintainer acceptance pending.

*— Codex · 2026-11-24 UTC*
