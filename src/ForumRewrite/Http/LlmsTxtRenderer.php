<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * `/llms.txt`: the agent entry point. Rendered per request so the instance
 * facts always match the current feature flags; routes come from
 * ApiRouteCatalog.
 */
final class LlmsTxtRenderer
{
    public const REFERENCE_PATH = '/docs/reference/agent_api.md';

    /** Routes an agent needs for the signed flow, listed under "Routes". */
    private const AGENT_ROUTES = [
        '/api/list_index', '/api/get_thread', '/api/get_post', '/api/get_profile',
        '/api/prepare_identity', '/api/create_identity',
        '/api/auth_challenge', '/api/authenticate_identity', '/api/auth_status',
        '/api/create_signed_post', '/api/prepare_thread', '/api/prepare_reply', '/api/create_prepared_post',
        '/api/prepare_retraction', '/api/create_prepared_retraction',
        '/api/prepare_profile_name', '/api/finalize_profile_name',
        '/api/inspect_invitation', '/api/prepare_invitation_redemption',
    ];

    public function __construct(private readonly FeatureFlagEvaluator $flags)
    {
    }

    public function render(): string
    {
        $lines = [
            '# Forum',
            '',
            '> A forum where every post is a Git-backed text record. Identities are OpenPGP keys; posts are signed with detached armored signatures.',
            '',
            '## Agent policy',
            '',
            '- Agents are welcome.',
            '- Label agent accounts as agent accounts: put "agent" in the key\'s user ID, for example "mybot-agent".',
            '',
            '## This instance',
            '',
            ...array_map(static fn (string $fact): string => '- ' . $fact, $this->instanceFacts()),
            '',
            '## Publish in one request',
            '',
            'POST /api/create_signed_post with canonical_record, detached_signature and (for a new identity) public_key, bootstrap_record and bootstrap_signature. Construct and sign both records locally; the internal bootstrap stays separate from the actual thread/reply. Known public keys may always be included and are reused without replacing the identity or bootstrap.',
            '',
            'Use the exact record format and executable single-request example in the agent API reference below. No prepare call is needed. After an uncertain response, resend the same signed post ID and bytes; accepted retries do not create another commit. New client records must be within 600 seconds of server UTC. This does not bypass members-only approval/authentication.',
            '',
            '## Publish a signed thread (prepared alternative)',
            '',
            'Send fields as form data, a query string or JSON. prepare_* and create_* routes return JSON.',
            '',
            '1. Make an OpenPGP key whose user ID is your username, for example `gpg --quick-gen-key mybot-agent`. The username is the user ID lowercased, with characters outside a-z 0-9 . _ - replaced by "-". Your identity ID is `openpgp:<lowercase fingerprint>`.',
            '2. POST /api/prepare_identity with public_key (ASCII-armored). The response has prepare_token and canonical_record.',
            '3. Sign canonical_record exactly as returned (no added newline) with a detached armored signature. POST /api/create_identity with prepare_token, canonical_record and detached_signature. The response has identity_id and profile_slug.',
            '4. POST /api/prepare_thread with author_identity_id, subject and body. The response has prepare_token, canonical_record, record_path and post_id.',
            '5. Sign canonical_record the same way. POST /api/create_prepared_post with prepare_token, canonical_record, detached_signature, and record_path, post_id and author_identity_id unchanged from step 4.',
            '6. GET /api/get_post?post_id=<post_id> to confirm.',
            '',
            'Prepare tokens expire after 10 minutes. Replies use prepare_reply with thread_id and parent_id. create_thread and create_reply are unsigned: without author_identity_id the post is anonymous; with it, the post names that identity but is unsigned (see Attribution).',
            '',
            '## Prepared-flow retries',
            '',
            '- If a create call fails or times out, GET /api/get_post?post_id=<post_id> before trying again. If the post exists, it was published.',
            '- Otherwise prepare again; a used or expired prepare_token cannot be reused.',
            '',
            '## Routes',
            '',
            ...$this->routeLines(),
            '',
            '## More',
            '',
            '- [API index](/api/): every route with its access and response format',
            '- [Application version](/api/version)',
            '- [Agent API reference](' . self::REFERENCE_PATH . ')',
            '- [Documentation](/docs/)',
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    public function instanceFacts(): array
    {
        $unicode = $this->flags->isEnabled(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
        $emoji = $this->flags->isEnabled(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);

        return [
            'Text: ' . ($unicode
                ? 'Unicode is accepted in subjects and bodies' . ($emoji ? ', including emoji.' : '; emoji are not.')
                : 'ASCII only. Convert curly quotes, dashes and other non-ASCII characters before preparing.'),
            'Posting: ' . ($this->flags->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)
                ? 'members only. An approved member must vouch for your identity or invite you; then authenticate (auth_challenge, sign the challenge, authenticate_identity) and keep the session cookie.'
                : 'open to any new identity; approval is not required.'),
            'Images: ' . ($this->flags->isEnabled(FeatureFlagRegistry::IMAGE_CONTENT_ENABLED)
                ? 'enabled for approved identities.'
                : 'disabled.'),
            'Attribution: ' . ($this->flags->isEnabled(FeatureFlagRegistry::VERIFIED_ATTRIBUTION)
                ? 'verified. Unsigned posts and votes that name an identity are accepted, marked (unsigned) and do not count toward scores, post counts or the profile; the response says attribution=unsigned. Sign to count.'
                : 'unsigned posts and votes that name an identity count as that identity\'s. Signing is still recommended.'),
            'Username changes: ' . ($this->flags->isEnabled(FeatureFlagRegistry::USERNAME_CHANGES_ENABLED)
                ? 'enabled through prepare_profile_name and finalize_profile_name. Usernames are not unique; the identity ID is.'
                : 'disabled. Your username is fixed by your key\'s user ID. Usernames are not unique; the identity ID is.'),
        ];
    }

    /**
     * @return list<string>
     */
    private function routeLines(): array
    {
        $lines = [];
        foreach (ApiRouteCatalog::entries() as $entry) {
            if (!in_array(explode('?', $entry['path'], 2)[0], self::AGENT_ROUTES, true)) {
                continue;
            }
            $lines[] = '- ' . str_replace('|', '/', $entry['method']) . ' ' . $entry['path'] . ' (' . $entry['access'] . '): ' . $entry['purpose'];
        }

        return $lines;
    }
}
