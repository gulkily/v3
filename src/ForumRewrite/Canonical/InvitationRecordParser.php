<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class InvitationRecordParser
{
    private const ACTIONS = ['issue', 'revoke', 'redeem'];

    public function __construct(
        private readonly PostRecordParser $postParser = new PostRecordParser(),
        private readonly GenericTextRecordParser $bodyParser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): InvitationRecord
    {
        $post = $this->postParser->parse($contents);
        foreach (['identity', 'invitation', 'internal'] as $tag) {
            if (!in_array($tag, $post->boardTags, true)) {
                throw new CanonicalRecordParseException('Invitation record must include Board-Tags: identity invitation internal.');
            }
        }
        if (!$post->isReply() || $post->authorIdentityId === null) {
            throw new CanonicalRecordParseException('Invitation record must be an authored reply.');
        }

        $details = $this->bodyParser->parse($post->body);
        $id = $details->headers['Invitation-ID'] ?? '';
        $action = $details->headers['Invitation-Action'] ?? '';
        $verificationHash = $details->headers['Verification-Hash'] ?? '';
        if (preg_match('/^invite-[a-z0-9]{16,64}$/', $id) !== 1) {
            throw new CanonicalRecordParseException('Invitation-ID must be invite- followed by 16-64 lowercase alphanumeric characters.');
        }
        if (!in_array($action, self::ACTIONS, true)) {
            throw new CanonicalRecordParseException('Invitation-Action must be issue, revoke, or redeem.');
        }
        if (preg_match('/^sha256:[a-f0-9]{64}$/', $verificationHash) !== 1) {
            throw new CanonicalRecordParseException('Verification-Hash must be a lowercase sha256 hash.');
        }

        $expiresAt = $details->headers['Expires-At'] ?? null;
        $destination = $details->headers['Destination'] ?? null;
        if ($action === 'issue') {
            if ($expiresAt === null || !$this->isUtcTimestamp($expiresAt)) {
                throw new CanonicalRecordParseException('Issued invitations require Expires-At in RFC 3339 UTC format.');
            }
            if ($destination !== null && !$this->isInternalDestination($destination)) {
                throw new CanonicalRecordParseException('Invitation Destination must be an internal absolute path.');
            }
        } elseif ($expiresAt !== null || $destination !== null) {
            throw new CanonicalRecordParseException('Only issued invitations may include Expires-At or Destination.');
        }

        return new InvitationRecord($post, $id, $action, $verificationHash, $expiresAt, $destination);
    }

    private function isUtcTimestamp(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            return false;
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d\TH:i:s\Z') === $value;
        } catch (\Exception) {
            return false;
        }
    }

    private function isInternalDestination(string $value): bool
    {
        return strlen($value) <= 500
            && str_starts_with($value, '/')
            && !str_starts_with($value, '//')
            && !str_contains($value, "\n")
            && !str_contains($value, "\r")
            && !str_contains($value, '#');
    }
}
