<?php

declare(strict_types=1);

final class PrivateSiteAuthTest
{
    /**
     * @return array<string, mixed>
     */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/private_site_auth.js'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testApprovedIdentityCompletesChallengeAndRedirects(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';
const fetches = [];
let ensureArguments = null;
let assignedUrl = '';
const status = { hidden: true, textContent: '', dataset: {} };
const state = { dataset: { authenticatedIdentityId: '' } };
const storage = {
  forum_pki_public_key: 'public-key',
  forum_pki_private_key: 'private-key',
  forum_pki_fingerprint: fingerprint
};

global.window = {
  localStorage: { getItem(key) { return storage[key] || ''; } },
  __forumOpenPgpLoader: { ready: Promise.resolve({}) },
  __forumBrowserIdentity: {
    async ensureReadyIdentity() { ensureArguments = Array.from(arguments); }
  },
  openpgp: {
    async readKey() { return { getFingerprint() { return fingerprint; } }; },
    async readPrivateKey() { return { getFingerprint() { return fingerprint; } }; },
    async createMessage({ text }) { return { text }; },
    async sign() { return 'detached-signature'; }
  },
  location: {
    assign(url) { assignedUrl = url; },
    reload() { throw new Error('approved viewer must not reload'); }
  }
};
global.document = {
  addEventListener(){},
  querySelector(selector) {
    if (selector === '[data-private-site-auth-state]') return state;
    if (selector === '[data-role="private-site-auth-status"]') return status;
    return null;
  }
};
global.fetch = async function(url, options) {
  fetches.push({ url: String(url), body: options && options.body ? String(options.body) : '' });
  if (String(url) === '/api/auth_challenge') {
    return { ok: true, async text() { return 'challenge=abcdef012345\n'; } };
  }
  return {
    ok: true,
    async text() {
      return 'status=ok\nidentity_id=openpgp:' + fingerprint + '\napproved=1\n';
    }
  };
};

vm.runInThisContext(source);
window.PrivateSiteAuth.authenticate()
  .then((result) => {
    process.stdout.write(JSON.stringify({
      result,
      fetches,
      ensureRootWasNull: ensureArguments[0] === null,
      verifyPublishedIdentity: ensureArguments[2].verifyPublishedIdentity,
      assignedUrl,
      status
    }));
  })
  .catch((error) => {
    process.stderr.write(error.stack || String(error));
    process.exit(1);
  });
NODE;

        $result = $this->runScript($script);

        assertSame('approved', $result['result']['status']);
        assertSame(true, $result['ensureRootWasNull']);
        assertSame(true, $result['verifyPublishedIdentity']);
        assertSame('/api/auth_challenge', $result['fetches'][0]['url']);
        assertSame('/api/authenticate_identity', $result['fetches'][1]['url']);
        assertStringContains('identity_id=openpgp%3A0123456789abcdef0123456789abcdef01234567', $result['fetches'][1]['body']);
        assertSame('/', $result['assignedUrl']);
        assertSame('Identity verified. Entering the site...', $result['status']['textContent']);
    }

    public function testMatchingAuthenticatedSessionSkipsRepeatedAuthentication(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';
let fetchCount = 0;

global.window = {
  localStorage: {
    getItem(key) {
      if (key === 'forum_pki_public_key') return 'public-key';
      if (key === 'forum_pki_private_key') return 'private-key';
      if (key === 'forum_pki_fingerprint') return fingerprint;
      return '';
    }
  },
  location: { assign(){}, reload(){} }
};
global.document = {
  addEventListener(){},
  querySelector(selector) {
    if (selector === '[data-private-site-auth-state]') {
      return { dataset: { authenticatedIdentityId: 'openpgp:' + fingerprint } };
    }
    return null;
  }
};
global.fetch = async function() { fetchCount += 1; throw new Error('fetch must not run'); };

vm.runInThisContext(source);
window.PrivateSiteAuth.authenticate()
  .then((result) => process.stdout.write(JSON.stringify({ result, fetchCount })))
  .catch((error) => {
    process.stderr.write(error.stack || String(error));
    process.exit(1);
  });
NODE;

        $result = $this->runScript($script);

        assertSame('authenticated', $result['result']['status']);
        assertSame(0, $result['fetchCount']);
    }

    public function testAuthenticationFailureIsVisible(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';
const status = { hidden: true, textContent: '', dataset: {} };
const errors = [];

global.window = {
  localStorage: {
    getItem(key) {
      if (key === 'forum_pki_public_key') return 'public-key';
      if (key === 'forum_pki_private_key') return 'private-key';
      if (key === 'forum_pki_fingerprint') return fingerprint;
      return '';
    }
  },
  __forumOpenPgpLoader: { ready: Promise.resolve({}) },
  openpgp: {},
  location: { assign(){}, reload(){} }
};
global.document = {
  addEventListener(){},
  querySelector(selector) {
    if (selector === '[data-private-site-auth-state]') return { dataset: { authenticatedIdentityId: '' } };
    if (selector === '[data-role="private-site-auth-status"]') return status;
    return null;
  }
};
global.console = { error() { errors.push(Array.from(arguments).map(String).join(' ')); } };
global.fetch = async function() {
  return { ok: false, async text() { return 'error=Challenge unavailable.\n'; } };
};

vm.runInThisContext(source);
window.PrivateSiteAuth.authenticate()
  .catch(() => {
    process.stdout.write(JSON.stringify({ status, errors }));
  });
NODE;

        $result = $this->runScript($script);

        assertSame(false, $result['status']['hidden']);
        assertSame('Challenge unavailable.', $result['status']['textContent']);
        assertSame('error', $result['status']['dataset']['kind']);
        assertSame(1, count($result['errors']));
    }
}
