<?php

declare(strict_types=1);

final class InvitationIssuanceTest
{
    /** @return array<string, mixed> */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/invite_issuance.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testDestinationFieldIsVisibleOnlyWhenIncluded(): void
    {
        $result = $this->runScript(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
let ready = null;
const listeners = {};
const checkbox = {
  checked: false,
  attributes: {},
  addEventListener(type, listener) { listeners[type] = listener; },
  setAttribute(name, value) { this.attributes[name] = value; }
};
const destination = { disabled: false, value: '', dataset: {}, addEventListener() {}, focus() {} };
const destinationFields = { hidden: false };
const link = { addEventListener() {}, select() {} };
const form = {
  elements: { include_destination: checkbox, destination },
  addEventListener() {},
  querySelector(selector) { return selector === '[data-role=invitation-destination-fields]' ? destinationFields : null; }
};
const root = {
  querySelector(selector) {
    if (selector === '[data-invitation-issue-form]') return form;
    if (selector === '[data-role=invitation-link]') return link;
    return null;
  }
};
global.window = { location: { origin: 'https://forum.test' } };
global.document = {
  addEventListener(type, listener) { if (type === 'DOMContentLoaded') ready = listener; },
  querySelector(selector) { return selector === '[data-invitation-page]' ? root : null; }
};
vm.runInThisContext(source);
ready();
const initial = { hidden: destinationFields.hidden, disabled: destination.disabled, expanded: checkbox.attributes['aria-expanded'] };
checkbox.checked = true;
listeners.change();
const checked = { hidden: destinationFields.hidden, disabled: destination.disabled, expanded: checkbox.attributes['aria-expanded'] };
checkbox.checked = false;
listeners.change();
const unchecked = { hidden: destinationFields.hidden, disabled: destination.disabled, expanded: checkbox.attributes['aria-expanded'] };
process.stdout.write(JSON.stringify({ initial, checked, unchecked }));
NODE);

        assertSame(['hidden' => true, 'disabled' => true, 'expanded' => 'false'], $result['initial']);
        assertSame(['hidden' => false, 'disabled' => false, 'expanded' => 'true'], $result['checked']);
        assertSame(['hidden' => true, 'disabled' => true, 'expanded' => 'false'], $result['unchecked']);
    }

    public function testInviteTemplateProvidesAnUnfilteredDestinationMenu(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/pages/invites.php');

        assertStringContains('data-role="invitation-destination-fields" hidden', $template);
        assertStringContains('class="invitation-destination-input"', $template);
        assertStringContains('value="" data-source-destination=', $template);
        assertStringContains('data-role="invitation-destination-menu"', $template);
        assertStringContains('data-role="invitation-destination-options"', $template);
        assertStringContains('data-destination-value="/">Board', $template);
        assertStringContains('data-destination-value="/activity/">Activity', $template);
        assertStringContains('data-destination-value="/users/">Users', $template);
        assertStringContains('data-destination-value="/tools/">Tools', $template);
        assertSame(2, substr_count($template, '<article class="card">'));
    }

    public function testInlineDestinationChooserUsesEqualInputInsets(): void
    {
        $styles = file_get_contents(__DIR__ . '/../public/assets/site.css');

        assertStringContains('inset: 0.35rem 0.35rem 0.35rem auto;', $styles);
        assertStringContains('.invitation-destination-input > input {', $styles);
        assertStringContains('height: 100%;', $styles);
    }

    public function testWord97InviteNavigationUsesAPersonAndPlusIcon(): void
    {
        $styles = file_get_contents(__DIR__ . '/../public/assets/theme-word97.css');

        assertStringContains(':root[data-theme="word97"] .nav-link[href="/invites/"]::before', $styles);
        assertStringContains("width='2' height='1' fill='%23000080'", $styles);
        assertStringContains("fill='%23008000'", $styles);
    }

