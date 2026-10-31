const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/image_upload.js', 'utf8');
const identity = 'openpgp:' + 'a'.repeat(40);
function setup({allowed = true, online = true, secure = true, networkError = false} = {}) {
  let release, requests = 0, currentAllowed = allowed;
  const form = {__imageIdentity: async () => identity, querySelector: () => null, dispatchEvent() {}};
  const window = {isSecureContext: secure, crypto: {subtle: {}}, addEventListener() {}};
  vm.runInNewContext(source, {window, document: {body: {dataset: {imageContentState: '1'}}},
    navigator: {onLine: online}, CustomEvent: class {}, AbortController, setTimeout, clearTimeout,
    fetch: async () => {
      const answer = currentAllowed;
      requests++;
      if (networkError) throw Error('offline');
      if (requests === 1 && allowed === 'deferred') await new Promise(resolve => { release = resolve; });
      return {ok: true, text: async () => `Identity-ID: ${identity}\nApproved: yes\nImage-Upload-Allowed: ${answer ? 'yes' : 'no'}\n`};
    }});
  return {api: window.ForumImageUpload, form, get requests() {return requests;},
    async firstRequest() {while (!release) await new Promise(resolve => setTimeout(resolve, 1));},
    release() {release();}, permission(value) {currentAllowed = value;}};
}
(async () => {
  for (const latestAllowed of [true, false]) {
    const test = setup({allowed: 'deferred'});
    const outcome = test.api.assertEligible(test.form).then(() => true, () => false);
    await test.firstRequest();
    test.permission(latestAllowed);
    await test.api.refreshEligibility(test.form);
    assert.equal(test.form.__imageEligible, latestAllowed);
    test.release();
    assert.equal(await outcome, latestAllowed, 'superseded approval must neither falsely reject nor bypass later denial');
    assert.equal(test.requests, 3, 'recheck the server instead of trusting the cached flag');
  }
  for (const options of [{allowed: true}, {allowed: false}, {online: false}, {secure: false}, {networkError: true}]) {
    const test = setup(options);
    const outcome = await test.api.assertEligible(test.form).then(() => true, () => false);
    assert.equal(outcome, options.allowed === true);
    assert.equal(test.requests, options.online === false || options.secure === false ? 0 : 1, 'real denial must not retry indefinitely');
  }
  console.log('PASS image eligibility: overlapping refresh approval/denial, authoritative recheck, offline/insecure/network denial');
})().catch(error => {console.error(error); process.exitCode = 1;});
