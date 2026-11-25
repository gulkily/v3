<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use ForumRewrite\Inbox\InboxDatabaseConfig;
use ForumRewrite\Inbox\InboxMarkStore;
use ForumRewrite\Messaging\PrivateMessageDatabaseConfig;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Session\ViewerSessionDatabaseConfig;
use ForumRewrite\Session\ViewerSessionStore;
use ForumRewrite\Support\PrivateConfig;
use PDO;

/**
 * The PushNotifier for this instance's private stores, as the web request (after its response)
 * and the task worker both run it. Reads Unread's marks and DM seen rows as the read check.
 */
final class PushNotifierFactory
{
    public static function forProject(string $projectRoot, PDO $readPdo): PushNotifier
    {
        $config = PrivateConfig::load($projectRoot);
        $store = PushSubscriptionStore::open(PushStorageConfig::databasePath($projectRoot, $config));
        $keys = new PushVapidKeys(PushStorageConfig::vapidKeyPath($projectRoot, $config));
        $sessions = new ViewerSessionStore(ViewerSessionDatabaseConfig::open($projectRoot, $config));
        $signedIn = static fn (string $sessionKey): string => $sessions->signedInIdentityForKey($sessionKey);
        $marks = null;
        $messages = null;

        return new PushNotifier(
            $store,
            static fn (string $site): PushDelivery => new PushDelivery($store, new PushSender($keys, $site), $signedIn),
            static function (array $row) use ($projectRoot, $config, &$marks, &$messages): bool {
                if ($row['kind'] === PushNotices::KIND_DM) {
                    $path = PrivateMessageDatabaseConfig::path($projectRoot, $config);
                    $messages ??= is_file($path) ? new PrivateMessageStore(new PDO('sqlite:' . $path)) : false;

                    return $messages !== false && $messages->isSeenBy((string) $row['recipient'], (string) $row['actor'], (string) $row['source_id']);
                }
                $marks ??= new InboxMarkStore(InboxDatabaseConfig::open($projectRoot));

                return $marks->isCaughtUp((string) $row['recipient'], 'replies', (string) $row['source_id'], (string) $row['source_created_at']);
            },
            static fn (array $device): bool => PushNotices::deviceMayReceive($readPdo, $device),
        );
    }

    public static function hasPending(string $projectRoot): bool
    {
        return PushSubscriptionStore::open(PushStorageConfig::databasePath($projectRoot, PrivateConfig::load($projectRoot)))->hasPending();
    }
}
