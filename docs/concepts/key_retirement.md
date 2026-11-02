# Key retirement

Name: Retained retirement evidence
Purpose: Preserve signed withdrawal of one key's future account privileges without deleting verification material.
Scenario: A rebuild verifies the signed record and matching repository-owned acceptance, derives historical approvals, then marks the target inactive. Later retirement of the actor does not invalidate that earlier accepted action.
Sources: `src/ForumRewrite/Security/KeyRetirement.php`, `docs/specs/key_retirement_record_v1.md`, `src/ForumRewrite/ReadModel/ReadModelBuilder.php`.
Status: Record/replay support implemented; mutation and full access enforcement are subsequent stages of the current cycle.

Name: Active versus historical approval
Purpose: Separate today's permission to act from retained approval evidence.
Scenario: Retiring an approver removes their active membership while the original approval graph still supports people they previously approved. Historical cryptographic verification remains independent of current session authorization.
Sources: `src/ForumRewrite/ReadModel/ReadModelSchema.php`, `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`.
