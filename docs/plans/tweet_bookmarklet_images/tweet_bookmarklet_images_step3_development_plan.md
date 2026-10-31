> **Feature plan:** [Step 1](./tweet_bookmarklet_images_step1_solution_assessment.md) · [Step 2](./tweet_bookmarklet_images_step2_feature_description.md) · [Step 3](./tweet_bookmarklet_images_step3_development_plan.md) · [Step 4](./tweet_bookmarklet_images_step4_implementation_summary.md)

Debug mode: enabled
Study: railspike-effort / v3/tweet_bookmarklet_images / R1 / P2
Extended estimates: enabled
Scope version: v2/B; Step 2 approved; implementation awaiting Approved Step 3

**Cycle estimate: 0.55**

## Completion Contract

Installed tweet bookmarklet, in either launch mode, opens the ordinary composer with text, attribution and the tweet's first photo; normal signed posting saves and displays that photo. Additional photo links survive. Removal/replacement, failed import, ineligible identity and posting retry preserve draft/attachment intent and provide recovery. Exclude galleries, picker, video/animation, quote media, new dependencies, schema or signing changes. Check actual serving/assets, shared composers and phone/desktop themes; live X/browser evidence is required for a live-capture claim. Local fixture success alone is insufficient. No merge, push or deployment authorized.

## Key Risks

- **High risk:** cross-origin access may block capture. Stage 1 proves client-side image access and handoff before integration; if unavailable, stop dependent work and revise the approach for approval. No server-side fetch/proxy fallback in this plan.
- **High risk:** pending or failed import could silently become text-only publication. Stage 1 checks submission/draft contracts; Stage 3 retains attachment intent until explicit removal and tests failure/retry. Existing Outbox cannot save image attachments; preserve its refusal/recovery.
- Wrong-photo capture or late UI initialization could mislead users. Stage 2 validates selected-tweet ownership/order; Stage 3 establishes pending import before interaction/paint, then updates only for real download/preparation state changes. Failures retain source links.

## Stage 1 — Prove transfer feasibility

**Estimate: 0.10**

- Goal/dependencies: resolve browser transfer before integration; approved plan, feature branch and planning-only commit required.
- Expected changes: record client-only URL handoff and disposable probe results; no image bytes in navigation or server proxy. Validate HTTPS photo-host/path, omit credentials, reject redirects, bound download size/time.
- Verification: real photo-host browser access, cross-origin fixture and both composer launch modes; inspect pending-state, draft and Outbox contracts. Failure blocks Stage 2 and triggers replanning.
- Risks: live CORS/CSP and initialization order unverified; record actual evidence before dependents.
- Canonical contracts: `tools_bookmarklets.js`, compose prefill controller, `browser_signing.js`, `image_upload.js`, `outbox_compose.js`.
- Estimate details: transfer/recovery proof; external policy is uncertain.

## Stage 2 — Capture first-photo intent

**Estimate: 0.10**

- Goal/dependencies: carry selected-tweet photo references; Stage 1 passed and committed.
- Expected changes: extend existing extraction/navigation, retain additional photo links and both launch/copy modes.
- Verification: no/one/multiple photos, quote/nested tweets, avatars, emoji, video thumbnails, missing tweet, ownership/order/deduplication and copied one-line execution.
- Risks: changing markup; restrict extraction to the chosen tweet's own media and distinguish live from fixture evidence.
- Canonical contracts: `public/assets/tools_bookmarklets.js`, existing bookmarklet copy tests/fixtures; no parallel capture implementation.
- Estimate details: media ownership/compatibility; live markup variation uncertain.

## Stage 3 — Review and publish through existing uploader

**Estimate: 0.20**

- Goal/dependencies: import one photo through normal posting; Stage 2 verified and committed.
- Expected changes: small client adapter, needed because text prefill has no remote-photo input; download into `ForumImageUpload.select(form, file)`. Reuse preparation, eligibility, preview/signing; establish pending intent before interaction/paint. Ignore stale downloads and prevent implicit text-only submission until explicit removal.
- Verification: publish/readback; removal/replacement, blocked/invalid/oversized/slow download, preparation failure, disabled images, identity changes, retry, Outbox refusal and existing saved draft. Retain source links and manual/retry recovery.
- Risks: races/draft overwrite; preserve existing draft precedence and fence stale attempts. Reload recovery uses source links; no new attachment persistence promise.
- Canonical contracts: shared composer/image partials, `image_prepare.js`, `image_upload.js`, `browser_signing.js`, renderer asset wiring. Preserve `LocalWriteService.php` and `Image-v1`; extend shared state only for pending intent if needed.
- Estimate details: state/recovery integration; first-paint and draft ordering uncertain.

## Stage 4 — Acceptance and documentation

**Estimate: 0.12**

- Goal/dependencies: shared acceptance/closeout; Stage 3 verified and committed.
- Expected changes: owns final shared checks, reference/concept updates and study closeout. Verify asset serving/invalidation; existing installed bookmarklets require replacement. No navigation addition.
- Verification: affected image/publication/render PHP checks; bookmarklet copy including qutebrowser where available; preparation/transport/browser checks. Check thread/reply/compact consumers, no-JS text posting, phone/desktop themes, initial/pending/success/failure/recovery, overflow and first paint; replay live X capture or mark external acceptance unverified.
- Risks: fixtures cannot establish live success; block completion on material flow failures and state deployment/full-suite/client limitations honestly.
- Canonical contracts: existing image/bookmarklet harnesses; touched reference/spec docs; proposed tweet-clipping/image-attachment concept entries, validated against delivered code; cycle artifacts and study ledger.
- Estimate details: shared consumer/theme/recovery coverage; available live/browser environments uncertain.

## Checkpoint and execution discipline

2026-10-31T07:41:46Z: Step 2 explicitly approved; no added scope or pending enhancement. Reaffirm whole-cycle 0.55 (v2/B, range 0.40–0.80), independently of original stage work S1 0.10 / S2 0.10 / S3 0.20 / S4 0.12. Preserve Step 1 v1/B 0.65, original baseline a1164752242c3acd0105c91d635100720ebe84ef, missing pristine intake, R1/P2 and single exposed Codex rater. Frozen report/review/feedback hashes reverified; planning checkout now 8b72318afdb2ecb90a603774ee317ec88372021e, not a new baseline. No runtime probes/tests performed at this checkpoint. Stage 1 is a blocking feasibility gate, not a claim of resolution. No measured labor or accepted outcome.

After Approved Step 3: create feature branch, organize this cycle under `docs/plans/tweet_bookmarklet_images/`, update plan navigation/index and commit approved plans before creating Step 4 summary or implementation. Each stage requires checks, updated summary and its own commit before the next. Preserve unrelated changes, including `todo.txt`. Dependency delta planned: zero.

*— Codex · 2026-10-31 UTC*
