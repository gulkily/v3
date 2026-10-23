> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

Branch: `feat/unsigned-attribution` · Planning commit: `53e70490`

## Effort snapshot

- Step 4 entry — 2026-10-23; same study, cycle, scope v4, R1/P2 and rater. **About 0.95 reaffirmed** from Step 3; recorded after the planning commit and before implementation.
- Step 4 re-entry — 2026-10-23; same study, cycle, R1/P2 and rater; scope v6 after Approved Step 2 (revision 3) and Approved Step 3 (revision 2). **About 1.0 reaffirmed**; Stage 1 kept, later stages renumbered.

## Stage 1 - Measure the impact

- Changes:
  - `scripts/audit_post_signatures.php` also scans thread labels and post reactions with the existing `ReactionSignature::status` check and reports `reaction_verified`, `reaction_unsigned_named`, `reaction_invalid`, `reaction_anonymous` and `scored_label_unsigned_named` (unsigned labels naming an identity that include a scored tag; an upper bound, since author approval is not checked). Exit status still reflects posts only.
  - `tests/PostSignatureAuditCommandTest.php`: reaction counting test.
- Verification:
  - `php tests/run.php PostSignatureAuditCommandTest`: 2 passed.
  - zenmemes: downloaded the public backup `https://zenmemes.com/downloads/repository.tar.gz` (read-only GET; repository head `5b98332a`, 2026-10-23T07:21:09-07:00) into scratch space and ran the audit.
- Results (zenmemes, before):
  - Posts: 1,680 total; 666 signed and valid; 884 name an identity without a signature; 130 anonymous; 0 invalid; 0 unknown key.
  - Unsigned named posts by username: `ilyag` 589 (across several keys), `reply-agent` 215, then `chrome` 11, `guest` 9 and 28 other usernames with 7 or fewer each (32 usernames in all). (Correction: the Stage 1 commit said 515 for `ilyag`; per-username totals were rechecked after that commit.)
  - Unsigned named posts by month: April 158, May 226, June 272, July 126, August 51, September 37, October 13. Signed posts start in August (157, 147, 362).
  - Votes: 374 total; 17 signed; 357 name an identity without a signature, 174 of them with a scored tag; 0 anonymous; 0 invalid. Unsigned votes continue every month, 41 in October.
- Notes:
  - With the planned rule, about 57% of attributed posts and 95% of votes would become unverified, nearly all from the instance's own long-standing identities and the reply agent rather than impersonation.
  - Review gate: the maintainer reviews these counts before Stage 2.

## Enhancement Requests

- **Request:** keep existing unsigned items counting with a cutoff (maintainer, at the Stage 1 review gate, 2026-10-23). / **Disposition:** changed scope; implementation paused after Stage 1; returned to Step 2 (revision 2). / **Approval status:** pending renewed Approved Step 2 and Approved Step 3. / **Outcome:** pending.
- **Request:** keep today's behavior as the default; write the cutoff automatically and show it to operators (maintainer, 2026-10-23). / **Disposition:** changed scope; Step 2 revision 3 (default-off verified attribution flag; accept-unsigned-content flag to its own cycle) and Step 3 revision 2. / **Approval status:** Approved Step 2 (revision 3) and Approved Step 3 (revision 2). / **Outcome:** pending.
