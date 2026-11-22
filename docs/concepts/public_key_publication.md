# Public-key publication

Name: Public-key publication preview
Purpose: Compare canonical local identities with an explicitly named destination without publishing them.
Scenario: An operator runs `./v3 publish-keys` with a destination. The command checks the complete candidate inventory before network access, rejects private material and source escapes, and distinguishes missing identities from existing, retired, refused or unknown results. Names that cannot be preserved are refused. No approval, private key or message data is transferred.
Sources: `scripts/publish_public_keys.php`, `src/ForumRewrite/Support/PublicKeyPublisher.php`, `src/ForumRewrite/Support/PublicKeyPublishDestination.php`, `src/ForumRewrite/Security/OpenPgpKeyInspector.php`.
