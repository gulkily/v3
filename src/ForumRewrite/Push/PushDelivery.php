<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use Closure;

/**
 * Sends one payload to one stored device, as Send a test and the DM and reply notices both do.
 *
 * The device belongs to the session that subscribed it: once that session is signed out or
 * signed in as another key, nothing is sent and the device is deleted. A 404 or 410 from the
 * push service deletes it too.
 */
final class PushDelivery
{
    /**
     * @param Closure(string): string $signedInIdentityForKey session key => identity ID, '' when signed out
     */
    public function __construct(
        private readonly PushSubscriptionStore $store,
        private readonly PushSender $sender,
        private readonly Closure $signedInIdentityForKey,
    ) {
    }

    /**
     * @param array{endpoint:string,identity_id:string,session_key:string,subscription:PushSubscription} $device
     * @param array<string, mixed> $payload
     */
    public function toDevice(array $device, array $payload, int $now, string $urgency = 'high'): PushSendResult
    {
        if (($this->signedInIdentityForKey)($device['session_key']) !== $device['identity_id']) {
            $this->store->delete($device['endpoint']);

            return PushSendResult::gone('signed out');
        }
        $result = $this->sender->send($device['subscription'], $payload, $urgency);
        if ($result->outcome === PushSendResult::GONE) {
            $this->store->delete($device['endpoint']);
        } elseif ($result->outcome === PushSendResult::SENT) {
            $this->store->markSent($device['endpoint'], $now);
        }

        return $result;
    }
}
