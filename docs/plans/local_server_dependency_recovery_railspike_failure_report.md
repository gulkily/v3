# Railspike failure report for local server dependency recovery

## Summary for the Railspike improver

Railspike produced an organized, tested Ubuntu 24.04 installer, but the delivery left the user's known Ubuntu 22.04 environment without actionable recovery instructions. The agent reported Step 4 complete; the user then ran `./v3 start`, encountered missing PHP and later missing extensions, and still had to ask what to do.

The principal failure was accepting scoped implementation evidence as sufficient evidence of the user's recovery outcome. The approved scope legitimately limited automatic installation. It also required actionable manual guidance outside that scope, but that requirement was not made concrete enough to prevent generic advice from passing acceptance. The agent owns that implementation and review failure; user approval of a narrow installer scope did not waive the manual-recovery requirement.

This report requests improvements to Railspike's planning and acceptance instructions. It does not request automatic installation on the user's machine, silently broaden platform support, or discard the useful tests already completed. Product remediation is tracked in the [recovery checklist](local_server_dependency_recovery_checklist.md).

## Evidence and sequence

1. [Step 2, Core Requirements](local_server_dependency_install/local_server_dependency_install_step2_feature_description.md) limited automatic installation to Ubuntu 24.04/APT distribution PHP 8.3 and explicitly said: “Unsupported platforms receive actionable manual guidance.” Ordinary checks and startup were required to remain read-only and noninteractive.
2. [Step 3, Key Risks](local_server_dependency_install/local_server_dependency_install_step3_development_plan.md) already recorded: “Workspace inspection found Ubuntu 22.04/PHP 8.1, not the target.” The response was to require disposable Ubuntu 24.04 verification. That was appropriate for the installer, but no equally concrete acceptance case was specified for recovery on the known user environment.
3. [Step 4 evidence](local_server_dependency_install/local_server_dependency_install_test_evidence.json) records 39 disposable-environment cases and 31 runtime checks, including actual installation and HTTP read/write/shutdown. Those results establish the tested behavior; they do not establish the usefulness of Ubuntu 22.04 fallback instructions.
4. The completion handoff led with “Step 4 is complete” and test counts, linking to the summary. Although earlier updates and documents identified the target limitation, the final handoff did not prominently say that the user's Ubuntu 22.04 machine still lacked automatic installation and that its manual recovery experience had not been validated for usefulness.
5. The user subsequently supplied the following output from `./v3 start` with PHP missing:

   ```text
   Cannot start v3: PHP CLI is not available on PATH.
   Install PHP CLI with SQLite/PDO SQLite and mbstring support using your system's
   package manager or PHP installation instructions.
   ```

6. After PHP became available, the user supplied diagnostics identifying `/usr/bin/php8.1`, `PHP 8.1.2-1ubuntu2.26`, and `/etc/php/8.1/cli/php.ini`. Required SQLite/mbstring recovery still said only:

   ```text
   Install/enable PDO SQLite for the selected PHP CLI, then retry the same command with the same options.
   Install/enable mbstring for the selected PHP CLI, then retry the same command with the same options.
   ```

   Startup shortened this to “Install/enable this extension for the selected PHP CLI.” Optional intl guidance had the same package-discovery gap. The user first asked whether helpful options should have appeared, then said these extension messages also seemed unhelpful.

7. [Platform detection](../../scripts/php_setup_guidance.sh), [missing-PHP guidance](../../scripts/local_server_dependencies.sh) and the [PHP capability report](../../scripts/check_local_server_dependencies.php) explain the behavior: the concrete Ubuntu recipe is bounded to 24.04/PHP 8.3, leaving this 22.04/PHP 8.1 path generic. The output alone does not establish whether extension packages were absent or installed but disabled.

## Where the process failed

### A known environment mismatch was handled only as a testing constraint

The plan noticed the user's environment before implementation. It resolved the risk of falsely claiming installer compatibility by testing the intended target, but failed to turn the mismatch into a user-outcome decision: how would this user recover without that installer? A narrower automatic-install scope can be sound, but it needs a concrete manual outcome for the known excluded environment.

### A stated requirement lacked an observable acceptance condition

“Actionable manual guidance” appeared in Step 2. Step 3 retained “manual recovery” without specifying what a usable message must contain. Acceptance therefore did not require detected OS/runtime, a verified package command when applicable, a distinct enabling path for already-installed modules, and a way to verify success. “Install/enable” text passed without resolving the user's next decision.

### Coverage concentrated on the new install entry point

Testing covered substantial installer behavior and also startup/read-only regressions. The missing check was the usefulness of the exact journey the user followed: failed `start`, follow the diagnostics pointer, choose a repair, then retry. Existing tests proved many messages and safeguards existed; they did not prove that following the Ubuntu 22.04 messages was sufficient to reach the next step without package-name research or another conversation.

### Noninteractive behavior and useful guidance were not separated clearly enough

The implementation correctly avoided installation prompts during startup. That restriction did not prohibit showing helpful commands, explaining install-mode eligibility, or identifying the detected platform. The review accepted sparse fallback text despite those improvements remaining compatible with read-only startup. This is a gap in execution and acceptance, not evidence that startup should install automatically.

