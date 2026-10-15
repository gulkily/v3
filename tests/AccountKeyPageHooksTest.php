<?php

declare(strict_types=1);

final class AccountKeyPageHooksTest
{
    /** Hooks that browser_signing.js, the page script, and browser tests rely on. */
    private const HOOKS = [
        'data-account-key-root',
        'data-private-site-auth-state',
        'data-authenticated-identity-id=',
        'data-profile-link-authorized=',
        'data-action="generate-browser-key"',
        'data-action="clear-browser-identity"',
        'data-action="load-browser-key"',
        'data-action="restore-private-key"',
        'data-action="copy-public-key"',
        'data-action="copy-private-key"',
        'data-action="clear-browser-key"',
        'data-action="undo-clear-browser-key"',
        'data-role="username-field"',
        'data-role="simple-status-dot"',
        'data-role="simple-status-badge"',
        'data-role="profile-link"',
        'data-role="profile-link-wrap"',
        'data-role="private-site-auth-status"',
        'data-role="browser-key-status"',
        'data-role="browser-key-status-message"',
        'data-role="automatic-guest-publication"',
        'data-role="identity-id-field"',
        'data-role="public-key-field"',
        'data-role="public-key-viewer"',
        'data-role="private-key-viewer"',
        'data-copy-source="public"',
        'data-copy-source="private"',
        'data-key-backup',
        'data-backup-tab="backup"',
        'data-backup-tab="restore"',
        'data-backup-panel="backup"',
        'data-backup-panel="restore"',
        'data-backup-password',
        'data-backup-confirm',
        'data-backup-download="text"',
        'data-backup-download="png"',
        'data-backup-file',
        'data-restore-password',
        'data-backup-restore',
        'data-backup-status',
        'id="key-backup"',
        'id="key-backup-tab"',
        'id="key-restore-tab"',
        'id="key-backup-panel"',
        'id="key-restore-panel"',
        'id="simple-status"',
        'id="saved-public-key-viewer"',
        'id="saved-private-key-viewer"',
        'name="public_key"',
    ];

    public function testTemplateKeepsScriptAndTestHooks(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/pages/account_key.php');
        foreach (self::HOOKS as $hook) {
            assertStringContains($hook, $template);
        }
    }

    public function testSigningScriptStillTargetsPageHooks(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/browser_signing.js');
        foreach ([
            '[data-role="username-field"]',
            '[data-role="private-key-viewer"]',
            '[data-role="public-key-viewer"]',
            '[data-action="generate-browser-key"]',
            '[data-action="clear-browser-key"]',
            '[data-action="undo-clear-browser-key"]',
            '[data-role="browser-key-status"]',
        ] as $selector) {
            assertStringContains($selector, $script);
        }
    }
}
