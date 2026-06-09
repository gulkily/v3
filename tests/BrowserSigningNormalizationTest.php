<?php

declare(strict_types=1);

final class BrowserSigningNormalizationTest
{
    /**
     * @return array<string, mixed>
     */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/browser_signing.js'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function runHelper(string $text, bool $removeUnsupported = false): array
    {
        $script = <<<'NODE'
global.window = {};
global.localStorage = { getItem(){ return ''; }, setItem(){}, removeItem(){} };
global.document = {
  addEventListener(){},
  querySelector(){ return null; },
  createElement(){ return { setAttribute(){}, style:{}, select(){}, value:'' }; },
  body: { appendChild(){}, removeChild(){} },
};
global.navigator = {};
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const helper = window.__forumComposeNormalization.normalizeComposeAscii;
process.stdout.write(JSON.stringify(helper(process.argv[2], { removeUnsupported: process.argv[3] === '1' })));
NODE;

        $command = sprintf(
            'node -e %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/browser_signing.js'),
            escapeshellarg($text),
            escapeshellarg($removeUnsupported ? '1' : '0'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function runTextHelper(string $text, bool $allowUnicodeAuthoredText, bool $removeUnsupported = false): array
    {
        $script = <<<'NODE'
global.window = {};
global.localStorage = { getItem(){ return ''; }, setItem(){}, removeItem(){} };
global.document = {
  addEventListener(){},
  querySelector(){ return null; },
  createElement(){ return { setAttribute(){}, style:{}, select(){}, value:'' }; },
  body: { appendChild(){}, removeChild(){} },
};
global.navigator = {};
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const helper = window.__forumComposeNormalization.normalizeComposeText;
process.stdout.write(JSON.stringify(helper(process.argv[2], {
  allowUnicodeAuthoredText: process.argv[3] === '1',
  removeUnsupported: process.argv[4] === '1'
})));
NODE;

        $command = sprintf(
            'node -e %s %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/browser_signing.js'),
            escapeshellarg($text),
            escapeshellarg($allowUnicodeAuthoredText ? '1' : '0'),
            escapeshellarg($removeUnsupported ? '1' : '0'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function runThreadReactionScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/thread_reactions.js'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testNormalizeComposeAsciiRewritesCommonPunctuation(): void
    {
        $result = $this->runHelper('“hello”… — test');

        assertSame('"hello"... - test', $result['text']);
        assertSame(true, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiTransliteratesApprovedLatinDiacritics(): void
    {
        $result = $this->runHelper('Café déjà vu. François ate smörgåsbord.');

        assertSame('Cafe deja vu. Francois ate smorgasbord.', $result['text']);
        assertSame(true, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiTransliteratesUppercaseAndLigatures(): void
    {
        $result = $this->runHelper('Æsir and Œuvre in STRAẞE ŁÓDŹ');

        assertSame('AEsir and OEuvre in STRASSE LODZ', $result['text']);
        assertSame(true, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiKeepsUnsupportedNonLatinCharactersVisible(): void
    {
        $result = $this->runHelper('Café Привет');

        assertSame('Cafe Привет', $result['text']);
        assertSame(true, $result['hadCorrections']);
        assertSame(6, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeTextPreservesReadableUnicodeWhenEnabled(): void
    {
        $result = $this->runTextHelper('Café Привет', true);

        assertSame('Café Привет', $result['text']);
        assertSame(false, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeTextStillRejectsUnsafeUnicodeWhenEnabled(): void
    {
        $result = $this->runTextHelper("Привет 🙂\u{200B}\u{202E}\u{E000}", true, true);

        assertSame('Привет ', $result['text']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(4, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiReportsUnsupportedCharacters(): void
    {
        $result = $this->runHelper('emoji 🙂 test');

        assertSame('emoji 🙂 test', $result['text']);
        assertSame(false, $result['hadCorrections']);
        assertSame(1, $result['unsupportedCount']);
        assertSame(0, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiCanRemoveUnsupportedCharacters(): void
    {
        $result = $this->runHelper('emoji 🙂 test', true);

        assertSame('emoji  test', $result['text']);
        assertSame(false, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(1, $result['removedUnsupportedCount']);
    }

    public function testNormalizeComposeAsciiCanCombinePunctuationTransliterationAndRemoval(): void
    {
        $result = $this->runHelper('“Café”… Привет', true);

        assertSame('"Cafe"... ', $result['text']);
        assertSame(true, $result['hadCorrections']);
        assertSame(0, $result['unsupportedCount']);
        assertSame(6, $result['removedUnsupportedCount']);
    }

    public function testIdentityBootstrapFailureClassificationPreservesTechnicalDetailsBehindFriendlyMessage(): void
    {
        $script = <<<'NODE'
global.window = {};
global.localStorage = { getItem(){ return ''; }, setItem(){}, removeItem(){} };
global.document = {
  addEventListener(){},
  querySelector(){ return null; },
  createElement(){ return { setAttribute(){}, style:{}, select(){}, value:'', addEventListener(){}, appendChild(){} }; },
  createTextNode(text){ return { textContent: text }; },
  body: { appendChild(){}, removeChild(){} },
};
global.navigator = {};
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const helper = window.__forumBrowserIdentity.classifyIdentityBootstrapFailure;
process.stdout.write(JSON.stringify(helper('Unable to commit canonical write: fatal: not a git repository')));
NODE;

        $result = $this->runScript($script);

        assertSame(
            'This forum could not save your browser identity automatically. Open /account/key/ to finish manually.',
            $result['friendlyMessage']
        );
        assertSame(
            'Unable to commit canonical write: fatal: not a git repository',
            $result['technicalDetails']
        );
    }

    public function testInsecureOpenPgpLoaderFailureReportsAnonymousAndHttpsOptions(): void
    {
        $script = <<<'NODE'
global.window = {
  isSecureContext: false,
  prompt() { return 'guest'; },
  confirm() { return true; }
};
const rejectedLoader = Promise.reject(new Error('legacy bundle unavailable'));
rejectedLoader.catch(() => {});
global.window.__forumOpenPgpLoader = {
  selectedVersion: 'v5',
  selectedPath: '/assets/openpgp.v5.11.3.min.js',
  ready: rejectedLoader
};
global.localStorage = {
  getItem(){ return ''; },
  setItem(){},
  removeItem(){}
};
global.document = {
  addEventListener(){},
  querySelector(){ return null; },
  createElement(){ return { setAttribute(){}, style:{}, select(){}, value:'', addEventListener(){}, appendChild(){} }; },
  createTextNode(text){ return { textContent: text }; },
  body: { appendChild(){}, removeChild(){} },
};
global.navigator = {};
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const root = { querySelector(){ return null; } };
const statusNode = { dataset: {}, textContent: '', querySelector(){ return null; } };

window.__forumBrowserIdentity.ensureReadyIdentity(root, statusNode).then(() => {
  process.stderr.write('expected failure');
  process.exit(1);
}).catch((error) => {
  const status = window.__forumBrowserIdentity.statusFromError(error, 'fallback');
  process.stdout.write(JSON.stringify(status));
});
NODE;

        $result = $this->runScript($script);

        assertSame(
            'Browser OpenPGP is unavailable on this HTTP page. You can post anonymously or switch to HTTPS for browser identity.',
            $result['message']
        );
        assertStringContains('/assets/openpgp.v5.11.3.min.js', $result['technicalDetails']);
        assertStringContains('legacy bundle unavailable', $result['technicalDetails']);
    }

    public function testAccountClearIdentityButtonClearsSavedBrowserIdentity(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = {
  localStore: {
    forum_pki_username: 'forum-user',
    forum_pki_public_key: '-----BEGIN PGP PUBLIC KEY BLOCK-----\nfixture\n-----END PGP PUBLIC KEY BLOCK-----',
    forum_pki_private_key: '-----BEGIN PGP PRIVATE KEY BLOCK-----\nfixture\n-----END PGP PRIVATE KEY BLOCK-----',
    forum_pki_fingerprint: '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954',
    forum_pki_published_fingerprint: '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954',
    forum_pki_compose_prompt_cancelled: '1'
  },
  removeCalls: [],
  fetches: [],
  domContentLoaded: null,
  clearIdentityClickHandler: null
};

function makeElement(text) {
  return {
    textContent: text || '',
    value: '',
    href: '',
    hidden: false,
    dataset: {},
    style: {},
    setAttribute() {},
    addEventListener() {},
    appendChild() {},
    remove() {},
    querySelector() { return null; }
  };
}

const statusMessage = makeElement('Ready.');
const statusNode = Object.assign(makeElement('Ready.'), {
  querySelector(selector) {
    if (selector === '[data-role="browser-key-status-message"]') {
      return statusMessage;
    }
    return null;
  }
});
const usernameField = makeElement('');
const publicKeyField = makeElement('');
const publicKeyViewer = makeElement('');
const privateKeyViewer = makeElement('');
const identityIdField = makeElement('');
const profileLink = makeElement('');
const profileLinkWrap = makeElement('');
const undoLink = makeElement('');
const clearIdentityButton = {
  addEventListener(type, handler) {
    if (type === 'click') {
      state.clearIdentityClickHandler = handler;
    }
  }
};

const root = {
  querySelector(selector) {
    if (selector === '[data-role="browser-key-status"]') return statusNode;
    if (selector === '[data-role="public-key-field"]') return publicKeyField;
    if (selector === '[data-action="generate-browser-key"]') return null;
    if (selector === '[data-action="load-browser-key"]') return null;
    if (selector === '[data-action="clear-browser-key"]') return null;
    if (selector === '[data-action="clear-browser-identity"]') return clearIdentityButton;
    if (selector === '[data-action="undo-clear-browser-key"]') return undoLink;
    if (selector === '[data-action="copy-public-key"]') return null;
    if (selector === '[data-action="copy-private-key"]') return null;
    if (selector === '[data-role="username-field"]') return usernameField;
    if (selector === '[data-role="private-key-viewer"]') return privateKeyViewer;
    if (selector === '[data-role="public-key-viewer"]') return publicKeyViewer;
    if (selector === '[data-role="identity-id-field"]') return identityIdField;
    if (selector === '[data-role="profile-link"]') return profileLink;
    if (selector === '[data-role="profile-link-wrap"]') return profileLinkWrap;
    return null;
  }
};

global.window = {
  addEventListener() {},
  localStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.localStore, key) ? state.localStore[key] : null; },
    setItem(key, value) { state.localStore[key] = String(value); },
    removeItem(key) {
      state.removeCalls.push(key);
      delete state.localStore[key];
    }
  }
};
global.localStorage = global.window.localStorage;
global.document = {
  addEventListener(type, handler) {
    if (type === 'DOMContentLoaded') {
      state.domContentLoaded = handler;
    }
  },
  querySelector(selector) {
    if (selector === '[data-account-key-root]') {
      return root;
    }
    return null;
  },
  createElement() { return makeElement(''); },
  createTextNode(text) { return makeElement(text); },
  body: { appendChild() {}, removeChild() {} }
};
global.navigator = {};
global.fetch = async function (url, options) {
  state.fetches.push({ url: String(url), method: options && options.method ? options.method : 'GET' });
  return { ok: true, status: 200, text: async () => 'identity_hint=guest\n' };
};

vm.runInThisContext(source);
state.domContentLoaded();

Promise.resolve(state.clearIdentityClickHandler()).then(() => {
  process.stdout.write(JSON.stringify({
    removeCalls: state.removeCalls,
    remainingKeys: Object.keys(state.localStore).sort(),
    fetches: state.fetches,
    username: usernameField.textContent,
    identityId: identityIdField.textContent,
    publicKeyViewer: publicKeyViewer.textContent,
    privateKeyViewer: privateKeyViewer.textContent,
    publicKeyField: publicKeyField.value,
    status: statusMessage.textContent
  }));
}).catch((error) => {
  process.stderr.write(error.stack || String(error));
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame([
            'forum_pki_username',
            'forum_pki_public_key',
            'forum_pki_private_key',
            'forum_pki_fingerprint',
            'forum_pki_published_fingerprint',
            'forum_pki_compose_prompt_cancelled',
        ], $result['removeCalls']);
        assertSame([], $result['remainingKeys']);
        assertStringContains('/api/set_identity_hint?identity_hint=guest', $result['fetches'][1]['url']);
        assertSame('POST', $result['fetches'][1]['method']);
        assertSame('guest', $result['username']);
        assertSame('none', $result['identityId']);
        assertSame('No browser public key saved yet.', $result['publicKeyViewer']);
        assertSame('No browser private key saved yet.', $result['privateKeyViewer']);
        assertSame('', $result['publicKeyField']);
        assertSame('Cleared the saved browser keypair from local storage.', $result['status']);
    }

    public function testReadyIdentityRepublishesWhenStoredPublishedFingerprintIsUnknownToServer(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = {
  localStore: {
    forum_pki_username: 'forum-user',
    forum_pki_public_key: '-----BEGIN PGP PUBLIC KEY BLOCK-----\nfixture\n-----END PGP PUBLIC KEY BLOCK-----',
    forum_pki_private_key: '-----BEGIN PGP PRIVATE KEY BLOCK-----\nfixture\n-----END PGP PRIVATE KEY BLOCK-----',
    forum_pki_fingerprint: '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954',
    forum_pki_published_fingerprint: '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954'
  },
  fetches: [],
  localSetCalls: []
};

global.window = {};
global.localStorage = {
  getItem(key) { return Object.prototype.hasOwnProperty.call(state.localStore, key) ? state.localStore[key] : null; },
  setItem(key, value) {
    state.localSetCalls.push([key, String(value)]);
    state.localStore[key] = String(value);
  },
  removeItem(key) { delete state.localStore[key]; }
};
global.document = {
  addEventListener(){},
  querySelector(){ return null; },
  createElement(){ return { setAttribute(){}, style:{}, select(){}, value:'', addEventListener(){}, appendChild(){} }; },
  createTextNode(text){ return { textContent: text }; },
  body: { appendChild(){}, removeChild(){} },
};
global.navigator = {};
global.fetch = async function (url, options) {
  state.fetches.push({ url: String(url), method: options && options.method ? options.method : 'GET' });
  if (String(url).indexOf('/api/get_profile?') === 0) {
    return { ok: false, status: 404, text: async () => 'profile not found\n' };
  }
  if (String(url) === '/api/link_identity') {
    return { ok: true, status: 200, text: async () => 'status=ok\nidentity_id=openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n' };
  }
  if (String(url).indexOf('/api/set_identity_hint?') === 0) {
    return { ok: true, status: 200, text: async () => 'identity_hint=openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n' };
  }

  throw new Error('Unexpected fetch: ' + url);
};

vm.runInThisContext(source);
const root = { querySelector(){ return null; } };
const statusNode = { dataset: {}, textContent: '', querySelector(){ return null; } };

window.__forumBrowserIdentity.ensureReadyIdentity(root, statusNode).then(() => {
  process.stdout.write(JSON.stringify({
    fetches: state.fetches,
    localSetCalls: state.localSetCalls,
    status: statusNode.textContent
  }));
}).catch((error) => {
  process.stderr.write(error.stack || String(error));
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame('/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $result['fetches'][0]['url']);
        assertSame('/api/link_identity', $result['fetches'][1]['url']);
        assertStringContains('/api/set_identity_hint?identity_hint=openpgp%3A0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $result['fetches'][2]['url']);
        assertSame(['forum_pki_published_fingerprint', '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954'], $result['localSetCalls'][0]);
        assertSame('Finishing browser identity setup...', $result['status']);
    }

    public function testSubmittedComposePageClearsDraftWithoutImmediatelySavingBlankReplacement(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = {
  localStore: {
    'forum_compose_draft:reply:root-001:root-001': '{"fields":{"board_tags":"saved","body":"saved body"}}'
  },
  localSetCalls: [],
  localRemoveCalls: [],
  sessionStore: {},
  domContentLoaded: null
};

function makeField(tagName, name, value, defaultValue, type) {
  return {
    tagName,
    name,
    value,
    defaultValue,
    dataset: {},
    hidden: false,
    disabled: false,
    getAttribute(attribute) {
      if (attribute === 'type') {
        return type || null;
      }
      return null;
    },
    addEventListener() {}
  };
}

const fields = [
  makeField('INPUT', 'thread_id', 'root-001', 'root-001', 'hidden'),
  makeField('INPUT', 'parent_id', 'root-001', 'root-001', 'hidden'),
  makeField('INPUT', 'author_identity_id', '', '', 'hidden'),
  makeField('INPUT', 'board_tags', '', 'general', 'hidden'),
  Object.assign(makeField('TEXTAREA', 'body', '', '', null), { dataset: { composeFieldLabel: 'Body' } })
];

const submitButton = { disabled: false };
const form = {
  dataset: { composeKind: 'reply' },
  querySelector(selector) {
    if (selector === 'input[name="thread_id"]') {
      return fields[0];
    }
    if (selector === 'input[name="parent_id"]') {
      return fields[1];
    }
    return null;
  },
  querySelectorAll(selector) {
    if (selector === 'input[name], textarea[name]') {
      return fields;
    }
    if (selector === 'button[type="submit"], input[type="submit"]') {
      return [submitButton];
    }
    return [];
  },
  addEventListener() {},
  submit() {},
  reset() {
    fields.forEach((field) => {
      field.value = field.defaultValue;
    });
  }
};

const statusNode = { dataset: {}, textContent: 'Ready.', hidden: false };
const root = {
  dataset: { composeSubmitted: '1' },
  querySelector(selector) {
    if (selector === '[data-compose-form]') {
      return form;
    }
    if (selector === '[data-role="compose-identity-status"]') {
      return statusNode;
    }
    return null;
  },
  querySelectorAll() {
    return [];
  }
};

global.window = {
  addEventListener() {},
  localStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.localStore, key) ? state.localStore[key] : null; },
    setItem(key, value) {
      state.localSetCalls.push([key, String(value)]);
      state.localStore[key] = String(value);
    },
    removeItem(key) {
      state.localRemoveCalls.push(key);
      delete state.localStore[key];
    }
  },
  sessionStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.sessionStore, key) ? state.sessionStore[key] : null; },
    setItem(key, value) { state.sessionStore[key] = String(value); },
    removeItem(key) { delete state.sessionStore[key]; }
  },
  __forumComposeNormalization: null
};

global.localStorage = global.window.localStorage;
global.sessionStorage = global.window.sessionStorage;
global.document = {
  addEventListener(type, handler) {
    if (type === 'DOMContentLoaded') {
      state.domContentLoaded = handler;
    }
  },
  querySelector(selector) {
    if (selector === '[data-account-key-root]') {
      return null;
    }
    if (selector === '[data-compose-root]') {
      return root;
    }
    return null;
  },
  createElement() { return { setAttribute() {}, style: {}, select() {}, value: '' }; },
  body: { appendChild() {}, removeChild() {} }
};
global.navigator = {};

vm.runInThisContext(source);
state.domContentLoaded();

process.stdout.write(JSON.stringify({
  removeCalls: state.localRemoveCalls,
  setCalls: state.localSetCalls,
  boardTags: fields[3].value,
  body: fields[4].value
}));
NODE;

        $result = $this->runScript($script);

        assertSame(['forum_compose_draft:reply:root-001:root-001'], $result['removeCalls']);
        assertSame([], $result['setCalls']);
        assertSame('general', $result['boardTags']);
        assertSame('', $result['body']);
    }

    public function testComposeQueryPrefillOverridesSavedDraft(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = {
  localStore: {
    'forum_compose_draft:thread': '{"fields":{"board_tags":"general","subject":"","body":""}}'
  },
  domContentLoaded: null
};

function makeField(tagName, name, value, type) {
  return {
    tagName,
    name,
    value,
    defaultValue: value,
    dataset: {},
    hidden: false,
    disabled: false,
    getAttribute(attribute) {
      if (attribute === 'type') {
        return type || null;
      }
      return null;
    },
    addEventListener() {}
  };
}

const fields = [
  makeField('INPUT', 'author_identity_id', '', 'hidden'),
  makeField('INPUT', 'board_tags', 'general', 'text'),
  Object.assign(makeField('INPUT', 'subject', 'Luke 19 NIV - Zacchaeus the Tax Collector - Jesus - Bible Gateway', 'text'), { dataset: { composeFieldLabel: 'Subject' } }),
  Object.assign(makeField('TEXTAREA', 'body', '19 Jesus entered Jericho and was passing through.\n\nhttps://www.biblegateway.com/passage/?search=Luke%2019&version=NIV', null), { dataset: { composeFieldLabel: 'Body' } })
];

const form = {
  dataset: { composeKind: 'thread' },
  querySelector() {
    return null;
  },
  querySelectorAll(selector) {
    if (selector === 'input[name], textarea[name]') {
      return fields;
    }
    return [];
  },
  addEventListener() {},
  submit() {},
  reset() {
    fields.forEach((field) => {
      field.value = field.defaultValue;
    });
  }
};

const statusNode = { dataset: {}, textContent: 'Ready.', hidden: false };
const root = {
  dataset: {},
  querySelector(selector) {
    if (selector === '[data-compose-form]') {
      return form;
    }
    if (selector === '[data-role="compose-identity-status"]') {
      return statusNode;
    }
    return null;
  },
  querySelectorAll() {
    return [];
  }
};

global.window = {
  location: {
    search: '?board_tags=general&subject=Luke%2019%20NIV%20-%20Zacchaeus%20the%20Tax%20Collector%20-%20Jesus%20-%20Bible%20Gateway&body=19%20Jesus%20entered%20Jericho%20and%20was%20passing%20through.%0A%0Ahttps%3A%2F%2Fwww.biblegateway.com%2Fpassage%2F%3Fsearch%3DLuke%252019%26version%3DNIV'
  },
  addEventListener() {},
  localStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.localStore, key) ? state.localStore[key] : null; },
    setItem(key, value) { state.localStore[key] = String(value); },
    removeItem(key) { delete state.localStore[key]; }
  },
  sessionStorage: {
    getItem() { return null; },
    removeItem() {}
  }
};

global.localStorage = global.window.localStorage;
global.sessionStorage = global.window.sessionStorage;
global.document = {
  addEventListener(type, handler) {
    if (type === 'DOMContentLoaded') {
      state.domContentLoaded = handler;
    }
  },
  querySelector(selector) {
    if (selector === '[data-account-key-root]') {
      return null;
    }
    if (selector === '[data-compose-root]') {
      return root;
    }
    return null;
  },
  createElement() { return { setAttribute() {}, style: {}, select() {}, value: '' }; },
  body: { appendChild() {}, removeChild() {} }
};
global.navigator = {};

vm.runInThisContext(source);
state.domContentLoaded();

process.stdout.write(JSON.stringify({
  subject: fields[2].value,
  body: fields[3].value,
  savedDraft: JSON.parse(state.localStore['forum_compose_draft:thread'])
}));
NODE;

        $result = $this->runScript($script);

        assertSame('Luke 19 NIV - Zacchaeus the Tax Collector - Jesus - Bible Gateway', $result['subject']);
        assertStringContains('19 Jesus entered Jericho and was passing through.', $result['body']);
        assertSame($result['subject'], $result['savedDraft']['fields']['subject']);
        assertSame($result['body'], $result['savedDraft']['fields']['body']);
    }

    public function testClearComposeFieldsButtonClearsEditableFieldsAndSavedDraft(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = {
  localStore: {
    'forum_compose_draft:thread': '{"fields":{"board_tags":"general","subject":"Saved subject","body":"Saved body"}}'
  },
  localRemoveCalls: [],
  domContentLoaded: null,
  clearClickHandler: null
};

function makeField(tagName, name, value, type) {
  return {
    tagName,
    name,
    value,
    defaultValue: value,
    dataset: {},
    hidden: false,
    disabled: false,
    getAttribute(attribute) {
      if (attribute === 'type') {
        return type || null;
      }
      return null;
    },
    addEventListener() {}
  };
}

const fields = [
  makeField('INPUT', 'author_identity_id', '', 'hidden'),
  makeField('INPUT', 'board_tags', 'general', 'text'),
  Object.assign(makeField('INPUT', 'subject', 'Saved subject', 'text'), { dataset: { composeFieldLabel: 'Subject' } }),
  Object.assign(makeField('TEXTAREA', 'body', 'Saved body', null), { dataset: { composeFieldLabel: 'Body' } })
];

const clearButton = {
  addEventListener(type, handler) {
    if (type === 'click') {
      state.clearClickHandler = handler;
    }
  }
};

const form = {
  dataset: { composeKind: 'thread' },
  querySelector() {
    return null;
  },
  querySelectorAll(selector) {
    if (selector === 'input[name], textarea[name]') {
      return fields;
    }
    return [];
  },
  addEventListener() {},
  submit() {},
  reset() {}
};

const statusNode = { dataset: {}, textContent: 'Ready.', hidden: false };
const root = {
  dataset: {},
  querySelector(selector) {
    if (selector === '[data-compose-form]') {
      return form;
    }
    if (selector === '[data-role="compose-identity-status"]') {
      return statusNode;
    }
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '[data-action="clear-compose-fields"]') {
      return [clearButton];
    }
    return [];
  }
};

global.window = {
  addEventListener() {},
  localStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.localStore, key) ? state.localStore[key] : null; },
    setItem(key, value) { state.localStore[key] = String(value); },
    removeItem(key) {
      state.localRemoveCalls.push(key);
      delete state.localStore[key];
    }
  },
  sessionStorage: {
    getItem() { return null; },
    removeItem() {}
  }
};

global.localStorage = global.window.localStorage;
global.sessionStorage = global.window.sessionStorage;
global.document = {
  addEventListener(type, handler) {
    if (type === 'DOMContentLoaded') {
      state.domContentLoaded = handler;
    }
  },
  querySelector(selector) {
    if (selector === '[data-account-key-root]') {
      return null;
    }
    if (selector === '[data-compose-root]') {
      return root;
    }
    return null;
  },
  createElement() { return { setAttribute() {}, style: {}, select() {}, value: '' }; },
  body: { appendChild() {}, removeChild() {} }
};
global.navigator = {};

vm.runInThisContext(source);
state.domContentLoaded();
state.clearClickHandler();

process.stdout.write(JSON.stringify({
  boardTags: fields[1].value,
  subject: fields[2].value,
  body: fields[3].value,
  removeCalls: state.localRemoveCalls,
  savedDraftExists: Object.prototype.hasOwnProperty.call(state.localStore, 'forum_compose_draft:thread')
}));
NODE;

        $result = $this->runScript($script);

        assertSame('', $result['boardTags']);
        assertSame('', $result['subject']);
        assertSame('', $result['body']);
        assertSame(['forum_compose_draft:thread'], $result['removeCalls']);
        assertSame(false, $result['savedDraftExists']);
    }

    public function testThreadReactionBootstrapsIdentityBeforeApplyingLike(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const events = [];
let clickHandler = null;

class HTMLButtonElement {
  constructor() {
    this.disabled = false;
    this.textContent = 'Like';
    this.attributes = {};
  }
  getAttribute(name) {
    if (name === 'data-tag') return 'like';
    if (name === 'data-applied-label') return 'Liked';
    return this.attributes[name] || null;
  }
  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }
  closest(selector) {
    return selector === '[data-action="apply-thread-tag"]' ? this : null;
  }
}

const button = new HTMLButtonElement();
const scoreNode = { textContent: 'Score: 0' };
const feedbackNode = { textContent: '', hidden: true, setAttribute(name, value) { this[name] = value; } };
const root = {
  getAttribute(name) {
    return name === 'data-thread-id' ? 'root-001' : '';
  },
  querySelector(selector) {
    if (selector === '[data-role="thread-score"]') return scoreNode;
    if (selector === '[data-role="thread-reaction-feedback"]') return feedbackNode;
    return null;
  },
  addEventListener(type, handler) {
    if (type === 'click') {
      clickHandler = handler;
    }
  }
};

global.Element = HTMLButtonElement;
global.HTMLButtonElement = HTMLButtonElement;
global.window = {
  __forumBrowserIdentity: {
    async ensureReadyIdentity(receivedRoot, receivedFeedback) {
      events.push(receivedRoot === root ? 'ensure-root' : 'ensure-wrong-root');
      events.push(receivedFeedback === feedbackNode ? 'ensure-feedback' : 'ensure-wrong-feedback');
      events.push('ensure');
    }
  }
};
global.fetch = async function(url) {
  events.push(url);
  return {
    async text() {
      return 'status=ok\nscore_total=1\nwrote_record=yes\nviewer_is_approved=yes\n';
    }
  };
};
global.document = {
  addEventListener(type, handler) {
    if (type === 'DOMContentLoaded') {
      handler();
    }
  },
  querySelector(selector) {
    if (selector === '[data-thread-reactions-root]') {
      return root;
    }
    return null;
  }
};

vm.runInThisContext(source);
clickHandler({
  target: button,
  preventDefault() {}
}).then(() => {
  process.stdout.write(JSON.stringify({
    events,
    score: scoreNode.textContent,
    feedback: feedbackNode.textContent,
    feedbackHidden: feedbackNode.hidden,
    buttonDisabled: button.disabled,
    buttonText: button.textContent,
    ariaPressed: button.attributes['aria-pressed'] || ''
  }));
});
NODE;

        $result = $this->runThreadReactionScript($script);

        assertSame(['ensure-root', 'ensure-feedback', 'ensure', '/api/apply_thread_tag'], array_slice($result['events'], 0, 4));
        assertSame('Score: 1', $result['score']);
        assertSame('Liked.', $result['feedback']);
        assertSame(false, $result['feedbackHidden']);
        assertSame(true, $result['buttonDisabled']);
        assertSame('Liked', $result['buttonText']);
        assertSame('true', $result['ariaPressed']);
    }

    public function testThreadReactionShowsBootstrapFailureInlineAndSkipsLikeWrite(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
let clickHandler = null;
let fetchCount = 0;

class HTMLButtonElement {
  constructor() {
    this.disabled = false;
    this.textContent = 'Like';
    this.attributes = {};
  }
  getAttribute(name) {
    if (name === 'data-tag') return 'like';
    if (name === 'data-applied-label') return 'Liked';
    return this.attributes[name] || null;
  }
  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }
  closest(selector) {
    return selector === '[data-action="apply-thread-tag"]' ? this : null;
  }
}

const button = new HTMLButtonElement();
const feedbackNode = { textContent: '', hidden: true, setAttribute(name, value) { this[name] = value; } };
const root = {
  getAttribute(name) {
    return name === 'data-thread-id' ? 'root-001' : '';
  },
  querySelector(selector) {
    if (selector === '[data-role="thread-score"]') return null;
    if (selector === '[data-role="thread-reaction-feedback"]') return feedbackNode;
    return null;
  },
  addEventListener(type, handler) {
    if (type === 'click') {
      clickHandler = handler;
    }
  }
};

global.Element = HTMLButtonElement;
global.HTMLButtonElement = HTMLButtonElement;
global.window = {
  __forumBrowserIdentity: {
    async ensureReadyIdentity() {
      throw new Error('Posting paused until you choose a username.');
    }
  }
};
global.fetch = async function() {
  fetchCount += 1;
  throw new Error('fetch should not run');
};
global.document = {
  addEventListener(type, handler) {
    if (type === 'DOMContentLoaded') {
      handler();
    }
  },
  querySelector(selector) {
    if (selector === '[data-thread-reactions-root]') {
      return root;
    }
    return null;
  }
};

vm.runInThisContext(source);
clickHandler({
  target: button,
  preventDefault() {}
}).then(() => {
  process.stdout.write(JSON.stringify({
    fetchCount,
    feedback: feedbackNode.textContent,
    feedbackHidden: feedbackNode.hidden,
    buttonDisabled: button.disabled,
    buttonText: button.textContent
  }));
});
NODE;

        $result = $this->runThreadReactionScript($script);

        assertSame(0, $result['fetchCount']);
        assertSame('Posting paused until you choose a username.', $result['feedback']);
        assertSame(false, $result['feedbackHidden']);
        assertSame(false, $result['buttonDisabled']);
        assertSame('Like', $result['buttonText']);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
