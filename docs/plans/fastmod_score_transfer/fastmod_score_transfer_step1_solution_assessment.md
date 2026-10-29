> **Feature plan:** [Step 1](./fastmod_score_transfer_step1_solution_assessment.md) · [Step 2](./fastmod_score_transfer_step2_feature_description.md) · Step 3 · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_score_transfer; [standing P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.70**
Status: Step 1 Option A approved; Step 2 proposed. Distinct follow-up to v3/fastmod_virtual_tags.

## Original Query

Can you estimate effort for adding just the fastmod scores to the export/import?

## Understood Intent

Preserve completed Fastmod probabilities when moving posts between instances, avoiding paid rescoring and restoring compatible score-derived tags. Transfer only post/content/rubric identifiers, named probabilities and original timestamps in a versioned Key: value plain-text supplement; exclude jobs, failures, prompts/responses, secrets, policy and legacy classifications/suppressions. Existing destination scores win; absent/malformed/incompatible source scores never become local authority or trigger provider calls. Matching content/rubric rules still govern tag use; unlike scores, suppression decisions do not travel.

Problem: Public content export/import currently excludes private scores, and its cache/recovery boundaries know only repository content.

## Option A — Include a scores supplement in normal content export/import

**Estimate: 0.70**

Extend normal content TAR/ZIP generation and import-instance's supported archive path with an optional versioned scores supplement, including dry-run counts and repeat/interruption-safe score merging after accepted content. Old archives remain supported; completed imports expose compatible tags without new model calls.

- Best fit for the requested normal workflow; no separate manual transfer step. Export only scores corresponding to included posts; preserve original retention timestamps and destination results.
- Main work is cache freshness independent of Git, bounded validation/extraction, and recovery spanning content and the private score store. Score-only updates must appear in a fresh export even without a content commit.
- Material policy assumption for later approval: selected score fields become available to whoever may download that archive; the prior private-only boundary changes. No raw private database export. An operator opt-in can bound this; exact enablement belongs in Step 2.

## Option B — Separate operator scores export/import commands

**Estimate: 0.40**

Export one plain-text scores file and import it manually after ordinary content import, reusing the same matching, validation, timestamp and destination-wins rules. A failed score import can be rerun independently without changing the content import.

- Smallest useful delivery; avoids archive-cache and content-import recovery changes and keeps transfer under operator control.
- Two extra manual operations; import-instance alone still does not preserve scores. No automatic download or archive integration.

## Recommendation and checkpoint

Recommend A for the requested export/import feature; B if the immediate workaround is sufficient. Each estimate includes inspection, planning, implementation, relevant round-trip/malformed/retry/no-provider tests and documentation; neither includes new rubrics, model calibration, suppression migration or deployment. No third-party components proposed. Scores can restore tags only when destination rubric/content/policy agree; do not relabel source revisions as current.

Evidence: [export/cache boundary](../../../src/ForumRewrite/Images/ImageBackup.php), [archive allowlist](../../../src/ForumRewrite/Import/RepositoryArchive.php), [import recovery](../../../src/ForumRewrite/Import/ContentImportRunner.php), [score store](../../../src/ForumRewrite/Scoring/SqliteFastScoreStore.php). Source review only; no tests or implementation changes for this estimate.

Checkpoint: Step 1 handoff, 2026-10-29T19:42:20Z; v1/A, baseline 53f0c89260c08e3fdf1d5535380d84d7cec92768; R1/P2. First projected total 0.70; B 0.40, alternative complete-cycle estimates, not additive or hours. Codex single exposed rater with predecessor knowledge; all three frozen R1 hashes verified. Pristine intake missing because export/import source inspection preceded rating; no reconstructed earlier score. Standing enrollment; debug inherited from present project marker. No measured labor, accepted outcome or implementation authority. Predecessor is locally complete/unmerged; this is separate planning with fresh approvals and no branch-reuse assumption. Await Approved Step 1 before Step 2.

*— Codex · 2026-10-29 UTC*

## Approval and current concept guidance

User approved Step 1 Option A. After the authorized main merge, Railspike v0.5.0-beta.1 applies to current planning. Concepts touched: Fastmod scoring (changed under A/B), content snapshot export and instance content import (changed under A; used unchanged under B), effective tags (used unchanged). Relevant entries are missing and proposed for authorized implementation under docs/concepts; no inventory files created or old plans backfilled. Original estimate/scope preserved.

*— Codex · 2026-10-29 UTC*
