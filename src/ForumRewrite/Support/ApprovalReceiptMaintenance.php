<?php

declare(strict_types=1);
namespace ForumRewrite\Support;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Security\ApprovalAcceptance;
use ForumRewrite\Security\ApprovalReceiptText;
use RuntimeException;

final class ApprovalReceiptMaintenance
{
    public function __construct(private string $root, private string $databasePath, private $checkpoint = null) {}

    public function inspect(): array
    {
        $rows = []; $blockers = []; $legacy = 0; $ids = [];
        foreach (glob($this->root . '/records/approval-acceptances/*') ?: [] as $file) {
            $name = basename($file);
            if (!preg_match('/^([A-Za-z0-9][A-Za-z0-9._-]*)\.(txt|json)$/D', $name, $match)) {
                $blockers[] = 'Unsupported receipt: ' . $name; continue;
            }
            if ($match[2] === 'json') $legacy++;
            $ids[$match[1]] = true;
        }
        foreach (array_keys($ids) as $id) {
            try {
                $grant = ApprovalAcceptance::grantPath($this->root, $id);
                $data = ApprovalAcceptance::read($this->root, $grant);
                $rows[] = ['post_id' => $id, 'paths' => ApprovalAcceptance::existingPaths($this->root, $id), 'state' => 'valid'];
                if ($data === null) throw new RuntimeException('Receipt disappeared; retry inspection.');
            } catch (\Throwable $e) { $blockers[] = $id . ': ' . $e->getMessage(); }
        }
        return ['receipts' => $rows, 'legacy_files' => $legacy, 'blockers' => $blockers,
            'archive_coverage' => 'unknown; only this live repository was inspected'];
    }

