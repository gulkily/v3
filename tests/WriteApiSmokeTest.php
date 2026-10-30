<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Application;
use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Agent\AgentIdentityService;
use ForumRewrite\Agent\SqliteAgentReplyGenerationStore;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\ReactionRecordParser;
use ForumRewrite\Security\AttributionCutoff;
use ForumRewrite\ReadModel\VerifiedAttribution;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\SqliteFastScoreStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\TaskQueue\AgentReplyTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\Write\LocalWriteService;

final class WriteApiSmokeTest
{
    public function testFastmodPanelAndSignedSelectionPersistWithoutContentWrites(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('fastmod-operator');
        $cookies = $_COOKIE;
        $post = $_POST;
        $appRoot = sys_get_temp_dir() . '/fastmod-app-' . bin2hex(random_bytes(6));
        mkdir($appRoot . '/prompts/fastmod', 0777, true);
        copy(dirname(__DIR__) . '/prompts/fastmod_introduction.txt', $appRoot . '/prompts/fastmod_introduction.txt');
        foreach (['meta', 'moderation'] as $id) {
            copy(dirname(__DIR__) . '/prompts/fastmod/' . $id . '.txt', $appRoot . '/prompts/fastmod/' . $id . '.txt');
        }
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $this->seedApprovedIdentity($root, $identity);
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            $head = $this->latestCommitSha($root);
            $prepared = $writer->changeFastmodRubrics(['rubrics' => ['meta']], $identity, $appRoot, false);
            assertSame(false, is_file($appRoot . '/state/private/fastmod_rubrics.json'));
            $input = ['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])];
            $tampered = $input;
            $tampered['canonical_record'] .= 'tampered';
            assertThrowsRuntime(fn () => $writer->changeFastmodRubrics($tampered, $identity, $appRoot, true), 'Prepared Fastmod change is invalid or expired.');
            $unsigned = $input;
            $unsigned['detached_signature'] = '';
            $rejected = false;
            try { $writer->changeFastmodRubrics($unsigned, $identity, $appRoot, true); }
            catch (RuntimeException) { $rejected = true; }
            assertSame(true, $rejected);
            assertSame(false, is_file($appRoot . '/state/private/fastmod_rubrics.json'));
            $result = $writer->changeFastmodRubrics($input, $identity, $appRoot, true);
            assertSame(['meta'], $result['rubrics']);
            assertSame('["meta"]' . "\n", file_get_contents($appRoot . '/state/private/fastmod_rubrics.json'));
            assertSame($head, $this->latestCommitSha($root));
            assertThrowsRuntime(fn () => $writer->changeFastmodRubrics($input, $identity, $appRoot, true), 'Prepared post not found.');
            $selection = new \ForumRewrite\Scoring\FastmodSelection($appRoot);
            $config = \ForumRewrite\Scoring\FastScoringConfig::fromPrivateConfig([]);
            assertSame(['meta'], $selection->composition($config)['ids']);
            try {
                $selection->save([], $config);
                throw new LogicException('Empty selection saved');
            } catch (InvalidArgumentException) {
                assertSame(['meta'], $selection->enabled($config));
            }
            $_COOKIE = [];
            $application = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $anonymous = $this->renderMethod($application, 'GET', '/tools/fastmod/');
            assertStringNotContains('data-fastmod-form', $anonymous);
            assertStringContains('fastmod-prompt-meta', $anonymous);
            assertStringContains('root-approved identity', $anonymous);
            $_POST = ['rubrics' => ['meta']];
            $denied = json_decode($this->renderMethod($application, 'POST', '/api/prepare_fastmod_rubrics'), true);
            assertSame('error', $denied['status']);
            $_COOKIE = ['identity_hint' => $identity];
            $authorized = $this->renderMethod($application, 'GET', '/tools/fastmod/');
            assertStringContains('data-fastmod-form', $authorized);
            assertStringNotContains('<dialog', $authorized);
            assertStringContains('/assets/fastmod.', $authorized);
        } finally {
            $_COOKIE = $cookies;
            $_POST = $post;
            $this->deleteTree($key['home']);
            $this->deleteTree($appRoot);
            $this->deleteTree($root);
        }
    }

    public function testSignedProfileNamePreservesIdentityAndRejectsForgery(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('temporary-reader');
        $other = $this->createSigningKey('other-reader');
        try {
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
                featureFlags: $this->usernameChangeFlags(true));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $bootstrap = $root . '/records/identity/identity-' . str_replace(':', '-', $identity) . '.txt';
            $before = file_get_contents($bootstrap);
            $concurrent = $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => 'stale-name']);
            $prepared = $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => 'chosen-name']);
            $signed = $prepared + ['author_identity_id' => $identity,
                'detached_signature' => $this->signCanonicalRecord($other['home'], $prepared['canonical_record'])];
            try {
                $writer->finalizePreparedProfileName($signed);
                throw new RuntimeException('Expected signature rejection.');
            } catch (RuntimeException $e) {
                assertStringContains('signature verification failed', $e->getMessage());
            }
            $signed['detached_signature'] = $this->signCanonicalRecord($key['home'], $prepared['canonical_record']);
            $tampered = $signed;
            $tampered['canonical_record'] = str_replace('chosen-name', 'forged-name', $signed['canonical_record']);
            try {
                $writer->finalizePreparedProfileName($tampered);
                throw new RuntimeException('Expected mismatch rejection.');
            } catch (RuntimeException $e) {
                assertStringContains('mismatch', $e->getMessage());
            }
            assertSame('chosen-name', $writer->finalizePreparedProfileName($signed)['username']);
            try {
                $writer->finalizePreparedProfileName($concurrent + ['author_identity_id' => $identity,
                    'detached_signature' => $this->signCanonicalRecord($key['home'], $concurrent['canonical_record'])]);
                throw new RuntimeException('Expected stale-name rejection.');
            } catch (RuntimeException $e) { assertStringContains('newer username', $e->getMessage()); }

            assertSame($before, file_get_contents($bootstrap));
            $pdo = (new ReadModelConnection($db))->open();
            assertSame('chosen-name', ProfileRepository::byIdentityId($pdo, $identity)['username']);
            $pdo->exec("UPDATE profiles SET username = 'temporary-reader', username_token = 'temporary-reader'");
            $pdo->exec("UPDATE metadata SET value = '19' WHERE key = 'schema_version'");
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            assertStringContains('chosen-name', $this->renderMethod($app, 'GET', '/profiles/openpgp-' . substr($identity, 8)));
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key = 'schema_version'")->fetchColumn());
            $writer->linkIdentity(['public_key' => $other['public_key']]);
            assertSame('chosen-name', ProfileRepository::byIdentityId($pdo, $identity)['username']);
            $again = $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => 'final-name']);
            $writer->finalizePreparedProfileName($again + ['author_identity_id' => $identity,
                'detached_signature' => $this->signCanonicalRecord($key['home'], $again['canonical_record'])]);
            assertSame('final-name', ProfileRepository::byIdentityId($pdo, $identity)['username']);
            file_put_contents($root . '/' . $again['record_path'] . '.asc', 'forged');
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            assertSame('chosen-name', ProfileRepository::byIdentityId($pdo, $identity)['username']);

            assertSame($prepared['canonical_record'], file_get_contents($root . '/' . $prepared['record_path']));
            try {
                $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => "invalid\nheader"]);
                throw new RuntimeException('Expected name rejection.');
            } catch (RuntimeException $e) {
                assertStringContains('Invalid profile name', $e->getMessage());
            }
        } finally {
            $this->deleteTree($key['home']);
            $this->deleteTree($other['home']);
        }
    }

    public function testUsernameChangesAreDisabledByDefaultAndGateBothEndpoints(): void
    {
        assertSame(false, (new FeatureFlagEvaluator())->isEnabled(FeatureFlagRegistry::USERNAME_CHANGES_ENABLED));
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('gated-reader');
        try {
            $enabled = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
                featureFlags: $this->usernameChangeFlags(true));
            $disabled = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
                featureFlags: $this->usernameChangeFlags(false));
            $identity = $enabled->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            try {
                $disabled->prepareProfileName(['author_identity_id' => $identity, 'username' => 'blocked']);
                throw new RuntimeException('Expected prepare rejection while disabled.');
            } catch (RuntimeException $e) { assertStringContains('Username changes are disabled', $e->getMessage()); }
            $prepared = $enabled->prepareProfileName(['author_identity_id' => $identity, 'username' => 'late-toggle']);
            $signed = $prepared + ['author_identity_id' => $identity,
                'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])];
            try {
                $disabled->finalizePreparedProfileName($signed);
                throw new RuntimeException('Expected finalize rejection while disabled.');
            } catch (RuntimeException $e) { assertStringContains('Username changes are disabled', $e->getMessage()); }
            assertSame(false, is_file($root . '/' . $prepared['record_path']));
            assertSame('late-toggle', $enabled->finalizePreparedProfileName($signed)['username']);
        } finally {
            $this->deleteTree($key['home']);
        }
    }

    private function usernameChangeFlags(bool $enabled): FeatureFlagEvaluator
    {
        return new FeatureFlagEvaluator(new FeatureFlagRegistry(), [FeatureFlagRegistry::USERNAME_CHANGES_ENABLED => $enabled]);
    }

    public function testVerifiedProfileNamesWriteOneOrderedActivityRowEach(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('activity-reader');
        try {
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
                featureFlags: $this->usernameChangeFlags(true));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $paths = [];
            foreach (['first-name', 'second-name', 'forged-name'] as $name) {
                $prepared = $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => $name]);
                $signature = $this->signCanonicalRecord($key['home'], $prepared['canonical_record']);
                $writer->finalizePreparedProfileName($prepared + ['author_identity_id' => $identity, 'detached_signature' => $signature]);
                $paths[$name] = $prepared['record_path'];
            }
            file_put_contents($root . '/' . $paths['forged-name'] . '.asc', 'forged');
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();

            $pdo = (new ReadModelConnection($db))->open();
            $rows = $pdo->query("SELECT kind, record_family, action_key, post_id, thread_id, label, board_tags_json, author_identity_id, source_path FROM activity WHERE record_family = 'profile_name' ORDER BY created_at, action_key")->fetchAll(PDO::FETCH_ASSOC);
            assertSame(2, count($rows));
            assertSame('profile_name_change', $rows[0]['kind']);
            assertSame($paths['first-name'], $rows[0]['action_key']);
            assertSame($paths['first-name'], $rows[0]['source_path']);
            assertSame('Changed username from activity-reader to first-name', $rows[0]['label']);
            assertSame('Changed username from first-name to second-name', $rows[1]['label']);
            assertSame(null, $rows[0]['post_id']);
            assertSame(null, $rows[0]['thread_id']);
            assertSame($identity, $rows[0]['author_identity_id']);
            assertSame('["identity","profile-name"]', $rows[0]['board_tags_json']);
            assertSame('second-name', ProfileRepository::byIdentityId($pdo, $identity)['username']);
        } finally {
            $this->deleteTree($key['home']);
        }
    }

    public function testProfileNameActivityIsListedOnlyWhileUsernameChangesAreEnabled(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('visible-reader');
        $previousFlag = getenv('FORUM_USERNAME_CHANGES_ENABLED');
        try {
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
                featureFlags: $this->usernameChangeFlags(true));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $prepared = $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => 'shown-name']);
            $writer->finalizePreparedProfileName($prepared + ['author_identity_id' => $identity,
                'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])]);

            $service = static fn (bool $show): ActivityService => new ActivityService(
                static fn (): PDO => (new ReadModelConnection($db))->open(), $root, $db, $show);
            $ids = static fn (ActivityService $svc, string $view): array => array_column(
                array_filter($svc->fetchActivity($view, 'date', 'desc')['items'], static fn (array $i): bool => $i['record_family'] === 'profile_name'),
                'id');

            foreach (['all', 'identity'] as $view) {
                assertSame(1, count($ids($service(true), $view)));
                assertSame([], $ids($service(false), $view));
            }
            assertSame([], $ids($service(true), 'content'));
            assertSame(
                $service(false)->countActivityViewTotal('all') + 1,
                $service(true)->countActivityViewTotal('all')
            );
            $id = (int) $ids($service(true), 'all')[0];
            assertSame('Changed username from visible-reader to shown-name', $service(true)->fetchActivityDetail($id)['label']);
            assertSame(null, $service(false)->fetchActivityDetail($id));

            putenv('FORUM_USERNAME_CHANGES_ENABLED=false');
            assertStringNotContains('Changed username from', $this->renderMethod(new Application(dirname(__DIR__), $root, $db, $artifacts), 'GET', '/activity/?view=all'));
            putenv('FORUM_USERNAME_CHANGES_ENABLED=true');
            $page = $this->renderMethod(new Application(dirname(__DIR__), $root, $db, $artifacts), 'GET', '/activity/?view=all');
            assertStringContains('Changed username from visible-reader to shown-name', $page);
            assertStringContains('href="/profiles/openpgp-' . substr($identity, 8) . '"', $page);
            assertStringNotContains('href="/posts/"', $page);
            assertStringContains('profile name record', $page);
            assertStringContains('detached signature', $page);
            assertStringContains('Signer:' . "\n" . '                  ' . $identity, $page);
            assertStringContains('href="/source/current/records/public-keys/openpgp-', $page);
        } finally {
            putenv($previousFlag === false ? 'FORUM_USERNAME_CHANGES_ENABLED' : 'FORUM_USERNAME_CHANGES_ENABLED=' . $previousFlag);
            $this->deleteTree($key['home']);
        }
    }

    public function testProfileNameActivityReachesFeedsForteAndPendingDirectoryOnlyWhileEnabled(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('pending-reader');
        $previousFlag = getenv('FORUM_USERNAME_CHANGES_ENABLED');
        try {
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
                featureFlags: $this->usernameChangeFlags(true));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $prepared = $writer->prepareProfileName(['author_identity_id' => $identity, 'username' => 'pending-renamed']);
            $writer->finalizePreparedProfileName($prepared + ['author_identity_id' => $identity,
                'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])]);
            $profileHref = '/profiles/openpgp-' . substr($identity, 8);
            $label = 'Changed username from pending-reader to pending-renamed';
            $pdo = (new ReadModelConnection($db))->open();

            $latest = static fn (bool $include): ?string => array_values(array_filter(
                ProfileRepository::pendingDirectoryProfiles($pdo, $include),
                static fn (array $row): bool => $row['username'] === 'pending-renamed'
            ))[0]['latest_activity_label'] ?? null;
            assertSame(false, $latest(false) === $label);
            assertSame($label, $latest(true));
            assertSame(false, $latest(false) === $latest(true));

            $render = fn (string $path): string => $this->renderMethod(new Application(dirname(__DIR__), $root, $db, $artifacts), 'GET', $path);
            putenv('FORUM_USERNAME_CHANGES_ENABLED=false');
            foreach (['/activity/?view=all&format=rss', '/forte/activity/?view=all'] as $path) {
                assertStringNotContains($label, $render($path));
            }
            putenv('FORUM_USERNAME_CHANGES_ENABLED=true');
            $rss = $render('/activity/?view=all&format=rss');
            assertStringContains($label, $rss);
            assertStringContains($profileHref, $rss);
            assertStringNotContains('/posts/<', $rss);
            $forte = $render('/forte/activity/?view=all');
            assertStringContains($label, $forte);
            assertStringContains('href="' . $profileHref . '"', $forte);
            assertStringNotContains('data-post-id="pending-renamed"', $forte);
        } finally {
            putenv($previousFlag === false ? 'FORUM_USERNAME_CHANGES_ENABLED' : 'FORUM_USERNAME_CHANGES_ENABLED=' . $previousFlag);
            $this->deleteTree($key['home']);
        }
    }

    public function testSignedThreadReactionPreservesBytesAndRejectsTampering(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('reaction-signer');
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            foreach (['legacy', 'unified'] as $layout) {
            $id = 'thread-label-signed-' . $layout;
            $created = gmdate('Y-m-d\TH:i:s\Z');
            $record = $layout === 'legacy'
                ? "Record-ID: {$id}\nCreated-At: {$created}\nThread-ID: root-001\nOperation: add\nLabels: like\nAuthor-Identity-ID: {$identity}\n\n"
                : ReactionRecordParser::build('thread', $id, $created, 'root-001', ['like'], $identity);
            if ($layout === 'unified') {
                // The legacy record already counted this vote; clear it so the unified one is new.
                unlink($root . '/records/thread-labels/thread-label-signed-legacy.txt');
                unlink($root . '/records/thread-labels/thread-label-signed-legacy.txt.asc');
            }
            $signature = $this->signCanonicalRecord($key['home'], $record);
            $input = ['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $identity,
                'canonical_record' => $record, 'detached_signature' => $signature];
            $result = $writer->applyThreadTag($input);
            assertSame('yes', $result['wrote_record']);
            assertSame($record, file_get_contents($root . '/records/thread-labels/' . $id . '.txt'));
            assertSame($signature, file_get_contents($root . '/records/thread-labels/' . $id . '.txt.asc'));
            assertSame('no', $writer->applyThreadTag($input)['wrote_record']);
            foreach ([
                ['canonical_record' => str_replace($id, 'thread-label-changed', $record)],
                ['canonical_record' => str_replace($id, 'thread-label-../bad', $record)],
                ['canonical_record' => str_replace(['Labels: like', 'Tags: like'], ['Labels: flag', 'Tags: flag'], $record)],
                ['canonical_record' => str_replace('Target-Kind: thread', 'Target-Kind: post', $record)],
                ['canonical_record' => str_replace("Target-Kind: thread\nTarget-ID: root-001\n", "Thread-ID: root-001\n", $record)],
                ['detached_signature' => 'invalid'],
                ['detached_signature' => ''],
            ] as $change) {
                if (($change['canonical_record'] ?? null) === $record) continue;
                $rejected = false;
                try { $writer->applyThreadTag(array_replace($input, $change)); }
                catch (RuntimeException) { $rejected = true; }
                assertTrue($rejected);
            }
            }
        } finally {
            $this->deleteTree($key['home']);
            $this->deleteTree($root);
        }
    }

    public function testUnsignedReactionsWriteUnifiedFormatAndDeduplicateAcrossFormats(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
            @mkdir($root . '/records/post-reactions', 0777, true);
            file_put_contents($root . '/records/thread-labels/thread-label-legacy-like.txt',
                "Record-ID: thread-label-legacy-like\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: root-001\nOperation: add\nLabels: like\nAuthor-Identity-ID: {$identity}\n\n");
            file_put_contents($root . '/records/post-reactions/post-reaction-legacy-like.txt',
                "Record-ID: post-reaction-legacy-like\nCreated-At: 2026-04-15T15:31:00Z\nPost-ID: reply-001\nOperation: add\nTags: like\nAuthor-Identity-ID: {$identity}\n\n");
            $this->runCommand($root, 'git add records && git commit -qm "legacy reactions"');
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();

            assertSame('no', $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $identity])['wrote_record']);
            assertSame('no', $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'like', 'author_identity_id' => $identity])['wrote_record']);

            $before = glob($root . '/records/{thread-labels,post-reactions}/*.txt', GLOB_BRACE) ?: [];
            assertSame('yes', $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'upvote', 'author_identity_id' => $identity])['wrote_record']);
            assertSame('yes', $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'flag', 'author_identity_id' => $identity])['wrote_record']);
            $written = array_values(array_diff(glob($root . '/records/{thread-labels,post-reactions}/*.txt', GLOB_BRACE) ?: [], $before));
            sort($written);
            assertSame(2, count($written));
            assertTrue(preg_match("/\\ARecord-ID: post-reaction-[^\\n]+\\nCreated-At: [^\\n]+\\nTarget-Kind: post\\nTarget-ID: reply-001\\nOperation: add\\nTags: flag\\nAuthor-Identity-ID: {$identity}\\n\\n\\z/", (string) file_get_contents($written[0])) === 1);
            assertTrue(preg_match("/\\ARecord-ID: thread-label-[^\\n]+\\nCreated-At: [^\\n]+\\nTarget-Kind: thread\\nTarget-ID: root-001\\nOperation: add\\nTags: upvote\\nAuthor-Identity-ID: {$identity}\\n\\n\\z/", (string) file_get_contents($written[1])) === 1);
            assertSame('no', $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'upvote', 'author_identity_id' => $identity])['wrote_record']);
        } finally {
            $this->deleteTree($root);
        }
    }

    public function testSignedPostReactionsThroughApiPreserveModerationAndDuplicates(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('post-reaction-signer');
        $cookies = $_COOKIE;
        $post = $_POST;
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $this->seedApprovedIdentity($root, $identity);
            $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $_COOKIE = ['identity_hint' => $identity];
            foreach (['root-001', 'reply-001'] as $target) {
                foreach (['like', 'flag'] as $tag) {
                    $id = 'post-reaction-' . $target . '-' . $tag;
                    $created = gmdate('Y-m-d\TH:i:s\Z');
                    $record = "Record-ID: {$id}\nCreated-At: {$created}\nPost-ID: {$target}\nOperation: add\nTags: {$tag}\nAuthor-Identity-ID: {$identity}\n\n";
                    $_POST = ['post_id' => $target, 'tag' => $tag, 'canonical_record' => $record,
                        'detached_signature' => $this->signCanonicalRecord($key['home'], $record)];
                    $result = $this->renderMethod($app, 'POST', '/api/apply_post_tag');
                    assertStringContains('wrote_record=yes', $result);
                    assertSame($record, file_get_contents($root . '/records/post-reactions/' . $id . '.txt'));
                    assertSame($_POST['detached_signature'], file_get_contents($root . '/records/post-reactions/' . $id . '.txt.asc'));
                    if ($tag === 'flag') assertStringContains('approved_flag_count=1', $result);
                    assertStringContains('wrote_record=no', $this->renderMethod($app, 'POST', '/api/apply_post_tag'));
                }
            }
        } finally {
            $_COOKIE = $cookies;
            $_POST = $post;
            $this->deleteTree($key['home']);
            $this->deleteTree($root);
        }
    }

    public function testSignedReactionsClockFallbackBindsPreparedBytesAndExpires(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('skew-reaction-signer');
        $cookies = $_COOKIE; $post = $_POST;
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $_COOKIE = ['identity_hint' => $identity];
            foreach ([['thread', 'legacy'], ['post', 'legacy'], ['thread', 'unified'], ['post', 'unified']] as [$kind, $layout]) {
                $family = $kind === 'thread' ? 'thread-label' : 'post-reaction';
                $targetHeader = $kind === 'thread' ? 'Thread-ID' : 'Post-ID';
                $tagHeader = $kind === 'thread' ? 'Labels' : 'Tags';
                $created = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
                $tag = $layout === 'legacy' ? 'like' : 'upvote';
                $record = $layout === 'legacy'
                    ? "Record-ID: {$family}-skew\nCreated-At: {$created}\n{$targetHeader}: root-001\nOperation: add\n{$tagHeader}: {$tag}\nAuthor-Identity-ID: {$identity}\n\n"
                    : ReactionRecordParser::build($kind, $family . '-skew-unified', $created, 'root-001', [$tag], $identity);
                $_POST = [$kind . '_id' => 'root-001', 'tag' => $tag, 'canonical_record' => $record,
                    'detached_signature' => $this->signCanonicalRecord($key['home'], $record)];
                $prepared = json_decode($this->renderMethod($app, 'POST', '/api/apply_' . $kind . '_tag'), true, 512, JSON_THROW_ON_ERROR);
                assertSame('clock_skew', $prepared['error_code']);
                assertSame(array_slice(explode("\n", $record), 2), array_slice(explode("\n", $prepared['canonical_record']), 2));
                $_POST['canonical_record'] = $prepared['canonical_record'];
                $_POST['prepare_token'] = $prepared['prepare_token'];
                $_POST['detached_signature'] = $this->signCanonicalRecord($key['home'], $_POST['canonical_record']);
                $validPost = $_POST;
                $_POST['canonical_record'] = preg_replace('/Created-At: [^\n]+/', 'Created-At: 2020-01-01T00:00:00Z', $_POST['canonical_record']);
                $_POST['detached_signature'] = $this->signCanonicalRecord($key['home'], $_POST['canonical_record']);
                assertStringContains('does not match', $this->renderMethod($app, 'POST', '/api/apply_' . $kind . '_tag'));
                $_POST = $validPost;
                $tokenPath = dirname($database) . '/prepared-posts/' . $prepared['prepare_token'] . '.json';
                $tokenBytes = file_get_contents($tokenPath);
                $expired = json_decode($tokenBytes, true);
                $expired['expires_at'] = '2000-01-01T00:00:00Z';
                file_put_contents($tokenPath, json_encode($expired));
                assertStringContains('expired', $this->renderMethod($app, 'POST', '/api/apply_' . $kind . '_tag'));
                file_put_contents($tokenPath, $tokenBytes);
                assertStringContains('wrote_record=yes', $this->renderMethod($app, 'POST', '/api/apply_' . $kind . '_tag'));
                assertStringContains('wrote_record=no', $this->renderMethod($app, 'POST', '/api/apply_' . $kind . '_tag'));
            }
        } finally {
            $_COOKIE = $cookies; $_POST = $post;
            $this->deleteTree($key['home']); $this->deleteTree($root);
        }
    }

    public function testHistoricalReactionSignaturesAreVerifiedOnRebuildAndImport(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        [$destination] = $this->createTempEnvironment();
        $key = $this->createSigningKey('historical-reaction-signer');
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $this->seedApprovedIdentity($root, $identity);
            $paths = [];
            foreach (['thread', 'post'] as $kind) {
                $family = $kind === 'thread' ? 'thread-label' : 'post-reaction';
                $targetHeader = $kind === 'thread' ? 'Thread-ID' : 'Post-ID';
                $tagHeader = $kind === 'thread' ? 'Labels' : 'Tags';
                $records = [
                    $family . '-historical' => "Record-ID: {$family}-historical\nCreated-At: 2020-01-01T00:00:00Z\n{$targetHeader}: root-001\nOperation: add\n{$tagHeader}: like\nAuthor-Identity-ID: {$identity}\n\n",
                    $family . '-historical-unified' => \ForumRewrite\Canonical\ReactionRecordParser::build($kind, $family . '-historical-unified',
                        '2020-01-01T00:00:01Z', 'root-001', ['like'], $identity),
                ];
                foreach ($records as $id => $record) {
                    $path = 'records/' . $family . 's/' . $id . '.txt';
                    if (!is_dir(dirname($root . '/' . $path))) mkdir(dirname($root . '/' . $path), 0777, true);
                    file_put_contents($root . '/' . $path, $record);
                    file_put_contents($root . '/' . $path . '.asc', $this->signCanonicalRecord($key['home'], $record));
                    $paths[] = $path;
                }
            }
            $planner = new \ForumRewrite\Import\ContentImportPlanner();
            $plan = $planner->plan($root, $destination);
            foreach ($paths as $path) assertSame('import', $plan[$path]['state']);
            $keyPath = '/records/public-keys/openpgp-' . strtoupper(substr($identity, 8)) . '.asc';
            copy($root . $keyPath, $destination . $keyPath);
            unlink($root . $keyPath);
            $plan = $planner->plan($root, $destination);
            foreach ($paths as $path) assertSame('import', $plan[$path]['state']);
            unlink($destination . $keyPath);
            $plan = $planner->plan($root, $destination);
            foreach ($paths as $path) assertSame('invalid', $plan[$path]['state']);
            file_put_contents($root . $keyPath, $key['public_key']);
            $builder = new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root));
            $builder->rebuild();
            $pdo = new PDO('sqlite:' . $database);
            foreach ($paths as $path) {
                $stmt = $pdo->prepare('SELECT signature_status FROM reaction_signatures WHERE source_path = ?');
                $stmt->execute([$path]);
                assertSame('verified', $stmt->fetchColumn());
                $stmt->closeCursor();
            }
            assertSame(1, (int) $pdo->query("SELECT post_score_total FROM posts WHERE post_id = 'root-001'")->fetchColumn());
            foreach ($paths as $path) file_put_contents($root . '/' . $path . '.asc', 'tampered');
            $builder->rebuild();
            assertSame(0, (int) $pdo->query("SELECT post_score_total FROM posts WHERE post_id = 'root-001'")->fetchColumn());
            foreach (['thread_label_invalid_count', 'post_reaction_invalid_count'] as $metric) {
                assertSame('2', $pdo->query("SELECT value FROM metadata WHERE key = '{$metric}'")->fetchColumn());
            }
            $plan = $planner->plan($root, $destination);
            foreach ($paths as $path) {
                assertSame('invalid', $plan[$path]['state']);
                assertSame('invalid', $plan[$path . '.asc']['state']);
            }
        } finally {
            $this->deleteTree($key['home']); $this->deleteTree($root); $this->deleteTree($destination);
        }
    }

    public function testSignedReactionArchiveRoundTripRetainsBothCompanions(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('archive-reaction-signer');
        $archive = $root . '/thread.zip';
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $pairs = [];
            foreach (['thread', 'post'] as $kind) {
                $family = $kind === 'thread' ? 'thread-label' : 'post-reaction';
                $targetHeader = $kind === 'thread' ? 'Thread-ID' : 'Post-ID';
                $tagHeader = $kind === 'thread' ? 'Labels' : 'Tags';
                $created = gmdate('Y-m-d\TH:i:s\Z');
                $record = "Record-ID: {$family}-archive\nCreated-At: {$created}\n{$targetHeader}: root-001\nOperation: add\n{$tagHeader}: like\nAuthor-Identity-ID: {$identity}\n\n";
                $signature = $this->signCanonicalRecord($key['home'], $record);
                $input = [$kind . '_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $identity,
                    'canonical_record' => $record, 'detached_signature' => $signature];
                $kind === 'thread' ? $writer->applyThreadTag($input) : $writer->applyPostTag($input);
                $path = 'records/' . $family . 's/' . $family . '-archive.txt';
                $pairs[$path] = $record; $pairs[$path . '.asc'] = $signature;
                $unified = \ForumRewrite\Canonical\ReactionRecordParser::build($kind, $family . '-archive-unified', $created, 'root-001', ['like'], $identity);
                $unifiedPath = 'records/' . $family . 's/' . $family . '-archive-unified.txt';
                $pairs[$unifiedPath] = $unified;
                $pairs[$unifiedPath . '.asc'] = $this->signCanonicalRecord($key['home'], $unified);
                file_put_contents($root . '/' . $unifiedPath, $unified);
                file_put_contents($root . '/' . $unifiedPath . '.asc', $pairs[$unifiedPath . '.asc']);
            }
            $this->runCommand($root, 'git add records && git commit -qm "unified reaction fixtures"');
            $output = $this->runCommand($root, 'php ' . escapeshellarg(__DIR__ . '/../scripts/archive_thread.php')
                . ' root-001 ' . escapeshellarg($root) . ' ' . escapeshellarg($database)
                . ' ' . escapeshellarg($artifacts) . ' ' . escapeshellarg($archive));
            assertStringContains('Archived thread', $output);
            foreach ($pairs as $path => $bytes) assertFalse(is_file($root . '/' . $path));
            $this->runCommand($root, 'unzip -oq ' . escapeshellarg($archive) . ' -d ' . escapeshellarg($root));
            foreach ($pairs as $path => $bytes) assertSame($bytes, file_get_contents($root . '/' . $path));
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            $pdo = new PDO('sqlite:' . $database);
            assertSame(4, (int) $pdo->query("SELECT COUNT(*) FROM reaction_signatures WHERE signature_status = 'verified'")->fetchColumn());
        } finally {
            $this->deleteTree($key['home']); $this->deleteTree($root);
        }
    }

    public function testSignedReactionRecoveryAfterPartialPairCommitAndRefreshFailures(): void
    {
        $key = $this->createSigningKey('recovery-reaction-signer');
        try {
            foreach (['signature_only', 'commit_failure', 'refresh_failure'] as $phase) {
                [$root, $database, $artifacts] = $this->createTempEnvironment();
                try {
                    $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
                    $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
                    $kind = $phase === 'signature_only' ? 'thread' : 'post';
                    $family = $kind === 'thread' ? 'thread-label' : 'post-reaction';
                    $targetHeader = $kind === 'thread' ? 'Thread-ID' : 'Post-ID';
                    $tagHeader = $kind === 'thread' ? 'Labels' : 'Tags';
                    $created = gmdate('Y-m-d\TH:i:s\Z');
                    $record = "Record-ID: {$family}-recover\nCreated-At: {$created}\n{$targetHeader}: root-001\nOperation: add\n{$tagHeader}: like\nAuthor-Identity-ID: {$identity}\n\n";
                    $signature = $this->signCanonicalRecord($key['home'], $record);
                    $path = 'records/' . $family . 's/' . $family . '-recover.txt';
                    $input = [$kind . '_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $identity,
                        'canonical_record' => $record, 'detached_signature' => $signature];
                    $journalDirectory = dirname($database) . '/signed-reaction-writes-' . substr(hash('sha256', $root), 0, 16);
                    if ($phase === 'signature_only') {
                        mkdir($journalDirectory, 0700, true);
                        file_put_contents($journalDirectory . '/' . hash('sha256', $path) . '.json', json_encode([$path, $record, $signature]));
                        file_put_contents($root . '/' . $path . '.asc', $signature);
                        assertFalse(is_file($root . '/' . $path));
                    } else {
                        $failing = $writer;
                        if ($phase === 'commit_failure') {
                            file_put_contents($root . '/.git/hooks/pre-commit', "#!/bin/sh\nexit 1\n");
                            chmod($root . '/.git/hooks/pre-commit', 0700);
                        } else {
                            $failing = new class($root, $database, $artifacts, new CanonicalRecordRepository($root)) extends LocalWriteService {
                                protected function canIncrementallyUpdateReadModel(): bool { return false; }
                                protected function rebuildReadModel(): array { throw new RuntimeException('injected refresh failure'); }
                            };
                        }
                        $failed = false;
                        try { $failing->applyPostTag($input); } catch (RuntimeException) { $failed = true; }
                        assertTrue($failed);
                        assertTrue(is_file($root . '/' . $path . '.asc'));
                        if ($phase === 'commit_failure') unlink($root . '/.git/hooks/pre-commit');
                    }
                    $result = $kind === 'thread' ? $writer->applyThreadTag($input) : $writer->applyPostTag($input);
                    assertSame('no', $result['wrote_record']);
                    assertSame($record, file_get_contents($root . '/' . $path));
                    assertSame($signature, file_get_contents($root . '/' . $path . '.asc'));
                    assertSame([], glob($journalDirectory . '/*.json'));
                    assertFalse((new ReadModelStaleMarker($database))->exists());
                    $committed = $this->runCommand($root, 'git show ' . escapeshellarg('HEAD:' . $path));
                    assertSame(trim($record), trim($committed));
                } finally { $this->deleteTree($root); }
            }
        } finally { $this->deleteTree($key['home']); }
    }

    public function testImageSignedPublicationRejectsTamperingAndRetriesSafely(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('image-author');
        $old = getenv('FORUM_IMAGE_CONTENT_ENABLED');
        $source = $root . '/upload.png';
        try {
            putenv('FORUM_IMAGE_CONTENT_ENABLED=true');
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $_POST = ['public_key' => $key['public_key']];
            $linked = $this->renderMethod($app, 'POST', '/api/link_identity');
            $_POST = [];
            $identity = $this->extractValue($linked, 'identity_id');
            $this->seedApprovedIdentity($root, $identity);
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
            file_put_contents($source, ImageContentTest::png());
            $ref = \ForumRewrite\Images\ImageStore::inspect($source, 'White pixel');
            $input = ['body' => "Picture\n" . \ForumRewrite\Images\ImageReference::line($ref), 'subject' => 'Image', 'author_identity_id' => $identity];
            try { $writer->createThread($input); throw new \LogicException('Unsigned attachment accepted'); }
            catch (RuntimeException $e) { assertStringContains('signed publication', $e->getMessage()); }
            $prepared = $writer->prepareThread($input);
            $final = $prepared + ['author_identity_id' => $identity, 'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])];
            file_put_contents($source, 'tampered');
            try { $writer->createPreparedPost($final, $source); throw new \LogicException('Tampered attachment accepted'); }
            catch (RuntimeException $e) { assertTrue(!is_file($root . '/' . $prepared['record_path'])); }
            file_put_contents($source, ImageContentTest::png());
            putenv('FORUM_IMAGE_CONTENT_ENABLED=false');
            try { $writer->createPreparedPost($final, $source); throw new \LogicException('Disabled attachment accepted'); }
            catch (RuntimeException $e) { assertStringContains('enabled image support', $e->getMessage()); }
            putenv('FORUM_IMAGE_CONTENT_ENABLED=true');
            $result = $writer->createPreparedPost($final, $source);
            $retry = $writer->createPreparedPost($final, $source);
            assertSame($result['post_id'], $retry['post_id']);
            assertSame($result['commit_sha'], $retry['commit_sha']);
            $imageUrl = '/images/' . \ForumRewrite\Images\ImageReference::filename($ref);
            assertSame(ImageContentTest::png(), $this->renderMethod(new Application(dirname(__DIR__), $root, $db, $artifacts), 'GET', $imageUrl));
            putenv('FORUM_IMAGE_CONTENT_ENABLED=false');
            assertStringContains('Image unavailable', $this->renderMethod(new Application(dirname(__DIR__), $root, $db, $artifacts), 'GET', $imageUrl));
            putenv('FORUM_IMAGE_CONTENT_ENABLED=true');
            assertSame(ImageContentTest::png(), file_get_contents((new \ForumRewrite\Images\ImageStore($root))->resolve($ref)));
            // New oversized uploads fail even when correctly signed; exact legacy repair remains valid.
            $large = imagecreatetruecolor(2049, 1);
            imagepng($large, $source); imagedestroy($large);
            $largeRef = \ForumRewrite\Images\ImageStore::inspect($source, 'Legacy wide image');
            $largeInput = array_replace($input, ['body' => \ForumRewrite\Images\ImageReference::line($largeRef)]);
            $largePrepared = $writer->prepareThread($largeInput);
            $largeFinal = $largePrepared + ['author_identity_id' => $identity, 'detached_signature' => $this->signCanonicalRecord($key['home'], $largePrepared['canonical_record'])];
            try { $writer->createPreparedPost($largeFinal, $source); throw new \LogicException('New oversized upload accepted'); }
            catch (RuntimeException $e) { assertStringContains('2048', $e->getMessage()); }
            assertSame(false, is_file($root . '/' . $largePrepared['record_path']));
            assertSame(false, is_file((new \ForumRewrite\Images\ImageStore($root))->root . '/' . \ForumRewrite\Images\ImageReference::filename($largeRef)));
            file_put_contents($root . '/' . $largePrepared['record_path'], $largePrepared['canonical_record']);
            file_put_contents($root . '/' . $largePrepared['record_path'] . '.asc', $largeFinal['detached_signature']);
            $this->runCommand($root, 'git add ' . escapeshellarg($largePrepared['record_path']) . ' ' . escapeshellarg($largePrepared['record_path'] . '.asc'));
            $this->runCommand($root, 'git commit -qm "Historical image fixture"');
            $writer->createPreparedPost($largeFinal, $source);
            assertSame(file_get_contents($source), file_get_contents((new \ForumRewrite\Images\ImageStore($root))->resolve($largeRef)));
        } finally {
            $_POST = [];
            putenv($old === false ? 'FORUM_IMAGE_CONTENT_ENABLED' : 'FORUM_IMAGE_CONTENT_ENABLED=' . $old);
            $this->deleteTree($root);
            $this->deleteTree($root . '-images');
            $this->deleteTree($artifacts);
            $this->deleteTree($key['home']);
            @unlink($db);
        }
    }

    public function testEventFieldsRequireEventSupportBeforeWriting(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $disabledService = new LocalWriteService(
            $repositoryRoot,
            $databasePath,
            $artifactRoot,
            new CanonicalRecordRepository($repositoryRoot),
            featureFlags: new FeatureFlagEvaluator(siteValues: [FeatureFlagRegistry::EVENT_SUPPORT_ENABLED => false]),
        );
        $initialPostCount = $this->countCanonicalPostFiles($repositoryRoot);
        $eventInput = [
            'board_tags' => 'general',
            'subject' => 'Event thread',
            'body' => 'Event body',
            'event_date' => '2026-11-01',
            'event_location' => 'Cambridge',
            'event_link' => 'https://example.test/rsvp',
        ];

        try {
            $disabledService->createThread($eventInput);
            throw new RuntimeException('Expected event-support validation failure.');
        } catch (RuntimeException $exception) {
            assertSame('Event support is disabled for this site.', $exception->getMessage());
        }
        assertSame($initialPostCount, $this->countCanonicalPostFiles($repositoryRoot));

        $normalThread = $disabledService->createThread([
            'board_tags' => 'general',
            'subject' => 'Ordinary thread',
            'body' => 'Ordinary body',
        ]);
        assertSame($initialPostCount + 1, $this->countCanonicalPostFiles($repositoryRoot));
        assertStringNotContains('Event-Date:', (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $normalThread['thread_id'])));

        $enabledService = new LocalWriteService(
            $repositoryRoot,
            $databasePath,
            $artifactRoot,
            new CanonicalRecordRepository($repositoryRoot),
            featureFlags: new FeatureFlagEvaluator(siteValues: [FeatureFlagRegistry::EVENT_SUPPORT_ENABLED => true]),
        );
        $eventThread = $enabledService->createThread($eventInput);
        $record = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $eventThread['thread_id']));

        assertStringContains('Event-Date: 2026-11-01', $record);
        assertStringContains('Event-Location: Cambridge', $record);
        assertStringContains('Event-Link: https://example.test/rsvp', $record);
    }

    public function testTitlelessThreadDisplaysBodyExcerptTitle(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $body = 'This titleless thread should use its body as the display title.';

        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=&body=' . rawurlencode($body)
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($threadId));
        $board = $this->renderMethod($application, 'GET', '/threads/?view=all');
        $boardRss = $this->renderMethod($application, 'GET', '/?format=rss');
        $apiIndex = $this->renderMethod($application, 'GET', '/api/list_index');

        assertStringContains('<title>' . $body . '</title>', $threadPage);
        assertStringContains('<h1>' . $body . '</h1>', $threadPage);
        assertStringContains('>' . $body . '</a>', $board);
        assertStringContains('<title>' . $body . '</title>', $boardRss);
        assertStringContains($threadId . "\t" . $body, $apiIndex);
    }

    public function testCreateThreadAndReplyApisWriteCanonicalFilesCommitAndInvalidateArtifacts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
        ]);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=New%20Thread&body=Thread%20body'
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');
        $threadCommitSha = $this->extractValue($threadResponse, 'commit_sha');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);
        $this->seedArtifacts($artifactRoot, [
            '/threads/' . $threadId . '.html',
            '/posts/' . $threadId . '.html',
            '/index.html',
        ]);

        $replyResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($threadId) . '&body=Reply%20body'
        );
        $replyId = $this->extractValue($replyResponse, 'post_id');
        $replyCommitSha = $this->extractValue($replyResponse, 'commit_sha');
        $postPage = $this->renderMethod($application, 'GET', '/posts/' . $replyId);

        assertStringContains('status=ok', $threadResponse);
        assertTrue(strlen($threadCommitSha) === 40);
        $threadRecordPath = $this->canonicalPostPath($repositoryRoot, $threadId);
        assertTrue(preg_match('#^records/posts/\d{4}/\d{2}/\d{2}/' . preg_quote($threadId, '#') . '\.txt$#', $threadRecordPath) === 1);
        assertStringContains('Created-At: ', (string) file_get_contents($repositoryRoot . '/' . $threadRecordPath));
        assertStringContains('New Thread', $threadPage);
        assertFalse(is_file($artifactRoot . '/index.html'));
        assertStringContains('status=ok', $replyResponse);
        assertTrue(strlen($replyCommitSha) === 40);
        $replyRecordPath = $this->canonicalPostPath($repositoryRoot, $replyId);
        assertTrue(preg_match('#^records/posts/\d{4}/\d{2}/\d{2}/' . preg_quote($replyId, '#') . '\.txt$#', $replyRecordPath) === 1);
        assertStringContains('Created-At: ', (string) file_get_contents($repositoryRoot . '/' . $replyRecordPath));
        assertStringContains('Reply body', $postPage);
        assertStringContains('by guest on <time datetime="', $postPage);
        assertFalse(is_file($artifactRoot . '/threads/' . $threadId . '.html'));
        assertFalse(is_file($artifactRoot . '/posts/' . $replyId . '.html'));
        assertTrue(is_file($artifactRoot . '/posts/' . $threadId . '.html'));
        assertSame($replyCommitSha, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
    }

    public function testPostAnalysisEndpointStoresStubResultIdempotently(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        $previousAutomaticReplies = getenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED');
        putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Analyzed&body=Thoughtful%20body%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $_COOKIE = [];
            $first = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $postCountAfterFirstAnalyze = $this->countCanonicalPostFiles($repositoryRoot);
            $_COOKIE = ['identity_hint' => 'guest'];
            $second = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $postCountAfterSecondAnalyze = $this->countCanonicalPostFiles($repositoryRoot);
            $_COOKIE = [];
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
            $_COOKIE = [];
            $anonymousThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = [];
            $pdo = new PDO('sqlite:' . $databasePath);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM post_analyses')->fetchColumn();
            $unicodeRiskCount = (int) $pdo->query('SELECT COUNT(*) FROM post_unicode_risks')->fetchColumn();
            $generatedCount = (int) $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn();
            $generatedRow = $pdo->query('SELECT provider, provider_model, raw_response_json FROM post_generated_responses')->fetch();
            $rawResponse = json_decode((string) $generatedRow['raw_response_json'], true);
            $agentPostId = (string) $first['agent_reply_post_id'];
            $replyRecord = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $agentPostId));

            assertSame('ok', $first['status']);
            assertSame('complete', $first['analysis_status']);
            assertSame(false, $first['cached']);
            assertSame(false, $first['viewer_can_see_analysis']);
            assertSame(false, isset($first['moderation']));
            assertSame(false, isset($first['unicode_risk']));
            assertSame(true, $first['agent_reply_generation_allowed']);
            assertSame('generated', $first['agent_reply_generation_status']);
            assertSame(true, $first['agent_reply_posted']);
            assertSame('/posts/' . $agentPostId, $first['agent_reply_post_url']);
            assertSame(null, $first['agent_reply_reason']);
            assertSame(null, $first['agent_reply_failure_code']);
            assertStringContains('Parent-ID: ' . $postId, $replyRecord);
            assertStringContains('Author-Identity-ID: openpgp:', $replyRecord);
            assertSame('ok', $second['status']);
            assertSame('complete', $second['analysis_status']);
            assertSame(true, $second['cached']);
            assertSame(true, $second['viewer_can_see_analysis']);
            assertSame(true, $second['agent_reply_generation_allowed']);
            assertSame('already_posted', $second['agent_reply_generation_status']);
            assertSame(true, $second['agent_reply_posted']);
            assertSame($agentPostId, $second['agent_reply_post_id']);
            assertSame($postCountAfterFirstAnalyze, $postCountAfterSecondAnalyze);
            assertSame('stub', $second['provider']);
            assertSame('The post says: Thoughtful body?', $second['post_summary']);
            assertSame('none', $second['moderation']['severity']);
            assertSame([], $second['unicode_risk']['deterministic_facts']['fields']['subject']['risk_labels']);
            assertSame([], $second['unicode_risk']['llm_review']);
            assertSame(true, $second['respondability']['asks_question']);
            assertSame('opinion', $second['respondability']['question_type']);
            assertSame(true, $second['respondability']['should_generate_response']);
            assertSame(1, $count);
            assertSame(1, $unicodeRiskCount);
            assertSame(1, $generatedCount);
            assertSame('stub', $generatedRow['provider']);
            assertSame('stub/agent-response', $generatedRow['provider_model']);
            assertStringContains('Be warm, interested, and direct', $rawResponse['messages'][0]['content']);
            assertStringContains('author: approved=', $rawResponse['messages'][0]['content']);
            assertStringContains('data-created-post-id="' . $postId . '"', $threadPage);
            assertStringContains('data-agent-reply-posted-id="' . $agentPostId . '"', $threadPage);
            assertStringNotContains('data-agent-reply-work=', $threadPage);
            assertStringNotContains('Post analysis', $anonymousThreadPage);
            assertStringContains('Post analysis', $approvedThreadPage);
            assertStringContains('Provider: stub / stub/post-analysis', $approvedThreadPage);
            assertStringNotContains('Post summary:', $approvedThreadPage);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $_COOKIE = [];
            assertStringNotContains('Post summary:', $approvedPostPage);
            assertStringNotContains('Reply to this post', $approvedPostPage);
            assertStringContains('>Reply</a>', $approvedPostPage);
            assertStringNotContains('<strong>Question:</strong>', $approvedPostPage);
            assertStringContains('Moderation summary:', $approvedPostPage);
            assertStringContains('Moderation summary:', $approvedThreadPage);
            assertStringContains('Respondability:', $approvedThreadPage);
            assertStringNotContains('<strong>Question:</strong>', $approvedThreadPage);
            assertStringContains('Response value:', $approvedThreadPage);
            assertStringNotContains('Unicode risk:', $approvedThreadPage);
            assertStringNotContains('Unicode scripts:', $approvedThreadPage);
            assertStringNotContains('Recommended action:', $approvedThreadPage);
            assertStringNotContains('Unicode risk:', $approvedPostPage);
            assertStringNotContains('Unicode scripts:', $approvedPostPage);
            assertStringContains('Suggested response:', $approvedThreadPage);
            assertFalse(is_dir($repositoryRoot . '/records/post-analyses'));
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $this->restoreEnvironmentValue('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', $previousAutomaticReplies);
            $_COOKIE = [];
        }
    }

    public function testPostAnalysisEndpointReportsMissingConfigWithoutStoringAnalysis(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('DEDALUS_ANALYSIS_MODE');
        putenv('DEDALUS_API_KEY');
        putenv('FORUM_SECRETS_PATH=' . sys_get_temp_dir() . '/forum-rewrite-missing-secrets-' . bin2hex(random_bytes(6)) . '.php');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $response = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=root-001'), true);

            assertSame('ok', $response['status']);
            assertSame('config_missing', $response['analysis_status']);
            assertSame('LLM API key is not configured.', $response['failure_message']);
        } finally {
            putenv('FORUM_SECRETS_PATH');
        }
    }

    public function testFastScoreEndpointRetiresSynchronousProviderExecution(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousEnabled = getenv('FAST_SCORING_ENABLED');
        $previousProvider = getenv('FAST_SCORING_LLM_PROVIDER');
        putenv('FAST_SCORING_ENABLED=true');
        putenv('FAST_SCORING_LLM_PROVIDER=stub');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $created = $this->renderMethod($application, 'POST', '/api/create_thread?board_tags=general&subject=Score&body=Question');
            $postId = $this->extractValue($created, 'post_id');

            $_COOKIE = [];
            $anonymous = json_decode($this->renderMethod($application, 'POST', '/api/score_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approved = json_decode($this->renderMethod($application, 'POST', '/api/score_post?post_id=' . rawurlencode($postId)), true);
            $missing = json_decode($this->renderMethod($application, 'POST', '/api/score_post?post_id=missing'), true);

            assertSame('fast_score_synchronous_execution_retired', $anonymous['error']);
            assertSame('Fastmod scores are created only by the private worker after new-content publication.', $anonymous['message']);
            assertSame('fast_score_synchronous_execution_retired', $approved['error']);
            assertSame('fast_score_synchronous_execution_retired', $missing['error']);
        } finally {
            $_COOKIE = [];
            $previousEnabled === false ? putenv('FAST_SCORING_ENABLED') : putenv('FAST_SCORING_ENABLED=' . $previousEnabled);
            $previousProvider === false ? putenv('FAST_SCORING_LLM_PROVIDER') : putenv('FAST_SCORING_LLM_PROVIDER=' . $previousProvider);
        }
    }

    public function testNewPublishedPostEnqueuesPrivateBackgroundWork(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $scorePath = sys_get_temp_dir() . '/forum-fast-score-work-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $queuePath = sys_get_temp_dir() . '/forum-fast-score-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previous = [
            'FAST_SCORING_ENABLED' => getenv('FAST_SCORING_ENABLED'),
            'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED' => getenv('FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED'),
            'FAST_SCORING_DATABASE_PATH' => getenv('FAST_SCORING_DATABASE_PATH'),
            'FORUM_TASK_QUEUE_DATABASE_PATH' => getenv('FORUM_TASK_QUEUE_DATABASE_PATH'),
        ];
        putenv('FAST_SCORING_ENABLED=true');
        putenv('FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED=true');
        putenv('FAST_SCORING_DATABASE_PATH=' . $scorePath);
        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $response = $this->renderMethod($application, 'POST', '/api/create_thread?board_tags=general&subject=Queued&body=New%20content');
            $postId = $this->extractValue($response, 'post_id');
            $work = (new PDO('sqlite:' . $scorePath))->query('SELECT post_id, state, attempt_count FROM fast_score_work')->fetch(PDO::FETCH_ASSOC);
            $queue = (new PDO('sqlite:' . $queuePath))->query("SELECT type, status FROM internal_tasks WHERE type = 'fast_score_sweep'")->fetch(PDO::FETCH_ASSOC);
            $snapshotQueue = (new PDO('sqlite:' . $queuePath))->query("SELECT type, status FROM internal_tasks WHERE type = 'publish_offline_snapshot'")->fetch(PDO::FETCH_ASSOC);

            assertSame($postId, $work['post_id']);
            assertSame('pending', $work['state']);
            assertSame(0, (int) $work['attempt_count']);
            assertSame('fast_score_sweep', $queue['type']);
            assertSame('queued', $queue['status']);
            assertSame('publish_offline_snapshot', $snapshotQueue['type']);
            assertSame('queued', $snapshotQueue['status']);
        } finally {
            foreach ($previous as $name => $value) {
                $value === false ? putenv($name) : putenv($name . '=' . $value);
            }
        }
    }

    public function testPostDetailPageShowsCurrentFastScoreToAllViewers(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $scorePath = sys_get_temp_dir() . '/forum-fast-score-detail-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previousScorePath = getenv('FAST_SCORING_DATABASE_PATH');
        putenv('FAST_SCORING_DATABASE_PATH=' . $scorePath);

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $created = $this->renderMethod($application, 'POST', '/api/create_thread?board_tags=general&subject=Scored&body=Current%20content');
            $postId = $this->extractValue($created, 'post_id');
            $readPdo = new PDO('sqlite:' . $databasePath);
            $post = $readPdo->query(
                'SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = ' . $readPdo->quote($postId)
            )->fetch(PDO::FETCH_ASSOC);
            $context = (new FastScoreContextFactory(static fn (string $_): ?array => null))->forPost($post);
            (new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath)))->save(
                $postId,
                (string) $context['content_hash'],
                'rubric-test',
                ['status' => 'scored', 'probability' => 0.42, 'source' => 'llm', 'signals' => []],
            );

            $_COOKIE = [];
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));

            assertStringContains('Fastmod (legacy) (earlier rubric revision)', $postPage);
            assertStringContains('Moderation: 0.42', $postPage);
            $store = new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath));
            $store->save($postId, (string) $context['content_hash'], 'new-revision',
                \ForumRewrite\Scoring\FastScoreResult::scoredSet(['meta' => 0.91, 'custom' => 0.12], 'llm'));
            $multi = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            assertStringContains('Meta: 0.91', $multi);
            assertStringContains('Custom: 0.12', $multi);
            assertStringNotContains('Moderation: 0.91', $multi);
            $store->save($postId, (string) $context['content_hash'], 'failed-revision',
                \ForumRewrite\Scoring\FastScoreResult::notScored('invalid_response', [], 'invalid_response'));
            $failed = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            assertStringContains('invalid response', $failed);
            assertStringNotContains('Meta: 0.00', $failed);
            assertStringNotContains('Fastmod:', $threadPage);
        } finally {
            $previousScorePath === false ? putenv('FAST_SCORING_DATABASE_PATH') : putenv('FAST_SCORING_DATABASE_PATH=' . $previousScorePath);
            $_COOKIE = [];
        }
    }

    public function testUnicodeRiskAnalysisFlagsMixedScriptIdentifierForApprovedViewersOnly(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Look at раypal.com') . '&body=' . rawurlencode('Plain body')
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = [];
            $anonymous = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approved = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $approvedPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $_COOKIE = [];
            $anonymousPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            $pdo = new PDO('sqlite:' . $databasePath);
            $riskRow = $pdo->query('SELECT status, llm_review_json FROM post_unicode_risks WHERE post_id = ' . $pdo->quote($postId))->fetch();
            $llmReview = json_decode((string) $riskRow['llm_review_json'], true);

            assertSame(false, isset($anonymous['unicode_risk']));
            assertSame(true, in_array('mixed_script', $approved['unicode_risk']['deterministic_facts']['fields']['subject']['risk_labels'], true));
            assertSame(true, in_array('confusable_identifier_like_text', $approved['unicode_risk']['deterministic_facts']['fields']['subject']['risk_labels'], true));
            assertSame('complete', $riskRow['status']);
            assertSame('low', $llmReview['review_priority']);
            assertStringContains('Unicode risk:', $approvedPostPage);
            assertStringContains('confusable_identifier_like_text', $approvedPostPage);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = [];
            assertStringContains('Unicode risk:', $approvedThreadPage);
            assertStringContains('confusable_identifier_like_text', $approvedThreadPage);
            assertStringNotContains('Unicode risk:', $anonymousPostPage);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
            $_COOKIE = [];
        }
    }

    public function testPostAnalysisContextIncludesRelatedCrossThreadContent(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        putenv('DEDALUS_AGENT_REPLIES_ENABLED=false');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $relatedResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Reply agent context')
                    . '&body=' . rawurlencode('The reply agent should use prior context before answering repeated questions.')
            );
            $relatedPostId = $this->extractValue($relatedResponse, 'post_id');
            $targetResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode('Repeated reply agent question')
                    . '&body=' . rawurlencode('Could the reply agent use prior context when a repeated question appears?')
            );
            $targetPostId = $this->extractValue($targetResponse, 'post_id');

            $response = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($targetPostId)), true);
            $pdo = new PDO('sqlite:' . $databasePath);
            $rawResponseJson = (string) $pdo->query('SELECT raw_response_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $engagementJson = (string) $pdo->query('SELECT engagement_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $relatedContentJson = (string) $pdo->query('SELECT related_content_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $relatedContentAssessmentJson = (string) $pdo->query('SELECT related_content_assessment_json FROM post_analyses WHERE post_id = ' . $pdo->quote($targetPostId))->fetchColumn();
            $rawResponse = json_decode($rawResponseJson, true);
            $engagement = json_decode($engagementJson, true);
            $storedRelatedContent = json_decode($relatedContentJson, true);
            $storedRelatedContentAssessment = json_decode($relatedContentAssessmentJson, true);
            $relatedContent = $rawResponse['related_content'] ?? [];
            $_COOKIE = [];
            $anonymousThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($targetPostId));
            $anonymousPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($targetPostId));
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($targetPostId));
            $approvedPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($targetPostId));
            $approvedResponse = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($targetPostId)), true);

            assertSame('ok', $response['status']);
            assertSame('complete', $response['analysis_status']);
            assertSame($relatedPostId, $relatedContent[0]['post_id'] ?? null);
            assertSame($relatedPostId, $storedRelatedContent[0]['post_id'] ?? null);
            assertSame(true, $storedRelatedContentAssessment['related_results_appropriate'] ?? null);
            assertSame(true, $approvedResponse['related_content_assessment']['related_results_appropriate'] ?? null);
            assertSame('/posts/' . $relatedPostId, $relatedContent[0]['post_url'] ?? null);
            assertStringContains('/posts/' . $relatedPostId, (string) ($engagement['suggested_response'] ?? ''));
            assertStringContains('/posts/' . $relatedPostId, $approvedResponse['related_content'][0]['post_url'] ?? '');
            assertStringNotContains('Possibly related', $anonymousThreadPage);
            assertStringNotContains('Post analysis', $anonymousPostPage);
            assertStringContains('Possibly related', $approvedThreadPage);
            assertStringContains('/posts/' . $relatedPostId, $approvedThreadPage);
            assertStringContains('Post analysis', $approvedPostPage);
            assertStringContains('/posts/' . $relatedPostId, $approvedPostPage);
            assertSame(false, in_array($targetPostId, array_column($relatedContent, 'post_id'), true));
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            putenv('DEDALUS_AGENT_REPLIES_ENABLED');
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyRequiresApprovedViewerAndRecordsRequest(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $queuePath = sys_get_temp_dir() . '/forum-rewrite-agent-reply-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previousQueuePath = getenv('FORUM_TASK_QUEUE_DATABASE_PATH');
        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
        putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Needs%20Analysis&body=Should%20this%20be%20answered%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
            $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);

            $_COOKIE = [];
            $forbidden = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $duplicate = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $postCountAfter = $this->countCanonicalPostFiles($repositoryRoot);
            $pdo = new PDO('sqlite:' . $databasePath);
            $stmt = $pdo->prepare('SELECT status, request_context_json FROM post_generated_responses WHERE target_post_id = :post_id');
            $stmt->execute(['post_id' => $postId]);
            $row = $stmt->fetch();
            $requestContext = json_decode((string) $row['request_context_json'], true);
            $tasks = array_values(array_filter(
                (new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath)))->recent(10),
                static fn (array $task): bool => $task['type'] === SqliteTaskQueueStore::AGENT_REPLY,
            ));

            assertStringContains('data-agent-reply-work="analyze"', $threadPage);
            assertSame('error', $forbidden['status']);
            assertSame('forbidden', $forbidden['error']);
            assertSame('ok', $response['status']);
            assertSame('requested', $response['generation_status']);
            assertSame('requested', $duplicate['generation_status']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame('requested', $row['status']);
            assertSame('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $requestContext['agent_reply_request']['requested_by_identity_id']);
            assertSame('default_text_reply', $requestContext['agent_reply_request']['agent_response_task']['type']);
            assertSame(1, count($tasks));
            assertSame(SqliteTaskQueueStore::AGENT_REPLY, $tasks[0]['type']);
            assertSame($postId, AgentReplyTask::targetFromDeduplicationKey($tasks[0]['deduplication_key'])['post_id']);
        } finally {
            $previousQueuePath === false
                ? putenv('FORUM_TASK_QUEUE_DATABASE_PATH')
                : putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $previousQueuePath);
            @unlink($queuePath);
            putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED');
            $_COOKIE = [];
        }
    }

    public function testAutomaticAgentReplyWorkCanBeDisabledWithoutDisablingApi(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $secretsPath = tempnam(sys_get_temp_dir(), 'forum-rewrite-secrets-');
        if ($secretsPath === false) {
            throw new RuntimeException('Unable to create temporary secrets file.');
        }
        file_put_contents($secretsPath, <<<'PHP'
<?php
return [
    'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => false,
];
PHP);
        putenv('FORUM_SECRETS_PATH=' . $secretsPath);

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Manual%20Analysis&body=Should%20manual%20analysis%20still%20work%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
            $analysis = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $generationCount = (int) (new PDO('sqlite:' . $databasePath))
                ->query('SELECT COUNT(*) FROM post_generated_responses')
                ->fetchColumn();
            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);

            assertStringNotContains('data-agent-reply-work=', $threadPage);
            assertSame(false, $analysis['agent_reply_generation_allowed']);
            assertSame('not_recommended', $analysis['agent_reply_generation_status']);
            assertSame('config_disabled', $analysis['agent_reply_reason']);
            assertSame(0, $generationCount);
            assertSame('ok', $response['status']);
            assertSame('requested', $response['generation_status']);
        } finally {
            putenv('FORUM_SECRETS_PATH');
            @unlink($secretsPath);
            $_COOKIE = [];
        }
    }

    public function testApprovedViewerSeesAgentReplyRequestButtonUntilRequestExists(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Agent%20Request&body=Should%20the%20agent%20join%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $this->renderMethod(
                $application,
                'POST',
                '/api/create_reply?thread_id=' . rawurlencode($postId) . '&parent_id=' . rawurlencode($postId) . '&body=Reply%20body'
            );

            $_COOKIE = [];
            $anonymousThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $requestedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));

            assertStringNotContains('data-action="request-agent-reply"', $anonymousThreadPage);
            assertStringContains('data-action="request-agent-reply"', $approvedThreadPage);
            assertStringContains('>Agent</button>', $approvedThreadPage);
            assertStringContains('data-agent-response-mode-catalog', $approvedThreadPage);
            assertStringContains('Logic analysis', $approvedThreadPage);
            $approvedPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
            foreach ([$approvedThreadPage, $approvedPostPage] as $page) {
                assertSame(1, substr_count($page, '"type":"scripture_oriented_analysis"'));
                assertStringContains('Scripture-oriented analysis', $page);
            }
            assertSame(2, substr_count($approvedThreadPage, 'data-action="request-agent-reply"'));
            assertSame('requested', $response['generation_status']);
            assertSame(1, substr_count($requestedThreadPage, 'data-action="request-agent-reply"'));
            assertStringContains('Agent reply requested.', $requestedThreadPage);
            assertSame(
                1,
                preg_match('#<article id="post-' . preg_quote($postId, '#') . '"[^>]*thread-root-card[^>]*meta-deferred[^>]*>(.*?)</article>#s', $requestedThreadPage, $rootCard),
            );
            assertOrdered(
                $rootCard[1],
                'data-role="agent-reply-feedback">Agent reply requested.</p>',
                'class="button-row button-row-natural post-card-actions thread-root-actions"',
            );
        } finally {
            $_COOKIE = [];
        }
    }

    public function testRequestedAgentReplyNoticePrecedesCollapsedReplyActions(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Reply%20Request&body=Thread%20body'
            );
            $threadId = $this->extractValue($threadResponse, 'post_id');
            $firstReply = $this->renderMethod(
                $application,
                'POST',
                '/api/create_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($threadId) . '&body=First%20reply'
            );
            $firstReplyId = $this->extractValue($firstReply, 'post_id');
            $this->renderMethod(
                $application,
                'POST',
                '/api/create_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($firstReplyId) . '&body=Second%20reply'
            );

            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($firstReplyId)
            ), true);
            $requestedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($threadId));

            assertSame('requested', $response['generation_status']);
            assertSame(
                1,
                preg_match('#<article id="post-' . preg_quote($firstReplyId, '#') . '"[^>]*continuation[^>]*>(.*?)</article>#s', $requestedThreadPage, $replyCard),
            );
            assertOrdered(
                $replyCard[1],
                'data-role="agent-reply-feedback">Agent reply requested.</p>',
                'class="button-row button-row-natural post-card-actions"',
            );
        } finally {
            $_COOKIE = [];
        }
    }

    public function testCodexHandoffApiRequiresApprovedLocalhostAndPreparesDraft(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost:8000';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=feature&subject=Codex%20Handoff&body=Approved%20users%20should%20hand%20off%20tasks%20to%20Codex.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'guest'];
            $created = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);
            $fetched = json_decode($this->renderMethod($application, 'GET', '/api/codex_handoff?handoff_id=' . rawurlencode($created['handoff_id'])), true);
            $approved = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/codex_handoff_approval?handoff_id=' . rawurlencode($created['handoff_id']) . '&decision=approve'
            ), true);
            $duplicateApproval = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/codex_handoff_approval?handoff_id=' . rawurlencode($created['handoff_id']) . '&decision=approve'
            ), true);

            assertSame('ok', $created['status']);
            assertSame('draft_ready', $created['handoff_status']);
            assertSame($postId, $created['origin_post_id']);
            assertStringContains('As an approved local user', $created['user_story']);
            assertStringContains('Step 1 Solution Assessment', $created['fdp_step1']);
            assertStringContains('confidence', strtolower((string) $created['confidence_summary']));
            assertSame($created['handoff_id'], $fetched['handoff_id']);
            assertSame('draft_ready', $fetched['handoff_status']);
            assertSame('ok', $approved['status']);
            assertSame('approved', $approved['handoff_status']);
            assertSame('error', $duplicateApproval['status']);
            assertSame('Codex handoff approval requires draft_ready status.', $duplicateApproval['error']);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testLegacyFdpHandoffRemainsUnchangedThroughApiAndCards(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;
        try {
            $_SERVER['HTTP_HOST'] = 'localhost';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $response = $this->renderMethod($application, 'POST',
                '/api/create_thread?board_tags=fdp&subject=Legacy%20handoff&body=Preserve%20existing%20work.');
            $postId = $this->extractValue($response, 'post_id');
            $_COOKIE = ['identity_hint' => 'guest'];
            $created = json_decode($this->renderMethod($application, 'POST',
                '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);
            assertSame('draft_ready', $created['handoff_status']);
            assertStringContains('Railspike', $created['draft_text']);
            // Represent a draft persisted before the migration; requesting it again must not regenerate it.
            $pdo = new PDO('sqlite:' . $databasePath);
            $statement = $pdo->prepare('UPDATE codex_handoffs SET fdp_step1 = ?, draft_text = ? WHERE handoff_id = ?');
            $statement->execute(['Legacy FDP assessment', 'Legacy FDP full draft', $created['handoff_id']]);
            foreach (['GET', 'POST'] as $method) {
                $stored = json_decode($this->renderMethod($application, $method,
                    '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);
                assertSame($created['handoff_id'], $stored['handoff_id']);
                assertSame('Legacy FDP assessment', $stored['fdp_step1']);
                assertSame('Legacy FDP full draft', $stored['draft_text']);
            }
            foreach (['/threads/', '/posts/'] as $route) {
                assertStringContains('Legacy FDP assessment', $this->renderMethod($application, 'GET', $route . rawurlencode($postId)));
            }
            $approved = json_decode($this->renderMethod($application, 'POST',
                '/api/codex_handoff_approval?handoff_id=' . rawurlencode($created['handoff_id']) . '&decision=approve'), true);
            assertSame('approved', $approved['handoff_status']);
            assertSame('Legacy FDP full draft', $approved['draft_text']);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testCodexHandoffApiRejectsNonLocalhostAndSupportsRejectDecision(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=feature&subject=Codex%20Reject&body=Please%20prepare%20a%20local%20Codex%20handoff.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'guest'];
            $_SERVER['HTTP_HOST'] = 'example.com';
            $forbidden = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);

            $_SERVER['HTTP_HOST'] = 'localhost';
            $created = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);
            $rejected = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/codex_handoff_approval?handoff_id=' . rawurlencode($created['handoff_id']) . '&decision=reject'
            ), true);

            assertSame('error', $forbidden['status']);
            assertSame('forbidden', $forbidden['error']);
            assertSame('draft_ready', $created['handoff_status']);
            assertSame('ok', $rejected['status']);
            assertSame('rejected', $rejected['handoff_status']);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testCodexHandoffRequiresDevelopmentTagOrThreadLabelInApiAndUi(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=General%20Codex%20Question&body=Could%20Codex%20look%20at%20this%20later%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'guest'];
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $response = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);

            assertStringNotContains('data-action="request-codex-handoff"', $threadPage);
            assertSame('error', $response['status']);
            assertSame('codex handoff target requires a development tag or thread label', $response['error']);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testCodexHandoffAllowsDevelopmentThreadLabelsOnRootPost(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Root%20Task%20Label&body='
                . rawurlencode("Please prepare this for Codex.\n#railspike\n")
            );
            $rootPostId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'guest'];
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($rootPostId));
            $created = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($rootPostId)), true);

            assertStringContains('Labels: railspike', $threadPage);
            assertStringContains('data-action="request-codex-handoff"', $threadPage);
            assertSame('ok', $created['status']);
            assertSame('draft_ready', $created['handoff_status']);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testCodexHandoffReplyAddedThreadLabelShowsRootButtonOnly(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Reply%20Task%20Label&body=This%20starts%20as%20a%20general%20thread.'
            );
            $threadId = $this->extractValue($threadResponse, 'thread_id');
            $replyResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($threadId) . '&body='
                . rawurlencode("This reply marks the thread for implementation.\n#railspike\n")
            );

            $_COOKIE = ['identity_hint' => 'guest'];
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($threadId));
            $created = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($threadId)), true);

            $replyId = $this->extractValue($replyResponse, 'post_id');
            $replyHandoff = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($replyId)), true);
            assertSame('error', $replyHandoff['status']);
            assertStringContains('requires a development tag or thread label', $replyHandoff['error']);
            assertStringContains('Labels: railspike', $threadPage);
            assertSame(1, substr_count($threadPage, 'data-action="request-codex-handoff"'));
            assertSame('ok', $created['status']);
            assertSame('draft_ready', $created['handoff_status']);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testCodexHandoffLifecycleAppearsInActivityAndSurvivesRebuild(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost';
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=feature&subject=Codex%20Activity&body=Please%20prepare%20a%20Codex%20handoff%20with%20activity%20audit%20entries.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'guest'];
            $created = json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);
            $approved = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/codex_handoff_approval?handoff_id=' . rawurlencode($created['handoff_id']) . '&decision=approve'
            ), true);
            $activity = $this->renderMethod($application, 'GET', '/activity/?view=all');

            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot), 'codex-handoff-test'))->rebuild();
            $rebuiltActivity = $this->renderMethod($application, 'GET', '/activity/?view=all');

            assertSame('approved', $approved['handoff_status']);
            assertStringContains('codex_handoff', $activity);
            assertStringContains('Requested Codex handoff for post ' . $postId, $activity);
            assertStringContains('Codex handoff draft ready for post ' . $postId, $activity);
            assertStringContains('Approved Codex handoff for post ' . $postId, $activity);
            assertStringContains('/posts/' . $postId, $activity);
            assertStringContains('codex_handoff', $rebuiltActivity);
            assertStringContains('Requested Codex handoff for post ' . $postId, $rebuiltActivity);
            assertStringContains('Codex handoff draft ready for post ' . $postId, $rebuiltActivity);
            assertStringContains('Approved Codex handoff for post ' . $postId, $rebuiltActivity);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testApprovedLocalhostViewerSeesCodexHandoffButtonAndPreviewAfterDraft(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $originalServer = $_SERVER;

        try {
            $_SERVER['HTTP_HOST'] = 'localhost:8000';
            unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=railspike&subject=Codex%20UI&body=Approved%20users%20should%20review%20a%20Codex%20handoff%20before%20execution.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'missing-profile'];
            $anonymousThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_COOKIE = ['identity_hint' => 'guest'];
            $approvedThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_SERVER['HTTP_HOST'] = 'example.com';
            $nonLocalThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $_SERVER['HTTP_HOST'] = 'localhost:8000';
            json_decode($this->renderMethod($application, 'POST', '/api/codex_handoff?post_id=' . rawurlencode($postId)), true);
            $draftThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $draftPostPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));

            assertStringNotContains('data-action="request-codex-handoff"', $anonymousThreadPage);
            assertStringContains('data-action="request-codex-handoff"', $approvedThreadPage);
            assertStringContains('Handoff to Codex', $approvedThreadPage);
            assertStringNotContains('data-action="request-codex-handoff"', $nonLocalThreadPage);
            assertStringNotContains('data-action="request-codex-handoff"', $draftThreadPage);
            assertStringContains('Codex handoff ready for approval.', $draftThreadPage);
            assertStringContains('data-role="codex-handoff-preview"', $draftThreadPage);
            assertStringContains('User story:', $draftThreadPage);
            assertStringContains('Confidence:', $draftThreadPage);
            assertStringContains('Step 1 Solution Assessment', $draftThreadPage);
            assertStringContains('data-action="approve-codex-handoff"', $draftThreadPage);
            assertStringContains('data-action="reject-codex-handoff"', $draftThreadPage);
            assertStringContains('data-role="codex-handoff-preview"', $draftPostPage);
            assertStringContains('Railspike', $draftThreadPage);
            assertStringContains('Railspike', $draftPostPage);
        } finally {
            $_SERVER = $originalServer;
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyDoesNotRequireCompletedAnalysis(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            (new SqlitePostAnalysisStore(new PDO('sqlite:' . $databasePath)))->saveFailed($postId, $contentHash, 'provider_error', 'analysis failed');

            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $claimed = $store->claimNextRequested();
            $result = $application->fulfillAgentReplyRequest($claimed[0]);
            $row = $store->findByTarget($postId, $contentHash);

            assertSame('ok', $response['status']);
            assertSame('requested', $response['generation_status']);
            assertSame('generated', $result['generation_status']);
            assertSame('posted', $row['status']);
            assertSame('default_text_reply', $row['response_intent']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testTaskPreflightRejectsMismatchedContextWithoutAnalysis(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Stale%20task&body=This%20task%20must%20not%20be%20analyzed.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $_COOKIE = ['identity_hint' => 'guest'];
            $this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=logic_analysis'
            );
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $claimed = $store->claimRequestedForPost($postId);
            $claimed['request_context']['agent_reply_request']['agent_response_task']['target_content_hash'] = 'mismatched-content-hash';
            $result = $application->fulfillAgentReplyRequest($claimed);
            $analysisCount = (int) (new PDO('sqlite:' . $databasePath))
                ->query('SELECT COUNT(*) FROM post_analyses')
                ->fetchColumn();

            assertSame('not_recommended', $result['generation_status']);
            assertSame('task_context_mismatch', $result['reason']);
            assertSame(0, $analysisCount);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyBypassesModelDerivedGates(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

            $lowPostId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['overall_score' => 0.4],
            ]);
            $riskPostId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['response_risk' => 'high'],
            ]);
            $moderationPostId = $this->createAnalyzedThread($application, $databasePath, [
                'moderation' => ['severity' => 'critical'],
            ]);
            $privatePostId = $this->createAnalyzedThread($application, $databasePath, [
                'engagement' => ['response_should_be_public' => false],
            ]);

            $_COOKIE = ['identity_hint' => 'guest'];
            $lowRequest = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($lowPostId)), true);
            $riskRequest = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($riskPostId)), true);
            $moderationRequest = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($moderationPostId)), true);
            $privateRequest = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($privatePostId)), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $low = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($lowPostId));
            $risk = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($riskPostId));
            $moderation = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($moderationPostId));
            $private = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($privatePostId));

            assertSame('requested', $lowRequest['generation_status']);
            assertSame('requested', $riskRequest['generation_status']);
            assertSame('requested', $moderationRequest['generation_status']);
            assertSame('requested', $privateRequest['generation_status']);
            assertSame('generated', $low['generation_status']);
            assertSame('generated', $risk['generation_status']);
            assertSame('generated', $moderation['generation_status']);
            assertSame('generated', $private['generation_status']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testAnalyzePostGateFailureUsesCompactVisibilityRules(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        $previousAutomaticReplies = getenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED');
        putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['overall_score' => 0.4],
            ]);
            $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);

            $_COOKIE = [];
            $anonymous = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $approved = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $postCountAfter = $this->countCanonicalPostFiles($repositoryRoot);
            $pdo = new PDO('sqlite:' . $databasePath);

            assertSame(false, $anonymous['agent_reply_generation_allowed']);
            assertSame('not_recommended', $anonymous['agent_reply_generation_status']);
            assertSame(false, $anonymous['agent_reply_posted']);
            assertSame(null, $anonymous['agent_reply_post_id']);
            assertSame(null, $anonymous['agent_reply_post_url']);
            assertSame('not_recommended', $anonymous['agent_reply_reason']);
            assertSame(null, $anonymous['agent_reply_failure_code']);
            assertSame(false, isset($anonymous['response_text']));
            assertSame(false, isset($anonymous['provider_model']));
            assertSame(false, isset($anonymous['raw_response']));
            assertSame(false, $approved['agent_reply_generation_allowed']);
            assertSame('not_recommended', $approved['agent_reply_generation_status']);
            assertSame('respondability_score_low', $approved['agent_reply_reason']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM sqlite_master WHERE type = "table" AND name = "post_generated_responses"')->fetchColumn());
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $this->restoreEnvironmentValue('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', $previousAutomaticReplies);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyRejectsAgentAuthoredTarget(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $identity = (new AgentIdentityService(
                $repositoryRoot,
                $databasePath,
                $artifactRoot,
                sys_get_temp_dir() . '/forum-rewrite-agent-private-' . bin2hex(random_bytes(6)),
                new CanonicalRecordRepository($repositoryRoot),
            ))->ensureReplyAgentIdentity();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Agent%20Thread&body=Agent%20body%3F&author_identity_id=' . rawurlencode((string) $identity['identity_id'])
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId));

            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);

            assertSame('not_recommended', $response['generation_status']);
            assertSame('agent_loop_prevention', $response['reason']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testClaimedAgentReplyRequestPublishesWithoutAnalysis(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Queued%20Agent&body=What%20should%20we%20consider%20next%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $claimed = $store->claimNextRequested();

            $result = $application->fulfillAgentReplyRequest($claimed[0]);
            $postCountAfter = $this->countCanonicalPostFiles($repositoryRoot);
            $row = $store->findByTarget($postId, $claimed[0]['target_content_hash']);
            $second = $application->fulfillAgentReplyRequest($claimed[0]);
            $analysisCount = (int) (new PDO('sqlite:' . $databasePath))
                ->query('SELECT COUNT(*) FROM post_analyses')
                ->fetchColumn();

            assertSame('requested', $request['generation_status']);
            assertSame(1, count($claimed));
            assertSame('generated', $result['generation_status']);
            assertSame(true, $result['posted']);
            assertSame(true, $postCountAfter > $postCountBefore);
            assertSame('posted', $row['status']);
            assertSame($result['agent_post_id'], $row['agent_post_id']);
            assertSame('already_posted', $second['generation_status']);
            assertSame($postCountAfter, $this->countCanonicalPostFiles($repositoryRoot));
            assertSame(0, $analysisCount);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testClaimedUnmarkedAgentReplyRequestStoresGateSkip(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath, [
                'respondability' => ['overall_score' => 0.4],
            ]);
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $request = $store->requestForTarget([
                'post_id' => $postId,
                'content_hash' => $contentHash,
                'analysis_hash' => 'legacy-analysis-hash',
            ], [
                'requested_by_identity_id' => 'openpgp:legacy-requester',
            ]);
            $claimed = $store->claimNextRequested();

            $result = $application->fulfillAgentReplyRequest($claimed[0]);
            $row = $store->findByTarget($postId, $claimed[0]['target_content_hash']);

            assertSame('requested', $request['status']);
            assertSame('not_recommended', $result['generation_status']);
            assertSame('respondability_score_low', $result['reason']);
            assertSame('skipped', $row['status']);
            assertSame('respondability_score_low', $row['failure_code']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testLegacyReplyWriterRetryAndCompletedDraftRecovery(): void
    {
        $previous = $this->useStubLlmProvider();
        $previousKey = getenv('LLM_API_KEY');
        try {
            foreach (['fresh', 'retry', 'draft'] as $case) {
                [$root, $db, $artifacts] = $this->createTempEnvironment();
                $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
                $id = $this->createAnalyzedThread($app, $db);
                $hash = $this->contentHashForAnalysis($db, $id);
                $pdo = new PDO('sqlite:' . $db);
                $store = new SqliteAgentReplyGenerationStore($pdo);
                $requester = (string) $pdo->query('SELECT identity_id FROM profiles WHERE is_approved = 1 LIMIT 1')->fetchColumn();
                $context = ['post_id' => $id, 'content_hash' => $hash, 'analysis_hash' => 'legacy'];
                $store->requestForTarget($context, ['requested_by_identity_id' => $requester]);
                $row = $store->claimRequestedForPost($id);
                if ($case === 'retry') {
                    putenv('LLM_PROVIDER=openai'); putenv('LLM_API_KEY=');
                    $failed = $app->fulfillAgentReplyRequest($row);
                    assertSame('failed', $failed['generation_status']);
                    assertSame('provider_configuration_error', $failed['failure_code']);
                    putenv('LLM_PROVIDER=stub');
                    $pdo->exec('UPDATE profiles SET is_approved = 0 WHERE identity_id = ' . $pdo->quote($requester));
                    $app->retryAgentReplyRequest($id, $hash);
                    $row = $store->claimRequestedForPost($id);
                } elseif ($case === 'draft') {
                    $store->saveComplete($context, ['response_text' => 'Preserved completed draft.', 'raw_response' => ['historical' => true]]);
                }
                $done = $app->fulfillAgentReplyRequest($row);
                assertSame('generated', $done['generation_status']);
                $saved = $store->findByTarget($id, $hash);
                assertSame($case === 'draft' ? 'Preserved completed draft.' : 'Stub agent response.', $saved['response_text']);
                if ($case !== 'draft') {
                    $prompt = $saved['raw_response']['messages'][0]['content'];
                    assertStringContains('Be warm, interested, and direct', $prompt);
                    assertStringContains($case === 'retry' ? 'approved=false' : 'approved=true', $prompt);
                }
                assertSame('already_posted', $app->fulfillAgentReplyRequest($row)['generation_status']);
                assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE parent_id = ' . $pdo->quote($id))->fetchColumn());
            }
        } finally {
            $this->restoreLlmProviderEnv($previous);
            putenv($previousKey === false ? 'LLM_API_KEY' : 'LLM_API_KEY=' . $previousKey);
            $_COOKIE = [];
        }
    }

    public function testTaskQueueProcessesQueuedAgentReplyOnce(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $queuePath = sys_get_temp_dir() . '/forum-rewrite-agent-reply-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        $previousEnvironment = [];
        foreach ([
            'FORUM_PUBLIC_ARTIFACT_ROOT' => $artifactRoot,
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $queuePath,
            'FORUM_SECRETS_PATH' => $artifactRoot . '/unused-secrets.php',
            'FAST_SCORING_ENABLED' => 'false',
            'FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED' => 'false',
        ] as $key => $value) {
            $previousEnvironment[$key] = getenv($key);
            putenv($key . '=' . $value);
        }

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Command%20Agent&body=What%20should%20the%20command%20do%3F'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];

            $queuedId = static function (string $target) use ($databasePath, $queuePath): int {
                $stmt = (new PDO('sqlite:' . $databasePath))->prepare('SELECT target_content_hash FROM post_generated_responses WHERE target_post_id = ?');
                $stmt->execute([$target]);
                $task = (new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath)))->enqueue(
                    SqliteTaskQueueStore::AGENT_REPLY,
                    AgentReplyTask::deduplicationKey($target, (string) $stmt->fetchColumn()),
                );
                return (int) $task['id'];
            };
            $taskId = $queuedId($postId);
            $baseCommand = 'php scripts/task_queue.php run'
                . ' --repository-root=' . escapeshellarg($repositoryRoot)
                . ' --database-path=' . escapeshellarg($databasePath)
                . ' --static-html-root=' . escapeshellarg($artifactRoot . '/static_html')
                . ' --queue-database-path=' . escapeshellarg($queuePath);
            $dryRun = $this->runCommand(dirname(__DIR__), $baseCommand . ' --dry-run');
            $firstRun = $this->runCommand(dirname(__DIR__), $baseCommand . ' --task-id=' . $taskId);
            $secondRun = $this->runCommand(dirname(__DIR__), $baseCommand . ' --task-id=' . $taskId);
            $secondThreadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Quiet%20Command%20Agent&body=Should%20quiet%20mode%20still%20work%3F'
            );
            $secondPostId = $this->extractValue($secondThreadResponse, 'post_id');
            $_COOKIE = ['identity_hint' => 'guest'];
            $secondRequest = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($secondPostId)), true);
            $_COOKIE = [];
            $quietRun = $this->runCommand(dirname(__DIR__), $baseCommand . ' --quiet --task-id=' . $queuedId($secondPostId));
            $pdo = new PDO('sqlite:' . $databasePath);
            $statement = $pdo->prepare('SELECT status, agent_post_id FROM post_generated_responses WHERE target_post_id = :post_id');
            $statement->execute(['post_id' => $postId]);
            $row = $statement->fetch();
            $statement->execute(['post_id' => $secondPostId]);
            $quietRow = $statement->fetch();

            assertSame('requested', $request['generation_status']);
            assertSame('requested', $secondRequest['generation_status']);
            assertStringContains('Task queue dry run', $dryRun);
            assertStringContains('Queue database: ' . $queuePath, $dryRun);
            assertStringContains('Task queue worker starting', $firstRun);
            assertStringContains('type=agent_reply', $firstRun);
            assertStringContains('Task queue run complete:', $firstRun);
            assertStringNotContains('type=agent_reply', $secondRun);
            assertSame('', $quietRun);
            assertSame('posted', $row['status']);
            assertSame(true, is_string($row['agent_post_id']) && $row['agent_post_id'] !== '');
            assertSame('posted', $quietRow['status']);
            assertSame(true, is_string($quietRow['agent_post_id']) && $quietRow['agent_post_id'] !== '');
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            foreach ($previousEnvironment as $key => $value) {
                $this->restoreEnvironmentValue($key, $value);
            }
            @unlink($queuePath);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyCreatesCanonicalReplyAndIsIdempotent(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);

            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $claimed = $store->claimRequestedForPost($postId);
            $first = $application->fulfillAgentReplyRequest($claimed);
            $agentPostId = (string) $first['agent_post_id'];
            $postCountAfterFirst = $this->countCanonicalPostFiles($repositoryRoot);
            $second = $application->fulfillAgentReplyRequest($claimed);
            $postCountAfter = $this->countCanonicalPostFiles($repositoryRoot);
            $pdo = new PDO('sqlite:' . $databasePath);
            $row = $pdo->query('SELECT status, provider, response_text, agent_post_id, agent_identity_id, agent_profile_slug, posted_at FROM post_generated_responses')->fetch();
            $replyRecord = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $agentPostId));
            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $createdThreadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId) . '?created_post_id=' . rawurlencode($postId));
            $agentProfile = $this->renderMethod($application, 'GET', '/profiles/' . rawurlencode((string) $row['agent_profile_slug']));
            $users = $this->renderMethod($application, 'GET', '/users/');
            $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');

            assertSame('requested', $request['generation_status']);
            assertSame('generated', $first['generation_status']);
            assertSame(true, $first['cached']);
            assertSame('stub', $first['provider']);
            assertSame('stub/agent-response', $first['provider_model']);
            assertSame('Stub agent response.', $first['response_text']);
            assertSame(true, $first['posted']);
            assertSame('/posts/' . $agentPostId, $first['agent_post_url']);
            assertStringContains('Parent-ID: ' . $postId, $replyRecord);
            assertStringContains('Author-Identity-ID: openpgp:', $replyRecord);
            assertStringContains('Stub agent response.', $replyRecord);
            $replyPath = $this->canonicalPostPath($repositoryRoot, $agentPostId);
            assertSame('verified', \ForumRewrite\Security\ReactionSignature::status($repositoryRoot, $replyPath, $replyRecord, (string) $row['agent_identity_id']), 'The reply agent signs its replies');
            assertStringContains($replyPath . '.asc', $this->gitOutput($repositoryRoot, 'show --name-only --format= HEAD'));
            assertSame('already_posted', $second['generation_status']);
            assertSame($agentPostId, $second['agent_post_id']);
            assertSame($postCountAfterFirst, $postCountAfter);
            assertSame('posted', $row['status']);
            assertSame('stub', $row['provider']);
            assertSame('Stub agent response.', $row['response_text']);
            assertSame($agentPostId, $row['agent_post_id']);
            assertStringContains('openpgp:', $row['agent_identity_id']);
            assertStringContains('openpgp-', $row['agent_profile_slug']);
            assertSame(true, is_string($row['posted_at']));
            assertStringContains('reply-agent', $threadPage);
            assertStringContains('Agent-authored reply', $threadPage);
            assertStringContains('data-agent-authored="reply-agent"', $threadPage);
            assertStringContains('Stub agent response.', $threadPage);
            assertStringContains('data-created-post-id="' . $postId . '"', $createdThreadPage);
            assertStringContains('data-post-id="' . $postId . '"', $createdThreadPage);
            assertStringContains('data-agent-reply-posted-id="' . $agentPostId . '"', $createdThreadPage);
            assertStringNotContains('data-agent-reply-work=', $createdThreadPage);
            assertStringContains('data-role="agent-reply-feedback"', $createdThreadPage);
            assertStringContains('Automated reply agent', $agentProfile);
            assertStringContains('Automated reply agent', $agentProfile);
            assertStringContains('reply-agent', $users);
            assertStringContains('<span class="chip agent-label">Agent</span>', $users);
            assertStringContains('Author: reply-agent', $activity);
            assertStringContains('automated reply agent', $activity);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyPersistsAndFulfillsSelectedResponseMode(): void
    {
        $this->assertSelectedModeLifecycle('logic_analysis', 'Logic analysis');
        $this->assertSelectedModeLifecycle('scripture_oriented_analysis', 'Scripture-oriented analysis');
    }

    private function assertSelectedModeLifecycle(string $mode, string $label): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);

            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=' . $mode
            ), true);
            $invalid = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=unknown'
            ), true);
            $retired = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=facts_analysis'
            ), true);
            $duplicate = json_decode($this->renderMethod(
                $application, 'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=' . $mode
            ), true);
            assertSame('requested', $duplicate['generation_status']);
            assertSame($label, $duplicate['response_mode_label']);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $claimed = $store->claimRequestedForPost($postId);
            $fulfilled = $application->fulfillAgentReplyRequest($claimed);
            $stored = $store->findByTarget($postId, (string) $claimed['target_content_hash']);
            $analysisCount = (int) (new PDO('sqlite:' . $databasePath))
                ->query('SELECT COUNT(*) FROM post_analyses')
                ->fetchColumn();

            assertSame('requested', $request['generation_status']);
            assertSame($label, $request['response_mode_label']);
            assertSame('error', $invalid['status']);
            assertSame('invalid response_mode', $invalid['error']);
            assertSame('error', $retired['status']);
            assertSame('invalid response_mode', $retired['error']);
            assertSame($mode, $stored['request_context']['agent_reply_request']['agent_response_task']['type']);
            assertSame($mode, $stored['response_intent']);
            $participantPrompt = $stored['raw_response']['messages'][0]['content'];
            assertStringContains('Application participation context', $participantPrompt);
            assertStringContains('approved=', $participantPrompt);
            assertStringNotContains('requested_by_identity_id', $participantPrompt);
            assertSame('generated', $fulfilled['generation_status']);
            assertSame(1, $analysisCount);
            assertSame(true, str_starts_with($stored['analysis_hash'], 'task:'));
            assertStringNotContains(
                $label . ' available.',
                $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId))
            );
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyUsesDefaultTextTaskInsteadOfStructuredSuggestion(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath, [
                'engagement' => [
                    'suggested_response' => "Unicode is supported here; the Cyrillic 'Хорошо' renders cleanly.",
                ],
            ]);

            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $response = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($postId));
            $pdo = new PDO('sqlite:' . $databasePath);
            $statement = $pdo->prepare('SELECT response_text, agent_post_id FROM post_generated_responses WHERE target_post_id = :post_id');
            $statement->execute(['post_id' => $postId]);
            $row = $statement->fetch();
            $replyRecord = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, (string) $row['agent_post_id']));
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode((string) $row['agent_post_id']));

            assertSame('requested', $request['generation_status']);
            assertSame('generated', $response['generation_status']);
            assertSame('Stub agent response.', (string) $row['response_text']);
            assertStringContains('Stub agent response.', $replyRecord);
            assertStringContains('Stub agent response.', $postPage);
            assertStringNotContains('Хорошо', (string) $row['response_text']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyPersistsThreadCommentContext(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Context&body=' . rawurlencode('Root body?')
            );
            $threadId = $this->extractValue($threadResponse, 'post_id');
            $this->analyzePostWithoutAgentReply($application, $threadId);
            $replyResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_reply?thread_id=' . rawurlencode($threadId)
                    . '&parent_id=' . rawurlencode($threadId)
                    . '&board_tags=general&body=' . rawurlencode('Reply body?')
            );
            $replyId = $this->extractValue($replyResponse, 'post_id');
            $this->analyzePostWithoutAgentReply($application, $replyId);

            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($replyId)), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $response = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($replyId));
            $pdo = new PDO('sqlite:' . $databasePath);
            $contextJson = (string) $pdo->query('SELECT request_context_json FROM post_generated_responses')->fetchColumn();
            $context = json_decode($contextJson, true);

            assertSame('requested', $request['generation_status']);
            assertSame('generated', $response['generation_status']);
            assertSame($replyId, $context['post_id']);
            assertSame(true, $context['exchange_dependencies']['complete']);
            assertSame(true, in_array($threadId, $context['exchange_dependencies']['post_ids'], true));
            assertSame(true, in_array($replyId, $context['exchange_dependencies']['post_ids'], true));
            assertSame($threadId, $context['thread_comments'][0]['post_id']);
            assertSame("Root body?\n", $context['thread_comments'][0]['body']);
            assertSame('The post says: Root body?', $context['thread_comments'][0]['post_summary']);
            assertSame(false, $context['thread_comments'][0]['is_target']);
            assertSame($replyId, $context['thread_comments'][1]['post_id']);
            assertSame("Reply body?\n", $context['thread_comments'][1]['body']);
            assertSame('The post says: Reply body?', $context['thread_comments'][1]['post_summary']);
            assertSame(true, $context['thread_comments'][1]['is_target']);
            assertSame(false, $context['thread_comments'][1]['is_parent']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyDoesNotStartWhenGenerationIsAlreadyPending(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $store->reserveGeneration([
                'post_id' => $postId,
                'content_hash' => $contentHash,
                'analysis_hash' => 'already-running',
            ]);
            $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);

            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $postCountAfter = $this->countCanonicalPostFiles($repositoryRoot);
            $row = $store->findByTarget($postId, $contentHash);

            assertSame('ok', $response['status']);
            assertSame('in_progress', $response['generation_status']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame('pending', $row['status']);
            assertSame(null, $row['agent_post_id']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testPostAnalysisScriptDoesNotExposeInProgressReplyGeneration(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/post_analysis.js');
        $scriptPath = dirname(__DIR__) . '/public/assets/post_analysis.js';
        exec('node --check ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode);
        assertStringContains('__forumAgentReplyGenerationStartedPostIds', $script);
        assertStringContains('data-agent-reply-work', $script);
        assertStringContains('function agentReplyResultFromAnalysis(analysis)', $script);
        assertStringContains('function bindAgentReplyRequestButtons()', $script);
        assertStringContains('data-action="request-agent-reply"', $script);
        assertStringContains('work === "analyze"', $script);
        assertStringContains('work !== "analyze" && work !== "publish"', $script);
        assertStringContains('const result = agentReplyResultFromAnalysis(analysis);', $script);
        assertStringContains('function agentResponseModes()', $script);
        assertStringContains('data-agent-response-mode-catalog', $script);
        assertStringContains('function openAgentResponseModeMenu(button)', $script);
        assertStringContains('setAttribute("role", "dialog")', $script);
        assertStringContains('agent-response-mode-menu-close', $script);
        assertStringContains('agentResponseModeTrigger === button', $script);
        assertStringContains('pointerdown', $script);
        assertStringContains('response_mode', $script);
        assertStringContains('agentReplySubject(result) + " requested."', $script);
        assertStringContains('Requesting " + mode.label', $script);
        assertStringNotContains('View agent reply.', $script);
        assertStringContains('result.reason === "config_disabled"', $script);
        assertStringContains('generation_status === "in_progress"', $script);
        $analyzeBranchStart = strpos($script, 'if (work === "analyze")');
        $publishBranchStart = strpos($script, 'const result = await generateAgentReply(postId);');
        assertFalse($analyzeBranchStart === false);
        assertFalse($publishBranchStart === false);
        $analyzeBranch = substr($script, (int) $analyzeBranchStart, (int) $publishBranchStart - (int) $analyzeBranchStart);
        assertStringNotContains('generateAgentReply(postId)', $analyzeBranch);
        assertStringNotContains('Generating agent reply...', $script);
        assertStringNotContains('Agent reply failed', $script);
        assertStringNotContains('Agent reply posted', $script);
    }

    public function testPostAnalysisScriptKeepsOnlyUnfinishedAgentFeedbackOutsideActions(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
let source = fs.readFileSync(process.argv[1], 'utf8');
source = source.replace(
  'function boot() {',
  'window.agentReplyFeedbackTest = { applyGenerationResult, placeAgentReplyFeedback }; function boot() {'
);
const codexFeedback = {};
const actions = {
  outsideCount: 0,
  restoredCount: 0,
  before(node) { this.outsideCount++; node.parent = 'outside'; },
  querySelector(selector) { return selector === '[data-role="codex-handoff-feedback"]' ? codexFeedback : null; },
  insertBefore(node, reference) { if (reference === codexFeedback) { this.restoredCount++; node.parent = 'actions'; } },
  appendChild(node) { node.parent = 'actions'; }
};
const card = { querySelector(selector) { return selector === '.post-card-actions' ? actions : null; } };
const feedback = {
  hidden: true,
  textContent: '',
  parent: 'actions',
  closest(selector) { return selector === '[data-post-id]' ? card : null; }
};
global.window = {};
global.document = { readyState: 'loading', addEventListener() {} };
vm.runInThisContext(source);
window.agentReplyFeedbackTest.applyGenerationResult(feedback, {
  status: 'ok', generation_status: 'requested', response_mode_label: 'Logic analysis'
}, null);
const requested = { parent: feedback.parent, text: feedback.textContent };
window.agentReplyFeedbackTest.applyGenerationResult(feedback, {
  status: 'ok', generation_status: 'failed', response_mode_label: 'Logic analysis'
}, null);
const failed = { parent: feedback.parent, text: feedback.textContent };
const completed = [];
for (const generation_status of ['generated', 'already_posted']) {
  feedback.hidden = false;
  feedback.textContent = 'Scripture-oriented analysis requested.';
  window.agentReplyFeedbackTest.applyGenerationResult(feedback, {
    status: 'ok', generation_status, agent_post_id: 'reply-1',
    response_mode_label: 'Scripture-oriented analysis'
  }, null);
  completed.push({ hidden: feedback.hidden, text: feedback.textContent });
}
process.stdout.write(JSON.stringify({
  requested,
  failed,
  completed,
  outsideCount: actions.outsideCount,
  restoredCount: actions.restoredCount
}));
NODE;
        $command = sprintf('node -e %s %s', escapeshellarg($script), escapeshellarg(dirname(__DIR__) . '/public/assets/post_analysis.js'));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Agent-feedback presentation check failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame([
            'requested' => ['parent' => 'outside', 'text' => 'Logic analysis requested.'],
            'failed' => ['parent' => 'actions', 'text' => 'Logic analysis request failed.'],
            'completed' => [['hidden' => true, 'text' => ''], ['hidden' => true, 'text' => '']],
            'outsideCount' => 1,
            'restoredCount' => 3,
        ], $result);
    }

    public function testPostAnalysisScriptBindsCodexHandoffActions(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/post_analysis.js');

        assertStringContains('__forumCodexHandoffStartedPostIds', $script);
        assertStringContains('function requestCodexHandoff(postId)', $script);
        assertStringContains('function decideCodexHandoff(handoffId, decision)', $script);
        assertStringContains('function bindCodexHandoffButtons()', $script);
        assertStringContains('/api/codex_handoff', $script);
        assertStringContains('/api/codex_handoff_approval', $script);
        assertStringContains('data-action="request-codex-handoff"', $script);
        assertStringContains('data-action="approve-codex-handoff"', $script);
        assertStringContains('data-action="reject-codex-handoff"', $script);
        assertStringContains('Preparing Codex handoff...', $script);
        assertStringContains('Codex handoff ready for approval.', $script);
        assertStringContains('Approving Codex handoff...', $script);
        assertStringContains('Rejecting Codex handoff...', $script);
        assertStringContains('bindCodexHandoffButtons();', $script);
    }

    public function testGenerateAgentReplyRespectsDisabledConfig(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        putenv('DEDALUS_AGENT_REPLIES_ENABLED=false');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);

            $analysis = json_decode($this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $response = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $postCountAfter = $this->countCanonicalPostFiles($repositoryRoot);
            $pdo = new PDO('sqlite:' . $databasePath);
            $rowCount = (int) $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn();

            assertSame(false, $analysis['agent_reply_generation_allowed']);
            assertSame('not_recommended', $analysis['agent_reply_generation_status']);
            assertSame(false, $analysis['agent_reply_posted']);
            assertSame(null, $analysis['agent_reply_post_id']);
            assertSame(null, $analysis['agent_reply_post_url']);
            assertSame('config_disabled', $analysis['agent_reply_reason']);
            assertSame(null, $analysis['agent_reply_failure_code']);
            assertSame('requested', $response['generation_status']);
            assertSame($postCountBefore, $postCountAfter);
            assertSame(1, $rowCount);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            putenv('DEDALUS_AGENT_REPLIES_ENABLED');
            $_COOKIE = [];
        }
    }

    public function testAgentResponseRequestFlagHidesChooserAndRejectsRequests(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('AGENT_RESPONSE_REQUESTS_ENABLED=false');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Disabled%20request&body=Do%20not%20request%20an%20agent%20response.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');

            $_COOKIE = ['identity_hint' => 'guest'];
            $page = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $response = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=logic_analysis'
            ), true);
            $_COOKIE = [];
            $rowCount = (int) (new PDO('sqlite:' . $databasePath))
                ->query('SELECT COUNT(*) FROM post_generated_responses')
                ->fetchColumn();

            assertStringNotContains('data-action="request-agent-reply"', $page);
            assertStringNotContains('data-agent-response-mode-catalog', $page);
            assertSame('not_recommended', $response['generation_status']);
            assertSame('config_disabled', $response['reason']);
            assertSame(0, $rowCount);
        } finally {
            putenv('AGENT_RESPONSE_REQUESTS_ENABLED');
            $_COOKIE = [];
        }
    }

    public function testLegacyAutomaticFlagDoesNotDisableDirectRequestedTasks(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();
        putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=false');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Flag%20separation&body=Confirm%20the%20two%20reply%20paths%20are%20separate.'
            );
            $postId = $this->extractValue($threadResponse, 'post_id');
            $analysis = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/analyze_post?post_id=' . rawurlencode($postId)
            ), true);
            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=logic_analysis'
            ), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $fulfilled = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($postId));

            assertSame(false, $analysis['agent_reply_generation_allowed']);
            assertSame('not_recommended', $analysis['agent_reply_generation_status']);
            assertSame('config_disabled', $analysis['agent_reply_reason']);
            assertSame('requested', $request['generation_status']);
            assertSame('generated', $fulfilled['generation_status']);
            assertSame('logic_analysis', $fulfilled['response_intent']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED');
            $_COOKIE = [];
        }
    }

    public function testVerifiedScriptureUnavailableSourceAndRequestedTranslationRecoverNormally(): void
    {
        $previous = getenv('SCRIPTURE_RESPONSE_GUIDANCE'); putenv('SCRIPTURE_RESPONSE_GUIDANCE=verified');
        $oldPath = getenv('SCRIPTURE_SOURCE_PATH');
        try {
            $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source) use ($oldPath): void {
                foreach (['source', 'translation', 'revoked'] as $case) {
                    $body = $case === 'translation' ? 'Use NIV. Help me choose a stopping point.' : 'Help me choose a stopping point.';
                    $created = $this->renderMethod($app, 'POST', '/api/create_reply?thread_id=' . $source . '&parent_id=' . $source . '&body=' . rawurlencode($body));
                    $target = $this->extractValue($created, 'post_id');
                    $this->renderMethod($app, 'POST', '/api/generate_agent_reply?post_id=' . $target . '&response_mode=scripture_oriented_analysis');
                    $claimed = $store->claimRequestedForPost($target);
                    if ($case === 'source') { putenv('SCRIPTURE_SOURCE_PATH=' . __FILE__ . '.missing'); }
                    if ($case === 'revoked') {
                        $pdo->exec('UPDATE profiles SET is_approved = 0 WHERE identity_id = ' . $pdo->quote($claimed['request_context']['agent_reply_request']['requested_by_identity_id']));
                    }
                    $result = $app->fulfillAgentReplyRequest($claimed);
                    if ($case === 'revoked') {
                        assertSame('failed', $result['generation_status']);
                        assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE parent_id = ' . $pdo->quote($target))->fetchColumn());
                    } else {
                        assertSame('generated', $result['generation_status']);
                        $published = (string) $pdo->query('SELECT body FROM posts WHERE post_id = ' . $pdo->quote($result['agent_post_id']))->fetchColumn();
                        assertStringContains('unavailable', $published);
                        assertStringNotContains('Proverbs 21:5, KJV', $published);
                    }
                    putenv($oldPath === false ? 'SCRIPTURE_SOURCE_PATH' : 'SCRIPTURE_SOURCE_PATH=' . $oldPath);
                }
            });
        } finally {
            putenv($previous === false ? 'SCRIPTURE_RESPONSE_GUIDANCE' : 'SCRIPTURE_RESPONSE_GUIDANCE=' . $previous);
            putenv($oldPath === false ? 'SCRIPTURE_SOURCE_PATH' : 'SCRIPTURE_SOURCE_PATH=' . $oldPath);
        }
    }

    public function testVerifiedScriptureExpansionNormalRenderingAndRecovery(): void
    {
        $previous = getenv('SCRIPTURE_RESPONSE_GUIDANCE'); putenv('SCRIPTURE_RESPONSE_GUIDANCE=verified');
        try {
            $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial, $root, $db, $artifacts, $directory): void {
                $this->renderMethod($app, 'POST', '/api/expand_agent_reply?post_id=' . $initial);
                $claimed = $store->claimRequestedForPost($initial);
                $store->saveComplete($claimed['request_context'], ['response_text' => 'Unverified old expansion.']);
                $failed = $app->fulfillAgentReplyRequest($claimed);
                assertSame('failed', $failed['generation_status']);
                assertSame('scripture_verification_failed', $failed['failure_code']);
                $app->retryAgentReplyRequest($initial, $claimed['target_content_hash']);
                $done = $app->fulfillAgentReplyRequest($store->claimRequestedForPost($initial));
                assertSame('generated', $done['generation_status']);
                $body = (string) $pdo->query('SELECT body FROM posts WHERE post_id = ' . $pdo->quote($done['agent_post_id']))->fetchColumn();
                assertStringContains('Further scripture reflection:', $body);
                assertStringContains('Proverbs 21:5, KJV', $body);
                $page = $this->renderMethod($app, 'GET', '/threads/' . $source);
                assertStringContains('data-expansion-body', $page);
                assertStringContains('Proverbs 21:5', $page);
                (new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder())->build($db, $directory . '/verified.sqlite3');
                $copy = new PDO('sqlite:' . $directory . '/verified.sqlite3');
                assertSame($body, $copy->query('SELECT body FROM posts WHERE post_id = ' . $copy->quote($done['agent_post_id']))->fetchColumn());
            });
        } finally { putenv($previous === false ? 'SCRIPTURE_RESPONSE_GUIDANCE' : 'SCRIPTURE_RESPONSE_GUIDANCE=' . $previous); }
    }

    public function testVerifiedScriptureStoredDraftFailureAndExplicitRetry(): void
    {
        $previous = getenv('SCRIPTURE_RESPONSE_GUIDANCE'); putenv('SCRIPTURE_RESPONSE_GUIDANCE=verified');
        try {
            $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source): void {
                $created = $this->renderMethod($app, 'POST', '/api/create_reply?thread_id=' . $source . '&parent_id=' . $source . '&body=How+should+I+choose+a+stopping+point%3F');
                $target = $this->extractValue($created, 'post_id');
                $this->renderMethod($app, 'POST', '/api/generate_agent_reply?post_id=' . $target . '&response_mode=scripture_oriented_analysis');
                $claimed = $store->claimRequestedForPost($target);
                $store->saveComplete($claimed['request_context'], ['response_text' => 'An old unverified quotation.', 'raw_response' => []]);
                $failed = $app->fulfillAgentReplyRequest($claimed);
                assertSame('failed', $failed['generation_status']);
                assertSame('scripture_verification_failed', $failed['failure_code']);
                $again = $app->fulfillAgentReplyRequest($claimed);
                assertSame('failed', $again['generation_status']);
                assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE parent_id = ' . $pdo->quote($target))->fetchColumn());
                $app->retryAgentReplyRequest($target, $claimed['target_content_hash']);
                $done = $app->fulfillAgentReplyRequest($store->claimRequestedForPost($target));
                assertSame('generated', $done['generation_status']);
                assertSame('already_posted', $app->fulfillAgentReplyRequest($claimed)['generation_status']);
                assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE parent_id = ' . $pdo->quote($target))->fetchColumn());
            });
        } finally { putenv($previous === false ? 'SCRIPTURE_RESPONSE_GUIDANCE' : 'SCRIPTURE_RESPONSE_GUIDANCE=' . $previous); }
    }

    public function testVerifiedScriptureInitialPublishesThroughNormalRequest(): void
    {
        $previous = getenv('SCRIPTURE_RESPONSE_GUIDANCE');
        putenv('SCRIPTURE_RESPONSE_GUIDANCE=verified');
        try {
            $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial): void {
                $body = (string) $pdo->query('SELECT body FROM posts WHERE post_id = ' . $pdo->quote($initial))->fetchColumn();
                assertStringContains('The thoughts of the diligent tend only to plenteousness;', $body);
                assertStringContains('Proverbs 21:5, KJV', $body);
                assertStringNotContains('"segments"', $body);
                $row = $store->findByTarget($source, (string) $pdo->query('SELECT target_content_hash FROM post_generated_responses WHERE target_post_id = ' . $pdo->quote($source))->fetchColumn());
                assertSame(\ForumRewrite\Agent\ScriptureSource::DIGEST, $row['raw_response']['scripture_verification']['source_digest']);
                $status = json_decode($this->renderMethod($app, 'GET', '/api/scripture_reply_status?post_id=' . $source), true);
                assertSame($initial, $status['agent_post_id']);
                assertStringContains('Proverbs 21:5', $this->renderMethod($app, 'GET', '/threads/' . $source));
            });
        } finally { putenv($previous === false ? 'SCRIPTURE_RESPONSE_GUIDANCE' : 'SCRIPTURE_RESPONSE_GUIDANCE=' . $previous); }
    }

    public function testScriptureInitialStatusReadsStoredResultsAndHonorsVisibility(): void
    {
        $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial): void {
            $url = '/api/scripture_reply_status?post_id=' . $source;
            $before = $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn();
            $_COOKIE = [];
            $first = json_decode($this->renderMethod($app, 'GET', $url), true);
            $again = json_decode($this->renderMethod($app, 'GET', $url), true);
            assertSame('already_posted', $first['generation_status']);
            assertSame($initial, $first['agent_post_id']);
            assertSame($first, $again);
            assertSame($before, $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn());
            assertSame('error', json_decode($this->renderMethod($app, 'POST', $url), true)['status']);
            $pdo->exec('UPDATE posts SET is_hidden = 1 WHERE post_id = ' . $pdo->quote($initial));
            assertSame('error', json_decode($this->renderMethod($app, 'GET', $url), true)['status']);
            $pdo->exec('UPDATE posts SET is_hidden = 0 WHERE post_id = ' . $pdo->quote($initial));
            $pdo->exec('UPDATE posts SET retracted_at = "2026-01-01" WHERE post_id = ' . $pdo->quote($source));
            assertSame('error', json_decode($this->renderMethod($app, 'GET', $url), true)['status']);
        });
    }

    private function withScriptureExpansionFixture(callable $check): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $directory = dirname($db) . '/expansion-fixture-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $overrides = ['FORUM_SECRETS_PATH' => $directory . '/absent.php', 'LLM_PROVIDER' => 'stub',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $directory . '/queue.sqlite3',
            'FAST_SCORING_DATABASE_PATH' => $directory . '/scores.sqlite3',
            'LLM_EXCHANGE_DATABASE_PATH' => $directory . '/exchanges.sqlite3', 'FORUM_PUBLIC_ARTIFACT_ROOT' => $artifacts];
        $previous = [];
        foreach ($overrides as $key => $value) { $previous[$key] = getenv($key); putenv($key . '=' . $value); }
        try {
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $source = $this->createAnalyzedThread($app, $db);
            $_COOKIE = ['identity_hint' => 'guest'];
            $this->renderMethod($app, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($source) . '&response_mode=scripture_oriented_analysis');
            $pdo = new PDO('sqlite:' . $db);
            $store = new SqliteAgentReplyGenerationStore($pdo);
            $first = $app->fulfillAgentReplyRequest($store->claimRequestedForPost($source));
            assertSame('generated', $first['generation_status']);
            $check($app, $pdo, $store, $source, $first['agent_post_id'], $root, $db, $artifacts, $directory);
        } finally {
            foreach ($previous as $key => $value) { putenv($value === false ? $key : $key . '=' . $value); }
            $_COOKIE = [];
        }
    }

    public function testScriptureExpansionRequestPublicationAndRebuild(): void
    {
        $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial, $root, $db, $artifacts): void {
            $api = '/api/expand_agent_reply?post_id=' . rawurlencode($initial);
            foreach (['/threads/' . $source, '/posts/' . $initial] as $path) {
                assertStringContains('data-action="expand-scripture"', $this->renderMethod($app, 'GET', $path));
            }
            $_COOKIE = [];
            assertSame('forbidden', json_decode($this->renderMethod($app, 'POST', $api), true)['error']);
            $_COOKIE = ['identity_hint' => 'guest'];
            assertSame('requested', json_decode($this->renderMethod($app, 'POST', $api), true)['generation_status']);
            assertSame('requested', json_decode($this->renderMethod($app, 'POST', $api), true)['generation_status']);
            $claimed = $store->claimRequestedForPost($initial);
            assertSame(null, $store->claimRequestedForPost($initial));
            $done = $app->fulfillAgentReplyRequest($claimed);
            assertSame('generated', $done['generation_status']);
            $saved = $store->findByTarget($initial, $claimed['target_content_hash']);
            $sourcePost = $pdo->query('SELECT * FROM posts WHERE post_id = ' . $pdo->quote($source))->fetch(PDO::FETCH_ASSOC);
            $participants = (new \ForumRewrite\Agent\AgentParticipantContext($pdo, $root))->resolve($sourcePost, $claimed['request_context']['agent_reply_request']['requested_by_identity_id']);
            assertStringContains(\ForumRewrite\Agent\AgentParticipantContext::prompt($participants), $saved['raw_response']['messages'][0]['content']);
            assertStringContains('Be warm, interested, and direct', $saved['raw_response']['messages'][0]['content']);

            $status = json_decode($this->renderMethod($app, 'GET', '/api/agent_expansion_status?post_id=' . rawurlencode($initial)), true);
            assertSame('generated', $status['generation_status']);
            assertSame('Stub agent response.', trim($status['expansion_body']));
            foreach (['/threads/' . $source, '/posts/' . $initial] as $path) {
                $html = $this->renderMethod($app, 'GET', $path);
                assertStringContains('<summary>Further scripture reflection</summary>', $html);
                assertStringNotContains('data-action="expand-scripture"', $html);
                assertStringNotContains('id="post-' . $done['agent_post_id'] . '"', $html);
            }
            $_COOKIE = [];
            assertStringContains('<summary>Further scripture reflection</summary>', $this->renderMethod($app, 'GET', '/posts/' . $initial));
            $_COOKIE = ['identity_hint' => 'guest'];
            assertSame($done['agent_post_id'], json_decode($this->renderMethod($app, 'POST', $api), true)['agent_post_id']);
            assertSame('error', json_decode($this->renderMethod($app, 'POST', '/api/expand_agent_reply?post_id=' . $done['agent_post_id']), true)['status']);
            // Replaying a claim after canonical publication makes no replacement.
            assertSame($done['agent_post_id'], $app->fulfillAgentReplyRequest($claimed)['agent_post_id']);
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $new = new Application(dirname(__DIR__), $root, $db, $artifacts);
            assertSame('generated', json_decode($this->renderMethod($new, 'GET', '/api/agent_expansion_status?post_id=' . $initial), true)['generation_status']);
        });
    }

    public function testExpansionOfReplyDisappearsWhenThreadRootIsHidden(): void
    {
        $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $rootPost, $ignoredInitial, $root, $db, $artifacts, $directory): void {
            $response = $this->renderMethod($app, 'POST', '/api/create_reply?thread_id=' . $rootPost . '&parent_id=' . $rootPost . '&body=How%20can%20we%20care%20for%20neighbors%3F');
            $source = $this->extractValue($response, 'post_id');
            $this->renderMethod($app, 'POST', '/api/generate_agent_reply?post_id=' . $source . '&response_mode=scripture_oriented_analysis');
            $initial = $app->fulfillAgentReplyRequest($store->claimRequestedForPost($source))['agent_post_id'];
            $this->renderMethod($app, 'POST', '/api/expand_agent_reply?post_id=' . $initial);
            $expansion = $app->fulfillAgentReplyRequest($store->claimRequestedForPost($initial))['agent_post_id'];
            $pdo->exec('UPDATE posts SET is_hidden = 1 WHERE post_id = ' . $pdo->quote($rootPost));
            assertSame('error', json_decode($this->renderMethod($app, 'GET', '/api/agent_expansion_status?post_id=' . $initial), true)['status']);
            (new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder())->build($db, $directory . '/hidden-thread.sqlite3');
            $copy = new PDO('sqlite:' . $directory . '/hidden-thread.sqlite3');
            $stmt = $copy->prepare('SELECT COUNT(*) FROM unavailable_expansions WHERE post_id = ?');
            $stmt->execute([$expansion]); assertSame(1, (int) $stmt->fetchColumn());
        });
    }

    public function testScriptureExpansionCacheAndOfflineVisibility(): void
    {
        $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial, $root, $db, $artifacts, $directory): void {
            @mkdir($artifacts . '/posts', 0700, true);
            file_put_contents($artifacts . '/posts/' . $initial . '.html', 'old initial artifact');
            $this->renderMethod($app, 'POST', '/api/expand_agent_reply?post_id=' . $initial);
            $done = $app->fulfillAgentReplyRequest($store->claimRequestedForPost($initial));
            assertSame('generated', $done['generation_status']);
            assertSame(false, is_file($artifacts . '/posts/' . $initial . '.html'));
            $_COOKIE = [];
            $author = $pdo->query('SELECT author_identity_id FROM posts WHERE post_id = ' . $pdo->quote($initial))->fetchColumn();
            $listed = \ForumRewrite\ReadModel\AuthoredContentRepository::visiblePosts($pdo, [$author]);
            assertSame(false, in_array($done['agent_post_id'], array_column($listed, 'post_id'), true));
            assertStringNotContains($done['agent_post_id'], $this->renderMethod($app, 'GET', '/threads/' . $source . '?format=rss'));
            $public = $this->renderMethod($app, 'GET', '/posts/' . $initial);
            assertStringContains('Further scripture reflection', $public);
            assertStringNotContains('data-action="expand-scripture"', $public);
            $oldDetailFlag = getenv('FORUM_STATIC_DETAIL_PAGES_ENABLED');
            putenv('FORUM_STATIC_DETAIL_PAGES_ENABLED=true');
            try {
                (new StaticArtifactBuilder(dirname(__DIR__), $root, $db, $artifacts))->buildFromReadModel();
                assertStringContains('Further scripture reflection', file_get_contents($artifacts . '/posts/' . $initial . '.html'));
                assertStringNotContains('data-action="expand-scripture"', file_get_contents($artifacts . '/threads/' . $source . '.html'));
            } finally {
                putenv($oldDetailFlag === false ? 'FORUM_STATIC_DETAIL_PAGES_ENABLED' : 'FORUM_STATIC_DETAIL_PAGES_ENABLED=' . $oldDetailFlag);
            }
            $snapshot = new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder();
            $snapshot->build($db, $directory . '/complete.sqlite3');
            $copy = new PDO('sqlite:' . $directory . '/complete.sqlite3');
            $stmt = $copy->prepare('SELECT agent_response_json FROM posts WHERE post_id = ?');
            $stmt->execute([$done['agent_post_id']]);
            assertSame('scripture_expansion', json_decode($stmt->fetchColumn(), true)['type']);
            $stmt->closeCursor(); $copy = null;
            foreach (['is_hidden = 1', "retracted_at = '2026-10-15T00:00:00Z'", "body = body || ' changed'"] as $index => $change) {
                $pdo->exec('UPDATE posts SET ' . $change . " WHERE post_id = " . $pdo->quote($source));
                assertStringNotContains('Further scripture reflection', $this->renderMethod($app, 'GET', '/posts/' . $initial));
                $snapshot->build($db, $directory . '/stale-' . $index . '.sqlite3');
                $copy = new PDO('sqlite:' . $directory . '/stale-' . $index . '.sqlite3');
                $stmt = $copy->prepare('SELECT COUNT(*) FROM posts WHERE post_id = ?');
                $stmt->execute([$done['agent_post_id']]);
                assertSame(0, (int) $stmt->fetchColumn()); $stmt->closeCursor(); $copy = null;
                if ($index < 2) { $pdo->exec('UPDATE posts SET is_hidden = 0, retracted_at = NULL WHERE post_id = ' . $pdo->quote($source)); }
            }
        });
    }

    public function testScriptureExpansionFailureRetryAndSourceRace(): void
    {
        foreach (['exhaustion', 'changed', 'revoked'] as $case) {
            $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial, $root, $db, $artifacts, $directory) use ($case): void {
                $api = '/api/expand_agent_reply?post_id=' . $initial;
                $this->renderMethod($app, 'POST', $api);
                $claimed = $store->claimRequestedForPost($initial);
                $workflow = (new ReflectionMethod($app, 'postWorkflowService'))->invoke($app);
                $provider = new class($pdo, $source, $claimed, $case) implements \ForumRewrite\Llm\TextChatProvider {
                    public function __construct(private PDO $pdo, private string $source, private array $row, private string $case) {}
                    public function completeTextChat(array $messages, array $options = []): array {
                        if ($this->case === 'exhaustion') { throw new \ForumRewrite\Llm\CompletionLimitException(); }
                        if ($this->case === 'changed') {
                            $stmt = $this->pdo->prepare('UPDATE posts SET body = body || ? WHERE post_id = ?');
                            $stmt->execute([' edited', $this->source]);
                        } else {
                            $stmt = $this->pdo->prepare('UPDATE profiles SET is_approved = 0 WHERE identity_id = ?');
                            $stmt->execute([$this->row['request_context']['agent_reply_request']['requested_by_identity_id']]);
                        }
                        return ['response_text' => 'Additional reflection.'];
                    }
                };
                $fetch = static function (string $id) use ($pdo): ?array {
                    $stmt = $pdo->prepare('SELECT * FROM posts WHERE post_id = ? AND is_hidden = 0');
                    $stmt->execute([$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                };
                $service = new \ForumRewrite\Agent\ScriptureExpansionService($pdo, $root, $directory . '/queue.sqlite3', $fetch,
                    fn (array $post): array => $workflow->postAnalysisContext($post, false),
                    fn () => new \ForumRewrite\Agent\AgentResponseGenerator($provider, true, true),
                    new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root)),
                    (new ReflectionMethod($workflow, 'agentIdentityService'))->invoke($workflow), true);
                $result = $service->fulfill($claimed);
                assertSame($case === 'exhaustion' ? 'failed' : 'not_recommended', $result['generation_status']);
                assertSame(false, is_file($root . '/records/posts/' . \ForumRewrite\Canonical\AgentResponseMetadata::expansionId($initial) . '.txt'));
                if ($case === 'exhaustion') {
                    assertSame('completion_limit_exhausted', $result['failure_code']);
                    $app->retryAgentReplyRequest($initial, $claimed['target_content_hash']);
                    assertSame('generated', $app->fulfillAgentReplyRequest($store->claimRequestedForPost($initial))['generation_status']);
                }
            });
        }
    }

    public function testScriptureExpansionRejectsChangedAndHiddenSources(): void
    {
        $this->withScriptureExpansionFixture(function ($app, $pdo, $store, $source, $initial): void {
            $api = '/api/expand_agent_reply?post_id=' . rawurlencode($initial);
            $this->renderMethod($app, 'POST', $api);
            $claimed = $store->claimRequestedForPost($initial);
            $stmt = $pdo->prepare('UPDATE posts SET body = body || ? WHERE post_id = ?');
            $stmt->execute([' changed', $source]);
            assertSame('not_recommended', $app->fulfillAgentReplyRequest($claimed)['generation_status']);
            assertSame('error', json_decode($this->renderMethod($app, 'POST', $api), true)['status']);
            $stmt = $pdo->prepare('UPDATE posts SET is_hidden = 1 WHERE post_id = ?');
            $stmt->execute([$source]);
            assertSame('error', json_decode($this->renderMethod($app, 'GET', '/api/agent_expansion_status?post_id=' . $initial), true)['status']);
        });
    }

    public function testExpansionProvenanceSurvivesRebuildAndPublicationReplay(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
        $meta = ['type' => 'scripture_oriented_analysis', 'source_post_id' => 'root-001', 'source_hash' => str_repeat('a', 64)];
        $initial = $writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Scripture response.'], $meta);
        $expansion = ['type' => 'scripture_expansion', 'source_post_id' => 'root-001', 'source_hash' => str_repeat('a', 64),
            'expansion_of' => $initial['post_id'], 'initial_hash' => str_repeat('b', 64)];
        $input = ['thread_id' => 'root-001', 'parent_id' => $initial['post_id'], 'body' => 'Additional context.'];
        $first = $writer->createReply($input, $expansion);
        $again = $writer->createReply($input, $expansion);
        assertSame($first['post_id'], $again['post_id']);
        assertSame(true, $again['already_posted']);
        $record = (new CanonicalRecordRepository($root))->loadPost('records/posts/' . $first['post_id'] . '.txt');
        assertSame($expansion, $record->agentResponse);
        (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
        $pdo = new PDO('sqlite:' . $db);
        $stmt = $pdo->prepare('SELECT agent_response_json FROM posts WHERE post_id = ?');
        $stmt->execute([$first['post_id']]);
        assertSame($expansion, json_decode($stmt->fetchColumn(), true));
        $stmt->closeCursor();
        $pdo = null;
        // Recover a canonical file written before its commit/read-model refresh.
        $this->runCommand($root, 'git reset --soft HEAD~1');
        $writer->createReply($input, $expansion);
        assertSame('', trim($this->runCommand($root, 'git status --porcelain')));
    }

    public function testOperatorRetryRepairsHistoricalCompletionAndRunsOnlySelectedTask(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $queuePath = sys_get_temp_dir() . '/reply-retry-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previous = $this->useStubLlmProvider();
        $oldQueue = getenv('FORUM_TASK_QUEUE_DATABASE_PATH');
        $oldArtifacts = getenv('FORUM_PUBLIC_ARTIFACT_ROOT');
        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
        putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $artifacts);
        try {
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $post = $this->createAnalyzedThread($app, $db);
            $_COOKIE = ['identity_hint' => 'guest'];
            $this->renderMethod($app, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($post) . '&response_mode=scripture_oriented_analysis');
            $pdo = new PDO('sqlite:' . $db);
            $hash = (string) $pdo->query('SELECT target_content_hash FROM post_generated_responses')->fetchColumn();
            $replies = new SqliteAgentReplyGenerationStore($pdo);
            $replies->saveFailed($post, $hash, 'task:old', 'analysis_suggestion_error', 'Historical length failure');
            $queue = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            foreach ($queue->claimNext(100) as $old) { $queue->markCompleted($old['id']); }
            $unrelated = $queue->enqueue(SqliteTaskQueueStore::AGENT_REPLY, 'unrelated@hash');
            try {
                $app->retryAgentReplyRequest($post, 'wrong-version');
                throw new RuntimeException('Stale recovery accepted');
            } catch (InvalidArgumentException $expected) {
                assertStringContains('content changed', $expected->getMessage());
            }
            // Simulate interruption between resetting the reply and enqueueing its task.
            $app->retryAgentReplyRequest($post, $hash);
            $args = ' --repository-root=' . escapeshellarg($root) . ' --database-path=' . escapeshellarg($db)
                . ' --queue-database-path=' . escapeshellarg($queuePath);
            $retry = 'php scripts/task_queue.php retry-agent-reply --post-id=' . escapeshellarg($post)
                . ' --content-hash=' . escapeshellarg($hash) . $args;
            $output = $this->runCommand(dirname(__DIR__), $retry);
            assertSame(1, preg_match('/task_id=(\d+)/', $output, $match));
            assertStringContains('task_id=' . $match[1], $this->runCommand(dirname(__DIR__), $retry));
            $run = 'php scripts/task_queue.php run --task-id=' . $match[1] . $args;
            assertStringContains('completed=1', $this->runCommand(dirname(__DIR__), $run));
            assertStringContains('claimed=0', $this->runCommand(dirname(__DIR__), $run));
            assertSame('queued', $queue->findById($unrelated['id'])['status']);
            $row = $replies->findByTarget($post, $hash);
            assertSame('posted', $row['status']);
            assertSame('scripture_oriented_analysis', $row['response_intent']);
            try {
                $app->retryAgentReplyRequest($post, $hash);
                throw new RuntimeException('Published recovery accepted');
            } catch (InvalidArgumentException $expected) {
                assertStringContains('Only failed', $expected->getMessage());
            }
        } finally {
            $this->restoreLlmProviderEnv($previous);
            putenv($oldQueue === false ? 'FORUM_TASK_QUEUE_DATABASE_PATH' : 'FORUM_TASK_QUEUE_DATABASE_PATH=' . $oldQueue);
            putenv($oldArtifacts === false ? 'FORUM_PUBLIC_ARTIFACT_ROOT' : 'FORUM_PUBLIC_ARTIFACT_ROOT=' . $oldArtifacts);
            $_COOKIE = [];
        }
    }

    public function testInvalidReplyBudgetFailsWithoutPublicationAndHasActionableFeedback(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previous = $this->useStubLlmProvider();
        $oldBudget = getenv('AGENT_REPLY_MAX_COMPLETION_TOKENS');
        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $_COOKIE = ['identity_hint' => 'guest'];
            $this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId));
            putenv('AGENT_REPLY_MAX_COMPLETION_TOKENS=0');
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $result = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($postId));
            assertSame('failed', $result['generation_status']);
            assertSame('provider_configuration_error', $result['failure_code']);
            foreach (['threads', 'posts'] as $surface) {
                assertStringContains('Provider configuration needs correction',
                    $this->renderMethod($application, 'GET', '/' . $surface . '/' . rawurlencode($postId)));
            }
        } finally {
            putenv($oldBudget === false ? 'AGENT_REPLY_MAX_COMPLETION_TOKENS' : 'AGENT_REPLY_MAX_COMPLETION_TOKENS=' . $oldBudget);
            $this->restoreLlmProviderEnv($previous);
            $_COOKIE = [];
        }
    }

    public function testGenerateAgentReplyRecordsPostingFailureAfterGeneration(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $this->deleteTree($repositoryRoot . '/.git');

            $_COOKIE = ['identity_hint' => 'guest'];
            $request = json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId) . '&response_mode=scripture_oriented_analysis'), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $response = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($postId));
            $pdo = new PDO('sqlite:' . $databasePath);
            $row = $pdo->query('SELECT status, failure_code, failure_message, agent_post_id FROM post_generated_responses')->fetch();

            assertSame('requested', $request['generation_status']);
            assertSame('Scripture-oriented analysis', $request['response_mode_label']);
            assertStringContains('Scripture-oriented analysis failed.',
                $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId)));
            assertSame('failed', $response['generation_status']);
            assertSame('posting_error', $response['failure_code']);
            assertSame('failed', $row['status']);
            assertSame('posting_error', $row['failure_code']);
            assertStringContains('Writable repository must be a git checkout', $row['failure_message']);
            assertSame(null, $row['agent_post_id']);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testLinkIdentityUsesPublicKeyUserIdForUsernameAndInvalidatesProfileArtifact(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $this->seedArtifacts($artifactRoot, [
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html',
            '/threads/root-001.html',
            '/posts/root-001.html',
        ]);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/link_identity?bootstrap_post_id=root-001'
        );
        $_POST = [];
        $commitSha = $this->extractValue($response, 'commit_sha');

        $profile = $this->renderMethod(
            $application,
            'GET',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        );
        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('status=ok', $response);
        assertStringContains('username=forum-user', $response);
        assertTrue(strlen($commitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'));
        assertTrue(is_file($repositoryRoot . '/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc'));
        assertStringContains('<h1>forum-user</h1>', $profile);
        assertStringContains('<h1 class="account-name">forum-user</h1>', $usernameRoute);
        assertStringNotContains('Approved Profiles', $usernameRoute);
        // Manage access is shown to operators only.
        assertStringNotContains('/tools/user-access/accounts/forum-user', $usernameRoute);
        assertFalse(is_file($artifactRoot . '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html'));
        assertFalse(is_file($artifactRoot . '/threads/root-001.html'));
        assertFalse(is_file($artifactRoot . '/posts/root-001.html'));
        assertSame($commitSha, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
    }

    public function testLinkIdentityAutoCreatesHiddenBootstrapPostWhenNoBootstrapPostIdIsProvided(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];

        $bootstrapPostId = $this->extractValue($response, 'bootstrap_post_id');
        $bootstrapThreadId = $this->extractValue($response, 'bootstrap_thread_id');
        $bootstrapRecord = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $bootstrapPostId));
        $board = $this->renderMethod($application, 'GET', '/');
        $activity = $this->renderMethod($application, 'GET', '/activity/?view=all');
        $identityActivity = $this->renderMethod($application, 'GET', '/activity/?view=identity');
        $bootstrapActivity = $this->renderMethod($application, 'GET', '/activity/?view=bootstrap');
        $bootstrapPostPage = $this->renderMethod($application, 'GET', '/posts/' . $bootstrapPostId);
        $profile = $this->renderMethod(
            $application,
            'GET',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        );

        assertStringContains('status=ok', $response);
        assertSame($bootstrapPostId, $bootstrapThreadId);
        assertStringContains('Board-Tags: identity internal', $bootstrapRecord);
        assertStringContains('Subject: account bootstrap', $bootstrapRecord);
        assertStringNotContains($bootstrapPostId, $board);
        assertStringContains($bootstrapPostId, $activity);
        assertStringContains('identity_bootstrap', $activity);
        assertStringContains($bootstrapPostId, $identityActivity);
        assertStringContains($bootstrapPostId, $bootstrapActivity);
        assertStringContains('Automatic account bootstrap anchor.', $bootstrapPostPage);
        assertStringContains('Threads:</strong> 0', $profile);
        assertStringContains('Posts:</strong> 0', $profile);
    }

    public function testHtmlComposeAndAccountFormsSubmitAgainstWritableRepo(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'board_tags' => 'general',
            'subject' => 'Form Thread',
            'body' => 'Form body',
        ];
        $threadResponse = $this->renderMethod($application, 'POST', '/compose/thread');
        $_POST = [];

        $threadId = $this->extractHrefId($threadResponse, '/threads/');

        $_POST = [
            'thread_id' => $threadId,
            'parent_id' => $threadId,
            'board_tags' => 'general',
            'body' => 'Form reply body',
        ];
        $replyResponse = $this->renderMethod($application, 'POST', '/compose/reply');
        $_POST = [];

        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $accountResponse = $this->renderMethod($application, 'POST', '/account/key/');
        $_POST = [];

        assertStringContains('Redirecting', $threadResponse);
        assertStringContains('Created thread', $threadResponse);
        assertStringContains('created_post_id=', $threadResponse);
        assertStringContains('__v=', $threadResponse);
        assertStringContains('Commit ', $threadResponse);
        assertStringContains('Redirecting', $replyResponse);
        assertStringContains('Created reply', $replyResponse);
        assertStringContains('/threads/' . $threadId, $replyResponse);
        assertStringContains('created_post_id=', $replyResponse);
        assertStringContains('__v=', $replyResponse);
        assertStringContains('#post-', $replyResponse);
        assertStringContains('Commit ', $replyResponse);
        assertStringContains('Redirecting', $accountResponse);
        assertStringContains('Linked identity', $accountResponse);
        assertStringContains('/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $accountResponse);
        assertStringContains('Commit ', $accountResponse);
    }

    public function testAccountPageLinksLoggedInApprovedUserToProfileRoute(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $account = $this->renderMethod($application, 'GET', '/account/key/');
        $_COOKIE = [];

        assertStringContains('This key’s profile', $account);
        assertStringContains('href="/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954"', $account);
        assertStringNotContains('href="/user/guest"', $account);
    }

    public function testAccountPageLinksLoggedInUnapprovedUserToProfileRoute(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identity = $this->linkGeneratedIdentity($application, 'pending-user');

        $_COOKIE = ['identity_hint' => $identity['identity_id']];
        $account = $this->renderMethod($application, 'GET', '/account/key/');
        $_COOKIE = [];

        assertStringContains('This key’s profile', $account);
        assertStringContains('href="/profiles/' . $identity['profile_slug'] . '"', $account);
        assertStringNotContains('href="/user/pending-user"', $account);
    }

    public function testCreateThreadUsesAuthorIdentityForRenderedAuthorLabel(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $identityResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $identityId = $this->extractValue($identityResponse, 'identity_id');
        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Signed%20Thread&body=Thread%20body&author_identity_id=' . rawurlencode($identityId)
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);

        assertStringContains('status=ok', $threadResponse);
        assertStringContains('forum-user', $threadPage);
        assertStringContains('/user/forum-user', $threadPage);
        assertStringContains('by <a href="/user/forum-user">forum-user</a> on <time datetime="', $threadPage);
        assertStringNotContains('(unapproved)', $threadPage);
        assertFalse(str_contains($threadPage, 'by guest'));
    }

    public function testPrepareThreadReturnsCanonicalRecordWithoutCommittingPost(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/prepare_thread?board_tags=general&subject=Prepared%20Thread&body=Prepared%20body&author_identity_id=' . rawurlencode($identityId)
        );
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('ok', $payload['status']);
        assertStringContains('thread-', (string) $payload['post_id']);
        assertSame($payload['post_id'], $payload['thread_id']);
        assertTrue(preg_match('#^records/posts/\d{4}/\d{2}/\d{2}/' . preg_quote((string) $payload['post_id'], '#') . '\.txt$#', (string) $payload['record_path']) === 1);
        assertStringContains('Post-ID: ' . $payload['post_id'] . "\n", $payload['canonical_record']);
        assertStringContains("Author-Identity-ID: {$identityId}\n", $payload['canonical_record']);
        assertStringContains("Subject: Prepared Thread\n\nPrepared body\n", $payload['canonical_record']);
        assertSame(hash('sha256', $payload['canonical_record']), $payload['canonical_sha256']);
        assertTrue(is_file(dirname($databasePath) . '/prepared-posts/' . $payload['prepare_token'] . '.json'));
        assertFalse(is_file($repositoryRoot . '/' . $payload['record_path']));
    }

    public function testPrepareThreadIncludesEventFieldsInCanonicalRecord(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\n"
        );
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/prepare_thread?board_tags=general&subject=Prepared%20Event&body=Prepared%20body'
            . '&author_identity_id=' . rawurlencode($identityId)
            . '&event_date=2026-11-01&event_time=19%3A00&event_location=Cambridge&event_link=' . rawurlencode('https://example.test/rsvp')
        );
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('ok', $payload['status']);
        assertStringContains("Event-Date: 2026-11-01\n", $payload['canonical_record']);
        assertStringContains("Event-Time: 19:00\n", $payload['canonical_record']);
        assertStringContains("Event-Location: Cambridge\n", $payload['canonical_record']);
        assertStringContains("Event-Link: https://example.test/rsvp\n", $payload['canonical_record']);
    }

    public function testPrepareThreadRejectsEventFieldsWhenEventSupportDisabled(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $service = new LocalWriteService(
            $repositoryRoot,
            $databasePath,
            $artifactRoot,
            new CanonicalRecordRepository($repositoryRoot),
            featureFlags: new FeatureFlagEvaluator(siteValues: [FeatureFlagRegistry::EVENT_SUPPORT_ENABLED => false]),
        );

        try {
            $service->prepareThread([
                'board_tags' => 'general',
                'subject' => 'Event thread',
                'body' => 'Event body',
                'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                'event_date' => '2026-11-01',
            ]);
            throw new RuntimeException('Expected event-support validation failure.');
        } catch (RuntimeException $exception) {
            assertSame('Event support is disabled for this site.', $exception->getMessage());
        }
    }

    public function testPrepareThreadRejectsMalformedEventTimeBeforeIssuingToken(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\n"
        );
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $preparedPostsBefore = glob(dirname($databasePath) . '/prepared-posts/*.json') ?: [];

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/prepare_thread?board_tags=general&subject=Bad%20Time&body=body'
            . '&author_identity_id=' . rawurlencode($identityId)
            . '&event_date=2026-11-01&event_time=99%3A99'
        );
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $preparedPostsAfter = glob(dirname($databasePath) . '/prepared-posts/*.json') ?: [];

        assertSame('error', $payload['status']);
        assertStringContains('event_time must be a valid 24-hour time.', $payload['error']);
        assertSame(count($preparedPostsBefore), count($preparedPostsAfter));
    }

    public function testPrepareThreadWithEventFieldsRoundTripsThroughFinalizeAndReadModel(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\n"
        );
        $signingKey = $this->createSigningKey('event-thread-signer');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = ['public_key' => $signingKey['public_key']];
        $linkResponse = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];
        $identityId = $this->extractValue($linkResponse, 'identity_id');

        $prepared = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_thread?board_tags=general&subject=Cypher%20Night&body=' . rawurlencode('Doors open sharp.')
            . '&author_identity_id=' . rawurlencode($identityId)
            . '&event_date=2026-11-14&event_time=19%3A30'
            . '&event_location=' . rawurlencode('MIT Media Lab, E14')
            . '&event_link=' . rawurlencode('https://example.test/cypher-night')
        ), true, 512, JSON_THROW_ON_ERROR);

        $signature = $this->signCanonicalRecord($signingKey['home'], $prepared['canonical_record']);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $identityId,
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $signature,
        ];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_post'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];

        assertSame('ok', $finalized['status']);
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode((string) $prepared['thread_id']));
        assertStringContains('event-block__date">📅 2026-11-14 at 19:30', $threadPage);
        assertStringContains('MIT Media Lab, E14', $threadPage);
        assertStringContains('https://example.test/cypher-night', $threadPage);
    }

    public function testOperatorRetractionCapturesAccountEvidenceAtAcceptance(): void
    {
        foreach (['same', 'different', 'unapproved', 'revoked', 'dirty', 'empty', 'missing'] as $scenario) {
            [$root, $db, $artifacts] = $this->createTempEnvironment();
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $author = $this->createAuthoredThread($app, 'AccountEvidence', 'Evidence target');
            $operator = $this->linkSigningIdentity($app, $scenario === 'different' ? 'OtherAccount' : 'accountevidence');
            $this->seedApprovedIdentity($root, $operator['identity_id']);
            if ($scenario !== 'unapproved') $this->seedApprovedIdentity($root, $author['identity_id']);
            if (in_array($scenario, ['empty', 'missing'], true)) {
                foreach ([$author, $operator] as $identity) {
                    $path = CanonicalPathResolver::identity(substr($identity['identity_id'], 8));
                    file_put_contents($root . '/' . $path, preg_replace('/^Username:.*\n/m', $scenario === 'empty' ? "Username: \n" : '', file_get_contents($root . '/' . $path)));
                }
                $this->runCommand($root, 'git add records/identity');
                $this->runCommand($root, 'git commit -m "Empty legacy canonical usernames"');
            }
            $prepared = $this->prepareRetractionRequest($app, $operator['identity_id'], $author['thread_id']);
            assertSame('ok', $prepared['status']);
            if ($scenario === 'revoked') {
                $this->runCommand($root, 'git rm ' . escapeshellarg('records/approval-seeds/openpgp-' . substr($author['identity_id'], 8) . '.txt'));
                $this->runCommand($root, 'git commit -m "Revoke target before acceptance"');
            }
            if ($scenario === 'dirty') file_put_contents($root . '/records/uncommitted.txt', 'Uncommitted account evidence');
            $_POST = ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
                'record_path' => $prepared['record_path'], 'actor_identity_id' => $operator['identity_id'],
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->signCanonicalRecord($operator['home'], $prepared['canonical_record'])];
            $result = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true);
            $_POST = [];
            assertSame('complete', $result['retraction_status']);
            $evidence = \ForumRewrite\Canonical\AcceptedRetraction::read($root, $author['thread_id']);
            assertSame($scenario === 'same' ? 'author' : 'operator', $evidence['attribution']);
            assertSame($operator['identity_id'], $evidence['actor']);
            assertSame($author['identity_id'], $evidence['post']['author_identity_id']);
            assertSame($prepared['canonical_record'], $evidence['signed_request']);
            assertStringContains('Retraction-Class: operator', $prepared['canonical_record']);
            $loaded = (new CanonicalRecordRepository($root))->loadPostRetraction($prepared['record_path']);
            assertSame('operator', $loaded->classification);
            assertSame($scenario === 'same' ? 'author' : 'operator', $loaded->attribution);
            $notice = $scenario === 'same' ? 'retracted by its author' : 'retracted by an operator';
            foreach (['/threads/' . $author['thread_id'], '/posts/' . $author['thread_id'],
                '/forte?selected=' . $author['thread_id'], '/source/current/' . $author['record_path'],
                '/source/blob/' . $result['commit_sha'] . '/' . $author['record_path']] as $route) {
                $output = $this->renderMethod($app, 'GET', $route);
                assertStringContains($notice, $output, $scenario . ' at ' . $route);
                assertSame(false, str_contains($output, 'Evidence target'));
            }
            $pdo = new PDO('sqlite:' . $db);
            foreach (['posts', 'threads'] as $table) {
                $idColumn = $table === 'posts' ? 'post_id' : 'root_post_id';
                $query = $pdo->prepare('SELECT retraction_class, retraction_attribution FROM ' . $table . ' WHERE ' . $idColumn . ' = ?');
                $query->execute([$author['thread_id']]);
                assertSame(['retraction_class' => 'operator', 'retraction_attribution' => $scenario === 'same' ? 'author' : 'operator'], $query->fetch(PDO::FETCH_ASSOC));
            }
            unset($query, $pdo);
            assertSame('operator', \ForumRewrite\Canonical\RetractedPosts::effectivePostIds($root)[$author['thread_id']]);
            if ($scenario === 'same') {
                $this->runCommand($root, 'git rm ' . escapeshellarg('records/approval-seeds/openpgp-' . substr($author['identity_id'], 8) . '.txt'));
                $this->runCommand($root, 'git commit -m "Revoke target after acceptance"');
                assertSame('author', (new CanonicalRecordRepository($root))->loadPostRetraction($prepared['record_path'])->attribution);
                (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
                assertStringContains($notice, $this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']));
                assertStringContains($notice, $this->renderMethod($app, 'GET', '/source/current/' . $author['record_path']));
            }
            $before = $this->latestCommitSha($root);
            $retry = $this->prepareRetractionRequest($app, $operator['identity_id'], $author['thread_id']);
            assertSame(true, $retry['already_retracted']);
            assertSame($before, $this->latestCommitSha($root));
        }
    }

    public function testSameAccountQuoteAndReplyNoticesShareAcceptedAttribution(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $author = $this->linkSigningIdentity($app, 'quote-account');
        $operator = $this->linkSigningIdentity($app, 'QUOTE-ACCOUNT');
        $this->seedApprovedIdentity($root, $author['identity_id']);
        $this->seedApprovedIdentity($root, $operator['identity_id']);
        $finalize = function (array $prepared, array $key, string $endpoint) use ($app): array {
            $_POST = ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
                'record_path' => $prepared['record_path'], 'author_identity_id' => $key['identity_id'],
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])];
            try { return json_decode($this->renderMethod($app, 'POST', $endpoint), true, 512, JSON_THROW_ON_ERROR); }
            finally { $_POST = []; }
        };
        $previousSite = getenv('FORUM_SITE_ID');
        putenv('FORUM_SITE_ID=qdb');
        try {
            $quote = json_decode($this->renderMethod($app, 'POST', '/api/prepare_quote?board_tags=general&body=Account%20quote&author_identity_id=' . $author['identity_id']), true);
            assertSame('ok', $finalize($quote, $author, '/api/create_prepared_post')['status']);
            $replyId = $this->createAuthoredReply($app, $author, $quote['post_id'], 'Account reply');
            foreach ([$replyId, $quote['post_id']] as $postId) {
                $prepared = $this->prepareRetractionRequest($app, $operator['identity_id'], $postId);
                assertSame('complete', $finalize($prepared, $operator, '/api/create_prepared_retraction')['retraction_status']);
            }
            $page = $this->renderMethod($app, 'GET', '/threads/' . $quote['post_id']);
            assertStringContains('quote-card-body meta', $page);
            assertSame(2, substr_count($page, 'This post was retracted by its author.'));
            assertSame(false, str_contains($page, 'retracted by an operator'));
        } finally { putenv($previousSite === false ? 'FORUM_SITE_ID' : 'FORUM_SITE_ID=' . $previousSite); }
        $paned = $this->renderMethod($app, 'GET', '/forte?selected=' . $quote['post_id']);
        assertSame(2, substr_count($paned, 'This post was retracted by its author.'));
        assertSame(false, str_contains($paned, 'retracted by an operator'));
        $pdo = new PDO('sqlite:' . $db);
        foreach ([\ForumRewrite\ReadModel\AuthoredContentRepository::visibleThreads($pdo, [$author['identity_id']]),
            \ForumRewrite\ReadModel\AuthoredContentRepository::visiblePosts($pdo, [$author['identity_id']])] as $rows) {
            assertSame([], $rows);
        }
        foreach (['threads' => 'root_post_id', 'posts' => 'post_id'] as $table => $idColumn) {
            $row = $pdo->query("SELECT retraction_class, retraction_attribution FROM {$table} WHERE {$idColumn} = " . $pdo->quote($quote['post_id']))->fetch(PDO::FETCH_ASSOC);
            assertSame('operator', $row['retraction_class']);
            assertSame('author', $row['retraction_attribution']);
        }
    }

    public function testApprovedOrdinarySameAccountKeyCannotRetractAnotherKeyPost(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $author = $this->createAuthoredThread($app, 'ordinary-account', 'Ordinary account target');
        $other = $this->linkSigningIdentity($app, 'ORDINARY-ACCOUNT');
        $service = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
        foreach ([$author, $other] as $identity) {
            $pdo = new PDO('sqlite:' . $db);
            $query = $pdo->prepare('SELECT bootstrap_post_id, bootstrap_thread_id FROM profiles WHERE identity_id = ?');
            $query->execute([$identity['identity_id']]);
            $target = $identity + $query->fetch(PDO::FETCH_ASSOC);
            unset($query, $pdo);
            $this->approveIdentity($service, $target);
        }
        $denied = $this->prepareRetractionRequest($app, $other['identity_id'], $author['thread_id']);
        assertSame('error', $denied['status']);
        assertStringContains('Only the post author', $denied['error']);
        assertStringContains('Ordinary account target', $this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']));
    }

    public function testHistoricalRequestCannotGrantAcceptanceOrReplaceCurrentOperatorPrivilege(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $operator = $this->linkSigningIdentity($app, 'history-operator');
        $this->seedApprovedIdentity($root, $operator['identity_id']);
        $actor = $operator['identity_id'];
        $contents = "Record-ID: post-retraction-root-001\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: root-001\nOperation: retract\n"
            . "Author-Identity-ID: openpgp:" . str_repeat('b', 40) . "\nActor-Identity-ID: {$actor}\nRetraction-Class: operator\n\n";
        $path = 'records/post-retractions/post-retraction-root-001.txt';
        mkdir($root . '/records/post-retractions');
        file_put_contents($root . '/' . $path, $contents);
        file_put_contents($root . '/' . $path . '.asc', $this->signCanonicalRecord($operator['home'], $contents));
        assertSame('verified', \ForumRewrite\Security\ReactionSignature::status($root, $path, $contents, $actor));
        $this->runCommand($root, 'git add records/post-retractions');
        $this->runCommand($root, 'git commit -m ' . escapeshellarg('Retract post root-001 by ' . $actor));
        $rejected = false;
        try { (new CanonicalRecordRepository($root))->loadPostRetraction($path); }
        catch (RuntimeException $error) { $rejected = str_contains($error->getMessage(), 'local acceptance'); }
        assertSame(true, $rejected);
        assertSame(true, \ForumRewrite\Security\RetractionAuthority::currentOperator($root, $actor));
        $this->runCommand($root, 'git rm ' . escapeshellarg('records/approval-seeds/openpgp-' . substr($actor, 8) . '.txt'));
        $this->runCommand($root, 'git commit -m "Remove operator privilege"');
        clearstatcache();
        assertSame(false, \ForumRewrite\Security\RetractionAuthority::currentOperator($root, $actor));
    }

    public function testOperatorRetractionSignsAsActorAndRechecksPrivilegeAtFinalize(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $author = $this->createAuthoredThread($app, 'operator-target', 'Operator target prose');
        $second = $this->createAuthoredThread($app, 'operator-target-two', 'Second target prose');
        $this->renderMethod($app, 'POST', '/api/create_reply?thread_id=' . $author['thread_id'] . '&parent_id=' . $author['thread_id'] . '&body=Operator%20reply%20remains');
        $original = sys_get_temp_dir() . '/operator-original-' . bin2hex(random_bytes(6));
        $this->copyDirectory($root . '/records', $original . '/records');
        $operator = $this->linkSigningIdentity($app, 'operator-actor');
        $this->seedApprovedIdentity($root, $operator['identity_id']);
        $protectedPath = 'records/posts/operator-protected.txt';
        file_put_contents($root . '/' . $protectedPath,
            "Post-ID: operator-protected\nCreated-At: 2026-10-14T12:00:00Z\nBoard-Tags: identity approval\n"
            . "Author-Identity-ID: " . $author['identity_id'] . "\n\nProtected authority fixture.\n");
        $this->runCommand($root, 'git add ' . escapeshellarg($protectedPath));
        $this->runCommand($root, 'git commit -m "Add protected authority fixture"');
        $protected = $this->prepareRetractionRequest($app, $operator['identity_id'], 'operator-protected');
        assertSame('error', $protected['status']);
        assertStringContains('cannot be retracted', $protected['error']);
        $oldCookies = $_COOKIE;
        $_COOKIE = ['identity_hint' => $operator['identity_id']];
        try {
            foreach (['/threads/', '/posts/'] as $prefix) {
                $page = $this->renderMethod($app, 'GET', $prefix . $author['thread_id']);
                assertStringContains('aria-label="More actions"', $page);
                assertStringContains('>Retract post</button>', $page);
            }
        } finally { $_COOKIE = $oldCookies; }
        $prepared = $this->prepareRetractionRequest($app, $operator['identity_id'], $author['thread_id']);
        assertSame('ok', $prepared['status']);
        assertStringContains('Author-Identity-ID: ' . $author['identity_id'], $prepared['canonical_record']);
        assertStringContains('Actor-Identity-ID: ' . $operator['identity_id'], $prepared['canonical_record']);
        $fields = [
            'prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'], 'actor_identity_id' => $operator['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
        ];
        $_POST = $fields + ['detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record'])];
        $bad = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true);
        assertSame('error', $bad['status']);
        $_POST = $fields + ['detached_signature' => $this->signCanonicalRecord($operator['home'], $prepared['canonical_record'])];
        $accepted = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true);
        $_POST = [];
        assertSame('complete', $accepted['retraction_status']);
        $commit = $accepted['commit_sha'];
        $commitDetail = json_decode($this->renderMethod($app, 'GET', '/api/forte_commit_detail?sha=' . $commit), true);
        assertSame($operator['identity_id'], \ForumRewrite\Canonical\AcceptedRetraction::read($root, $author['thread_id'])['actor']);
        assertStringContains('post retraction record', $commitDetail['html']);
        assertStringContains('Operator reply remains', $this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']));
        assertStringContains('retracted by an operator', $this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']));
        assertSame(false, str_contains($this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']), 'Operator target prose'));
        assertStringContains('retracted by an operator', $this->renderMethod($app, 'GET', '/source/current/' . $author['record_path']));
        $stale = $this->prepareRetractionRequest($app, $operator['identity_id'], $second['thread_id']);
        $this->runCommand($root, 'git rm ' . escapeshellarg('records/approval-seeds/openpgp-' . substr($operator['identity_id'], 8) . '.txt'));
        $this->runCommand($root, 'git commit -m "Revoke operator"');
        $_POST = ['prepare_token' => $stale['prepare_token'], 'post_id' => $stale['post_id'],
            'record_path' => $stale['record_path'], 'author_identity_id' => $operator['identity_id'],
            'canonical_record' => $stale['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($operator['home'], $stale['canonical_record'])];
        $denied = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true);
        $_POST = [];
        assertSame('error', $denied['status']);
        assertSame(false, is_file($root . '/' . $stale['record_path']));
        $retry = $this->prepareRetractionRequest($app, $operator['identity_id'], $author['thread_id']);
        assertSame(true, $retry['already_retracted']);
        (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
        assertSame(false, str_contains($this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']), 'Operator target prose'));
        assertStringContains('retracted by an operator', $this->renderMethod($app, 'GET', '/source/current/' . $author['record_path']));
        $report = (new \ForumRewrite\Import\ContentImportRunner($root, $db))->run($original, function () use ($root, $db): void {
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
        });
        assertSame('complete', $report['status']);
        clearstatcache(true);
        assertSame(false, is_file($root . '/' . $author['record_path']));
        $restoredPage = $this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']);
        assertSame(false, str_contains($restoredPage, 'Operator target prose'));
        assertStringContains('retracted by an operator', $restoredPage);
        assertStringContains('Operator reply remains', $restoredPage);
        $source = sys_get_temp_dir() . '/operator-import-' . bin2hex(random_bytes(6));
        mkdir($source);
        $this->copyDirectory($root . '/records', $source . '/records');
        $plan = (new \ForumRewrite\Import\ContentImportPlanner())->plan($source, $root);
        assertSame('duplicate', $plan[\ForumRewrite\Canonical\AcceptedRetraction::path($author['thread_id'])]['state']);
        $freshPath = $stale['record_path'];
        file_put_contents($source . '/' . $freshPath, $stale['canonical_record']);
        file_put_contents($source . '/' . $freshPath . '.asc', $this->signCanonicalRecord($operator['home'], $stale['canonical_record']));
        $plan = (new \ForumRewrite\Import\ContentImportPlanner())->plan($source, $root);
        assertSame('invalid', $plan[$freshPath]['state']);
        assertStringContains('local acceptance evidence', $plan[$freshPath]['reason']);
        // A substituted detached signature invalidates the retained evidence set.
        file_put_contents($root . '/' . $prepared['record_path'] . '.asc', 'forged');
        $rejected = false;
        try { (new CanonicalRecordRepository($root))->loadPostRetraction($prepared['record_path']); }
        catch (RuntimeException) { $rejected = true; }
        assertSame(true, $rejected);

    }

    public function testLlmExchangeLinksAppearOnlyOnTheirPostDetailPages(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $exchangeDb = sys_get_temp_dir() . '/exchange-location-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $config = $exchangeDb . '.php';
        file_put_contents($config, '<?php return ' . var_export(['LLM_EXCHANGE_DATABASE_PATH' => $exchangeDb], true) . ';');
        $previous = getenv('FORUM_SECRETS_PATH');
        $cookies = $_COOKIE;
        putenv('FORUM_SECRETS_PATH=' . $config);
        try {
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $recorder = new \ForumRewrite\Llm\LlmExchangeRecorder(new PDO('sqlite:' . $exchangeDb));
            $ids = [];
            foreach (['root-001', 'reply-001'] as $postId) {
                $ids[$postId] = $recorder->record(['call_type' => 'post_analysis', 'post_id' => $postId,
                    'exchange_dependencies' => \ForumRewrite\Llm\ExchangeDependencies::manifest([$postId])],
                    ['payload' => []], ['body' => 'Exchange response'], 'completed', 1);
            }
            $_COOKIE = ['identity_hint' => 'guest'];
            $thread = $this->renderMethod($app, 'GET', '/threads/root-001');
            assertStringNotContains('LLM exchanges:', $thread);
            foreach ($ids as $postId => $id) {
                assertStringNotContains('/tools/llm-exchanges/' . $id . '"', $thread);
                $post = $this->renderMethod($app, 'GET', '/posts/' . $postId);
                assertStringContains('LLM exchanges:', $post);
                assertStringContains('/tools/llm-exchanges/' . $id . '"', $post);
                foreach ($ids as $otherPostId => $otherId) {
                    if ($otherPostId !== $postId) {
                        assertStringNotContains('/tools/llm-exchanges/' . $otherId . '"', $post);
                    }
                }
            }
            $_COOKIE = [];
            assertStringNotContains('LLM exchanges:', $this->renderMethod($app, 'GET', '/posts/root-001'));
            file_put_contents($config, '<?php return ' . var_export([
                'LLM_EXCHANGE_DATABASE_PATH' => $exchangeDb,
                'LLM_CONVERSATION_UI_ENABLED' => false,
            ], true) . ';');
            $_COOKIE = ['identity_hint' => 'guest'];
            $disabledApp = new Application(dirname(__DIR__), $root, $db, $artifacts);
            assertStringNotContains('LLM exchanges:', $this->renderMethod($disabledApp, 'GET', '/posts/root-001'));
        } finally {
            $_COOKIE = $cookies;
            putenv($previous === false ? 'FORUM_SECRETS_PATH' : 'FORUM_SECRETS_PATH=' . $previous);
            unlink($config);
        }
    }

    public function testRetractionMakesDirectAndContextOnlyLlmExchangeDetailsUnavailable(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $exchangeDb = sys_get_temp_dir() . '/exchange-privacy-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $config = $exchangeDb . '.php';
        file_put_contents($config, '<?php return ' . var_export(['LLM_EXCHANGE_DATABASE_PATH' => $exchangeDb], true) . ';');
        $previous = getenv('FORUM_SECRETS_PATH');
        $cookies = $_COOKIE;
        putenv('FORUM_SECRETS_PATH=' . $config);
        try {
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $author = $this->createAuthoredThread($app, 'exchange-author', 'Exchange private prose');
            $pdo = new PDO('sqlite:' . $exchangeDb);
            $recorder = new \ForumRewrite\Llm\LlmExchangeRecorder($pdo);
            $ids = [];
            foreach ([[$author['thread_id'], [$author['thread_id']]], ['root-001', ['root-001', $author['thread_id']]], ['root-001', ['root-001']]] as $index => [$primary, $dependencies]) {
                $ids[] = $recorder->record(['call_type' => 'post_analysis', 'post_id' => $primary,
                    'exchange_dependencies' => \ForumRewrite\Llm\ExchangeDependencies::manifest($dependencies)],
                    ['payload' => ['messages' => [['role' => 'user', 'content' => $index === 2 ? 'Unrelated prompt' : 'Exchange private prose']]]],
                    ['body' => 'Exchange response prose'], 'completed', 1, ['message' => 'Exchange error prose']);
            }
            $_COOKIE = ['identity_hint' => 'guest'];
            $withoutRequestId = $this->renderMethod($app, 'GET', '/tools/llm-exchanges/' . $ids[0]);
            assertStringContains('Exchange private prose', $withoutRequestId);
            assertStringNotContains('Request ID:', $withoutRequestId);
            $pdo->exec("UPDATE llm_exchanges SET provider_request_id = 'req-visible-123' WHERE id = " . (int) $ids[2]);
            assertStringContains('Request ID: req-visible-123', $this->renderMethod($app, 'GET', '/tools/llm-exchanges/' . $ids[2]));
            $rawRows = $pdo->query('SELECT * FROM llm_exchanges ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $prepared = $this->prepareRetractionRequest($app, $author['identity_id'], $author['thread_id']);
            $_POST = ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'], 'record_path' => $prepared['record_path'],
                'author_identity_id' => $author['identity_id'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record'])];
            $this->renderMethod($app, 'POST', '/api/create_prepared_retraction');
            $_POST = [];
            assertStringContains('Exchange private prose', $this->renderMethod($app, 'GET', '/tools/llm-exchanges/' . $ids[0]));
            (new \ForumRewrite\Retraction\RetractionSweep($root, $exchangeDb, $root . '/unused-sweep-state.json'))->run();
            foreach (array_slice($ids, 0, 2) as $id) {
                $page = $this->renderMethod($app, 'GET', '/tools/llm-exchanges/' . $id);
                assertStringContains('LLM exchange unavailable.', $page);
                assertSame(false, str_contains($page, 'Exchange private prose'));
                assertSame(false, str_contains($page, 'Exchange response prose'));
                assertSame(false, str_contains($page, 'Exchange error prose'));
            }
            assertStringContains('Unrelated prompt', $this->renderMethod($app, 'GET', '/tools/llm-exchanges/' . $ids[2]));
            $list = $this->renderMethod($app, 'GET', '/tools/llm-exchanges/');
            assertSame(false, str_contains($list, '/tools/llm-exchanges/' . $ids[0] . '/'));
            assertSame(false, str_contains($list, '/tools/llm-exchanges/' . $ids[1] . '/'));
            assertStringContains('/tools/llm-exchanges/' . $ids[2] . '/', $list);
            $post = $this->renderMethod($app, 'GET', '/posts/' . $author['thread_id']);
            assertSame(false, str_contains($post, 'LLM exchanges:'));
            $rootPost = $this->renderMethod($app, 'GET', '/posts/root-001');
            assertSame(false, str_contains($rootPost, '/tools/llm-exchanges/' . $ids[1] . '"'));
            assertStringContains('/tools/llm-exchanges/' . $ids[2] . '"', $rootPost);

            assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn());
            assertSame($rawRows[2], $pdo->query('SELECT * FROM llm_exchanges WHERE id=' . $ids[2])->fetch(PDO::FETCH_ASSOC));
            assertTrue(is_file($root . '/' . \ForumRewrite\Canonical\AcceptedRetraction::path($author['thread_id'])));
        } finally {
            $_POST = []; $_COOKIE = $cookies;
            putenv($previous === false ? 'FORUM_SECRETS_PATH' : 'FORUM_SECRETS_PATH=' . $previous);
            unlink($config);
        }
    }

    public function testRetractionDeletesCanonicalFilesAndRetriesFromRetainedRecord(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $author = $this->createAuthoredThread($app, 'delete-now', "Delete this prose\n#pinned");
        \ForumRewrite\Write\SourceLabelRemoval::run($root, $db, $author['thread_id'], true, [], $artifacts);
        $local = \ForumRewrite\Canonical\LabelRemoval::read($root, $author['thread_id']);
        assertSame('resolve', $local['operation']);
        assertTrue(count($local['labels']) > 0);
        assertSame(true, is_file($root . '/' . $author['record_path']));
        $prepared = $this->prepareRetractionRequest($app, $author['identity_id'], $author['thread_id']);
        $fields = ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'], 'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record']];
        $_POST = $fields + ['detached_signature' => 'invalid signature'];
        $bad = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true);
        assertSame('error', $bad['status']);
        assertSame(true, is_file($root . '/' . $author['record_path']));
        $_POST = $fields + ['detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record'])];
        try { $result = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true); }
        finally { $_POST = []; }
        assertSame('ok', $result['status']);
        assertSame(false, is_file($root . '/' . $author['record_path']));
        assertSame(false, is_file($root . '/' . $author['record_path'] . '.asc'));
        $record = \ForumRewrite\Canonical\AcceptedRetraction::read($root, $author['thread_id']);
        assertSame($prepared['canonical_record'], $record['signed_request']);
        $combined = \ForumRewrite\Canonical\AcceptedRemoval::read($root, $author['thread_id']);
        assertSame('delete', $combined['operation']);
        assertSame($local['labels'], $combined['labels']);
        assertSame($prepared['record_path'], $result['record_path']);
        assertSame($result['record_path'] . '.asc', $result['signature_path']);
        assertSame($prepared['canonical_record'], file_get_contents($root . '/' . $result['record_path']));
        assertSame($record['signature'], file_get_contents($root . '/' . $result['signature_path']));
        assertStringContains('Request-SHA256: ' . hash('sha256', $prepared['canonical_record']), file_get_contents($root . '/' . $result['acceptance_path']));

        foreach (['record_path', 'signature_path', 'acceptance_path'] as $field) {
            $bytes = file_get_contents($root . '/' . $result[$field]);
            assertSame($bytes, $this->renderMethod($app, 'GET', '/source/current/' . $result[$field]));
            assertSame($bytes, $this->renderMethod($app, 'GET', '/source/blob/' . $result['commit_sha'] . '/' . $result[$field]));
        }
        $export = (new \ForumRewrite\Images\ImageBackup($root, $db))->export('zip');
        $zip = new ZipArchive(); assertSame(true, $zip->open($export['path']));
        try {
            foreach (['record_path', 'signature_path', 'acceptance_path'] as $field) {
                assertSame(file_get_contents($root . '/' . $result[$field]), $zip->getFromName('repository/' . $result[$field]));
            }
            assertSame(false, $zip->getFromName('repository/' . $author['record_path']));
        } finally { $zip->close(); unlink($export['path']); }
        $repeat = $this->prepareRetractionRequest($app, $author['identity_id'], $author['thread_id']);
        assertSame(true, $repeat['already_retracted']);
        assertSame($result['commit_sha'], $repeat['commit_sha']);
        $_POST = $fields + ['detached_signature' => $record['signature']];
        try { $replay = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true); }
        finally { $_POST = []; }
        assertSame(true, $replay['already_retracted']);
        assertSame($result['commit_sha'], $replay['commit_sha']);
        $_POST = $fields + ['detached_signature' => 'forged replay'];
        try { $rejectedReplay = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true); }
        finally { $_POST = []; }
        assertSame('error', $rejectedReplay['status']);

        $pdo = new PDO('sqlite:' . $db);
        $pdo->exec("UPDATE metadata SET value='25' WHERE key='schema_version'");
        $pdo->exec("UPDATE posts SET body='stale prose' WHERE post_id='" . $author['thread_id'] . "'");
        $fresh = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $page = $this->renderMethod($fresh, 'GET', '/threads/' . $author['thread_id']);
        assertStringNotContains('stale prose', $page);
        assertSame(\ForumRewrite\ReadModel\ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
        $this->runCommand(dirname(__DIR__), './v3 rebuild ' . escapeshellarg($root) . ' ' . escapeshellarg($db));
        assertSame('', $pdo->query("SELECT body FROM posts WHERE post_id='" . $author['thread_id'] . "'")->fetchColumn());

    }

    public function testAuthorRetractionSuppressesThreadAndRejectsNonAuthorAndBadSignature(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $author = $this->createAuthoredThread($application, 'retract-author', "Retraction marker body\n#pinned");
        $other = $this->linkSigningIdentity($application, 'retract-other');

        $_POST = ['author_identity_id' => $other['identity_id'], 'post_id' => $author['thread_id']];
        $rejected = json_decode($this->renderMethod($application, 'POST', '/api/prepare_retraction'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        assertSame('error', $rejected['status']);
        assertStringContains('Only the post author can retract this post.', $rejected['error']);
        assertSame(false, is_dir($repositoryRoot . '/records/post-retractions'));

        $prepared = $this->prepareRetractionRequest($application, $author['identity_id'], $author['thread_id']);
        assertSame('ok', $prepared['status']);
        assertSame(false, $prepared['already_retracted']);
        $postFields = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
        ];
        $_POST = $postFields + ['detached_signature' => 'invalid detached signature'];
        $invalid = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_retraction'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        assertSame('error', $invalid['status']);
        assertStringContains('Detached signature verification failed:', $invalid['error']);
        assertSame(false, is_file($repositoryRoot . '/' . $prepared['record_path']));

        $_POST = $postFields + ['detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record'])];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_retraction'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];

        assertSame('ok', $finalized['status']);
        assertSame('complete', $finalized['retraction_status']);
        assertTrue(is_file($repositoryRoot . '/' . \ForumRewrite\Canonical\AcceptedRetraction::path($author['thread_id'])));
        assertSame(false, is_file($repositoryRoot . '/' . $author['record_path']));
        assertSame('Retract post ' . $author['thread_id'] . ' by ' . $author['identity_id'], $this->gitOutput($repositoryRoot, 'log -1 --format=%s'));
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($author['thread_id']));
        assertSame(false, str_contains($threadPage, 'Retraction marker body'));
        $pdo = new PDO('sqlite:' . $databasePath);
        assertSame('', $pdo->query("SELECT body FROM posts WHERE post_id = '" . $author['thread_id'] . "'")->fetchColumn());
        assertSame('[]', $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id = '" . $author['thread_id'] . "'")->fetchColumn());

        $repeat = $this->prepareRetractionRequest($application, $author['identity_id'], $author['thread_id']);
        assertSame(true, $repeat['already_retracted']);
        assertSame('complete', $repeat['retraction_status']);
    }

    public function testPhysicallyDeletedRootStillAcceptsRepliesAndPreservesPrivacyState(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $author = $this->createAuthoredThread($app, 'physical-root-reply', "Removed prose\n#pinned");
        $prepared = $this->prepareRetractionRequest($app, $author['identity_id'], $author['thread_id']);
        $_POST = ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'], 'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'], 'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record'])];
        try { $result = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true, 512, JSON_THROW_ON_ERROR); }
        finally { $_POST = []; }
        assertSame('ok', $result['status']);
        \ForumRewrite\Write\SourceLabelRemoval::run($root, $database, $author['thread_id'], false, [], $artifacts);
        assertSame(true, \ForumRewrite\Llm\ExchangeRetractionState::read($root)[$author['thread_id']] ?? false);
        $response = $this->renderMethod($app, 'POST', '/api/create_reply?thread_id=' . $author['thread_id'] . '&parent_id=' . $author['thread_id'] . '&body=Preserved%20conversation');
        assertStringContains('status=ok', $response);
        $page = $this->renderMethod($app, 'GET', '/threads/' . $author['thread_id']);
        assertStringContains('Preserved conversation', $page);
        assertStringNotContains('Removed prose', $page);
    }

    public function testLegacyLabelRetractionReportsOperatorReviewWithoutGuessingOwnership(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $author = $this->createAuthoredThread($app, 'legacy-label-retract', "Old source\n#pinned");
        foreach (glob($root . '/records/thread-labels/*.txt') as $path) {
            $bytes = file_get_contents($path);
            if (!str_contains($bytes, 'Target-ID: ' . $author['thread_id'])) continue;
            // Rewrite as a legacy label without source provenance.
            $bytes = preg_replace('/^Source-Post-(?:ID|SHA256):.*\n/m', '', $bytes);
            $bytes = str_replace(["Target-Kind: thread\n", 'Target-ID: ', 'Tags: '], ['', 'Thread-ID: ', 'Labels: '], $bytes);
            file_put_contents($path, $bytes);
        }
        $this->gitOutput($root, 'add records'); $this->gitOutput($root, 'commit -m legacy-label-fixture');
        $prepared = $this->prepareRetractionRequest($app, $author['identity_id'], $author['thread_id']);
        $_POST = ['prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'], 'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'], 'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record'])];
        try { $result = json_decode($this->renderMethod($app, 'POST', '/api/create_prepared_retraction'), true, 512, JSON_THROW_ON_ERROR); }
        finally { $_POST = []; }
        assertSame('complete', $result['label_cleanup_status']);
        assertSame([], $result['unresolved_label_records']);
        $pdo = new PDO('sqlite:' . $database);
        assertSame('["pinned"]', $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id='" . $author['thread_id'] . "'")->fetchColumn());
    }

    public function testRetractedPostProseIsAbsentFromEveryServedSurface(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $author = $this->createAuthoredThread($application, 'retract-surfaces', 'zzprosemarker unique body words');
        $createCommit = $this->gitOutput($repositoryRoot, 'log --format=%H -n 1 -- ' . escapeshellarg($author['record_path']));
        $routes = [
            '/', '/threads/', '/threads/' . $author['thread_id'], '/posts/' . $author['thread_id'],
            '/api/get_post?post_id=' . $author['thread_id'], '/api/get_thread?thread_id=' . $author['thread_id'],
            '/api/list_index', '/activity/', '/activity/?format=rss', '/latest', '/top', '/tags/', '/users/',
        ];
        $before = $this->renderMethod($application, 'GET', '/api/get_post?post_id=' . $author['thread_id']);
        assertStringContains('zzprosemarker', $before);
        assertStringContains('zzprosemarker', $this->readModelFileBytes($databasePath));

        mkdir($artifactRoot . '/tags', 0777, true);
        mkdir($artifactRoot . '/offline', 0777, true);
        file_put_contents($artifactRoot . '/tags/general.html', 'zzprosemarker');
        file_put_contents($artifactRoot . '/threads.html', 'zzprosemarker');
        file_put_contents($artifactRoot . '/offline/snapshot.sqlite3', 'zzprosemarker');

        $prepared = $this->prepareRetractionRequest($application, $author['identity_id'], $author['thread_id']);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
        ];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_retraction'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        assertSame('complete', $finalized['retraction_status']);

        foreach ($routes as $route) {
            $output = $this->renderMethod($application, 'GET', $route);
            assertSame(false, str_contains($output, 'zzprosemarker'), 'Retracted prose served at ' . $route);
        }
        $search = $this->renderMethod($application, 'GET', '/search?search=' . rawurlencode('unique body'));
        assertSame(false, str_contains($search, $author['thread_id']), 'Retracted post listed in search');

        $current = $this->renderMethod($application, 'GET', '/source/current/' . $author['record_path']);
        assertSame(false, str_contains($current, 'zzprosemarker'));
        assertStringContains('retracted by its author', $current);
        $blob = $this->renderMethod($application, 'GET', '/source/blob/' . $createCommit . '/' . $author['record_path']);
        assertSame(false, str_contains($blob, 'zzprosemarker'));
        assertStringContains('retracted by its author', $blob);

        foreach (['/tags/general.html', '/threads.html', '/offline/snapshot.sqlite3'] as $artifact) {
            assertSame(false, is_file($artifactRoot . $artifact), 'Artifact not retired: ' . $artifact);
        }
        assertSame(false, str_contains($this->readModelFileBytes($databasePath), 'zzprosemarker'), 'Prose left in the read-model file');
    }

    public function testRetractedRootShowsPlaceholderOnPermalinkKeepsRepliesAndLeavesBoard(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $author = $this->createAuthoredThread($application, 'retract-render', 'zzrenderprose body words');
        $reply = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=' . rawurlencode($author['thread_id'])
            . '&parent_id=' . rawurlencode($author['thread_id']) . '&body=' . rawurlencode('Reply that stays visible.')
        );
        $replyId = $this->extractValue($reply, 'post_id');

        $prepared = $this->prepareRetractionRequest($application, $author['identity_id'], $author['thread_id']);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
        ];
        $this->renderMethod($application, 'POST', '/api/create_prepared_retraction');
        $_POST = [];

        $notice = 'This post was retracted by its author.';
        $thread = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($author['thread_id']));
        assertStringContains($notice, $thread);
        assertStringContains('Reply that stays visible.', $thread);
        assertStringContains('Deleted post', $thread);
        assertSame(false, str_contains($thread, 'zzrenderprose'));
        assertSame(false, str_contains($thread, 'data-action="apply-thread-tag"'), 'Root reaction actions remain on a retracted post');
        assertStringContains('data-action="apply-post-tag"', $thread);

        $board = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=newest');
        assertSame(false, str_contains($board, 'Deleted post'), 'Retracted thread remains in the board listing');
        assertSame(false, str_contains($board, rawurlencode($author['thread_id'])));
        assertSame(false, str_contains($board, 'Subject retract-render'));

        $post = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($author['thread_id']));
        assertStringContains($notice, $post);

        $forte = $this->renderMethod($application, 'GET', '/forte?selected=' . rawurlencode($author['thread_id']));
        assertSame(false, str_contains($forte, 'zzrenderprose'));
        assertStringContains($notice, $forte);
        assertTrue($replyId !== '');
    }

    public function testRetractControlAppearsOnlyForTheAuthorOfAnUnretractedPost(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $author = $this->createAuthoredThread($application, 'retract-control', 'Control body');
        $other = $this->linkSigningIdentity($application, 'retract-control-other');
        $threadPath = '/threads/' . rawurlencode($author['thread_id']);

        try {
            $_COOKIE = [];
            $anonymous = $this->renderMethod($application, 'GET', $threadPath);
            assertSame(false, str_contains($anonymous, 'data-action="retract-post"'));

            $_COOKIE = ['identity_hint' => $other['identity_id']];
            $notAuthor = $this->renderMethod($application, 'GET', $threadPath);
            assertSame(false, str_contains($notAuthor, 'data-action="retract-post"'));

            $_COOKIE = ['identity_hint' => $author['identity_id']];
            $asAuthor = $this->renderMethod($application, 'GET', $threadPath);
            assertStringContains('data-action="retract-post"', $asAuthor);
            assertStringContains('data-confirm="Retract this post?"', $asAuthor);
            assertStringContains('post_retraction.', $asAuthor);
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($author['thread_id']));
            assertStringContains('data-action="retract-post"', $postPage);

            $prepared = $this->prepareRetractionRequest($application, $author['identity_id'], $author['thread_id']);
            $_POST = [
                'prepare_token' => $prepared['prepare_token'],
                'post_id' => $prepared['post_id'],
                'record_path' => $prepared['record_path'],
                'author_identity_id' => $author['identity_id'],
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
            ];
            $this->renderMethod($application, 'POST', '/api/create_prepared_retraction');
            $_POST = [];

            $afterRetraction = $this->renderMethod($application, 'GET', $threadPath);
            assertSame(false, str_contains($afterRetraction, 'data-action="retract-post"'));
        } finally {
            $_COOKIE = [];
        }
    }

    public function testRetractionEndToEndKeepsRepliesRejectsOthersAndSurvivesReimportOfTheOriginalArchive(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $author = $this->createAuthoredThread($application, 'retract-e2e', 'zze2eprose root body');
        $stranger = $this->linkSigningIdentity($application, 'retract-e2e-stranger');
        $authorReply = $this->createAuthoredReply($application, $author, $author['thread_id'], 'zze2eprose reply body');
        $guestReply = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=' . rawurlencode($author['thread_id'])
            . '&parent_id=' . rawurlencode($author['thread_id']) . '&body=' . rawurlencode('Guest reply that stays.')
        );
        assertStringContains('post_id=', $guestReply);

        $archive = sys_get_temp_dir() . '/forum-rewrite-retraction-archive-' . bin2hex(random_bytes(6));
        $this->copyDirectory($repositoryRoot . '/records', $archive . '/records');

        // A different identity cannot retract, and the failed request changes nothing.
        $headBefore = $this->gitOutput($repositoryRoot, 'rev-parse HEAD');
        $rejected = $this->prepareRetractionRequest($application, $stranger['identity_id'], $author['thread_id']);
        assertSame('error', $rejected['status']);
        assertSame($headBefore, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
        assertSame(false, is_dir($repositoryRoot . '/records/post-retractions'));

        // Authority posts are never retractable, even by their author.
        file_put_contents(
            $repositoryRoot . '/records/posts/approval-authority.txt',
            "Post-ID: approval-authority\nCreated-At: 2026-04-12T12:00:00Z\nBoard-Tags: identity approval\nThread-ID: "
            . $author['thread_id'] . "\nParent-ID: " . $author['thread_id'] . "\nAuthor-Identity-ID: " . $author['identity_id']
            . "\n\nApprove-Identity-ID: " . $stranger['identity_id'] . "\n"
        );
        $this->runCommand($repositoryRoot, 'git add records/posts/approval-authority.txt && git commit -q -m "Add authority fixture"');
        $authority = $this->prepareRetractionRequest($application, $author['identity_id'], 'approval-authority');
        assertSame('error', $authority['status']);
        assertStringContains('cannot be retracted', $authority['error']);

        // The author retracts their reply and then their root; both finish complete.
        foreach ([$authorReply, $author['thread_id']] as $postId) {
            $prepared = $this->prepareRetractionRequest($application, $author['identity_id'], $postId);
            $_POST = [
                'prepare_token' => $prepared['prepare_token'],
                'post_id' => $prepared['post_id'],
                'record_path' => $prepared['record_path'],
                'author_identity_id' => $author['identity_id'],
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
            ];
            $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_retraction'), true, 512, JSON_THROW_ON_ERROR);
            $_POST = [];
            assertSame('complete', $finalized['retraction_status']);
        }

        $assertSuppressed = function () use ($application, $databasePath, $author, $authorReply): void {
            $thread = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($author['thread_id']));
            assertSame(false, str_contains($thread, 'zze2eprose'));
            assertStringContains('Guest reply that stays.', $thread);
            assertStringContains('This post was retracted by its author.', $thread);
            foreach (['/', '/activity/', '/latest', '/top', '/users/', '/api/list_index', '/api/get_post?post_id=' . $authorReply, '/profiles/' . rawurlencode($author['profile_slug']), '/api/get_profile?profile_slug=' . rawurlencode($author['profile_slug'])] as $route) {
                assertSame(false, str_contains($this->renderMethod($application, 'GET', $route), 'zze2eprose'), 'Prose at ' . $route);
            }
            $pdo = new PDO('sqlite:' . $databasePath);
            foreach ([$author['thread_id'], $authorReply] as $postId) {
                $retractedAt = $pdo->query("SELECT retracted_at FROM posts WHERE post_id = '" . $postId . "'")->fetchColumn();
                assertTrue(is_string($retractedAt) && $retractedAt !== '', 'Post row missing or not marked retracted: ' . $postId);
            }
            assertSame(false, str_contains($this->readModelFileBytes($databasePath), 'zze2eprose'));
        };
        $assertSuppressed();

        // Re-importing the original archive over the live repository leaves both posts suppressed.
        $runner = new \ForumRewrite\Import\ContentImportRunner($repositoryRoot, $databasePath);
        $runner->run($archive, function () use ($repositoryRoot, $databasePath): void {
            (new \ForumRewrite\ReadModel\ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot), 'reimport_test'))->rebuild();
        });
        $assertSuppressed();

        // Repeated archive imports cannot restore accepted deletions.
        clearstatcache(true);
        foreach ([$authorReply, $author['thread_id']] as $postId) {
            foreach (CanonicalPathResolver::postCandidates($postId) as $candidate) {
                assertSame(false, is_file($repositoryRoot . '/' . $candidate));
            }
        }
        $restored = $runner->run($archive, function () use ($repositoryRoot, $databasePath): void {
            (new \ForumRewrite\ReadModel\ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot), 'restore_test'))->rebuild();
        });
        assertSame('complete', $restored['status']);
        clearstatcache(true);
        foreach ([$authorReply, $author['thread_id']] as $postId) {
            $present = false;
            foreach (CanonicalPathResolver::postCandidates($postId) as $candidate) {
                $present = $present || is_file($repositoryRoot . '/' . $candidate);
            }
            assertSame(false, $present, 'Archive resurrected a retracted post: ' . $postId);
        }
        $assertSuppressed();

        $this->deleteTree($archive);
    }

    public function testRetractionCleanupEnqueueFailurePreservesAcceptanceAndRetriesWithoutSigning(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $author = $this->createAuthoredThread($app, 'cleanup-retraction', 'Cleanup recovery prose');
        $fail = true;
        $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root),
            enqueueRetractionSweep: static function () use (&$fail): void { if ($fail) throw new RuntimeException('Queue offline'); });
        $input = ['author_identity_id' => $author['identity_id'], 'post_id' => $author['thread_id']];
        $prepared = $writer->prepareRetraction($input);
        $result = $writer->finalizePreparedRetraction($input + [
            'prepare_token' => $prepared['prepare_token'], 'record_path' => $prepared['record_path'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
        ]);
        assertSame('complete', $result['retraction_status']);
        assertSame('failed', $result['cleanup_status']);
        $head = $this->gitOutput($root, 'rev-parse HEAD');
        $fail = false;
        $retry = $writer->prepareRetraction($input);
        assertSame(true, $retry['already_retracted']);
        assertSame('pending', $retry['cleanup_status']);
        assertSame($head, $this->gitOutput($root, 'rev-parse HEAD'));
        $page = $this->renderMethod($app, 'GET', '/posts/' . $author['thread_id']);
        assertStringNotContains('Cleanup recovery prose', $page);
    }

    public function testRetractionArtifactFailureRetriesWithoutAnotherCommit(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
        $author = $this->createAuthoredThread($app, 'artifact-retraction', 'Artifact recovery prose');
        $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
        $prepared = $writer->prepareRetraction(['author_identity_id' => $author['identity_id'], 'post_id' => $author['thread_id']]);
        symlink($root, $artifacts . '/threads');
        file_put_contents($artifacts . '/threads.html', 'Artifact recovery prose');
        $result = $writer->finalizePreparedRetraction([
            'prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'], 'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
        ]);
        assertSame('partial', $result['retraction_status']);
        assertSame('artifact_invalidate', $result['failed_step']);
        $head = $this->gitOutput($root, 'rev-parse HEAD');
        $retry = ['author_identity_id' => $author['identity_id'], 'post_id' => $author['thread_id']];
        assertSame('partial', $writer->prepareRetraction($retry)['retraction_status']);
        unlink($artifacts . '/threads');
        assertSame('complete', $writer->prepareRetraction($retry)['retraction_status']);
        assertSame(false, is_file($artifacts . '/threads.html'));
        assertSame($head, $this->gitOutput($root, 'rev-parse HEAD'));
    }

    public function testRetractionRefreshFailureReportsPartialAndRetryRecovers(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $author = $this->createAuthoredThread($application, 'retract-partial', 'Partial marker body');

        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            public bool $failRefresh = true;

            protected function rebuildReadModel(): array
            {
                if ($this->failRefresh) {
                    throw new RuntimeException('simulated refresh failure');
                }

                return parent::rebuildReadModel();
            }
        };

        $prepared = $service->prepareRetraction(['author_identity_id' => $author['identity_id'], 'post_id' => $author['thread_id']]);
        $partial = $service->finalizePreparedRetraction([
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
        ]);

        assertSame('partial', $partial['retraction_status']);
        assertSame('read_model_refresh', $partial['failed_step']);
        assertTrue(is_file($repositoryRoot . '/' . \ForumRewrite\Canonical\AcceptedRetraction::path($author['thread_id'])));
        assertTrue((new \ForumRewrite\ReadModel\ReadModelStaleMarker($databasePath))->exists());

        $service->failRefresh = false;
        $retried = $service->prepareRetraction(['author_identity_id' => $author['identity_id'], 'post_id' => $author['thread_id']]);
        assertSame(true, $retried['already_retracted']);
        assertSame('complete', $retried['retraction_status']);
        assertSame(false, (new \ForumRewrite\ReadModel\ReadModelStaleMarker($databasePath))->exists());
    }

    /**
     * @param array{identity_id:string,home:string} $author
     */
    private function createAuthoredReply(Application $application, array $author, string $parentId, string $body): string
    {
        $prepared = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_reply?thread_id=' . rawurlencode($parentId) . '&parent_id=' . rawurlencode($parentId)
            . '&body=' . rawurlencode($body) . '&author_identity_id=' . rawurlencode($author['identity_id'])
        ), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $author['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($author['home'], $prepared['canonical_record']),
        ];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_post'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        assertSame('ok', $finalized['status']);

        return (string) $prepared['post_id'];
    }

    /** Raw bytes of the read-model database and any SQLite sidecar, as a download would expose. */
    private function readModelFileBytes(string $databasePath): string
    {
        $bytes = '';
        foreach ([$databasePath, $databasePath . '-wal', $databasePath . '-journal'] as $path) {
            if (is_file($path)) {
                $bytes .= (string) file_get_contents($path);
            }
        }

        return $bytes;
    }

    /**
     * @return array{identity_id:string,profile_slug:string,home:string,public_key:string}
     */
    private function linkSigningIdentity(Application $application, string $username): array
    {
        $key = $this->createSigningKey($username);
        $_POST = ['public_key' => $key['public_key']];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];

        return $key + [
            'identity_id' => $this->extractValue($response, 'identity_id'),
            'profile_slug' => $this->extractValue($response, 'profile_slug'),
        ];
    }

    /**
     * @return array{identity_id:string,home:string,public_key:string,thread_id:string,record_path:string}
     */
    private function createAuthoredThread(Application $application, string $username, string $body): array
    {
        $key = $this->linkSigningIdentity($application, $username);
        $prepared = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_thread?board_tags=general&subject=' . rawurlencode('Subject ' . $username)
            . '&body=' . rawurlencode($body) . '&author_identity_id=' . rawurlencode($key['identity_id'])
        ), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [
            'prepare_token' => $prepared['prepare_token'],
            'post_id' => $prepared['post_id'],
            'record_path' => $prepared['record_path'],
            'author_identity_id' => $key['identity_id'],
            'canonical_record' => $prepared['canonical_record'],
            'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record']),
        ];
        $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_post'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];
        assertSame('ok', $finalized['status']);

        return $key + ['thread_id' => (string) $prepared['thread_id'], 'record_path' => (string) $prepared['record_path']];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareRetractionRequest(Application $application, string $identityId, string $postId): array
    {
        $_POST = ['author_identity_id' => $identityId, 'post_id' => $postId];
        $response = json_decode($this->renderMethod($application, 'POST', '/api/prepare_retraction'), true, 512, JSON_THROW_ON_ERROR);
        $_POST = [];

        return $response;
    }

    public function testQdbQuoteApiAssignsSequentialQuoteNumbersAcrossDigitBoundary(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

            for ($expectedNumber = 1; $expectedNumber <= 10; $expectedNumber++) {
                $response = $this->renderMethod(
                    $application,
                    'POST',
                    '/api/create_quote?board_tags=general&subject=&body=' . rawurlencode('Quote ' . $expectedNumber)
                );
                $threadId = $this->extractValue($response, 'thread_id');

                assertTrue(
                    preg_match('/^thread-\d{14}-qdb-(\d+)$/', $threadId, $matches) === 1,
                    'Unexpected qdb thread ID shape: ' . $threadId
                );
                assertSame((string) $expectedNumber, $matches[1]);
            }
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testQdbQuoteApiStartsQuoteNumberingAtOneWithNoExistingQuotes(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

            $response = $this->renderMethod(
                $application,
                'POST',
                '/api/create_quote?board_tags=general&subject=&body=First%20quote'
            );
            $threadId = $this->extractValue($response, 'thread_id');

            assertTrue(str_ends_with($threadId, '-qdb-1'));
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testQdbPrepareQuoteContinuesSameQuoteNumberSequenceAsCreateQuote(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $identityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

            $createResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_quote?board_tags=general&subject=&body=First%20quote'
            );
            $createdThreadId = $this->extractValue($createResponse, 'thread_id');

            $prepareResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/prepare_quote?board_tags=general&subject=&body=Second%20quote&author_identity_id=' . rawurlencode($identityId)
            );
            $prepared = json_decode($prepareResponse, true, 512, JSON_THROW_ON_ERROR);

            assertTrue(str_ends_with($createdThreadId, '-qdb-1'));
            assertTrue(str_ends_with((string) $prepared['thread_id'], '-qdb-2'));
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testNonQdbSiteProfileThreadIdsAreUnaffectedByQuoteNumbering(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Not%20a%20quote&body=Thread%20body'
        );
        $threadId = $this->extractValue($response, 'thread_id');

        assertTrue(preg_match('/^thread-\d{14}-[0-9a-f]{8}$/', $threadId) === 1, 'Unexpected thread ID shape: ' . $threadId);
        assertStringNotContains('-qdb-', $threadId);
    }

    public function testQdbGenericCreateAndPrepareThreadUseRegularIds(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $identityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

            $createResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=Regular&body=Regular%20thread'
            );
            $threadId = $this->extractValue($createResponse, 'thread_id');

            $prepareResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/prepare_thread?board_tags=general&subject=Prepared&body=Regular%20thread&author_identity_id=' . rawurlencode($identityId)
            );
            $prepared = json_decode($prepareResponse, true, 512, JSON_THROW_ON_ERROR);

            assertTrue(preg_match('/^thread-\d{14}-[0-9a-f]{8}$/', $threadId) === 1, 'Unexpected QDB regular thread ID: ' . $threadId);
            assertStringNotContains('-qdb-', $threadId);
            assertTrue(preg_match('/^thread-\d{14}-[0-9a-f]{8}$/', (string) $prepared['thread_id']) === 1);
            assertStringNotContains('-qdb-', (string) $prepared['thread_id']);
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testQdbAddAndComposeThreadFormsSelectDifferentIdTypes(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

            $quoteResponse = $this->renderMethod($application, 'POST', '/add?body=Form%20quote');
            $threadResponse = $this->renderMethod($application, 'POST', '/compose/thread?subject=Regular&body=Form%20thread');

            assertTrue(preg_match('/Created thread (thread-\d{14}-qdb-1)\./', $quoteResponse) === 1);
            assertTrue(preg_match('/Created thread (thread-\d{14}-[0-9a-f]{8})\./', $threadResponse, $threadMatches) === 1);
            assertStringNotContains('-qdb-', $threadMatches[1]);
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testQuoteApiRejectsNonQdbSiteProfiles(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_quote?board_tags=general&subject=&body=Not%20a%20quote'
        );

        assertStringContains('QDB quote authoring is available only on the QDB site.', $response);
    }

    public function testPrepareReplyReturnsCanonicalRecordWithoutCommittingPost(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/prepare_reply?thread_id=root-001&parent_id=root-001&board_tags=general&body=Prepared%20reply&author_identity_id=' . rawurlencode($identityId)
        );
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('ok', $payload['status']);
        assertStringContains('reply-', (string) $payload['post_id']);
        assertSame('root-001', $payload['thread_id']);
        assertTrue(preg_match('#^records/posts/\d{4}/\d{2}/\d{2}/' . preg_quote((string) $payload['post_id'], '#') . '\.txt$#', (string) $payload['record_path']) === 1);
        assertStringContains("Thread-ID: root-001\nParent-ID: root-001\n", $payload['canonical_record']);
        assertStringContains("Author-Identity-ID: {$identityId}\n\nPrepared reply\n", $payload['canonical_record']);
        assertTrue(is_file(dirname($databasePath) . '/prepared-posts/' . $payload['prepare_token'] . '.json'));
        assertFalse(is_file($repositoryRoot . '/' . $payload['record_path']));
    }

    public function testPrepareThreadRejectsAnonymousPost(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/prepare_thread?board_tags=general&subject=Prepared%20Thread&body=Prepared%20body'
        );
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('error', $payload['status']);
        assertSame('author_identity_id is required for signed prepare.', $payload['error']);
    }

    public function testCreatePreparedPostVerifiesDetachedSignatureAndCommitsSignatureFile(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->installOpenPgpSignatureFixtureIdentity($repositoryRoot);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $canonicalRecord = $this->readOpenPgpSignatureFixture('signed-post.txt');
        $signature = $this->readOpenPgpSignatureFixture('signed-post.txt.asc');
        $token = str_repeat('a', 32);
        $this->storePreparedPostFixture($databasePath, $token, $canonicalRecord, [
            'kind' => 'thread',
            'post_id' => 'signed-test',
            'thread_id' => 'signed-test',
            'parent_id' => null,
            'author_identity_id' => 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919',
            'created_at' => '2026-07-19T20:00:00Z',
        ]);

        $_POST = [
            'prepare_token' => $token,
            'post_id' => 'signed-test',
            'record_path' => 'records/posts/2026/07/19/signed-test.txt',
            'author_identity_id' => 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919',
            'canonical_record' => $canonicalRecord,
            'detached_signature' => $signature,
        ];
        $response = $this->renderMethod($application, 'POST', '/api/create_prepared_post');
        $_POST = [];
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('ok', $payload['status']);
        assertSame('signed-test', $payload['post_id']);
        assertSame('records/posts/2026/07/19/signed-test.txt', $payload['record_path']);
        assertSame('records/posts/2026/07/19/signed-test.txt.asc', $payload['signature_path']);
        assertTrue(strlen((string) $payload['commit_sha']) === 40);
        assertSame($canonicalRecord, (string) file_get_contents($repositoryRoot . '/records/posts/2026/07/19/signed-test.txt'));
        assertSame($signature, (string) file_get_contents($repositoryRoot . '/records/posts/2026/07/19/signed-test.txt.asc'));
        assertFalse(is_file(dirname($databasePath) . '/prepared-posts/' . $token . '.json'));

        $committedFiles = $this->gitOutput($repositoryRoot, 'show --name-only --format= ' . escapeshellarg((string) $payload['commit_sha']));
        assertStringContains('records/posts/2026/07/19/signed-test.txt', $committedFiles);
        assertStringContains('records/posts/2026/07/19/signed-test.txt.asc', $committedFiles);

        $postPage = $this->renderMethod($application, 'GET', '/posts/signed-test');
        assertStringContains('Source:', $postPage);
        assertStringContains('Signature:', $postPage);
        assertStringContains('/source/current/records/posts/2026/07/19/signed-test.txt.asc', $postPage);
    }

    public function testCreatePreparedPostRejectsInvalidDetachedSignatureWithoutWritingFiles(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->installOpenPgpSignatureFixtureIdentity($repositoryRoot);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $canonicalRecord = $this->readOpenPgpSignatureFixture('signed-post.txt');
        $token = str_repeat('b', 32);
        $this->storePreparedPostFixture($databasePath, $token, $canonicalRecord, [
            'kind' => 'thread',
            'post_id' => 'signed-test',
            'thread_id' => 'signed-test',
            'parent_id' => null,
            'author_identity_id' => 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919',
            'created_at' => '2026-07-19T20:00:00Z',
        ]);

        $_POST = [
            'prepare_token' => $token,
            'post_id' => 'signed-test',
            'record_path' => 'records/posts/2026/07/19/signed-test.txt',
            'author_identity_id' => 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919',
            'canonical_record' => $canonicalRecord,
            'detached_signature' => str_replace('kipu', 'fail', $this->readOpenPgpSignatureFixture('signed-post.txt.asc')),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/create_prepared_post');
        $_POST = [];
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('error', $payload['status']);
        assertStringContains('Detached signature verification failed:', $payload['error']);
        assertStringContains('[GNUPG:] NODATA', $payload['error']);
        assertFalse(is_file($repositoryRoot . '/records/posts/2026/07/19/signed-test.txt'));
        assertFalse(is_file($repositoryRoot . '/records/posts/2026/07/19/signed-test.txt.asc'));
        assertTrue(is_file(dirname($databasePath) . '/prepared-posts/' . $token . '.json'));
    }

    public function testCreatePreparedPostRejectsExpiredPrepareToken(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->installOpenPgpSignatureFixtureIdentity($repositoryRoot);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $canonicalRecord = $this->readOpenPgpSignatureFixture('signed-post.txt');
        $token = str_repeat('c', 32);
        $this->storePreparedPostFixture($databasePath, $token, $canonicalRecord, [
            'kind' => 'thread',
            'post_id' => 'signed-test',
            'thread_id' => 'signed-test',
            'parent_id' => null,
            'author_identity_id' => 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919',
            'created_at' => '2026-07-19T20:00:00Z',
            'expires_at' => '2026-01-01T00:00:00Z',
        ]);

        $_POST = [
            'prepare_token' => $token,
            'post_id' => 'signed-test',
            'record_path' => 'records/posts/2026/07/19/signed-test.txt',
            'author_identity_id' => 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919',
            'canonical_record' => $canonicalRecord,
            'detached_signature' => $this->readOpenPgpSignatureFixture('signed-post.txt.asc'),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/create_prepared_post');
        $_POST = [];
        $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        assertSame('error', $payload['status']);
        assertSame('Prepared post has expired.', $payload['error']);
        assertFalse(is_file($repositoryRoot . '/records/posts/2026/07/19/signed-test.txt'));
        assertFalse(is_file($repositoryRoot . '/records/posts/2026/07/19/signed-test.txt.asc'));
    }

    public function testUnicodeAuthoredTextFlagDoesNotWidenMachineFields(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

        try {
            $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
            $threadResult = $service->createThread([
                'board_tags' => 'тест bug',
                'subject' => 'Привет',
                'body' => 'Привет мир',
            ]);
            $record = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $threadResult['thread_id']));

            assertStringContains('Board-Tags: bug', $record);
            assertStringNotContains('тест', $record);

            $rejected = false;
            try {
                $service->createReply([
                    'thread_id' => 'ветка',
                    'parent_id' => $threadResult['thread_id'],
                    'body' => 'Привет',
                ]);
            } catch (RuntimeException $exception) {
                assertStringContains('thread_id is required and must be an ASCII token.', $exception->getMessage());
                $rejected = true;
            }
            if (!$rejected) {
                throw new RuntimeException('Expected Unicode thread_id rejection.');
            }

            $rejected = false;
            try {
                $service->applyThreadTag([
                    'thread_id' => $threadResult['thread_id'],
                    'tag' => 'лайк',
                    'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                ]);
            } catch (RuntimeException $exception) {
                assertStringContains('tag must be one of:', $exception->getMessage());
                $rejected = true;
            }
            if (!$rejected) {
                throw new RuntimeException('Expected Unicode reaction tag rejection.');
            }

            $rejected = false;
            try {
                $service->applyThreadTag([
                    'thread_id' => $threadResult['thread_id'],
                    'tag' => 'like',
                    'author_identity_id' => 'openpgp:кириллица',
                ]);
            } catch (RuntimeException $exception) {
                assertStringContains('ASCII token', $exception->getMessage());
                $rejected = true;
            }
            if (!$rejected) {
                throw new RuntimeException('Expected Unicode identity ID rejection.');
            }
        } finally {
            putenv('FORUM_UNICODE_AUTHORED_TEXT');
        }
    }

    public function testUnicodeAuthoredTextSurvivesWriteRenderRssAndStaticArtifacts(): void
    {
        $previousStaticDetails = getenv('FORUM_STATIC_DETAIL_PAGES_ENABLED');
        putenv('FORUM_STATIC_DETAIL_PAGES_ENABLED=true');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            putenv('FORUM_UNICODE_AUTHORED_TEXT=true');

            try {
                $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
                $response = $this->renderMethod(
                    $application,
                    'POST',
                    '/api/create_thread?board_tags=general&subject=' . rawurlencode('Привет') . '&body=' . rawurlencode('Привет мир')
                );
                $postId = $this->extractValue($response, 'post_id');
                $record = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $postId));
                $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
                $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($postId));
                $activity = $this->renderMethod($application, 'GET', '/activity/');
                $rss = $this->renderMethod($application, 'GET', '/activity/?format=rss');

                $builder = new StaticArtifactBuilder(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
                $builder->build();
                $threadArtifact = (string) file_get_contents($artifactRoot . '/threads/' . $postId . '.html');
                $postArtifact = (string) file_get_contents($artifactRoot . '/posts/' . $postId . '.html');

                assertStringContains('status=ok', $response);
                assertStringContains('Subject: Привет', $record);
                assertStringContains('Привет мир', $record);
                assertStringContains('Привет', $threadPage);
                assertStringContains('Привет мир', $threadPage);
                assertStringContains('Привет', $postPage);
                assertStringContains('Привет', $activity);
                assertStringContains('Привет', $rss);
                assertStringContains('Привет мир', $threadArtifact);
                assertStringContains('Привет мир', $postArtifact);
                assertTrue(is_file($artifactRoot . '/threads/' . $postId . '.html'));
                assertTrue(is_file($artifactRoot . '/posts/' . $postId . '.html'));
            } finally {
                putenv('FORUM_UNICODE_AUTHORED_TEXT');
            }
        } finally {
            putenv($previousStaticDetails === false
                ? 'FORUM_STATIC_DETAIL_PAGES_ENABLED'
                : 'FORUM_STATIC_DETAIL_PAGES_ENABLED=' . $previousStaticDetails);
        }
    }

    public function testUnicodeAuthoredTextCanBeEnabledFromSiteFeatureFlagsRecord(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_UNICODE_AUTHORED_TEXT: true\n"
        );

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=' . rawurlencode('Привет') . '&body=' . rawurlencode('Привет мир')
        );
        $postId = $this->extractValue($response, 'post_id');
        $record = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $postId));

        assertStringContains('status=ok', $response);
        assertStringContains('Subject: Привет', $record);
        assertStringContains('Привет мир', $record);
    }

    public function testEmojiAuthoredTextRequiresUnicodeAndEmojiFeatureFlags(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        file_put_contents(
            $repositoryRoot . '/records/instance/feature-flags.txt',
            "Schema: site-feature-flags-v1\n\nFORUM_EMOJI_AUTHORED_TEXT: true\nFORUM_UNICODE_AUTHORED_TEXT: true\n"
        );

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=' . rawurlencode('Hello 🙂') . '&body=' . rawurlencode('Looks good 🙂')
        );
        $postId = $this->extractValue($response, 'post_id');
        $record = (string) file_get_contents($repositoryRoot . '/' . $this->canonicalPostPath($repositoryRoot, $postId));

        assertStringContains('status=ok', $response);
        assertStringContains('Subject: Hello 🙂', $record);
        assertStringContains('Looks good 🙂', $record);
    }

    public function testSetFeatureFlagWritesCanonicalRecordCommitsAndInvalidatesArtifacts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
            '/tools.html',
            '/tools/feature-flags.html',
        ]);
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        $result = $service->setFeatureFlag([
            'key' => 'FORUM_APP_VERSION_NOTIFICATION',
            'value' => 'false',
        ]);
        $record = (string) file_get_contents($repositoryRoot . '/records/instance/feature-flags.txt');

        assertSame('ok', $result['status']);
        assertSame('yes', $result['wrote_record']);
        assertSame('site', $result['source']);
        assertSame('false', $result['site_value']);
        assertSame('false', $result['effective_value']);
        assertTrue(strlen((string) $result['commit_sha']) === 40);
        assertSame($result['commit_sha'], $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
        assertStringContains('Schema: site-feature-flags-v1', $record);
        assertStringContains('FORUM_APP_VERSION_NOTIFICATION: false', $record);
        assertFalse(is_file($artifactRoot . '/index.html'));
        assertFalse(is_file($artifactRoot . '/tools.html'));
        assertFalse(is_file($artifactRoot . '/tools/feature-flags.html'));
    }

    public function testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $signingKey = $this->createSigningKey('feature-flag-signer');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $_POST = ['public_key' => $signingKey['public_key']];
            $identityResponse = $this->renderMethod($application, 'POST', '/api/link_identity');
            $_POST = [];
            $identityId = $this->extractValue($identityResponse, 'identity_id');
            $this->seedApprovedIdentity($repositoryRoot, $identityId);
            $this->renderMethod($application, 'GET', '/');

            $_COOKIE = ['identity_hint' => $identityId];
            $prepared = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/prepare_feature_flag_change?key=FORUM_APP_VERSION_NOTIFICATION&value=false'
            ), true, 512, JSON_THROW_ON_ERROR);
            $snapshotPath = $repositoryRoot . '/records/instance/feature-flags.txt';
            $snapshotBefore = is_file($snapshotPath) ? (string) file_get_contents($snapshotPath) : null;

            $_POST = [
                'prepare_token' => $prepared['prepare_token'], 'record_id' => $prepared['record_id'],
                'record_path' => $prepared['record_path'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => 'invalid detached signature',
            ];
            $invalid = json_decode($this->renderMethod($application, 'POST', '/api/finalize_feature_flag_change'), true, 512, JSON_THROW_ON_ERROR);
            $_POST = [];
            assertSame('error', $invalid['status']);
            assertSame('Detached signature verification failed: invalid_signature', $invalid['error']);
            assertSame($snapshotBefore, is_file($snapshotPath) ? (string) file_get_contents($snapshotPath) : null);
            assertFalse(is_file($repositoryRoot . '/' . $prepared['record_path']));

            $signature = $this->signCanonicalRecord($signingKey['home'], $prepared['canonical_record']);
            $_POST = [
                'prepare_token' => $prepared['prepare_token'], 'record_id' => $prepared['record_id'],
                'record_path' => $prepared['record_path'], 'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $signature,
            ];
            $result = json_decode($this->renderMethod($application, 'POST', '/api/finalize_feature_flag_change'), true, 512, JSON_THROW_ON_ERROR);
            $_POST = [];

            assertSame('ok', $result['status']);
            assertSame($identityId, $result['operator_identity_id']);
            assertSame($signature, (string) file_get_contents($repositoryRoot . '/' . $prepared['record_path'] . '.asc'));
            assertStringContains('FORUM_APP_VERSION_NOTIFICATION: false', (string) file_get_contents($repositoryRoot . '/records/instance/feature-flags.txt'));
            $committedFiles = $this->gitOutput($repositoryRoot, 'show --name-only --format= ' . escapeshellarg($result['commit_sha']));
            assertStringContains('records/instance/feature-flags.txt', $committedFiles);
            assertStringContains($prepared['record_path'], $committedFiles);
            assertStringContains($prepared['record_path'] . '.asc', $committedFiles);
            $expectedFiles = ['records/instance/feature-flags.txt', $prepared['record_path'], $prepared['record_path'] . '.asc'];
            $actualFiles = preg_split('/\R/', trim($committedFiles));
            sort($expectedFiles);
            sort($actualFiles);
            assertSame($expectedFiles, $actualFiles);
            $activity = $this->renderMethod($application, 'GET', '/activity/');
            assertStringContains($identityId, $activity);
            assertStringContains($prepared['record_path'], $activity);
            assertStringContains($prepared['record_path'] . '.asc', $activity);

            $rebuiltDatabasePath = dirname($databasePath) . '/rebuilt-' . basename($databasePath);
            $rebuiltApplication = new Application(dirname(__DIR__), $repositoryRoot, $rebuiltDatabasePath, $artifactRoot);
            $rebuiltActivity = $this->renderMethod($rebuiltApplication, 'GET', '/activity/');
            assertStringContains($identityId, $rebuiltActivity);
            assertStringContains($prepared['record_path'], $rebuiltActivity);
        } finally {
            $_POST = [];
            $_COOKIE = [];
            $this->deleteTree($signingKey['home']);
        }
    }

    public function testVerifiedAttributionCutoffIsSetBySwitchOnKeptByToggleAndMovable(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $signingKey = $this->createSigningKey('attribution-cutoff-signer');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            assertSame(null, AttributionCutoff::resolve($repositoryRoot));
            $unauthorized = json_decode($this->renderMethod($application, 'POST', '/api/prepare_attribution_cutoff?cutoff_at=2026-01-01T00:00'), true, 512, JSON_THROW_ON_ERROR);
            assertSame('error', $unauthorized['status']);

            $_POST = ['public_key' => $signingKey['public_key']];
            $identityId = $this->extractValue($this->renderMethod($application, 'POST', '/api/link_identity'), 'identity_id');
            $_POST = [];
            $this->seedApprovedIdentity($repositoryRoot, $identityId);
            $this->renderMethod($application, 'GET', '/');
            $_COOKIE = ['identity_hint' => $identityId];

            $signedChange = function (string $prepareRoute, string $finalizeRoute) use ($application, $signingKey): array {
                $prepared = json_decode($this->renderMethod($application, 'POST', $prepareRoute), true, 512, JSON_THROW_ON_ERROR);
                assertSame('ok', $prepared['status']);
                $_POST = [
                    'prepare_token' => $prepared['prepare_token'], 'record_id' => $prepared['record_id'],
                    'record_path' => $prepared['record_path'], 'canonical_record' => $prepared['canonical_record'],
                    'detached_signature' => $this->signCanonicalRecord($signingKey['home'], $prepared['canonical_record']),
                ];
                $result = json_decode($this->renderMethod($application, 'POST', $finalizeRoute), true, 512, JSON_THROW_ON_ERROR);
                $_POST = [];
                assertSame('ok', $result['status']);

                return $prepared + ['result' => $result];
            };
            $flagChange = fn (string $value): array => $signedChange(
                '/api/prepare_feature_flag_change?key=FORUM_VERIFIED_ATTRIBUTION&value=' . $value,
                '/api/finalize_feature_flag_change'
            );

            $switchOn = $flagChange('true');
            preg_match('/^Created-At: (\S+)$/m', $switchOn['canonical_record'], $createdAt);
            assertSame($createdAt[1], $switchOn['result']['attribution_cutoff_at']);
            assertSame(['cutoff_at' => $createdAt[1], 'source' => 'switch-on', 'source_path' => $switchOn['record_path']], AttributionCutoff::resolve($repositoryRoot));
            assertStringContains('Cutoff: <time data-attribution-cutoff-at datetime="' . $createdAt[1] . '"', $this->renderMethod($application, 'GET', '/tools/feature-flags/'));

            sleep(1);
            $flagChange('false');
            $flagChange('true');
            assertSame($createdAt[1], AttributionCutoff::resolve($repositoryRoot)['cutoff_at']);

            $future = json_decode($this->renderMethod($application, 'POST', '/api/prepare_attribution_cutoff?cutoff_at=2999-01-01T00:00'), true, 512, JSON_THROW_ON_ERROR);
            assertSame('The attribution cutoff cannot be in the future.', $future['error']);

            $moved = $signedChange('/api/prepare_attribution_cutoff?cutoff_at=2026-01-01T00:00', '/api/finalize_attribution_cutoff');
            assertSame('2026-01-01T00:00:00Z', $moved['result']['attribution_cutoff_at']);
            assertSame(['cutoff_at' => '2026-01-01T00:00:00Z', 'source' => 'moved', 'source_path' => $moved['record_path']], AttributionCutoff::resolve($repositoryRoot));
            $committedFiles = preg_split('/\R/', trim($this->gitOutput($repositoryRoot, 'show --name-only --format= HEAD')));
            sort($committedFiles);
            assertSame([$moved['record_path'], $moved['record_path'] . '.asc'], $committedFiles);
            assertStringContains('2026-01-01 00:00:00 UTC', $this->renderMethod($application, 'GET', '/tools/feature-flags/'));

            unlink($repositoryRoot . '/' . $moved['record_path'] . '.asc');
            assertSame('switch-on', AttributionCutoff::resolve($repositoryRoot)['source']);
        } finally {
            $_POST = [];
            $_COOKIE = [];
            $this->deleteTree($signingKey['home']);
        }
    }

    public function testVerifiedTotalsAgreeAcrossRebuildIncrementalToggleCutoffMoveAndEnvironment(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('verified-totals-signer');
        $previousFlag = getenv('FORUM_VERIFIED_ATTRIBUTION');
        putenv('FORUM_VERIFIED_ATTRIBUTION');
        try {
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $identity = $writer->linkIdentity(['public_key' => $key['public_key']])['identity_id'];
            $this->seedApprovedIdentity($root, $identity);
            $this->renderMethod(new Application(dirname(__DIR__), $root, $database, $artifacts), 'GET', '/');
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $signed = function (string $prepare, string $finalize, array $input) use (&$writer, $identity, $key): array {
                $prepared = $writer->{$prepare}($input, $identity);
                return $writer->{$finalize}([
                    'prepare_token' => $prepared['prepare_token'], 'record_id' => $prepared['record_id'],
                    'record_path' => $prepared['record_path'], 'canonical_record' => $prepared['canonical_record'],
                    'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record']),
                ], $identity);
            };
            $flag = function (bool $value) use (&$writer, $signed, $root, $database, $artifacts): void {
                $signed('prepareFeatureFlagChange', 'finalizePreparedFeatureFlagChange', ['key' => 'FORUM_VERIFIED_ATTRIBUTION', 'value' => $value ? 'true' : 'false']);
                $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            };
            $columns = static function (string $path): array {
                $pdo = (new ReadModelConnection($path))->open();
                return [
                    $pdo->query("SELECT score_total, claimed_score_total, verified_score_total, vote_count FROM threads WHERE root_post_id = 'root-001'")->fetch(\PDO::FETCH_NUM),
                    $pdo->query("SELECT post_score_total, claimed_post_score_total, verified_post_score_total, approved_flag_count, claimed_approved_flag_count, verified_approved_flag_count FROM posts WHERE post_id = 'reply-001'")->fetch(\PDO::FETCH_NUM),
                    $pdo->query("SELECT score_total, claimed_score_total, verified_score_total FROM threads WHERE root_post_id = 'thread-zenmemes-rules'")->fetch(\PDO::FETCH_NUM),
                    $pdo->query('SELECT post_id, attribution_verified, attribution_counted, author_profile_slug FROM posts ORDER BY post_id')->fetchAll(\PDO::FETCH_NUM),
                    $pdo->query('SELECT identity_id, post_count, thread_count, claimed_post_count, verified_post_count, claimed_thread_count, verified_thread_count FROM profiles ORDER BY identity_id')->fetchAll(\PDO::FETCH_NUM),
                    $pdo->query('SELECT source_path, label, attribution_verified, attribution_counted FROM activity WHERE source_path IS NOT NULL AND author_identity_id IS NOT NULL ORDER BY source_path, label')->fetchAll(\PDO::FETCH_NUM),
                ];
            };
            $rebuilt = function () use ($root, $columns): array {
                $path = sys_get_temp_dir() . '/forum-rewrite-verified-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
                (new ReadModelBuilder($root, $path, new CanonicalRecordRepository($root)))->rebuild();
                $result = $columns($path);
                unlink($path);
                return $result;
            };

            // Before any cutoff, an unsigned like and an unsigned flag naming the member count.
            $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $identity]);
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'flag', 'author_identity_id' => $identity]);
            sleep(1);
            $flag(true);
            $cutoff = AttributionCutoff::resolve($root)['cutoff_at'];
            [$thread, $post] = $columns($database);
            assertSame([1, 1, 1], array_slice($thread, 0, 3), 'Switch-on changes no counts');
            assertSame([-100, -100, -100, 1, 1, 1], $post);
            sleep(1);

            // After the cutoff, an unsigned upvote naming the member is claimed only; a signed downvote counts.
            $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'upvote', 'author_identity_id' => $identity]);
            $created = gmdate('Y-m-d\TH:i:s\Z');
            $record = "Record-ID: thread-label-verified-down\nCreated-At: {$created}\nThread-ID: root-001\nOperation: add\nLabels: downvote\nAuthor-Identity-ID: {$identity}\n\n";
            $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'downvote', 'author_identity_id' => $identity,
                'canonical_record' => $record, 'detached_signature' => $this->signCanonicalRecord($key['home'], $record)]);
            [$thread] = $columns($database);
            assertSame([0, 1, 0], array_slice($thread, 0, 3), 'Flag on: effective score uses verified totals');

            // A signed reply and its body label count; an unsigned reply naming the member does not.
            $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $signedReply = $this->createAuthoredReply($app, ['identity_id' => $identity, 'home' => $key['home']], 'thread-zenmemes-rules', 'Agreed #upvote');
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $unsignedReply = $writer->createReply(['thread_id' => 'thread-zenmemes-rules', 'parent_id' => 'thread-zenmemes-rules', 'body' => 'Not me #downvote', 'author_identity_id' => $identity])['post_id'];
            [, , $rules, $posts, $profiles] = $columns($database);
            $postsById = array_column($posts, null, 0);
            assertSame([1, 1], [$postsById[$signedReply][1], $postsById[$signedReply][2]]);
            assertSame([$unsignedReply, 0, 0, null], $postsById[$unsignedReply], 'Unverified reply is unlinked and uncounted');
            assertSame(1, $rules[2] - $rules[1], 'Body label of the unverified reply is claimed only');
            assertSame($rules[2], $rules[0]);
            $profile = array_column($profiles, null, 0)[$identity];
            assertSame(1, $profile[3] - $profile[4], 'Profile counts drop the unverified reply');
            assertSame($profile[4], $profile[1]);
            $profileIds = array_column(\ForumRewrite\ReadModel\AuthoredContentRepository::visiblePosts((new ReadModelConnection($database))->open(), [$identity]), 'post_id');
            assertTrue(in_array($signedReply, $profileIds, true));
            assertFalse(in_array($unsignedReply, $profileIds, true));
            $render = fn (string $path): string => $this->renderMethod(new Application(dirname(__DIR__), $root, $database, $artifacts), 'GET', $path);
            assertSame(1, substr_count($render('/threads/thread-zenmemes-rules'), '(unsigned)'), 'Only the unverified reply is marked');
            assertStringContains('(unsigned)', $render('/activity/?view=all'));
            assertSame([0, 0], array_slice(array_values(array_filter($columns($database)[5], static fn (array $row): bool => str_contains($row[0], $unsignedReply)))[0], 2), 'Activity marks the unverified reply');
            assertSame($rebuilt(), $columns($database), 'Incremental equals rebuild');

            $metadata = static fn (): array => ReadModelMetadata::readMetadata((new ReadModelConnection($database))->open());
            $flag(false);
            assertSame('write_incremental', $metadata()['rebuild_reason'], 'Toggle needs no rebuild');
            assertSame([1, 1, 0], array_slice($columns($database)[0], 0, 3));
            $postsById = array_column($columns($database)[3], null, 0);
            assertSame(1, $postsById[$unsignedReply][2], 'Flag off: the reply counts again');
            assertTrue($postsById[$unsignedReply][3] !== null, 'Flag off: the reply links again');
            assertSame(0, substr_count($this->renderMethod(new Application(dirname(__DIR__), $root, $database, $artifacts), 'GET', '/threads/thread-zenmemes-rules'), '(unsigned)'), 'Flag off: no marks');
            assertSame($rebuilt(), $columns($database));
            $flag(true);
            assertSame($cutoff, AttributionCutoff::resolve($root)['cutoff_at'], 'Off and on keeps the cutoff');
            assertSame([0, 1, 0], array_slice($columns($database)[0], 0, 3));

            // Moving the cutoff before the first unsigned votes leaves only the signed vote verified.
            $signed('prepareAttributionCutoff', 'finalizePreparedAttributionCutoff', ['cutoff_at' => '2026-01-01T00:00']);
            [$thread, $post] = $columns($database);
            assertSame([-1, 1, -1], array_slice($thread, 0, 3));
            assertSame([0, -100, 0, 0, 1, 0], $post);
            assertSame($rebuilt(), $columns($database));

            // Unsigned writes naming the member say so and how to sign; anonymous and signed writes do not.
            $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $_POST = ['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Named', 'author_identity_id' => $identity];
            $named = $this->renderMethod($app, 'POST', '/api/create_reply');
            assertStringContains("attribution=unsigned\n", $named);
            assertStringContains('/api/prepare_reply, then /api/create_prepared_post', $named);
            $_POST = ['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Anonymous'];
            assertStringNotContains('attribution=', $this->renderMethod($app, 'POST', '/api/create_reply'));
            $_COOKIE = ['identity_hint' => $identity];
            $_POST = ['thread_id' => 'root-001', 'tag' => 'like'];
            assertStringContains('attribution_notice=Recorded as unsigned', $this->renderMethod($app, 'POST', '/api/apply_thread_tag'));
            $created = gmdate('Y-m-d\TH:i:s\Z');
            $record = "Record-ID: post-reaction-verified-like\nCreated-At: {$created}\nPost-ID: root-001\nOperation: add\nTags: like\nAuthor-Identity-ID: {$identity}\n\n";
            $_POST = ['post_id' => 'root-001', 'tag' => 'like', 'canonical_record' => $record, 'detached_signature' => $this->signCanonicalRecord($key['home'], $record)];
            $signedTag = $this->renderMethod($app, 'POST', '/api/apply_post_tag');
            assertStringContains('wrote_record=yes', $signedTag);
            assertStringNotContains('attribution=', $signedTag);
            $_POST = [];
            $_COOKIE = [];

            // An environment override is applied on the next request without a rebuild.
            putenv('FORUM_VERIFIED_ATTRIBUTION=false');
            $this->renderMethod(new Application(dirname(__DIR__), $root, $database, $artifacts), 'GET', '/');
            assertSame([1, 1, -1], array_slice($columns($database)[0], 0, 3));
            assertSame('0', $metadata()[VerifiedAttribution::METADATA_ENABLED]);
        } finally {
            putenv($previousFlag === false ? 'FORUM_VERIFIED_ATTRIBUTION' : 'FORUM_VERIFIED_ATTRIBUTION=' . $previousFlag);
            $this->deleteTree($key['home']);
            $this->deleteTree($root);
        }
    }

    public function testSetFeatureFlagAcceptsDisplayedEnabledDisabledValues(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        $disabled = $service->setFeatureFlag([
            'key' => 'FORUM_APP_VERSION_NOTIFICATION',
            'value' => 'disabled',
        ]);
        $enabled = $service->setFeatureFlag([
            'key' => 'FORUM_APP_VERSION_NOTIFICATION',
            'value' => 'enabled',
        ]);
        $record = (string) file_get_contents($repositoryRoot . '/records/instance/feature-flags.txt');

        assertSame('false', $disabled['site_value']);
        assertSame('true', $enabled['site_value']);
        assertStringContains('FORUM_APP_VERSION_NOTIFICATION: true', $record);
    }

    public function testSetFeatureFlagRejectsEnvironmentOverrideAndInvalidValues(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        assertThrowsRuntime(
            static fn () => $service->setFeatureFlag(['key' => 'FORUM_APP_VERSION_NOTIFICATION', 'value' => 'maybe']),
            'value must be true or false.'
        );
        assertThrowsRuntime(
            static fn () => $service->setFeatureFlag(['key' => 'FORUM_NOT_REAL', 'value' => 'true']),
            'unknown feature flag: FORUM_NOT_REAL'
        );

        putenv('FORUM_APP_VERSION_NOTIFICATION=false');
        try {
            $overriddenService = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
            assertThrowsRuntime(
                static fn () => $overriddenService->setFeatureFlag(['key' => 'FORUM_APP_VERSION_NOTIFICATION', 'value' => 'true']),
                'feature flag is currently overridden by environment: FORUM_APP_VERSION_NOTIFICATION'
            );
        } finally {
            putenv('FORUM_APP_VERSION_NOTIFICATION');
        }
    }

    public function testFeatureFlagApisRequireRootApprovalAndBrowserSignature(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $unauthorized = $this->renderMethod(
            $application,
            'POST',
            '/api/set_feature_flag?key=FORUM_APP_VERSION_NOTIFICATION&value=false'
        );
        $_COOKIE = ['identity_hint' => 'guest'];
        $unsigned = $this->renderMethod(
            $application,
            'POST',
            '/api/set_feature_flag?key=FORUM_APP_VERSION_NOTIFICATION&value=false'
        );
        $prepared = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_feature_flag_change?key=FORUM_APP_VERSION_NOTIFICATION&value=false'
        ), true, 512, JSON_THROW_ON_ERROR);
        $_COOKIE = [];

        assertStringContains('error=Feature flag changes require a root-approved identity.', $unauthorized);
        assertStringContains('error=Feature flag changes require a browser signature.', $unsigned);
        assertSame('ok', $prepared['status']);
        assertSame('yes', $prepared['wrote_record']);
        assertStringContains('Operator-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $prepared['canonical_record']);
        assertFalse(is_file($repositoryRoot . '/records/instance/feature-flags.txt'));
    }

    public function testSetFeatureFlagAddsActivityForWarmReadModel(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $response = $service->setFeatureFlag(['key' => 'FORUM_APP_VERSION_NOTIFICATION', 'value' => 'false']);
        $activity = $this->renderMethod($application, 'GET', '/activity/');

        $commitSha = $response['commit_sha'];
        assertStringContains('site_feature_flag', $activity);
        assertStringContains('Set feature flag FORUM_APP_VERSION_NOTIFICATION=false', $activity);
        assertStringContains('records/instance/feature-flags.txt', $activity);
        assertStringContains(substr($commitSha, 0, 12), $activity);
    }

    public function testFeatureFlagActivityIsRecoveredByFullRebuildFromGitHistory(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->setFeatureFlag([
            'key' => 'FORUM_APP_VERSION_NOTIFICATION',
            'value' => 'false',
        ]);
        $rebuiltDatabasePath = dirname($databasePath) . '/rebuilt-' . basename($databasePath);
        $rebuiltApplication = new Application(dirname(__DIR__), $repositoryRoot, $rebuiltDatabasePath, $artifactRoot);

        $activity = $this->renderMethod($rebuiltApplication, 'GET', '/activity/');

        assertStringContains('site_feature_flag', $activity);
        assertStringContains('Set feature flag FORUM_APP_VERSION_NOTIFICATION=false', $activity);
        assertStringContains('records/instance/feature-flags.txt', $activity);
        assertStringContains(substr((string) $result['commit_sha'], 0, 12), $activity);
    }

    public function testFeatureFlagFormSubmitRequiresRootApprovedIdentityAndRedirectsAfterCommit(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $anonymousForm = $this->renderMethod($application, 'GET', '/tools/feature-flags/');
        $unauthorized = $this->renderMethod(
            $application,
            'POST',
            '/tools/feature-flags/?key=FORUM_APP_VERSION_NOTIFICATION&value=false'
        );
        $_COOKIE = ['identity_hint' => 'guest'];
        $form = $this->renderMethod($application, 'GET', '/tools/feature-flags/');
        $redirect = $this->renderMethod(
            $application,
            'POST',
            '/tools/feature-flags/?key=FORUM_APP_VERSION_NOTIFICATION&value=false'
        );
        $updated = $this->renderMethod($application, 'GET', '/tools/feature-flags/');
        $_COOKIE = [];

        assertStringNotContains('data-feature-flag-toggle', $anonymousForm);
        assertStringContains('title="Read-only &mdash; requires a root-approved identity to change."', $anonymousForm);
        assertStringContains('method="post" action="/tools/feature-flags/"', $form);
        assertStringContains('data-feature-flag-form', $form);
        assertStringContains('Feature flag changes require a root-approved identity.', $unauthorized);
        assertStringContains('Feature flag changes require a browser signature.', $redirect);
        assertStringContains('FORUM_APP_VERSION_NOTIFICATION', $updated);
        assertStringContains('data-role="feature-flag-source">default</span>', $updated);
    }

    public function testFeatureFlagFormSubmitAcceptsDisplayedEnabledDisabledValues(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $_POST = [
            'key' => 'FORUM_APP_VERSION_NOTIFICATION',
            'value' => 'disabled',
        ];
        try {
            $redirect = $this->renderMethod($application, 'POST', '/tools/feature-flags/');
        } finally {
            $_COOKIE = [];
            $_POST = [];
        }

        assertStringContains('Feature flag changes require a browser signature.', $redirect);
        assertFalse(is_file($repositoryRoot . '/records/instance/feature-flags.txt'));
    }

    public function testFeatureFlagWriteSyncsRepositoryHeadMetadataImmediately(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $service->setFeatureFlag(['key' => 'FORUM_APP_VERSION_NOTIFICATION', 'value' => 'false']);

        $currentHead = ReadModelMetadata::repositoryHead($repositoryRoot);
        $pdo = new PDO('sqlite:' . $databasePath);
        $metadata = [];
        foreach ($pdo->query('SELECT key, value FROM metadata')->fetchAll() as $row) {
            $metadata[$row['key']] = $row['value'];
        }

        assertSame($currentHead, $metadata['repository_head'] ?? null);
        $this->assertReadModelHealthy($databasePath);

        // The read model already matches HEAD, so the next request must not
        // need to rebuild - confirm it stays in sync rather than requiring
        // this second render to resync it.
        $this->renderMethod($application, 'GET', '/about/');
        $pdo2 = new PDO('sqlite:' . $databasePath);
        $metadataAfter = [];
        foreach ($pdo2->query('SELECT key, value FROM metadata')->fetchAll() as $row) {
            $metadataAfter[$row['key']] = $row['value'];
        }
        assertSame($currentHead, $metadataAfter['repository_head'] ?? null);
    }

    public function testFeatureFlagWriteClearsPreexistingStaleMarkerAndAvoidsDuplicateActivity(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $staleMarker = new ReadModelStaleMarker($databasePath);
        $staleMarker->mark([
            'reason' => 'write_refresh_failed',
            'note' => 'simulated failure from an unrelated write, for this test',
        ]);
        assertTrue($staleMarker->exists());

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $service->setFeatureFlag(['key' => 'FORUM_APP_VERSION_NOTIFICATION', 'value' => 'false']);
        $this->assertReadModelHealthy($databasePath);

        $pdo = new PDO('sqlite:' . $databasePath);
        $activityCount = (int) $pdo->query(
            "SELECT COUNT(*) AS c FROM activity WHERE kind = 'site_feature_flag'"
        )->fetch()['c'];
        assertSame(1, $activityCount);

        $currentHead = ReadModelMetadata::repositoryHead($repositoryRoot);
        $metadata = [];
        foreach ($pdo->query('SELECT key, value FROM metadata')->fetchAll() as $row) {
            $metadata[$row['key']] = $row['value'];
        }
        assertSame($currentHead, $metadata['repository_head'] ?? null);
    }

    public function testThreadAndReplyWritesUseIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $threadResult = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Incremental Thread',
            'body' => 'Incremental body',
        ]);
        $replyResult = $service->createReply([
            'thread_id' => $threadResult['thread_id'],
            'parent_id' => $threadResult['thread_id'],
            'board_tags' => 'general',
            'body' => 'Incremental reply',
        ]);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadResult['thread_id']);
        $replyPage = $this->renderMethod($application, 'GET', '/posts/' . $replyResult['post_id']);

        $this->assertUsedIncrementalUpdate($threadResult);
        $this->assertUsedIncrementalUpdate($replyResult);
        assertStringContains('Incremental Thread', $threadPage);
        assertStringContains('Incremental reply', $replyPage);
        assertStringContains('Incremental reply', $threadPage);
    }

    public function testLinkIdentityUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->linkIdentity([
            'public_key' => $this->readFixturePublicKey(),
            'bootstrap_post_id' => 'root-001',
        ]);

        $profilePage = $this->renderMethod($application, 'GET', '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');

        $this->assertUsedIncrementalUpdate($result);
        assertStringContains('<h1>forum-user</h1>', $profilePage);
        assertStringContains('forum-user', $threadPage);
    }

    public function testLinkIdentityWithNewBootstrapUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->linkIdentity([
            'public_key' => $this->readFixturePublicKey(),
        ]);

        $this->assertUsedIncrementalUpdate($result);
    }

    public function testCreateThreadWithHashtagsWritesThreadLabelRecordAndRendersLabels(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Tagged%20Thread&body='
            . rawurlencode("Thread body\n#bug #needs-review\n> #ignored\n#bug\n")
        );
        $threadId = $this->extractValue($response, 'thread_id');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);
        $records = glob($repositoryRoot . '/records/thread-labels/*.txt');

        assertStringContains('status=ok', $response);
        assertStringContains('Labels: bug, needs-review', $threadPage);
        assertSame(1, count(array_filter($records, static fn (string $path): bool => str_contains((string) file_get_contents($path), "Target-Kind: thread\nTarget-ID: " . $threadId))));

        $recordPath = array_values(array_filter($records, static fn (string $path): bool => str_contains((string) file_get_contents($path), "Target-Kind: thread\nTarget-ID: " . $threadId)))[0];
        $recordContents = (string) file_get_contents($recordPath);
        assertStringContains('Source-Post-ID: ' . $threadId, $recordContents);
        assertSame('proven', \ForumRewrite\Canonical\LabelOwnership::inspect((new \ForumRewrite\Canonical\ThreadLabelRecordParser())->parse($recordContents), $repositoryRoot)['status']);
        assertStringContains('Tags: bug needs-review', $recordContents);
        assertStringNotContains('ignored', $recordContents);
    }

    public function testCreateReplyWithHashtagsWritesThreadLabelRecordForTargetThread(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&body=' . rawurlencode("Reply body\n#answered\n> #ignored\n")
        );
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $records = glob($repositoryRoot . '/records/thread-labels/*.txt');
        $matchingRecords = array_values(array_filter(
            $records,
            static fn (string $path): bool => str_contains((string) file_get_contents($path), "Target-Kind: thread\nTarget-ID: root-001\n")
                && str_contains((string) file_get_contents($path), 'Tags: answered')
        ));

        assertStringContains('status=ok', $response);
        assertStringContains('Labels: answered, bug, needs-review', $threadPage);
        assertSame(1, count($matchingRecords));
        $label = (new \ForumRewrite\Canonical\ThreadLabelRecordParser())->parse((string) file_get_contents($matchingRecords[0]));
        assertSame($this->extractValue($response, 'post_id'), $label->sourcePostId);
        assertSame('proven', \ForumRewrite\Canonical\LabelOwnership::inspect($label, $repositoryRoot)['status']);
    }

    public function testLabeledWritesUseIncrementalReadModelAndPublishLabelActivityImmediately(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->createReply([
            'thread_id' => 'root-001',
            'parent_id' => 'root-001',
            'board_tags' => 'general',
            'body' => "Reply body\n#answered\n",
        ]);

        $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');

        assertSame(true, isset($result['timings']['read_model_incremental_update']));
        assertSame(true, isset($result['timings']['read_model_incremental_post_insert_post']));
        assertSame(true, isset($result['timings']['read_model_incremental_thread_label_update_thread_labels']));
        assertSame(true, isset($result['timings']['read_model_incremental_thread_label_refresh_thread_label_activity']));
        assertSame(false, isset($result['timings']['read_model_rebuild']));
        assertStringContains('thread_label_add', $activity);
        assertStringContains('Labels added: answered', $activity);
        assertStringContains('/threads/root-001', $activity);
    }

    public function testVersion23StartupRecoversOmittedLikeActivityWithoutNewRecords(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
        $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        foreach (['#like', 'Another contribution #like'] as $body) {
            $writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => $body, 'author_identity_id' => $author]);
        }
        $pdo = new PDO('sqlite:' . $database);
        $where = "kind='thread_label_add' AND thread_id='root-001' AND label LIKE '%like%'";
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM activity WHERE ' . $where)->fetchColumn());
        $recordsBefore = glob($root . '/records/thread-labels/*.txt');
        $pdo->exec('DELETE FROM activity WHERE id=(SELECT MAX(id) FROM activity WHERE ' . $where . ')');
        $pdo->exec("UPDATE metadata SET value='23' WHERE key='schema_version'");
        $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
        assertStringContains('Labels added: like', $this->renderMethod($app, 'GET', '/activity/'));
        assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM activity WHERE ' . $where)->fetchColumn());
        $output = [];
        exec(escapeshellarg(dirname(__DIR__) . '/v3') . ' rebuild ' . escapeshellarg($root) . ' ' . escapeshellarg($database) . ' 2>&1', $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM activity WHERE ' . $where)->fetchColumn());
        assertSame($recordsBefore, glob($root . '/records/thread-labels/*.txt'));
    }

    public function testEachAcceptedLikeRecordAppearsOnceWithoutChangingVoteDeduplication(): void
    {
        [$root, $database, $artifacts] = $this->createTempEnvironment();
        $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
        $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
        $other = $this->linkGeneratedIdentity($app, 'later-like');
        $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => '#like', 'author_identity_id' => $author]);
        $later = $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $other['identity_id']]);
        assertSame('yes', $later['wrote_record']);
        $repeat = $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $other['identity_id']]);
        assertSame('no', $repeat['wrote_record']);
        $writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => '#like #answered #bug', 'author_identity_id' => $author]);
        $pdo = new PDO('sqlite:' . $database);
        $query = "SELECT action_key, created_at, source_path, source_commit_sha, author_identity_id, label FROM activity WHERE kind='thread_label_add' AND thread_id='root-001' AND label LIKE '%like%' ORDER BY action_key";
        $incremental = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        assertSame(3, count($incremental));
        assertSame(3, count(array_unique(array_column($incremental, 'source_commit_sha'))));
        foreach ($incremental as $event) {
            $history = [];
            exec('git -C ' . escapeshellarg($root) . ' log -1 --format=%H -- ' . escapeshellarg($event['source_path']), $history, $exitCode);
            assertSame(0, $exitCode);
            assertSame($history[0], $event['source_commit_sha']);
        }
        assertSame(3, count(array_unique(array_column($incremental, 'action_key'))));
        assertSame(2, count(array_filter($incremental, static fn (array $row): bool => $row['author_identity_id'] === $author)));
        assertSame(1, count(array_filter($incremental, static fn (array $row): bool => $row['label'] === 'Labels added: answered, like')));
        $activity = new ActivityService(static fn (): PDO => $pdo, $root, $database);
        $keys = [];
        $cursor = null;
        do {
            $page = $activity->fetchActivityRows('all', 'date', 'desc', $cursor, 1);
            $row = $page['items'][0];
            $keys[] = $row['action_key'];
            $cursor = ['sort_value' => $row['created_at'], 'action_key' => $row['action_key'], 'id' => (int) $row['id']];
        } while ($page['has_more']);
        assertSame($activity->countActivityViewTotal('all'), count($keys));
        assertSame(count($keys), count(array_unique($keys)));
        foreach ($incremental as $event) assertTrue(in_array($event['action_key'], $keys, true));
        $before = $pdo->query("SELECT score_total, vote_count, thread_labels_json FROM threads WHERE root_post_id='root-001'")->fetch(PDO::FETCH_ASSOC);
        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
        assertSame($incremental, $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC));
        assertSame($before, $pdo->query("SELECT score_total, vote_count, thread_labels_json FROM threads WHERE root_post_id='root-001'")->fetch(PDO::FETCH_ASSOC));
        assertSame('no', $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $other['identity_id']])['wrote_record']);
        assertSame(3, count($pdo->query($query)->fetchAll(PDO::FETCH_ASSOC)));
        // A restored archive can have canonical records without Git history.
        rename($root . '/.git', $root . '/saved-git');
        try {
            (new IncrementalReadModelUpdater($database, $root))->applyThreadLabelWrite('root-001', str_repeat('a', 40));
            $withoutHistory = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
            assertSame([null, null, null], array_column($withoutHistory, 'source_commit_sha'));
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            assertSame($withoutHistory, $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            rename($root . '/saved-git', $root . '/.git');
        }
    }

    public function testApprovedLikeTagAddsScoreAndDoesNotDoubleCountForSameIdentity(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $first = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
            . '&body=' . rawurlencode("Reply body\n#like\n")
        );
        $second = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
            . '&body=' . rawurlencode("Another reply\n#like\n")
        );

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');
        $board = $this->renderMethod($application, 'GET', '/');

        assertStringContains('status=ok', $first);
        assertStringContains('status=ok', $second);
        assertStringNotContains('Score: 1', $threadPage);
        assertStringContains('Score-Total: 1', $threadApi);
        assertStringNotContains('Score: 1', $board);
    }

    public function testUnapprovedLikeTagDoesNotAffectScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $identity = $this->linkGeneratedIdentity($application, 'alice');
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode($identity['identity_id'])
            . '&body=' . rawurlencode("Reply body\n#like\n")
        );

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        assertStringContains('status=ok', $response);
        assertStringNotContains('Score: 0', $threadPage);
        assertStringContains('Score-Total: 0', $threadApi);
    }

    public function testApprovedFlagTagSubtractsFromScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=root-001&parent_id=root-001&author_identity_id='
            . rawurlencode('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
            . '&body=' . rawurlencode("Reply body\n#flag\n")
        );

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');
        $createdPostId = $this->extractValue($response, 'post_id');
        $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($createdPostId));

        assertStringContains('status=ok', $response);
        assertStringNotContains('Score: -100', $threadPage);
        assertStringContains('Post ' . $createdPostId, $postPage);
        assertStringContains('Score-Total: -100', $threadApi);
    }

    public function testBoardSupportsLikedViewAndNewestOldestTopSorts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $newThreadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Brand%20New%20Thread&body=Thread%20body'
        );
        $newThreadId = $this->extractValue($newThreadResponse, 'thread_id');
        $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Unliked%20Thread&body=Thread%20body'
        );

        $_COOKIE = ['identity_hint' => 'guest'];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=' . rawurlencode($newThreadId) . '&tag=like');

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $this->approveIdentity(new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)), $alice);
        $_COOKIE = ['identity_hint' => 'alice'];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $boardDefault = $this->renderMethod($application, 'GET', '/');
        $boardLikedNewest = $this->renderMethod($application, 'GET', '/?view=liked&sort=newest');
        $boardLikedOldest = $this->renderMethod($application, 'GET', '/?view=liked&sort=oldest');
        $boardLikedTop = $this->renderMethod($application, 'GET', '/?view=liked&sort=top');

        assertStringNotContains('View: All', $boardDefault);
        assertStringNotContains('Sort: Newest', $boardDefault);
        assertStringNotContains('View: Liked', $boardLikedNewest);
        assertStringNotContains('Sort: Newest', $boardLikedNewest);
        assertOrdered($boardLikedNewest, 'Brand New Thread', 'Hello world');
        assertOrdered($boardLikedOldest, 'Hello world', 'Brand New Thread');
        assertOrdered($boardLikedTop, 'Hello world', 'Brand New Thread');
        assertStringNotContains('Unliked Thread', $boardLikedNewest);
    }

    public function testPinnedThreadsSortBeforeNormalThreadsForBoardSorts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Newer%20Normal%20Thread&body=Thread%20body'
        );

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $this->approveIdentity(new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)), $alice);
        $_COOKIE = ['identity_hint' => 'alice'];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $boardNewest = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=newest');
        $boardOldest = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=oldest');
        $boardTop = $this->renderMethod($application, 'GET', '/threads/?view=all&sort=top');

        assertOrdered($boardNewest, 'The Rules of ZenMemes.com', 'Newer Normal Thread');
        assertOrdered($boardOldest, 'The Rules of ZenMemes.com', 'Hello world');
        assertOrdered($boardTop, 'The Rules of ZenMemes.com', 'Hello world');
    }

    public function testLikedViewIncludesUnapprovedLikesBecauseItFiltersLabelsNotScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $identity = $this->linkGeneratedIdentity($application, 'pending-user');
        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Pending%20Liked%20Thread&body=Thread%20body'
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');

        $_COOKIE = ['identity_hint' => $identity['identity_id']];
        $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=' . rawurlencode($threadId) . '&tag=like');
        $_COOKIE = [];

        $boardLiked = $this->renderMethod($application, 'GET', '/?view=liked&sort=newest');

        assertStringContains('Pending Liked Thread', $boardLiked);
    }

    public function testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $identity = $this->linkGeneratedIdentity($application, 'permalink-upvoter');

            $threadResponse = $this->renderMethod(
                $application,
                'POST',
                '/api/create_quote?board_tags=general&subject=&body=Quote%20to%20upvote'
            );
            $threadId = $this->extractValue($threadResponse, 'thread_id');

            $_COOKIE = ['identity_hint' => $identity['identity_id']];
            $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=' . rawurlencode($threadId) . '&tag=upvote');
            $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=' . rawurlencode($threadId) . '&tag=flag');
            $votedPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($threadId));
            $numericPath = '/' . \ForumRewrite\Qdb\QdbQuoteNumbers::fromThreadId($threadId);
            $numericPage = $this->renderMethod($application, 'GET', $numericPath);
            assertSame(200, http_response_code());
            assertSame(2, preg_match_all('/data-action="apply-thread-tag"[^>]*aria-pressed="true"[^>]*disabled="disabled"/', $numericPage));
            assertSame(1, preg_match('/data-action="apply-post-tag"[^>]*data-tag="flag"[^>]*aria-pressed="true"/', $numericPage));
            $_COOKIE = [];
            $anonymousPage = $this->renderMethod($application, 'GET', $numericPath);
            assertSame(200, http_response_code());
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertSame(2, preg_match_all('/data-action="apply-thread-tag"[^>]*aria-pressed="true"[^>]*disabled="disabled"/', $votedPage));
        assertSame(2, preg_match_all('/data-action="apply-thread-tag"[^>]*aria-pressed="false"/', $anonymousPage));
        assertSame(0, preg_match_all('/data-action="apply-thread-tag"[^>]*disabled="disabled"/', $anonymousPage));
    }

    public function testQdbLegacyLikeCountsTowardVoteTotalOnIncrementalAndRebuildPaths(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->renderMethod(new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot), 'GET', '/');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        putenv('FORUM_SITE_ID=qdb');
        try {
            $service->applyThreadTag([
                'thread_id' => 'root-001',
                'tag' => 'like',
                'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
            ]);
            $incrementalVoteCount = $this->threadVoteCount($databasePath, 'root-001');

            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot), 'qdb-like-parity-test'))->rebuild();
            $rebuiltVoteCount = $this->threadVoteCount($databasePath, 'root-001');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertSame(1, $incrementalVoteCount);
        assertSame(1, $rebuiltVoteCount);
    }

    public function testNonQdbLikeDoesNotCountTowardVoteTotal(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->renderMethod(new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot), 'GET', '/');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        $service->applyThreadTag([
            'thread_id' => 'root-001',
            'tag' => 'like',
            'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
        ]);

        assertSame(0, $this->threadVoteCount($databasePath, 'root-001'));
    }

    private function threadVoteCount(string $databasePath, string $threadId): int
    {
        $stmt = (new ReadModelConnection($databasePath))->open()->prepare('SELECT vote_count FROM threads WHERE root_post_id = :id');
        $stmt->execute(['id' => $threadId]);

        return (int) $stmt->fetchColumn();
    }

    public function testApplyThreadTagUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->applyThreadTag([
            'thread_id' => 'root-001',
            'tag' => 'like',
            'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
        ]);

        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        $this->assertUsedIncrementalUpdate($result);
        assertStringContains('Score-Total: 1', $threadApi);
    }

    public function testApplyThreadTagOnImportedQuoteAddsVoteOnTopOfImportedSeed(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();

        $postId = 'thread-20030613104735-qdb-999';
        $postDir = $repositoryRoot . '/records/posts/2003/06/13';
        mkdir($postDir, 0777, true);
        file_put_contents(
            $postDir . '/' . $postId . '.txt',
            "Post-ID: {$postId}\n"
            . "Created-At: 2003-06-13T10:47:35Z\n"
            . "Board-Tags: general\n"
            . "Imported-Score-Seed: 842\n"
            . "Imported-Vote-Count-Seed: 1200\n"
            . "\nImported historical quote body.\n"
        );
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add imported quote fixture"');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->applyThreadTag([
            'thread_id' => $postId,
            'tag' => 'upvote',
            'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
        ]);

        $this->assertUsedIncrementalUpdate($result);

        $pdo = new PDO('sqlite:' . $databasePath);
        $stmt = $pdo->prepare('SELECT score_total, vote_count FROM threads WHERE root_post_id = :root_post_id');
        $stmt->execute(['root_post_id' => $postId]);
        $row = $stmt->fetch();

        assertSame('843', (string) $row['score_total']);
        assertSame('1201', (string) $row['vote_count']);
    }

    public function testApplyPostTagUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $service->applyPostTag([
            'post_id' => 'reply-001',
            'tag' => 'flag',
            'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
        ]);

        $pdo = new PDO('sqlite:' . $databasePath);
        $row = $pdo->query("SELECT post_tags_json, post_score_total, approved_flag_count, is_hidden FROM posts WHERE post_id = 'reply-001'")->fetch();
        $activityRow = $pdo->query("SELECT kind, record_family, post_id, source_path FROM activity WHERE kind = 'post_reaction_add' AND post_id = 'reply-001' ORDER BY id DESC LIMIT 1")->fetch();

        $this->assertUsedIncrementalUpdate($result);
        assertSame('reply-001', $result['post_id']);
        assertSame('root-001', $result['thread_id']);
        assertSame('-100', $result['post_score_total']);
        assertSame('1', $result['approved_flag_count']);
        assertSame('no', $result['is_hidden']);
        assertSame('["flag"]', $row['post_tags_json']);
        assertSame('-100', (string) $row['post_score_total']);
        assertSame('1', (string) $row['approved_flag_count']);
        assertSame('0', (string) $row['is_hidden']);
        assertSame('post_reaction_add', $activityRow['kind']);
        assertSame('post_reaction', $activityRow['record_family']);
        assertSame('reply-001', $activityRow['post_id']);
        assertStringContains('records/post-reactions/post-reaction-', $activityRow['source_path']);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-reaction-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);
        $freshActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=all');
        assertStringContains('post_reaction_add', $freshActivity);
        assertStringContains('records/post-reactions/', $freshActivity);
    }

    public function testApplyThreadTagApiWritesApprovedLikeAndReportsUpdatedScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=like', $response);
        assertStringContains('score_total=1', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringNotContains('Score: 1', $threadPage);
        assertStringContains('>Liked</button>', $threadPage);
        assertStringContains('aria-pressed="true"', $threadPage);
        assertStringNotContains('Set up or choose an identity', $threadPage);
    }

    public function testApplyPostTagApiWritesApprovedFlagAndReportsPostState(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('post_id=reply-001', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=flag', $response);
        assertStringContains('post_score_total=-100', $response);
        assertStringContains('approved_flag_count=1', $response);
        assertStringContains('is_hidden=no', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('>Flagged</button>', $threadPage);
        assertStringContains('data-action="apply-post-tag"', $threadPage);
    }

    public function testSignedReactionApiVerifiesCommentLikeAndRetainsBothTimes(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $key = $this->createSigningKey('offline-like');

        try {
            $_POST = ['public_key' => $key['public_key']];
            $identityResponse = $this->renderMethod($application, 'POST', '/api/link_identity');
            $_POST = [];
            $identityId = $this->extractValue($identityResponse, 'identity_id');
            $canonical = "Schema: Forum Offline Intent v1\n"
                . "Intent-ID: outbox-reaction-signed-like-1\n"
                . "Action: reaction\n"
                . "Target-Kind: post\n"
                . "Target-ID: reply-001\n"
                . "Action-At: 2026-10-01T12:00:00.000Z\n"
                . "Author-Identity-ID: {$identityId}\n"
                . "Payload: {\"postId\":\"reply-001\",\"tag\":\"like\"}\n";
            $_POST = [
                'canonical_record' => $canonical,
                'detached_signature' => $this->signCanonicalRecord($key['home'], $canonical),
            ];
            $response = $this->renderMethod($application, 'POST', '/api/apply_signed_reaction');
            $_POST = [];

            assertStringContains('status=ok', $response);
            assertStringContains('post_id=reply-001', $response);
            assertStringContains('tag=like', $response);
            assertStringContains('action_at=2026-10-01T12:00:00.000Z', $response);
            assertTrue($this->extractValue($response, 'integration_at') !== '');

            $recordPaths = glob($repositoryRoot . '/records/post-reactions/*.txt') ?: [];
            $record = (string) file_get_contents($recordPaths[0]);
            assertStringContains('Action-At: 2026-10-01T12:00:00.000Z', $record);
            assertStringContains('Intent-ID: outbox-reaction-signed-like-1', $record);
            assertStringContains("Target-Kind: post\nTarget-ID: reply-001\nOperation: add\nTags: like\n", $record);
        } finally {
            $_POST = [];
            $this->deleteTree($key['home']);
        }
    }

    public function testApplyPostTagApiWritesApprovedCommentLikeAndRendersLikedButton(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $recordsBefore = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=like');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('post_id=reply-001', $response);
        assertStringContains('thread_id=root-001', $response);
        assertStringContains('tag=like', $response);
        assertStringContains('post_score_total=1', $response);
        assertStringContains('approved_flag_count=0', $response);
        assertStringContains('is_hidden=no', $response);
        assertStringContains('viewer_is_approved=yes', $response);
        assertStringContains('wrote_record=yes', $response);
        assertTrue(strlen($this->extractValue($response, 'commit_sha')) === 40);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('data-post-id="reply-001"', $threadPage);
        assertStringContains('data-tag="like"', $threadPage);
        assertStringContains('>Liked</button>', $threadPage);
        assertStringContains('aria-pressed="true"', $threadPage);
    }

    public function testApplyPostTagApiDuplicateShortCircuitsWithoutNewRecord(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $first = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $recordsAfterFirst = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);
        $second = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $_COOKIE = [];

        $recordsAfterSecond = count(glob($repositoryRoot . '/records/post-reactions/*.txt') ?: []);

        assertStringContains('wrote_record=yes', $first);
        assertStringContains('wrote_record=no', $second);
        assertStringNotContains('commit_sha=', $second);
        assertSame($recordsAfterFirst, $recordsAfterSecond);
    }

    public function testApplyPostTagApiRejectsAnonymousViewerAndUnknownTag(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $anonymous = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=flag');
        $_COOKIE = ['identity_hint' => 'guest'];
        $unknownTag = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=reply-001&tag=bug');
        $_COOKIE = [];

        assertStringContains('error=You must set an identity hint before applying a tag.', $anonymous);
        assertStringContains('error=tag must be one of: like, flag', $unknownTag);
    }

    public function testApprovedFlagOnReplyAgentPostHidesPublicSurfaces(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $previousLlmProviderEnv = $this->useStubLlmProvider();

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $postId = $this->createAnalyzedThread($application, $databasePath);
            $_COOKIE = ['identity_hint' => 'guest'];
            json_decode($this->renderMethod($application, 'POST', '/api/generate_agent_reply?post_id=' . rawurlencode($postId)), true);
            $_COOKIE = [];
            $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite:' . $databasePath));
            $reply = $application->fulfillAgentReplyRequest($store->claimRequestedForPost($postId));
            $agentPostId = (string) $reply['agent_post_id'];

            $_COOKIE = ['identity_hint' => 'guest'];
            $flagResponse = $this->renderMethod($application, 'POST', '/api/apply_post_tag?post_id=' . rawurlencode($agentPostId) . '&tag=flag');
            $_COOKIE = [];

            $threadPage = $this->renderMethod($application, 'GET', '/threads/' . rawurlencode($postId));
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($agentPostId));
            $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=' . rawurlencode($postId));
            $postApi = $this->renderMethod($application, 'GET', '/api/get_post?post_id=' . rawurlencode($agentPostId));
            $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');

            assertStringContains('is_hidden=yes', $flagResponse);
            assertStringNotContains('strongest reason', $threadPage);
            assertStringContains('This post has been hidden.', $postPage);
            assertStringNotContains($agentPostId, $threadApi);
            assertStringContains('post not found', $postApi);
            assertStringNotContains($agentPostId, $activity);
        } finally {
            $this->restoreLlmProviderEnv($previousLlmProviderEnv);
            $_COOKIE = [];
        }
    }

    public function testApplyThreadTagApiDuplicateShortCircuitsWithoutNewRecord(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $first = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $recordsAfterFirst = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);
        $second = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $_COOKIE = [];

        $recordsAfterSecond = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);
        $threadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');

        assertStringContains('wrote_record=yes', $first);
        assertStringContains('score_total=1', $first);
        assertStringContains('wrote_record=no', $second);
        assertStringContains('score_total=1', $second);
        assertStringNotContains('commit_sha=', $second);
        assertSame($recordsAfterFirst, $recordsAfterSecond);
        assertStringContains('Score-Total: 1', $threadApi);
    }

    public function testApplyThreadTagApiAllowsUnapprovedUserButDoesNotChangeScore(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $identity = $this->linkGeneratedIdentity($application, 'alice');
        $recordsBefore = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        $_COOKIE = ['identity_hint' => 'alice'];
        $response = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $_COOKIE = [];

        $recordsAfter = count(glob($repositoryRoot . '/records/thread-labels/*.txt') ?: []);

        assertStringContains('status=ok', $response);
        assertStringContains('viewer_identity_id=' . $identity['identity_id'], $response);
        assertStringContains('viewer_is_approved=no', $response);
        assertStringContains('score_total=0', $response);
        assertStringContains('wrote_record=yes', $response);
        assertSame($recordsBefore + 1, $recordsAfter);
        assertStringContains('>Liked</button>', $threadPage);
        assertStringNotContains('start affecting score once you are approved.', $threadPage);
    }

    public function testApplyThreadTagApiRejectsAnonymousViewerAndUnknownTag(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = [];
        $anonymous = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=like');

        $_COOKIE = ['identity_hint' => 'guest'];
        $unknownTag = $this->renderMethod($application, 'POST', '/api/apply_thread_tag?thread_id=root-001&tag=bug');
        $_COOKIE = [];

        assertStringContains('error=You must set an identity hint before applying a tag.', $anonymous);
        assertStringContains('error=tag must be one of: like, flag', $unknownTag);
    }

    public function testIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyPostWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated incremental failure');
                    }
                };
            }
        };

        $result = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Fallback Thread',
            'body' => 'Fallback body',
        ]);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $result['thread_id']);
        assertSame(true, isset($result['timings']['read_model_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_rebuild_fallback']));
        assertStringContains('Fallback Thread', $threadPage);
        $this->assertReadModelHealthy($databasePath);
    }

    public function testLabeledWriteIncrementalLabelFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyThreadLabelWrite(string $threadId, string $commitSha): array
                    {
                        throw new RuntimeException('simulated thread-label incremental failure');
                    }
                };
            }
        };

        $result = $service->createReply([
            'thread_id' => 'root-001',
            'parent_id' => 'root-001',
            'board_tags' => 'general',
            'body' => "Fallback labeled reply\n#answered\n",
        ]);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        $activity = $this->renderMethod($application, 'GET', '/activity/?view=content');
        assertSame(true, isset($result['timings']['read_model_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_rebuild_fallback']));
        assertStringContains('Fallback labeled reply', $threadPage);
        assertStringContains('Labels: answered, bug, needs-review', $threadPage);
        assertStringContains('thread_label_add', $activity);
        assertStringContains('Labels added: answered', $activity);
        $this->assertReadModelHealthy($databasePath);
    }

    public function testIdentityIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyIdentityLink(\ForumRewrite\Canonical\IdentityBootstrapRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated identity incremental failure with private material');
                    }
                };
            }
        };

        $result = $service->linkIdentity([
            'public_key' => $this->readFixturePublicKey(),
            'bootstrap_post_id' => 'root-001',
        ]);

        $profilePage = $this->renderMethod($application, 'GET', '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        assertSame(true, isset($result['timings']['read_model_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_rebuild_fallback']));
        assertSame('full_rebuild', $result['identity_update_metrics']['path']);
        assertSame('incremental_update_failed', $result['identity_update_metrics']['fallback_reason']);
        assertStringNotContains('private material', json_encode($result['identity_update_metrics']));
        assertStringContains('<h1>forum-user</h1>', $profilePage);
        $this->assertReadModelHealthy($databasePath);
    }

    public function testIncrementalThreadAndReplyMatchFreshRebuildView(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $identity = $this->linkGeneratedIdentity($application, 'compare-user');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $threadResult = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Parity Thread',
            'body' => 'Parity body',
            'author_identity_id' => $identity['identity_id'],
        ]);
        $replyResult = $service->createReply([
            'thread_id' => $threadResult['thread_id'],
            'parent_id' => $threadResult['thread_id'],
            'board_tags' => 'general',
            'body' => "Parity reply\n#parity\n",
            'author_identity_id' => $identity['identity_id'],
        ]);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalBoard = $this->renderMethod($application, 'GET', '/');
        $freshBoard = $this->renderMethod($freshApplication, 'GET', '/');
        $incrementalThread = $this->renderMethod($application, 'GET', '/threads/' . $threadResult['thread_id']);
        $freshThread = $this->renderMethod($freshApplication, 'GET', '/threads/' . $threadResult['thread_id']);
        $incrementalReply = $this->renderMethod($application, 'GET', '/posts/' . $replyResult['post_id']);
        $freshReply = $this->renderMethod($freshApplication, 'GET', '/posts/' . $replyResult['post_id']);
        $incrementalUser = $this->renderMethod($application, 'GET', '/user/compare-user');
        $freshUser = $this->renderMethod($freshApplication, 'GET', '/user/compare-user');
        $incrementalActivity = $this->renderMethod($application, 'GET', '/activity/?view=content');
        $freshActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=content');

        assertSame($this->normalizeRenderedPage($freshBoard), $this->normalizeRenderedPage($incrementalBoard));
        assertSame($this->normalizeRenderedPage($freshThread), $this->normalizeRenderedPage($incrementalThread));
        assertSame($this->normalizeRenderedPage($freshReply), $this->normalizeRenderedPage($incrementalReply));
        assertSame($this->normalizeRenderedPage($freshUser), $this->normalizeRenderedPage($incrementalUser));
        assertSame($this->normalizeRenderedPage($freshActivity), $this->normalizeRenderedPage($incrementalActivity));
    }

    public function testIncrementalIdentityLinkMatchesFreshRebuildView(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $identity = $this->linkGeneratedIdentity($application, 'compare-user');
        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-identity-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalProfile = $this->renderMethod($application, 'GET', '/profiles/' . $identity['profile_slug']);
        $freshProfile = $this->renderMethod($freshApplication, 'GET', '/profiles/' . $identity['profile_slug']);
        $incrementalUser = $this->renderMethod($application, 'GET', '/user/compare-user');
        $freshUser = $this->renderMethod($freshApplication, 'GET', '/user/compare-user');
        $incrementalPost = $this->renderMethod($application, 'GET', '/posts/' . $identity['bootstrap_post_id']);
        $freshPost = $this->renderMethod($freshApplication, 'GET', '/posts/' . $identity['bootstrap_post_id']);
        $incrementalActivity = $this->renderMethod($application, 'GET', '/activity/?view=bootstrap');
        $freshActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=bootstrap');

        assertSame($this->normalizeRenderedPage($freshProfile), $this->normalizeRenderedPage($incrementalProfile));
        assertSame($this->normalizeRenderedPage($freshUser), $this->normalizeRenderedPage($incrementalUser));
        assertSame($this->normalizeRenderedPage($freshPost), $this->normalizeRenderedPage($incrementalPost));
        assertSame($this->normalizeRenderedPage($freshActivity), $this->normalizeRenderedPage($incrementalActivity));
    }

    public function testWriteApiReportsGitFailureWithoutInvalidatingArtifacts(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
        ]);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=New%20Thread&body=Thread%20body'
        );

        assertStringContains('error=Writable repository must be a git checkout before writes are allowed.', $response);
        assertTrue(is_file($artifactRoot . '/index.html'));
    }

    public function testWriteMarksDerivedStateStaleWhenRefreshFailsAfterCommit(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, ['/index.html']);
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function rebuildReadModel(): array
            {
                throw new RuntimeException('simulated refresh failure');
            }
        };

        try {
            $service->createThread([
                'board_tags' => 'general',
                'subject' => 'New Thread',
                'body' => 'Thread body',
            ]);
            throw new RuntimeException('Expected refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $this->assertReadModelMarkedStale($databasePath);
        assertTrue(is_file($artifactRoot . '/index.html'));
    }

    public function testIdentityWriteMarksDerivedStateStaleWhenIncrementalAndFallbackRefreshFail(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyIdentityLink(\ForumRewrite\Canonical\IdentityBootstrapRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated identity incremental failure');
                    }
                };
            }

            protected function rebuildReadModel(): array
            {
                throw new RuntimeException('simulated identity rebuild failure');
            }
        };

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        try {
            $service->linkIdentity([
                'public_key' => $this->readFixturePublicKey(),
                'bootstrap_post_id' => 'root-001',
            ]);
            throw new RuntimeException('Expected identity refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $this->assertReadModelMarkedStale($databasePath);
    }

    public function testLinkIdentityAllowsDuplicateUsernameTokensWithoutBreakingRebuild(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $firstResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $secondResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $status = $this->renderMethod($application, 'GET', '/api/read_model_status');
        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('status=ok', $firstResponse);
        assertStringContains('status=ok', $secondResponse);
        assertStringContains('status=ready', $status);
        assertStringContains('<h1 class="account-name">forum-user</h1>', $usernameRoute);
        assertStringContains('No approved member uses this name.', $usernameRoute);
        assertStringNotContains('Unapproved Profiles', $usernameRoute);
    }

    public function testUsernameRouteShowsUnapprovedProfilesWhenNoApprovedMatchesExist(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('<h1 class="account-name">forum-user</h1>', $usernameRoute);
        assertStringContains('No approved member uses this name.', $usernameRoute);
        assertStringNotContains('Unapproved Profiles', $usernameRoute);
        // Manage access is shown to operators only.
        assertStringNotContains('/tools/user-access/accounts/forum-user', $usernameRoute);
    }

    public function testApprovedUserCanApproveAnotherUserAndApprovalStaysOutOfFeeds(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $targetProfileSlug = $target['profile_slug'];
        $targetBootstrapThreadId = $target['bootstrap_thread_id'];
        $targetBootstrapPostId = $target['bootstrap_post_id'];

        $_COOKIE = ['identity_hint' => 'guest'];
        $profilePage = $this->renderMethod($application, 'GET', '/tools/user-access/profiles/' . $targetProfileSlug);

        $this->seedArtifacts($artifactRoot, [
            '/profiles/' . $targetProfileSlug . '.html',
            '/user/alice.html',
            '/forte/user/alice.html',
            '/forte/profiles/' . $targetProfileSlug . '.html',
        ]);
        $postIdsBefore = $this->listCanonicalPostIds($repositoryRoot);
        $approvalResult = $this->approveIdentity(
            new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)),
            $target
        );
        $postIdsAfter = $this->listCanonicalPostIds($repositoryRoot);
        $approvalPostIds = array_values(array_diff($postIdsAfter, $postIdsBefore));
        sort($approvalPostIds);
        $approvalPostId = $approvalPostIds[0] ?? '';

        $approvalLanding = $this->renderMethod(
            $application,
            'GET',
            '/profiles/' . rawurlencode($targetProfileSlug) . '?approval=success&post_id=' . rawurlencode($approvalPostId)
                . '&commit=' . rawurlencode($approvalResult['commit_sha'])
        );
        $targetProfile = $this->renderMethod($application, 'GET', '/tools/user-access/profiles/' . $targetProfileSlug);
        $targetProfileApi = $this->renderMethod($application, 'GET', '/api/get_profile?profile_slug=' . rawurlencode($targetProfileSlug));
        $board = $this->renderMethod($application, 'GET', '/');
        $activity = $this->renderMethod($application, 'GET', '/activity/?view=all');
        $identityActivity = $this->renderMethod($application, 'GET', '/activity/?view=identity');
        $approvalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
        $bootstrapThread = $this->renderMethod($application, 'GET', '/threads/' . $targetBootstrapThreadId);
        $_COOKIE = [];

        foreach (['/tools/user-access/accounts/alice', '/forte/user/alice', '/tools/user-access/profiles/' . $targetProfileSlug, '/forte/profiles/' . $targetProfileSlug] as $route) {
            $historyPage = $this->renderMethod($application, 'GET', $route);
            assertStringContains(str_contains($route, '/profiles/') ? 'Key approval' : 'Account key history', $historyPage);
            assertStringContains('New account', $historyPage);
            assertStringContains('Authority: root-approved operator', $historyPage);
            assertStringContains('Accepted: <time', $historyPage);
            assertStringContains('/source/current/records/approval-acceptances/', $historyPage);
        }
        assertStringContains('Approval-Classification: new_account', $targetProfileApi);
        foreach (['/user/alice.html', '/forte/user/alice.html', '/forte/profiles/' . $targetProfileSlug . '.html'] as $artifact) {
            assertSame(false, is_file($artifactRoot . $artifact));
        }
        $receiptPath = \ForumRewrite\Security\ApprovalAcceptance::path($approvalPostId);
        $receiptPage = $this->renderMethod($application, 'GET', '/source/current/' . $receiptPath);
        assertStringContains('Context-Classification: new_account', $receiptPage);
        assertStringContains('Approve new member alice', $profilePage);
        assertSame(1, count($approvalPostIds));
        assertStringContains('Approved user alice', $approvalLanding);
        assertStringContains('/posts/' . $approvalPostId, $approvalLanding);
        assertStringContains('Approved: yes', $targetProfileApi);
        assertStringContains('Approved-By: guest', $targetProfileApi);
        assertStringContains('Approved:</strong> yes', $targetProfile);
        assertStringContains('Approved by:</strong>', $targetProfile);
        assertStringContains('/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $targetProfile);
        assertStringContains('>guest</a>', $targetProfile);
        assertStringNotContains('data-signed-approval-form', $approvalLanding);
        assertStringNotContains('data-signed-approval-form', $targetProfile);
        assertStringNotContains($approvalPostId, $board);
        assertStringContains($approvalPostId, $activity);
        assertStringContains('approval', $activity);
        assertStringContains($approvalPostId, $identityActivity);
        assertStringContains($approvalPostId, $approvalActivity);
        assertStringContains($approvalPostId, $bootstrapThread);
        assertStringContains('Approve-Identity-ID: ' . $target['identity_id'], $bootstrapThread);
    }

    public function testUnapprovedUserCannotApproveAndSelfApprovalIsRejected(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $bob = $this->linkGeneratedIdentity($application, 'bob');

        $_COOKIE = ['identity_hint' => 'alice'];
        $unapprovedResponse = $this->renderMethod($application, 'POST', '/profiles/' . $bob['profile_slug'] . '/approve');

        $_COOKIE = ['identity_hint' => 'guest'];
        $selfResponse = $this->renderMethod(
            $application,
            'POST',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954/approve'
        );
        $_COOKIE = [];

        assertStringContains('Approval requires a browser signature.', $unapprovedResponse);
        assertStringContains('Approval requires a browser signature.', $selfResponse);
    }

    public function testApprovedViewerResolvesFromIdentityIdAndProfileSlugHints(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $target = $this->linkGeneratedIdentity($application, 'alice');

        $_COOKIE = ['identity_hint' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'];
        $byIdentityId = $this->renderMethod($application, 'GET', '/tools/user-access/profiles/' . $target['profile_slug']);

        $_COOKIE = ['identity_hint' => 'openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'];
        $byProfileSlug = $this->renderMethod($application, 'GET', '/tools/user-access/profiles/' . $target['profile_slug']);
        $_COOKIE = [];

        assertStringContains('Approve new member alice', $byIdentityId);
        assertStringContains('Approve new member alice', $byProfileSlug);
        assertStringContains('data-signed-approval-form', $byIdentityId);
        assertStringContains('/assets/browser_signing.', $byIdentityId);
        assertStringContains('/assets/pending_approvals.', $byIdentityId);
    }

    public function testUserDirectoryShowsOnlyApprovedUsersAndPendingDirectoryRequiresApprovedViewer(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $approvedUsersWithoutPending = $this->renderMethod($application, 'GET', '/users/');

        $approvedTarget = $this->linkGeneratedIdentity($application, 'alice');
        $pendingTarget = $this->linkGeneratedIdentity($application, 'bob');

        $this->approveIdentity(
            new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)),
            $approvedTarget
        );
        $approvedUsers = $this->renderMethod($application, 'GET', '/users/');
        $pendingUsers = $this->renderMethod($application, 'GET', '/tools/user-access/pending/');

        $_COOKIE = ['identity_hint' => 'bob'];
        $pendingUsersForbidden = $this->renderMethod($application, 'GET', '/tools/user-access/pending/');
        $_COOKIE = [];

        assertStringNotContains('/users/pending/', $approvedUsersWithoutPending);
        assertStringContains('alice', $approvedUsers);
        assertStringNotContains('bob', $approvedUsers);
        assertStringContains('/user/alice', $approvedUsers);
        assertStringNotContains('Profile:', $approvedUsers);
        assertStringNotContains('Username route:', $approvedUsers);
        assertStringNotContains('/users/pending/', $approvedUsers);
        assertStringContains('Users Awaiting Approval', $pendingUsers);
        assertStringContains('bob', $pendingUsers);
        assertStringNotContains('alice', $pendingUsers);
        assertStringContains('title="' . $pendingTarget['profile_slug'] . '"', $pendingUsers);
        assertStringContains('aria-label="' . $pendingTarget['profile_slug'] . '"', $pendingUsers);
        assertStringContains(
            substr($pendingTarget['profile_slug'], 0, 17) . '...',
            $pendingUsers
        );
        assertStringNotContains('>' . $pendingTarget['profile_slug'] . '</a>', $pendingUsers);
        assertStringContains('<table ', $pendingUsers);
        assertStringMatches(
            '#<tr[^>]+data-username="bob"[^>]*>(?:(?!</tr>).)*href="/posts/'
            . preg_quote($pendingTarget['bootstrap_post_id'], '#')
            . '"(?:(?!</tr>).)*data-action="approve-user"[^>]+data-profile-slug="'
            . preg_quote($pendingTarget['profile_slug'], '#') . '"(?:(?!</tr>).)*</tr>#s',
            $pendingUsers
        );
        assertStringContains('account bootstrap', $pendingUsers);
        assertStringContains('href="/posts/' . $pendingTarget['bootstrap_post_id'] . '"', $pendingUsers);
        assertStringContains('<time datetime="', $pendingUsers);
        assertStringNotContains('Latest activity:', $pendingUsers);
        assertStringContains('Approve', $pendingUsers);
        assertStringContains('/assets/browser_signing.', $pendingUsers);
        assertStringContains('/assets/pending_approvals.', $pendingUsers);
        assertStringNotContains('Username route', $pendingUsers);
        assertStringNotContains('Threads', $pendingUsers);
        assertStringNotContains('Posts', $pendingUsers);
        assertStringNotContains('Bootstrap thread', $pendingUsers);
        assertStringContains('Only approved users can view pending approvals.', $pendingUsersForbidden);
    }

    public function testPendingDirectoryRendersLaterActivityWithReadingFriendlyTimestampAndFallback(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');
        $target = $this->linkGeneratedIdentity($application, 'bob');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $laterActivity = $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Later pending activity',
            'body' => 'This pending profile has newer activity.',
            'author_identity_id' => $target['identity_id'],
        ]);

        $_COOKIE = ['identity_hint' => 'guest'];
        $pendingUsers = $this->renderMethod($application, 'GET', '/tools/user-access/pending/');
        $_COOKIE = [];

        assertStringContains('Later pending activity', $pendingUsers);
        assertStringContains('href="/posts/' . $laterActivity['post_id'] . '"', $pendingUsers);
        assertStringMatches('#<time datetime="[^"]+" title="[^"]+">(?:just now|\d+ minutes? ago)</time>#', $pendingUsers);
        assertStringNotContains('Latest activity:', $pendingUsers);

        $pdo = new PDO('sqlite:' . $databasePath);
        $delete = $pdo->prepare(
            'DELETE FROM activity WHERE author_identity_id = :identity_id OR post_id = :bootstrap_post_id'
        );
        $delete->execute([
            'identity_id' => $target['identity_id'],
            'bootstrap_post_id' => $target['bootstrap_post_id'],
        ]);

        $_COOKIE = ['identity_hint' => 'guest'];
        $fallback = $this->renderMethod($application, 'GET', '/tools/user-access/pending/');
        $_COOKIE = [];

        assertStringContains('No recorded activity', $fallback);
    }

    public function testPendingDirectoryProfilesIncludeBootstrapActivitySummary(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');
        $target = $this->linkGeneratedIdentity($application, 'bob');

        $profiles = ProfileRepository::pendingDirectoryProfiles(new PDO('sqlite:' . $databasePath));
        $profile = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => $profile['profile_slug'] === $target['profile_slug'],
        ))[0] ?? null;

        assertTrue(is_array($profile));
        assertSame('account bootstrap', $profile['latest_activity_label']);
        assertTrue((string) $profile['latest_activity_at'] !== '');
        assertSame($target['bootstrap_post_id'], $profile['latest_activity_post_id']);
    }

    public function testPendingDirectoryProfilesPreferLaterActivityAndHandleMissingActivity(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');
        $target = $this->linkGeneratedIdentity($application, 'bob');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Pending later activity',
            'body' => 'This is the newer pending-user activity.',
            'author_identity_id' => $target['identity_id'],
        ]);

        $pdo = new PDO('sqlite:' . $databasePath);
        $summary = static function () use ($pdo, $target): ?array {
            $profiles = ProfileRepository::pendingDirectoryProfiles($pdo);

            return array_values(array_filter(
                $profiles,
                static fn (array $profile): bool => $profile['profile_slug'] === $target['profile_slug'],
            ))[0] ?? null;
        };

        $profile = $summary();
        assertTrue(is_array($profile));
        assertSame('Pending later activity', $profile['latest_activity_label']);

        $sameTimestamp = '2030-01-01T00:00:00Z';
        $update = $pdo->prepare(
            'UPDATE activity
             SET created_at = :created_at
             WHERE author_identity_id = :identity_id OR post_id = :bootstrap_post_id'
        );
        $update->execute([
            'created_at' => $sameTimestamp,
            'identity_id' => $target['identity_id'],
            'bootstrap_post_id' => $target['bootstrap_post_id'],
        ]);

        $profile = $summary();
        assertTrue(is_array($profile));
        assertSame('Pending later activity', $profile['latest_activity_label']);

        $delete = $pdo->prepare(
            'DELETE FROM activity WHERE author_identity_id = :identity_id OR post_id = :bootstrap_post_id'
        );
        $delete->execute([
            'identity_id' => $target['identity_id'],
            'bootstrap_post_id' => $target['bootstrap_post_id'],
        ]);

        $profile = $summary();
        assertTrue(is_array($profile));
        assertSame(null, $profile['latest_activity_label']);
        assertSame(null, $profile['latest_activity_at']);
        assertSame(null, $profile['latest_activity_post_id']);
    }

    public function testPendingDirectoryProfilesSortByLatestActivityWithMissingActivityLast(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');
        $oldest = $this->linkGeneratedIdentity($application, 'alice');
        $newest = $this->linkGeneratedIdentity($application, 'bob');
        $missing = $this->linkGeneratedIdentity($application, 'carol');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Older pending activity',
            'body' => 'This pending profile has older activity.',
            'author_identity_id' => $oldest['identity_id'],
        ]);
        $service->createThread([
            'board_tags' => 'general',
            'subject' => 'Newest pending activity',
            'body' => 'This pending profile has newer activity.',
            'author_identity_id' => $newest['identity_id'],
        ]);

        $pdo = new PDO('sqlite:' . $databasePath);
        $update = $pdo->prepare(
            'UPDATE activity
             SET created_at = :created_at
             WHERE author_identity_id = :identity_id OR post_id = :bootstrap_post_id'
        );
        foreach ([
            [$oldest, '2030-01-01T00:00:00Z'],
            [$newest, '2030-01-02T00:00:00Z'],
        ] as [$target, $createdAt]) {
            $update->execute([
                'created_at' => $createdAt,
                'identity_id' => $target['identity_id'],
                'bootstrap_post_id' => $target['bootstrap_post_id'],
            ]);
        }
        $delete = $pdo->prepare(
            'DELETE FROM activity WHERE author_identity_id = :identity_id OR post_id = :bootstrap_post_id'
        );
        $delete->execute([
            'identity_id' => $missing['identity_id'],
            'bootstrap_post_id' => $missing['bootstrap_post_id'],
        ]);

        $targets = [
            $oldest['profile_slug'],
            $newest['profile_slug'],
            $missing['profile_slug'],
        ];
        $profiles = array_values(array_filter(
            ProfileRepository::pendingDirectoryProfiles($pdo),
            static fn (array $profile): bool => in_array($profile['profile_slug'], $targets, true),
        ));

        assertSame(
            [$newest['profile_slug'], $oldest['profile_slug'], $missing['profile_slug']],
            array_column($profiles, 'profile_slug')
        );
    }

    public function testUnsignedApproveUserApiRequiresBrowserSignature(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $target = $this->linkGeneratedIdentity($application, 'alice');

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/approve_user?profile_slug=' . rawurlencode($target['profile_slug'])
        );
        $pendingUsersAfter = $this->renderMethod($application, 'GET', '/tools/user-access/pending/');
        $_COOKIE = [];

        assertStringContains('error=Approval requires a browser signature.', $response);
        assertStringContains('alice', $pendingUsersAfter);
    }

    public function testPrepareApprovalApiReturnsUnsignedCanonicalRecordWithoutChangingApprovalState(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $target = $this->linkGeneratedIdentity($application, 'alice');
        $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);

        $_COOKIE = ['identity_hint' => 'guest'];
        $response = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_approval?profile_slug=' . rawurlencode($target['profile_slug'])
        ), true);
        $targetProfile = $this->renderMethod($application, 'GET', '/api/get_profile?profile_slug=' . rawurlencode($target['profile_slug']));
        $_COOKIE = [];

        assertSame('ok', $response['status']);
        assertStringContains('By approving this user, you vouch for their conduct and integrity.', $response['approval_review']['consequence']);
        assertStringContains('This key will be able to act as alice.', $response['approval_review']['consequence']);
        assertStringContains('Board-Tags: identity approval', $response['canonical_record']);
        assertStringContains('Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $response['canonical_record']);
        assertStringContains('Approve-Identity-ID: ' . $target['identity_id'], $response['canonical_record']);
        assertSame(hash('sha256', $response['canonical_record']), $response['canonical_sha256']);
        assertTrue(is_file(dirname($databasePath) . '/prepared-posts/' . $response['prepare_token'] . '.json'));
        assertSame($postCountBefore, $this->countCanonicalPostFiles($repositoryRoot));
        assertStringContains('Approved: no', $targetProfile);
    }

    public function testPrepareApprovalApiRejectsIneligibleApprovers(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $target = $this->linkGeneratedIdentity($application, 'alice');
        $unapproved = $this->linkGeneratedIdentity($application, 'bob');

        $_COOKIE = ['identity_hint' => 'bob'];
        $unapprovedResponse = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_approval?profile_slug=' . rawurlencode($target['profile_slug'])
        ), true);
        $_COOKIE = ['identity_hint' => 'guest'];
        $selfResponse = json_decode($this->renderMethod(
            $application,
            'POST',
            '/api/prepare_approval?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        ), true);
        $_COOKIE = [];

        assertSame('error', $unapprovedResponse['status']);
        assertSame('Only approved users can approve other users.', $unapprovedResponse['error']);
        assertSame('error', $selfResponse['status']);
        assertSame('Self-approval is not allowed.', $selfResponse['error']);
        assertTrue($unapproved['identity_id'] !== $target['identity_id']);
    }

    public function testFinalizePreparedApprovalVerifiesSignatureBeforeCreatingApproval(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $signingKey = $this->createSigningKey('approval-signer');

        try {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
            $_POST = ['public_key' => $signingKey['public_key']];
            $signerResponse = $this->renderMethod($application, 'POST', '/api/link_identity');
            $_POST = [];
            $signerIdentityId = $this->extractValue($signerResponse, 'identity_id');
            $this->seedApprovedIdentity($repositoryRoot, $signerIdentityId);
            $target = $this->linkGeneratedIdentity($application, 'approval-target');
            $_COOKIE = ['identity_hint' => $signerIdentityId];
            $prepared = json_decode($this->renderMethod(
                $application,
                'POST',
                '/api/prepare_approval?profile_slug=' . rawurlencode($target['profile_slug'])
            ), true, 512, JSON_THROW_ON_ERROR);
            $postCountBefore = $this->countCanonicalPostFiles($repositoryRoot);

            $_POST = [
                'prepare_token' => $prepared['prepare_token'],
                'post_id' => $prepared['post_id'],
                'record_path' => $prepared['record_path'],
                'author_identity_id' => $signerIdentityId,
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => 'invalid detached signature',
            ];
            $invalid = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_approval'), true, 512, JSON_THROW_ON_ERROR);
            $_POST = [];

            assertSame('error', $invalid['status']);
            assertStringContains('Detached signature verification failed:', $invalid['error']);
            assertSame($postCountBefore, $this->countCanonicalPostFiles($repositoryRoot));
            assertTrue(is_file(dirname($databasePath) . '/prepared-posts/' . $prepared['prepare_token'] . '.json'));

            $signature = $this->signCanonicalRecord($signingKey['home'], $prepared['canonical_record']);
            $_POST = [
                'prepare_token' => $prepared['prepare_token'],
                'post_id' => $prepared['post_id'],
                'record_path' => $prepared['record_path'],
                'author_identity_id' => $signerIdentityId,
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => $signature,
            ];
            $hook = $repositoryRoot . '/.git/hooks/pre-commit';
            file_put_contents($hook, "#!/bin/sh\nexit 1\n");
            chmod($hook, 0700);
            $failedCommit = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_approval'), true, 512, JSON_THROW_ON_ERROR);
            unlink($hook);
            assertSame('error', $failedCommit['status']);
            assertSame(false, is_file($repositoryRoot . '/' . $prepared['record_path']));
            assertSame(false, is_file($repositoryRoot . '/' . $prepared['record_path'] . '.asc'));
            assertSame([], glob($repositoryRoot . '/records/approval-acceptances/*') ?: []);
            $retryPayload = $_POST;
            $finalized = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_approval'), true, 512, JSON_THROW_ON_ERROR);
            $_POST = $retryPayload;
            $recovered = json_decode($this->renderMethod($application, 'POST', '/api/create_prepared_approval'), true, 512, JSON_THROW_ON_ERROR);
            assertSame(true, $recovered['recovered']);
            assertSame($postCountBefore + 1, $this->countCanonicalPostFiles($repositoryRoot));
            $receipt = \ForumRewrite\Security\ApprovalAcceptance::read($repositoryRoot, $prepared['record_path']);
            assertSame('direct', $receipt['kind']);
            assertSame($target['identity_id'], $receipt['context']['target_identity_id']);
            $_POST = [];
            $targetProfile = $this->renderMethod($application, 'GET', '/api/get_profile?profile_slug=' . rawurlencode($target['profile_slug']));
            $postPage = $this->renderMethod($application, 'GET', '/posts/' . rawurlencode($prepared['post_id']));
            $classicApprovalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
            $forteApprovalActivity = $this->renderMethod($application, 'GET', '/forte/activity/?view=approval');
            $fingerprint = strtoupper(substr($signerIdentityId, strlen('openpgp:')));
            $publicKeyPath = 'records/public-keys/openpgp-' . $fingerprint . '.asc';

            assertSame('ok', $finalized['status']);
            assertSame($prepared['record_path'] . '.asc', $finalized['signature_path']);
            assertSame($prepared['canonical_record'], (string) file_get_contents($repositoryRoot . '/' . $prepared['record_path']));
            assertSame($signature, (string) file_get_contents($repositoryRoot . '/' . $prepared['record_path'] . '.asc'));
            assertFalse(is_file(dirname($databasePath) . '/prepared-posts/' . $prepared['prepare_token'] . '.json'));
            assertStringContains('Approved: yes', $targetProfile);
            assertStringContains('/source/current/' . $prepared['record_path'] . '.asc', $postPage);
            assertStringContains('/source/current/' . $publicKeyPath, $postPage);
            assertTrue(preg_match('/Signer:\s+' . preg_quote($signerIdentityId, '/') . '/', $classicApprovalActivity) === 1);
            assertTrue(preg_match('/Signer:\s+' . preg_quote($signerIdentityId, '/') . '/', $forteApprovalActivity) === 1);
            assertStringContains('/source/current/' . $publicKeyPath, $classicApprovalActivity);
            assertStringContains('/source/current/' . $publicKeyPath, $forteApprovalActivity);
            $committedFiles = $this->gitOutput($repositoryRoot, 'show --name-only --format= ' . escapeshellarg($finalized['commit_sha']));
            assertStringContains($prepared['record_path'], $committedFiles);
            assertStringContains($prepared['record_path'] . '.asc', $committedFiles);
        } finally {
            $_POST = [];
            $_COOKIE = [];
            $this->deleteTree($signingKey['home']);
        }
    }

    public function testSignedInvitationRedemptionRechecksPolicyBeforeAcceptance(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $issuerKey = $this->createSigningKey('invitation-sponsor');
        $recipientKey = $this->createSigningKey('forum-user');
        $previous = getenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL');
        try {
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $profiles = [];
            foreach ([$issuerKey, $recipientKey] as $key) {
                $_POST = ['public_key' => $key['public_key']];
                $response = $this->renderMethod($app, 'POST', '/api/link_identity');
                $_POST = [];
                $profiles[] = [
                    'identity_id' => $this->extractValue($response, 'identity_id'),
                    'profile_slug' => $this->extractValue($response, 'profile_slug'),
                    'thread_id' => $this->extractValue($response, 'bootstrap_thread_id'),
                    'parent_id' => $this->extractValue($response, 'bootstrap_post_id'),
                ];
            }
            [$issuer, $recipient] = $profiles;
            $existing = $this->linkGeneratedIdentity($app, 'forum-user');
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
            $writer->approveUser([
                'approver_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                'target_identity_id' => $existing['identity_id'], 'target_profile_slug' => $existing['profile_slug'],
                'thread_id' => $existing['bootstrap_thread_id'], 'parent_id' => $existing['bootstrap_post_id'],
            ]);
            $writer->approveUser([
                'approver_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954',
                'target_identity_id' => $issuer['identity_id'], 'target_profile_slug' => $issuer['profile_slug'],
                'thread_id' => $issuer['thread_id'], 'parent_id' => $issuer['parent_id'],
            ]);
            $token = bin2hex(random_bytes(16));
            $issue = $writer->prepareInvitation([
                'issuer_identity_id' => $issuer['identity_id'], 'thread_id' => $issuer['thread_id'],
                'parent_id' => $issuer['parent_id'], 'verification_hash' => 'sha256:' . hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600), 'destination' => '/threads/root-001?q=1#reply-001',
            ]);
            $writer->finalizePreparedInvitation($issue + [
                'author_identity_id' => $issuer['identity_id'],
                'detached_signature' => $this->signCanonicalRecord($issuerKey['home'], $issue['canonical_record']),
            ]);
            $countBeforeInspection = $this->countCanonicalPostFiles($root);
            assertSame(['status' => 'ok', 'targeted' => true, 'state' => 'available'], $writer->inspectInvitation($token));
            assertSame($countBeforeInspection, $this->countCanonicalPostFiles($root));
            $approvedAttempt = $writer->prepareInvitationRedemption([
                'recipient_identity_id' => $issuer['identity_id'], 'thread_id' => $issuer['thread_id'],
                'parent_id' => $issuer['parent_id'], 'invite_token' => $token,
            ]);
            $approvedResult = $writer->finalizePreparedInvitation($approvedAttempt + ['author_identity_id' => $issuer['identity_id'],
                'detached_signature' => $this->signCanonicalRecord($issuerKey['home'], $approvedAttempt['canonical_record'])]);
            assertSame(true, $approvedResult['already_approved']);
            assertSame('available', $writer->inspectInvitation($token)['state']);
            $redeem = $writer->prepareInvitationRedemption([
                'recipient_identity_id' => $recipient['identity_id'], 'thread_id' => $recipient['thread_id'],
                'parent_id' => $recipient['parent_id'], 'invite_token' => $token,
            ]);
            $signed = $redeem + ['author_identity_id' => $recipient['identity_id'],
                'detached_signature' => $this->signCanonicalRecord($recipientKey['home'], $redeem['canonical_record'])];
            $before = $this->countCanonicalPostFiles($root);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            try {
                $writer->finalizePreparedInvitation($signed);
                throw new RuntimeException('Expected cross-account denial.');
            } catch (RuntimeException $exception) {
                if (!str_contains($exception->getMessage(), 'same username or a root-approved operator')) {
                    throw $exception;
                }
            }
            assertSame($before, $this->countCanonicalPostFiles($root));
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            assertSame('ok', $writer->finalizePreparedInvitation($signed)['status']);
            assertSame('used', $writer->inspectInvitation($token)['state']);
            $afterSuccess = $this->countCanonicalPostFiles($root);
            $retry = $writer->prepareInvitationRedemption([
                'recipient_identity_id' => $recipient['identity_id'], 'thread_id' => $recipient['thread_id'],
                'parent_id' => $recipient['parent_id'], 'invite_token' => $token,
            ]);
            $recovery = $retry + ['author_identity_id' => $recipient['identity_id'],
                'detached_signature' => $this->signCanonicalRecord($issuerKey['home'], $retry['canonical_record'])];
            try {
                $writer->finalizePreparedInvitation($recovery);
                throw new RuntimeException('Expected forged recovery rejection.');
            } catch (RuntimeException $e) { assertStringContains('signature verification failed', $e->getMessage()); }
            $recovery['detached_signature'] = $this->signCanonicalRecord($recipientKey['home'], $retry['canonical_record']);
            $result = $writer->finalizePreparedInvitation($recovery);
            assertSame(true, $result['recovered']);
            assertSame('/threads/root-001?q=1#reply-001', $result['destination']);
            assertSame($afterSuccess, $this->countCanonicalPostFiles($root));
            try {
                $writer->prepareInvitationRedemption(['recipient_identity_id' => $issuer['identity_id'], 'invite_token' => $token]);
                throw new RuntimeException('Expected foreign-recipient rejection.');
            } catch (RuntimeException $e) { assertStringContains('another identity', $e->getMessage()); }

            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $profile = ProfileRepository::byIdentityId((new ReadModelConnection($db))->open(), $recipient['identity_id']);
            assertSame(1, (int) $profile['is_approved']);
            $receipt = \ForumRewrite\Security\ApprovalAcceptance::read($root, $redeem['record_path']);
            assertSame('invitation', $receipt['kind']);
            assertSame('cross_account_key', $receipt['context']['classification']);
            assertSame(true, $receipt['context']['cross_account_allowed']);
            assertSame($issuer['identity_id'], $receipt['context']['actor_identity_id']);
            $history = (new \ForumRewrite\ReadModel\ApprovalHistory((new ReadModelConnection($db))->open(), $root))->forUsername('forum-user');
            $grants = array_column($history['rows'], null, 'identity_id');
            assertSame('cross_account_key', $grants[$recipient['identity_id']]['classification']);
            assertSame($receipt['accepted_at'], $grants[$recipient['identity_id']]['accepted_at']);
            require_once __DIR__ . '/Support/ImportTestWorkspace.php';
            $workspace = new ImportTestWorkspace();
            $restored = $workspace->repository('restored', true);
            $workspace->git($restored);
            $archive = $workspace->root . '/accepted.tar.gz';
            [$code, $output] = $workspace->command(['tar', '-czf', $archive, '-C', $root, 'records']);
            assertSame(0, $code, $output);
            $restoreDb = $workspace->root . '/restored.sqlite3';
            [$code, $output] = $workspace->command(['php', __DIR__ . '/../scripts/import_repository_archive.php', $archive, $restored, $restoreDb]);
            assertSame(0, $code, $output);
            assertSame($receipt, \ForumRewrite\Security\ApprovalAcceptance::read($restored, $redeem['record_path']));
            $restoredProfile = ProfileRepository::byIdentityId((new ReadModelConnection($restoreDb))->open(), $recipient['identity_id']);
            assertSame(1, (int) $restoredProfile['is_approved']);
            (new ReadModelBuilder($restored, $restoreDb, new CanonicalRecordRepository($restored)))->rebuild();
            assertSame($receipt, \ForumRewrite\Security\ApprovalAcceptance::read($restored, $redeem['record_path']));
            // Mixed archives retain the same facts; a contradictory companion aborts before copying.
            $legacyPath = $root . '/records/approval-acceptances/' . $redeem['post_id'] . '.json';
            file_put_contents($legacyPath, json_encode($receipt));
            [$code, $output] = $workspace->command(['tar', '-czf', $archive, '-C', $root, 'records']);
            assertSame(0, $code, $output);
            [$code, $output] = $workspace->command(['php', __DIR__ . '/../scripts/import_repository_archive.php', $archive, $restored, $restoreDb]);
            assertSame(0, $code, $output);
            assertSame($receipt, \ForumRewrite\Security\ApprovalAcceptance::read($restored, $redeem['record_path']));
            $legacyOnly = $workspace->repository('legacy-only', true); $workspace->git($legacyOnly);
            unlink($root . '/' . \ForumRewrite\Security\ApprovalAcceptance::path($redeem['post_id']));
            [$code, $output] = $workspace->command(['tar', '-czf', $archive, '-C', $root, 'records']);
            assertSame(0, $code, $output);
            [$code, $output] = $workspace->command(['php', __DIR__ . '/../scripts/import_repository_archive.php', $archive, $legacyOnly, $workspace->root . '/legacy.sqlite3']);
            assertSame(0, $code, $output);
            assertSame($receipt, \ForumRewrite\Security\ApprovalAcceptance::read($legacyOnly, $redeem['record_path']));
            file_put_contents($root . '/' . \ForumRewrite\Security\ApprovalAcceptance::path($redeem['post_id']), \ForumRewrite\Security\ApprovalReceiptText::encode($receipt));
            $bad = $receipt; $bad['accepted_at'] = '2099-01-01T00:00:00Z'; file_put_contents($legacyPath, json_encode($bad));
            [$code, $output] = $workspace->command(['tar', '-czf', $archive, '-C', $root, 'records']);
            assertSame(0, $code, $output);
            [$code, $output] = $workspace->command(['php', __DIR__ . '/../scripts/import_repository_archive.php', $archive, $restored, $restoreDb]);
            assertSame(1, $code, $output);
            assertStringContains('Conflicting approval', $output);

        } finally {
            $_POST = [];
            putenv($previous === false ? 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL' : 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=' . $previous);
            $this->deleteTree($issuerKey['home']);
            $this->deleteTree($recipientKey['home']);
        }
    }

    public function testSignedAccountKeyFlowsPreserveReviewAndRecoverCommittedRefreshFailure(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('approval-owner');
        $previous = getenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL');
        try {
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $_POST = ['public_key' => $key['public_key']];
            $response = $this->renderMethod($app, 'POST', '/api/link_identity'); $_POST = [];
            $owner = ['identity_id' => $this->extractValue($response, 'identity_id'), 'profile_slug' => $this->extractValue($response, 'profile_slug'),
                'bootstrap_thread_id' => $this->extractValue($response, 'bootstrap_thread_id'), 'bootstrap_post_id' => $this->extractValue($response, 'bootstrap_post_id')];
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
            $this->approveIdentity($writer, $owner);
            $bob = $this->linkGeneratedIdentity($app, 'existing-bob');
            $this->approveIdentity($writer, $bob);
            $new = $this->linkGeneratedIdentity($app, 'new-member');
            $own = $this->linkGeneratedIdentity($app, 'approval-owner');
            $cross = $this->linkGeneratedIdentity($app, 'existing-bob');
            $prepare = fn (array $target) => $writer->prepareApproval(['approver_identity_id' => $owner['identity_id'],
                'target_identity_id' => $target['identity_id'], 'target_profile_slug' => $target['profile_slug'],
                'thread_id' => $target['bootstrap_thread_id'], 'parent_id' => $target['bootstrap_post_id']]);
            $sign = fn (array $prepared) => $prepared + ['author_identity_id' => $owner['identity_id'],
                'detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])];
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            $sponsor = $prepare($new);
            assertSame('Approve new member new-member', $sponsor['approval_review']['label']);
            assertSame('ok', $writer->finalizePreparedApproval($sign($sponsor))['status']);
            $addition = $prepare($own);
            assertSame('Add this key to my account', $addition['approval_review']['label']);
            assertSame('ok', $writer->finalizePreparedApproval($sign($addition))['status']);
            try { $prepare($cross); throw new RuntimeException('Expected cross-account denial.'); }
            catch (RuntimeException $error) { assertStringContains('same username or a root-approved operator', $error->getMessage()); }
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            $grant = $prepare($cross);
            assertSame('Grant this key access as existing-bob', $grant['approval_review']['label']);
            assertStringContains('already has 1 approved keys', $grant['approval_review']['consequence']);
            $signed = $sign($grant);
            $failing = new class($root, $db, $artifacts, new CanonicalRecordRepository($root)) extends LocalWriteService {
                protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
                {
                    return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                        public function applyApprovalWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array { throw new RuntimeException('test incremental failure'); }
                    };
                }
                protected function rebuildReadModel(): array { throw new RuntimeException('test rebuild failure'); }
            };
            try { $failing->finalizePreparedApproval($signed); throw new RuntimeException('Expected committed refresh failure.'); }
            catch (RuntimeException $error) { assertStringContains('Canonical write committed at', $error->getMessage()); }
            $this->assertReadModelMarkedStale($db);
            $this->seedArtifacts($artifacts, ['/activity.html', '/users.html', '/user/existing-bob.html']);
            $before = $this->countCanonicalPostFiles($root);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            $recovered = $writer->finalizePreparedApproval($signed);
            assertSame(true, $recovered['recovered']);
            assertSame($before, $this->countCanonicalPostFiles($root));
            foreach (['/activity.html', '/users.html', '/user/existing-bob.html'] as $artifact) assertSame(false, is_file($artifacts . $artifact));
            $this->assertReadModelHealthy($db);
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $history = new \ForumRewrite\ReadModel\ApprovalHistory((new ReadModelConnection($db))->open(), $root);
            $ownKeys = array_column($history->forUsername('approval-owner')['rows'], null, 'identity_id');
            assertSame('own_account_key', $ownKeys[$own['identity_id']]['classification']);
            $bobKeys = array_column($history->forUsername('existing-bob')['rows'], null, 'identity_id');
            assertSame('cross_account_key', $bobKeys[$cross['identity_id']]['classification']);
            assertSame(true, $bobKeys[$cross['identity_id']]['cross_account_allowed']);
        } finally {
            $_POST = []; $_COOKIE = [];
            putenv($previous === false ? 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL' : 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=' . $previous);
            $this->deleteTree($key['home']);
        }
    }

    public function testOperatorSecurityReviewRequiresAuthenticatedRootAndCorrelatesPolicyChanges(): void
    {
        [$root, $db, $artifacts] = $this->createTempEnvironment();
        $key = $this->createSigningKey('security-operator');
        $previousSession = $_SESSION ?? [];
        $previousFlag = getenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL');
        putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL');
        try {
            $app = new Application(dirname(__DIR__), $root, $db, $artifacts);
            $_POST = ['public_key' => $key['public_key']];
            $response = $this->renderMethod($app, 'POST', '/api/link_identity');
            $_POST = [];
            $operator = $this->extractValue($response, 'identity_id');
            $this->seedApprovedIdentity($root, $operator);
            $_SESSION = ['authenticated_identity_id' => $operator];
            assertStringContains('No access changes recorded yet.', $this->renderMethod($app, 'GET', '/tools/user-access/'));
            $_SESSION = [];
            $target = $this->linkGeneratedIdentity($app, 'security-target');
            $writer = new LocalWriteService($root, $db, $artifacts, new CanonicalRecordRepository($root));
            $change = function (bool $value) use ($writer, $operator, $key): void {
                $prepared = $writer->prepareFeatureFlagChange(['key' => 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL', 'value' => $value ? 'true' : 'false'], $operator);
                if (($prepared['wrote_record'] ?? '') === 'no') return;
                $writer->finalizePreparedFeatureFlagChange($prepared + ['detached_signature' => $this->signCanonicalRecord($key['home'], $prepared['canonical_record'])], $operator);
            };
            $change(false);
            $change(true);
            $grant = $this->approveIdentity($writer, $target, $operator);
            $change(false);
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $paths = ['/activity/?view=security', '/forte/activity/?view=security', '/api/forte_activity_page?view=security', '/api/forte_activity_detail?view=security&id=1', '/api/forte_commit_detail?view=security&sha=' . $grant['commit_sha'], '/activity/?view=security&format=rss'];
            foreach ([null, $target['identity_id']] as $viewer) {
                $_SESSION = $viewer === null ? [] : ['authenticated_identity_id' => $viewer];
                $_COOKIE = ['identity_hint' => $operator];
                assertStringNotContains('>Security review</a>', $this->renderMethod($app, 'GET', '/tools/user-access/'));
                foreach ($paths as $path) {
                    $denied = $this->renderMethod($app, 'GET', $path);
                    assertSame(403, http_response_code());
                    if ($viewer === null && !str_starts_with($path, '/api/') && !str_contains($path, 'format=rss')) {
                        assertStringContains('Sign in with your operator account', $denied);
                        assertStringContains('data-auth-return-to="', $denied);
                    } else {
                        assertStringContains('Authenticated root-approved operator access required', $denied);
                    }
                    assertStringNotContains('Acceptance receipt', $denied);
                }
            }
            $_SESSION = ['authenticated_identity_id' => $operator];
            $_COOKIE = [];
            $recent = $this->renderMethod($app, 'GET', '/tools/user-access/');
            assertStringContains('<h2>Access history</h2>', $recent);
            assertStringContains('Account key approved for security-target', $recent);
            assertStringContains('Acceptance receipt', $recent);
            assertSame(4, substr_count($recent, 'class="access-history-event"'));
            assertStringNotContains('>View all</a>', $recent);
            $change(true); $change(false); $change(true);
            $recent = $this->renderMethod($app, 'GET', '/tools/user-access/');
            assertSame(5, substr_count($recent, 'class="access-history-event"'));
            assertStringContains('>View all</a>', $recent);
            assertStringNotContains('Review account key grants', $this->renderMethod($app, 'GET', '/tools/feature-flags/'));
            foreach (['/activity/', '/forte/activity/'] as $path) {
                $page = $this->renderMethod($app, 'GET', $path);
                assertStringNotContains('Operator security review', $page);
                assertStringNotContains('<span>Operator security review</span>', $page);
            }
            foreach (['/activity/?view=security', '/forte/activity/?view=security'] as $path) {
                $page = $this->renderMethod($app, 'GET', $path);
                assertSame(200, http_response_code());
                assertStringNotContains('>Operator security review</a>', $page);
                assertStringNotContains('<span>Operator security review</span>', $page);
                assertStringContains('Cross-account approvals enabled', $page);
                assertStringContains('Cross-account approvals restricted', $page);
                assertStringContains('security-target', $page);
                assertStringContains('Current cross-account policy:', $page);
                if (str_starts_with($path, '/forte/')) {
                    assertStringContains('data-paned-activity-rss href="/activity/?view=security&amp;format=rss" hidden', $page);
                    assertSame(1, substr_count($page, 'Current cross-account policy:'));
                    assertTrue(strpos($page, 'Current cross-account policy:') < strpos($page, 'data-paned-activity-list-pane'));
                }
            }
            $pdo = (new ReadModelConnection($db))->open();
            $query = $pdo->prepare('SELECT id FROM activity WHERE post_id = ?');
            $query->execute([$grant['post_id']]);
            $id = (int) $query->fetchColumn();
            $query->closeCursor();
            $detail = $this->renderMethod($app, 'GET', '/api/forte_activity_detail?view=security&id=' . $id);
            assertStringContains('Acceptance receipt', $detail);
            assertStringContains('Affected account: security-target', $detail);
            $pagination = $this->renderMethod($app, 'GET', '/api/forte_activity_page?view=security');
            $securityPage = json_decode($pagination, true, 512, JSON_THROW_ON_ERROR);
            assertSame('ok', $securityPage['status']);
            assertStringContains('data-paned-activity-view-security="1"', $securityPage['html']);
            assertStringContains('Acceptance receipt', $securityPage['detail_html']);
            assertStringContains('Affected account: security-target', $securityPage['detail_html']);
            $securityRows = json_decode($this->renderMethod($app, 'GET', '/api/forte_activity_page?view=security&rows_only=1'), true, 512, JSON_THROW_ON_ERROR);
            assertStringContains('Account key approved for security-target', $securityRows['html']);
            $ordinary = $this->renderMethod($app, 'GET', '/api/forte_activity_detail?id=' . $id);
            assertStringNotContains('Affected account:', $ordinary);
            assertStringNotContains('Acceptance receipt', $ordinary);
            $this->renderMethod($app, 'GET', '/activity/?view=security&format=rss');
            assertSame(406, http_response_code());
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            assertStringContains('security-target', $this->renderMethod($app, 'GET', '/activity/?view=security'));
        } finally {
            $_POST = []; $_COOKIE = []; $_SESSION = $previousSession;
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            putenv($previousFlag === false ? 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL' : 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=' . $previousFlag);
            $this->deleteTree($key['home']);
        }
    }

    public function testAlreadyApprovedUserCannotBeApprovedAgain(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $target = $this->linkGeneratedIdentity($application, 'alice');

        $approvalResult = $this->approveIdentity(
            new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)),
            $target
        );
        $_COOKIE = ['identity_hint' => 'guest'];
        $secondResponse = $this->renderMethod($application, 'POST', '/profiles/' . $target['profile_slug'] . '/approve');
        $_COOKIE = [];

        $matchingApprovals = 0;
        foreach ($this->listCanonicalPostPaths($repositoryRoot) as $relativePath) {
            $path = $repositoryRoot . '/' . $relativePath;
            $contents = (string) file_get_contents($path);
            if (str_contains($contents, 'Thread-ID: ' . $target['bootstrap_thread_id'])
                && str_contains($contents, 'Parent-ID: ' . $target['bootstrap_post_id'])
                && str_contains($contents, 'Approve-Identity-ID: ' . $target['identity_id'])) {
                $matchingApprovals++;
            }
        }

        $bootstrapThread = $this->renderMethod($application, 'GET', '/threads/' . $target['bootstrap_thread_id']);

        assertTrue(strlen($approvalResult['commit_sha']) === 40);
        assertStringContains('Approval requires a browser signature.', $secondResponse);
        assertSame(1, $matchingApprovals);
        assertStringContains('Approve-Identity-ID: ' . $target['identity_id'], $bootstrapThread);
    }

    public function testApproveUserUsesIncrementalReadModelUpdateWhenDatabaseIsWarm(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $result = $this->approveIdentity($service, $target);
        $profilePage = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);

        assertSame(true, isset($result['timings']['read_model_approval_incremental_update']));
        assertSame(false, isset($result['timings']['read_model_approval_rebuild']));
        assertStringContains('Approval:</strong> Approved ·', $profilePage);
        assertStringContains('>guest</a>', $profilePage);
    }

    public function testIncrementalApprovalMatchesFreshRebuildForProfileThreadAndActivity(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $this->approveIdentity($service, $target);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-approval-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalProfile = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);
        $freshProfile = $this->renderMethod($freshApplication, 'GET', '/profiles/' . $target['profile_slug']);
        $incrementalBootstrapThread = $this->renderMethod($application, 'GET', '/threads/' . $target['bootstrap_thread_id']);
        $freshBootstrapThread = $this->renderMethod($freshApplication, 'GET', '/threads/' . $target['bootstrap_thread_id']);
        $incrementalApprovalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
        $freshApprovalActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=approval');

        assertSame($this->normalizeRenderedPage($freshProfile), $this->normalizeRenderedPage($incrementalProfile));
        assertSame($this->normalizeRenderedPage($freshBootstrapThread), $this->normalizeRenderedPage($incrementalBootstrapThread));
        assertSame($this->normalizeRenderedPage($freshApprovalActivity), $this->normalizeRenderedPage($incrementalApprovalActivity));
    }

    public function testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $alice = $this->linkGeneratedIdentity($application, 'alice');
        $bob = $this->linkGeneratedIdentity($application, 'bob');
        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));

        $likeResult = $service->applyThreadTag([
            'thread_id' => 'root-001',
            'tag' => 'like',
            'author_identity_id' => $alice['identity_id'],
        ]);
        $approveAlice = $this->approveIdentity($service, $alice);
        $approveBob = $this->approveIdentity($service, $bob, $alice['identity_id']);

        $freshDatabasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-approval-transitive-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $freshApplication = new Application(dirname(__DIR__), $repositoryRoot, $freshDatabasePath, $artifactRoot);

        $incrementalRootThread = $this->renderMethod($application, 'GET', '/threads/root-001');
        $freshRootThread = $this->renderMethod($freshApplication, 'GET', '/threads/root-001');
        $incrementalBobProfile = $this->renderMethod($application, 'GET', '/profiles/' . $bob['profile_slug']);
        $freshBobProfile = $this->renderMethod($freshApplication, 'GET', '/profiles/' . $bob['profile_slug']);
        $incrementalApprovalActivity = $this->renderMethod($application, 'GET', '/activity/?view=approval');
        $freshApprovalActivity = $this->renderMethod($freshApplication, 'GET', '/activity/?view=approval');
        $incrementalThreadApi = $this->renderMethod($application, 'GET', '/api/get_thread?thread_id=root-001');
        $freshThreadApi = $this->renderMethod($freshApplication, 'GET', '/api/get_thread?thread_id=root-001');

        assertSame('no', $likeResult['viewer_is_approved']);
        assertSame(true, isset($approveAlice['timings']['read_model_approval_incremental_update']));
        assertSame(true, isset($approveBob['timings']['read_model_approval_incremental_update']));
        assertStringContains('Score-Total: 1', $incrementalThreadApi);
        assertSame($this->normalizeRenderedPage($freshRootThread), $this->normalizeRenderedPage($incrementalRootThread));
        assertSame($this->normalizeRenderedPage($freshBobProfile), $this->normalizeRenderedPage($incrementalBobProfile));
        assertSame($this->normalizeRenderedPage($freshApprovalActivity), $this->normalizeRenderedPage($incrementalApprovalActivity));
        assertSame($freshThreadApi, $incrementalThreadApi);
    }

    public function testApprovalIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyApprovalWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated approval incremental failure');
                    }
                };
            }
        };

        $result = $this->approveIdentity($service, $target);
        $profilePage = $this->renderMethod($application, 'GET', '/profiles/' . $target['profile_slug']);
        assertSame(true, isset($result['timings']['read_model_approval_incremental_fallback']));
        assertSame(true, isset($result['timings']['read_model_approval_rebuild_fallback']));
        assertStringContains('Approval:</strong> Approved ·', $profilePage);
        $this->assertReadModelHealthy($databasePath);
    }

    public function testApprovalWriteMarksDerivedStateStaleWhenIncrementalAndFallbackRefreshFail(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $target = $this->linkGeneratedIdentity($application, 'alice');
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function incrementalReadModelUpdater(): IncrementalReadModelUpdater
            {
                return new class($this->databasePath(), $this->repositoryRoot()) extends IncrementalReadModelUpdater {
                    public function applyApprovalWrite(\ForumRewrite\Canonical\PostRecord $record, string $commitSha): array
                    {
                        throw new RuntimeException('simulated approval incremental failure');
                    }
                };
            }

            protected function rebuildReadModel(): array
            {
                throw new RuntimeException('simulated approval rebuild failure');
            }
        };

        try {
            $this->approveIdentity($service, $target);
            throw new RuntimeException('Expected approval refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $this->assertReadModelMarkedStale($databasePath);
    }

    public function testTagsIndexShowsFiveNewestThreadsAndTagPageShowsAllThreads(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $subjects = [];
        for ($index = 1; $index <= 6; $index++) {
            $subject = 'Bug thread ' . $index;
            $subjects[] = $subject;
            $response = $this->renderMethod(
                $application,
                'POST',
                '/api/create_thread?board_tags=general&subject=' . rawurlencode($subject) . '&body=' . rawurlencode("Thread body\n#bug\n")
            );
            assertStringContains('status=ok', $response);
        }

        $tags = $this->renderMethod($application, 'GET', '/tags/');
        $tagPage = $this->renderMethod($application, 'GET', '/tags/bug');

        assertStringContains('showing 5 newest of 7', $tags);
        assertStringNotContains('View all bug', $tags);
        assertSame(5, substr_count($tags, 'data-tag-preview-item="bug"'));
        assertSame(1, preg_match('#<ul class="tag-thread-list" data-tag-preview-for="bug">(.*?)</ul>#s', $tags, $matches));
        $preview = $matches[1];
        assertSame(5, substr_count($preview, 'Bug thread '));

        foreach ($subjects as $subject) {
            assertStringContains($subject, $tagPage);
        }
        assertSame(6, substr_count($tagPage, 'Bug thread '));
    }

    /**
     * @return array{string,string,string}
     */
    private function createTempEnvironment(): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->initializeGitRepository($repositoryRoot);

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertUsedIncrementalUpdate(array $result): void
    {
        assertSame(true, isset($result['timings']['read_model_incremental_update']));
        assertSame(false, isset($result['timings']['read_model_rebuild']));
    }

    private function assertReadModelHealthy(string $databasePath): void
    {
        assertFalse(is_file(dirname($databasePath) . '/read_model_stale.json'));
    }

    private function assertReadModelMarkedStale(string $databasePath, string $expectedReason = 'write_refresh_failed'): void
    {
        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';
        assertTrue(is_file($staleMarkerPath));
        $staleMarker = json_decode((string) file_get_contents($staleMarkerPath), true, 512, JSON_THROW_ON_ERROR);
        assertSame($expectedReason, $staleMarker['reason'] ?? null);
        assertTrue(strlen((string) ($staleMarker['commit_sha'] ?? '')) === 40);
    }

    private function readFixturePublicKey(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc');
    }

    private function readOpenPgpSignatureFixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/openpgp_signature/' . $name);
    }

    private function installOpenPgpSignatureFixtureIdentity(string $repositoryRoot): void
    {
        $fingerprintUpper = '15C2EF95BAEE78F4C635A43323B6AE7EC7AF9919';
        $fingerprintLower = strtolower($fingerprintUpper);
        $publicKey = $this->readOpenPgpSignatureFixture('public-key.asc');
        $publicKeyPath = $repositoryRoot . '/records/public-keys/openpgp-' . $fingerprintUpper . '.asc';
        $identityPath = $repositoryRoot . '/records/identity/identity-openpgp-' . $fingerprintLower . '.txt';
        file_put_contents($publicKeyPath, $publicKey);
        file_put_contents(
            $identityPath,
            "Post-ID: identity-openpgp-{$fingerprintLower}\n"
            . "Board-Tags: identity\n"
            . "Subject: identity bootstrap\n"
            . "Username: signature-test\n"
            . "Identity-ID: openpgp:{$fingerprintLower}\n"
            . "Signer-Fingerprint: {$fingerprintUpper}\n"
            . "Bootstrap-By-Post: root-001\n"
            . "Bootstrap-By-Thread: root-001\n"
            . "\n{$publicKey}"
        );
        $this->runCommand(
            $repositoryRoot,
            'git add records/public-keys/openpgp-' . $fingerprintUpper . '.asc records/identity/identity-openpgp-' . $fingerprintLower . '.txt'
        );
        $this->runCommand($repositoryRoot, 'git commit -m "Add signature fixture identity"');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function storePreparedPostFixture(string $databasePath, string $token, string $canonicalRecord, array $metadata): void
    {
        $directory = dirname($databasePath) . '/prepared-posts';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $payload = array_merge([
            'prepare_token' => $token,
            'record_path' => 'records/posts/2026/07/19/signed-test.txt',
            'canonical_sha256' => hash('sha256', $canonicalRecord),
            'canonical_record' => $canonicalRecord,
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 600),
        ], $metadata);
        file_put_contents($directory . '/' . $token . '.json', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    private function extractValue(string $response, string $key): string
    {
        foreach (explode("\n", trim($response)) as $line) {
            if (str_starts_with($line, $key . '=')) {
                return substr($line, strlen($key) + 1);
            }
        }

        throw new RuntimeException('Missing response key: ' . $key);
    }

    private function extractHrefId(string $response, string $prefix): string
    {
        if (preg_match('#href="' . preg_quote($prefix, '#') . '([^"]+)"#', $response, $matches) !== 1) {
            throw new RuntimeException('Missing href prefix: ' . $prefix);
        }

        return strtok($matches[1], '?') ?: $matches[1];
    }

    /**
     * @param array<string, mixed> $analysisOverrides
     */
    private function createAnalyzedThread(Application $application, string $databasePath, array $analysisOverrides = []): string
    {
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=Respondable%20Question&body=' . rawurlencode('What should we consider next?')
        );
        $postId = $this->extractValue($response, 'post_id');
        $this->analyzePostWithoutAgentReply($application, $postId);

        if ($analysisOverrides !== []) {
            $contentHash = $this->contentHashForAnalysis($databasePath, $postId);
            (new SqlitePostAnalysisStore(new PDO('sqlite:' . $databasePath)))->saveComplete(
                $postId,
                $contentHash,
                array_replace_recursive($this->baseCompletedAnalysis(), $analysisOverrides)
            );
        }

        return $postId;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzePostWithoutAgentReply(Application $application, string $postId): array
    {
        $previous = getenv('DEDALUS_AGENT_REPLIES_ENABLED');
        putenv('DEDALUS_AGENT_REPLIES_ENABLED=false');

        try {
            return json_decode(
                $this->renderMethod($application, 'POST', '/api/analyze_post?post_id=' . rawurlencode($postId)),
                true
            );
        } finally {
            if ($previous === false) {
                putenv('DEDALUS_AGENT_REPLIES_ENABLED');
            } else {
                putenv('DEDALUS_AGENT_REPLIES_ENABLED=' . $previous);
            }
        }
    }

    /**
     * @return array<string, string|false>
     */
    private function useStubLlmProvider(): array
    {
        $previous = [
            'LLM_PROVIDER' => getenv('LLM_PROVIDER'),
            'DEDALUS_ANALYSIS_MODE' => getenv('DEDALUS_ANALYSIS_MODE'),
        ];

        putenv('LLM_PROVIDER=stub');
        putenv('DEDALUS_ANALYSIS_MODE=stub');

        return $previous;
    }

    /**
     * @param array<string, string|false> $previous
     */
    private function restoreLlmProviderEnv(array $previous): void
    {
        foreach (['LLM_PROVIDER', 'DEDALUS_ANALYSIS_MODE'] as $key) {
            $this->restoreEnvironmentValue($key, $previous[$key] ?? false);
        }
    }

    private function restoreEnvironmentValue(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            return;
        }

        putenv($key . '=' . $value);
    }

    private function contentHashForAnalysis(string $databasePath, string $postId): string
    {
        $pdo = new PDO('sqlite:' . $databasePath);
        $stmt = $pdo->prepare('SELECT content_hash FROM post_analyses WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $postId]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            throw new RuntimeException('Missing analysis content hash for ' . $postId);
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseCompletedAnalysis(): array
    {
        return [
            'provider' => 'stub',
            'provider_model' => 'stub/post-analysis',
            'provider_request_id' => 'stub-analysis',
            'post_summary' => 'The post asks what should be considered next.',
            'moderation' => [
                'severity' => 'none',
                'labels' => [],
                'confidence' => 0.9,
                'summary' => 'No moderation concern.',
                'recommended_action' => 'none',
            ],
            'engagement' => [
                'suggested_response' => 'Consider naming the main tradeoff.',
                'response_style' => 'curious',
                'response_should_be_public' => true,
            ],
            'quality' => [
                'discussion_value' => 'medium',
                'good_faith_likelihood' => 0.9,
                'needs_human_review' => false,
            ],
            'respondability' => [
                'overall_score' => 0.9,
                'asks_question' => true,
                'question_type' => 'opinion',
                'invites_response' => true,
                'author_benefit' => 'medium',
                'audience_benefit' => 'medium',
                'response_effort_required' => 'low',
                'response_risk' => 'low',
                'best_response_mode' => 'answer',
                'should_generate_response' => true,
                'reason' => 'Respondable test post.',
            ],
            'raw_response' => ['stub' => true],
        ];
    }

    /**
     * @return array{identity_id:string,profile_slug:string,bootstrap_post_id:string,bootstrap_thread_id:string}
     */
    private function linkGeneratedIdentity(Application $application, string $username): array
    {
        $_POST = [
            'public_key' => $this->generatePublicKey($username),
        ];
        $response = $this->renderMethod($application, 'POST', '/api/link_identity');
        $_POST = [];

        return [
            'identity_id' => $this->extractValue($response, 'identity_id'),
            'profile_slug' => $this->extractValue($response, 'profile_slug'),
            'bootstrap_post_id' => $this->extractValue($response, 'bootstrap_post_id'),
            'bootstrap_thread_id' => $this->extractValue($response, 'bootstrap_thread_id'),
        ];
    }

    /**
     * @return list<string>
     */
    private function listCanonicalPostIds(string $repositoryRoot): array
    {
        $ids = [];
        foreach ($this->listCanonicalPostPaths($repositoryRoot) as $relativePath) {
            $ids[] = basename($relativePath, '.txt');
        }

        sort($ids);

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function listCanonicalPostPaths(string $repositoryRoot): array
    {
        $paths = [];
        $postsRoot = $repositoryRoot . '/records/posts';
        if (!is_dir($postsRoot)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($postsRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || !str_ends_with($item->getFilename(), '.txt')) {
                continue;
            }

            $paths[] = str_replace('\\', '/', substr($item->getPathname(), strlen($repositoryRoot) + 1));
        }

        sort($paths);

        return $paths;
    }

    private function countCanonicalPostFiles(string $repositoryRoot): int
    {
        return count($this->listCanonicalPostPaths($repositoryRoot));
    }

    private function canonicalPostPath(string $repositoryRoot, string $postId): string
    {
        foreach (CanonicalPathResolver::postCandidates($postId) as $relativePath) {
            if (is_file($repositoryRoot . '/' . $relativePath)) {
                return $relativePath;
            }
        }

        foreach ($this->listCanonicalPostPaths($repositoryRoot) as $relativePath) {
            if (basename($relativePath) === $postId . '.txt') {
                return $relativePath;
            }
        }

        throw new RuntimeException('Canonical post file not found: ' . $postId);
    }

    private function latestCommitSha(string $repositoryRoot): string
    {
        $command = sprintf(
            'git -C %s rev-parse HEAD 2>&1',
            escapeshellarg($repositoryRoot)
        );
        exec($command, $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));

        return trim(implode("\n", $output));
    }

    /**
     * @param array{identity_id:string,profile_slug:string,bootstrap_post_id:string,bootstrap_thread_id:string} $target
     * @return array<string, mixed>
     */
    private function approveIdentity(LocalWriteService $service, array $target, string $approverIdentityId = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'): array
    {
        return $service->approveUser([
            'approver_identity_id' => $approverIdentityId,
            'target_identity_id' => $target['identity_id'],
            'target_profile_slug' => $target['profile_slug'],
            'thread_id' => $target['bootstrap_thread_id'],
            'parent_id' => $target['bootstrap_post_id'],
        ]);
    }

    private function normalizeRenderedPage(string $html): string
    {
        $normalized = preg_replace('/[a-f0-9]{40}/', 'COMMIT_SHA', $html) ?? $html;
        return preg_replace('#(href="/source/commits/COMMIT_SHA" title="COMMIT_SHA">)[a-f0-9]{12}(</a>)#', '$1SHORT_COMMIT_SHA$2', $normalized) ?? $normalized;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    private function deleteDirectoryContents(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    /**
     * @param list<string> $relativePaths
     */
    private function seedArtifacts(string $artifactRoot, array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $path = $artifactRoot . $relativePath;
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, '<!doctype html><html><body>artifact</body></html>');
        }
    }

    private function initializeGitRepository(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
    }

    private function generatePublicKey(string $username): string
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-gpg-home-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $this->runCommand(
            $home,
            'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir '
            . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0'
        );

        $publicKey = $this->runCommand(
            $home,
            'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export'
        );

        GpgHomeCleaner::dispose($home);

        return trim($publicKey) . "\n";
    }

    /**
     * @return array{home:string,public_key:string}
     */
    private function createSigningKey(string $username): array
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-approval-gpg-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $generate = 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir '
            . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0';
        try {
            $this->runCommand($home, $generate);
        } catch (RuntimeException $e) {
            // gpg occasionally rejects a key stamped one second ahead of its own clock; a second attempt succeeds.
            if (!str_contains($e->getMessage(), 'Time conflict')) {
                throw $e;
            }
            sleep(1);
            GpgHomeCleaner::dispose($home);
            mkdir($home, 0700, true);
            $this->runCommand($home, $generate);
        }
        // Not every test deletes its signing home; stop its agent and remove it at exit.
        register_shutdown_function(static function () use ($home): void {
            GpgHomeCleaner::dispose($home);
        });

        return [
            'home' => $home,
            'public_key' => trim($this->runCommand(
                $home,
                'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export'
            )) . "\n",
        ];
    }

    private function signCanonicalRecord(string $home, string $canonicalRecord): string
    {
        $recordPath = $home . '/approval-record.txt';
        $signaturePath = $recordPath . '.asc';
        @unlink($signaturePath);
        file_put_contents($recordPath, $canonicalRecord);
        $this->runCommand(
            $home,
            'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . escapeshellarg($home)
            . ' --armor --detach-sign --output ' . escapeshellarg($signaturePath) . ' ' . escapeshellarg($recordPath)
        );

        return (string) file_get_contents($signaturePath);
    }

    private function seedApprovedIdentity(string $repositoryRoot, string $identityId): void
    {
        $fingerprint = substr($identityId, strlen('openpgp:'));
        $path = 'records/approval-seeds/openpgp-' . $fingerprint . '.txt';
        file_put_contents(
            $repositoryRoot . '/' . $path,
            "Approved-Identity-ID: {$identityId}\nSeed-Reason: test signer\n\nTest trust anchor.\n"
        );
        $this->runCommand($repositoryRoot, 'git add ' . escapeshellarg($path));
        $this->runCommand($repositoryRoot, 'git commit -m "Seed approval test signer"');
    }

    private function gitOutput(string $repositoryRoot, string $command): string
    {
        return trim($this->runCommand($repositoryRoot, 'git ' . $command));
    }

    private function runCommand(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        if (str_starts_with(basename($path), 'forum-rewrite-approval-gpg-')) {
            GpgHomeCleaner::dispose($path);

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }

}

if (!function_exists('assertFalse')) {
    function assertFalse(bool $condition): void
    {
        if ($condition) {
            throw new RuntimeException('Failed asserting that condition is false.');
        }
    }
}

if (!function_exists('assertThrowsRuntime')) {
    function assertThrowsRuntime(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== $expectedMessage) {
                throw new RuntimeException('Unexpected exception message: ' . $exception->getMessage());
            }

            return;
        }

        throw new RuntimeException('Expected RuntimeException was not thrown.');
    }
}
