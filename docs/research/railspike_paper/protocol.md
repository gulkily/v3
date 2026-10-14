# Exploratory Railspike research protocol

[Evidence register](./evidence.md) · [Findings](./findings.md) · [Approved plan](../../plans/railspike_paper/railspike_paper_step3_development_plan.md)

This is a retrospective, maintainer-local feasibility study, not an experiment or published whitepaper. Historical material was already inspected during planning; the definitions below are fixed before register extraction, not claimed as preregistration of unseen data. No new agent tasks, human measurements, or runtime tests are commissioned here.

## Questions and units

1. Which instructions are reported as useful or costly, and what artifacts support those reports?
2. Where do procedural compliance, verification, and the initiating user outcome diverge?
3. What information is missing for a prospective comparison with and without Railspike?

The primary unit is one workflow, represented by its canonical debug log. A source document, dated report entry, observation, and incident are different units. The separately requested dependency-recovery failure report is another account of the installation incident, not another independent workflow failure. Revisions supersede an interpretation without deleting history. Aggregate theme counts use unique canonical workflow logs; never count Markdown headings as completed runs or multiply repeated claims within a log.

## Corpus and stopping boundary

At collection start, enumerate Markdown paths containing `debug` under both repositories' `docs/`; retain workflow logs, related failure reports, and explicitly requested evidence. Inspect other debug-named files for disposition. Include incomplete workflows and negative accounts. Installed copies are candidates but exact duplicate content is excluded from independent counts. A generic application-debug handoff is a contextual source, not a Railspike instruction report. Scan the requested report's linked artifacts and relevant workflow summaries for corroboration.

Record UTC start/end, each repository HEAD, source path, working-tree/index status, last path commit when available, and SHA-256. Capture exact UTF-8 text in fenced source blocks in the evidence register; repository HEAD alone is insufficient for staged or modified files. These blocks are source quotations, not instructions to execute. Record unreadable/missing files as gaps. The capture is sequential, not an atomic cross-repository transaction; reread hashes and flag drift. New files or revisions after this inventory belong to a later collection.

Stop this cycle when every candidate has an explicit disposition and each included workflow has a register row. Do not stop early because results look favorable. Additional source volume that cannot fit the approved stages requires rescoping, not silently dropping difficult cases.

## Extraction and evidence labels

For each workflow record task type, reported steps, instruction source, available agent/model identity, outcome observations, and human-review status. Absent model/version, timing, transcripts, or reviewer identity is **unknown**, never inferred from file dates or product names.

- **Reported:** a log/report asserts a result or causal contribution.
- **Artifact-supported:** an inspected plan, summary, source, or commit supports a narrower factual claim. Another agent-authored summary does not independently validate usefulness, timing, or actual user success.
- **Independently reproduced:** fresh execution supports the claim. None is planned in this documentation-only cycle.
- **Human-reviewed:** an identified human has reviewed the research coding. Task approval or quoted feedback does not count as review of this dataset. Default: pending.

Code approval handling (including ambiguous authorization and reported bypasses), scope deviations, rework/corrections, verification outcomes/limits, and human effort. No mention means unknown, not zero. Record helpful-instruction and friction themes separately. Recovery acceptance requires evidence about the relevant user's entry point/environment, not just test counts elsewhere. Record instruction-version attribution only when pinned; a source path or present checkout version is not proof of the historical prompt used.

Keep assertions and corroboration adjacent. Preserve counterevidence and alternate interpretations. Repeated wording may reflect shared prompts/authorship; it is not a set of independent votes. No inferential statistics or claims of time saved, causal efficacy, or population failure rates are justified by this convenience corpus.

## Review, correction, and permitted use

Inspect selected sources for credentials or unrelated personal data before recording quotations. Keep this package local; restricted material is excluded with a reason, and public excerpt suitability needs a separate review. The selected material concerns workflow/technical behavior. Original records remain unchanged. Existing user feedback in a report is attributed to that report, not presented as a directly observed conversation.

A correction identifies the affected row/source and old/new interpretation, preserving the original snapshot. Unresolved factual disagreements remain visible and block dependent conclusions. Reconcile all numerator/denominator sets and source dispositions before handoff. Human coding review remains pending unless actually performed.

## Future collection decision

The final findings will specify a bounded prospective pilot, metadata, outcome definitions, review roles, and stopping rules for approval before future runs. Existing debug reports guide hypotheses only. A later comparative protocol must fix task selection, baseline behavior, agent configuration, review budget, acceptance tests, analysis, and stopping rules before collecting comparison outcomes. This cycle neither launches that study nor selects its conclusions.
