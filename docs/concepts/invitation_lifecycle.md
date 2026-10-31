# Invitation lifecycle

Purpose: Let an approved member sponsor a bounded number of distinct recipient keys through one signed bearer link.
Scenario: An issuer signs Max-Redemptions: 2. Two different keys accept under the repository write lock; a third is refused. A lost-response retry by either accepted key recovers access without using another slot. Revocation closes unused capacity but preserves earlier grants.
Authority: Existing signed invitation records and validated repository acceptance receipts. Missing multi-use acceptance evidence blocks replay and invitation operations; derived caches cannot create fresh capacity.
Compatibility: Absent limit means one. Browser issuance remains single-use; API-created capped links use the same Lobby. Trusted restore retains grants; public content import excludes invitation authority.
Sources: src/ForumRewrite/Canonical/InvitationRecordParser.php; src/ForumRewrite/Invitation/InvitationLedger.php; src/ForumRewrite/Invitation/InvitationApprovalReplay.php; src/ForumRewrite/Write/LocalWriteService.php; docs/specs/invitation_record_v1.md; docs/reference/invitation_entry.md.
