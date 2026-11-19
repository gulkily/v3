<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Port\HeldPortStore;
use ForumRewrite\Port\PortFile;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * Porting a thread from another site (docs/concepts/ported_thread.md).
 *
 * POST /port       the bookmarklet's form, from the other site: hold the file, 303 to the preview.
 * GET  /port/<id>  the preview: title, comment count, and Port for an approved member.
 * POST /port/<id>  Port, from the preview on this site: write the thread, 303 to it.
 *
 * The first POST carries no session cookie (SameSite=Lax), so it only holds the
 * file; the redirect's GET does carry it, so the preview knows who is looking.
 */
final class PortPageController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $viewerProfile
     * @param \Closure(string): bool $flagEnabled
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $viewerProfile,
        private readonly \Closure $flagEnabled,
        private readonly HeldPortStore $store,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function receive(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->waiting();
            return;
        }
        try {
            $port = PortFile::parse((string) ($this->routeServices->requestData($query)['port'] ?? ''));
            $id = $this->store->hold($port, time());
        } catch (\RuntimeException $exception) {
            $this->message('Couldn’t port this thread', $exception->getMessage() . ' Nothing was posted.', 422);
            return;
        }
        $this->routeServices->sendRedirect('/port/' . $id, 'Continue to the port preview.', 303, $this->routeServices->noStoreHeaders(), 'tools');
    }

    /** @param array<string, mixed> $query */
    public function preview(string $method, string $id, array $query): void
    {
        $held = $this->store->find($id);
        if ($held === null) {
            $this->message('Port expired', 'This port is no longer waiting. Tap the bookmarklet on the Hacker News page again.', 404);
            return;
        }
        $viewer = ($this->viewerProfile)();
        $canPort = RouteServices::signedInApproved($viewer);
        $error = $held['error'];
        if ($method === 'POST') {
            if (!$canPort || !SameOriginRequest::check()) {
                $error = 'Only an approved member, signed in on this site, can port. Nothing was posted.';
            } else {
                try {
                    $result = $this->routeServices->writer()->createPortedThread($held['port'], (string) $viewer['identity_id']);
                    $this->store->discard($id);
                    $this->routeServices->sendRedirect('/threads/' . rawurlencode($result['thread_id']), 'Open the ported thread.', 303, $this->routeServices->noStoreHeaders(), 'tools');
                    return;
                } catch (\Throwable $throwable) {
                    $error = $throwable->getMessage() . ' Nothing was posted; you can try again.';
                    $this->store->recordError($id, $error);
                }
            }
        }

        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('port.php', [
            'viewerProfile' => $viewer,
            'heldId' => $id,
            'port' => $held['port'],
            'existingThreadId' => $this->existingThreadId($held['port']),
            'canPort' => $canPort,
            'signedIn' => $viewer !== null && ($viewer['_authenticated_identity'] ?? false) === true,
            'guestsMayPort' => ($this->flagEnabled)(FeatureFlagRegistry::THREAD_PORT_GUESTS_ENABLED),
            'error' => $error,
        ], 'Port a thread', 'tools'), $method === 'POST' && $error !== '' ? 409 : 200, $this->routeServices->noStoreHeaders());
    }

    /** GET /port/: ports guests sent, for an approved member to open and port. Only while guests may port. */
    private function waiting(): void
    {
        $viewer = ($this->viewerProfile)();
        if (!($this->flagEnabled)(FeatureFlagRegistry::THREAD_PORT_GUESTS_ENABLED) || !RouteServices::signedInApproved($viewer)) {
            $this->message('Port a thread', 'Use the Port HN bookmarklet on Tools → Bookmarklets on a Hacker News thread.', 200);
            return;
        }
        $this->routeServices->sendHtml($this->routeServices->renderPageTemplate('port_waiting.php', [
            'viewerProfile' => $viewer,
            'items' => $this->store->all(time()),
        ], 'Ports waiting', 'tools'), 200, $this->routeServices->noStoreHeaders());
    }

    private function existingThreadId(PortFile $port): ?string
    {
        try {
            $stmt = $this->routeServices->pdo()->prepare('SELECT post_id FROM posts WHERE ported_from = ? AND post_id = thread_id AND retracted_at IS NULL LIMIT 1');
            $stmt->execute([PortFile::itemAddress($port->root()->id)]);
            $id = $stmt->fetchColumn();
            return is_string($id) && $id !== '' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function message(string $heading, string $message, int $status): void
    {
        $this->routeServices->sendHtml($this->routeServices->renderMessagePage($heading, $heading, $message, 'tools'), $status, $this->routeServices->noStoreHeaders());
    }
}
