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
        try {
            $remote = $this->remote($this->field('source', 2048));
            $result = match ($this->field('action', 32)) {
                'key_status' => $this->keyStatus($remote),
                'prepare_identity' => $remote->prepareIdentity($this->field('public_key', 65536)),
                'create_identity' => $remote->createIdentity($this->field('prepare_token', 4096),
                    $this->field('canonical_record', 65536), $this->field('detached_signature', 65536)),
                'challenge' => ['challenge' => $remote->challenge()],
                'pull' => $this->pull($remote),
                'trust_candidates' => $this->trustCandidates($remote),
                'trust' => $this->trust($remote),
                'verify_list' => $this->verifyList(),
                'confirm' => $this->confirm($remote),
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
     * Pulled transfers to this key that the browser has not verified yet, with what it needs
     * to verify them: original envelopes, the donor key and the original senders' keys.
     */
    private function verifyList(): array
    {
        [$account, $target] = $this->localKey();
        [$messages, $sync] = $this->stores();
        $after = is_string($_POST['after'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $_POST['after']) === 1 ? $_POST['after'] : '';
        $page = $sync->unverifiedFor($account, $target, $after);
        $pdo = $this->routes->pdo();
        $senderKeys = [];
        $transfers = [];
        foreach ($page['transfers'] as $transfer) {
            $donor = ProfileRepository::byIdentityId($pdo, 'openpgp:' . $transfer['source']);
            $items = [];
            foreach ($transfer['items'] as $item) {
                $row = $messages->historySyncMessage($account, (string) $item['message_id']);
                if ($row === null) continue;
                $sender = (string) $row['sender_username_token'];
                $senderKeys[$sender] ??= array_values(array_filter(array_map(static fn (array $profile): string => (string) ($profile['public_key'] ?? ''),
                    ProfileRepository::byUsernameToken($pdo, $sender))));
                $items[] = $item + ['envelope' => (string) $row['encrypted_envelope'], 'sender' => $sender];
            }
            $transfers[] = ['transfer_id' => $transfer['transfer_id'], 'source' => $transfer['source'], 'target' => $transfer['target'],
                'ciphertext' => $transfer['ciphertext'], 'source_key' => (string) ($donor['public_key'] ?? ''),
                'source_account' => (string) ($donor['username_token'] ?? ''), 'items' => $items];
        }
        return ['account' => $account, 'transfers' => $transfers, 'sender_keys' => $senderKeys, 'next' => $page['next']];
    }

    /** Records browser-verified transfers here, then confirms them on the source after a fresh sign-in. */
    private function confirm(LocalDevelopmentRemote $remote): array
    {
        [$account, $target, $identityId] = $this->localKey();
        $items = json_decode($this->field('items', 262144), true);
        if (!is_array($items) || !array_is_list($items) || count($items) > 100) throw new InvalidArgumentException('Send at most 100 verified items.');
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['message_id'] ?? null) || !is_string($item['digest'] ?? null)
                || !is_string($item['transfer_id'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $item['transfer_id']) !== 1) {
                throw new InvalidArgumentException('A verified item is malformed.');
            }
        }
        [, $sync] = $this->stores();
        // The source accepts one transfer per message in a batch; keep the first verified one.
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['message_id']] ??= ['message_id' => $item['message_id'], 'digest' => $item['digest'], 'transfer_id' => $item['transfer_id']];
        }
        $items = array_values($unique);
        $signedIn = $remote->signIn($identityId, $this->field('challenge', 256), $this->field('signature', 65536));
        if (!$signedIn['approved']) throw new InvalidArgumentException('This key is not approved on ' . $remote->source() . ' yet.');
        $confirmed = 0;
        // Record coverage here only after the source accepted it, so a failed batch is retried next time.
        foreach (array_chunk($items, 10) as $chunk) {
            $confirmed += (int) ($remote->acknowledge($chunk)['confirmed'] ?? count($chunk));
            $sync->acknowledge($account, $target, $chunk);
        }
        return ['confirmed' => $confirmed];
    }

    /** @return array{0:string,1:string,2:string} account, target fingerprint and identity ID of an approved local key */
    private function localKey(): array
    {
        $identityId = strtolower($this->field('identity_id', 128));
        $local = ProfileRepository::byIdentityId($this->routes->pdo(), $identityId);
        if ($local === null || (int) ($local['is_approved'] ?? 0) !== 1 || !str_starts_with($identityId, 'openpgp:')) {
            throw new InvalidArgumentException('This clone does not know this key is approved yet. Import public content above, then try again.');
        }
        return [(string) $local['username_token'], substr($identityId, 8), $identityId];
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
