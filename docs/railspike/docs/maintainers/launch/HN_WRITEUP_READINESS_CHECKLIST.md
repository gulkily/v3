# HN Writeup Readiness Checklist

_Maintainer working checklist. It records launch preparation; it is not part of the user-facing workflow._

Use this checklist to make Railspike easy to evaluate in a few minutes and to ensure the Hacker News post is backed by a concrete, honest demonstration.

## Product Proof

- [x] State the project in one clear sentence: Railspike helps you review, combine, and revise agent plans in Markdown before approving work on code, documentation, tests, or checklists.
- [ ] Select and write replacement examples from the [case-study candidates](../case-study-candidates.md); case studies are the last item in the [positioning checklist](../positioning-checklist.md).
- [ ] Include a complete feature cycle with its original request, reproducible prompts, planning artifacts, verification, and stage commits.
- [ ] Explain the outcome and support any comparison with recorded evidence.
- [x] Keep claims narrow; do not claim that Railspike eliminates hallucinations or replaces engineering judgment.

## First Run Experience

- [x] Add a compact quick-start section with the recommended installation method and the first prompt to send an AI agent.
- [x] Show a concrete request to combine options and revise the recommendation before approval.
- [x] Show the approval loop with a concrete `Approved Step N` example.
- [x] Explain where plans live, what gets committed, and how Railspike is updated in a consuming repository.
- [ ] Verify the renamed quick start and file-mention behavior with the supported agents. Historical subtree verification does not validate the current submodule installation or autocomplete.

## Workflow Clarity and Validation

- [x] Add a Mermaid overview diagram showing the Step 1–4 flow, approval gates, planning artifacts, branches, and commits.
- [x] Add a user-path diagram for normal work and for returning to planning when scope changes.
- [ ] Add replacement end-to-end prompt fixtures with expected artifacts or decisions alongside the new examples.
- [x] Cover common failure modes: skipped approval, scope expansion during Step 4, documentation-only work, and missing verification or stage commit.
- [x] Make risks prominent in the workflow and show how they block dependent work until resolved.

## Repository Readiness

- [x] Add a license appropriate for intended reuse. (MIT)
- [x] Decide and document the release/versioning approach. See [Releases and Versioning](../RELEASES.md).
- [ ] Create a tagged first release once the new launch materials are committed, pushed, and reviewed on the public repository.
- [ ] Verify Markdown links, paths, commands, and Mermaid rendering from a clean checkout after the launch-material commit is publicly available. Run local link/path and submodule-workflow checks; GitHub rendering still needs published review.
- [x] Add contribution guidance if outside contributions are wanted.
- [x] Clearly label the remaining maintainer-only checklist material so it does not look like user-facing workflow guidance.

## HN Post

- [x] Use a plain, descriptive title that states what Railspike does. See [HN post draft](./HN_POST_DRAFT.md).
- [x] Open with the problem: the first plan needs discussion, revision, and a persistent record before work begins.
- [x] Explain the mechanism briefly: small instruction files, explicit approvals, planning artifacts, and stage-scoped commits.
- [x] Link to the quick start.
- [ ] Link to a replacement worked example once it is ready.
- [x] Name the intended audience and the tradeoff: extra process is useful for work with meaningful scope and decisions, but can be excessive for trivial changes.
- [x] Include a short "what this is not" note: Railspike is not an autonomous-agent framework, a replacement for judgment, or a universal process.
- [x] Invite specific feedback, such as whether the gates are useful, where the process is too rigid, and which agent integrations would matter.

## Launch Gate

- [ ] Verify that a newcomer can understand the thesis, run the first workflow, and inspect a replacement example in under five minutes.
- [x] The post makes no claim that the repository cannot demonstrate.
- [ ] The repository has a stable, shareable link and all linked material is public and usable.
