<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

/** File counts need tree changes, not the line diffs computed by --shortstat. */
final class CommitHistoryReader
{
    /** @return iterable<array{sha:string,author_name:string,author_email:string,committed_at:string,subject:string,file_count:int}> */
    public function rows(string $repositoryRoot): iterable
    {
        if (!is_dir($repositoryRoot . '/.git')) return;
        // Spool before yielding: a failed Git invocation must not publish partial history.
        // tmpfile is file-backed and automatically removed on every exit path.
        $output = tmpfile();
        if ($output === false) throw new \RuntimeException('Cannot prepare commit history output.');
        try {
            $process = proc_open(['git', '-C', $repositoryRoot, 'log',
                '--format=%H%x1f%an%x1f%ae%x1f%cd%x1f%s', '--date=format:%Y-%m-%dT%H:%M:%SZ', '--raw'],
                [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => ['file', '/dev/null', 'w']], $pipes);
            if (!is_resource($process)) return;
            if (proc_close($process) !== 0) return;
            rewind($output);
            $pending = null;
            while (($line = fgets($output)) !== false) {
                // exec(), used by the former reader, also removed trailing whitespace.
                $line = rtrim($line);
                $fields = explode("\x1f", $line);
                if (count($fields) === 5 && preg_match('/^[0-9a-f]{40}$/', $fields[0]) === 1) {
                    if ($pending !== null) yield $pending;
                    [$sha, $name, $email, $date, $subject] = $fields;
                    $pending = ['sha'=>$sha, 'author_name'=>$name, 'author_email'=>$email,
                        'committed_at'=>$date, 'subject'=>$subject, 'file_count'=>0];
                } elseif ($pending !== null && preg_match('/^:+[0-7]{6} /', $line) === 1) {
                    $pending['file_count']++;
                }
            }
            if (!feof($output)) throw new \RuntimeException('Cannot read commit history output.');
            if ($pending !== null) yield $pending;
        } finally {
            fclose($output);
        }
    }
}
