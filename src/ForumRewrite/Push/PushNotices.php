<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use Closure;
use ForumRewrite\Canonical\AgentResponseMetadata;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Support\ClaimDirective;
use ForumRewrite\Support\ReleaseDirective;
use ForumRewrite\Support\ThreadLabels;
use PDO;

/**
 * Turns a newly published reply or an accepted DM into pending notices (see PushNotifier).
 *
 * A reply notifies the member who wrote its parent, under Unread's Replies rule: the parent's
 * author is any key of their username, and the reply is not theirs. Skipped: ported comments,
 * Scripture expansions, tag-only and `title:`-only replies, bare claim and release lines in the
 * claims thread, replies more than an hour old (format changes, repairs), the shared name
 * "guest", and members with no device turned on.
 */
final class PushNotices
{
    public const KIND_REPLY = 'reply';
    public const KIND_DM = 'dm';
    public const BODY_CHARACTERS = 100;
    private const FRESH_SECONDS = 3600;

    /**
     * @param Closure(): PushSubscriptionStore $openStore
     * @param string $site the site's https address, for the push token
     */
    public function __construct(
        private readonly PDO $readPdo,
        private readonly Closure $openStore,
        private readonly string $site,
        private readonly ?string $claimsThread = null,
        private readonly ?Closure $clock = null,
    ) {
    }

    /** @return int notices recorded */
    public function recordReply(string $postId): int
    {
        $statement = $this->readPdo->prepare('SELECT * FROM posts WHERE post_id = ?');
        $statement->execute([$postId]);
        $post = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($post) || (string) ($post['parent_id'] ?? '') === '' || !$this->notifies($post)) {
            return 0;
        }
        $parent = $this->readPdo->prepare('SELECT author_identity_id FROM posts WHERE post_id = ?');
        $parent->execute([(string) $post['parent_id']]);
        $parentAuthor = (string) ($parent->fetchColumn() ?: '');
        // Only an approved key speaks for its username; an unapproved key could pick any name.
        $recipient = $this->usernameToken($parentAuthor, approvedOnly: true);
        if ($recipient === '' || $recipient === 'guest' || $recipient === $this->usernameToken((string) ($post['author_identity_id'] ?? ''), approvedOnly: true)) {
            return 0;
        }

        return $this->add([
            'kind' => self::KIND_REPLY,
            'source_id' => (string) $post['post_id'],
            'source_created_at' => (string) $post['created_at'],
            'recipient' => $recipient,
            'tag' => 'thread:' . $post['thread_id'],
            'actor' => trim((string) ($post['author_label'] ?? '')) !== '' ? (string) $post['author_label'] : 'guest',
            'body' => self::firstPlainLine((string) $post['body']),
            'url' => '/posts/' . rawurlencode((string) $post['post_id']),
        ]);
    }

    /** @return int notices recorded */
    public function recordMessage(string $messageId, string $senderToken, string $recipientToken): int
    {
        $senderToken = strtolower($senderToken);
        $recipientToken = strtolower($recipientToken);
        if ($recipientToken === '' || $recipientToken === 'guest' || $recipientToken === $senderToken) {
            return 0;
        }

        return $this->add([
            'kind' => self::KIND_DM,
            'source_id' => $messageId,
            'recipient' => $recipientToken,
            'tag' => 'dm:' . $senderToken,
            'actor' => $senderToken,
            'body' => '',
            'url' => '/messages/conversation/' . rawurlencode($senderToken),
        ]);
    }

    /** The first line with text, without Markdown markers, cut to about BODY_CHARACTERS. */
    public static function firstPlainLine(string $body): string
    {
        $line = '';
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $candidate) {
            $candidate = trim((string) preg_replace('/^\s*(?:>\s*)+|^\s*(?:#{1,6}|[-*+]|\d+[.)])\s+/u', '', $candidate));
            if ($candidate !== '' && !preg_match('/^(?:```|~~~|---+|\*\*\*+)$/', $candidate)) {
                $line = $candidate;
                break;
            }
        }
        $line = (string) preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', $line);
        $line = str_replace(['**', '__', '`'], '', $line);
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));

        return mb_strlen($line) > self::BODY_CHARACTERS ? rtrim(mb_substr($line, 0, self::BODY_CHARACTERS - 1)) . '…' : $line;
    }

    /** @param array<string, mixed> $post */
    private function notifies(array $post): bool
    {
        if ((string) ($post['ported_from'] ?? '') !== '' || (int) ($post['is_hidden'] ?? 0) === 1 || ($post['retracted_at'] ?? null) !== null) {
            return false;
        }
        $agentResponse = json_decode((string) ($post['agent_response_json'] ?? ''), true);
        if (is_array($agentResponse) && ($agentResponse['type'] ?? '') === AgentResponseMetadata::EXPANSION) {
            return false;
        }
        if (ThreadLabels::isTagOrTitleOnly($post)) {
            return false;
        }
        $body = (string) $post['body'];
        if ($this->claimsThread !== null && (string) $post['thread_id'] === $this->claimsThread
            && (ClaimDirective::isBare($body) || ReleaseDirective::isBare($body))) {
            return false;
        }
        $created = strtotime((string) $post['created_at']);

        return $created !== false && $created >= $this->now() - self::FRESH_SECONDS;
    }

    private function usernameToken(string $identityId, bool $approvedOnly): string
    {
        if ($identityId === '') {
            return '';
        }
        $profile = ProfileRepository::byIdentityId($this->readPdo, $identityId);
        if (!is_array($profile) || ($approvedOnly && (int) ($profile['is_approved'] ?? 0) !== 1)) {
            return '';
        }

        return strtolower(trim((string) ($profile['username_token'] ?? '')));
    }

    /**
     * True when a stored device may get its username's notices: the key that turned it on is an
     * approved key of that username. Anyone can sign in with an unapproved key named "ilyag".
     *
     * @param array{username:string,identity_id:string} $device
     */
    public static function deviceMayReceive(PDO $readPdo, array $device): bool
    {
        $profile = ProfileRepository::byIdentityId($readPdo, $device['identity_id']);

        return is_array($profile) && (int) ($profile['is_approved'] ?? 0) === 1
            && strtolower((string) ($profile['username_token'] ?? '')) === strtolower($device['username']);
    }

    /** @param array<string, string> $notice */
    private function add(array $notice): int
    {
        $store = ($this->openStore)();
        if ($store->devicesFor($notice['recipient']) === []) {
            return 0;
        }

        return $store->addPending($notice + ['site' => $this->site], $this->now()) ? 1 : 0;
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
