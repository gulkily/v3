const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup(changeDuringSigning = false) {
  const fingerprint = 'a'.repeat(40);
  const storage = new Map([['forum_pki_public_key', 'public-A'], ['forum_pki_private_key', 'private-A']]);
  const localStorage = { getItem: name => storage.get(name) || null, setItem: (name, value) => storage.set(name, value) };
  let signed = 0;
  const openpgp = {
    readKey: async () => ({ getFingerprint: () => fingerprint }),
    readPrivateKey: async () => ({ getFingerprint: () => fingerprint }),
    createMessage: async ({text}) => text,
    sign: async () => { signed++; if (changeDuringSigning) storage.set('forum_pki_private_key', 'private-B'); return 'signature'; }
  };
  const window = { localStorage, openpgp };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/browser_signing.js'), 'utf8'), {
    window, localStorage, openpgp, document: { addEventListener() {} }
  });
  return { window, signed: () => signed, fingerprint };
}
(async () => {
  const wrong = setup();
  await assert.rejects(wrong.window.ForumBrowserSigning.signCanonicalRecord('record', 'openpgp:' + 'b'.repeat(40)), e => e.code === 'identity_changed');
  assert.equal(wrong.signed(), 0);
  const raced = setup(true);
  await assert.rejects(raced.window.ForumBrowserSigning.signCanonicalRecord('record', 'openpgp:' + raced.fingerprint), e => e.code === 'identity_changed');
  assert.equal(raced.signed(), 1);
  const stable = setup();
  assert.equal(await stable.window.ForumBrowserSigning.signCanonicalRecord('record', 'openpgp:' + stable.fingerprint), 'signature\n');
  assert.equal(await stable.window.ForumBrowserSigning.signCanonicalRecord('record'), 'signature\n', 'existing callers retain their contract');
  console.log('PASS expected signer binding before and after asynchronous signing; existing callers unchanged');
})().catch(error => { console.error(error); process.exitCode = 1; });
