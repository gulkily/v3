# hashtag_self_tags Railspike debug log

## 2026-11-20T18:35Z — Step 1

### Necessary/useful instructions

- Host `AGENTS.md`, search feedback items first: found PF-20261111-F58, which asks for exactly this.
- `step1_solution_assessment.md`, Problem grounded in evidence: counting hashtag positions across 793 labelled posts on a local import turned a design question into data and separated the three options.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- None this step.

### Uncertain assessments

- Whether the composer preview belongs in every option or only the recommended one. Put in every option, since each changes or keeps a rule authors can't see today.

## 2026-11-20T19:45Z — Step 2

### Necessary/useful instructions

- `step2_feature_description.md`, Shared component inventory: found `markdown_detect.js` as the pattern for a composer helper, and confirmed `PostHashtags` needs no change because links stay.
- `step2_feature_description.md`, Risks with earliest validation: led to a shared example list read by both PHP and JavaScript tests.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- None this step.

### Uncertain assessments

- Step 1's open questions were not answered in the approval; Step 2 takes Option A's stated defaults and says so.

## 2026-11-20T20:40Z — Step 3

### Necessary/useful instructions

- `implementation_standards.md`, identify every surface that already uses the data: found that `ThreadActivity` and `TagOnlyLeafFilter` also call `fromBody`. That led to a separate `selfTags` method so their behaviour can't change.
- `step3_development_plan_do.md`, Completion Contract with deployment: checking where labels come from showed they are stored records written at post time, so the plan needs no rebuild or migration stage.
- Host `AGENTS.md`, UI copy, final on first paint: led the composer partial to render the Tags line on the server for a prefilled draft, instead of only in JavaScript.

### Redundant/unnecessary instructions

- `RAILSPIKE.md`, commit discipline, keep Step 1–3 documents uncommitted: the maintainer asked for each step to be committed and pushed to the branch, as Steps 1 and 2 were. The rule and the request conflict, and the request was followed.

### Inapplicable instructions

- `step3_development_plan_do.md`, replay a named user's reported failure: Step 2 names no user or environment failure.

### Uncertain assessments

- `RAILSPIKE.md`, the exact `Approved Step N` phrase: the maintainer wrote "Approve step 2". It was treated as approval and recorded with its wording.
- The page limit: the plan runs past one page because it has five stages with estimate fields.

## 2026-11-20T22:55Z — Step 4

### Necessary/useful instructions

- `implementation_standards.md`, replay the outcome through the ordinary entry: running the recount on a real import turned the high-usability risk into evidence. It found 26 real tags the approved rule would drop, 12 of them hidden by the `Image-v1` line.
- `RAILSPIKE.md`, Enhancement Requests, changed scope: stopping for the maintainer's choice, and recording Revision 1 in Steps 2 and 3, kept the rule change approved and visible. The maintainer took one of three proposed additions.
- `implementation_standards.md`, inspect existing implementations: led to reusing `PostHashtags`' pattern and `ImageReference::withoutReference` instead of new parsers.
- `step4_implementation_do.md`, stage boundary protocol: running checks before each commit caught a test file that never ran (not listed in `tests/run.php`) and a trailing-whitespace failure.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- `step4_implementation_before.md`, organize plans into the feature folder: they were already there from Step 1.
- Host `AGENTS.md`, navigation icons: no destination changed.

### Uncertain assessments

- `step4_implementation_before.md`, the planning commit: Steps 1–3 were already committed at the maintainer's request. The planning commit added only the Step 3 approval line. Whether that satisfies "approved plans only" as intended is unclear.
- `RAILSPIKE.md`, stage identities with a mid-cycle addition: Stage 5's scope was narrowed and Stage 6 added, rather than splitting Stage 5. The rules allow either; the choice kept stage numbers stable.
