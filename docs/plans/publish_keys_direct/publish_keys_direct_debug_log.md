# Direct public-key publication diagnostics

## 2026-11-23T02:09:02+00:00 — One-shot closeout

### Necessary/useful instructions

- CONTRIBUTING.md, Operator CLI: scripts/import_instance.php is the concrete precedent for execute-by-default and --dry-run. The prior syntax-only fix missed this interaction. Retain the explicit comparison requirement; this cycle records and tests its actual semantics instead of only accepting the same URL forms.
- PRINCIPLES.md, How we build it: removing mandatory saved files/fingerprint copying allowed the publisher's plan writer/parser to be deleted. Retain the instruction to cut unnecessary steps and text; source safety/reconciliation remains in the existing implementation.
- one_shot.md, Planning commit gate: new branch fix/publish-keys-direct in the existing checkout, plan/index-only commit 857f25d1a before code and a completion commit with code/evidence. The user's explicit change request authorized the bounded simplification; appreciation of one-shot did not authorize production writes. Retain.
- implementation_standards.md, Risk and reuse: default all-key publication required preserving complete initial public-only validation and optional selection checks before requests. Per-key source revalidation inspects only the pending identity, avoiding quadratic GPG inspection while keeping source-hash/name/retirement checks. Reused destination transport/read-back rather than adding another API path or hidden plan file. Retain.
- implementation_standards.md, Ordinary entry/recovery: actual checkout's two-key source replay against isolated HTTP showed refused/missing in dry-run, refused/published by default and refused/existing on rerun with one total POST. Actual server API fixture also confirms unapproved registration and unchanged dry-run/rerun HEAD. Retain concrete user-environment evidence and separate live acceptance.
- Host Tests rule: initial focused 25/25; final publisher/command 16/16 includes a late-added unsafe-unselected-key check. Smoke 507/511 with four known parent-confirmed Unicode/PCRE failures. No full suite. Legacy --plan/--publish reject before requests so an old preview invocation cannot accidentally publish. Retain safety-specific checks and accurate counts/omissions.
- effort-estimation.md, One-shot checkpoints: intake remains unavailable after prior exposure; entry and retrospective whole-cycle 0.30 retained separately, R1/P2 verified, Codex single exposed rater, measured labor unknown, maintainer fit pending. No fabricated stages or outcome increments. Retain.

### Redundant/unnecessary instructions

No supported workflow-instruction findings. The removed mandatory review-file interaction was product behavior explicitly superseded by the user, not a reason to weaken approval/source-safety instructions or erase prior plans.

### Inapplicable instructions

- Browser/theme/navigation/icon and migration/dependency checks: no affected surfaces, schema or third-party changes. Existing actual API consumers remain covered.
- Live production key publication, external posting, push, PR, merge and deployment: outside this cycle's authorization. No such action performed; isolated fixture writes are acceptance evidence only for the local implementation.
- Full-suite execution: prohibited by host test policy; scoped smoke/affected checks run instead. No full-suite pass claimed.

### Uncertain assessments

- Original source has a locally renamed guest identity. Its refusal remains visible and exit status stays 1 while another eligible key succeeds. Changing name history or retirement behavior would expand scope; not attempted.
- Old saved-plan files are not migrated or removed. Their CLI flags now reject with --dry-run guidance. Historical parent records preserve earlier behavior; current CLI reference and concept describe the new default.
- Maintainer comfortable-fit judgment, deployment/production acceptance date and measured active labor remain pending/unknown. Feedback B04 stays locally verified and blocked for acceptance; broader reverse import remains separate.

No unapproved workflow edits, exhaustive diagnostic claim or independent-rater claim.
