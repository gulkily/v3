# Key retirement

Name: Retained retirement evidence
Purpose: Preserve signed withdrawal of one key's future account privileges without deleting verification material.
Scenario: A rebuild verifies the signed record and matching repository-owned acceptance, derives historical approvals, then marks the target inactive. Later retirement of the actor does not invalidate that earlier accepted action.
Sources: `src/ForumRewrite/Security/KeyRetirement.php`, `docs/specs/key_retirement_record_v1.md`, `src/ForumRewrite/ReadModel/ReadModelBuilder.php`.
Status: Implemented through signed API and shared key access UI, active access guards, retained history verification, schema 42 replay and administrative restore. Local acceptance evidence is recorded in `docs/plans/key_retirement/key_retirement_step4_implementation_summary.md`; production is not verified.

Name: Active versus historical approval
Purpose: Separate today's permission to act from retained approval evidence.
Scenario: Retiring an approver removes their active membership while the original approval graph still supports people they previously approved. Historical cryptographic verification remains independent of current session authorization.
Sources: `src/ForumRewrite/ReadModel/ReadModelSchema.php`, `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`.


Name: Disabled-rename retirement authority
Purpose: Allow same-account retirement without treating mutable usernames as membership proof.
Scenario: With username changes disabled, another active approved key can retire a key only when both retain their bootstrap names and have no verified rename history. Active root-approved operators have an independent path; both paths preserve an unchanged active replacement. Cross-account approval flags do not grant retirement authority.
Sources: `src/ForumRewrite/Security/KeyRetirementPolicy.php`, `src/ForumRewrite/Write/LocalWriteService.php`, `templates/partials/key_retirement.php`.

Name: Historical message verification
Purpose: Keep retained encrypted messages readable after their signing or donating keys retire.
Scenario: Active devices use retired keys to verify existing signatures and trusted retained transfers. New encryption recipients and new donors remain active-only; acceptance is serialized with retirement.
Sources: `src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php`, `src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php`, `public/assets/private_message_reader.js`.
