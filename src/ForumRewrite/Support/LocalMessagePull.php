<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Messaging\PrivateMessageHistorySyncStore;
use ForumRewrite\Messaging\PrivateMessageStore;
use RuntimeException;

/**
 * Copies a member's own messages and transfers from a signed-in source into this clone.
 * Insert-only: local rows always win. A pass resumes from a private progress file and
 * removes it when it reaches the end, so the next pass rescans and picks up transfers
 * that arrived later for older messages.
 */
final class LocalMessagePull
{
    public const PAGES_PER_REQUEST = 10;

    public function __construct(
        private readonly PrivateMessageStore $messages,
        private readonly PrivateMessageHistorySyncStore $transfers,
        private readonly string $progressPath,
    ) {}

    /** @return array{done:bool,added:int,existing:int,mismatched:list<string>,transfers_added:int,transfers_mismatched:int,read_marked:int} */
    public function run(LocalDevelopmentRemote $remote, string $account, int $maxPages = self::PAGES_PER_REQUEST): array
    {
        $account = strtolower(trim($account));
        $progress = $this->progress($remote->source(), $account);
        $summary = ['done' => false, 'added' => 0, 'existing' => 0, 'mismatched' => [], 'transfers_added' => 0,
            'transfers_mismatched' => 0, 'read_marked' => 0];
        for ($page = 0; $page < $maxPages; $page++) {
            $result = $remote->export($progress['after_time'], $progress['after_id']);
            $sourceAccount = strtolower((string) ($result['account'] ?? ''));
            if ($sourceAccount !== $account) {
                $host = (string) parse_url($remote->source(), PHP_URL_HOST);
                throw new LocalPullFailure($host . ' has this key as "' . $sourceAccount . '", this clone as "' . $account
                    . '". Nothing was pulled. This clone shows mail only for its own name, so use a key named "' . $sourceAccount . '" here.',
                    ['source_account' => $sourceAccount, 'local_account' => $account]);
            }
            foreach ($result['messages'] ?? [] as $row) {
                $outcome = $this->messages->importEnvelope($row);
                if ($outcome === 'mismatch') {
                    $summary['mismatched'][] = (string) $row['message_id'];
                    continue;
                }
                $summary[$outcome]++;
                if (($row['read'] ?? false) === true && $row['recipient_username_token'] === $account && $row['sender_username_token'] !== $account) {
                    $this->messages->importReadMark($account, (string) $row['sender_username_token'], (string) $row['message_id']);
                    $summary['read_marked']++;
                }
            }
            foreach ($result['transfers'] ?? [] as $transfer) {
                $outcome = $this->transfers->importTransfer($account, $transfer);
                if ($outcome === 'added') $summary['transfers_added']++;
                if ($outcome === 'mismatch') $summary['transfers_mismatched']++;
            }
            $next = $result['next'] ?? null;
            if (!is_array($next)) {
                @unlink($this->progressPath);
                $summary['done'] = true;
                return $summary;
            }
            $progress = ['after_time' => (string) $next['after_time'], 'after_id' => (string) $next['after_id']];
            $this->save($remote->source(), $account, $progress);
        }
        return $summary;
    }

    /** @return array{after_time:string,after_id:string} */
    private function progress(string $source, string $account): array
    {
        $saved = is_file($this->progressPath) ? json_decode((string) file_get_contents($this->progressPath), true) : null;
        if (is_array($saved) && ($saved['source'] ?? null) === $source && ($saved['account'] ?? null) === $account
            && is_string($saved['after_time'] ?? null) && is_string($saved['after_id'] ?? null)) {
            return ['after_time' => $saved['after_time'], 'after_id' => $saved['after_id']];
        }
        return ['after_time' => '', 'after_id' => ''];
    }

    private function save(string $source, string $account, array $progress): void
    {
        $directory = dirname($this->progressPath);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot save pull progress in ' . $directory . '.');
        }
        $temporary = $this->progressPath . '.tmp';
        file_put_contents($temporary, json_encode(['source' => $source, 'account' => $account] + $progress, JSON_THROW_ON_ERROR));
        @chmod($temporary, 0600);
        rename($temporary, $this->progressPath);
    }
}
