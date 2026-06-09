<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use RuntimeException;

final class OpenPgpKeyInspector
{
    /**
     * @return array{fingerprint:string,username:string}
     */
    public function inspect(string $armoredPublicKey): array
    {
        $tempDir = sys_get_temp_dir() . '/forum-rewrite-gpg-' . bin2hex(random_bytes(6));
        mkdir($tempDir, 0700, true);
        $keyPath = $tempDir . '/key.asc';
        file_put_contents($keyPath, $armoredPublicKey);

        $command = sprintf(
            'gpg --batch --no-tty --homedir %s --with-colons --import-options show-only --import %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($keyPath),
        );
        exec($command, $output, $exitCode);
        $this->cleanup($tempDir);

        if ($exitCode !== 0) {
            $details = $this->compactGpgOutput($output);
            throw new RuntimeException(
                'Unable to inspect OpenPGP public key.'
                . ($details !== '' ? ' gpg said: ' . $details : '')
            );
        }

        $fingerprint = null;
        $username = null;

        foreach ($output as $line) {
            $parts = explode(':', $line);
            if (($parts[0] ?? '') === 'fpr' && $fingerprint === null) {
                $fingerprint = $parts[9] ?? null;
            }

            if (($parts[0] ?? '') === 'uid' && $username === null) {
                $username = $parts[9] ?? null;
            }
        }

        if ($fingerprint === null || $username === null) {
            $fallback = $this->inspectArmoredPublicKey($armoredPublicKey);
            $fingerprint ??= $fallback['fingerprint'];
            $username ??= $fallback['username'];
        }

        if ($fingerprint === null || $username === null) {
            $records = $this->compactGpgRecordSummary($output);
            throw new RuntimeException(
                'OpenPGP public key is missing a fingerprint or user ID.'
                . ($records !== '' ? ' gpg records: ' . $records : '')
            );
        }

        return [
            'fingerprint' => strtoupper(trim($fingerprint)),
            'username' => $this->normalizeUsername($username),
        ];
    }

    private function normalizeUsername(string $username): string
    {
        $normalized = strtolower(trim($username));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'guest';
    }

    /**
     * @return array{fingerprint:?string,username:?string}
     */
    private function inspectArmoredPublicKey(string $armoredPublicKey): array
    {
        $binary = $this->decodeArmoredPublicKey($armoredPublicKey);
        if ($binary === null) {
            return ['fingerprint' => null, 'username' => null];
        }

        $offset = 0;
        $length = strlen($binary);
        $fingerprint = null;
        $username = null;

        while ($offset < $length) {
            $packet = $this->readPacket($binary, $offset);
            if ($packet === null) {
                break;
            }

            if ($packet['tag'] === 6 && $fingerprint === null) {
                $fingerprint = $this->fingerprintPublicKeyPacket($packet['body']);
            } elseif ($packet['tag'] === 13 && $username === null) {
                $username = $this->normalizeUsername($packet['body']);
            }

            if ($fingerprint !== null && $username !== null) {
                break;
            }
        }

        return ['fingerprint' => $fingerprint, 'username' => $username];
    }

    private function decodeArmoredPublicKey(string $armoredPublicKey): ?string
    {
        if (preg_match('/-----BEGIN PGP PUBLIC KEY BLOCK-----\s*(.*?)-----END PGP PUBLIC KEY BLOCK-----/s', $armoredPublicKey, $matches) !== 1) {
            return null;
        }

        $base64Lines = [];
        foreach (preg_split('/\R/', $matches[1]) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '=') || str_contains($line, ':')) {
                continue;
            }

