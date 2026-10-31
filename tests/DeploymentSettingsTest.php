<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\DeploymentSettings;

final class DeploymentSettingsTest
{
    public function testEnvironmentThenPrivateFileThenDefaultForEveryKey(): void
    {
        $this->withPrivateFile([], function (string $file): void {
            foreach (DeploymentSettings::KEYS as $key) {
                assertSame(null, DeploymentSettings::value($key));
                assertSame('default', DeploymentSettings::source($key));
            }
        });

        $values = ['FORUM_SITE_ID' => 'qdb'];
        foreach (array_slice(DeploymentSettings::KEYS, 1) as $key) {
            $values[$key] = '/srv/file/' . strtolower($key);
        }
        $this->withPrivateFile($values, function (string $file) use ($values): void {
            foreach ($values as $key => $value) {
                assertSame($value, DeploymentSettings::value($key));
                assertSame('private file ' . $file, DeploymentSettings::source($key));

                putenv($key . '=' . ($key === 'FORUM_SITE_ID' ? 'chouse' : '/srv/env'));
                try {
                    assertSame($key === 'FORUM_SITE_ID' ? 'chouse' : '/srv/env', DeploymentSettings::value($key));
                    assertSame('environment', DeploymentSettings::source($key));
                } finally {
                    putenv($key);
                }

                putenv($key . '=');
                try {
                    assertSame($value, DeploymentSettings::value($key));
                } finally {
                    putenv($key);
                }
            }
        });
    }

    public function testPrivateFileSiteProfileDrivesActiveProfile(): void
    {
        $this->withPrivateFile(['FORUM_SITE_ID' => 'qdb'], function (): void {
            assertSame(SiteProfileRegistry::all()['qdb']['name'], SiteProfileRegistry::active()['name']);
        });
    }

    public function testUnknownSiteProfileInPrivateFileFailsWithFile(): void
    {
        $this->withPrivateFile(['FORUM_SITE_ID' => 'qbd'], function (string $file): void {
            $message = '';
            try {
                SiteProfileRegistry::active();
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage();
            }
            assertTrue(str_contains($message, 'Unknown FORUM_SITE_ID "qbd" in ' . $file));
            assertTrue(str_contains($message, 'qdb'));
        });
    }

    public function testUnknownEnvironmentSiteProfileKeepsDefaultFallback(): void
    {
        $this->withPrivateFile(['FORUM_SITE_ID' => 'qdb'], function (): void {
            putenv('FORUM_SITE_ID=not-a-real-site');
            try {
                assertSame(array_key_first(SiteProfileRegistry::all()), SiteProfileRegistry::active()['name']);
            } finally {
                putenv('FORUM_SITE_ID');
            }
        });
    }

    public function testRelativeOrNonStringPathsInPrivateFileAreRejected(): void
    {
        foreach ([['FORUM_DATABASE_PATH' => 'state/db.sqlite3'], ['FORUM_REPOSITORY_ROOT' => ['/srv']]] as $values) {
            $this->withPrivateFile($values, function () use ($values): void {
                $rejected = false;
                try {
                    DeploymentSettings::value(array_key_first($values));
                } catch (RuntimeException) {
                    $rejected = true;
                }
                assertTrue($rejected);
            });
        }
    }

    public function testPerCheckoutPrivateFileSelectsSettingsForThatCheckout(): void
    {
        $parent = sys_get_temp_dir() . '/forum-rewrite-deployment-' . bin2hex(random_bytes(6));
        $previous = getenv('FORUM_SECRETS_PATH');
        putenv('FORUM_SECRETS_PATH');
        putenv('FORUM_SITE_ID');
        DeploymentSettings::resetForTesting();
        try {
            mkdir($parent . '/forum-private/qdb', 0700, true);
            file_put_contents($parent . '/forum-private/qdb/secrets.php', "<?php\nreturn ['FORUM_SITE_ID' => 'qdb'];\n");
            file_put_contents($parent . '/forum-private/secrets.php', "<?php\nreturn ['FORUM_SITE_ID' => 'chouse'];\n");
            assertSame('qdb', DeploymentSettings::value('FORUM_SITE_ID', $parent . '/qdb'));
            assertSame('chouse', DeploymentSettings::value('FORUM_SITE_ID', $parent . '/zenmemes'));
        } finally {
            putenv($previous === false ? 'FORUM_SECRETS_PATH' : 'FORUM_SECRETS_PATH=' . $previous);
            DeploymentSettings::resetForTesting();
            exec('rm -rf ' . escapeshellarg($parent));
        }
    }

    public function testNewKeysStayOutOfTheGeneratedTemplate(): void
    {
        $template = \ForumRewrite\Support\PrivateConfigSchema::templateDefaults();
        foreach (DeploymentSettings::KEYS as $key) {
            assertSame(false, array_key_exists($key, $template));
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param callable(string):void $callback
     */
    private function withPrivateFile(array $values, callable $callback): void
    {
        $file = sys_get_temp_dir() . '/forum-rewrite-deployment-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($file, '<?php return ' . var_export($values, true) . ';');
        $previous = getenv('FORUM_SECRETS_PATH');
        $environment = [];
        foreach (DeploymentSettings::KEYS as $key) {
            $environment[$key] = getenv($key);
            putenv($key);
        }
        putenv('FORUM_SECRETS_PATH=' . $file);
        DeploymentSettings::resetForTesting();
        try {
            $callback($file);
        } finally {
            putenv($previous === false ? 'FORUM_SECRETS_PATH' : 'FORUM_SECRETS_PATH=' . $previous);
            foreach ($environment as $key => $value) {
                putenv($value === false ? $key : $key . '=' . $value);
            }
            DeploymentSettings::resetForTesting();
            @unlink($file);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected '
                . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}
