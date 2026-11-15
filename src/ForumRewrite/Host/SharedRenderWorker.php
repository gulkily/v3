<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use RuntimeException;
use Throwable;

/**
 * Body of the isolated render workers (scripts/high_traffic_render.php and
 * scripts/tour_render.php). The parent invokes a worker with an argument
 * array; visitor state is never inherited.
 */
final class SharedRenderWorker
{
    public const HIGH_TRAFFIC = 'high_traffic';
    public const TOUR = 'tour';

    /** @param list<string> $argv */
    public static function run(array $argv, string $kind): int
    {
        try {
            if (PHP_SAPI !== 'cli' || !in_array(count($argv), [7, 8], true)) throw new RuntimeException('Internal render worker arguments required.');
            [, $project, $repository, $database, $assets, $route, $profile] = $argv;
            // Apache SetEnv values are request-scoped and need not reach CLI children.
            // Require the parent's resolved profile instead of silently using a fallback.
            if (!isset(\ForumRewrite\SiteProfileRegistry::all()[$profile])) throw new RuntimeException('Unknown shared render site profile.');
            if (!putenv('FORUM_SITE_ID=' . $profile)) throw new RuntimeException('Cannot set shared render site profile.');
            $_COOKIE = $_GET = $_POST = $_REQUEST = $_SESSION = [];
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $route, 'HTTP_USER_AGENT' => 'v3-cache-bot'];
            $report = static function (string $stage): void { fwrite(STDERR, 'High-traffic stage: ' . $stage . "\n"); };
            $report('validating retraction evidence (no full-record scan)');
            $context = HighTrafficPublication::captureForRender($repository);
            if (isset($argv[7]) && !hash_equals($argv[7], HighTrafficPublication::identity($context))) {
                throw new RuntimeException('High-traffic source changed before rendering; retry generation.');
            }
            $report('preparing isolated render input');
            $database = HighTrafficRenderInput::prepare($context, $database, dirname($assets) . '/model', $report);
            $report('rendering HTML and copying referenced assets');
            $builder = new StaticArtifactBuilder($project, $repository, $database, $assets, $report);
            $stale = $context['state']['absent'] !== [];
            $html = $kind === self::TOUR
                ? $builder->renderTourRoute($route, allowStaleReadModel: $stale)
                : $builder->renderHighTrafficRoute($route, allowStaleReadModel: $stale);
            $report('validating rendered candidate');
            HighTrafficPublication::validate($context);
            fwrite(STDOUT, $html);
            return 0;
        } catch (Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");
            return http_response_code() === 404 ? 4 : 1;
        }
    }
}
