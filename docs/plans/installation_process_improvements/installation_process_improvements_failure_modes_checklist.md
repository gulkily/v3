# Installation process failure modes checklist

Review of [installation_log.txt](../../../installation_log.txt), compared with the original installation/welcome rules; links now point to the revised [installation checks](../../../README.md#installation-checks) and [welcome instructions](../../../WELCOME.md). Line numbers refer to the supplied log. Checked items have a documentation/scenario-review disposition in the evidence below; they do not claim that every agent has executed the procedure successfully.

## Observed failures and friction

- [x] **Unrelated work blocks adoption.** Lines 14–48 show installation stopping because of existing untracked plans, samples, and a nested repository. Stopping followed the current rule, but the blanket clean-tree prerequisite introduced work unrelated to installation. Acceptance: default to copying reviewed files into a dedicated project directory without requiring a clean Git tree, staging changes, or touching unrelated work. Use submodules only on explicit request; verify destination conflicts separately.

- [x] **A failed check is treated as passing after partial cleanup.** The user authorized “Stash” at line 54; lines 64–65 acknowledge that the nested repository remained untracked and the clean check was wrong, yet installation proceeded. Permission to stash did not by itself select the separately offered “install anyway” option. Acceptance: reevaluate every applicable prerequisite after remediation, distinguish command success from a satisfied condition, and stop or use an explicitly supported alternative when it still fails. Include a nested-repository case.

- [x] **Stashing leaves the user's working context unavailable.** Lines 63 and 83 leave files, including existing plans, in a stash and make restoration the user's next job. No restoration is recorded. Acceptance: the default copy installation requires no stash or other relocation of unrelated work. If a user separately requests stashing, track the exact stash, skipped paths, and restoration ownership; preserve recovery data on conflicts.

- [x] **Welcome proceeds despite an unresolved installation prerequisite.** Lines 58–97 report installation and introduce the workflow despite the admitted failed clean check; both README and WELCOME instructions at the time prohibited that transition. Acceptance: distinguish files added, installation verified, commit pending, and welcome completed. Enter welcome only after the chosen installation policy's conditions pass; staged-but-uncommitted installation alone need not block welcome.

- [x] **Optional guidance and the first-task choice are easily left unresolved.** Lines 85–97 combine an AGENTS.md offer with a request for the next task and whether to use Railspike. The reply at line 101 supplies only a task. Acceptance: announce Railspike as the default for the first suitable task during welcome, with an opt-out. Keep persistent project guidance a separate choice; an unanswered guidance offer must neither block the task nor authorize editing project instructions.

- [x] **The first substantial task receives no Railspike suggestion.** The deployment request at line 101 leads to several categories of work at lines 105–157, then a requested manifest edit at lines 161–196, without a visible workflow offer. This is an onboarding gap, not proof that an approved workflow was bypassed: no opt-in or accepted reminder entry is recorded, and small direct changes are allowed. Acceptance under the revised direction: treat the first suitable task supplied during welcome as a request to begin Railspike planning unless the user opts out; retain step approvals and the small-task exception. This changes the current opt-in behavior rather than retroactively making the logged edit a violation.

## Safeguards needing stronger evidence

- [x] **Inspection and installation can target different revisions.** Line 58 reports the same inspected and installed revision, so mismatch is not observed here. The original README separately described inspection and an unpinned submodule-add command. Acceptance: copy the same snapshot that was reviewed, record its source and revision, and verify destination contents rather than downloading again after inspection. Apply equivalent revision verification to an explicitly requested submodule. Retain the log's inspection limitations (line 28).

- [x] **The result report is difficult to audit or recover from.** Lines 56 and 62–65 summarize commands and intended changes without recording the underlying status/diff evidence; the agent's admitted check error makes this material. Acceptance: provide a concise before/after account of installation paths, preexisting staged and unstaged work, inspected/installed revision, remaining blockers, and recovery actions. Verify existing installations, path conflicts, and partial failures without removing unrelated work. These cases extend coverage; they are not all observed failures in this log.

## Additional coverage for the revised copy default

These are prospective failure modes introduced or exposed by the proposed approach, not additional observations from the log.

- [x] **Unpacking or copying escapes its intended directory.** Acceptance: use an isolated temporary location and prevent archive paths or symlinks from writing outside it or redirecting the destination; inspect before executing or adopting downloaded instructions.

- [x] **A repeated install overwrites existing material or leaves a partial installation.** Acceptance: recognize an existing copy or submodule, preserve local edits, and avoid merging blindly into an occupied destination. Verify completeness before welcome and restrict failure cleanup to material created by this attempt. Converting existing submodules is a separate decision.

- [x] **The copied distribution is incomplete or carries unwanted repository state.** Acceptance: preserve required workflow files, relative links, and license notices; exclude Git metadata. Define whether to copy the full source snapshot or a maintained subset, and keep host planning artifacts distinct from bundled upstream examples.

- [x] **A copied installation loses its update path.** Acceptance: record source and revision, explain manual review-and-replace updates, and detect local edits before replacement. Automatic updating is deferred; submodule update instructions apply only to submodule installations.

- [x] **The onboarding default disappears in later sessions or bypasses approvals.** Acceptance: distinguish the current welcome session's default from persistent project guidance. Announce default workflow use, honor opt-out and small tasks, and stop at normal planning approval gates. Define consent and conflict handling for durable guidance without treating installation as blanket implementation approval.

## Scope and interpretation

The log supports process findings, not an independent security verdict or a reproduction of the host repository's state. It records inspection before installation, a revision claim, staged changes disclosed without committing, and an AGENTS.md proposal without an unauthorized edit; preserve those behaviors. Chrome Store advice and application correctness are outside this installation review. The record ends during follow-up work, so it does not establish final deployment or recovery outcomes.

Compare approaches in [Step 1](./installation_process_improvements_step1_solution_assessment.md).

## Verification evidence

Evidence below is a review of instruction paths, not an executed install or a guarantee of agent behavior. Item numbers follow checklist order. The supplied log remains a local, untracked source; its line references and excerpts above preserve the relevant observations, but its link requires that local file.

### Stage 1 installation review

- Items 1–3: README's default sequence accepts unrelated staged/unstaged files and nested repositories without stashing; its separate submodule path retains the clean-tree gate and requires rechecking after remediation. Stash restoration is unnecessary for the default path; separately user-requested stashing remains outside the installer.
- Item 4: both inspection concerns and failed acquisition/copy/verification block welcome; pending commit status alone does not. Rechecking prevents proceeding after an acknowledged failure.
- Items 7–8: source revision is fixed before review, the same snapshot is copied, a durable source record is written, destination paths/contents and the existing index are compared, and results are reported. An explicitly requested submodule must match the inspected revision before adoption; if its checkout is corrected, only its pointer is re-staged and the staged diff is rechecked. Update coverage follows in Stage 2.
- Items 9–11: archive checks precede extraction; redirected and occupied paths, including empty directories and existing submodules, cause refusal. Partial-state recovery removes only known, unchanged files from this attempt. The complete snapshot retains license/layout/dotfiles, excludes Git metadata, and separates bundled examples from host plans. Source-record name collisions stop before copying.
- Integrity: changed-document paths and plan navigation resolve locally. The repository-wide checker reports existing failures in unrelated draft plans and research evidence; those files are outside scope. The local source log is not added to Git.

### Stage 2 update review

- Items 7–8 and 12: copy updates require the recorded source and exact baseline, identify additions/edits/deletions, and review the new snapshot before replacement. An unchanged current revision is a no-op; local differences require a preservation decision. Missing provenance or an unavailable baseline blocks replacement rather than guessing. The report covers old/new revisions, retained deviations, changes, and recovery location.
- Item 10: replacement keeps a prior copy and source record, rechecks for concurrent changes, verifies the result, and retains both versions when restoration could overwrite subsequent work. Existing submodules use their separate procedure and are never converted implicitly.
- Verification: `bash scripts/check-railspike-git-workflow.sh` passed. The marked submodule update block is byte-for-byte unchanged from `master`; the fixture tests that existing path, not the new prose-only copy procedure. Targeted links, the copy-update anchor, scope, and whitespace checks passed.

### Stage 3 welcome review

- Items 5–6 and 13: README and WELCOME announce the first suitable task's planning default. The installed-path example remains available for later sessions; persistent guidance is separate and requires consent. The log's deployment request now maps to a first planning artifact, without a second opt-in or automatic implementation.

| Scenario | Instruction outcome reviewed |
| --- | --- |
| Substantial task, no answer to guidance offer | Begin Step 1 or Step 2, save the artifact, stop for its approval; leave AGENTS.md unchanged. |
| Task included in installation request | Complete installation verification, announce default, then use that task without asking for repetition. |
| User opts out of Railspike | Honor the choice and do not repeat the suggestion for that task. |
| Small standalone correction | Proceed directly; a correction inside an active workflow still obeys its scope and approvals. |
| Guidance accepted | Preserve unrelated host instructions, update an existing entry rather than duplicate it, and clarify conflicts; acceptance alone starts no task. |
| Guidance declined or unanswered | Make no project-guidance edit and do not block the task; distinguish declining persistence from opting out of workflow use. |
| Later session without persistent guidance | Use the explicit invocation example; do not promise persistence from installation alone. |
| Existing workflow | Establish its current approved step; do not infer approvals from file presence or restart blindly. |

### Final checklist disposition

All 13 items are addressed at the approved documentation boundary: items 1–4 and 9–11 by Stage 1, items 7–8 by Stages 1–2, item 12 by Stage 2, and items 5–6 and 13 by Stage 3. For item 3, default-install stash restoration is inapplicable because installation never stashes; independently requested stash operations remain the host workflow's responsibility. Evidence supports instruction coverage and the retained submodule fixture, not an end-to-end run across coding agents. No runtime/application change was made.