    public function convert(bool $dryRun): array
    {
        if ($dryRun) return $this->inspect() + ['status' => 'preview'];
        return (new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(function (): array {
            $journalPath = trim($this->git(['rev-parse', '--absolute-git-dir'])) . '/approval-receipt-conversion.json';
            if (is_link($journalPath)) throw new RuntimeException('Conversion journal must not be a symlink.');
            if (is_file($journalPath)) {
                $journal = json_decode((string)file_get_contents($journalPath), true, 32, JSON_THROW_ON_ERROR);
            } else {
                if (trim($this->git(['status', '--porcelain', '--untracked-files=all'])) !== '') {
                    throw new RuntimeException('Repository must be clean before conversion. Commit or preserve unrelated changes first.');
                }
                $report = $this->inspect();
                if ($report['blockers'] !== []) throw new RuntimeException(implode("\n", $report['blockers']));
                if ($report['legacy_files'] === 0) return $report + ['status' => 'unchanged'];
                $journal = ['version' => 1, 'base' => trim($this->git(['rev-parse', 'HEAD'])), 'token' => bin2hex(random_bytes(12)), 'entries' => []];
                foreach ($report['receipts'] as $row) {
                    $id = $row['post_id']; $new = ApprovalAcceptance::path($id); $old = substr($new, 0, -4) . '.json';
                    if (!in_array($old, $row['paths'], true)) continue;
                    $grant = ApprovalAcceptance::grantPath($this->root, $id);
                    $data = ApprovalAcceptance::read($this->root, $grant);
                    $journal['entries'][] = ['id' => $id, 'old' => base64_encode($this->readFile($old)),
                        'new' => base64_encode(ApprovalReceiptText::encode($data)),
                        'had_text' => is_file($this->root . '/' . $new), 'grant' => $grant,
                        'grant_hash' => hash('sha256', $this->readFile($grant))];
                }
                $this->atomicWrite($journalPath, json_encode($journal, JSON_THROW_ON_ERROR));
            }
            // Private recovery state is validated before it can name any working-tree path.
            if (($journal['version'] ?? null) !== 1 || !preg_match('/^[a-f0-9]{40}$/D', $journal['base'] ?? '')
                || !preg_match('/^[a-f0-9]{24}$/D', $journal['token'] ?? '') || empty($journal['entries'])) {
                throw new RuntimeException('Invalid conversion journal; preserve it for recovery.');
            }
            $paths = []; $entries = [];
            foreach ($journal['entries'] as $entry) {
                $new = ApprovalAcceptance::path($entry['id']); $old = substr($new, 0, -4) . '.json';
                $oldBytes = base64_decode($entry['old'], true); $newBytes = base64_decode($entry['new'], true);
                if ($oldBytes === false || $newBytes === false || !is_bool($entry['had_text'])) throw new RuntimeException('Invalid conversion journal bytes.');
                $grant = ApprovalAcceptance::grantPath($this->root, $entry['id']);
                if ($grant !== $entry['grant'] || hash('sha256', $this->readFile($grant)) !== $entry['grant_hash']) throw new RuntimeException('Grant changed during conversion; preserve journal and resolve edits.');
                $data = ApprovalReceiptText::decode($newBytes);
                ApprovalAcceptance::validate($data, $this->readFile($grant));
                if ($data['post_id'] !== $entry['id']) throw new RuntimeException('Journal receipt ID mismatch.');
                $paths[] = $old; $paths[] = $new;
                $entries[] = [$old, $new, $oldBytes, $newBytes, $entry['had_text']];
            }
            $head = trim($this->git(['rev-parse', 'HEAD']));
            $message = 'Convert approval receipts ' . $journal['token'];
            $committed = $head !== $journal['base'];
            if ($committed && (trim($this->git(['show', '-s', '--format=%P', 'HEAD'])) !== $journal['base']
                || trim($this->git(['show', '-s', '--format=%B', 'HEAD'])) !== $message)) {
                throw new RuntimeException('Repository HEAD changed during conversion; preserve journal and resolve before retry.');
            }
            $status = $this->git(['status', '--porcelain', '-z', '--untracked-files=all']);
            foreach (array_filter(explode("\0", $status)) as $line) {
                if (str_contains(substr($line, 0, 2), 'R') || str_contains(substr($line, 0, 2), 'C') || !in_array(substr($line, 3), $paths, true)) {
                    throw new RuntimeException('Unrelated repository changes block conversion retry; preserve them before retrying.');
                }
            }
            foreach ($entries as [$old, $new, $oldBytes, $newBytes, $hadText]) {
                // Reject intervening edits, including symlinks, instead of overwriting them.
                foreach ([$old => $oldBytes, $new => $newBytes] as $path => $expected) {
                    if (is_link($this->root . '/' . $path) || (file_exists($this->root . '/' . $path) && $this->readFile($path) !== $expected)) {
                        throw new RuntimeException('Receipt changed during conversion: ' . $path . '; preserve journal and resolve edits.');
                    }
                }
                if ($committed) {
                    if ($this->git(['show', 'HEAD:' . $new]) !== $newBytes || trim($this->git(['ls-tree', '--name-only', 'HEAD', '--', $old])) !== '') {
                        throw new RuntimeException('Committed conversion evidence mismatch; preserve journal.');
                    }
                } else {
                    if ($this->git(['show', $journal['base'] . ':' . $old]) !== $oldBytes) throw new RuntimeException('Original receipt differs from committed base.');
                    $baseText = trim($this->git(['ls-tree', '--name-only', $journal['base'], '--', $new]));
                    if (($baseText !== '') !== $hadText || ($hadText && $this->git(['show', $journal['base'] . ':' . $new]) !== $newBytes)) {
                        throw new RuntimeException('Original text differs from conversion journal.');
                    }
                }
            }
            $this->point('journal');
            if (!$committed) {
                foreach ($entries as [$old, $new, $oldBytes, $newBytes]) {
                    $this->atomicWrite($this->root . '/' . $new, $newBytes);
                    $this->point('text-written');
                    if (is_file($this->root . '/' . $old) && !unlink($this->root . '/' . $old)) throw new RuntimeException('Cannot remove converted legacy file; retry conversion.');
                }
                $this->git(['add', '-A', '--', 'records/approval-acceptances']);
                $this->git(['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid', 'commit', '--only', '-m', $message, '--', ...$paths]);
                $head = trim($this->git(['rev-parse', 'HEAD']));
            }
            // Restore expected working files after a committed interruption, but never overwrite edits.
            foreach ($entries as [$old, $new, $oldBytes, $newBytes]) {
                if (!is_file($this->root . '/' . $new)) $this->atomicWrite($this->root . '/' . $new, $newBytes);
                if (is_file($this->root . '/' . $old) && !unlink($this->root . '/' . $old)) throw new RuntimeException('Unable to finish converted receipt cleanup.');
            }
            $marker = new ReadModelStaleMarker($this->databasePath);
            $marker->mark(['reason' => 'approval receipt conversion', 'commit_sha' => $head]);
            try {
                $this->point('committed');
                (new ReadModelBuilder($this->root, $this->databasePath, new CanonicalRecordRepository($this->root)))->rebuild();
                $this->point('refreshed');
                $marker->clear();
            } catch (\Throwable $e) {
                throw new RuntimeException('Conversion committed at ' . $head . '; retry conversion to finish refresh. ' . $e->getMessage(), 0, $e);
            }
            if (!unlink($journalPath)) throw new RuntimeException('Conversion complete; retry to clear recovery journal.');
            return $this->inspect() + ['status' => 'converted', 'commit' => $head, 'converted' => count($entries)];
        });
    }

    private function point(string $name): void { if ($this->checkpoint !== null) ($this->checkpoint)($name); }
    private function readFile(string $path): string
    {
        $file = $this->root . '/' . $path;
        if (!is_file($file) || is_link($file)) throw new RuntimeException('Expected regular file: ' . $path);
        $bytes = file_get_contents($file);
        if ($bytes === false) throw new RuntimeException('Unable to read ' . $path);
        return $bytes;
    }
    private function atomicWrite(string $path, string $bytes): void
    {
        $temp = $path . '.tmp-' . bin2hex(random_bytes(6));
        try {
            $handle = fopen($temp, 'xb');
            if ($handle === false) throw new RuntimeException('Cannot create conversion file.');
            try {
                if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) throw new RuntimeException('Cannot write conversion file.');
                if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('Cannot sync conversion file.');
            } finally { fclose($handle); }
            if (!rename($temp, $path)) throw new RuntimeException('Cannot publish conversion file.');
        } finally { if (is_file($temp)) unlink($temp); }
    }
    private function git(array $args): string
    {
        $process = proc_open(['git', '-C', $this->root, ...$args], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to run Git.');
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Conversion Git operation failed; retry with the same repository. ' . trim($err));
        return $out;
    }
}
