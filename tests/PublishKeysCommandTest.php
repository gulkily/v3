<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';
require_once __DIR__ . '/Support/PublicKeyPublishFixture.php';

use ForumRewrite\Security\OpenPgpKeyInspector;

final class PublishKeysCommandTest
{
    public function testPreviewOnlyReadsProfilesAndLeavesCanonicalSourceUntouched(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            $before = hash_file('sha256', $f->identityPath);
            [$code, $out, $err] = $f->command();
            assertSame(0, $code, $err); assertStringContains('State: missing', $out);
            assertSame($before, hash_file('sha256', $f->identityPath));
            assertSame(1, substr_count($f->requests(), 'GET /api/get_profile?'));
            assertSame(false, str_contains($f->requests(), 'POST'));
            $f->response(200, $f->profile());
            [$code, $out] = $f->command();
            assertSame(0, $code); assertStringContains('State: existing', $out);
        } finally { $f->close(); }
    }

    public function testSourceSecretsAndSymlinksFailBeforeAnyNetwork(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            $hidden = str_replace('PRIVATE KEY BLOCK', 'PUBLIC KEY BLOCK', $f->secretKey);
            $f->source($hidden);
            [$code, $out, $err] = $f->command();
            assertSame(1, $code); assertStringContains('secret-key packets', $err);
            assertSame('', $f->requests()); assertSame(false, str_contains($out . $err, trim($hidden)));
            $f->source(); unlink($f->keyPath); symlink($f->directory . '/missing-key', $f->keyPath);
            [$code] = $f->command(); assertSame(1, $code); assertSame('', $f->requests());
        } finally { $f->close(); }
    }

    public function testRefusalsAndUnknownProfilesNeverBecomeMissing(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            foreach ([[404, '<h1>not found</h1>', 'text/html', 'unknown'], [200, 'broken', 'text/plain', 'unknown'],
                [302, 'redirect', 'text/plain', 'unknown'], [403, 'denied', 'text/plain', 'refused'],
                [200, $f->profile('other-name'), 'text/plain', 'refused'], [200, $f->profile('publisher-fixture', 'yes'), 'text/plain', 'refused']] as [$status, $body, $type, $state]) {
                $f->response($status, $body, $type);
                [$code, $out] = $f->command(); assertSame(1, $code); assertStringContains('State: ' . $state, $out);
                assertSame(false, str_contains($out, 'State: missing'));
            }
            assertSame(false, str_contains($f->requests(), '/other-instance'));
            $f->source(null, 'local-renamed');
            $before = $f->requests(); [$code, $out] = $f->command();
            assertSame(1, $code); assertStringContains('Source name differs', $out); assertSame($before, $f->requests());
        } finally { $f->close(); }
    }

    public function testStrictInspectionRejectsSecretPacketsAndPreservesLegacyInspection(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            $i = new OpenPgpKeyInspector();
            assertSame($i->inspect($f->publicKey), $i->inspectPublicOnly($f->publicKey));
            assertSame($f->fingerprint, $i->inspect($f->secretKey)['fingerprint']);
            foreach ([$f->secretKey, str_replace('PRIVATE KEY BLOCK', 'PUBLIC KEY BLOCK', $f->secretKey), $f->publicKey . $f->secretKey] as $body) {
                $rejected = false;
                try { $i->inspectPublicOnly($body); } catch (RuntimeException) { $rejected = true; }
                assertSame(true, $rejected, 'Outbound inspection must reject private material.');
            }
        } finally { $f->close(); }
    }
}
