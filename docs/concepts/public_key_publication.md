# Public-key publication

Name: Public-key publication
Purpose: Register eligible missing public identities on an explicitly named instance.
Scenario: An operator runs `./v3 publish-keys` with a known instance name, hostname (HTTPS) or HTTP(S) URL; --dry-run checks without registering. Output identifies the resolved source repository and destination. All identities are considered unless full fingerprints are selected with --key. Complete initial public-only validation and per-key source revalidation reject private material, escapes, retired identities and name conflicts. Existing destination identities are skipped. Each registration attempt is read back; rerunning reconciles uncertain or partial outcomes. No review file is required. Registration does not grant membership or message access, and no private key, approval or message history is transferred.
Sources: `scripts/publish_public_keys.php`, `src/ForumRewrite/Support/PublicKeyPublisher.php`, `src/ForumRewrite/Support/PublicKeyPublishDestination.php`, `src/ForumRewrite/Security/OpenPgpKeyInspector.php`, `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`, `src/ForumRewrite/Http/ApiTextController.php`.
