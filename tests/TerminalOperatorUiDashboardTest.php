<?php

declare(strict_types=1);

final class TerminalOperatorUiDashboardTest
{
    public function testDashboardDelegatesToCanonicalReadOnlyCommands(): void
    {
        $directory = sys_get_temp_dir() . '/forum-tui-dashboard-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $fakeWhiptail = $directory . '/fake-whiptail';
        $logPath = $directory . '/calls.log';
        $statePath = $directory . '/menu-state';
        file_put_contents($fakeWhiptail, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf 'ARGS:%s\n' "$*" >> "$V3_TUI_TEST_LOG"
if [[ " $* " == *' --menu '* ]]; then
  count=0
  if [[ -f "$V3_TUI_TEST_STATE" ]]; then
    count="$(cat "$V3_TUI_TEST_STATE")"
  fi
  count=$((count + 1))
  printf '%s' "$count" > "$V3_TUI_TEST_STATE"
  case "$count" in
    1) printf 'status\n' >&2 ;;
    2) printf 'config\n' >&2 ;;
    3) printf 'flags\n' >&2 ;;
    *) printf 'exit\n' >&2 ;;
  esac
elif [[ " $* " == *' --textbox '* ]]; then
  textbox_file=''
  next_is_file=0
  for argument in "$@"; do
    if [[ "$next_is_file" == 1 ]]; then
      textbox_file="$argument"
      break
    fi
    if [[ "$argument" == '--textbox' ]]; then
      next_is_file=1
    fi
  done
  printf '%s\n' 'TEXTBOX:' >> "$V3_TUI_TEST_LOG"
  cat "$textbox_file" >> "$V3_TUI_TEST_LOG"
fi
BASH);
        chmod($fakeWhiptail, 0700);

        try {
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                'TERM=xterm V3_TUI_WHIPTAIL=' . escapeshellarg($fakeWhiptail)
                    . ' V3_TUI_TEST_LOG=' . escapeshellarg($logPath)
                    . ' V3_TUI_TEST_STATE=' . escapeshellarg($statePath)
                    . " script -qec './v3 tui' /dev/null"
            );
            $log = (string) file_get_contents($logPath);

            assertSame(0, $exitCode);
            assertSame('', $stderr);
            assertStringContains('ARGS:--title v3 terminal operator --menu', $log);
            assertStringContains('TEXTBOX:', $log);
            assertStringContains('v3 status', $log);
            assertStringContains('Private configuration (redacted)', $log);
            assertStringContains('Private config path:', $log);
            assertStringContains('/tools/feature-flags/', $log);
            assertStringNotContains('LLM_API_KEY = ', $stdout);
        } finally {
            @unlink($fakeWhiptail);
            @unlink($logPath);
            @unlink($statePath);
            @rmdir($directory);
        }
    }

    /** @return array{int,string,string} */
    private function runCommand(string $command): array
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
