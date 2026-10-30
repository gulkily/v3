<?php

declare(strict_types=1);
namespace ForumRewrite\Write;

use ForumRewrite\Canonical\{CanonicalPathResolver, IdentityBootstrapRecordParser};
use ForumRewrite\Security\{OpenPgpKeyInspector, OpenPgpSignatureVerifier};
use RuntimeException;

/** Resolve without mutation. Call again under the canonical write lock. */
final class SignedPostIdentity
{
    public function __construct(private readonly string $root, private readonly SignedPostBundleValidator $validator) {}

    public function resolve(array $input): array
    {
        $post = $this->validator->validateRecord((string) ($input['canonical_record'] ?? ''), false, false);
        $fingerprint = strtoupper(substr($post->authorIdentityId, 8));
        $identityPath = CanonicalPathResolver::identity(strtolower($fingerprint));
        $keyPath = CanonicalPathResolver::publicKey($fingerprint);
        $publicKey = (string) ($input['public_key'] ?? '');
        $inspected = null;
        if ($publicKey !== '') {
            $this->assertArmor($publicKey, 'PUBLIC KEY BLOCK');
            if (strlen($publicKey) > \ForumRewrite\Canonical\RecordLimits::ARMOR || preg_match('/[^\x0A\x0D\x20-\x7E]/', $publicKey)) throw new RuntimeException('Invalid public key armor.');
            $inspected = $this->inspect($publicKey);
            if ($inspected['fingerprint'] !== $fingerprint) throw new RuntimeException('Public key does not match Author-Identity-ID.');
        }
        $known = is_file($this->root . '/' . $identityPath);
        $identity = null;
        if ($known) {
            $identity = (new IdentityBootstrapRecordParser())->parse((string) file_get_contents($this->root . '/' . $identityPath));
            if (!is_file($this->root . '/' . $keyPath)) $keyPath = 'records/public-keys/openpgp-' . strtolower($fingerprint) . '.asc';
            if (!is_file($this->root . '/' . $keyPath)) throw new RuntimeException('Stored identity is incomplete; restore its public key before retrying.');
            $publicKey = (string) file_get_contents($this->root . '/' . $keyPath);
            $stored = $this->inspect($publicKey);
            if ($stored['fingerprint'] !== $fingerprint || $identity->identityId !== $post->authorIdentityId) throw new RuntimeException('Stored identity key mismatch.');
        } elseif ($publicKey === '') {
            throw new RuntimeException('Unknown identity requires public_key and a signed bootstrap.');
        } elseif (is_file($this->root . '/' . $keyPath) || is_file($this->root . '/records/public-keys/openpgp-' . strtolower($fingerprint) . '.asc')) {
            throw new RuntimeException('Public key exists without identity; repair registration before retrying.');
        }
        $verifier = new OpenPgpSignatureVerifier();
        $this->verify($verifier, $publicKey, $input['canonical_record'], (string) ($input['detached_signature'] ?? ''), $fingerprint);
        $bootstrapText = (string) ($input['bootstrap_record'] ?? '');
        $bootstrapSignature = (string) ($input['bootstrap_signature'] ?? '');
        if (($bootstrapText === '') !== ($bootstrapSignature === '')) throw new RuntimeException('Supply both bootstrap_record and bootstrap_signature.');
        $bootstrap = null;
        if ($bootstrapText !== '') {
            $bootstrap = $this->validator->validateRecord($bootstrapText, true);
            if ($bootstrap->authorIdentityId !== $post->authorIdentityId || $bootstrap->postId === $post->postId) throw new RuntimeException('Bootstrap must use the same identity and a distinct ID.');
            $this->verify($verifier, $publicKey, $bootstrapText, $bootstrapSignature, $fingerprint);
        }
        if (!$known && $bootstrap === null) throw new RuntimeException('Unknown identity requires a signed bootstrap.');
        $files = [];
        if (!$known) {
            $publicKey = str_replace("\r\n", "\n", trim($publicKey)) . "\n";
            $text = "Post-ID: identity-openpgp-" . strtolower($fingerprint) . "\nBoard-Tags: identity\nSubject: identity bootstrap\nUsername: {$inspected['username']}\nIdentity-ID: {$post->authorIdentityId}\nSigner-Fingerprint: {$fingerprint}\nBootstrap-By-Post: {$bootstrap->postId}\nBootstrap-By-Thread: {$bootstrap->postId}\n\n{$publicKey}";
            $identity = (new IdentityBootstrapRecordParser())->parse($text);
            $path = CanonicalPathResolver::datedPost($bootstrap->postId,$bootstrap->createdAt);
            $files = [$identityPath=>$text, $keyPath=>$publicKey, $path=>$bootstrapText, $path.'.asc'=>$bootstrapSignature];
        }
        return ['post'=>$post, 'bootstrap'=>$bootstrap, 'identity'=>$identity, 'known'=>$known, 'files'=>$files, 'identity_path'=>$identityPath];
    }

    private function inspect(string $publicKey): array
    {
        try { return (new OpenPgpKeyInspector())->inspect($publicKey); }
        catch (RuntimeException $error) {
            throw new RuntimeException('Unable to inspect public key. Supply one armored public key with a valid user ID.', 0, $error);
        }
    }

    private function assertArmor(string $value, string $kind): void
    {
        $value = trim($value);
        if (!str_starts_with($value, '-----BEGIN PGP ' . $kind . "-----\n")
            && !str_starts_with($value, '-----BEGIN PGP ' . $kind . "-----\r\n")) throw new RuntimeException('Expected one armored OpenPGP ' . $kind . '.');
        if (!str_ends_with($value, '-----END PGP ' . $kind . '-----')
            || substr_count($value, '-----BEGIN PGP ') !== 1 || substr_count($value, '-----END PGP ') !== 1) {
            throw new RuntimeException('Supply exactly one public key or signature armor block, without extra material.');
        }
    }

    private function verify(OpenPgpSignatureVerifier $verifier, string $key, string $text, string $signature, string $fingerprint): void
    {
        $this->assertArmor($signature, 'SIGNATURE');
        if (!$verifier->verifyDetached($key,$text,$signature,$fingerprint)['ok']) throw new RuntimeException('Detached signature verification failed.');
    }
}