### Completion reporting emphasized verified scope without foregrounding the remaining user gap

Stage commits, an implementation summary and extensive tests made the delivered work reviewable. Their completion did not establish that the user's setup problem was solved. The handoff needed an explicit distinction between verified Ubuntu 24.04 installer capability and the remaining Ubuntu 22.04 recovery gap. Test counts are supporting evidence, not an outcome definition.

### Debug feedback missed the product failure

The [original completed-step debug report](local_server_dependency_install/local_server_dependency_install_debug_log.md) recognized valuable gates and instruction repetition but did not identify the unusable fallback subsequently reported by the user. Its positive assessment of contract verification was too broad for this recovery path. These later observations should supplement the historical report rather than erase it.

## Proposed changes to Railspike instructions

These are proposals for the improver, not edits to Railspike in this change.

- [ ] **Step 2:** Require the known user environment and supported implementation environment to be named separately when they differ. Require an explicit outcome for the user's environment: supported repair, verified manual workaround, or a prominently stated unresolved limitation. Do not automatically force broader platform support.
- [ ] **Step 2:** Define “actionable recovery” for failure-guidance work: identify the applicable environment, explain the cause sufficiently to select a remedy, give a verified next action, distinguish missing software from configuration restrictions, and explain verification/retry.
- [ ] **Step 3:** Translate every recovery promise into at least one concrete acceptance case. For a known unsupported environment, include the exact user-facing failure and expected next action; a generic error string is not sufficient evidence.
- [ ] **Step 3:** Include a small matrix of environment, ordinary entry point, failure state, expected recovery and required evidence. For this case it must include Ubuntu 22.04/PHP missing and Ubuntu 22.04/PHP 8.1 with missing SQLite/mbstring, alongside the supported installer cases.
- [ ] **Step 4 Do:** Add a recovery walkthrough from the ordinary entry point. Judge whether the displayed instructions let a user choose the correct next action without guessing, then verify that action in a disposable matching environment. Do not require risky changes on the user's machine.
- [ ] **Step 4 Do:** Treat unsupported-mode refusal and unsupported-environment recovery as separate checks: refusing an unsafe install is necessary, but does not prove the alternative guidance is useful.
- [ ] **Step 4 After:** Compare the delivered result with both the approved contract and the initiating user problem. If the known user's outcome remains unresolved, state that prominently and record the gap instead of relying on a broad completion label.
- [ ] **Debug mode:** When later user evidence contradicts a completed-step assessment, append a revision identifying the earlier conclusion and the new evidence. Include product-outcome misses alongside observations about instruction overhead.
- [ ] **Keep the additions small:** Integrate these checks into existing requirements, risks, completion contracts and handoff gates rather than creating another approval phase or duplicating the entire workflow.

## Necessary/useful instructions to retain

- **Source:** [Railspike Key Rules](../railspike/RAILSPIKE.md), “A feature is a releasable vertical slice: normal entry, end-to-end outcome, and required recovery,” and [Step 4 After](../railspike/docs/workflow/step4_implementation_after.md), checklist item 4. These already express the right principle. The failure shows that “required recovery” needs concrete evidence for the known excluded environment; retain and operationalize the rule rather than replacing it.
- **Source:** [Step 4 Do](../railspike/docs/workflow/step4_implementation_do.md), stage boundaries and contract completion. Real testing exposed package mapping and sudo/session defects and produced a useful supported-platform implementation. Keep real transactions, failure/interrupt tests, and stage-scoped history.
- **Source:** [Railspike scope and approval rules](../railspike/RAILSPIKE.md). Preserve deliberate limits and explicit authorization. A process fix should not encourage an agent to install packages on a user's host or silently add platform support to make a test pass.

## Redundant/unnecessary instructions

No supported finding establishes that instruction repetition or commit cadence caused this product failure. The earlier debug log contains separate overhead suggestions; those should not replace the substantive fix here: observable user-recovery acceptance. Requiring more approvals or another generic “test thoroughly” instruction would not address the missing condition.

## Uncertain assessments and limits

- This case demonstrates a failure in application of Railspike and insufficiently specific acceptance criteria. It does not establish that all Railspike workflows fail or that the workflow caused every implementation decision.
- The user transcripts demonstrate inadequate messages, not absent packages versus disabled modules. That distinction remains a remediation check.
- The supported-platform test results remain valid within their recorded environment. The correction is to the breadth of the completion/recovery assessment, not a claim that those results were fabricated.
- Package recipes discussed in follow-up are proposed repairs to validate. Automatic Ubuntu 22.04 support is a separate proposed extension, not a retroactive requirement to have installed software on the user's machine.

## How to test an improved Railspike process

Give the improved workflow a task where the available installer targets one platform but the known user has another. Supply the two failure transcripts above. A successful process should surface the mismatch during planning, specify concrete fallback acceptance, test that fallback as well as the supported installer, and report any remaining user limitation explicitly. Preserve read-only startup and safe installation boundaries. Passing only the supported-platform installer tests should no longer justify a claim that the user's recovery need is fully handled.
