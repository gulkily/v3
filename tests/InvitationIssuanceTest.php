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
const destination = { disabled: false, value: '', addEventListener() {} };
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

    public function testInviteTemplateProvidesAnEditableDropdownHost(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/pages/invites.php');

        assertStringContains('data-role="invitation-destination-fields" hidden', $template);
        assertStringContains('list="invite-destination-suggestions"', $template);
        assertStringContains('<datalist id="invite-destination-suggestions"', $template);
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
