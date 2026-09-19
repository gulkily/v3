<?php

declare(strict_types=1);

final class AuthNavigationTest
{
    /** @return array<string, mixed> */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/auth_navigation.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testExpiredSessionAuthenticatesBeforeNavigating(): void
    {
        $result = $this->runScript(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
let clickListener = null;
const fetches = [];
const authOptions = [];
const assigned = [];
const link = { href: 'https://forum.test/threads/root-001?view=full#reply-2', target: '', hasAttribute() { return false; } };
const event = {
  defaultPrevented: false, button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false,
  target: { closest() { return link; } },
  preventDefault() { this.defaultPrevented = true; }
};
global.window = {
  location: {
    href: 'https://forum.test/', origin: 'https://forum.test', pathname: '/', search: '',
    assign(url) { assigned.push(url); }
  },
  PrivateSiteAuth: {
    async authenticate(options) {
      authOptions.push(options);
      window.location.assign(options.returnTo);
      return { status: 'approved' };
    }
  }
};
global.document = { addEventListener(type, listener) { if (type === 'click') clickListener = listener; } };
global.fetch = async function(url) { fetches.push(String(url)); return { ok: false }; };
vm.runInThisContext(source);
clickListener(event);
setTimeout(() => process.stdout.write(JSON.stringify({ prevented: event.defaultPrevented, fetches, authOptions, assigned })), 0);
NODE);

        assertSame(true, $result['prevented']);
        assertSame(['/api/auth_status'], $result['fetches']);
        assertSame('/threads/root-001?view=full#reply-2', $result['authOptions'][0]['returnTo']);
        assertSame(['/threads/root-001?view=full#reply-2'], $result['assigned']);
    }

    public function testActiveSessionNavigatesWithoutReauthentication(): void
    {
        $result = $this->runScript(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
let clickListener = null;
let authenticationCalls = 0;
const assigned = [];
const link = { href: 'https://forum.test/about/', target: '', hasAttribute() { return false; } };
const event = {
  defaultPrevented: false, button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false,
  target: { closest() { return link; } },
  preventDefault() { this.defaultPrevented = true; }
};
global.window = {
  location: {
    href: 'https://forum.test/', origin: 'https://forum.test', pathname: '/', search: '',
    assign(url) { assigned.push(url); }
  },
  PrivateSiteAuth: { async authenticate() { authenticationCalls += 1; return { status: 'approved' }; } }
};
global.document = { addEventListener(type, listener) { if (type === 'click') clickListener = listener; } };
global.fetch = async function() { return { ok: true }; };
vm.runInThisContext(source);
clickListener(event);
setTimeout(() => process.stdout.write(JSON.stringify({ prevented: event.defaultPrevented, authenticationCalls, assigned })), 0);
NODE);

        assertSame(true, $result['prevented']);
        assertSame(0, $result['authenticationCalls']);
        assertSame(['/about/'], $result['assigned']);
    }

    public function testInviteNavigationIsLeftToItsDedicatedHandler(): void
    {
        $result = $this->runScript(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
let clickListener = null;
let fetchCount = 0;
const link = {
  href: 'https://forum.test/invites/', target: '',
  hasAttribute(name) { return name === 'data-invite-navigation'; }
};
const event = {
  defaultPrevented: false, button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false,
  target: { closest() { return link; } },
  preventDefault() { this.defaultPrevented = true; }
};
global.window = {
  location: { href: 'https://forum.test/', origin: 'https://forum.test', pathname: '/', search: '', assign() {} }
};
global.document = { addEventListener(type, listener) { if (type === 'click') clickListener = listener; } };
global.fetch = async function() { fetchCount += 1; return { ok: true }; };
vm.runInThisContext(source);
clickListener(event);
setTimeout(() => process.stdout.write(JSON.stringify({ prevented: event.defaultPrevented, fetchCount })), 0);
NODE);

        assertSame(false, $result['prevented']);
        assertSame(0, $result['fetchCount']);
    }
}