    public function testDestinationSuggestionsIncludeCuratedAndValidSourceLocations(): void
    {
        $result = $this->runScript(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
function suggestionsFor(value) {
  let ready = null;
  const listeners = {};
  const checkbox = { checked: false, addEventListener(type, listener) { listeners[type] = listener; }, setAttribute() {} };
  const destination = { disabled: false, value: '', dataset: { sourceDestination: value }, addEventListener() {}, focus() { this.focused = true; } };
  const destinationFields = { hidden: false };
  const menu = { open: false };
  const optionListeners = {};
  const options = {
    values: ['/', '/activity/', '/users/', '/tools/'],
    querySelectorAll() { return this.values.map((item) => ({ dataset: { destinationValue: item } })); },
    appendChild(option) { this.values.push(option.dataset.destinationValue); },
    addEventListener(type, listener) { optionListeners[type] = listener; }
  };
  const link = { addEventListener() {}, select() {} };
  const form = {
    elements: { include_destination: checkbox, destination },
    addEventListener() {},
    querySelector(selector) {
      if (selector === '[data-role=invitation-destination-fields]') return destinationFields;
      if (selector === '[data-role=invitation-destination-menu]') return menu;
      if (selector === '[data-role=invitation-destination-options]') return options;
      return null;
    }
  };
  const root = {
    querySelector(selector) {
      if (selector === '[data-invitation-issue-form]') return form;
      if (selector === '[data-role=invitation-link]') return link;
      return null;
    }
  };
  const document = {
    addEventListener(type, listener) { if (type === 'DOMContentLoaded') ready = listener; },
    querySelector(selector) { return selector === '[data-invitation-page]' ? root : null; },
    createElement() { return { dataset: {} }; }
  };
  vm.runInNewContext(source, { window: { location: { origin: 'https://forum.test' } }, document, console });
  ready();
  menu.open = true;
  optionListeners.click({ target: { closest() { return { dataset: { destinationValue: '/users/' } }; } } });
  return { values: options.values, selected: destination.value, menuOpen: menu.open, focused: destination.focused === true };
}
process.stdout.write(JSON.stringify({ valid: suggestionsFor('/threads/root-001'), external: suggestionsFor('https://example.test/'), fragment: suggestionsFor('/threads/root-001#reply-1') }));
NODE);

        assertSame(['/', '/activity/', '/users/', '/tools/', '/threads/root-001'], $result['valid']['values']);
        assertSame(['/', '/activity/', '/users/', '/tools/'], $result['external']['values']);
        assertSame(['/', '/activity/', '/users/', '/tools/'], $result['fragment']['values']);
        assertSame('/users/', $result['valid']['selected']);
        assertSame(false, $result['valid']['menuOpen']);
        assertSame(true, $result['valid']['focused']);
    }

    public function testOnlySuccessfulInvitationsRememberValidLocalDestinations(): void
    {
        $result = $this->runScript(<<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
async function issue(finalStatus, storageUnavailable) {
  let ready = null;
  const listeners = {};
  const storage = {
    value: JSON.stringify(['/activity/', '/activity/', 'https://example.test/']), writes: 0,
    getItem() { if (storageUnavailable) throw new Error('unavailable'); return this.value; },
    setItem(key, value) { if (storageUnavailable) throw new Error('unavailable'); this.writes += 1; this.value = value; }
  };
  const checkbox = { checked: true, addEventListener(type, listener) { listeners[type] = listener; }, setAttribute() {} };
  const destination = { disabled: false, value: '/threads/new', dataset: {}, addEventListener() {}, focus() {} };
  const destinationFields = { hidden: false };
  const menu = { open: false };
  const options = { querySelectorAll() { return []; }, appendChild() {}, addEventListener() {} };
  const feedback = { hidden: true, textContent: '', dataset: {} };
  const result = { hidden: true };
  const link = { value: '', addEventListener() {}, select() {} };
  const form = {
    elements: { include_destination: checkbox, destination },
    addEventListener(type, listener) { listeners[type] = listener; },
    querySelector(selector) {
      if (selector === '[data-role=invitation-destination-fields]') return destinationFields;
      if (selector === '[data-role=invitation-destination-menu]') return menu;
      if (selector === '[data-role=invitation-destination-options]') return options;
      return null;
    }
  };
  const root = {
    querySelector(selector) {
      if (selector === '[data-invitation-issue-form]') return form;
      if (selector === '[data-role=invitation-feedback]') return feedback;
      if (selector === '[data-role=invitation-result]') return result;
      if (selector === '[data-role=invitation-link]') return link;
      return null;
    }
  };
  const document = {
    addEventListener(type, listener) { if (type === 'DOMContentLoaded') ready = listener; },
    querySelector(selector) { return selector === '[data-invitation-page]' ? root : null; },
    createElement() { return { dataset: {} }; }
  };
  const context = {
    window: {
      location: { origin: 'https://forum.test' }, localStorage: storage,
      ForumBrowserSigning: { async ensureActionIdentity() {}, async signCanonicalRecord() { return 'signature'; } }
    },
    document,
    crypto: { getRandomValues(bytes) { bytes.fill(1); }, subtle: { async digest() { return new Uint8Array(32).buffer; } } },
    TextEncoder,
    URLSearchParams,
    fetch: async function() {
      return { json: async function() {
        return finalStatus === 'prepare-error'
          ? { status: 'error', error: 'unable' }
          : { status: finalStatus, canonical_record: 'Author-Identity-ID: openpgp:test', prepare_token: 'token', post_id: 'post', record_path: 'path' };
      } };
    },
    console
  };
  vm.runInNewContext(source, context);
  ready();
  await listeners.submit({ preventDefault() {} });
  return { stored: storage.value, writes: storage.writes, resultHidden: result.hidden };
}
(async function() {
  process.stdout.write(JSON.stringify({ success: await issue('ok', false), failed: await issue('error', false), unavailable: await issue('ok', true) }));
}()).catch((error) => { console.error(error); process.exit(1); });
NODE);

        assertSame('["/threads/new","/activity/"]', $result['success']['stored']);
        assertSame(1, $result['success']['writes']);
        assertSame(false, $result['success']['resultHidden']);
        assertSame(0, $result['failed']['writes']);
        assertSame(true, $result['failed']['resultHidden']);
        assertSame(0, $result['unavailable']['writes']);
        assertSame(false, $result['unavailable']['resultHidden']);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException("Expected to find {$needle}.");
        }
    }
}
