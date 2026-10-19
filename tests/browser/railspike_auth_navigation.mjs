import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/assets/railspike_workflow.js', import.meta.url), 'utf8');

for (const status of ['pending', 'approved']) {
  let initialize;
  let reloads = 0;
  let authenticationCalls = 0;
  const requests = [];
  const mount = {
    dataset: { railspikeThread: 'thread-test' },
    replaceChildren() {},
  };
  const context = {
    window: {
      PrivateSiteAuth: {
        async authenticate(options) {
          authenticationCalls++;
          // Both successful outcomes navigate by default in PrivateSiteAuth.
          if (options?.navigate !== false) reloads++;
          return { status };
        },
      },
    },
    document: {
      hidden: false,
      querySelectorAll: () => [mount],
      querySelector: () => null,
      addEventListener(type, listener) {
        if (type === 'DOMContentLoaded') initialize = listener;
      },
    },
    localStorage: { getItem: () => 'saved-private-key' },
    async fetch(url) {
      requests.push(url);
      return { status: url === '/api/auth_status' ? 401 : 403, ok: false };
    },
    setInterval() {},
  };
  vm.runInNewContext(source, context);
  await initialize();
  assert.equal(authenticationCalls, 1);
  assert.equal(reloads, 0, `${status} background authentication must not reload`);
  assert.deepEqual(requests, ['/api/auth_status', '/api/railspike_workflow?thread_id=thread-test']);
  assert.equal(mount.hidden, true, 'Unavailable workflow controls stay hidden');
}
console.log('Railspike background authentication navigation checks passed.');
