<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use Closure;

/**
 * Sends the DM and reply notices waiting in the push store to every device of their member.
 *
 * One notice per conversation or thread (`tag`): the first goes at once, and later ones for
 * that tag are held until HOLD_SECONDS after its last send, then go together as one
 * replacement. A member gets at most CAP_SENDS notices per CAP_WINDOW_SECONDS; the rest are
 * folded into one "N new" notice under the tag `more`, which is held the same way. A notice
 * already read on the server is dropped, and so is one older than MAX_AGE_SECONDS.
 */
final class PushNotifier
{
    public const HOLD_SECONDS = 300;
    public const CAP_SENDS = 6;
    public const CAP_WINDOW_SECONDS = 600;
    public const MAX_AGE_SECONDS = 24 * 3600;
    public const OVERFLOW_TAG = 'more';
    private const LEASE_SECONDS = 120;

    /**
     * @param Closure(string): PushDelivery $deliveryForSite site address => delivery signed for it
     * @param Closure(array<string, mixed>): bool $isRead pending row => already read on the server
     * @param (Closure(array<string, mixed>): bool)|null $mayReceive device => may get its username's notices
     */
    public function __construct(
        private readonly PushSubscriptionStore $store,
        private readonly Closure $deliveryForSite,
        private readonly Closure $isRead,
        private readonly ?Closure $mayReceive = null,
    ) {
    }

    /**
     * Sends whatever is due. `$route` names who ran it (after-response, detached, queue) and is
     * kept on each finished notice.
     *
     * @return array{sent:int,held:int,read:int,dropped:int,devices:int,busy:bool}
     */
    public function sendDue(int $now, string $route): array
    {
        $counts = ['sent' => 0, 'held' => 0, 'read' => 0, 'dropped' => 0, 'devices' => 0, 'busy' => false];
        $owner = $route . ':' . getmypid() . ':' . bin2hex(random_bytes(4));
        if (!$this->store->takeLease($owner, $now, self::LEASE_SECONDS)) {
            $counts['busy'] = true;
            return $counts;
        }
        try {
            $groups = [];
            foreach ($this->store->pending() as $row) {
                if ((int) $row['created_at'] < $now - self::MAX_AGE_SECONDS) {
                    $this->store->finishPending([(int) $row['id']], 'dropped', $now, $route);
                    $counts['dropped']++;
                    continue;
                }
                $groups[(string) $row['recipient']][(string) $row['tag']][] = $row;
            }
            foreach ($groups as $recipient => $tags) {
                $this->sendForRecipient((string) $recipient, $tags, $now, $route, $counts);
            }
            $this->store->prune($now - 2 * self::MAX_AGE_SECONDS);
        } finally {
            $this->store->releaseLease($owner);
        }

        return $counts;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tags
     * @param array<string, int|bool> $counts
     */
    private function sendForRecipient(string $recipient, array $tags, int $now, string $route, array &$counts): void
    {
        $devices = $this->store->devicesFor($recipient);
        if ($this->mayReceive !== null) {
            $devices = array_values(array_filter($devices, $this->mayReceive));
        }
        $overflow = [];
        foreach ($tags as $tag => $rows) {
            if ($devices === []) {
                $this->store->finishPending($this->ids($rows), 'dropped', $now, $route);
                $counts['dropped'] += count($rows);
                continue;
            }
            if ($this->held($recipient, (string) $tag, $now)) {
                $counts['held'] += count($rows);
                continue;
            }
            $rows = $this->unread($rows, $now, $route, $counts);
            if ($rows === []) {
                continue;
            }
            if ($this->store->sendsSince($recipient, $now - self::CAP_WINDOW_SECONDS, self::OVERFLOW_TAG) >= self::CAP_SENDS) {
                array_push($overflow, ...$rows);
                continue;
            }
            $this->deliver($recipient, (string) $tag, self::payload($rows), $rows, $devices, $now, $route, $counts);
        }
        if ($overflow === []) {
            return;
        }
        if ($this->held($recipient, self::OVERFLOW_TAG, $now)) {
            $counts['held'] += count($overflow);
            return;
        }
        $this->deliver($recipient, self::OVERFLOW_TAG, self::overflowPayload($overflow), $overflow, $devices, $now, $route, $counts);
    }

    private function held(string $recipient, string $tag, int $now): bool
    {
        $last = $this->store->lastSendAt($recipient, $tag);

        return $last !== null && $now < $last + self::HOLD_SECONDS;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int|bool> $counts
     * @return list<array<string, mixed>>
     */
    private function unread(array $rows, int $now, string $route, array &$counts): array
    {
        $unread = [];
        foreach ($rows as $row) {
            if (($this->isRead)($row)) {
                $this->store->finishPending([(int) $row['id']], 'read', $now, $route);
                $counts['read']++;
            } else {
                $unread[] = $row;
            }
        }

        return $unread;
    }

    /**
     * @param array<string, string> $payload
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $devices
     * @param array<string, int|bool> $counts
     */
    private function deliver(string $recipient, string $tag, array $payload, array $rows, array $devices, int $now, string $route, array &$counts): void
    {
        $delivery = ($this->deliveryForSite)((string) $rows[0]['site']);
        $sent = false;
        foreach ($devices as $device) {
            if ($delivery->toDevice($device, $payload, $now)->outcome === PushSendResult::SENT) {
                $sent = true;
                $counts['devices']++;
            }
        }
        // A notice no device took is finished either way; retrying it would only send it late.
        $this->store->finishPending($this->ids($rows), $sent ? 'sent' : 'failed', $now, $route);
        if ($sent) {
            $this->store->recordSend($recipient, $tag, $now);
            $counts['sent'] += count($rows);
        } else {
            $counts['dropped'] += count($rows);
        }
    }

    /**
     * What the service worker shows for one tag's notices.
     *
     * @param list<array<string, mixed>> $rows oldest first, all with the same tag
     * @return array{title:string,body:string,url:string,tag:string}
     */
    public static function payload(array $rows): array
    {
        $last = $rows[count($rows) - 1];
        $count = count($rows);
        if ((string) $last['kind'] === 'dm') {
            return [
                'title' => (string) $last['actor'],
                'body' => $count === 1 ? 'New message' : $count . ' new messages',
                'url' => (string) $last['url'],
                'tag' => (string) $last['tag'],
            ];
        }
        if ($count === 1) {
            return ['title' => $last['actor'] . ' replied', 'body' => (string) $last['body'], 'url' => (string) $last['url'], 'tag' => (string) $last['tag']];
        }

        return ['title' => $count . ' replies', 'body' => self::names($rows), 'url' => (string) $last['url'], 'tag' => (string) $last['tag']];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{title:string,body:string,url:string,tag:string}
     */
    public static function overflowPayload(array $rows): array
    {
        $dms = count(array_filter($rows, static fn (array $row): bool => (string) $row['kind'] === 'dm'));
        $replies = count($rows) - $dms;
        $parts = array_filter([
            $replies > 0 ? $replies . ($replies === 1 ? ' new reply' : ' new replies') : '',
            $dms > 0 ? $dms . ($dms === 1 ? ' new message' : ' new messages') : '',
        ]);

        return ['title' => 'ZenMemes', 'body' => implode(', ', $parts) . ' · ' . self::names($rows), 'url' => '/unread/', 'tag' => self::OVERFLOW_TAG];
    }

    /** @param list<array<string, mixed>> $rows up to three names, newest first, then "…" */
    private static function names(array $rows): string
    {
        $names = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['actor'], array_reverse($rows))));
        $shown = implode(', ', array_slice($names, 0, 3));

        return count($names) > 3 ? $shown . ', …' : $shown;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
