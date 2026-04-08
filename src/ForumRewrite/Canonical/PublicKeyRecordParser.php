<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PublicKeyRecordParser
{
    public function parse(string $contents, string $pathFingerprint): PublicKeyRecord
    {
        if (!preg_match('//u', $contents) || preg_match('/[^\x09\x0A\x20-\x7E]/', $contents)) {
            throw new CanonicalRecordParseException('Public key file must be ASCII only.');
        }

        if (str_contains($contents, "\r")) {
            throw new CanonicalRecordParseException('Public key file must use LF line endings.');
        }

        if ($contents === '' || !str_ends_with($contents, "\n")) {
            throw new CanonicalRecordParseException('Public key file must end with a trailing LF.');
        }

        if (!preg_match('/^[A-F0-9]+$/', $pathFingerprint)) {
            throw new CanonicalRecordParseException('Public key fingerprint must be uppercase ASCII hex.');
        }

        if (!str_contains($contents, '-----BEGIN PGP PUBLIC KEY BLOCK-----')) {
            throw new CanonicalRecordParseException('Public key file must contain an armored OpenPGP public key.');
        }

        return new PublicKeyRecord($pathFingerprint, $contents);
    }
}
