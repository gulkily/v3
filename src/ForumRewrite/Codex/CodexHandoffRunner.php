<?php

declare(strict_types=1);

namespace ForumRewrite\Codex;

use RuntimeException;

final class CodexHandoffRunner
{
    public function __construct(
        private readonly CodexHandoffStore $store,
        private readonly string $projectRoot,
        private readonly string $codexBin = 'codex',
    ) {
    }

    /**
     * @param array<string, mixed> $handoff
     * @return array<string, mixed>
     */
    public function runApproved(array $handoff): array
    {
        $handoffId = (string) ($handoff['handoff_id'] ?? '');
        if ($handoffId === '') {
            throw new RuntimeException('Codex handoff runner requires a handoff id.');
        }

        $prompt = trim((string) ($handoff['draft_text'] ?? ''));
        if ($prompt === '') {
            $failed = $this->store->updateStatus($handoffId, 'failed', [
                'failure' => 'missing_draft_text',
            ]);
            return [
                'handoff_id' => $handoffId,
                'status' => 'failed',
                'handoff' => $failed,
                'exit_code' => null,
            ];
        }

        $result = $this->runCodexExec($prompt);
        if ($result['exit_code'] === 0) {
            $completed = $this->store->updateStatus($handoffId, 'completed', [
                'exit_code' => 0,
                'stdout_tail' => $this->tail((string) $result['stdout']),
                'stderr_tail' => $this->tail((string) $result['stderr']),
            ]);

            return [
                'handoff_id' => $handoffId,
                'status' => 'completed',
                'handoff' => $completed,
                'exit_code' => 0,
            ];
        }

        $failed = $this->store->updateStatus($handoffId, 'failed', [
            'exit_code' => $result['exit_code'],
            'stdout_tail' => $this->tail((string) $result['stdout']),
            'stderr_tail' => $this->tail((string) $result['stderr']),
        ]);

        return [
            'handoff_id' => $handoffId,
            'status' => 'failed',
            'handoff' => $failed,
            'exit_code' => $result['exit_code'],
        ];
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    private function runCodexExec(string $prompt): array
    {
        $command = sprintf(
            '%s exec --json --sandbox workspace-write %s',
            escapeshellarg($this->codexBin),
            escapeshellarg($prompt)
        );
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes, $this->projectRoot);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start Codex handoff runner.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => (int) $exitCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }

    private function tail(string $text): string
    {
        $text = trim($text);
        if (strlen($text) <= 2000) {
            return $text;
        }

        return substr($text, -2000);
    }
}
