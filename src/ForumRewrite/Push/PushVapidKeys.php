<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use OpenSSLAsymmetricKey;
use RuntimeException;

/** The site's VAPID key pair, made on first use and kept as an owner-only PEM file. */
final class PushVapidKeys
{
    private ?OpenSSLAsymmetricKey $key = null;

    public function __construct(private readonly string $path)
    {
    }

    public function privateKey(): OpenSSLAsymmetricKey
    {
        return $this->key ??= $this->loadOrCreate();
    }

    /** The 65-byte public key the browser subscribes with (`applicationServerKey`). */
    public function publicKeyRaw(): string
    {
        return P256Key::rawPublicKey($this->privateKey());
    }

    private function loadOrCreate(): OpenSSLAsymmetricKey
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create ' . $directory . '.');
        }
        $lock = fopen($this->path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Unable to lock the push key.');
        }
        try {
            flock($lock, LOCK_EX);
            if (is_file($this->path)) {
                $key = openssl_pkey_get_private((string) file_get_contents($this->path));
                if ($key === false) {
                    throw new RuntimeException('The push key at ' . $this->path . ' is unreadable; remove it to make a new one.');
                }

                return $key;
            }
            $key = P256Key::generate();
            $pem = '';
            if (!openssl_pkey_export($key, $pem)) {
                throw new RuntimeException('Unable to export the push key.');
            }
            $temporary = $this->path . '.' . bin2hex(random_bytes(4)) . '.tmp';
            $previous = umask(0077);
            try {
                if (file_put_contents($temporary, $pem) === false || !chmod($temporary, 0600) || !rename($temporary, $this->path)) {
                    @unlink($temporary);
                    throw new RuntimeException('Unable to save the push key.');
                }
            } finally {
                umask($previous);
            }

            return $key;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
