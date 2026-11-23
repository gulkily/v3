# Public-key publication

Name: Public-key publication preview
Purpose: Compare canonical local identities with an explicitly named destination without publishing them.
Scenario: An operator runs `./v3 publish-keys` with a known instance name, hostname (HTTPS) or HTTP(S) URL. Output identifies the resolved canonical source repository and destination. The command checks the complete candidate inventory before network access, rejects private material and source escapes, and distinguishes missing identities from existing, retired, refused or unknown results. Names that cannot be preserved are refused. No approval, private key or message data is transferred.
Sources: `scripts/publish_public_keys.php`, `src/ForumRewrite/Support/PublicKeyPublisher.php`, `src/ForumRewrite/Support/PublicKeyPublishDestination.php`, `src/ForumRewrite/Security/OpenPgpKeyInspector.php`.

Name: Reviewed public-key publication
Purpose: Register explicitly selected missing identities through the destination's existing public registration API.
Scenario: An operator saves a Key: value plan outside the canonical repository, reviews it and selects full fingerprints. Publishing rechecks the source hashes, name/retirement eligibility and destination. Each attempted registration is read back; an uncertain outcome stays retryable, and an already accepted identity is skipped on rerun. Public registration does not grant membership or transfer local authority.
Sources: `src/ForumRewrite/Support/PublicKeyPublisher.php`, `src/ForumRewrite/Support/PublicKeyPublishDestination.php`, `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`, `src/ForumRewrite/Http/ApiTextController.php`.
