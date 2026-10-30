<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * The one list of `/api/` routes. The `/api/` index and `/llms.txt` render
 * from it, and ApiRouteCatalogTest compares it with the router so a new or
 * removed route cannot go unlisted.
 */
final class ApiRouteCatalog
{
    public const ACCESS_LEGEND = [
        'public' => 'no identity needed',
        'anonymous' => 'unsigned write; never attributed to an identity',
        'signed' => 'prepare, sign the returned bytes with a detached armored OpenPGP signature, then submit',
        'hint' => 'unsigned; uses the identity hint cookie',
        'session' => 'authenticated identity (signed auth_challenge)',
        'member' => 'approved identity',
        'operator' => 'root-approved operator identity',
        'testing' => 'test instances only',
        'retired' => 'always returns an error; kept for old clients',
    ];

    /**
     * @return list<array{method:string,path:string,access:string,format:string,purpose:string}>
     */
    public static function entries(): array
    {
        $rows = [
            // Discovery and reads
            ['GET', '/api/text_rules', 'public', 'json', 'Active authored-text policy and original character position semantics.'],
            ['GET', '/api/', 'public', 'text', 'This index.'],
            ['GET', '/api/version', 'public', 'text', 'Application version.'],
            ['GET', '/api/list_index', 'public', 'text', 'Threads, one per line: thread ID, title, reply count (tab-separated).'],
            ['GET', '/api/get_thread?thread_id=<id>', 'public', 'text', 'Thread headers, then one line per post.'],
            ['GET', '/api/get_post?post_id=<id>', 'public', 'text', 'Post headers and exact body.'],
            ['GET', '/api/get_profile?profile_slug=<slug>', 'public', 'text', 'Identity, username, approval and post count.'],
            ['GET', '/api/get_username?username=<name>', 'public', 'text', 'How many approved keys use this username.'],
            ['GET', '/api/get_username_claim_cta', 'public', 'text', 'Short prompt for claiming a username.'],
            ['GET', '/api/read_model_status', 'public', 'text', 'Read-model health.'],
            // Identity
            ['POST', '/api/prepare_identity', 'signed', 'json', 'Step 1 of identity creation: send public_key, receive the bytes to sign.'],
            ['POST', '/api/create_identity', 'signed', 'json', 'Step 2 of identity creation: send prepare_token and signature.'],
            ['POST', '/api/link_identity', 'public', 'text', 'Browser key bootstrap from public_key.'],
            ['GET|POST', '/api/set_identity_hint', 'public', 'text', 'Set or read the identity hint cookie.'],
            ['POST', '/api/clear_identity', 'public', 'text', 'Clear the identity hint cookie.'],
            ['GET', '/api/auth_challenge', 'public', 'text', 'Challenge to sign for authenticate_identity.'],
            ['POST', '/api/authenticate_identity', 'signed', 'text', 'Start a session with a signed challenge.'],
            ['GET', '/api/auth_status', 'session', 'text', 'Current session identity.'],
            ['POST', '/api/prepare_profile_name', 'signed', 'json', 'Prepare a username change (when username changes are enabled).'],
            ['POST', '/api/finalize_profile_name', 'signed', 'json', 'Submit a signed username change.'],
            // Posting
            ['POST', '/api/prepare_thread', 'signed', 'json', 'Prepare a signed thread: send author_identity_id and body, receive the bytes to sign.'],
            ['POST', '/api/prepare_reply', 'signed', 'json', 'Prepare a signed reply to thread_id.'],
            ['POST', '/api/prepare_quote', 'signed', 'json', 'Prepare a signed quote (QDB sites only).'],
            ['POST', '/api/create_signed_post', 'signed', 'json', 'Publish client-signed post and optional separately signed bootstrap in one request; reuse known keys.'],
            ['POST', '/api/create_prepared_post', 'signed', 'json', 'Publish a prepared post: send prepare_token and signature.'],
            ['POST', '/api/create_thread', 'anonymous', 'text', 'Unsigned thread; anonymous unless author_identity_id is sent.'],
            ['POST', '/api/create_reply', 'anonymous', 'text', 'Unsigned reply; anonymous unless author_identity_id is sent.'],
            ['POST', '/api/create_quote', 'anonymous', 'text', 'Unsigned quote; anonymous unless author_identity_id is sent (QDB sites only).'],
            ['POST', '/api/prepare_retraction', 'signed', 'json', 'Prepare a signed retraction of your own post.'],
            ['POST', '/api/create_prepared_retraction', 'signed', 'json', 'Publish a prepared retraction.'],
            // Tags and reactions
            ['POST', '/api/apply_thread_tag', 'hint', 'text', 'Tag a thread; signed when canonical_record and detached_signature are sent.'],
            ['POST', '/api/apply_post_tag', 'hint', 'text', 'Tag a post; signed when canonical_record and detached_signature are sent.'],
            ['POST', '/api/apply_signed_reaction', 'signed', 'text', 'Apply a reaction with a detached signature.'],
            // Approval and invitations
            ['POST', '/api/prepare_approval', 'signed', 'json', 'Prepare a signed approval of profile_slug.'],
            ['POST', '/api/create_prepared_approval', 'signed', 'json', 'Publish a prepared approval.'],
            ['POST', '/api/prepare_invitation', 'member', 'json', 'Prepare an invitation to sign.'],
            ['POST', '/api/create_prepared_invitation', 'signed', 'json', 'Publish a prepared invitation.'],
            ['POST', '/api/inspect_invitation', 'public', 'json', 'Check an invitation token.'],
            ['POST', '/api/prepare_invitation_redemption', 'signed', 'json', 'Prepare redemption of an invitation by an identity.'],
            ['POST', '/api/approve_user', 'retired', 'text', 'Replaced by prepare_approval.'],
            // Analysis and agent replies
            ['POST', '/api/analyze_post', 'public', 'json', 'Analyze post_id; approved viewers see the full result.'],
            ['GET|POST', '/api/score_post', 'retired', 'json', 'Fastmod scoring now runs in the background.'],
            ['POST', '/api/generate_agent_reply', 'member', 'json', 'Request an agent reply to post_id.'],
            ['POST', '/api/expand_agent_reply', 'member', 'json', 'Request a longer agent reply.'],
            ['GET', '/api/agent_expansion_status?post_id=<id>', 'public', 'json', 'Status of an agent reply expansion.'],
            ['GET', '/api/scripture_reply_status?post_id=<id>', 'public', 'json', 'Status of a scripture reply.'],
            ['GET', '/api/codex_handoff?handoff_id=<id>', 'operator', 'json', 'Read a Codex handoff.'],
            ['POST', '/api/codex_handoff', 'operator', 'json', 'Create a Codex handoff for post_id.'],
            ['POST', '/api/codex_handoff_approval', 'operator', 'json', 'Approve a Codex handoff.'],
            // Private messages
            ['GET', '/api/private_messages/inbox', 'member', 'json', 'Received messages.'],
            ['GET', '/api/private_messages/sent', 'member', 'json', 'Sent messages.'],
            ['GET', '/api/private_messages/conversation?username_token=<username>&cursor=<optional-opaque-cursor>', 'member', 'json', 'One conversation, newest page first.'],
            ['GET', '/api/private_messages/conversations?cursor=<optional-opaque-cursor>', 'member', 'json', 'Conversation list.'],
            ['GET', '/api/private_messages/recipient_keys?username_token=<username>', 'member', 'json', 'Recipient encryption keys.'],
            ['GET', '/api/private_messages/unread?counterparts[]=<optional-username>', 'member', 'json', 'Unread state.'],
            ['POST', '/api/private_messages', 'member', 'json', 'Send an encrypted message.'],
            ['POST', '/api/private_messages/read', 'member', 'json', 'Acknowledge read messages.'],
            ['GET', '/api/private_messages/history_sync/work', 'member', 'json', 'Pending history-sync work.'],
            ['GET', '/api/private_messages/history_sync/transfers?message_id=<id>&after=<optional-transfer-id>', 'member', 'json', 'History-sync transfers.'],
            ['POST', '/api/private_messages/history_sync/transfers', 'member', 'json', 'Upload a history-sync transfer.'],
            ['POST', '/api/private_messages/history_sync/acknowledge', 'member', 'json', 'Acknowledge history-sync transfers.'],
            // Operator tools
            ['POST', '/api/prepare_feature_flag_change', 'operator', 'json', 'Prepare a signed feature flag change.'],
            ['POST', '/api/finalize_feature_flag_change', 'operator', 'json', 'Publish a signed feature flag change.'],
            ['POST', '/api/prepare_attribution_cutoff', 'operator', 'json', 'Prepare a signed move of the verified attribution cutoff.'],
            ['POST', '/api/finalize_attribution_cutoff', 'operator', 'json', 'Publish a signed move of the verified attribution cutoff.'],
            ['POST', '/api/set_feature_flag', 'retired', 'text', 'Replaced by prepare_feature_flag_change.'],
            ['POST', '/api/prepare_high_traffic', 'operator', 'json', 'Prepare a signed high-traffic mode change.'],
            ['POST', '/api/finalize_high_traffic', 'operator', 'json', 'Publish a signed high-traffic mode change.'],
            ['POST', '/api/prepare_fastmod_rubrics', 'operator', 'json', 'Prepare a signed Fastmod rubric change.'],
            ['POST', '/api/finalize_fastmod_rubrics', 'operator', 'json', 'Publish a signed Fastmod rubric change.'],
            ['GET', '/api/railspike_workflow?thread_id=<id>', 'operator', 'json', 'Railspike workflow state for a thread.'],
            ['GET', '/api/railspike_workflow?activity=1', 'operator', 'json', 'Private Railspike workflow summaries.'],
            ['POST', '/api/railspike_workflow', 'operator', 'json', 'Prepare a signed workflow action, then submit signed_text and signature (same-origin JSON, X-Requested-With: ForumRailspike).'],
            ['POST', '/api/test_self_approval', 'testing', 'json', 'Self-approval on test instances.'],
            // Page fragments used by the web UI
            ['GET', '/api/feature_flags/navigation', 'public', 'json', 'Navigation HTML fragment.'],
            ['GET', '/api/forte_activity_page', 'public', 'json', 'Forte activity HTML fragment.'],
            ['GET', '/api/forte_activity_detail?id=<id>', 'public', 'json', 'Forte activity detail fragment.'],
            ['GET', '/api/forte_commit_detail?sha=<sha>', 'public', 'json', 'Forte commit detail fragment.'],
            ['GET', '/api/forte_thread_detail?thread_id=<id>', 'public', 'json', 'Forte thread detail fragment.'],
            ['GET', '/api/forte_user_detail?username_token=<username>', 'public', 'json', 'Forte user detail fragment.'],
            ['GET', '/api/get_forte_content_summary?post_id=<id>', 'public', 'json', 'Forte content summary fragment.'],
        ];

        return array_map(
            static fn (array $row): array => array_combine(['method', 'path', 'access', 'format', 'purpose'], $row),
            $rows,
        );
    }

    /**
     * Route paths without their query hints, for matching against the router.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $entry): string => explode('?', $entry['path'], 2)[0],
            self::entries(),
        )));
    }

    public static function renderIndex(): string
    {
        $lines = ['Plain-text API. Agents start at /llms.txt.', 'Access:'];
        foreach (self::ACCESS_LEGEND as $access => $meaning) {
            $lines[] = '  ' . $access . ': ' . $meaning;
        }
        $lines[] = '';
        foreach (self::entries() as $entry) {
            foreach (explode('|', $entry['method']) as $method) {
                $lines[] = $method . ' ' . $entry['path'] . ' [' . $entry['access'] . ', ' . $entry['format'] . '] ' . $entry['purpose'];
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
