<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class IdentityBootstrapRecordParser
{
    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): IdentityBootstrapRecord
    {
        $record = $this->parser->parse($contents);

        foreach (IdentityBootstrapRecord::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required identity header: ' . $header);
            }
        }

        $postId = $record->headers['Post-ID'];
        $identityId = $record->headers['Identity-ID'];
        $signerFingerprint = $record->headers['Signer-Fingerprint'];

        if ($record->headers['Board-Tags'] !== 'identity') {
            throw new CanonicalRecordParseException('Identity bootstrap record must use Board-Tags: identity.');
        }

        if ($record->headers['Subject'] !== 'identity bootstrap') {
            throw new CanonicalRecordParseException('Identity bootstrap record must use Subject: identity bootstrap.');
        }

        if (!preg_match('/^[A-F0-9]+$/', $signerFingerprint)) {
            throw new CanonicalRecordParseException('Signer-Fingerprint must be uppercase ASCII hex.');
        }

        $expectedIdentityId = 'openpgp:' . strtolower($signerFingerprint);
        if ($identityId !== $expectedIdentityId) {
            throw new CanonicalRecordParseException('Identity-ID must be derived from Signer-Fingerprint.');
        }

        if ($record->body === '') {
            throw new CanonicalRecordParseException('Identity bootstrap record must include an armored public key body.');
        }

        if (!str_contains($record->body, '-----BEGIN PGP PUBLIC KEY BLOCK-----')) {
            throw new CanonicalRecordParseException('Identity bootstrap record body must contain an armored OpenPGP public key.');
        }

        $expectedPostId = 'identity-' . str_replace(':', '-', $expectedIdentityId);
        if ($postId !== $expectedPostId) {
            throw new CanonicalRecordParseException('Post-ID must be derived from Identity-ID.');
        }

        return new IdentityBootstrapRecord(
            $postId,
            $record->headers['Username'] ?? 'guest',
            $identityId,
            $signerFingerprint,
            $record->headers['Bootstrap-By-Post'],
            $record->headers['Bootstrap-By-Thread'],
            $record->body,
        );
    }
}
