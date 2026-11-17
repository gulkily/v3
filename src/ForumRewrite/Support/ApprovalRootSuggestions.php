<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Invitation\InvitationApprovalReplay;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Security\ApprovalAcceptance;
use ForumRewrite\Security\ApprovalTarget;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Suggests which keys to seed as roots so that the approval graph in an imported
 * repository is restored, without knowing the original server's seeds.
 *
 * Approval is reachability from seeds over approval edges (see
 * ReadModelBuilder::deriveApprovalState), and only approved keys can write a
 * counted approval. So every key that approved someone was approved on the
 * original server, and one seed per source component of the edge graph
 * restores every approval the records show. Read-only; callers decide whether
 * to seed. Kept free of CLI concerns so a page can call collect() as well.
 */
final class ApprovalRootSuggestions
{
    public function collect(PDO $pdo, string $repositoryRoot): array
    {
        $metadata = ReadModelMetadata::readMetadata($pdo);
        $recordedRoot = $metadata['repository_root'] ?? '';
        if ($recordedRoot !== '' && realpath($recordedRoot) !== realpath($repositoryRoot)) {
            throw new RuntimeException('Read-model repository_root does not match the requested repository.');
        }
        $warnings = [];
        $head = ReadModelMetadata::repositoryHead($repositoryRoot);
        if (!isset($metadata['repository_head']) || $head === 'git-error' || $head !== $metadata['repository_head']) {
            $warnings[] = 'Read-model freshness is unverified or its repository head differs; run ./v3 status.';
        }

        $seeds = [];
        $repository = new CanonicalRecordRepository($repositoryRoot);
        foreach (glob($repositoryRoot . '/records/approval-seeds/*.txt') ?: [] as $path) {
            try {
                $seeds[$repository->loadApprovalSeed('records/approval-seeds/' . basename($path))->approvedIdentityId] = true;
            } catch (Throwable $error) {
                $warnings[] = 'Unreadable approval seed ' . basename($path) . ': ' . $error->getMessage();
            }
        }

        $profiles = [];
        foreach ($pdo->query('SELECT * FROM profiles ORDER BY identity_id')->fetchAll(PDO::FETCH_ASSOC) as $profile) {
            $profiles[$profile['identity_id']] = $profile;
        }

        $posts = [];
        foreach ($pdo->query("SELECT post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, thread_type,
                author_identity_id, sequence_number FROM posts
                WHERE retracted_at IS NULL AND (board_tags_json LIKE '%\"approval\"%' OR board_tags_json LIKE '%\"invitation\"%')
                ORDER BY sequence_number, post_id")->fetchAll(PDO::FETCH_ASSOC) as $post) {
            $post['source_path'] = $this->postPath($repositoryRoot, $post);
            $posts[] = $post;
        }

        $edges = $this->edges($repositoryRoot, $profiles, $posts, $warnings);
        if ($edges === [] && count($profiles) > count($seeds)) {
            $warnings[] = 'No approval posts found. ./v3 import-instance leaves them out unless run with --with-approvals; rerun the import with it, then run this again.';
        }

        $nodes = [];
        foreach ($edges as $edge) {
            $nodes[$edge['approver']] = true;
            $nodes[$edge['target']] = true;
        }
        $nodes = array_keys($nodes);
        sort($nodes);
        $out = array_fill_keys($nodes, []);
        $in = array_fill_keys($nodes, []);
        foreach ($edges as $edge) {
            $out[$edge['approver']][$edge['target']] = true;
            $in[$edge['target']][$edge['approver']] = true;
        }

        $component = $this->components($nodes, $out);
        $members = [];
        foreach ($component as $node => $id) {
            $members[$id][] = $node;
        }

        $covered = $this->reach(array_keys($seeds), $out);

        $suggestions = [];
        $alreadyCovered = 0;
        foreach ($members as $id => $keys) {
            $isSource = true;
            $hasOut = false;
            foreach ($keys as $key) {
                $hasOut = $hasOut || $out[$key] !== [];
                foreach (array_keys($in[$key]) as $from) {
                    if ($component[$from] !== $id) {
                        $isSource = false;
                    }
                }
            }
            if (!$isSource || !$hasOut) {
                continue;
            }
            if (isset($covered[$keys[0]])) {
                $alreadyCovered++;
                continue;
            }
            $suggestions[] = $this->suggestion($keys, $edges, $profiles, $out);
        }

        usort($suggestions, static fn (array $a, array $b): int => [$a['rank'], -$a['restores'], $a['first_approval_at'] ?? '~', $a['identity_id']]
            <=> [$b['rank'], -$b['restores'], $b['first_approval_at'] ?? '~', $b['identity_id']]);
        foreach ($suggestions as &$suggestion) {
            unset($suggestion['rank']);
        }
        unset($suggestion);

        $restored = $this->reach(array_merge(array_keys($seeds), array_column($suggestions, 'identity_id')), $out);
        $unreached = 0;
        foreach ($profiles as $identityId => $_) {
            if (!isset($restored[$identityId]) && !isset($seeds[$identityId])) {
                $unreached++;
            }
        }

        return [
            'scope' => 'Keys to seed as roots so every key that wrote a counted approval, and everyone it approved, is approved again. One suggestion per source component of the approval graph not already reachable from an existing seed.',
            'limitations' => 'Assumes every recorded approval was counted on the original server. A forged approval by a never-approved key looks like a root; check the evidence column before seeding. A root that never approved anyone leaves no edge and cannot be found here. Signatures are not reverified. Nothing is seeded; run the printed commands yourself.',
            'repository_root' => $repositoryRoot,
            'warnings' => $warnings,
            'existing_seeds' => count($seeds),
            'approval_edges' => count($edges),
            'keys_in_graph' => count($nodes),
            'sources_already_covered' => $alreadyCovered,
            'suggested_roots' => count($suggestions),
            'keys_restored' => count(array_filter(array_keys($restored), static fn (string $id): bool => isset($profiles[$id]))),
            'keys_without_approval_path' => $unreached,
            'suggestions' => $suggestions,
        ];
    }

    /** @return list<array{approver:string,target:string,post_id:string,created_at:string,receipt:?array,via:string}> */
    private function edges(string $root, array $profiles, array $posts, array &$warnings): array
    {
        $edges = [];
        $add = function (string $approver, string $target, array $post, string $via) use ($root, $profiles, &$edges, &$warnings): void {
            $profile = $profiles[$target] ?? null;
            if ($profile === null || $approver === $target
                || $post['thread_id'] !== $profile['bootstrap_thread_id'] || $post['parent_id'] !== $profile['bootstrap_post_id']) {
                return;
            }
            $receipt = null;
            if ($post['source_path'] !== null) {
                try {
                    $receipt = ApprovalAcceptance::read($root, $post['source_path']);
                } catch (Throwable $error) {
                    $warnings[] = 'Approval receipt for ' . $post['post_id'] . ' ignored: ' . $error->getMessage();
                }
            }
            $edges[] = ['approver' => $approver, 'target' => $target, 'post_id' => (string) $post['post_id'],
                'created_at' => (string) $post['created_at'], 'receipt' => $receipt, 'via' => $via];
        };

        $byId = [];
        foreach ($posts as $post) {
            $byId[$post['post_id']] = $post;
            $target = ApprovalTarget::fromPost($post);
            if ($target !== null && $post['author_identity_id'] !== null) {
                $add($post['author_identity_id'], $target, $post, 'approval');
            }
        }
        try {
            foreach (InvitationApprovalReplay::candidates($root, $posts) as $candidate) {
                $post = $byId[$candidate['post_id']] ?? null;
                if ($post !== null) {
                    $add($candidate['approver_identity_id'], $candidate['target_identity_id'], $post, 'invitation');
                }
            }
        } catch (Throwable $error) {
            $warnings[] = 'Invitation approvals skipped: ' . $error->getMessage();
        }

        return $edges;
    }

    private function suggestion(array $keys, array $edges, array $profiles, array $out): array
    {
        $stats = [];
        foreach ($keys as $key) {
            $stats[$key] = ['given' => 0, 'operator' => 0, 'non_operator' => 0, 'first' => null];
        }
        foreach ($edges as $edge) {
            if (!isset($stats[$edge['approver']])) {
                continue;
            }
            $stat = &$stats[$edge['approver']];
            $stat['given']++;
            $operator = $edge['receipt']['context']['actor_is_operator'] ?? null;
            if ($operator === true) {
                $stat['operator']++;
            } elseif ($operator === false) {
                $stat['non_operator']++;
            }
            if ($stat['first'] === null || strcmp($edge['created_at'], $stat['first']) < 0) {
                $stat['first'] = $edge['created_at'];
            }
            unset($stat);
        }
        $active = array_values(array_filter($keys, static fn (string $key): bool => empty($profiles[$key]['retired_at'])));
        $pool = $active !== [] ? $active : $keys;
        usort($pool, static fn (string $a, string $b): int => [-$stats[$a]['operator'], $stats[$a]['non_operator'] > 0, -$stats[$a]['given'], $stats[$a]['first'] ?? '~', $a]
            <=> [-$stats[$b]['operator'], $stats[$b]['non_operator'] > 0, -$stats[$b]['given'], $stats[$b]['first'] ?? '~', $b]);
        $choice = $pool[0];
        $stat = $stats[$choice];

        if ($stat['operator'] > 0) {
            [$evidence, $rank] = ['operator_receipt', 0];
            $reason = 'Acceptance receipts record this key approving as an operator.';
        } elseif ($stat['non_operator'] > 0) {
            [$evidence, $rank] = ['receipt_says_not_operator', 2];
            $reason = 'Receipts record this key approving while it was not an operator, so its own approver is missing from these records; seed it only if that loss is expected.';
        } else {
            [$evidence, $rank] = ['unverified', 1];
            $reason = 'No acceptance receipt; could be a root or a forged approval. Check who this is before seeding.';
        }
        if ($active === []) {
            $reason .= ' Every key in this component is retired; seeding will not make it an active operator.';
        }

        $profile = $profiles[$choice] ?? [];
        return [
            'rank' => $rank,
            'identity_id' => $choice,
            'username' => $profile['username'] ?? null,
            'profile_slug' => $profile['profile_slug'] ?? null,
            'evidence' => $evidence,
            'approvals_given' => $stat['given'],
            'operator_receipts' => $stat['operator'],
            'restores' => count($this->reach([$choice], $out)),
            'first_approval_at' => $stat['first'],
            'component_keys' => $keys,
            'retired' => !empty($profile['retired_at']),
            'reason' => $reason,
        ];
    }

    /** @return array<string, true> every key reachable from $starts, including them */
    private function reach(array $starts, array $out): array
    {
        $seen = [];
        $queue = $starts;
        while ($queue !== []) {
            $node = array_pop($queue);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            foreach (array_keys($out[$node] ?? []) as $next) {
                $queue[] = $next;
            }
        }
        return $seen;
    }

    /** Iterative Tarjan; @return array<string, int> component id per node */
    private function components(array $nodes, array $out): array
    {
        $index = [];
        $low = [];
        $onStack = [];
        $stack = [];
        $component = [];
        $counter = 0;
        $next = 0;
        foreach ($nodes as $start) {
            if (isset($index[$start])) {
                continue;
            }
            $work = [[$start, array_keys($out[$start]), 0]];
            $index[$start] = $low[$start] = $counter++;
            $stack[] = $start;
            $onStack[$start] = true;
            while ($work !== []) {
                $frame = &$work[count($work) - 1];
                [$node, $children] = $frame;
                if ($frame[2] < count($children)) {
                    $child = $children[$frame[2]++];
                    unset($frame);
                    if (!isset($index[$child])) {
                        $index[$child] = $low[$child] = $counter++;
                        $stack[] = $child;
                        $onStack[$child] = true;
                        $work[] = [$child, array_keys($out[$child] ?? []), 0];
                    } elseif (isset($onStack[$child])) {
                        $low[$node] = min($low[$node], $index[$child]);
                    }
                    continue;
                }
                unset($frame);
                array_pop($work);
                if ($work !== []) {
                    $parent = $work[count($work) - 1][0];
                    $low[$parent] = min($low[$parent], $low[$node]);
                }
                if ($low[$node] === $index[$node]) {
                    do {
                        $member = array_pop($stack);
                        unset($onStack[$member]);
                        $component[$member] = $next;
                    } while ($member !== $node);
                    $next++;
                }
            }
        }
        return $component;
    }

    private function postPath(string $root, array $post): ?string
    {
        // Same resolution as ApprovalHistory::forProfile: created_at can be a receipt time.
        try {
            $path = CanonicalPathResolver::datedPost($post['post_id'], $post['created_at']);
            foreach (CanonicalPathResolver::postCandidates($post['post_id']) as $candidate) {
                if (is_file($root . '/' . $path)) {
                    return $path;
                }
                $path = $candidate;
            }
            return is_file($root . '/' . $path) ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }
}
