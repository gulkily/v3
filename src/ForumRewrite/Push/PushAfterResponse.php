<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use Closure;
use ForumRewrite\TaskQueue\DetachedTaskQueueLauncher;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
use PDO;

/**
 * Sends a request's new push notices once its response is out, so the poster never waits.
 *
 * On php-fpm, fastcgi_finish_request() ends the response and the same request runs the send
 * pass. Elsewhere a detached one-task worker runs the queued `push_notify` task. Either way that
 * task stays queued, so cron sends anything this misses within a minute.
 */
final class PushAfterResponse
{
    private static ?int $taskId = null;

    /** Called when a notice was recorded and its queue task is `$taskId`. */
    public static function request(int $taskId): void
    {
        self::$taskId = $taskId;
    }

    public static function requested(): bool
    {
        return self::$taskId !== null;
    }

    /**
     * @param (Closure(): bool)|null $finish ends the response; default fastcgi_finish_request() when it exists
     * @param (Closure(int): string)|null $launch starts a worker for a task ID; default DetachedTaskQueueLauncher
     * @param (Closure(): PushNotifier)|null $notifier default PushNotifierFactory for the project
     * @return string|null 'after-response', 'detached', 'launch-failed', or null when nothing was recorded
     */
    public static function run(string $projectRoot, string $databasePath, ?Closure $finish = null, ?Closure $launch = null, ?Closure $notifier = null): ?string
    {
        $taskId = self::$taskId;
        self::$taskId = null;
        if ($taskId === null) {
            return null;
        }
        $finish ??= function_exists('fastcgi_finish_request') ? static fn (): bool => fastcgi_finish_request() : null;
        if ($finish !== null && $finish()) {
            ignore_user_abort(true);
            if (function_exists('set_time_limit')) {
                @set_time_limit(60);
            }
            $notifier ??= static fn (): PushNotifier => PushNotifierFactory::forProject($projectRoot, new PDO('sqlite:' . $databasePath));
            $notifier()->sendDue(time(), 'after-response');

            return 'after-response';
        }
        $launch ??= static fn (int $id): string => (new DetachedTaskQueueLauncher($projectRoot, TaskQueueDatabaseConfig::path($projectRoot)))->launchForTask($id);

        return $launch($taskId) === 'launched' ? 'detached' : 'launch-failed';
    }
}
