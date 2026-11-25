<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use InvalidArgumentException;
use RuntimeException;

final class PrivateMessagePageController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $authenticatedViewerProfile
     * @param \Closure(): PrivateMessageStore $privateMessageStore
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $authenticatedViewerProfile,
        private readonly \Closure $privateMessageStore,
    ) {
    }

    public function inbox(string $method): void
    {
        $this->redirectMailbox($method);
    }

    public function conversations(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for Messages.', 'messages'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->accessPage($viewer);
            return;
        }
        try {
            $page = $this->service()->conversations($viewer);
        } catch (\ForumRewrite\Security\PublicOnlyKeyException) {
            $this->accessPage($viewer, true);
            return;
        } catch (RuntimeException $exception) {
            $this->messageError('Messages Unavailable', $exception->getMessage(), 403);
            return;
        }
        $waiting = $this->waitingKeys($viewer);
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_list.php',
            ['page' => $page, 'viewerProfile' => $viewer, 'waitingKeys' => $waiting,
                'accountName' => (string) ($viewer['username'] ?? $viewer['username_token'] ?? '')], 'Messages', 'messages', array_merge([
                '/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_messages.js', '/assets/key_backup_status.js',
                '/assets/private_message_cache.js', '/assets/private_message_reader.js', '/assets/message_time.js', '/assets/private_message_list.js',
            ], $waiting === [] ? [] : ['/assets/private_access_confirm.js'])), 200, $this->routeServices->noStoreHeaders());
    }

    /** The page a public-only device's QR opens: confirm that key from a device of its account. */
    public function confirmPage(string $method, string $fingerprint): void
    {
        if ($method !== 'GET') {
            $this->messageError('Method Not Allowed', 'Only GET is supported for this page.', 405);
            return;
        }
        $pdo = $this->routeServices->pdo();
        $target = preg_match('/^[a-f0-9]{40}$/D', $fingerprint) === 1
            ? \ForumRewrite\ReadModel\ProfileRepository::byIdentityId($pdo, 'openpgp:' . $fingerprint) : null;
        if ($target === null || (int) ($target['is_approved'] ?? 0) !== 1) {
            $this->messageError('Key Not Found', 'No approved key has this address.', 404);
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->accessPage($viewer);
            return;
        }
        $root = $this->routeServices->repositoryRoot();
        $access = new \ForumRewrite\Security\PrivateKeyAccess($pdo, $root);
        $viewerId = (string) $viewer['identity_id'];
        $state = match (true) {
            $viewerId === (string) $target['identity_id'] => 'self',
            $access->hasAccess((string) $target['identity_id']) => 'has_access',
            ($access->hasAccess($viewerId) && (string) $viewer['username_token'] === (string) $target['username_token'])
                || \ForumRewrite\Security\ApprovalPolicy::isActiveOperator($root, $viewerId) => 'confirm',
            default => 'not_allowed',
        };
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_access_confirm.php', [
            'state' => $state,
            'target' => $target,
            'accountName' => (string) ($target['username'] ?? $target['username_token'] ?? ''),
        ], 'Confirm a device', 'messages', $state === 'confirm'
            ? ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_access_confirm.js'] : []),
            200, $this->routeServices->noStoreHeaders());
    }

    public function sent(string $method): void
    {
        $this->redirectMailbox($method);
    }

    public function conversation(string $method, string $counterpartUsernameToken): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for private-message conversations.', 'messages'), 405, $this->routeServices->noStoreHeaders());
            return;
        }
        $viewer = ($this->authenticatedViewerProfile)();
        if ($viewer === null || ((int) ($viewer['is_approved'] ?? 0)) !== 1) {
            $this->accessPage($viewer);
            return;
        }
        try {
            $page = $this->service()->conversationPage($viewer, $counterpartUsernameToken);
        } catch (\ForumRewrite\Security\PublicOnlyKeyException) {
            $this->accessPage($viewer, true);
            return;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->messageError('Conversation Unavailable', $exception->getMessage(), 404);
            return;
        }
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_conversation.php', ['counterpartUsernameToken' => strtolower($counterpartUsernameToken), 'messages' => $page['messages'], 'historyPage' => $page, 'viewerProfile' => $viewer, 'tabBarHidden' => true], strtolower($counterpartUsernameToken), 'messages', ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_messages.js', '/assets/key_backup_status.js', '/assets/private_message_compose.js', '/assets/private_message_cache.js', '/assets/private_message_reader.js', '/assets/message_time.js', '/assets/private_message_conversation.js', '/assets/private_message_seen.js']), 200, $this->routeServices->noStoreHeaders());
    }

    /**
     * Messages for someone who isn't a signed-in approved member: the same page for
     * every visitor, which names their state and next step from browser storage
     * before paint, and signs in an approved key where it is.
     *
     * @param array<string, mixed>|null $viewer
     */
    /**
     * @param array<string, mixed> $viewer
     * @return list<array<string, mixed>>
     */
    private function waitingKeys(array $viewer): array
    {
        try {
            return (new \ForumRewrite\Security\PrivateKeyAccess($this->routeServices->pdo(), $this->routeServices->repositoryRoot()))->waitingForViewer($viewer);
        } catch (\Throwable) {
            return [];
        }
    }

    private function accessPage(?array $viewer, bool $publicOnly = false): void
    {
        $identityId = (string) ($viewer['identity_id'] ?? '');
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('private_message_access.php', [
            'viewerIdentityId' => $identityId,
            'publicOnly' => $publicOnly,
            'shortCode' => \ForumRewrite\Security\PrivateKeyAccess::shortCode($identityId),
            'confirmPath' => \ForumRewrite\Security\PrivateKeyAccess::confirmPath($identityId),
        ], 'Messages', 'messages', $publicOnly
            ? ['/assets/qrcode-generator.js', '/assets/qr_canvas.js', '/assets/private_access_device.js']
            : ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/private_site_auth.js', '/assets/private_message_access.js'],
        ), $viewer === null ? 401 : 403, $this->routeServices->noStoreHeaders());
    }

    private function redirectMailbox(string $method): void
    {
        if ($method !== 'GET') {
            $this->messageError('Method Not Allowed', 'Only GET is supported for Messages.', 405);
            return;
        }
        $this->routeServices->sendRedirect('/messages', 'Opening Messages.', 302, $this->routeServices->noStoreHeaders(), 'messages');
    }

    private function messageError(string $heading, string $message, int $status): void
    {
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('message.php', [
            'heading' => $heading, 'message' => $message, 'actionUrl' => '/messages', 'actionLabel' => 'Back to Messages',
        ], $heading, 'messages'), $status, $this->routeServices->noStoreHeaders());
    }

    private function service(): PrivateMessageMailboxService
    {
        return new PrivateMessageMailboxService(
            ($this->privateMessageStore)(),
            $this->routeServices->pdo(),
            new ApprovedUserKeyResolver($this->routeServices->repositoryRoot()),
            $this->routeServices->repositoryRoot(),
        );
    }
}
