<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** Owns one newt dialog; resizing requires recreating its layout. */
final class TerminalDialog
{
    /** @return array{int,int} */
    public static function size(): array
    {
        $size = trim(self::stty(['size']));
        $parts = array_map('intval', explode(' ', $size));
        return count($parts) === 2 && min($parts) > 0 ? $parts : [24, 80];
    }

    /** @param list<string> $arguments @return array{int,string} */
    public static function run(array $arguments): array
    {
        $terminal = stream_isatty(STDIN);
        $size = $terminal ? self::size() : [24, 80];
        $settings = $terminal ? trim(self::stty(['-g'])) : '';
        $command = [getenv('V3_TUI_WHIPTAIL') ?: 'whiptail', '--output-fd', '3', ...$arguments];
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 3 => ['pipe', 'w'], 2 => ['redirect', 3]], $pipes);
        if (!is_resource($process)) {
            return [1, ''];
        }
        stream_set_blocking($pipes[3], false);
        $value = '';
        $resized = false;
        while (true) {
            $value .= stream_get_contents($pipes[3]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if ($terminal && self::size() !== $size) {
                $resized = true;
                proc_terminate($process);
                usleep(100000);
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                break;
            }
            usleep(50000);
        }
        $value .= stream_get_contents($pipes[3]);
        fclose($pipes[3]);
        $closed = proc_close($process);
        if ($resized) {
            if ($settings !== '') {
                self::stty([$settings]);
            }
            fwrite(STDOUT, "\033[0m\033[?25h\033[2J\033[H");
            return [75, ''];
        }
        return [$status['exitcode'] >= 0 ? $status['exitcode'] : $closed, $value];
    }

    /** @param list<string> $arguments */
    private static function stty(array $arguments): string
    {
        $process = proc_open(['stty', ...$arguments], [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            return '';
        }
        $value = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
        return (string) $value;
    }
}
