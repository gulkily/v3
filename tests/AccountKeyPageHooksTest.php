<?php

declare(strict_types=1);

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

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
        'data-action="remove-key"',
        'data-action="undo-remove"',
        'data-role="remove-dialog"',
        'data-action="undo-clear-browser-key"',
        'data-role="username-field"',
        'data-role="simple-status-badge"',
        'data-role="profile-link"',
        'data-role="profile-link-wrap"',
        'data-role="private-site-auth-status"',
        'data-role="browser-key-status"',
        'data-role="browser-key-status-message"',
        'data-role="identity-id-field"',
        'data-role="public-key-field"',
        'data-role="public-key-viewer"',
        'data-role="private-key-viewer"',
        'data-copy-source="public"',
        'data-copy-source="private"',
        'data-key-backup',
        'data-backup-panel="backup"',
        'data-backup-panel="restore"',
        'data-backup-download',
        'data-backup-format',
        'data-backup-open="backup"',
        'data-backup-open="restore"',
        'data-action="retry-account-action"',
        'data-role="name-editor"',
        'data-role="account-name-input"',
        'data-backup-password',
        'data-backup-confirm',
        'data-backup-file',
        'data-restore-password',
        'data-backup-restore',
        'data-backup-status',
        'id="key-backup"',
        'id="simple-status"',
        'id="saved-public-key-viewer"',
        'id="saved-private-key-viewer"',
        'name="public_key"',
        'data-role="fingerprint-field"',
        'data-action="copy-fingerprint"',
    ];

    public function testTemplateKeepsScriptAndTestHooks(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/pages/account_key.php');
        foreach (self::HOOKS as $hook) {
            assertStringContains($hook, $template);
        }
    }

    public function testChooseUsernameButtonIsHiddenUnlessUsernameChangesAreEnabled(): void
    {
        foreach ([false, true] as $enabled) {
            $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates', 'test', new FeatureFlagEvaluator(
                new FeatureFlagRegistry(),
                [FeatureFlagRegistry::USERNAME_CHANGES_ENABLED => $enabled]
            ));
            $html = $renderer->renderPageTemplate('account_key.php', ['notice' => null, 'error' => null], 'Account', 'account');
            preg_match('/<button\b[^>]*data-action="rename-profile"[^>]*>/', $html, $button);
            assertTrue($button !== []);
            assertSame(!$enabled, str_contains($button[0], ' hidden'));
            assertStringContains('data-username-changes-enabled="' . ($enabled ? '1' : '0') . '"', $html);
        }
        $default = (new TemplateRenderer(dirname(__DIR__) . '/templates'))
            ->renderPageTemplate('account_key.php', ['notice' => null, 'error' => null], 'Account', 'account');
        preg_match('/<button\b[^>]*data-action="rename-profile"[^>]*>/', $default, $button);
        assertStringContains(' hidden', $button[0]);
    }

    public function testPageChoosesItsStateBeforeFirstPaint(): void
    {
        $html = (new TemplateRenderer(dirname(__DIR__) . '/templates'))
            ->renderPageTemplate('account_key.php', ['notice' => null, 'error' => null], 'Account', 'account');
        foreach (['data-account-state="guest"', 'data-account-card="guest"', 'data-account-card="member"', 'data-account-when="waiting"', 'data-account-when="approved"', 'data-viewer-identity-id=""', 'data-viewer-approved="0"', 'data-sky="account"', 'data-starfield-slot', 'class="account-card account-card--member night"', 'data-tone="neutral"'] as $hook) {
            assertStringContains($hook, $html);
        }
        // The state script runs as soon as it is parsed, after the page markup, not on DOMContentLoaded.
        $script = substr($html, (int) strpos($html, '</section>'));
        assertStringContains("    syncSimpleUI();\n    document.addEventListener('DOMContentLoaded'", $script);
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

    public function testEveryBackupScriptHookExistsOnPage(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/pages/account_key.php');
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/key_backup_ui.js');
        preg_match_all('/\[(data-[a-z-]+)(?:="([^"]+)")?\]/', $script, $matches, PREG_SET_ORDER);
        assertTrue($matches !== []);
        foreach ($matches as $match) {
            $hook = $match[2] ?? '' ? $match[1] . '="' . $match[2] . '"' : $match[1];
            assertStringContains($hook, $template);
        }
    }

    /** Hooks retired by ui_account_states: the backup tabs became one dialog, and one Remove replaced two clear buttons. */
    private const RETIRED_HOOKS = [
        'data-backup-tab=', 'data-backup-download="text"', 'data-backup-download="png"',
        'id="key-backup-tab"', 'id="key-restore-tab"', 'id="key-backup-panel"', 'id="key-restore-panel"',
        // One Remove this key (clear-browser-identity, behind a confirmation) replaces the duplicate Clear Saved Keypair.
        'data-action="clear-browser-key"',
    ];

    public function testNoHookIsRetiredWithoutBeingListed(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/pages/account_key.php');
        foreach (self::RETIRED_HOOKS as $hook) {
            assertSame(false, str_contains($template, $hook), $hook);
        }
    }
}
