<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Import\InstanceSourceResolver;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Security\OpenPgpKeyInspector;
use ForumRewrite\Support\LocalDevelopmentImport;
use ForumRewrite\Support\LocalDevelopmentRemote;
use InvalidArgumentException;
use Throwable;

/**
 * POST /local/remote: the local development page's relay to its source instance.
 * Same loopback guard and token as the public import; JSON in and out.
 */
final class LocalDevelopmentRemoteController
{
    /** @param (\Closure(string): LocalDevelopmentRemote)|null $remoteFactory */
    public function __construct(
        private readonly RouteServices $routes,
        private readonly LocalDevelopmentImport $import,
        private readonly ?\Closure $remoteFactory = null,
        private readonly ?\Closure $keyInspector = null,
        private readonly ?\Closure $pullFactory = null,
    ) {}

    public function handle(string $method): void
    {
        if ($method !== 'POST') {
            $this->send(['status' => 'error', 'error' => 'Use the local development page.'], 405);
            return;
        }
        if (!$this->import->authorize($_SERVER, $_POST['token'] ?? null)) {
            $this->send(['status' => 'error', 'error' => 'Reload the local development page and try again from this local address.'], 403);
            return;
        }
        try {
            $remote = $this->remote($this->field('source', 2048));
            $result = match ($this->field('action', 32)) {
                'key_status' => $this->keyStatus($remote),
                'prepare_identity' => $remote->prepareIdentity($this->field('public_key', 65536)),
                'create_identity' => $remote->createIdentity($this->field('prepare_token', 4096),
                    $this->field('canonical_record', 65536), $this->field('detached_signature', 65536)),
                'challenge' => ['challenge' => $remote->challenge()],
                'pull' => $this->pull($remote),
                default => throw new InvalidArgumentException('Unknown action.'),
            };
            $this->send(['status' => 'ok'] + $result + ['source' => $remote->source()], 200);
        } catch (InvalidArgumentException $error) {
            $this->send(['status' => 'error', 'error' => $error->getMessage()], 400);
        } catch (Throwable $error) {
            $this->send(['status' => 'error', 'error' => $error->getMessage()], 502);
        }
    }

    /** What the page needs before registering: who the key says it is, here and on the source. */
    private function keyStatus(LocalDevelopmentRemote $remote): array
    {
        $inspected = $this->keyInspector !== null
            ? ($this->keyInspector)($this->field('public_key', 65536))
            : (new OpenPgpKeyInspector())->inspect($this->field('public_key', 65536));
        $identityId = 'openpgp:' . strtolower($inspected['fingerprint']);
        $local = ProfileRepository::byIdentityId($this->routes->pdo(), $identityId);
        return $remote->keyState($identityId) + [
            'identity_id' => $identityId,
            'source_key_url' => $remote->source() . '/tools/user-access/profiles/' . str_replace(':', '-', $identityId) . '/',
            'username' => $inspected['username'],
            'source_approved_keys' => $remote->approvedKeyCount($inspected['username']),
            'locally_known' => $local !== null,
            'locally_approved' => $local !== null && (int) ($local['is_approved'] ?? 0) === 1,
        ];
    }

    /** Signs in with the browser's fresh signature, then pulls a bounded number of pages. */
    private function pull(LocalDevelopmentRemote $remote): array
    {
        $identityId = strtolower($this->field('identity_id', 128));
        $local = ProfileRepository::byIdentityId($this->routes->pdo(), $identityId);
        if ($local === null || (int) ($local['is_approved'] ?? 0) !== 1) {
            throw new InvalidArgumentException('This clone does not know this key is approved yet. Run Import above, then pull again.');
        }
        if ($this->pullFactory === null) throw new \RuntimeException('Message pull is unavailable on this server.');
        $signedIn = $remote->signIn($identityId, $this->field('challenge', 256), $this->field('signature', 65536));
        if (!$signedIn['approved']) {
            throw new InvalidArgumentException('This key is not approved on ' . $remote->source() . ' yet. Approve it there, then pull again.');
        }
        return ($this->pullFactory)()->run($remote, (string) $local['username_token']);
    }

    private function remote(string $source): LocalDevelopmentRemote
    {
        $resolved = (new InstanceSourceResolver())->resolve($source);
        return $this->remoteFactory !== null ? ($this->remoteFactory)($resolved) : new LocalDevelopmentRemote($resolved);
    }

    private function field(string $name, int $max): string
    {
        $value = $_POST[$name] ?? '';
        if (!is_string($value) || $value === '' || strlen($value) > $max) {
            throw new InvalidArgumentException('Missing or oversized field: ' . $name . '.');
        }
        return $value;
    }

    private function send(array $payload, int $status): void
    {
        $this->routes->sendJson($payload, $status, ['Cache-Control: no-store, private', 'X-Robots-Tag: noindex']);
    }
}
