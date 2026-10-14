> **Feature plan:** [Step 1](./image_upload_enhancements_step1_solution_assessment.md) · [Step 2](./image_upload_enhancements_step2_feature_description.md) · [Step 3](./image_upload_enhancements_step3_development_plan.md) · [Step 4](./image_upload_enhancements_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Approved members use every existing thread/reply composer to select/drop/paste, preview, describe, publish, and view a prepared image. Failures preserve the open draft/attachment and allow retry/removal without duplicate posts. Release requires server bypass tests, mobile/keyboard coverage, backup/restore/legacy repair, and decoder availability in the serving PHP runtime. No production deployment is implied. Update only verified `todo.txt` items, retaining partial/deferred work and unrelated edits. No schema changes; eight stages budgeted at approximately one hour each. Return to Step 2 if feasibility requires broader scope.

## Key Risks

- **High risk: decoding/deployment.** Current PHP lacks GD/Imagick; header inspection is insufficient. Stage 1 proves bounded GD JPEG/PNG decoding and still-image validation, including malformed/APNG samples; missing capabilities reject new uploads with operator guidance, never fall back to headers. Blocks Stages 2–4.
- **High risk: signed/legacy data.** Global limit changes break old references. Stage 2 separates new-upload policy from legacy read/restore and verified existing-post repair; Stage 4 freezes prepared bytes through retry.
- **High risk: mobile/eligibility.** Large decodes can exhaust memory; stale identity checks can expose/strand controls. Stages 3/5 validate bounded preparation and identity refresh before UI rollout. Browser formats remain capability-dependent; absent physical-device evidence must be reported.

## Stage 1
- Goal: Establish server-validation feasibility and runtime readiness.
- Dependencies: Step 3 approval and planning commit.
- Expected changes: Feature-scoped GD capability checks, setup guidance, decoder validation contract; bound bytes/pixels before decoding and reject animated output.
- Verification approach: Valid JPEG/PNG, damaged payloads, APNG, resource bounds, missing decoder.
- Risks or open questions: Resolve decoder availability/strictness before dependents; no header-only fallback.
- Canonical components/API contracts touched: Image upload validator; dependency checks/runbook.

## Stage 2
- Goal: Enforce new-upload policy without breaking old images.
- Dependencies: Stage 1.
- Expected changes: Validate actual contents, 5 MiB/2048-edge limits and signed metadata before persistence; retain legacy repair only for an exact existing signed post.
- Verification approach: Direct-upload rejection, no stored rejected bytes/post, legacy read/restore/repair.
- Risks or open questions: Legacy exemption must not admit new oversized posts.
- Canonical components/API contracts touched: ImageStore, ImageReference, LocalWriteService, finalization API, ImageContentTest.

## Stage 3
- Goal: Prepare one display-sized client image.
- Dependencies: Stage 1 feasibility; Stage 2 complete.
- Expected changes: Shared bounded decoding/encoding, orientation/transparency, 2048 edge, no upscaling, disclosed still preview, resource cleanup.
- Verification approach: >5 MiB and >4096-edge inputs, JPEG/PNG/WebP/GIF, rotated JPEG, transparent PNG, unsupported/corrupt inputs; mobile viewport.
- Risks or open questions: Establish tested source byte/pixel bounds and browser matrix; reject excess with guidance.
- Canonical components/API contracts touched: Shared browser image-preparation module and fixtures.

## Stage 4
- Goal: Publish exactly the prepared preview and recover reliably.
- Dependencies: Stages 2–3.
- Expected changes: Bind hashing/signing/upload to prepared bytes; invalidate stale attempts after replacement; preserve retry state and Outbox exclusion.
- Verification approach: Lost response, repeated submit, replacement during preparation, removal, reload/reselection, stored-byte equality.
- Risks or open questions: Discard stale asynchronous results; never submit the original.
- Canonical components/API contracts touched: browser_signing, outbox_compose, draft clearing; existing prepare/finalize APIs.

## Stage 5
- Goal: Display image controls only for eligible identities.
- Dependencies: Stage 4.
- Expected changes: Hidden initial state; approval lookup tied to current signing identity; refresh on identity/online/focus changes and before upload; preserve text composition.
- Verification approach: Approved/unapproved/unknown identities, switching/revocation, feature-off, offline/insecure, public/private access.
- Risks or open questions: Ignore stale responses; validate public profile lookup/private access without bypassing membership gates.
- Canonical components/API contracts touched: Image fields, lazy signing, identity events, get_profile approval response, server authorization.

## Stage 6
- Goal: Complete the shared accessible uploader.
- Dependencies: Stage 5.
- Expected changes: File/drop/scoped-paste input, preview, description, replace/remove/status, 44-pixel targets and keyboard focus across existing composers.
- Verification approach: All input methods, ordinary text paste, keyboard-only use, touch layouts, multiple composers.
- Risks or open questions: Prevent cross-form attachment and stale preview state.
- Canonical components/API contracts touched: image_compose_fields, compose CSS, shared client controller; board/thread/dedicated/QDB/paned forms.

## Stage 7
- Goal: Verify the complete user journey and regressions.
- Dependencies: Stage 6; component tests passing.
- Expected changes: Extend existing image browser/transport/API coverage and fix integration failures within scope.
- Verification approach: ImageContentTest, image_compose_transport.cjs, image_content_browser.mjs, image_content_apache_test.py; desktop Chromium and mobile WebKit/touch coverage where available.
- Risks or open questions: Missing engines/device evidence cannot count as passing; resolve release-critical gaps before completion.
- Canonical components/API contracts touched: Existing test harnesses, image delivery/rendering and shared compose entry points.

## Stage 8
- Goal: Validate recovery/operations and close the cycle.
- Dependencies: Stage 7.
- Expected changes: Operator dependency/rollback guidance, accurate `todo.txt` completion marks, final implementation summary.
- Verification approach: Backup/restore new and legacy images, repair, feature-off/on retention, serving-runtime decoder readiness, relevant regression suite.
- Risks or open questions: Preserve original signed objects and user edits; leave LLM/server-conversion work open. New collapsed-selector todo remains outside approved scope.
- Canonical components/API contracts touched: Image backup/restore, runbook, todo.txt, Step 4 summary.
