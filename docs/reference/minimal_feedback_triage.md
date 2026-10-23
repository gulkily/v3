# Minimal feedback triage process

Use this format to select an actionable feedback item with high user benefit and low total effort. Keep the triage short enough to review at a glance; link supporting investigation rather than reproducing it.

For migrated requests, start with the [production feedback register](../plans/production_feedback_register.md) and [management guide](production_feedback_management.md). Keep current workflow state, delivery evidence and next actions there; separate triage documents retain investigation/estimate provenance. A triage recommendation is not approval. All future Railspike cycles follow the project’s standing P2 study enrollment; ordinary one-shot triage does not itself create a cycle.

## Process

1. Read the feedback inventory and compare likely benefit, effort, uncertainty, and overlap with completed work. Briefly inspect relevant source to establish whether the reported behavior still has a plausible code path. Distinguish source evidence from runtime reproduction.
2. Create a separate triage file in `docs/plans/`. Link directly to every comment/post that supplies the selected report, plus its parent post and full thread for context. Preserve all source links when several reports are combined.
3. Record the four fields below. Include required verification and documentation in the score; a tiny code change can still need substantial checking.
4. Keep any detailed investigation or estimation evidence in a separate linked document. Preserve inherited feedback estimates when a source-informed estimate differs; do not manufacture earlier checkpoints or independent ratings. Follow the [Railspike workflow and estimation contract](railspike_workflow.md) where applicable.
5. Keep the issue open until its completion checks pass. If requested, reply under the original comment after verified completion, describing only the work actually resolved.

## Format

```markdown
# <Selected issue>

Report: [comment](...) · [parent post](...) · [full thread](...).

- **Railspike score:** <score> — total scoped effort, including verification; <confidence>.
- **Workflow:** <One-shot / Checklist / Railspike> — <brief reason>.
- **Why this next:** <user benefit relative to effort>.
- **Done when:** <observable outcome and essential preservation/recovery checks>.

[Source and estimation evidence](<optional supporting document>)
```

Railspike scores are relative judgments: **1.0** is the largest well-scoped, releasable slice comfortably approvable for one cycle, including verification and documentation. They are not hours, priority, or approval. Note uncertainty briefly and retain calibration/provenance details in linked evidence when available; formal effort-study participation follows the project’s standing P2 enrollment for Railspike cycles.

Choose **One-shot** for a small, straightforward change with clear acceptance checks; **Checklist** for a clear approach with several bounded checks or surfaces to track; **Railspike** when alternatives, uncertainty, dependencies, or work across sessions warrant staged planning. A checklist does not bypass repository requirements to use Railspike. Workflow recommendations do not approve implementation or later Railspike steps.

## Example

The [minimal identity-ready triage](../plans/browser_identity_ready_minimal_triage_2026-10-21.md) was created separately from its [detailed triage](../plans/browser_identity_ready_triage_2026-10-21.md). It records a 0.10 score, recommends one-shot work, explains the recurring-noise benefit, and defines completion while preserving useful identity feedback. Direct links identify the reporting comment, parent post, and full thread. Local implementation and verification are complete; deployment and the requested closeout reply remain pending.