            $base64Lines[] = $line;
        }

        $decoded = base64_decode(implode('', $base64Lines), true);
        return $decoded === false ? null : $decoded;
    }

    /**
     * @return array{tag:int,body:string}|null
     */
    private function readPacket(string $binary, int &$offset): ?array
    {
        $length = strlen($binary);
        if ($offset >= $length) {
            return null;
        }

        $header = ord($binary[$offset++]);
        if (($header & 0x80) === 0) {
            return null;
        }

        if (($header & 0x40) !== 0) {
            $tag = $header & 0x3f;
            $packetLength = $this->readNewPacketLength($binary, $offset);
        } else {
            $tag = ($header >> 2) & 0x0f;
            $packetLength = $this->readOldPacketLength($binary, $offset, $header & 0x03);
        }

        if ($packetLength === null || $packetLength < 0 || $offset + $packetLength > $length) {
            return null;
        }

        $body = substr($binary, $offset, $packetLength);
        $offset += $packetLength;

        return ['tag' => $tag, 'body' => $body];
    }

    private function readNewPacketLength(string $binary, int &$offset): ?int
    {
        $length = strlen($binary);
        if ($offset >= $length) {
            return null;
        }

        $first = ord($binary[$offset++]);
        if ($first < 192) {
            return $first;
        }

        if ($first < 224) {
            if ($offset >= $length) {
                return null;
            }

            return (($first - 192) << 8) + ord($binary[$offset++]) + 192;
        }

        if ($first === 255) {
            if ($offset + 4 > $length) {
                return null;
            }

            $unpacked = unpack('Nlength', substr($binary, $offset, 4));
            $offset += 4;
            return (int) ($unpacked['length'] ?? 0);
        }

        return null;
    }

    private function readOldPacketLength(string $binary, int &$offset, int $lengthType): ?int
    {
        $length = strlen($binary);
        if ($lengthType === 0) {
            if ($offset >= $length) {
                return null;
            }

            return ord($binary[$offset++]);
        }

        if ($lengthType === 1) {
            if ($offset + 2 > $length) {
                return null;
            }

            $unpacked = unpack('nlength', substr($binary, $offset, 2));
            $offset += 2;
            return (int) ($unpacked['length'] ?? 0);
        }

        if ($lengthType === 2) {
            if ($offset + 4 > $length) {
                return null;
            }

            $unpacked = unpack('Nlength', substr($binary, $offset, 4));
            $offset += 4;
            return (int) ($unpacked['length'] ?? 0);
        }

        return $length - $offset;
    }

    private function fingerprintPublicKeyPacket(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $version = ord($body[0]);
        $length = strlen($body);

        if ($version === 4) {
            return strtoupper(sha1(chr(0x99) . pack('n', $length) . $body));
        }

        if ($version === 5 || $version === 6) {
            return strtoupper(hash('sha256', chr(0x9a) . pack('N', $length) . $body));
        }

        return null;
    }

    /**
     * @param list<string> $output
     */
    private function compactGpgOutput(array $output): string
    {
        $lines = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'gpg: keybox ') || str_starts_with($line, 'gpg: trustdb ')) {
                continue;
            }

            $lines[] = $line;
            if (count($lines) >= 3) {
                break;
            }
        }

        return implode(' ', $lines);
    }

    /**
     * @param list<string> $output
     */
    private function compactGpgRecordSummary(array $output): string
    {
        $records = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'gpg:')) {
                continue;
            }

            $parts = explode(':', $line);
            $record = $parts[0] ?? '';
            if ($record === '') {
                continue;
            }

            $value = '';
            if ($record === 'fpr') {
                $value = $parts[9] ?? '';
            } elseif ($record === 'uid') {
                $value = $parts[9] ?? '';
            } elseif ($record === 'pub' || $record === 'sub') {
                $value = ($parts[3] ?? '') . '/' . ($parts[4] ?? '');
            }

            $records[] = $value !== '' ? $record . '(' . $value . ')' : $record;
            if (count($records) >= 6) {
                break;
            }
        }

        return implode(', ', $records);
    }

    private function cleanup(string $tempDir): void
    {
        foreach (glob($tempDir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($tempDir);
    }
}
