> **Feature plan:** [Step 1](./backup_feature_flags_step1_solution_assessment.md) · [Step 2](./backup_feature_flags_step2_feature_description.md) · [Step 3](./backup_feature_flags_step3_development_plan.md) · [Step 4](./backup_feature_flags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/backup_feature_flags; [host ledger](../effort_estimation_trial.md#cycle-v3backup_feature_flags)
Extended estimates: enabled

**Cycle estimate: 0.40**

## Original Query

As operator, I want to have a feature flag to disable backup if I so choose. Please write Step 1.

We should have two separate flags, one for image and one for text.

## Understood Intent

Provide independent operator controls for site text/repository and image backup downloads through the existing feature-flag system. Both default to enabled; disabling preserves stored content and re-enabling restores access. Browser key backups, filesystem backups, restore/import and ordinary content access are outside scope.

## Problem

Backup downloads currently lack independent operator controls, and hiding links alone would leave direct URLs usable.

## Option A — Control each backup category completely (recommended)

**Estimate: 0.40**

Two flags govern text backups (repository archives and the downloadable SQLite index) and image backups respectively, including direct requests and cached archive delivery.

- Pros: Consistent category boundaries; existing operator controls and configuration precedence; all four combinations supported independently of image authoring.
- Cons: Disabling text backups also removes the SQLite Viewer's database source; its unavailable state must be handled coherently.
- Driver/uncertainty: Shared navigation, cached pages and Viewer behavior need actual-flow verification; existing flag and archive machinery limits scope.

## Option B — Control portable archives only

**Estimate: 0.30**

Two flags govern repository archives and image archives; the SQLite index remains downloadable as a separate inspection facility.

- Pros: Smaller change; SQLite Viewer remains usable with text archives disabled.
- Cons: Bulk text remains downloadable through SQLite, so “text backup disabled” has a narrower meaning that operators may not expect.
- Driver/uncertainty: Reuses the same flag and download infrastructure with fewer affected surfaces; adequacy depends on accepting the SQLite exception.

## Recommendation and verification boundary

Recommendation: Option A, as one complete vertical slice covering operator selection, visible availability, enforced download policy and re-enable recovery. Keep Backup navigation while either category is available; remove it when both are disabled and handle bookmarked aliases consistently. Retain relevant matching-revision warnings without implying either archive alone is a complete restore. Previously downloaded copies and transfers already underway cannot be recalled.

Evidence required: All four flag combinations, configuration precedence, direct URLs, warm archive/page caches, retained images with image authoring disabled, re-enable recovery, SQLite Viewer, and phone/desktop Tools navigation across themes. Track copy cleanup and verification in the UI copy audit checklist during implementation. No new or renamed destination, dependency, canonical-format migration or read-model schema change is proposed.

Source basis: [Backup controller](../../../src/ForumRewrite/Http/InstancePageController.php), [flag registry](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php), [SQLite Viewer](../../../public/assets/sqlite_viewer.js). Repository and image archives share export machinery, so enforcement must distinguish the requested category.

## Enhancement requests

Request: Separate image and text flags. Disposition: Incorporated into both options; no master flag. Approval: Assessment requested; Step 1 approval pending.

## Study checkpoints and approval

Scope: v1; baseline 2acea7d8ca05793cef7face657a793f130a76761; R1/P2; no parent/replacement cycle. Existing archive work is reused, not reopened. Documentation scope includes this assessment, debug log and ledger bookkeeping.
Rater: Codex, single exposed rater; all three frozen R1 hashes verified. Initial source/search and reference inspection preceded recording; pristine pre-inspection intake is missing, not reconstructed.
Intake: 2026-10-26T07:52:12Z; 0.40 for Option A's complete outcome, recovery, verification and documentation from the baseline. Comparable to modest cross-surface integration, well below new backup/restore infrastructure.
Step 1 handoff: 2026-10-26T07:52:12Z; 0.40 reaffirmed. A/v1 0.40 and B/v1 0.30 are alternative whole-cycle judgments under the same baseline/R1/exposure, not additive, hours or measured labor.
Status: Await Approved Step 1; no implementation or commit, independent rating, accepted outcome or accepted-count increment.

*— Codex · 2026-10-26 UTC*

Approval update: 2026-10-27T00:30:46Z; user approved Step 1. Recommended Option A proceeds to [Step 2](./backup_feature_flags_step2_feature_description.md); earlier pending status is historical.

*— Codex · 2026-10-27 UTC*
