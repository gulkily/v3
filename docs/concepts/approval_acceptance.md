# Approval acceptance

Purpose: Preserve local acceptance facts without confusing them with member signatures.
Scenario: A member signs an approval; the server records the exact grant hash and
reviewed context, then history/replay validate both before attributing acceptance.
Sources: `src/ForumRewrite/Security/ApprovalAcceptance.php`,
`src/ForumRewrite/Security/ApprovalReceiptText.php`,
`docs/specs/approval_acceptance_record_v1.md`.

# Signed approval review

Purpose: Bind a member's approval to the context actually reviewed.
Scenario: Policy changes between preparation and acceptance require fresh review;
the signed context digest remains verifiable even after storage conversion.
Sources: `src/ForumRewrite/Security/ApprovalPolicy.php`,
`src/ForumRewrite/Write/LocalWriteService.php`.

# Invitation approval replay

Purpose: Preserve accepted invitation grants independently of today's clock.
Scenario: A redemption accepted before expiry remains eligible during rebuild;
missing legacy acceptance facts remain unknown rather than invented.
Sources: `src/ForumRewrite/Invitation/InvitationApprovalReplay.php`.
