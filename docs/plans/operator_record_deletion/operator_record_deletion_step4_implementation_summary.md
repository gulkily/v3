> **Feature plan:** [Step 1](./operator_record_deletion_step1_solution_assessment.md) · [Step 2](./operator_record_deletion_step2_feature_description.md) · [Step 3](./operator_record_deletion_step3_development_plan.md) · [Step 4](./operator_record_deletion_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Durable authority evidence

- Changes: optional actor/classification headers preserve the legacy author format. Operator acceptance requires identical record bytes in a local addition commit with the retraction subject and a matching, regular-file operator seed in its sole parent revision. Git history is the local trust boundary; archive claims cannot manufacture acceptance.
- Verification: signed fixture test passed (1/1), covering uncommitted rejection, accepted authority, live seed removal, tampered history claims, and legacy parsing.
- Notes: missing/pruned history fails closed for operator actions. Preserve the canonical repository history during backup/restore; arbitrary server-side Git writes remain privileged operations. Signature enforcement is wired into readers in Stage 3.

## Stage 2 - Signed operator submission

- Changes: prepare/finalize distinguish target author from actor, accept the actor_identity_id alias, verify the actor key, and require approved operator status backed by the committed live seed. Finalize rechecks authority under the write lock. Previously accepted operator actions remain retryable after privilege removal.
- Verification: three focused API/history tests passed; coverage includes author compatibility, wrong signing key, operator submission, privilege removal between prepare/finalize, and post-revocation retry.
- Notes: the existing author_identity_id request parameter remains an actor alias for backward compatibility; canonical Author-Identity-ID continues to identify the target author.

## Stage 3 - Shared validation and suppression

- Changes: canonical loading verifies supplied signatures and requires a verified actor signature plus locally accepted history for operator records. Shared suppression accepts only validated operator actions; rebuilds and raw routes inherit the same rule. Legacy unsigned local author records remain readable for compatibility; import enforcement follows in Stage 4.
- Verification: six targeted tests passed, covering legacy rebuilds, operator suppression, raw source denial, persistence after seed removal/rebuild, forged signature rejection, and author suppression across served surfaces.
- Notes: validation caches key on verified bytes/key and repository HEAD, so mutations or new revisions invalidate the decision. No signature or imported archive statement grants operator authority by itself.

## Stage 4 - Import trust boundary

- Changes: imported retractions require verified signatures; operator evidence is validated against destination history, never archive history. Path identity preserves duplicate recognition for validated operator records. Actor identity dependencies accompany operator evidence.
- Verification: 11 targeted import/API tests passed, including forged/unsigned import rejection, accepted operator duplicate after revocation, signed-but-unaccepted operator rejection, and author reimport suppression.
- Notes: importing a remote instance's operator decision cannot grant local authority; remote moderation propagation remains excluded. Locally accepted actions survive import of the original content.

## Stage 5 - Accurate attribution and cache migration

- Changes: derived author/operator classification is carried by post/thread rows and all existing retraction queries. Shared notices, QDB/Forte placeholders, and current/historical source denial identify the actual action class. Listing titles remain unchanged. Schema version 18 triggers rebuild of prior caches.
- Verification: six targeted tests passed, including version-17 cache with both columns removed starting normally, rebuild command, schema fingerprint, operator notice/source attribution, and author suppression across served surfaces. Earlier placeholder/reply coverage also passed.
- Notes: actor identity and acceptance evidence remain canonical; derived classification only controls presentation. Deploy application code with the schema change; do not downgrade to code that ignores operator retractions.

## Stage 6 - Shared overflow placement

- Changes: shared native details control renders … with the accessible name More actions and a Retract post option. Author/operator visibility is computed from existing eligibility, with no new QDB/Forte entry points. Shared feedback sits outside the disclosure; Escape closes it and restores summary focus.
- Verification: four API/script tests passed, including operator root/post pages and ordinary author visibility. Real Chromium menu checks passed for keyboard activation, Escape/focus restoration, and mobile touch activation.
- Notes: Playwright does not resolve native summary with getByRole(button) in this browser, so the browser fixture selects the explicitly named summary. Native disclosure semantics remain intact. Corrected an EOF whitespace warning from Stage 5.

## Stage 7 - Browser signing and visible recovery

- Changes: browser submits actor_identity_id and signs the canonical target/actor record with its own key. Confirmation/progress/completion say retract. Submission closes the menu; partial status exposes a standalone Retry retraction outside it. Controls are disabled together during submission. Server retries now repeat artifact invalidation even when the read model is already refreshed.
- Verification: four focused PHP/DOM tests passed, including real artifact invalidation failure and recovery without another commit. Real Chromium author/operator signing and accurate attribution passed, including mobile operator use, cancellation, and simulated partial-response recovery with unchanged HEAD. Menu keyboard/touch checks also passed.
- Notes: browser partial response is injected after a real successful commit; separate server tests cause actual read-model/artifact failures. Together they verify UI and server recovery boundaries.

## Stage 8 - Integrated verification and handoff

- Changes: Commits/source evidence identifies the actual retraction signer and verifies its signature; retraction records have an explicit file role and signature-status header. Retry checks reject ineffective/corrupt existing records. Expanded operator regression coverage exercises protected-post denial, retained replies, audit inspection, privilege removal, and actual restoration via ContentImportRunner. Updated the reference, schema tables, and backup/deployment/rollback guidance.
- Verification: final focused run passed 16/16; changed PHP files pass syntax checks; git diff --check passes. Both real Chromium scripts pass (keyboard/touch menu and author/operator signing, cancellation, attribution, and partial-response retry). Explicit ./v3 rebuild with the parity fixture succeeded. Full suite: 1163 run, 1156 passed, 7 failed.
- Baseline comparison: detached dd10397f full suite had 1159 run, 1153 passed, 6 failed. The same six route/offline/QDB failures reproduce before this feature. The seventh, testTaskQueueProcessesQueuedAgentReplyOnce, also reproduces on dd10397f when given this workspace's local private configuration; its failure is configuration-dependent. No retraction test fails. Temporary baseline worktree removed after verification.
- Existing failures: LocalAppSmokeTest::testApplicationRendersCoreRoutes; OfflineSnapshotBootstrapTest::{testPublishesWhenNoSnapshotIsServed,testRetainsAnExistingServedSnapshot}; OfflineSnapshotPublishCommandTest::{testPublishBuildsOnlyIndependentOfflineSnapshot,testPublishReportsSelectedProfileWithoutChangingExplicitRootPrecedence}; QuoteCardDisplayNumberTest::testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews; WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce.
- Notes: legacy unsigned local author records retain compatibility; new imports require verified signatures. Operator evidence depends on retained, trusted local Git history and public keys. Privilege removal does not revoke prior accepted actions. Whole-repository downloads/history/backups retain original content. External deployment and cross-instance moderation are outside scope.

## Final verification

- All eight stages completed sequentially with their summary updates and separate commits, following the planning-only commit on feature/operator-record-retraction.
- Normal entry, signed author/operator outcome, visible partial recovery, role enforcement, migration from schema 17, audit evidence, rebuild and reimport persistence verified. Every existing action uses … → Retract post; QDB/Forte receive actor-aware notices without new action entry points.
- Plans organized with working navigation/index; debug mode remains disabled. Changes remain on the feature branch for review; no deployment or merge performed.

## Follow-up - Overflow control last in the action row

- Changes: moved the shared retraction control after agent response and Codex handoff buttons in both post and thread-root cards.
- Verification: two existing PHP tests passed; real Chromium confirmed the overflow control is the final action with agent responses available on thread and individual post pages. Real operator signing still passed; PHP syntax and diff checks passed.
- Notes: user-requested placement refinement following the eight completed stages.

## Follow-up - Balanced menu spacing and short confirmation

- Changes: dropdown action has equal horizontal padding, zero margin, a contained box width, and centered text. Confirmation is now “Retract this post?” (three words); updated the reference and existing confirmation assertion.
- Verification: three existing PHP/script tests and both browser scripts passed. A rendered mobile-page geometry check confirmed equal left/right panel spacing and padding; operator signing still passed. Diff checks passed.
