<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\Support\BoundedProcess;
use ForumRewrite\Support\OperationBudget;
use ForumRewrite\Support\PhpCli;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use InvalidArgumentException;
use RuntimeException;

/**
 * Runs `./v3 import-instance` for the Tools → Import page through the task worker.
 *
 * The queue holds no payload, so each request is a private `Key: value` file named by its
 * deduplication key, beside the queue database. The worker starts the unchanged import
 * script as a child process, so the import keeps its own supervisor, locks and limits, and
 * records each output line as task progress. Destination paths come from the worker, never
 * from the request.
 */
final class InstanceImportTask
{
    public const ACTIONS = ['import', 'resume', 'cancel'];

    /** A few seconds past the import's own 300-second limit and cleanup. */
    private const DEADLINE_SECONDS = 330;
    private const MAX_OUTPUT_BYTES = 4194304;
    private const KEY_PREFIX = 'instance-import-';

    public function __construct(private readonly string $stateDirectory)
    {
    }

    public static function forQueue(string $queueDatabasePath): self
    {
        return new self(dirname($queueDatabasePath) . '/instance_import');
    }

    public function sources(): InstanceImportSources
    {
        return new InstanceImportSources($this->stateDirectory);
    }

    public function stateDirectory(): string
    {
        return $this->stateDirectory;
    }

    /**
     * @return array{enqueued: bool, task_id: int, message: string}
     */
    public function queue(SqliteTaskQueueStore $store, string $action, ?string $source, string $actor): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Unknown import action.');
        }
        $source = $source === null ? null : trim($source);
        if ($action === 'import' && ($source === null || $source === '')) {
            throw new InvalidArgumentException('Enter a source URL or hostname.');
        }
        if ($source !== null && (preg_match('/[\x00-\x1F\x7F]/', $source) === 1 || strlen($source) > 2048 || str_starts_with($source, '-'))) {
            throw new InvalidArgumentException('That source is not a URL or hostname.');
        }
        $latest = $store->latestByType(SqliteTaskQueueStore::INSTANCE_IMPORT);
        if ($latest !== null && in_array($latest['status'] ?? null, ['queued', 'running'], true)) {
            return ['enqueued' => false, 'task_id' => (int) $latest['id'], 'message' => 'An import is already queued or running.'];
        }
        $key = self::KEY_PREFIX . gmdate('Ymd\THis') . '-' . bin2hex(random_bytes(4));
        $this->write($this->requestPath($key), [
            'Action' => $action,
            'Source' => $action === 'import' ? (string) $source : '',
            'Requested-By' => $actor,
            'Requested-At' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
        $task = $store->enqueue(SqliteTaskQueueStore::INSTANCE_IMPORT, $key, 1);
        if ($action === 'import') {
            $this->sources()->remember((string) $source);
        }

        return ['enqueued' => ($task['enqueued'] ?? false) === true, 'task_id' => (int) $task['id'], 'message' => 'Queued for the task worker.'];
    }

    /**
     * Run a claimed task. $progress receives each output line.
     *
     * @param array<string, mixed> $task
     * @param array{repository_root: string, database_path: string, static_html_root: string} $destination
     * @param callable(string):void $progress
     * @return array{exit_code: int, message: string, request: array<string, string>}
     */
    public function run(array $task, string $projectRoot, array $destination, callable $progress): array
    {
        $key = (string) ($task['deduplication_key'] ?? '');
        $request = $this->request($key);
        if ($request === null) {
            return ['exit_code' => 1, 'message' => 'The import request file is missing.', 'request' => []];
        }
        $command = [PhpCli::binary(), $projectRoot . '/scripts/import_instance.php'];
        $command[] = match ($request['Action'] ?? '') {
            'import' => $request['Source'] ?? '',
            'resume' => '--resume',
            'cancel' => '--cancel',
            default => throw new RuntimeException('Unknown import action in request.'),
        };
        $command[] = '--repository-root=' . $destination['repository_root'];
        $command[] = '--database-path=' . $destination['database_path'];
        if (($request['Action'] ?? '') !== 'cancel') {
            $command[] = '--static-html-root=' . $destination['static_html_root'];
        }

        $log = $this->logPath($key);
        $handle = fopen($log, 'w');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the import log.');
        }
        chmod($log, 0600);
        $buffer = '';
        $lastLine = '';
        $emit = static function (string $line) use ($progress, &$lastLine): void {
            $line = rtrim($line);
            if ($line === '') return;
            $lastLine = $line;
            $progress($line);
        };
        try {
            $result = BoundedProcess::run($command, new OperationBudget(self::DEADLINE_SECONDS), self::MAX_OUTPUT_BYTES, 'Instance import', $projectRoot,
                static function (int $stream, string $chunk) use ($handle, &$buffer, $emit): void {
                    fwrite($handle, $chunk);
                    $buffer .= $chunk;
                    while (($end = strpos($buffer, "\n")) !== false) {
                        $emit(substr($buffer, 0, $end));
                        $buffer = substr($buffer, $end + 1);
                    }
                });
            $emit($buffer);
            $exitCode = $result['exit_code'];
        } catch (\Throwable $error) {
            fwrite($handle, $error->getMessage() . "\n");
            $emit($error->getMessage());
            $exitCode = 1;
        } finally {
            fclose($handle);
        }

        return ['exit_code' => $exitCode, 'message' => $lastLine, 'request' => $request];
    }

    /** @return array<string, string>|null */
    public function request(string $key): ?array
    {
        if (!str_starts_with($key, self::KEY_PREFIX) || preg_match('/^[a-z0-9T-]+$/', $key) !== 1) {
            return null;
        }
        $path = $this->requestPath($key);
        if (!is_file($path)) {
            return null;
        }

        return self::parse((string) file_get_contents($path));
    }

    public function log(string $key): ?string
    {
        if ($this->request($key) === null || !is_file($this->logPath($key))) {
            return null;
        }

        return (string) file_get_contents($this->logPath($key));
    }

    /** @return array<string, string> */
    public static function parse(string $text): array
    {
        $fields = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^([A-Za-z][A-Za-z-]*): ?(.*)$/', rtrim($line, "\r"), $match) === 1) {
                $fields[$match[1]] = $match[2];
            }
        }

        return $fields;
    }

    /** @param array<string, string> $fields */
    private function write(string $path, array $fields): void
    {
        if (!is_dir($this->stateDirectory) && !mkdir($this->stateDirectory, 0700, true) && !is_dir($this->stateDirectory)) {
            throw new RuntimeException('Import state directory is not writable.');
        }
        $text = '';
        foreach ($fields as $name => $value) {
            $text .= $name . ': ' . $value . "\n";
        }
        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, $text) !== strlen($text) || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            throw new RuntimeException('Unable to save the import request.');
        }
    }

    private function requestPath(string $key): string
    {
        return $this->stateDirectory . '/' . $key . '.txt';
    }

    private function logPath(string $key): string
    {
        return $this->stateDirectory . '/' . $key . '.log';
    }
}
