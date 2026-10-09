<?php

declare(strict_types=1);

final class PrivateMessageReaderTest
{
    /** @return array<string, mixed> */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/private_message_reader.js'),
            escapeshellarg(__DIR__ . '/../public/assets/openpgp.min.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testDecryptsBothCopiesAndRendersOnlyVerifiedPlaintext(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.crypto = require('crypto').webcrypto;
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
const saved = new Map();
global.window = {
  crypto: global.crypto,
  localStorage: { getItem(key) { return saved.get(key) || ''; }, setItem(key, value) { saved.set(key, value); } },
  __forumBrowserIdentity: { async ensureOpenPgpApi() { return openpgp; } }
};
global.document = { addEventListener() {}, querySelectorAll() { return []; } };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
(async () => {
  const generate = (name) => openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  const [alice, bob, mallory] = await Promise.all([generate('alice'), generate('bob'), generate('mallory')]);
  const alicePublic = await openpgp.readKey({ armoredKey: alice.publicKey });
  const bobPublic = await openpgp.readKey({ armoredKey: bob.publicKey });
  const bobPrivate = await openpgp.readPrivateKey({ armoredKey: bob.privateKey });
  const envelope = await openpgp.encrypt({
    message: await openpgp.createMessage({ text: 'verified private prose' }),
    encryptionKeys: [alicePublic, bobPublic],
    signingKeys: bobPrivate,
    format: 'armored'
  });
  const read = window.ForumPrivateMessageReader.decryptEnvelope;
  saved.set('forum_pki_private_key', alice.privateKey);
  const aliceCopy = await read({ encryptedEnvelope: envelope, senderPublicKeyArmors: [bob.publicKey] });
  saved.set('forum_pki_private_key', bob.privateKey);
  const bobCopy = await read({ encryptedEnvelope: envelope, senderPublicKeyArmors: [bob.publicKey] });
  saved.set('forum_pki_private_key', '');
  const unavailable = await read({ encryptedEnvelope: envelope, senderPublicKeyArmors: [bob.publicKey] });
  saved.set('forum_pki_private_key', mallory.privateKey);
  const undecryptable = await read({ encryptedEnvelope: envelope, senderPublicKeyArmors: [bob.publicKey] });
  saved.set('forum_pki_private_key', alice.privateKey);
  const tamperedEnvelope = envelope.replace(/\n\n([A-Za-z0-9+/])/, (_match, character) => '\n\n' + (character === 'A' ? 'B' : 'A'));
  const tampered = await read({ encryptedEnvelope: tamperedEnvelope, senderPublicKeyArmors: [bob.publicKey] });
  const badSignature = await read({ encryptedEnvelope: envelope, senderPublicKeyArmors: [mallory.publicKey] });
  process.stdout.write(JSON.stringify({ aliceCopy, bobCopy, unavailable, undecryptable, tampered, badSignature }));
})().catch((error) => { process.stderr.write(error.stack || String(error)); process.exit(1); });
NODE;

        $result = $this->runScript($script);

        assertSame('verified', $result['aliceCopy']['kind']);
        assertSame('verified private prose', $result['aliceCopy']['plaintext']);
        assertSame('verified', $result['bobCopy']['kind']);
        assertSame('verified private prose', $result['bobCopy']['plaintext']);
        assertSame('unavailable', $result['unavailable']['kind']);
        assertSame('decryption-failed', $result['undecryptable']['kind']);
        assertSame('decryption-failed', $result['tampered']['kind']);
        assertSame('bad-signature', $result['badSignature']['kind']);
        assertSame(false, array_key_exists('plaintext', $result['unavailable']));
        assertSame(false, array_key_exists('plaintext', $result['undecryptable']));
        assertSame(false, array_key_exists('plaintext', $result['tampered']));
        assertSame(false, array_key_exists('plaintext', $result['badSignature']));
    }
}
