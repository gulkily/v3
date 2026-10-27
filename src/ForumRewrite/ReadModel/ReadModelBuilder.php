<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\PostReactionRecord;
use ForumRewrite\Canonical\PostRecord;
use ForumRewrite\Canonical\PostRetractionRecord;
use ForumRewrite\Canonical\RetractedPosts;
use ForumRewrite\Canonical\ThreadLabelRecord;
use ForumRewrite\Canonical\ThreadSubjectRecord;
use ForumRewrite\Qdb\QdbVoteScoringPolicy;
use ForumRewrite\SiteConfig;
use ForumRewrite\TagScore;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class ReadModelBuilder
{
    private const HIDDEN_BOOTSTRAP_TAG = 'identity';
    /** @var array<string, float> */
    private array $timings = [];
    private int $invalidThreadLabelRecordCount = 0;
    private int $invalidPostReactionRecordCount = 0;
    private int $invalidThreadSubjectRecordCount = 0;
    /** @var array<int, array{created_at:string,thread_id:string,author_identity_id:?string,labels_added:list<string>,source_path:string,source_commit_sha:?string}> */
    private array $threadLabelActivityEvents = [];
    /** @var array<int, array{created_at:string,post_id:string,thread_id:string,author_identity_id:?string,tags:list<string>,board_tags_json:string,source_path:string,source_commit_sha:?string}> */
    private array $postReactionActivityEvents = [];
    /** @var list<array<string, string|null>> */
    private array $profileNameActivityEvents = [];
    /** @var array<string, ?string> */
    private array $sourceCommitShaByPath = [];
    private ?QdbVoteScoringPolicy $qdbVoteScoringPolicy = null;
    private ?string $attributionCutoff = null;
    private array $excludedPublicationPosts = [];

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly CanonicalRecordRepository $canonicalRepository,
        private readonly string $rebuildReason = 'manual',
        private readonly ?\Closure $progressReporter = null,
        private readonly array $publicationExclusions = [],
    ) {
    }

    public function rebuild(): void
    {
        $databaseDir = dirname($this->databasePath);
        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // The database file is downloadable; zero freed pages so a rebuild after a retraction
        // does not leave the retracted prose in the file's free list.
        $pdo->exec('PRAGMA secure_delete = ON');

        $this->timings = [];
        $this->invalidThreadLabelRecordCount = 0;
        $this->invalidPostReactionRecordCount = 0;
        $this->invalidThreadSubjectRecordCount = 0;
        $this->threadLabelActivityEvents = [];
        $this->postReactionActivityEvents = [];
        $this->profileNameActivityEvents = [];
        $this->sourceCommitShaByPath = [];
        $this->excludedPublicationPosts = $this->publicationExclusions;
        $pdo->beginTransaction();
        try {
            $this->measure('drop_schema', fn (): mixed => $this->dropSchema($pdo));
            $this->measure('create_schema', fn (): mixed => $this->createSchema($pdo));
            $this->attributionCutoff = $this->measure('resolve_attribution_cutoff', fn (): ?string => VerifiedAttribution::resolveCutoff($this->repositoryRoot));

            $posts = $this->measure('index_posts', fn (): array => $this->indexPosts($pdo));
            $profiles = $this->measure('index_profiles', fn (): array => $this->indexProfiles($pdo));
            $approvalState = $this->measure('derive_approval_state', fn (): array => $this->deriveApprovalState($profiles, $posts));
            $this->measure('index_thread_labels', fn (): mixed => $this->indexThreadLabels($pdo, $approvalState));
            $this->measure('index_thread_subjects', fn (): mixed => $this->indexThreadSubjects($pdo));
            $postAttribution = $this->measure('derive_post_attribution', fn (): array => $this->derivePostAttribution($posts));
            $profileCounts = $this->measure('derive_profile_counts', fn (): array => $this->deriveProfileCounts($profiles, $posts, $postAttribution));
            $this->measure('link_post_authors', fn (): mixed => $this->linkPostAuthors($pdo, $profiles, $approvalState, $profileCounts, $postAttribution));
            $this->measure('index_post_reactions', fn (): mixed => $this->indexPostReactions($pdo, $approvalState));
            $this->measure('index_instance', fn (): mixed => $this->indexInstance($pdo));
            $this->measure('index_activity', fn (): mixed => $this->indexActivity($pdo, $posts));
            $this->measure('mark_activity_attribution', fn (): mixed => VerifiedAttribution::markActivity($pdo, $this->repositoryRoot, $this->attributionCutoff, false));
            $this->measure('index_commits', fn (): mixed => $this->indexCommits($pdo));
            $this->measure('index_reaction_signatures', fn (): mixed => ReactionSignatureIndex::refresh($pdo, $this->repositoryRoot));
            $this->measure('write_metadata', fn (): mixed => $this->writeMetadata($pdo));
            $this->measure('apply_verified_attribution', fn (): mixed => VerifiedAttribution::sync($pdo, $this->repositoryRoot, $this->attributionCutoff));
            $this->measure('commit_read_model', fn (): bool => $pdo->commit());
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array<string, float>
     */
    public function timings(): array
    {
        return $this->timings;
    }

    private function qdbVoteScoringPolicy(): ?QdbVoteScoringPolicy
    {
        if (SiteConfig::siteName() !== 'qdb') {
            return null;
        }

        return $this->qdbVoteScoringPolicy ??= QdbVoteScoringPolicy::forReadModel($this->databasePath);
    }

    private function countsThreadLabelTowardVoteTotal(string $tag): bool
    {
        return $this->qdbVoteScoringPolicy()?->countsTowardVoteTotal($tag) ?? TagScore::countsTowardVoteTotal($tag);
    }

    private function isScoredThreadLabel(string $tag): bool
    {
        return $this->qdbVoteScoringPolicy()?->isScoredTag($tag) ?? TagScore::isScoredTag($tag);
    }

    private function threadLabelScoreValue(string $tag): int
    {
        return $this->qdbVoteScoringPolicy()?->scoreValueForTag($tag) ?? TagScore::scoreValueForTag($tag);
    }

    private function threadVoteDedupeKey(string $identityId, string $tag): string
    {
        return $this->qdbVoteScoringPolicy()?->isVoteTag($tag) ? $identityId : $identityId . ':' . $tag;
    }

    /**
     * Drop every table the schema defines, before createSchema() recreates them,
     * so an in-place rebuild over an existing database does not collide.
     *
     * The table list is derived from ReadModelSchema::statements() rather than
     * hand-maintained: previously this method held a hardcoded DROP list that had
     * to be kept in sync with the CREATE TABLE statements by hand, and a forgotten
     * entry caused in-place rebuilds to fail with "table <name> already exists"
     * (this bit the viewer_tags addition). Deriving it means adding a table to the
     * schema automatically drops it on rebuild. Indexes need no DROP — dropping a
     * table drops its indexes. No read-model table declares a foreign key, so drop
     * order is immaterial.
     */
    private function dropSchema(PDO $pdo): void
    {
        foreach (ReadModelSchema::tableNames() as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }

    private function createSchema(PDO $pdo): void
    {
        foreach (ReadModelSchema::statements() as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * @return array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,source_path:string,source_commit_sha:?string,sequence_number:int}>
     */
    private function indexPosts(PDO $pdo): array
    {
        $insertPost = $pdo->prepare(
            'INSERT INTO posts (post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, thread_type, agent_response_json, author_identity_id, retracted_at, retraction_class, retraction_attribution, sequence_number)
             VALUES (:post_id, :created_at, :thread_id, :parent_id, :subject, :body, :board_tags_json, :thread_type, :agent_response_json, :author_identity_id, :retracted_at, :retraction_class, :retraction_attribution, :sequence_number)'
        );
        $insertThread = $pdo->prepare(
            'INSERT INTO threads (root_post_id, root_post_created_at, last_activity_at, subject, body_preview, reply_count, last_post_id, board_tags_json, thread_labels_json, score_total, vote_count, event_date, event_location, event_link, event_time, retracted_at, retraction_class, retraction_attribution)
             VALUES (:root_post_id, :root_post_created_at, :last_activity_at, :subject, :body_preview, :reply_count, :last_post_id, :board_tags_json, :thread_labels_json, :score_total, :vote_count, :event_date, :event_location, :event_link, :event_time, :retracted_at, :retraction_class, :retraction_attribution)'
        );

        $removals = \ForumRewrite\Canonical\AcceptedRetraction::removals($this->repositoryRoot) + \ForumRewrite\Canonical\LabelRemoval::all($this->repositoryRoot);
        $retractions = $this->loadPostRetractions();
        $paths = $this->findRelativePaths('records/posts');
        $parsedPosts = [];
        $this->measure('resolve_legacy_post_creation_times', fn () => $this->canonicalRepository->preloadLegacyPostCreatedAts($paths));
        $this->measure('resolve_post_source_commits', fn () => $this->preloadSourceCommitShas($paths, 'records/posts'));

        foreach ($this->progressItems('parsing_post_records', $paths) as $index => $relativePath) {
            $record = $this->canonicalRepository->loadPost($relativePath);
            if (($removals[$record->postId]['operation'] ?? null) === 'delete') continue;
            $threadId = $record->threadId ?? $record->postId;
            // An author retraction suppresses prose at index time so no later ingest path can restore it.
            $retractedAt = $this->retractionTimestampFor($record, $retractions);
            $isRetracted = $retractedAt !== null;
            $parsedPosts[] = [
                'post_id' => $record->postId,
                'created_at' => $record->createdAt,
                'thread_id' => $threadId,
                'parent_id' => $record->parentId,
                'subject' => $isRetracted ? null : $record->subject,
                'body' => $isRetracted ? '' : $record->body,
                'retracted_at' => $retractedAt,
                'retraction_class' => $isRetracted ? $retractions[$record->postId]->classification : null,
                'retraction_attribution' => $isRetracted ? ($retractions[$record->postId]->attribution ?? $retractions[$record->postId]->classification) : null,
                'board_tags_json' => json_encode($record->boardTags, JSON_THROW_ON_ERROR),
                'thread_type' => $record->threadType,
                'agent_response_json' => $record->agentResponse === null ? null : json_encode($record->agentResponse, JSON_THROW_ON_ERROR),
                'author_identity_id' => $record->authorIdentityId,
                'imported_score_seed' => $record->importedScoreSeed,
                'imported_vote_count_seed' => $record->importedVoteCountSeed,
                'event_date' => $isRetracted ? null : $record->eventDate,
                'event_location' => $isRetracted ? null : $record->eventLocation,
                'event_link' => $isRetracted ? null : $record->eventLink,
                'event_time' => $isRetracted ? null : $record->eventTime,
                'source_path' => $relativePath,
                'source_commit_sha' => $this->sourceCommitShaForPath($relativePath),
                'source_order' => $index + 1,
            ];
        }

        foreach ($removals as $id => $removal) {
            if ($removal['operation'] !== 'delete') continue;
            $meta = $removal['post'];
            $parsedPosts[] = [
                'post_id' => $id, 'created_at' => $meta['created_at'], 'thread_id' => $meta['thread_id'],
                'parent_id' => $meta['parent_id'], 'subject' => null, 'body' => '',
                'retracted_at' => $removal['accepted_at'], 'retraction_class' => $removal['classification'] ?? 'operator', 'retraction_attribution' => $removal['attribution'] ?? 'operator',
                'board_tags_json' => '[]', 'thread_type' => $meta['thread_type'] ?? null, 'agent_response_json' => null,
                'author_identity_id' => $meta['author_identity_id'], 'imported_score_seed' => 0, 'imported_vote_count_seed' => 0,
                'event_date' => null, 'event_location' => null, 'event_link' => null, 'event_time' => null,
                'source_path' => isset($removal['signed_request']) ? \ForumRewrite\Canonical\AcceptedRetraction::path($id) : \ForumRewrite\Canonical\LabelRemoval::path($id), 'source_commit_sha' => null, 'source_order' => 0,
            ];
        }

        if ($this->excludedPublicationPosts !== []) {
            $parsedPosts = PublicationPostFilter::apply($parsedPosts, $this->excludedPublicationPosts);
        }

        $this->measure('sort_posts', function () use (&$parsedPosts): void {
            usort($parsedPosts, static function (array $left, array $right): int {
                if ($left['thread_id'] !== $right['thread_id']) {
                    return $left['thread_id'] <=> $right['thread_id'];
                }

                $leftIsRoot = $left['post_id'] === $left['thread_id'];
                $rightIsRoot = $right['post_id'] === $right['thread_id'];
                if ($leftIsRoot !== $rightIsRoot) {
                    return $leftIsRoot ? -1 : 1;
                }

                return $left['source_order'] <=> $right['source_order'];
            });
        });

        $posts = [];
        $threadSummaries = [];
        foreach ($this->progressItems('writing_post_records', $parsedPosts) as $index => $post) {
            $post['sequence_number'] = $index + 1;
            unset($post['source_order']);
            $posts[] = $post;
            $insertPost->execute([
                'post_id' => $post['post_id'],
                'created_at' => $post['created_at'],
                'thread_id' => $post['thread_id'],
                'parent_id' => $post['parent_id'],
                'subject' => $post['subject'],
                'body' => $post['body'],
                'board_tags_json' => $post['board_tags_json'],
                'thread_type' => $post['thread_type'],
                'agent_response_json' => $post['agent_response_json'],
                'author_identity_id' => $post['author_identity_id'],
                'retracted_at' => $post['retracted_at'],
                'retraction_class' => $post['retraction_class'],
                'retraction_attribution' => $post['retraction_attribution'],
                'sequence_number' => $post['sequence_number'],
            ]);

            if (!isset($threadSummaries[$post['thread_id']])) {
                $threadSummaries[$post['thread_id']] = [
                    'root_post_id' => $post['thread_id'],
                    'root_post_created_at' => $post['created_at'],
                    'last_activity_at' => $post['created_at'],
                    'subject' => $post['subject'],
                    'body_preview' => $this->preview($post['body']),
                    'reply_count' => 0,
                    'last_post_id' => $post['post_id'],
                    'board_tags_json' => $post['board_tags_json'],
                    'thread_labels_json' => '[]',
                    'score_total' => $post['imported_score_seed'] ?? 0,
                    'vote_count' => $post['imported_vote_count_seed'] ?? 0,
                    'event_date' => $post['event_date'],
                    'event_location' => $post['event_location'],
                    'event_link' => $post['event_link'],
                    'event_time' => $post['event_time'],
                    'retracted_at' => $post['retracted_at'],
                    'retraction_class' => $post['retraction_class'],
                    'retraction_attribution' => $post['retraction_attribution'],
                ];
            }

            $summary = &$threadSummaries[$post['thread_id']];
            if ($post['post_id'] !== $post['thread_id']) {
                $summary['reply_count']++;
            }
            if ($post['created_at'] > $summary['last_activity_at']) {
                $summary['last_activity_at'] = $post['created_at'];
            }
            $summary['last_post_id'] = $post['post_id'];
            unset($summary);
        }

        foreach ($this->progressItems('writing_thread_summaries', $threadSummaries) as $summary) {
            $insertThread->execute($summary);
        }

        return $posts;
    }

    /**
     * @return array<string, PostRetractionRecord>
     */
    private function loadPostRetractions(): array
    {
        // Legacy suppression is retired. Explicit migration converts accepted requests to deletion.
        return [];
    }

    /**
     * See RetractedPosts::applies() for when a retraction takes effect.
     *
     * @param array<string, PostRetractionRecord> $retractions
     */
    private function retractionTimestampFor(PostRecord $post, array $retractions): ?string
    {
        $retraction = $retractions[$post->postId] ?? null;

        return RetractedPosts::applies($post, $retraction) ? $retraction->createdAt : null;
    }

    /**
     * @param array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}> $approvalState
     */
    private function indexThreadLabels(PDO $pdo, array $approvalState): void
    {
        $rootThreadRows = $pdo->query('SELECT root_post_id, score_total, vote_count FROM threads')->fetchAll();
        $knownRootThreads = [];
        $seedScoreByThread = [];
        $seedVoteCountByThread = [];
        foreach ($rootThreadRows as $row) {
            $rootPostId = (string) $row['root_post_id'];
            $knownRootThreads[$rootPostId] = true;
            $seedScoreByThread[$rootPostId] = (int) $row['score_total'];
            $seedVoteCountByThread[$rootPostId] = (int) $row['vote_count'];
        }
        $records = [];
        $viewerTagRows = [];

        $inactive = \ForumRewrite\Canonical\LabelOwnership::inactive($this->repositoryRoot);
        $paths = $this->findRelativePaths('records/thread-labels');
        foreach ($this->progressItems('parsing_thread_label_records', $paths) as $index => $relativePath) {
            try {
                $record = $this->canonicalRepository->loadThreadLabel($relativePath);
                // viewer_tags mirrors ViewerTagLookup::threadTags()'s file scan, which applies
                // no inactive/publication exclusion: project every loaded record's labels.
                if ($record->authorIdentityId !== null) {
                    foreach ($record->labels as $label) {
                        $viewerTagRows[] = ['thread-labels', $record->threadId, $record->authorIdentityId, $label];
                    }
                }
                if (isset($this->excludedPublicationPosts[$record->sourcePostId ?? ''])) continue;
                if (!isset($inactive[$record->recordId])) $records[] = $record;
            } catch (CanonicalRecordParseException) {
                $this->invalidThreadLabelRecordCount++;
            }
        }
        $this->writeViewerTags($pdo, $viewerTagRows);

        $this->measure('sort_thread_labels', function () use (&$records): void {
            usort($records, static function (ThreadLabelRecord $left, ThreadLabelRecord $right): int {
                if ($left->createdAt !== $right->createdAt) {
                    return $left->createdAt <=> $right->createdAt;
                }

                return $left->recordId <=> $right->recordId;
            });
        });

        $this->measure('resolve_thread_label_source_commits', fn () => $this->preloadSourceCommitShas(
            array_map(static fn (ThreadLabelRecord $record): string => CanonicalPathResolver::threadLabel($record->recordId), $records),
            'records/thread-labels',
        ));

        $labelsByThread = [];
        $scoreByThread = [];
        $countedApprovedScoredTagsByThread = [];
        $verifiedScoreByThread = [];
        $countedVerifiedScoredTagsByThread = [];
        $voteCountByThread = [];
        $countedVoteTagsByThread = [];
        foreach ($this->progressItems('deriving_thread_label_records', $records) as $record) {
            if (!isset($knownRootThreads[$record->threadId])) {
                $this->invalidThreadLabelRecordCount++;
                continue;
            }

            $labelsByThread[$record->threadId] ??= [];
            $scoreByThread[$record->threadId] ??= $seedScoreByThread[$record->threadId] ?? 0;
            $countedApprovedScoredTagsByThread[$record->threadId] ??= [];
            $verifiedScoreByThread[$record->threadId] ??= $seedScoreByThread[$record->threadId] ?? 0;
            $countedVerifiedScoredTagsByThread[$record->threadId] ??= [];
            $recordCountsVerified = null;
            $voteCountByThread[$record->threadId] ??= $seedVoteCountByThread[$record->threadId] ?? 0;
            $countedVoteTagsByThread[$record->threadId] ??= [];
            $labelsAdded = [];
            foreach ($record->labels as $label) {
                if (!isset($labelsByThread[$record->threadId][$label])) {
                    $labelsByThread[$record->threadId][$label] = true;
                    $labelsAdded[] = $label;
                } elseif ($label === 'like') {
                    // Activity records contributions, even when the effective label already exists.
                    $labelsAdded[] = $label;
                }

                if ($record->authorIdentityId !== null && $this->countsThreadLabelTowardVoteTotal($label)) {
                    $voteDedupeKey = $this->threadVoteDedupeKey($record->authorIdentityId, $label);
                    if (!isset($countedVoteTagsByThread[$record->threadId][$voteDedupeKey])) {
                        $countedVoteTagsByThread[$record->threadId][$voteDedupeKey] = true;
                        $voteCountByThread[$record->threadId]++;
                    }
                }

                if ($record->authorIdentityId === null || !isset($approvalState[$record->authorIdentityId]) || !$this->isScoredThreadLabel($label)) {
                    continue;
                }

                $dedupeKey = $this->threadVoteDedupeKey($record->authorIdentityId, $label);
                $recordCountsVerified ??= VerifiedAttribution::countsThreadLabel($this->repositoryRoot, $this->attributionCutoff, $record);
                if ($recordCountsVerified && !isset($countedVerifiedScoredTagsByThread[$record->threadId][$dedupeKey])) {
                    $countedVerifiedScoredTagsByThread[$record->threadId][$dedupeKey] = true;
                    $verifiedScoreByThread[$record->threadId] += $this->threadLabelScoreValue($label);
                }
                if (isset($countedApprovedScoredTagsByThread[$record->threadId][$dedupeKey])) {
                    continue;
                }

                $countedApprovedScoredTagsByThread[$record->threadId][$dedupeKey] = true;
                $scoreByThread[$record->threadId] += $this->threadLabelScoreValue($label);
            }

            if ($labelsAdded !== []) {
                sort($labelsAdded);
                $this->threadLabelActivityEvents[] = [
                    'created_at' => $record->createdAt,
                    'thread_id' => $record->threadId,
                    'author_identity_id' => $record->authorIdentityId,
                    'labels_added' => $labelsAdded,
                    'source_path' => CanonicalPathResolver::threadLabel($record->recordId),
                    'source_commit_sha' => $this->sourceCommitShaForPath(CanonicalPathResolver::threadLabel($record->recordId)),
                ];
            }
        }

        $updateThread = $pdo->prepare(
            'UPDATE threads
             SET thread_labels_json = :thread_labels_json,
                 score_total = :score_total,
                 claimed_score_total = :score_total,
                 verified_score_total = :verified_score_total,
                 vote_count = :vote_count
             WHERE root_post_id = :root_post_id'
        );

        foreach ($this->progressItems('writing_thread_labels', $knownRootThreads) as $threadId => $_present) {
            $labels = array_keys($labelsByThread[$threadId] ?? []);
            sort($labels);
            $updateThread->execute([
                'thread_labels_json' => json_encode($labels, JSON_THROW_ON_ERROR),
                'score_total' => $scoreByThread[$threadId] ?? ($seedScoreByThread[$threadId] ?? 0),
                'verified_score_total' => $verifiedScoreByThread[$threadId] ?? ($seedScoreByThread[$threadId] ?? 0),
                'vote_count' => $voteCountByThread[$threadId] ?? ($seedVoteCountByThread[$threadId] ?? 0),
                'root_post_id' => $threadId,
            ]);
        }
    }

    private function indexThreadSubjects(PDO $pdo): void
    {
        $rootThreadRows = $pdo->query('SELECT root_post_id, subject, retracted_at FROM threads')->fetchAll();
        $knownRootThreads = [];
        $currentSubjectByThread = [];
        $retractedThreads = [];
        foreach ($rootThreadRows as $row) {
            $rootPostId = (string) $row['root_post_id'];
            $knownRootThreads[$rootPostId] = true;
            $currentSubjectByThread[$rootPostId] = $row['subject'];
            if ($row['retracted_at'] !== null) {
                $retractedThreads[$rootPostId] = true;
            }
        }

        $records = [];
        $paths = $this->findRelativePaths('records/thread-subjects');
        foreach ($this->progressItems('parsing_thread_subject_records', $paths) as $index => $relativePath) {
            try {
                $records[] = $this->canonicalRepository->loadThreadSubject($relativePath);
            } catch (CanonicalRecordParseException) {
                $this->invalidThreadSubjectRecordCount++;
            }
        }

        $this->measure('sort_thread_subjects', function () use (&$records): void {
            usort($records, static function (ThreadSubjectRecord $left, ThreadSubjectRecord $right): int {
                if ($left->createdAt !== $right->createdAt) {
                    return $left->createdAt <=> $right->createdAt;
                }

                return $left->recordId <=> $right->recordId;
            });
        });

        $resolvedSubjectByThread = [];
        foreach ($this->progressItems('deriving_thread_subject_records', $records) as $record) {
            if (!isset($knownRootThreads[$record->threadId])) {
                $this->invalidThreadSubjectRecordCount++;
                continue;
            }

            if (isset($retractedThreads[$record->threadId])) {
                continue;
            }

            $currentSubject = $currentSubjectByThread[$record->threadId];
            if ($currentSubject !== null && trim((string) $currentSubject) !== '') {
                continue;
            }

            if (isset($resolvedSubjectByThread[$record->threadId])) {
                continue;
            }

            $resolvedSubjectByThread[$record->threadId] = $record->subject;
        }

        if ($resolvedSubjectByThread === []) {
            return;
        }

        $updateThread = $pdo->prepare('UPDATE threads SET subject = :subject WHERE root_post_id = :root_post_id');
        $updatePost = $pdo->prepare('UPDATE posts SET subject = :subject WHERE post_id = :post_id');
        foreach ($this->progressItems('writing_thread_subjects', $resolvedSubjectByThread) as $threadId => $subject) {
            $updateThread->execute([
                'subject' => $subject,
                'root_post_id' => $threadId,
            ]);
            $updatePost->execute([
                'subject' => $subject,
                'post_id' => $threadId,
            ]);
        }
    }

    /**
     * @param array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}> $approvalState
     */
    private function indexPostReactions(PDO $pdo, array $approvalState): void
    {
        $postRows = $pdo->query('SELECT post_id, thread_id, author_label, board_tags_json FROM posts')->fetchAll();
        $knownPosts = [];
        foreach ($postRows as $row) {
            $knownPosts[(string) $row['post_id']] = [
                'thread_id' => (string) $row['thread_id'],
                'author_label' => (string) $row['author_label'],
                'board_tags_json' => (string) $row['board_tags_json'],
            ];
        }

        $records = [];
        $viewerTagRows = [];
        $paths = $this->findRelativePaths('records/post-reactions');
        foreach ($this->progressItems('parsing_post_reaction_records', $paths) as $index => $relativePath) {
            try {
                $record = $this->canonicalRepository->loadPostReaction($relativePath);
                $records[] = $record;
                // viewer_tags mirrors ViewerTagLookup::postTags()'s file scan over every loaded record.
                if ($record->authorIdentityId !== null) {
                    foreach ($record->tags as $tag) {
                        $viewerTagRows[] = ['post-reactions', $record->postId, $record->authorIdentityId, $tag];
                    }
                }
            } catch (CanonicalRecordParseException) {
                $this->invalidPostReactionRecordCount++;
                continue;
            }
        }
        $this->writeViewerTags($pdo, $viewerTagRows);

        $this->measure('sort_post_reactions', function () use (&$records): void {
            usort($records, static function (PostReactionRecord $left, PostReactionRecord $right): int {
                if ($left->createdAt !== $right->createdAt) {
                    return $left->createdAt <=> $right->createdAt;
                }

                return $left->recordId <=> $right->recordId;
            });
        });

        $this->measure('resolve_post_reaction_source_commits', fn () => $this->preloadSourceCommitShas(
            array_map(static fn (PostReactionRecord $record): string => CanonicalPathResolver::postReaction($record->recordId), $records),
            'records/post-reactions',
        ));

        $tagsByPost = [];
        $scoreByPost = [];
        $flaggersByPost = [];
        $countedApprovedScoredTagsByPost = [];
        $verifiedScoreByPost = [];
        $verifiedFlaggersByPost = [];
        $countedVerifiedScoredTagsByPost = [];
        foreach ($this->progressItems('deriving_post_reaction_records', $records) as $record) {
            if (!isset($knownPosts[$record->postId])) {
                continue;
            }

            $this->postReactionActivityEvents[] = [
                'created_at' => $record->createdAt,
                'post_id' => $record->postId,
                'thread_id' => $knownPosts[$record->postId]['thread_id'],
                'author_identity_id' => $record->authorIdentityId,
                'tags' => $record->tags,
                'board_tags_json' => $knownPosts[$record->postId]['board_tags_json'],
                'source_path' => CanonicalPathResolver::postReaction($record->recordId),
                'source_commit_sha' => $this->sourceCommitShaForPath(CanonicalPathResolver::postReaction($record->recordId)),
            ];

            $tagsByPost[$record->postId] ??= [];
            $scoreByPost[$record->postId] ??= 0;
            $flaggersByPost[$record->postId] ??= [];
            $countedApprovedScoredTagsByPost[$record->postId] ??= [];
            $verifiedScoreByPost[$record->postId] ??= 0;
            $verifiedFlaggersByPost[$record->postId] ??= [];
            $countedVerifiedScoredTagsByPost[$record->postId] ??= [];
            $recordCountsVerified = null;
            foreach ($record->tags as $tag) {
                $tagsByPost[$record->postId][$tag] = true;

                if ($record->authorIdentityId === null || !isset($approvalState[$record->authorIdentityId])) {
                    continue;
                }

                $recordCountsVerified ??= VerifiedAttribution::counts($this->repositoryRoot, $this->attributionCutoff, CanonicalPathResolver::postReaction($record->recordId), $record->authorIdentityId, $record->createdAt);
                if ($tag === 'flag') {
                    $flaggersByPost[$record->postId][$record->authorIdentityId] = true;
                    if ($recordCountsVerified) {
                        $verifiedFlaggersByPost[$record->postId][$record->authorIdentityId] = true;
                    }
                }

                if (!TagScore::isScoredTag($tag)) {
                    continue;
                }

                $dedupeKey = $record->authorIdentityId . ':' . $tag;
                if ($recordCountsVerified && !isset($countedVerifiedScoredTagsByPost[$record->postId][$dedupeKey])) {
                    $countedVerifiedScoredTagsByPost[$record->postId][$dedupeKey] = true;
                    $verifiedScoreByPost[$record->postId] += TagScore::scoreValueForTag($tag);
                }
                if (isset($countedApprovedScoredTagsByPost[$record->postId][$dedupeKey])) {
                    continue;
                }

                $countedApprovedScoredTagsByPost[$record->postId][$dedupeKey] = true;
                $scoreByPost[$record->postId] += TagScore::scoreValueForTag($tag);
            }
        }

        $updatePost = $pdo->prepare(
            'UPDATE posts
             SET post_tags_json = :post_tags_json,
                 post_score_total = :post_score_total,
                 approved_flag_count = :approved_flag_count,
                 claimed_post_score_total = :post_score_total,
                 verified_post_score_total = :verified_post_score_total,
                 claimed_approved_flag_count = :approved_flag_count,
                 verified_approved_flag_count = :verified_approved_flag_count,
                 is_hidden = :is_hidden,
                 hidden_reason = :hidden_reason
             WHERE post_id = :post_id'
        );

        foreach ($this->progressItems('writing_post_reactions', $knownPosts) as $postId => $post) {
            $tags = array_keys($tagsByPost[$postId] ?? []);
            sort($tags);
            $approvedFlagCount = count($flaggersByPost[$postId] ?? []);
            $isHidden = $approvedFlagCount > 0 && $post['author_label'] === 'reply-agent';
            $updatePost->execute([
                'post_tags_json' => json_encode($tags, JSON_THROW_ON_ERROR),
                'post_score_total' => $scoreByPost[$postId] ?? 0,
                'approved_flag_count' => $approvedFlagCount,
                'verified_post_score_total' => $verifiedScoreByPost[$postId] ?? 0,
                'verified_approved_flag_count' => count($verifiedFlaggersByPost[$postId] ?? []),
                'is_hidden' => $isHidden ? 1 : 0,
                'hidden_reason' => $isHidden ? 'approved_flagged_reply_agent' : null,
                'post_id' => $postId,
            ]);
        }
    }

    /**
     * Insert projected viewer-tag rows (record_family, target_id, author_identity_id, tag).
     * INSERT OR IGNORE dedupes repeat (identity, target, tag) across multiple records,
     * matching the set semantics of the file-scan lookups.
     *
     * @param list<array{0:string,1:string,2:string,3:string}> $rows
     */
    private function writeViewerTags(PDO $pdo, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO viewer_tags (record_family, target_id, author_identity_id, tag)
             VALUES (:record_family, :target_id, :author_identity_id, :tag)'
        );
        foreach ($rows as [$recordFamily, $targetId, $authorIdentityId, $tag]) {
            $insert->execute([
                'record_family' => $recordFamily,
                'target_id' => $targetId,
                'author_identity_id' => $authorIdentityId,
                'tag' => $tag,
            ]);
        }
    }

    /**
     * @return array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}>
     */
    private function indexProfiles(PDO $pdo): array
    {
        $insertProfile = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key
             )'
        );
        $insertUsernameRoute = $pdo->prepare(
            'INSERT INTO username_routes (username_token, identity_id) VALUES (:username_token, :identity_id)'
        );

        $paths = $this->findRelativePaths('records/identity');
        $nameHistory = ProfileNames::history($this->repositoryRoot);
        $profiles = [];
        $claimedUsernameTokens = [];
        foreach ($this->progressItems('parsing_identity_records', $paths) as $index => $relativePath) {
            $identity = $this->canonicalRepository->loadIdentity($relativePath);
            $publicKeyPath = 'records/public-keys/openpgp-' . $identity->signerFingerprint . '.asc';
            if (!is_file($this->repositoryRoot . '/' . $publicKeyPath)) {
                $publicKeyPath = 'records/public-keys/openpgp-' . strtolower($identity->signerFingerprint) . '.asc';
            }

            $publicKey = $this->canonicalRepository->loadPublicKey($publicKeyPath);
            $bootstrapUsername = $identity->username !== '' ? $identity->username : 'guest';
            $previousUsername = $bootstrapUsername;
            foreach ($nameHistory[$identity->identityId] ?? [] as $nameRecord) {
                $namePath = 'records/profile-names/' . $nameRecord['Record-ID'] . '.txt';
                $this->profileNameActivityEvents[] = [
                    'created_at' => $nameRecord['Created-At'],
                    'identity_id' => $identity->identityId,
                    'previous_username' => $previousUsername,
                    'username' => $nameRecord['Username'],
                    'source_path' => $namePath,
                    'source_commit_sha' => $this->sourceCommitShaForPath($namePath),
                ];
                $previousUsername = $nameRecord['Username'];
            }
            $username = $previousUsername;
            $usernameToken = strtolower($username);
            $fallbackLabel = $username . '-' . substr(strtolower($identity->signerFingerprint), 0, 8);
            $profile = [
                'identity_id' => $identity->identityId,
                'profile_slug' => $identity->identitySlug(),
                'username' => $username,
                'username_token' => $usernameToken,
                'fallback_label' => $fallbackLabel,
                'signer_fingerprint' => $identity->signerFingerprint,
                'bootstrap_post_id' => $identity->bootstrapByPost,
                'bootstrap_thread_id' => $identity->bootstrapByThread,
                'public_key' => $publicKey->armoredKey,
            ];

            $insertProfile->execute($profile);
            if (!isset($claimedUsernameTokens[$usernameToken])) {
                $insertUsernameRoute->execute([
                    'username_token' => $usernameToken,
                    'identity_id' => $identity->identityId,
                ]);
                $claimedUsernameTokens[$usernameToken] = true;
            }

            $profiles[$identity->identityId] = [
                'identity_id' => $identity->identityId,
                'profile_slug' => $identity->identitySlug(),
                'username' => $username,
                'username_token' => $usernameToken,
                'bootstrap_post_id' => $identity->bootstrapByPost,
                'bootstrap_thread_id' => $identity->bootstrapByThread,
            ];
        }

        return $profiles;
    }

    /**
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @param array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}> $approvalState
     * @param array<string, array{post_count:int,thread_count:int}> $profileCounts
     */
    private function linkPostAuthors(PDO $pdo, array $profiles, array $approvalState, array $profileCounts, array $postAttribution): void
    {
        $markUnverified = $pdo->prepare('UPDATE posts SET attribution_verified = 0 WHERE post_id = ?');
        foreach (array_keys(array_filter($postAttribution, static fn (bool $verified): bool => !$verified)) as $postId) {
            $markUnverified->execute([$postId]);
        }
        $updatePost = $pdo->prepare(
            'UPDATE posts
             SET author_identity_id = :author_identity_id, author_profile_slug = :author_profile_slug, author_label = :author_label
             WHERE post_id = :bootstrap_post_id OR author_identity_id = :author_identity_id'
        );
        $updateProfileCounts = $pdo->prepare(
            'UPDATE profiles
             SET is_approved = :is_approved,
                 approved_by_identity_id = :approved_by_identity_id,
                 approved_by_profile_slug = :approved_by_profile_slug,
                 approved_by_label = :approved_by_label,
                 post_count = :post_count,
                 thread_count = :thread_count,
                 claimed_post_count = :post_count,
                 claimed_thread_count = :thread_count,
                 verified_post_count = :verified_post_count,
                 verified_thread_count = :verified_thread_count
             WHERE identity_id = :identity_id'
        );

        foreach ($this->progressItems('linking_author_profiles', $profiles) as $profile) {
            $updatePost->execute([
                'author_identity_id' => $profile['identity_id'],
                'author_profile_slug' => $profile['profile_slug'],
                'author_label' => $profile['username'],
                'bootstrap_post_id' => $profile['bootstrap_post_id'],
            ]);

            $counts = $profileCounts[$profile['identity_id']] ?? ['post_count' => 0, 'thread_count' => 0, 'verified_post_count' => 0, 'verified_thread_count' => 0];
            $approval = $approvalState[$profile['identity_id']] ?? [
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => null,
            ];
            $updateProfileCounts->execute([
                'is_approved' => isset($approvalState[$profile['identity_id']]) ? 1 : 0,
                'approved_by_identity_id' => $approval['approved_by_identity_id'],
                'approved_by_profile_slug' => $approval['approved_by_profile_slug'],
                'approved_by_label' => $approval['approved_by_label'],
                'post_count' => $counts['post_count'],
                'thread_count' => $counts['thread_count'],
                'verified_post_count' => $counts['verified_post_count'],
                'verified_thread_count' => $counts['verified_thread_count'],
                'identity_id' => $profile['identity_id'],
            ]);
        }
    }

    private function indexInstance(PDO $pdo): void
    {
        $instance = $this->canonicalRepository->loadInstancePublic('records/instance/public.txt');
        $stmt = $pdo->prepare(
            'INSERT INTO instance_public (
                singleton, instance_name, admin_name, admin_contact, retention_policy, install_date, body
             ) VALUES (
                1, :instance_name, :admin_name, :admin_contact, :retention_policy, :install_date, :body
             )'
        );
        $stmt->execute([
            'instance_name' => $instance->headers['Instance-Name'],
            'admin_name' => $instance->headers['Admin-Name'],
            'admin_contact' => $instance->headers['Admin-Contact'],
            'retention_policy' => $instance->headers['Retention-Policy'],
            'install_date' => $instance->headers['Install-Date'],
            'body' => $instance->body,
        ]);
    }

    /**
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,source_path:string,source_commit_sha:?string,sequence_number:int}> $posts
     */
    private function indexActivity(PDO $pdo, array $posts): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO activity (
                created_at, kind, record_family, action_key, post_id, thread_id, label, board_tags_json,
                author_identity_id, author_profile_slug, author_username_token, author_label, author_is_approved,
                source_path, source_commit_sha
             ) VALUES (
                :created_at, :kind, :record_family, :action_key, :post_id, :thread_id, :label, :board_tags_json,
                :author_identity_id, :author_profile_slug, :author_username_token, :author_label, :author_is_approved,
                :source_path, :source_commit_sha
             )'
        );
        $postsById = [];

        foreach ($this->progressItems('writing_post_activity', $posts) as $post) {
            $postsById[$post['post_id']] = $post;
            $author = $this->resolveActivityAuthor($pdo, $post['author_identity_id']);
            $recordFamily = $this->activityRecordFamilyForPost($post);
            $kind = match ($recordFamily) {
                'identity_bootstrap' => 'identity_bootstrap',
                'approval' => 'approval',
                'identity' => 'identity',
                'invitation' => 'invitation',
                default => $post['post_id'] === $post['thread_id'] ? 'thread' : 'reply',
            };
            $label = $this->activityLabelForPost($post);
            $receipt = in_array($kind, ['approval', 'invitation'], true)
                ? \ForumRewrite\Security\ApprovalAcceptance::read($this->repositoryRoot, $post['source_path']) : null;
            $stmt->execute([
                'created_at' => $receipt['accepted_at'] ?? $post['created_at'],
                'kind' => $kind,
                'record_family' => $recordFamily,
                'action_key' => $post['source_path'],
                'post_id' => $post['post_id'],
                'thread_id' => $post['thread_id'],
                'label' => $label,
                'board_tags_json' => $post['board_tags_json'],
                'author_identity_id' => $post['author_identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
                'source_path' => $post['source_path'],
                'source_commit_sha' => $post['source_commit_sha'],
            ]);
        }

        foreach ($this->progressItems('writing_thread_label_activity', $this->threadLabelActivityEvents) as $event) {
            $thread = $postsById[$event['thread_id']] ?? null;
            if ($thread === null) {
                continue;
            }

            $author = $this->resolveActivityAuthor($pdo, $event['author_identity_id']);
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'thread_label_add',
                'record_family' => 'thread_label',
                'action_key' => $event['source_path'],
                'post_id' => $event['thread_id'],
                'thread_id' => $event['thread_id'],
                'label' => 'Labels added: ' . implode(', ', $event['labels_added']),
                'board_tags_json' => $thread['board_tags_json'],
                'author_identity_id' => $event['author_identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
                'source_path' => $event['source_path'],
                'source_commit_sha' => $event['source_commit_sha'],
            ]);
        }

        foreach ($this->progressItems('writing_post_reaction_activity', $this->postReactionActivityEvents) as $event) {
            $author = $this->resolveActivityAuthor($pdo, $event['author_identity_id']);
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'post_reaction_add',
                'record_family' => 'post_reaction',
                'action_key' => $event['source_path'],
                'post_id' => $event['post_id'],
                'thread_id' => $event['thread_id'],
                'label' => 'Tags added: ' . implode(', ', $event['tags']),
                'board_tags_json' => $event['board_tags_json'],
                'author_identity_id' => $event['author_identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
                'source_path' => $event['source_path'],
                'source_commit_sha' => $event['source_commit_sha'],
            ]);
        }

        foreach ($this->progressItems('writing_profile_name_activity', $this->profileNameActivityEvents) as $event) {
            $author = $this->resolveActivityAuthor($pdo, $event['identity_id']);
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'profile_name_change',
                'record_family' => 'profile_name',
                'action_key' => $event['source_path'],
                'post_id' => null,
                'thread_id' => null,
                'label' => 'Changed username from ' . $event['previous_username'] . ' to ' . $event['username'],
                'board_tags_json' => '["identity","profile-name"]',
                'author_identity_id' => $event['identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
                'source_path' => $event['source_path'],
                'source_commit_sha' => $event['source_commit_sha'],
            ]);
        }

        $signedFeatureFlagEvents = $this->measure('derive_signed_flag_activity', fn () => $this->featureFlagChangeActivityEvents());
        $signedCommitShas = [];
        foreach ($this->progressItems('writing_signed_flag_activity', $signedFeatureFlagEvents) as $event) {
            $signedCommitShas[$event['source_commit_sha']] = true;
            $author = $this->resolveActivityAuthor($pdo, $event['operator_identity_id']);
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'site_feature_flag',
                'record_family' => 'instance_feature_flags',
                'action_key' => $event['source_path'] . '@' . $event['source_commit_sha'],
                'post_id' => null,
                'thread_id' => null,
                'label' => 'Set feature flag ' . $event['flag_key'] . '=' . ($event['value'] ? 'true' : 'false'),
                'board_tags_json' => '["site"]',
                'author_identity_id' => $event['operator_identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
                'source_path' => $event['source_path'],
                'source_commit_sha' => $event['source_commit_sha'],
            ]);
        }

        foreach ($this->progressItems('writing_legacy_flag_activity', $this->measure('read_legacy_flag_activity', fn () => $this->featureFlagActivityEvents($signedCommitShas))) as $event) {
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'site_feature_flag',
                'record_family' => 'instance_feature_flags',
                'action_key' => CanonicalPathResolver::featureFlags() . '@' . $event['source_commit_sha'],
                'post_id' => null,
                'thread_id' => null,
                'label' => $event['label'],
                'board_tags_json' => '["site"]',
                'author_identity_id' => null,
                'author_profile_slug' => null,
                'author_username_token' => null,
                'author_label' => 'site configuration',
                'author_is_approved' => 1,
                'source_path' => CanonicalPathResolver::featureFlags(),
                'source_commit_sha' => $event['source_commit_sha'],
            ]);
        }

        foreach ($this->progressItems('writing_handoff_activity', $this->codexHandoffActivityEvents($pdo)) as $event) {
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'codex_handoff',
                'record_family' => 'codex_handoff',
                'action_key' => null,
                'post_id' => $event['origin_post_id'],
                'thread_id' => $event['origin_thread_id'],
                'label' => $event['label'],
                'board_tags_json' => '["codex","handoff"]',
                'author_identity_id' => $event['author_identity_id'],
                'author_profile_slug' => $event['author_profile_slug'],
                'author_username_token' => $event['author_username_token'],
                'author_label' => $event['author_label'],
                'author_is_approved' => $event['author_is_approved'],
                'source_path' => null,
                'source_commit_sha' => null,
            ]);
        }
    }

    /**
     * Populates the `commits` table from a single `git log` invocation
     * (not one call per commit - the per-commit `git diff-tree` cost this
     * app already pays elsewhere is exactly what this table exists to
     * avoid paying at request time). Uses `\x1f` (unit separator) to split
     * fields, since it can't appear in a commit subject, and `--shortstat`
     * for file counts, tolerating commits with no stat line (e.g. merges
     * with no changes) by defaulting file_count to 0 for those.
     */
    private function indexCommits(PDO $pdo): void
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            return;
        }

        $delimiter = "\x1f";
        $command = sprintf(
            'git -C %s log --format=%s --date=format:%s --shortstat 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg('%H' . $delimiter . '%an' . $delimiter . '%ae' . $delimiter . '%cd' . $delimiter . '%s'),
            escapeshellarg('%Y-%m-%dT%H:%M:%SZ'),
        );
        $output = [];
        $exitCode = 0;
        $this->measure('read_commit_history', static function () use ($command, &$output, &$exitCode): bool {
            exec($command, $output, $exitCode);
            return $exitCode === 0;
        });
        if ($exitCode !== 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO commits (sha, author_name, author_email, committed_at, subject, file_count)
             VALUES (:sha, :author_name, :author_email, :committed_at, :subject, :file_count)'
        );

        $pendingRow = null;
        foreach ($this->progressItems('processing_commit_history_lines', $output) as $line) {
            $fields = explode($delimiter, $line);
            if (count($fields) === 5 && preg_match('/^[0-9a-f]{40}$/', $fields[0]) === 1) {
                if ($pendingRow !== null) {
                    $insert->execute($pendingRow);
                }
                [$sha, $authorName, $authorEmail, $committedAt, $subject] = $fields;
                $pendingRow = [
                    'sha' => $sha,
                    'author_name' => $authorName,
                    'author_email' => $authorEmail,
                    'committed_at' => $committedAt,
                    'subject' => $subject,
                    'file_count' => 0,
                ];
                continue;
            }

            if ($pendingRow !== null && preg_match('/^\s*(\d+)\s+files?\s+changed/', $line, $statMatches) === 1) {
                $pendingRow['file_count'] = (int) $statMatches[1];
            }
        }

        if ($pendingRow !== null) {
            $insert->execute($pendingRow);
        }
    }

    /**
     * @return array{author_profile_slug:?string,author_username_token:?string,author_label:string,author_is_approved:int}
     */
    private function resolveActivityAuthor(PDO $pdo, ?string $identityId): array
    {
        if ($identityId === null) {
            return [
                'author_profile_slug' => null,
                'author_username_token' => null,
                'author_label' => 'guest',
                'author_is_approved' => 0,
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT profile_slug, username, username_token, COALESCE(is_approved, 0) AS is_approved
             FROM profiles WHERE identity_id = :identity_id'
        );
        $stmt->execute(['identity_id' => $identityId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return [
                'author_profile_slug' => null,
                'author_username_token' => null,
                'author_label' => $identityId,
                'author_is_approved' => 0,
            ];
        }

        return [
            'author_profile_slug' => $row['profile_slug'] !== null ? (string) $row['profile_slug'] : null,
            'author_username_token' => $row['username_token'] !== null ? (string) $row['username_token'] : null,
            'author_label' => (string) $row['username'],
            'author_is_approved' => (int) $row['is_approved'],
        ];
    }

    /**
     * @param array{board_tags_json:string} $post
     */
    private function activityRecordFamilyForPost(array $post): string
    {
        $boardTags = json_decode($post['board_tags_json'], true);
        if (!is_array($boardTags) || !in_array('identity', $boardTags, true)) {
            return 'post';
        }

        if (in_array('invitation', $boardTags, true)) {
            return 'invitation';
        }

        if (in_array('internal', $boardTags, true)) {
            return 'identity_bootstrap';
        }

        if (in_array('approval', $boardTags, true)) {
            return 'approval';
        }

        return 'identity';
    }

    /** @param array{subject:?string,body:string,board_tags_json:string} $post */
    private function activityLabelForPost(array $post): string
    {
        if (($post['retracted_at'] ?? null) !== null) {
            return 'Deleted post';
        }

        $boardTags = json_decode($post['board_tags_json'], true);
        if (is_array($boardTags) && in_array('invitation', $boardTags, true)
            && preg_match('/^Invitation-Action: (issue|revoke|redeem)$/m', $post['body'], $action)
            && preg_match('/^Verification-Hash: (sha256:[a-f0-9]{64})$/m', $post['body'], $hash)) {
            return 'Invitation ' . $action[1] . ': ' . $hash[1];
        }

        return $post['subject'] ?? $this->preview($post['body']);
    }

    private function writeMetadata(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO metadata (key, value) VALUES (:key, :value)');
        $metadata = array_merge(ReadModelMetadata::expectedSchemaIdentity(), [
            'repository_root' => $this->repositoryRoot,
            'repository_head' => ReadModelMetadata::repositoryHead($this->repositoryRoot),
            'rebuilt_at' => gmdate('c'),
            'rebuild_reason' => $this->rebuildReason,
            'thread_label_invalid_count' => (string) $this->invalidThreadLabelRecordCount,
            'post_reaction_invalid_count' => (string) $this->invalidPostReactionRecordCount,
            'thread_subject_invalid_count' => (string) $this->invalidThreadSubjectRecordCount,
        ]);

        if ($metadata['repository_head'] === 'no-git') {
            $metadata['canonical_fingerprint'] = ReadModelMetadata::canonicalFingerprint($this->repositoryRoot);
        }

        foreach ($metadata as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function findRelativePaths(string $relativeDirectory): array
    {
        $basePath = $this->repositoryRoot . '/' . $relativeDirectory;
        if (!is_dir($basePath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );
        $paths = [];
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relativePath = substr($item->getPathname(), strlen($this->repositoryRoot) + 1);
            if (!str_ends_with($relativePath, '.txt')) {
                continue;
            }

            $paths[] = $relativePath;
        }

        sort($paths);

        return $paths;
    }

    private function preview(string $body): string
    {
        $line = strtok($body, "\n");
        return $line === false ? '' : $line;
    }

    private function sourceCommitShaForPath(string $relativePath): ?string
    {
        if (array_key_exists($relativePath, $this->sourceCommitShaByPath)) {
            return $this->sourceCommitShaByPath[$relativePath];
        }

        if (!is_dir($this->repositoryRoot . '/.git')) {
            $this->sourceCommitShaByPath[$relativePath] = null;
            return null;
        }

        $command = sprintf(
            'git -C %s log -1 --format=%%H -- %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($relativePath),
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $commitSha = trim((string) ($output[0] ?? ''));
        $this->sourceCommitShaByPath[$relativePath] = $exitCode === 0
            && preg_match('/^[A-Fa-f0-9]{40}$/', $commitSha) === 1
            ? $commitSha
            : null;

        return $this->sourceCommitShaByPath[$relativePath];
    }

    /**
     * Resolves the latest commit for every requested path with one Git history
     * walk. Calling `git log -1` once per post makes a large rebuild spend most
     * of its time starting Git processes rather than parsing canonical records.
     *
     * @param list<string> $relativePaths
     */
    private function preloadSourceCommitShas(array $relativePaths, string $pathspec): void
    {
        if ($relativePaths === [] || !is_dir($this->repositoryRoot . '/.git')) {
            return;
        }

        $unresolvedPaths = [];
        foreach ($relativePaths as $relativePath) {
            if (!array_key_exists($relativePath, $this->sourceCommitShaByPath)) {
                $unresolvedPaths[$relativePath] = true;
            }
        }

        if ($unresolvedPaths === []) {
            return;
        }

        $command = sprintf(
            'git -C %s log %s --format=%%H --name-only -- %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            // Combined diffs include merge resolutions, but not paths unchanged
            // from a parent. Preserve the established post-history behavior.
            $pathspec === 'records/posts' ? '' : '-c',
            escapeshellarg($pathspec),
        );
        $output = [];
        $exitCode = 0;
        $this->measure('read_source_history', static function () use ($command, &$output, &$exitCode): bool {
            exec($command, $output, $exitCode);
            return $exitCode === 0;
        });

        if ($exitCode !== 0) {
            foreach (array_keys($unresolvedPaths) as $relativePath) {
                $this->sourceCommitShaByPath[$relativePath] = null;
            }

            return;
        }

        $commitSha = null;
        foreach ($output as $line) {
            if (preg_match('/^[A-Fa-f0-9]{40}$/', $line) === 1) {
                $commitSha = strtolower($line);
                continue;
            }

            if ($commitSha === null || !isset($unresolvedPaths[$line])) {
                continue;
            }

            $this->sourceCommitShaByPath[$line] = $commitSha;
            unset($unresolvedPaths[$line]);
        }

        foreach (array_keys($unresolvedPaths) as $relativePath) {
            $this->sourceCommitShaByPath[$relativePath] = null;
        }
    }

    /**
     * @param array<string, true> $excludedCommitShas
     * @return list<array{created_at:string,label:string,source_commit_sha:string}>
     */
    private function featureFlagActivityEvents(array $excludedCommitShas = []): array
    {
        $path = CanonicalPathResolver::featureFlags();
        if (!is_file($this->repositoryRoot . '/' . $path) || !is_dir($this->repositoryRoot . '/.git')) {
            return [];
        }

        $command = [
            'git',
            '-C',
            $this->repositoryRoot,
            'log',
            '--format=%cI%x00%H%x00%s',
            '--',
            $path,
        ];
        $output = [];
        $exitCode = 0;
        exec(implode(' ', array_map('escapeshellarg', $command)) . ' 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            return [];
        }

        $events = [];
        foreach ($output as $line) {
            $parts = explode("\0", $line, 3);
            if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            if (isset($excludedCommitShas[$parts[1]])) {
                continue;
            }

            $subject = trim($parts[2]);
            $events[] = [
                'created_at' => $parts[0],
                'label' => $subject !== '' ? $subject : 'Updated site feature flags',
                'source_commit_sha' => $parts[1],
            ];
        }

        return $events;
    }

    /**
     * @return list<array{created_at:string,flag_key:string,value:bool,operator_identity_id:string,source_path:string,source_commit_sha:string}>
     */
    private function featureFlagChangeActivityEvents(): array
    {
        $records = [];
        $paths = $this->findRelativePaths('records/feature-flag-changes');
        $this->measure('resolve_feature_flag_source_commits', fn () => $this->preloadSourceCommitShas($paths, 'records/feature-flag-changes'));
        foreach ($this->progressItems('parsing_feature_flag_changes', $paths) as $relativePath) {
            try {
                $record = $this->canonicalRepository->loadFeatureFlagChange($relativePath);
            } catch (CanonicalRecordParseException) {
                continue;
            }
            $commitSha = $this->sourceCommitShaForPath($relativePath);
            if ($commitSha === null) {
                continue;
            }
            $records[] = [
                'created_at' => $record->createdAt,
                'flag_key' => $record->flagKey,
                'value' => $record->value,
                'operator_identity_id' => $record->operatorIdentityId,
                'source_path' => CanonicalPathResolver::featureFlagChange($record->recordId),
                'source_commit_sha' => $commitSha,
            ];
        }
        $this->measure('sort_feature_flag_activity', function () use (&$records): void {
            usort($records, static fn (array $left, array $right): int => [$left['created_at'], $left['source_path']] <=> [$right['created_at'], $right['source_path']]);
        });

        return $records;
    }

    /**
     * @return list<array{created_at:string,origin_post_id:string,origin_thread_id:string,label:string,author_identity_id:?string,author_profile_slug:?string,author_username_token:?string,author_label:string,author_is_approved:int}>
     */
    private function codexHandoffActivityEvents(PDO $pdo): array
    {
        if (!$this->tableExists($pdo, 'codex_handoff_events')) {
            return [];
        }

        $stmt = $pdo->query(
            'SELECT created_at, origin_post_id, origin_thread_id, label,
                    author_identity_id, author_profile_slug, author_username_token,
                    author_label, author_is_approved
             FROM codex_handoff_events
             ORDER BY created_at ASC, id ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'created_at' => (string) ($row['created_at'] ?? ''),
                'origin_post_id' => (string) ($row['origin_post_id'] ?? ''),
                'origin_thread_id' => (string) ($row['origin_thread_id'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'author_identity_id' => isset($row['author_identity_id']) ? (string) $row['author_identity_id'] : null,
                'author_profile_slug' => isset($row['author_profile_slug']) ? (string) $row['author_profile_slug'] : null,
                'author_username_token' => isset($row['author_username_token']) ? (string) $row['author_username_token'] : null,
                'author_label' => (string) ($row['author_label'] ?? 'approved user'),
                'author_is_approved' => (int) ($row['author_is_approved'] ?? 1),
            ];
        }

        return $events;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,source_path:string,source_commit_sha:?string,sequence_number:int}> $posts
     * @return array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}>
     */
    private function deriveApprovalState(array $profiles, array $posts): array
    {
        $approved = [];
        foreach ($this->loadApprovalSeedIdentityIds() as $identityId) {
            $approved[$identityId] = [
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => 'root',
            ];
        }

        $changed = true;
        $pass = 0;
        while ($changed) {
            $this->reportProgress('Read model: approval pass ' . ++$pass . '.');
            $changed = false;
            foreach ($this->progressItems('checking_approval_posts', $posts) as $post) {
                $targetIdentityId = $this->extractApprovalTargetIdentityId($post);
                if ($targetIdentityId !== null && isset($post['source_path'])) {
                    \ForumRewrite\Security\ApprovalAcceptance::read($this->repositoryRoot, $post['source_path']);
                }
                if ($targetIdentityId === null || !isset($profiles[$targetIdentityId]) || isset($approved[$targetIdentityId])) {
                    continue;
                }

                $approverIdentityId = $post['author_identity_id'];
                if ($approverIdentityId === null || !isset($approved[$approverIdentityId])) {
                    continue;
                }

                if ($approverIdentityId === $targetIdentityId) {
                    continue;
                }

                $targetProfile = $profiles[$targetIdentityId];
                if ($post['thread_id'] !== $targetProfile['bootstrap_thread_id']) {
                    continue;
                }

                if ($post['parent_id'] !== $targetProfile['bootstrap_post_id']) {
                    continue;
                }

                $approverProfile = $profiles[$approverIdentityId] ?? null;
                $approved[$targetIdentityId] = [
                    'approved_by_identity_id' => $approverIdentityId,
                    'approved_by_profile_slug' => $approverProfile['profile_slug'] ?? null,
                    'approved_by_label' => $approverProfile['username'] ?? $approverIdentityId,
                ];
                $changed = true;
            }
            foreach ($this->progressItems('checking_invitation_candidates', $this->invitationApprovalCandidates($posts)) as $candidate) {
                $targetIdentityId = $candidate['target_identity_id'];
                $approverIdentityId = $candidate['approver_identity_id'];
                if (!isset($profiles[$targetIdentityId]) || isset($approved[$targetIdentityId]) || !isset($approved[$approverIdentityId])) {
                    continue;
                }
                $targetProfile = $profiles[$targetIdentityId];
                if ($candidate['thread_id'] !== $targetProfile['bootstrap_thread_id'] || $candidate['parent_id'] !== $targetProfile['bootstrap_post_id']) {
                    continue;
                }
                $approverProfile = $profiles[$approverIdentityId] ?? null;
                $approved[$targetIdentityId] = [
                    'approved_by_identity_id' => $approverIdentityId,
                    'approved_by_profile_slug' => $approverProfile['profile_slug'] ?? null,
                    'approved_by_label' => $approverProfile['username'] ?? $approverIdentityId,
                ];
                $changed = true;
            }
        }

        return $approved;
    }

    /**
     * @return list<string>
     */
    /**
     * @param array<int, array{thread_id:string,parent_id:?string,body:string,board_tags_json:string,author_identity_id:?string}> $posts
     * @return list<array{target_identity_id:string,approver_identity_id:string,thread_id:string,parent_id:?string}>
     */
    private function invitationApprovalCandidates(array $posts): array
    {
        return \ForumRewrite\Invitation\InvitationApprovalReplay::candidates($this->repositoryRoot, $posts);
    }

    private function loadApprovalSeedIdentityIds(): array
    {
        $identityIds = [];
        foreach ($this->findRelativePaths('records/approval-seeds') as $relativePath) {
            $identityIds[] = $this->canonicalRepository->loadApprovalSeed($relativePath)->approvedIdentityId;
        }

        sort($identityIds);

        return $identityIds;
    }

    /**
     * @param array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,source_path:string,source_commit_sha:?string,sequence_number:int} $post
     */
    private function extractApprovalTargetIdentityId(array $post): ?string
    {
        $boardTags = json_decode($post['board_tags_json'], true);
        if (!is_array($boardTags) || !in_array('identity', $boardTags, true) || !in_array('approval', $boardTags, true)) {
            return null;
        }

        if (preg_match('/^Approve-Identity-ID: (openpgp:[a-f0-9]{40})$/m', $post['body'], $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,source_path:string,source_commit_sha:?string,sequence_number:int}> $posts
     * @return array<string, bool> whether each post counts as its named author's under verified attribution
     */
    private function derivePostAttribution(array $posts): array
    {
        $verified = [];
        foreach ($this->progressItems('deriving_post_attribution', $posts) as $post) {
            $verified[$post['post_id']] = VerifiedAttribution::countsPost(
                $this->repositoryRoot,
                $this->attributionCutoff,
                $post['source_path'],
                $post['author_identity_id'],
                $post['created_at'],
            );
        }

        return $verified;
    }

    /**
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,source_path:string,source_commit_sha:?string,sequence_number:int}> $posts
     * @param array<string, bool> $postAttribution
     * @return array<string, array{post_count:int,thread_count:int,verified_post_count:int,verified_thread_count:int}>
     */
    private function deriveProfileCounts(array $profiles, array $posts, array $postAttribution): array
    {
        $counts = [];
        $bootstrapPostOwners = [];
        foreach ($this->progressItems('initializing_profile_counts', $profiles) as $identityId => $profile) {
            $counts[$identityId] = ['post_count' => 0, 'thread_count' => 0, 'verified_post_count' => 0, 'verified_thread_count' => 0];
            $bootstrapPostOwners[$profile['bootstrap_post_id']] = $identityId;
        }

        foreach ($this->progressItems('counting_profile_posts', $posts) as $post) {
            if ($this->isHiddenBootstrapBoardTagsJson($post['board_tags_json'])) {
                continue;
            }

            $owners = [];
            if ($post['author_identity_id'] !== null && isset($counts[$post['author_identity_id']])) {
                $owners[$post['author_identity_id']] = true;
            }
            if (isset($bootstrapPostOwners[$post['post_id']])) {
                $owners[$bootstrapPostOwners[$post['post_id']]] = true;
            }

            foreach (array_keys($owners) as $identityId) {
                $isThread = $post['post_id'] === $post['thread_id'];
                $counts[$identityId]['post_count']++;
                $counts[$identityId]['thread_count'] += $isThread ? 1 : 0;
                // A bootstrap post belongs to its identity's owner whatever its signature.
                if (($postAttribution[$post['post_id']] ?? true) || ($bootstrapPostOwners[$post['post_id']] ?? null) === $identityId) {
                    $counts[$identityId]['verified_post_count']++;
                    $counts[$identityId]['verified_thread_count'] += $isThread ? 1 : 0;
                }
            }
        }

        return $counts;
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array(self::HIDDEN_BOOTSTRAP_TAG, $boardTags, true);
    }

    private function measure(string $name, callable $callback): mixed
    {
        $startedAt = hrtime(true);
        $succeeded = false;
        $this->reportProgress('Read model: ' . str_replace('_', ' ', $name) . '...');
        try {
            $result = $callback();
            $succeeded = $result !== false;
            return $result;
        } finally {
            $this->reportDuration($name, $startedAt, $succeeded);
        }
    }

    /** Preserve keys and report completed work even when a loop skips a record. */
    private function progressItems(string $name, array $items): \Generator
    {
        $startedAt = hrtime(true);
        $label = 'Read model: ' . str_replace('_', ' ', $name);
        $total = count($items);
        $completed = 0;
        $succeeded = false;
        $this->reportBatchStart($label, $total);
        try {
            foreach ($items as $key => $item) {
                yield $key => $item;
                $this->reportBatchProgress($label, ++$completed, $total);
            }
            $succeeded = true;
        } finally {
            $this->reportDuration($name, $startedAt, $succeeded);
        }
    }

    private function reportDuration(string $name, int $startedAt, bool $succeeded): void
    {
        $duration = round((hrtime(true) - $startedAt) / 1000000, 1);
        $this->timings[$name] = round(($this->timings[$name] ?? 0.0) + $duration, 1);
        $this->reportProgress(sprintf(
            'Read model: %s %s (%.1f ms).',
            str_replace('_', ' ', $name),
            $succeeded ? 'complete' : 'failed',
            $duration,
        ));
    }

    private function reportProgress(string $message): void
    {
        if ($this->progressReporter !== null) {
            ($this->progressReporter)($message);
        }
    }

    private function reportBatchStart(string $label, int $total): void
    {
        $this->reportProgress("{$label} (0/{$total}).");
    }

    private function reportBatchProgress(string $label, int $completed, int $total): void
    {
        if ($completed === $total || $completed % max(100, (int) ceil($total / 100)) === 0) {
            $this->reportProgress("{$label} ({$completed}/{$total}).");
        }
    }
}
