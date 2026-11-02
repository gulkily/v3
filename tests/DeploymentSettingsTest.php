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

    public function testNoCodeReadsDeploymentSettingsDirectlyFromTheEnvironment(): void
    {
        $project = dirname(__DIR__);
        $keys = implode('|', DeploymentSettings::KEYS);
        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($project) + 1);
            if (!preg_match('#^(src|scripts|public)/.*\.(php|sh)$|^v3$#', $relative)
                || $relative === 'src/ForumRewrite/Support/DeploymentSettings.php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match('/getenv\(\s*[\'"](' . $keys . ')[\'"]\s*\)|\$_(SERVER|ENV)\[\s*[\'"](' . $keys . ')[\'"]|\$\{?(' . $keys . ')\b/', $contents, $match)) {
                $offenders[] = $relative . ': ' . $match[0];
            }
        }
        assertSame([], $offenders);
    }

    public function testCliUsesPrivateFileRepositoryAndDatabaseWithoutEnvironment(): void
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-deployment-cli-' . bin2hex(random_bytes(6));
        mkdir($root . '/repository', 0700, true);
        exec('cp -R ' . escapeshellarg(dirname(__DIR__) . '/tests/fixtures/parity_minimal_v1/records') . ' ' . escapeshellarg($root . '/repository/records'));
        exec('cd ' . escapeshellarg($root . '/repository') . ' && git init -q && git add . && git -c user.name=t -c user.email=t@t commit -qm fixture');
        file_put_contents($root . '/secrets.php', '<?php return ' . var_export([
            'FORUM_SITE_ID' => 'qdb',
            'FORUM_REPOSITORY_ROOT' => $root . '/repository',
            'FORUM_DATABASE_PATH' => $root . '/state/read.sqlite3',
        ], true) . ';');
        mkdir($root . '/state', 0700);
        $environment = 'env ' . implode(' ', array_map(static fn (string $key): string => '-u ' . $key, DeploymentSettings::KEYS))
            . ' FORUM_SECRETS_PATH=' . escapeshellarg($root . '/secrets.php');
        try {
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ' . $environment . ' php scripts/rebuild_read_model.php 2>&1', $rebuild, $rebuildExit);
            assertSame(0, $rebuildExit);
            $pdo = new PDO('sqlite:' . $root . '/state/read.sqlite3');
            assertSame('1', (string) $pdo->query("SELECT COUNT(*) FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());

            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ' . $environment . ' php scripts/status.php --url=http://127.0.0.1:9 2>&1', $status);
            $status = implode("\n", $status);
            assertTrue(str_contains($status, 'Repository: ' . $root . '/repository'));
            assertTrue(str_contains($status, 'Read-model database: ' . $root . '/state/read.sqlite3'));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    public function testStatusReportsEachSourceAndWarnsAboutTrackedDeploymentEdits(): void
    {
        $parent = sys_get_temp_dir() . '/forum-rewrite-deployment-status-' . bin2hex(random_bytes(6));
        $checkout = $parent . '/qdb';
        try {
            mkdir($checkout . '/scripts', 0700, true);
            mkdir($checkout . '/public', 0700, true);
            copy(dirname(__DIR__) . '/autoload.php', $checkout . '/autoload.php');
            copy(dirname(__DIR__) . '/scripts/status.php', $checkout . '/scripts/status.php');
            copy(dirname(__DIR__) . '/public/.htaccess', $checkout . '/public/.htaccess');
            copy(dirname(__DIR__) . '/public/index.php', $checkout . '/public/index.php');
            exec('cp -R ' . escapeshellarg(dirname(__DIR__) . '/src') . ' ' . escapeshellarg($checkout . '/src'));
            exec('cd ' . escapeshellarg($checkout) . ' && git init -q && git add . && git -c user.name=t -c user.email=t@t commit -qm checkout');
            mkdir($parent . '/forum-private/qdb', 0700, true);
            file_put_contents($parent . '/forum-private/qdb/secrets.php', '<?php return ' . var_export([
                'FORUM_SITE_ID' => 'qdb',
                'FORUM_REPOSITORY_ROOT' => $parent . '/repository',
                'LLM_API_KEY' => 'secret-must-not-print',
            ], true) . ';');
            $run = function (string $extra = '') use ($checkout): string {
                $output = [];
                exec('cd ' . escapeshellarg($checkout) . ' && env -u FORUM_SECRETS_PATH '
                    . implode(' ', array_map(static fn (string $key): string => '-u ' . $key, DeploymentSettings::KEYS))
                    . ' ' . $extra . ' php scripts/status.php --url=http://127.0.0.1:9 2>&1', $output);
                return implode("\n", $output);
            };

            $status = $run('FORUM_DATABASE_PATH=' . escapeshellarg($parent . '/read.sqlite3'));
            $file = $parent . '/forum-private/qdb/secrets.php';
            assertTrue(str_contains($status, 'Private config: ' . $file));
            assertTrue(str_contains($status, 'FORUM_SITE_ID: qdb (private file ' . $file . ')'));
            assertTrue(str_contains($status, 'FORUM_REPOSITORY_ROOT: ' . $parent . '/repository (private file ' . $file . ')'));
            assertTrue(str_contains($status, 'FORUM_DATABASE_PATH: ' . $parent . '/read.sqlite3 (environment)'));
            assertTrue(str_contains($status, 'FORUM_IMAGE_ROOT: built-in (default)'));
            assertTrue(!str_contains($status, 'secret-must-not-print'));
            assertTrue(!str_contains($status, 'has local edits'));

            file_put_contents($checkout . '/public/.htaccess', "\nSetEnv FORUM_SITE_ID qdb\n", FILE_APPEND);
            $status = $run();
            assertTrue(str_contains($status, 'WARN  public/.htaccess has local edits; a code reset discards them. Keep deployment settings in ' . $file . '.'));

            unlink($file);
            $status = $run();
            assertTrue(str_contains($status, 'Private config: ' . $parent . '/forum-private/secrets.php (missing)'));
            assertTrue(str_contains($status, 'FORUM_SITE_ID: zenmemes (default)'));
        } finally {
            exec('rm -rf ' . escapeshellarg($parent));
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
