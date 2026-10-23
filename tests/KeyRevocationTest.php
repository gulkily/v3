<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Canonical\KeyRevocationRecordParser;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;

final class KeyRevocationTest
{
    public function testSignedContractRequiresExplicitCutoffWithoutCommitTimeFallback(): void
    {
        $parser = new KeyRevocationRecordParser();
        $contents = $this->record();
        assertSame('2026-10-23T15:00:00Z', $parser->parse($contents)['Revocation-Cutoff']);
        foreach ([str_replace("Revocation-Cutoff: 2026-10-23T15:00:00Z\n", '', $contents),
            str_replace('2026-10-23T15:00:00Z', '2026-02-30T15:00:00Z', $contents),
            str_replace('2026-10-23T15:00:00Z', '2026-10-23T15:00:00+00:00', $contents),
            str_replace('Operation: revoke-key', "Operation: revoke-key\nAccepted-At: 2026-10-23T15:02:00Z", $contents)] as $bad) {
            try { $parser->parse($bad); throw new LogicException('Invalid revocation accepted'); }
            catch (\ForumRewrite\Canonical\CanonicalRecordParseException) {}
        }
    }

    public function testLegacySponsorshipDerivationHasMatchingFullAndIncrementalHistory(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('history');
        $ids = array_map(fn($n) => 'openpgp:' . str_repeat((string) $n, 40), [1,2,3,4]);
        $w->put($root . '/records/approval-seeds/openpgp-' . substr($ids[0], 8) . '.txt', "Approved-Identity-ID: {$ids[0]}\nSeed-Reason: test\n\nRoot.\n");
        $profiles = []; $posts = [];
        foreach ($ids as $i => $id) {
            $profiles[$id] = ['identity_id' => $id, 'username' => 'member' . $i, 'username_token' => 'member' . $i,
                'profile_slug' => 'key' . $i, 'bootstrap_thread_id' => 'boot' . $i, 'bootstrap_post_id' => 'boot' . $i];
            if ($i === 0) continue;
            $posts[] = ['post_id' => 'grant' . $i, 'created_at' => '2026-01-01T00:00:00Z', 'author_identity_id' => $ids[$i-1],
                'thread_id' => 'boot' . $i, 'parent_id' => 'boot' . $i, 'body' => 'Approve-Identity-ID: ' . $id . "\n", 'board_tags_json' => '["identity","approval"]'];
        }
        $builder = new ReadModelBuilder($root, $root . '/db', new CanonicalRecordRepository($root));
        $updater = new IncrementalReadModelUpdater($root . '/db', $root);
        $full = (new ReflectionMethod($builder, 'deriveApprovalState'))->invoke($builder, $profiles, $posts);
        $incremental = (new ReflectionMethod($updater, 'deriveApprovalState'))->invoke($updater, $posts, $profiles);
        assertSame($full, $incremental);
        assertSame($ids, array_keys($full));
        assertSame($ids[1], $full[$ids[2]]['approved_by_identity_id']);
        // Revocation must overlay current access after this graph has been derived.
        // Removing a sponsor from this historical derivation would lose descendants.
    }

    private function record(): string
    {
        return 'Record-ID: key-revocation-' . str_repeat('a', 32) . "\nOperation: revoke-key\nAuthor-Identity-ID: openpgp:" . str_repeat('1', 40)
            . "\nTarget-Identity-ID: openpgp:" . str_repeat('2', 40) . "\nUsername: bob\nRevocation-Cutoff: 2026-10-23T15:00:00Z\nReview-Context: sha256:" . str_repeat('b', 64) . "\n\n";
    }
}
