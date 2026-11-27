<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Import\InstanceSourceResolver;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Security\OpenPgpKeyInspector;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
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
        private readonly ?\Closure $localStores = null,
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
        $action = is_string($_POST['action'] ?? null) ? substr($_POST['action'], 0, 32) : '';
        $started = microtime(true);
        $remote = null;
        try {
            $remote = $this->remote($this->field('source', 2048));
            $result = match ($this->field('action', 32)) {
                'key_status' => $this->keyStatus($remote),
                'challenge' => ['challenge' => $remote->challenge()],
                'pull' => $this->pull($remote),
                'trust_candidates' => $this->trustCandidates($remote),
                'trust' => $this->trust($remote),
                'saved_copies' => $this->savedCopies(),
                default => throw new InvalidArgumentException('Unknown action.'),
            };
            $this->send(['status' => 'ok'] + $result + ['source' => $remote->source()], 200);
        } catch (InvalidArgumentException $error) {
            $this->sendError($error, 400, $action, $remote, $started);
        } catch (Throwable $error) {
            $this->sendError($error, 502, $action, $remote, $started);
        }
    }

    /** The short message, plus fields for the page's Details: the step, the error and the conditions. */
    private function sendError(Throwable $error, int $status, string $action, ?LocalDevelopmentRemote $remote, float $started): void
    {
        $details = ['step' => 'local pull: ' . ($action !== '' ? $action : 'request'), 'error' => get_class($error) . ': ' . $error->getMessage(),
            'local_status' => $status, 'waited_ms' => (int) round((microtime(true) - $started) * 1000),
            'source' => $remote?->source()];
        if (is_string($_POST['identity_id'] ?? null) && preg_match('/^openpgp:[a-f0-9]{40}$/D', strtolower($_POST['identity_id'])) === 1) {
            $details['key'] = strtolower($_POST['identity_id']);
        }
        if ($error instanceof \ForumRewrite\Support\LocalPullFailure) $details += $error->details;
        $this->send(['status' => 'error', 'error' => $error->getMessage(), 'step' => $details['step'], 'details' => $details], $status);
    }

    /** What Connect checks first: who the key says it is, here and on the source. */
    private function keyStatus(LocalDevelopmentRemote $remote): array
    {
        $inspected = $this->keyInspector !== null
            ? ($this->keyInspector)($this->field('public_key', 65536))
            : (new OpenPgpKeyInspector())->inspect($this->field('public_key', 65536));
        $identityId = 'openpgp:' . strtolower($inspected['fingerprint']);
        $local = ProfileRepository::byIdentityId($this->routes->pdo(), $identityId);
        return $remote->keyState($identityId) + [
            'local_username' => $local === null ? null : (string) $local['username'],
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
        if ($local === null) {
            throw new InvalidArgumentException('This clone does not know this key yet. Import public content above, then pull again.');
        }
        if ($this->pullFactory === null) throw new \RuntimeException('Message pull is unavailable on this server.');
        $signedIn = $remote->signIn($identityId, $this->field('challenge', 256), $this->field('signature', 65536));
        if (!$signedIn['approved']) {
            throw new InvalidArgumentException('This key is not approved on ' . $remote->source() . ' yet. Approve it there, then pull again.');
        }
        return ($this->pullFactory)()->run($remote, (string) $local['username_token']);
    }

    /**
     * Keys this clone must approve before Messages can read pulled mail: this browser's key,
     * the keys that signed pulled messages and the donors of pulled transfers. Only keys the
     * source approves, that this clone knows and has not approved, are offered.
     */
    private function trustCandidates(LocalDevelopmentRemote $remote): array
    {
        $identityId = strtolower($this->field('identity_id', 128));
        $pdo = $this->routes->pdo();
        $own = ProfileRepository::byIdentityId($pdo, $identityId);
        if ($own === null) throw new InvalidArgumentException('This clone does not know this key yet. Import public content above, then try again.');
        $account = (string) $own['username_token'];
        [$messages, $sync] = $this->stores();
        $ids = array_merge([$identityId], $messages->senderIdentitiesFor($account), array_map(
            static fn (string $fingerprint): string => 'openpgp:' . $fingerprint, $sync->sourcesFor($account, substr($identityId, 8))));
        $keys = [];
        $missing = [];
        foreach (array_slice(array_values(array_unique(array_map('strtolower', $ids))), 0, 200) as $candidate) {
            $profile = ProfileRepository::byIdentityId($pdo, $candidate);
            if ($profile !== null && (int) ($profile['is_approved'] ?? 0) === 1) continue;
            if (!$remote->keyState($candidate)['approved']) continue;
            if ($profile === null) { $missing[] = $candidate; continue; }
            $keys[] = ['identity_id' => $candidate, 'username' => (string) $profile['username'], 'own' => $candidate === $identityId];
        }
        return ['keys' => $keys, 'missing' => $missing];
    }

    /**
     * Approves the listed keys in this clone only. This browser's key gets a local approval
     * seed; the others are approved by it. Each key is rechecked against the source first.
     */
    private function trust(LocalDevelopmentRemote $remote): array
    {
        $identityId = strtolower($this->field('identity_id', 128));
        $requested = json_decode($this->field('keys', 65536), true);
        if (!is_array($requested) || !array_is_list($requested) || count($requested) > 200) throw new InvalidArgumentException('Send at most 200 keys.');
        $pdo = $this->routes->pdo();
        $writer = $this->routes->writer();
        $reason = 'Trusted for pulled messages because ' . $remote->source() . ' approves it';
        $own = ProfileRepository::byIdentityId($pdo, $identityId);
        if ($own === null) throw new InvalidArgumentException('This clone does not know this key yet. Import public content above, then try again.');
        $trusted = [];
        $ordered = array_values(array_unique(array_map(static fn ($id): string => strtolower((string) $id), $requested)));
        usort($ordered, static fn (string $a, string $b): int => ($b === $identityId) <=> ($a === $identityId));
        foreach ($ordered as $candidate) {
            if (preg_match('/^openpgp:[a-f0-9]{40}$/D', $candidate) !== 1) throw new InvalidArgumentException('A key ID is malformed.');
            $profile = ProfileRepository::byIdentityId($pdo, $candidate);
            if ($profile === null) throw new InvalidArgumentException('This clone does not know ' . $candidate . '. Import public content above, then try again.');
            if ((int) ($profile['is_approved'] ?? 0) === 1) continue;
            if (!$remote->keyState($candidate)['approved']) throw new InvalidArgumentException($remote->source() . ' does not approve ' . $candidate . '. Nothing more was trusted.');
            if ($candidate === $identityId) {
                $writer->seedApprovedIdentity($candidate, $reason);
            } else {
                if ((int) (ProfileRepository::byIdentityId($pdo, $identityId)['is_approved'] ?? 0) !== 1) {
                    throw new InvalidArgumentException('Trust this browser\'s key first.');
                }
                $writer->approveUser(['approver_identity_id' => $identityId, 'target_identity_id' => $candidate,
                    'target_profile_slug' => (string) $profile['profile_slug'], 'thread_id' => (string) $profile['bootstrap_thread_id'],
                    'parent_id' => (string) $profile['bootstrap_post_id']]);
            }
            $trusted[] = $candidate;
        }
        return ['trusted' => $trusted];
    }

    /**
     * Turns on saved message copies in this clone, so Messages opens pulled mail without
     * decrypting it again. A value set by the environment or private config is reported, never changed.
     *
     * @return array{saved_copies:bool,changed:bool,overridden_by:?string}
     */
    private function savedCopies(): array
    {
        $key = FeatureFlagRegistry::PRIVATE_MESSAGE_CACHE_ENABLED;
        $state = $this->routes->featureFlags()->evaluate($key);
        if (in_array($state->source, ['environment', 'private-config'], true)) {
            return ['saved_copies' => $state->effectiveValue, 'changed' => false, 'overridden_by' => $state->source];
        }
        if ($state->effectiveValue) {
            return ['saved_copies' => true, 'changed' => false, 'overridden_by' => null];
        }
        $result = $this->routes->writer()->setFeatureFlag(['key' => $key, 'value' => true]);
        return ['saved_copies' => true, 'changed' => ($result['wrote_record'] ?? 'yes') !== 'no', 'overridden_by' => null];
    }

    /** @return array{0:\ForumRewrite\Messaging\PrivateMessageStore,1:\ForumRewrite\Messaging\PrivateMessageHistorySyncStore} */
    private function stores(): array
    {
        if ($this->localStores === null) throw new \RuntimeException('Local message storage is unavailable on this server.');
        return ($this->localStores)();
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
