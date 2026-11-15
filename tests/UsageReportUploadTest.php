<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Http\RouteServices;
use ForumRewrite\Http\UsageReportsController;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\UsageReports\UsageLogFormat;
use ForumRewrite\UsageReports\UsageReportStore;
use ForumRewrite\View\TemplateRenderer;

final class UsageReportUploadTest
{
    private const LOG = "usage-log v1\nview\t0\t/\nclick\t4\t/\tnavbar\ttab:tools\t/tools\nview\t1\t/tools\nclick\t3\t/tools\ttools-bar\ttool:usage\t/tools/usage-log\nsubmit\t9\t/threads/*\tcomposer\tPost reply\n";

    public function testFormatAcceptsRecorderOutputAndRejectsAnythingElse(): void
    {
        assertTrue(UsageLogFormat::isValid(self::LOG));
        assertSame(5, count(UsageLogFormat::entries(self::LOG)));
        assertSame('/tools', UsageLogFormat::entries(self::LOG)[1]['to']);
        assertTrue(UsageLogFormat::isValid("usage-log v1\nclick\t2\t/\tpost\tWhere do we keep the keys?\texternal\n"));
        assertTrue(UsageLogFormat::isValid("usage-log v1\n"));
        foreach ([
            '' => 'empty',
            "usage-log v2\nview\t0\t/\n" => 'wrong header',
            "usage-log v1\nview\t0\t/threads/T123\n" => 'unredacted ID',
            "usage-log v1\nview\t0\t/threads?x=1\n" => 'query string',
            "usage-log v1\nkeypress\t0\t/\n" => 'unknown entry',
            "usage-log v1\nclick\t0\t/\tnavbar\t\t-\n" => 'empty name',
            "usage-log v1\nview\t0\t/\nusername: root\n" => 'smuggled field',
        ] as $text => $why) {
            assertFalse(UsageLogFormat::isValid((string) $text), $why);
        }
    }

    public function testStoresTheExactTextWithTheSessionUsernameOrAnonymous(): void
    {
        [$controller, $store] = $this->controller(static fn (): ?string => 'ilyag');
        [$status, $payload] = $this->upload($controller, self::LOG);
        assertSame(201, $status);
        assertSame('ilyag', $payload['username']);
        assertSame([self::LOG], array_column($store->reports('ilyag'), 'body'));

        [$anonymous, $anonymousStore] = $this->controller(static fn (): ?string => null);
        [$status, $payload] = $this->upload($anonymous, self::LOG);
        assertSame(201, $status);
        assertSame(null, $payload['username']);
        assertSame([null], array_column($anonymousStore->reports(), 'username'));
    }

    /** The Usage log page sends JSON, because some hosts refuse raw line breaks in a POST body. */
    public function testAcceptsTheLogWrappedInJsonAndStoresTheExactText(): void
    {
        [$controller, $store] = $this->controller(static fn (): ?string => 'ilyag');
        $json = json_encode(['log' => self::LOG], JSON_THROW_ON_ERROR);
        assertFalse(str_contains($json, "\n"), 'the JSON body has no raw line breaks');

        [$status] = $this->upload($controller, $json);
        assertSame(201, $status);
        assertSame([self::LOG], array_column($store->reports(), 'body'));

        assertSame(400, $this->upload($controller, json_encode(['log' => "usage-log v1\nview\t0\t/threads/T1\n"], JSON_THROW_ON_ERROR))[0]);
        assertSame(400, $this->upload($controller, '{"other": 1}')[0]);
        assertSame(413, $this->upload($controller, json_encode(['log' => "usage-log v1\n" . str_repeat("view\t0\t/\n", 20000)], JSON_THROW_ON_ERROR))[0]);
        assertSame(1, count($store->reports()));
    }

    public function testRefusalsUseTheDocumentedStatusesAndStoreNothing(): void
    {
        [$controller, $store] = $this->controller(static fn (): ?string => null);

        assertSame(405, $this->upload($controller, self::LOG, 'GET')[0]);
        assertSame(403, $this->upload($controller, self::LOG, 'POST', null)[0]);
        assertSame(403, $this->upload($controller, self::LOG, 'POST', 'cross-site')[0]);
        assertSame(413, $this->upload($controller, "usage-log v1\n" . str_repeat("view\t0\t/\n", 20000))[0]);
        [$status, $payload] = $this->upload($controller, "usage-log v1\nview\t0\t/threads/T1\n");
        assertSame(400, $status);
        assertSame('invalid_log', $payload['code']);
        assertSame(0, count($store->reports()));
    }

    public function testRateLimitAnswers429WithRetryAfterAndAFullStoreAnswers503(): void
    {
        [$limited] = $this->controller(static fn (): ?string => null, uploadsPerHour: 1);
        assertSame(201, $this->upload($limited, self::LOG)[0]);
        [$status, $payload] = $this->upload($limited, self::LOG);
        assertSame(429, $status);
        assertSame('rate_limited', $payload['code']);
        assertTrue($payload['retry_after'] > 3500 && $payload['retry_after'] <= 3600);

        [$full, $fullStore] = $this->controller(static fn (): ?string => null, maxStoreBytes: 20);
        [$status, $payload] = $this->upload($full, self::LOG);
        assertSame(503, $status);
        assertSame('store_full', $payload['code']);
        assertSame(0, count($fullStore->reports()));
    }

    /** @return array{0: UsageReportsController, 1: UsageReportStore} */
    private function controller(\Closure $username, int $uploadsPerHour = UsageReportStore::UPLOADS_PER_HOUR, int $maxStoreBytes = UsageReportStore::MAX_STORE_BYTES): array
    {
        $store = new UsageReportStore(new PDO('sqlite::memory:'), uploadsPerHour: $uploadsPerHour, maxStoreBytes: $maxStoreBytes);
        $services = new RouteServices(
            sys_get_temp_dir() . '/usage-upload-unused.sqlite3',
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): ?array => null,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            static fn (): ?array => null,
        );

        return [new UsageReportsController($services, static fn (): UsageReportStore => $store, $username), $store];
    }

    /** @return array{0:int, 1:array<string, mixed>} */
    private function upload(UsageReportsController $controller, string $body, string $method = 'POST', ?string $fetchSite = 'same-origin'): array
    {
        $server = $_SERVER;
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_SEC_FETCH_SITE']);
        if ($fetchSite !== null) {
            $_SERVER['HTTP_SEC_FETCH_SITE'] = $fetchSite;
        }
        http_response_code(200);
        ob_start();
        try {
            $controller->upload($method, $body, '203.0.113.9', 'TestBrowser/1', time());
        } finally {
            $output = (string) ob_get_clean();
            $_SERVER = $server;
        }

        return [(int) http_response_code(), (array) json_decode($output, true)];
    }
}
