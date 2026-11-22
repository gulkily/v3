<?php

declare(strict_types=1);

use ForumRewrite\Deploy\AppUpdate;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueWorker;

require_once __DIR__ . '/../autoload.php';

final class AppUpdateTest
{
    public function testDeployFastForwardsToOriginMainAndLogsTheAttempt(): void
    {
        [$app, $seed] = $this->fixture();
        $old = $this->head($app);
        $new = $this->pushCommit($seed, 'second');

        $result = (new AppUpdate($app))->deploy('openpgp:test', AppUpdate::MODE_INSTANT);

        assertSame('deployed', $result['outcome']);
        assertSame($old, $result['old_commit']);
        assertSame($new, $result['new_commit']);
        assertSame($new, $this->head($app));
        $latest = (new AppUpdate($app))->latestAttempt();
        assertSame('deployed', $latest['Outcome']);
        assertSame('instant', $latest['Mode']);
        assertSame('openpgp:test', $latest['Actor']);
        assertSame($new, $latest['New-Commit']);
    }

    public function testEachDeployThatChangesTheCommitIsRecordedInTheHistory(): void
    {
        [$app, $seed] = $this->fixture();
        $first = $this->head($app);
        $update = new AppUpdate($app);
        assertSame([], $update->history());

        $second = $this->pushCommit($seed, 'second');
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT); // up to date: no entry
        $third = $this->pushCommit($seed, 'third');
        $update->deploy('cli:ops', AppUpdate::MODE_CLI);

