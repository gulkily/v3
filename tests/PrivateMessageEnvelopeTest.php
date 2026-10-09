<?php

declare(strict_types=1);

final class PrivateMessageEnvelopeTest
{
    /** @return array<string, mixed> */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/private_messages.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testPreparesOneSignedEnvelopeForEveryApprovedSenderAndRecipientKey(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const armored = (label) => '-----BEGIN PGP PUBLIC KEY BLOCK-----\n' + label + '\n-----END PGP PUBLIC KEY BLOCK-----\n';
const fetched = [];
let requiredMethods = [];
let encrypted = null;
global.window = {
  localStorage: {
    getItem(key) {
      return {
        forum_pki_username: 'alice',
        forum_pki_public_key: armored('LOCAL'),
        forum_pki_private_key: 'PRIVATE KEY LOCAL'
      }[key] || '';
    }
  },
  __forumBrowserIdentity: {
    async ensureOpenPgpApi(methods) { requiredMethods = methods; return global.window.openpgp; }
  },
  openpgp: {
    async readKey({ armoredKey }) { return { armoredKey, getFingerprint() { return armoredKey.includes('LOCAL') ? 'LOCAL' : 'OTHER'; } }; },
    async readPrivateKey() { return { getFingerprint() { return 'LOCAL'; } }; },
    async createMessage({ text }) { return { text }; },
    async encrypt(input) { encrypted = input; return '-----BEGIN PGP MESSAGE-----\nCIPHERTEXT\n-----END PGP MESSAGE-----\n'; }
  }
};
global.localStorage = global.window.localStorage;
global.fetch = async function(url, options) {
  fetched.push({ url: String(url), body: options && options.body ? String(options.body) : '' });
  const isAlice = String(url).includes('username_token=alice');
  return {
    ok: true,
    async json() {
      return { status: 'ok', keys: (isAlice ? ['ALICE-ONE', 'ALICE-TWO'] : ['ILYAG-ONE', 'ILYAG-TWO']).map((label) => ({ public_key: armored(label) })) };
    }
  };
};
vm.runInThisContext(source);
window.ForumPrivateMessages.prepareEnvelope({ plaintext: 'private prose', recipientUsernameToken: 'ilyag' })
  .then((result) => process.stdout.write(JSON.stringify({
    result,
    fetched,
    requiredMethods,
    plaintext: encrypted.message.text,
    keyLabels: encrypted.encryptionKeys.map((key) => key.armoredKey.match(/\n([^\n]+)\n-----END/)[1]),
    signingFingerprint: encrypted.signingKeys.getFingerprint(),
    format: encrypted.format
  })))
  .catch((error) => { process.stderr.write(error.stack || String(error)); process.exit(1); });
NODE;

        $result = $this->runScript($script);

        assertSame('-----BEGIN PGP MESSAGE-----' . "\n" . 'CIPHERTEXT' . "\n" . '-----END PGP MESSAGE-----' . "\n", $result['result']['encryptedEnvelope']);
        assertSame(['LOCAL', 'ALICE-ONE', 'ALICE-TWO', 'ILYAG-ONE', 'ILYAG-TWO'], $result['keyLabels']);
        assertSame('LOCAL', $result['signingFingerprint']);
        assertSame('armored', $result['format']);
        assertSame(['readKey', 'readPrivateKey', 'createMessage', 'encrypt'], $result['requiredMethods']);
        assertSame('', $result['fetched'][0]['body']);
        assertSame('', $result['fetched'][1]['body']);
        assertSame('private prose', $result['plaintext']);
    }
}