        $history = $update->history();
        assertSame([$third, $second], array_column($history, 'new_commit'));
        assertSame([$second, $first], array_column($history, 'old_commit'));
        assertSame(['third', 'second'], array_column($history, 'subject'));
        assertSame(['deploy', 'deploy'], array_column($history, 'action'));
        assertSame('cli', $history[0]['mode']);
        assertSame('cli:ops', $history[0]['actor']);
        assertSame([$third], array_column($update->history(1), 'new_commit'));
        assertStringContains("Action: deploy\n", (string) file_get_contents($app . '/state/logs/deploy_history.txt'));
    }

    public function testHistoryStartsFromTheDeploysInTheAttemptLog(): void
    {
        [$app, $seed] = $this->fixture();
        $first = $this->head($app);
        $second = $this->pushCommit($seed, 'second');
        $third = $this->pushCommit($seed, 'third');
        $this->git($app, ['pull', '-q']);
        mkdir($app . '/state/logs', 0775, true);
        // An older rotated log without Read-Model, a refusal, an unreadable block, then a newer deploy.
        file_put_contents($app . '/state/logs/app_update.log.1',
            "Finished-At: 2026-11-01T10:00:00Z\nMode: cli\nActor: cli:ops\nOutcome: deployed\nOld-Commit: {$first}\nNew-Commit: {$second}\n\n");
        file_put_contents($app . '/state/logs/app_update.log',
            "Finished-At: 2026-11-02T10:00:00Z\nMode: instant\nOutcome: refused\nOld-Commit: {$second}\nNew-Commit: {$second}\n\n"
            . "Outcome: deployed\nOld-Commit: -\nNew-Commit: -\n\n"
            . "Finished-At: 2026-11-03T10:00:00Z\nMode: instant\nActor: openpgp:test\nOutcome: deployed\nOld-Commit: {$second}\nNew-Commit: {$third}\nRead-Model: current\n\n");

        $history = (new AppUpdate($app))->history();

        assertSame([$third, $second], array_column($history, 'new_commit'));
        assertSame(['2026-11-03T10:00:00Z', '2026-11-01T10:00:00Z'], array_column($history, 'deployed_at'));
        assertSame(['third', 'second'], array_column($history, 'subject'));
    }

    public function testCliHistoryListsVersionsNewestFirst(): void
    {
        [$app, $seed] = $this->fixture();
        $second = $this->pushCommit($seed, 'second');
        (new AppUpdate($app))->deploy('cli:ops', AppUpdate::MODE_CLI);

        [$code, $out] = $this->cli($app, ['--history']);

        assertSame(0, $code, $out);
        assertSame('Running: ' . substr($second, 0, 12) . "\nVersion: ", substr($out, 0, 31));
        assertStringContains(' deploy ' . substr($second, 0, 12) . " second\n", $out);
    }

    public function testRollbackReturnsToAnEarlierDeployedCommitAndTheNextDeployMovesForward(): void
    {
        [$app, $first, $second, $third] = $this->deployedTwice();
        $checks = 0;
        $update = new AppUpdate($app, null, static function () use (&$checks): array {
            $checks++;

            return ['status' => 'current', 'message' => ''];
        });

        $result = $update->rollback($second, 'openpgp:test', AppUpdate::MODE_INSTANT);

        assertSame('rolled_back', $result['outcome'], $result['message']);
        assertSame($third, $result['old_commit']);
        assertSame($second, $result['new_commit']);
        assertSame($second, $this->head($app));
        assertSame('main', trim($this->git($app, ['symbolic-ref', '--short', 'HEAD'])));
        assertSame(1, $checks);
        assertSame('rolled_back', $update->latestAttempt()['Outcome']);
        assertSame('current', $update->latestAttempt()['Read-Model']);
        $history = $update->history();
        assertSame(['rollback', 'deploy', 'deploy'], array_column($history, 'action'));
        assertSame([$second, $third, $second], array_column($history, 'new_commit'));

        // A short commit name works, and so does the version before the first recorded deploy.
        assertSame('rolled_back', $update->rollback(substr($first, 0, 12), 'openpgp:test', AppUpdate::MODE_INSTANT)['outcome']);
        assertSame($first, $this->head($app));

        $forward = $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        assertSame('deployed', $forward['outcome'], $forward['message']);
        assertSame($third, $this->head($app));
    }

    public function testRollbackKeepsLocalEditsToFilesItDoesNotTouch(): void
    {
        [$app, , $second] = $this->deployedTwice();
        file_put_contents($app . '/README.md', "app\nlocal edit\n");

        $result = (new AppUpdate($app))->rollback($second, 'openpgp:test', AppUpdate::MODE_INSTANT);

        assertSame('rolled_back', $result['outcome'], $result['message']);
        assertStringContains('Kept local changes to README.md.', $result['message']);
        assertSame("app\nlocal edit\n", file_get_contents($app . '/README.md'));
        assertSame('', trim($this->git($app, ['stash', 'list'])));
        assertTrue(!is_file($app . '/third.txt'));
    }

    public function testRollbackThatConflictsWithALocalEditChangesNothing(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/config.txt', "new line\n");
        $this->git($seed, ['add', '.']);
        $this->git($seed, ['commit', '-q', '-m', 'add config']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        $first = $this->head($app);
        $update = new AppUpdate($app);
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        $second = $this->head($app);
        // The file does not exist at the older commit, so the edit cannot be put back there.
        file_put_contents($app . '/config.txt', "new line\nlocal edit\n");

        $result = $update->rollback($first, 'openpgp:test', AppUpdate::MODE_INSTANT);

        assertSame('refused', $result['outcome'], $result['message']);
        assertSame('local_changes_conflict', $result['reason']);
        assertStringContains('Nothing was changed; roll back over SSH', $result['message']);
        assertSame($second, $this->head($app));
        assertSame("new line\nlocal edit\n", file_get_contents($app . '/config.txt'));
        assertSame('', trim($this->git($app, ['stash', 'list'])));
        assertSame(['deploy'], array_column($update->history(), 'action'));
    }

    public function testRollbackRefusesVersionsItMustNotMoveTo(): void
    {
        [$app, $first, $second, $third, $seed] = $this->deployedTwice();
        $update = new AppUpdate($app);
        $refusal = static function (string $commit) use ($update): ?string {
            $result = $update->rollback($commit, 'openpgp:test', AppUpdate::MODE_INSTANT);

            return $result['outcome'] === 'refused' ? $result['reason'] : $result['outcome'];
        };

        assertSame('already_running', $refusal($third));
        assertSame('not_deployed_here', $refusal(str_repeat('a', 40)));
        assertSame('not_deployed_here', $refusal('main'));
        assertSame('not_deployed_here', $refusal('--hard'));
        // On origin/main and fetched, but never deployed here.
        $fourth = $this->pushCommit($seed, 'fourth');
        $update->check();
        assertSame('not_deployed_here', $refusal($fourth));
        // Deployed here, but ahead of the running version after rolling back.
        $update->rollback($second, 'openpgp:test', AppUpdate::MODE_INSTANT);
        assertSame('not_behind', $refusal($third));
        assertSame($second, $this->head($app));

        $this->git($app, ['checkout', '-q', '-b', 'other']);
        assertSame('wrong_branch', $refusal($first));
        $this->git($app, ['checkout', '-q', 'main']);

        $lock = fopen($app . '/state/app_update.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            assertSame('busy', $refusal($first));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        assertSame($second, $this->head($app));
    }

    public function testRollbackPreviewListsWhatItUndoesAndChangesNothing(): void
    {
        [$app, $first, $second, $third] = $this->deployedTwice();
        $update = new AppUpdate($app);

        $preview = $update->rollbackPreview(substr($first, 0, 12));

        assertSame($first, $preview['commit']);
        assertSame(['third', 'second'], array_column($preview['commits'], 'subject'));
        assertSame([$third, $second], array_column($preview['commits'], 'commit'));
        assertSame(0, $preview['more']);
        assertSame([], $preview['warnings']);
        assertSame($third, $this->head($app));

        assertSame('already_running', $update->rollbackPreview($third)['reason']);
        assertSame('not_deployed_here', $update->rollbackPreview(str_repeat('a', 40))['reason']);
        assertSame(null, $update->rollbackPreview($third)['commit']);
    }

    public function testRollbackPreviewWarnsWhenTheRangeCrossesAFormatChange(): void
    {
        [$app, $seed] = $this->fixture();
        $update = new AppUpdate($app);
        $write = function (string $path, string $content) use ($seed): string {
            @mkdir(dirname($seed . '/' . $path), 0777, true);
            file_put_contents($seed . '/' . $path, $content);

            return $this->pushCommit($seed, 'change-' . md5($path . $content));
        };
        $metadata = 'src/ForumRewrite/ReadModel/ReadModelMetadata.php';
        $base = $write($metadata, "<?php\n    public const SCHEMA_VERSION = '55';\n");
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        $plain = $write('notes.txt', "notes\n");
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        assertSame([], $update->rollbackPreview($base)['warnings']);

        $write($metadata, "<?php\n    public const SCHEMA_VERSION = '56';\n");
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        $warnings = $update->rollbackPreview($plain)['warnings'];
        assertSame(1, count($warnings));
        assertStringContains('(56 back to 55)', $warnings[0]);

        $write('src/ForumRewrite/Canonical/RecordParser.php', "<?php\n");
        $write('docs/specs/record.md', "spec\n");
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        $warnings = $update->rollbackPreview($plain)['warnings'];
        assertSame(3, count($warnings));
        assertStringContains('reads records', $warnings[1]);
        assertStringContains('docs/specs/', $warnings[2]);
        // Warnings never block.
        assertSame('rolled_back', $update->rollback($plain, 'openpgp:test', AppUpdate::MODE_INSTANT)['outcome']);
    }

    public function testCliRollbackReturnsToAVersionFromTheHistory(): void
    {
        [$app, , $second, $third] = $this->deployedTwice();

        [$code, $out] = $this->cli($app, ['--rollback=' . substr($second, 0, 12)]);

        assertSame(0, $code, $out);
        assertStringContains("Outcome: rolled_back\n", $out);
        assertStringContains('Undoes-Commit: ' . substr($third, 0, 12) . ' ', $out);
        assertStringContains('Old-Commit: ' . $third . "\n", $out);
        assertStringContains('New-Commit: ' . $second . "\n", $out);
        assertSame($second, $this->head($app));
        [$again] = $this->cli($app, ['--rollback=' . $second]);
        assertSame(1, $again);
        assertSame(2, $this->cli($app, ['--rollback=main'])[0]);
    }

    public function testDeployChecksTheReadModelWithTheNewCodeAndQueuesARebuild(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'schema bump');
        $checks = 0;
        $check = static function () use (&$checks): array {
            $checks++;
            return ['status' => 'queued', 'message' => 'Read model: queued a rebuild for the new schema (updating)'];
        };

        $started = microtime(true);
        $result = (new AppUpdate($app, null, $check))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame(1, $checks);
        assertStringContains('queued a rebuild for the new schema', $result['message']);
        assertSame('queued', (new AppUpdate($app))->latestAttempt()['Read-Model']);
        assertTrue(microtime(true) - $started < 30.0);
    }

    public function testDeployWithoutChangesSkipsTheReadModelCheck(): void
    {
        [$app] = $this->fixture();
        $checks = 0;
        $check = static function () use (&$checks): array {
            $checks++;
            return ['status' => 'current', 'message' => ''];
        };

        $result = (new AppUpdate($app, null, $check))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('up_to_date', $result['outcome']);
        assertSame(0, $checks);
        assertSame('-', (new AppUpdate($app))->latestAttempt()['Read-Model']);
    }

    public function testDeployWithoutTheCheckScriptRecordsUnknown(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'second');

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame('unknown', (new AppUpdate($app))->latestAttempt()['Read-Model']);
    }

    /** Under the web server PHP_BINARY is php-fpm or php-cgi; the check runs the CLI PHP instead. */
    public function testReadModelCheckRunsTheCommandLinePhp(): void
    {
        [$app, $seed] = $this->fixture();
        mkdir($seed . '/scripts');
        file_put_contents($seed . '/scripts/task_queue.php', "<?php echo \"Read model: current\\n\";\n");
        $this->pushCommit($seed, 'second');
        $marker = dirname($app) . '/php-cli-used';
        $wrapper = dirname($app) . '/php-cli';
        file_put_contents($wrapper, "#!/bin/sh\ntouch " . escapeshellarg($marker) . "\nexec " . escapeshellarg(PHP_BINARY) . " \"\$@\"\n");
        chmod($wrapper, 0755);
        $old = getenv('FORUM_PHP_CLI');
        putenv('FORUM_PHP_CLI=' . $wrapper);
        try {
            $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);
        } finally {
            putenv($old === false ? 'FORUM_PHP_CLI' : 'FORUM_PHP_CLI=' . $old);
        }

        assertSame('deployed', $result['outcome']);
        assertTrue(is_file($marker), 'the read-model check ran through FORUM_PHP_CLI');
        assertSame('current', (new AppUpdate($app))->latestAttempt()['Read-Model']);
    }

    public function testDeployReportsUpToDateWithoutChanges(): void
    {
        [$app] = $this->fixture();
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('up_to_date', $result['outcome']);
        assertSame($head, $this->head($app));
    }

    public function testInspectNeverContactsTheRemote(): void
    {
        [$app, $seed, $remote] = $this->fixture();
        $this->pushCommit($seed, 'unseen');
        // A remote that cannot be reached proves inspect() does not fetch.
        $this->git($app, ['remote', 'set-url', 'origin', $remote . '-missing']);

        $state = (new AppUpdate($app))->inspect();

        assertSame(0, $state['behind']);
        assertSame('main', $state['branch']);
        assertSame(0, $state['local_changes']);
        assertSame(false, $state['busy']);
    }

    public function testCheckFetchesAndReportsBehindCount(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'one');
        $this->pushCommit($seed, 'two');
        $head = $this->head($app);

        $result = (new AppUpdate($app))->check();

        assertSame('checked', $result['outcome']);
        assertSame(2, $result['state']['behind']);
        assertTrue($result['state']['last_checked_at'] !== null, 'Expected a last-checked time after fetching.');
        assertSame($head, $this->head($app));
    }

    public function testCheckWithMaxAgeReusesARecentFetch(): void
    {
        [$app, $seed] = $this->fixture();
        $update = new AppUpdate($app);
        $update->check();
        $this->pushCommit($seed, 'after the first check');

        $recent = $update->check(60, 600);
        assertSame('checked', $recent['outcome']);
        assertSame('recent', $recent['reason']);
        assertSame(0, $recent['state']['behind']);

        // The Deploy page's button passes no maximum age and always fetches.
        $forced = $update->check(60);
        assertSame(null, $forced['reason']);
        assertSame(1, $forced['state']['behind']);
    }

    public function testCheckWithMaxAgeFetchesWhenTheLastFetchIsOld(): void
    {
        [$app, $seed] = $this->fixture();
        $update = new AppUpdate($app);
        $update->check();
        $this->pushCommit($seed, 'after an old check');
        touch($app . '/.git/FETCH_HEAD', time() - 3600);

        $result = $update->check(60, 600);

        assertSame(null, $result['reason']);
        assertSame(1, $result['state']['behind']);
    }

    public function testInspectListsIncomingCommitsAndSizeAfterACheck(): void
    {
        [$app, $seed] = $this->fixture();
        $first = $this->pushCommit($seed, 'one');
        $second = $this->pushCommit($seed, 'two');

        $before = (new AppUpdate($app))->inspect();
        assertSame([], $before['incoming_commits'], 'Nothing is incoming before a fetch.');
        assertSame(null, $before['incoming_stat']);

        (new AppUpdate($app))->check();
        $state = (new AppUpdate($app))->inspect();

        assertSame([$second, $first], array_column($state['incoming_commits'], 'commit'));
        assertSame(['two', 'one'], array_column($state['incoming_commits'], 'subject'));
        assertSame('Test', $state['incoming_commits'][0]['author']);
        assertTrue(preg_match('/^\d{4}-\d{2}-\d{2}$/', $state['incoming_commits'][0]['date']) === 1, 'Expected a YYYY-MM-DD date.');
        assertSame(0, $state['incoming_more']);
        assertSame(['files' => 2, 'insertions' => 2, 'deletions' => 0], $state['incoming_stat']);
        assertSame([], $state['incoming_local_overlap']);
    }

    public function testIncomingCommitsStopAtTheLimitAndCountTheRest(): void
    {
        [$app, $seed] = $this->fixture();
        for ($i = 1; $i <= AppUpdate::INCOMING_LIMIT + 3; $i++) {
            $this->pushCommit($seed, 'c' . $i);
        }

        $state = (new AppUpdate($app))->check()['state'];

        assertSame(AppUpdate::INCOMING_LIMIT, count($state['incoming_commits']));
        assertSame('c' . (AppUpdate::INCOMING_LIMIT + 3), $state['incoming_commits'][0]['subject']);
        assertSame(3, $state['incoming_more']);
    }

    public function testIncomingLeavesOutMergeCommits(): void
    {
        [$app, $seed] = $this->fixture();
        $this->git($seed, ['checkout', '-q', '-b', 'topic']);
        $this->pushCommit($seed, 'on-topic');
        $this->git($seed, ['checkout', '-q', 'main']);
        $this->git($seed, ['merge', '-q', '--no-ff', '-m', 'Merge branch topic', 'topic']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);

        $state = (new AppUpdate($app))->check()['state'];

        assertSame(2, $state['behind']);
        assertSame(['on-topic'], array_column($state['incoming_commits'], 'subject'));
        assertSame(0, $state['incoming_more']);
    }

    public function testIncomingNamesLocallyEditedFilesTheUpdateAlsoChanges(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "app\nremote line\n");
        $this->git($seed, ['commit', '-q', '-am', 'readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        $this->pushCommit($seed, 'other');
        file_put_contents($app . '/README.md', "app on the server\n");
        file_put_contents($app . '/.gitignore', "state/\nlocal\n");

        $state = (new AppUpdate($app))->check()['state'];

        assertSame(['README.md'], $state['incoming_local_overlap']);
    }

    public function testKeepsLocalChangesToFilesTheUpdateDoesNotTouch(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/README.md', "edited on the server\n");

        $state = (new AppUpdate($app))->inspect();
        assertSame(['README.md'], $state['changed_files']);
        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame($new, $this->head($app));
        assertSame("edited on the server\n", file_get_contents($app . '/README.md'));
        assertStringContains('Kept local changes to README.md', $result['message']);
        assertSame('', trim($this->git($app, ['stash', 'list'])));
    }

    public function testKeepsLocalEditsOnOtherLinesOfAFileTheUpdateChanges(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "one\ntwo\nthree\nfour\nfive\nsix\n");
        $this->git($seed, ['commit', '-q', '-am', 'longer readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);
        file_put_contents($seed . '/README.md', "one\ntwo\nthree\nfour\nfive\nSIX FROM MAIN\n");
        $this->git($seed, ['commit', '-q', '-am', 'edit last line']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        $new = $this->head($seed);
        file_put_contents($app . '/README.md', "ONE ON SERVER\ntwo\nthree\nfour\nfive\nsix\n");

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame($new, $this->head($app));
        assertSame("ONE ON SERVER\ntwo\nthree\nfour\nfive\nSIX FROM MAIN\n", file_get_contents($app . '/README.md'));
        assertSame('', trim($this->git($app, ['stash', 'list'])));
    }

    public function testAssetBypassReleaseKeepsServerEditsToHtaccess(): void
    {
        // The release that changed the managed block and the asset condition, deployed over
        // a server edit at the end of public/.htaccess and one just below the managed block.
        $before = (string) file_get_contents(__DIR__ . '/fixtures/asset_bypass/htaccess_before.txt');
        $after = (string) file_get_contents(dirname(__DIR__) . '/public/.htaccess');
        foreach ([
            'end' => static fn (string $rules): string => $rules . "\n# Server: force HTTPS\nRewriteCond %{HTTPS} off\nRewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n",
            'directory index' => static fn (string $rules): string => str_replace("DirectoryIndex index.php index.html\n", "DirectoryIndex index.php\n", $rules),
        ] as $where => $edit) {
            [$app, $seed] = $this->fixture();
            mkdir($seed . '/public');
            file_put_contents($seed . '/public/.htaccess', $before);
            $this->git($seed, ['add', 'public/.htaccess']);
            $this->git($seed, ['commit', '-q', '-m', 'before asset bypass']);
            $this->git($seed, ['push', '-q', 'origin', 'main']);
            (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);
            assertSame($before, file_get_contents($app . '/public/.htaccess'));
            file_put_contents($seed . '/public/.htaccess', $after);
            $this->git($seed, ['commit', '-q', '-am', 'asset bypass']);
            $this->git($seed, ['push', '-q', 'origin', 'main']);
            $new = $this->head($seed);
            file_put_contents($app . '/public/.htaccess', $edit($before));

            $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

            if ($result['outcome'] !== 'deployed') throw new RuntimeException($where . ': ' . $result['outcome'] . ' ' . ($result['reason'] ?? '') . ' ' . $result['message']);
            assertSame($new, $this->head($app), $where);
            assertSame($edit($after), file_get_contents($app . '/public/.htaccess'), $where);
            assertSame('', trim($this->git($app, ['stash', 'list'])), $where);
        }
    }

    public function testConflictingLocalEditRollsBackWithTheEditIntact(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "from main\n");
        $this->git($seed, ['commit', '-q', '-am', 'change readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        file_put_contents($app . '/README.md', "edited on the server\n");
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('refused', $result['outcome']);
        assertSame('local_changes_conflict', $result['reason']);
        assertStringContains('README.md', $result['message']);
        assertSame($head, $this->head($app));
        assertSame("edited on the server\n", file_get_contents($app . '/README.md'));
        assertSame('', trim($this->git($app, ['stash', 'list'])));
        assertSame('', trim($this->git($app, ['diff', '--name-only', '--diff-filter=U'])));
    }

    public function testKeepsLocalChangesWithoutAGitIdentityConfigured(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/README.md', "edited on the server\n");
        $previous = getenv('GIT_CONFIG_GLOBAL');
        // No global identity, and no guessing from the host name: as on a
        // shared host where Git cannot auto-detect an email address.
        putenv('GIT_CONFIG_GLOBAL=/dev/null');
        putenv('GIT_CONFIG_NOSYSTEM=1');
        putenv('GIT_CONFIG_COUNT=1');
        putenv('GIT_CONFIG_KEY_0=user.useConfigOnly');
        putenv('GIT_CONFIG_VALUE_0=true');
        try {
            $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);
        } finally {
            putenv($previous === false ? 'GIT_CONFIG_GLOBAL' : 'GIT_CONFIG_GLOBAL=' . $previous);
            foreach (['GIT_CONFIG_NOSYSTEM', 'GIT_CONFIG_COUNT', 'GIT_CONFIG_KEY_0', 'GIT_CONFIG_VALUE_0'] as $name) {
                putenv($name);
            }
        }

        assertSame('deployed', $result['outcome'], $result['message']);
        assertSame($new, $this->head($app));
        assertSame("edited on the server\n", file_get_contents($app . '/README.md'));
    }

    public function testUntrackedFilesDoNotBlockADeploy(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/notes.local', "scratch\n");

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame($new, $this->head($app));
        assertSame("scratch\n", file_get_contents($app . '/notes.local'));
    }

    public function testRefusesOffTheMainBranch(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'remote');
        $this->git($app, ['checkout', '-q', '-b', 'hotfix']);
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('wrong_branch', $result['reason']);
        assertSame($head, $this->head($app));
        assertSame('hotfix', trim($this->git($app, ['symbolic-ref', '--short', 'HEAD'])));
    }

    public function testRefusesDivergedHistory(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/local.txt', "server-only commit\n");
        $this->git($app, ['add', 'local.txt']);
        $this->git($app, ['commit', '-q', '-m', 'local']);
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('refused', $result['outcome']);
        assertSame('diverged', $result['reason']);
        assertSame($head, $this->head($app));
    }

    public function testRefusesWhileAnotherDeployHoldsTheLock(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'remote');
        $head = $this->head($app);
        @mkdir($app . '/state', 0775, true);
        $lock = fopen($app . '/state/app_update.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $update = new AppUpdate($app);
            assertSame(true, $update->isBusy());
            $result = $update->deploy('cli', AppUpdate::MODE_QUEUED);
            assertSame('busy', $result['reason']);
            assertSame('busy', $update->check()['reason']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        assertSame($head, $this->head($app));
        assertSame('busy', (new AppUpdate($app))->latestAttempt()['Reason']);
    }

    public function testFetchFailureLeavesCheckoutUnchanged(): void
    {
        [$app, , $remote] = $this->fixture();
        $head = $this->head($app);
        $this->git($app, ['remote', 'set-url', 'origin', $remote . '-missing']);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('failed', $result['outcome']);
        assertSame('fetch_failed', $result['reason']);
        assertSame($head, $this->head($app));
    }

    public function testFetchErrorsDoNotRevealRemoteCredentials(): void
    {
        [$app] = $this->fixture();
        $this->git($app, ['remote', 'set-url', 'origin', 'https://deployer:s3cr3t-token@127.0.0.1:9/repo.git']);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI, 10);

        assertSame('failed', $result['outcome']);
        assertSame(false, str_contains($result['message'], 's3cr3t-token'), $result['message']);
        assertSame(false, str_contains((string) file_get_contents($app . '/state/logs/app_update.log'), 's3cr3t-token'));
    }

    public function testNonCheckoutIsRefused(): void
    {
        $dir = $this->tempDir();
        $result = (new AppUpdate($dir))->deploy('cli', AppUpdate::MODE_CLI);
        assertSame('not_a_checkout', $result['reason']);
        assertSame(false, (new AppUpdate($dir))->inspect()['is_checkout']);
    }

    public function testQueuedDeployCoalescesAndRunsAsTheRequestingOperator(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'queued');
        $store = $this->store($app);
        $update = new AppUpdate($app);

        $first = $update->queue($store, 'openpgp:operator');
        $second = $update->queue($store, 'openpgp:operator');
        assertSame(true, $first['enqueued']);
        assertSame(false, $second['enqueued']);
        assertSame($first['task_id'], $second['task_id']);
        assertSame('queued', $update->latestAttempt()['Outcome']);

        $summary = $this->worker($store, $app)->run();

        assertSame(1, $summary['completed']);
        assertSame($new, $this->head($app));
        assertSame('completed', $store->findById($first['task_id'])['status']);
        $latest = $update->latestAttempt();
        assertSame('deployed', $latest['Outcome']);
        assertSame('queued', $latest['Mode']);
        assertSame('openpgp:operator', $latest['Actor']);
    }

    public function testQueuedDeployIsClaimedBeforeEarlierQueuedWork(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'priority');
        $store = $this->store($app);
        $rebuild = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $reply = $store->enqueue(SqliteTaskQueueStore::AGENT_REPLY, 'reply-1');
        $deploy = (new AppUpdate($app))->queue($store, 'openpgp:operator');
        $rebuilds = 0;

        $this->worker($store, $app, $rebuilds)->run(1);

        assertSame($new, $this->head($app));
        assertSame('completed', $store->findById($deploy['task_id'])['status']);
        assertSame(0, $rebuilds);
        assertSame('queued', $store->findById($rebuild['id'])['status']);
        assertSame('queued', $store->findById($reply['id'])['status']);
    }

    public function testOtherTaskTypesKeepTheirQueuedOrder(): void
    {
        [$app] = $this->fixture();
        $store = $this->store($app);
        $score = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score');
        $rebuild = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');

        $claimed = $store->claimNext(2);

        assertSame([$score['id'], $rebuild['id']], array_map(static fn (array $t): int => (int) $t['id'], $claimed));
    }

    public function testQueuedDeployRetriesWhenAnInstantDeployHoldsTheLock(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'busy');
        $store = $this->store($app);
        $queued = (new AppUpdate($app))->queue($store, 'openpgp:operator');
        @mkdir($app . '/state', 0775, true);
        $lock = fopen($app . '/state/app_update.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->worker($store, $app)->run();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        assertSame('queued', $store->findById($queued['task_id'])['status']);

        $this->worker($store, $app)->run();

        assertSame('completed', $store->findById($queued['task_id'])['status']);
        assertSame('openpgp:operator', (new AppUpdate($app))->latestAttempt()['Actor']);
    }

    public function testRefusedQueuedDeployFailsWithTheReason(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "from main\n");
        $this->git($seed, ['commit', '-q', '-am', 'change readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        file_put_contents($app . '/README.md', "edited\n");
        $store = $this->store($app);
        $queued = (new AppUpdate($app))->queue($store, 'openpgp:operator');

        $this->worker($store, $app)->run();

        $task = $store->findById($queued['task_id']);
        assertSame('failed', $task['status']);
        assertSame('local_changes_conflict', $task['failure_code']);
    }

    private function store(string $app): SqliteTaskQueueStore
    {
        return new SqliteTaskQueueStore(new PDO('sqlite:' . $app . '/state/tasks.sqlite3'));
    }

    private function worker(SqliteTaskQueueStore $store, string $app, int &$rebuilds = 0): TaskQueueWorker
    {
        return new TaskQueueWorker(
            $store,
            static function () use (&$rebuilds): void {
                $rebuilds++;
            },
            runAppUpdate: static fn (): array => (new AppUpdate($app))->runQueued(30),
        );
    }

    public function testStatusLinesIncludeTheReadModelCheck(): void
    {
        assertSame(['Latest-Attempt: none'], AppUpdate::latestAttemptLines(null));
        $base = ['Finished-At' => '2026-11-09T22:50:00Z', 'Outcome' => 'deployed', 'Mode' => 'cli'];
        assertSame(['Latest-Attempt: 2026-11-09T22:50:00Z deployed (cli)', 'Latest-Read-Model: current'],
            AppUpdate::latestAttemptLines($base + ['Read-Model' => 'current']));
        assertSame(['Latest-Attempt: 2026-11-09T22:50:00Z deployed (cli)'],
            AppUpdate::latestAttemptLines($base + ['Read-Model' => '-']));
    }

    public function testCliHelpAndStatusRun(): void
    {
        $root = dirname(__DIR__);
        exec('php ' . escapeshellarg($root . '/scripts/deploy.php') . ' --help 2>&1', $help, $code);
        assertSame(0, $code);
        assertStringContains('Usage: ./v3 deploy', implode("\n", $help));
        exec('php ' . escapeshellarg($root . '/scripts/deploy.php') . ' --bogus 2>&1', $bad, $badCode);
        assertSame(2, $badCode);
    }

    /**
     * A checkout that has deployed twice, so its history holds three versions.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string} app, first, second and third commit, seed clone
     */
    private function deployedTwice(): array
    {
        [$app, $seed] = $this->fixture();
        $first = $this->head($app);
        $update = new AppUpdate($app);
        $second = $this->pushCommit($seed, 'second');
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);
        $third = $this->pushCommit($seed, 'third');
        $update->deploy('openpgp:test', AppUpdate::MODE_INSTANT);

        return [$app, $first, $second, $third, $seed];
    }

    /**
     * Runs the real scripts/deploy.php against the fixture checkout.
     *
     * @param list<string> $args
     * @return array{0: int, 1: string} exit code, combined output
     */
    private function cli(string $app, array $args): array
    {
        if (!is_dir($app . '/scripts')) {
            mkdir($app . '/scripts');
            copy(dirname(__DIR__) . '/scripts/deploy.php', $app . '/scripts/deploy.php');
            file_put_contents($app . '/autoload.php', '<?php require ' . var_export(dirname(__DIR__) . '/autoload.php', true) . ";\n");
        }
        exec('php ' . escapeshellarg($app . '/scripts/deploy.php') . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $lines, $code);

        return [$code, implode("\n", $lines) . "\n"];
    }

    /**
     * @return array{0: string, 1: string, 2: string} app clone, seed clone, bare remote
     */
    private function fixture(): array
    {
        $base = $this->tempDir();
        $remote = $base . '/remote.git';
        $seed = $base . '/seed';
        $app = $base . '/app';
        $this->run(['git', 'init', '-q', '--bare', '-b', 'main', $remote]);
        $this->run(['git', 'init', '-q', '-b', 'main', $seed]);
        file_put_contents($seed . '/README.md', "app\n");
        file_put_contents($seed . '/.gitignore', "state/\n");
        $this->git($seed, ['add', '.']);
        $this->git($seed, ['commit', '-q', '-m', 'initial']);
        $this->git($seed, ['remote', 'add', 'origin', $remote]);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        $this->run(['git', 'clone', '-q', $remote, $app]);
        mkdir($app . '/state', 0775, true);

        return [$app, $seed, $remote];
    }

    private function pushCommit(string $seed, string $name): string
    {
        file_put_contents($seed . '/' . $name . '.txt', $name . "\n");
        $this->git($seed, ['add', '.']);
        $this->git($seed, ['commit', '-q', '-m', $name]);
        $this->git($seed, ['push', '-q', 'origin', 'main']);

        return $this->head($seed);
    }

    private function head(string $dir): string
    {
        return trim($this->git($dir, ['rev-parse', 'HEAD']));
    }

    /**
     * @param list<string> $args
     */
    private function git(string $dir, array $args): string
    {
        return $this->run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', '-C', $dir, ...$args]);
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command): string
    {
        $pipes = [];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException(implode(' ', $command) . ' failed: ' . $err);
        }

        return (string) $out;
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/app-update-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
