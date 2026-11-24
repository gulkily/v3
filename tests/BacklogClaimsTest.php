<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Feedback\BacklogClaims;
use ForumRewrite\Feedback\FeedbackItem;
use ForumRewrite\Support\ClaimDirective;
use ForumRewrite\Support\ReleaseDirective;

final class BacklogClaimsTest
{
    private const A = 'PF-20261104-B18';
    private const B = 'PF-20261104-B12';
    private const C = 'PF-20261101-F03';
    /** Noon on 2026-11-23, UTC. */
    private const NOW = 1795435200;

    public function testClaimLineIsTheFirstLineAndNamesItemIds(): void
    {
        assertSame([self::A, self::B], ClaimDirective::extract('claim: ' . self::A . ', ' . self::B));
        assertSame([self::A, self::B], ClaimDirective::extract("\n  Claim:  pf-20261104-b18  " . self::B . "  \n\nBranch fix/fonts."));
        assertSame([self::A], ClaimDirective::extract('claim: ' . self::A . ',' . self::A));
        // Only the first line counts, so a quote or a later line is ordinary text.
        assertSame(null, ClaimDirective::extract("I will take it.\nclaim: " . self::A));
        assertSame(null, ClaimDirective::extract('> claim: ' . self::A));
        assertSame(null, ClaimDirective::extract('claim:'));
        assertSame(null, ClaimDirective::extract('claim: the favicon one'));
        assertSame(null, ClaimDirective::extract('claim: ' . self::A . ' and the favicon one'));
        assertSame(null, ClaimDirective::extract('my claim: ' . self::A));
        assertSame('claim: ' . self::A . ', ' . self::B, ClaimDirective::body([self::A, self::B]));
        assertSame(true, ClaimDirective::isBare(ClaimDirective::body([self::A]) . "\n"));
        assertSame(false, ClaimDirective::isBare(ClaimDirective::body([self::A]) . "\n\nBranch fix/fonts."));
    }

    public function testReleaseLineMayNameItemsOrStandAlone(): void
    {
        assertSame([], ReleaseDirective::extract('release'));
        assertSame([], ReleaseDirective::extract(" Release: \n\nStopped; could not reproduce."));
        assertSame([self::A], ReleaseDirective::extract('release: ' . self::A));
        assertSame(null, ReleaseDirective::extract('release the hounds'));
        assertSame(null, ReleaseDirective::extract("Done.\nrelease"));
        assertSame('release', ReleaseDirective::body());
        assertSame('release: ' . self::A, ReleaseDirective::body([self::A]));
        assertSame(true, ReleaseDirective::isBare('release'));
        assertSame(false, ReleaseDirective::isBare("release\n\nStopped."));
    }

    public function testEarliestCountedClaimHoldsAndLaterOnesAreListed(): void
    {
        $claims = BacklogClaims::fromPosts([
            $this->post('p1', 'mara', 'claim: ' . self::A . ', ' . self::B, '2026-11-23T08:00:00Z'),
            $this->post('p2', 'rin', 'claim: ' . self::A, '2026-11-23T09:00:00Z'),
        ], $this->items(), self::NOW);

        assertSame('mara', $claims[self::A]['holder']['name']);
        assertSame('p1', $claims[self::A]['holder']['post_id']);
        assertSame('2026-11-23T08:00:00Z', $claims[self::A]['holder']['since']);
        assertSame(['rin'], array_column($claims[self::A]['also'], 'name'));
        assertSame('mara', $claims[self::B]['holder']['name']);
        assertSame([], $claims[self::B]['also']);
        assertSame(false, isset($claims[self::C]));
        assertSame(true, BacklogClaims::isHeld($claims[self::A]));
        assertSame(['Claimed by mara', 'Also claimed by rin'], BacklogClaims::lines($claims[self::A]));
        assertSame([], BacklogClaims::lines(null));
        assertSame(false, BacklogClaims::isHeld(null));
    }

    public function testGuestAndUnapprovedClaimsAreShownAndNotCounted(): void
    {
        $claims = BacklogClaims::fromPosts([
            $this->post('p1', '', 'claim: ' . self::A, '2026-11-23T08:00:00Z'),
            $this->post('p2', 'newkey', 'claim: ' . self::A, '2026-11-23T08:30:00Z', approved: false),
            $this->post('p3', 'unverified', 'claim: ' . self::B, '2026-11-23T08:40:00Z', counted: false),
        ], $this->items(), self::NOW);

        assertSame(null, $claims[self::A]['holder']);
        assertSame(1, count($claims[self::A]['guests']));
        assertSame(['newkey'], array_column($claims[self::A]['waiting'], 'name'));
        assertSame(false, BacklogClaims::isHeld($claims[self::A]));
        assertSame(['Claimed by newkey · waiting for approval', 'Claimed by a guest · not counted'], BacklogClaims::lines($claims[self::A]));
        assertSame(null, $claims[self::B]['holder']);
        assertSame(['unverified'], array_column($claims[self::B]['waiting'], 'name'));

        // The same posts count from the moment the key is approved; nothing is rewritten.
        $approved = BacklogClaims::fromPosts([$this->post('p2', 'newkey', 'claim: ' . self::A, '2026-11-23T08:30:00Z')], $this->items(), self::NOW);
        assertSame('newkey', $approved[self::A]['holder']['name']);
    }

    public function testReleaseByTheHolderOrAnOperatorEndsAClaim(): void
    {
        $claim = $this->post('p1', 'mara', 'claim: ' . self::A . ', ' . self::B, '2026-11-23T08:00:00Z');
        $operator = static fn (string $identity): bool => $identity === 'id-ilyag';

        // The holder gives up one item and keeps the other.
        $claims = BacklogClaims::fromPosts([$claim, $this->post('p2', 'mara', 'release: ' . self::A, '2026-11-23T09:00:00Z', parent: 'p1')], $this->items(), self::NOW);
        assertSame(false, isset($claims[self::A]));
        assertSame('mara', $claims[self::B]['holder']['name']);

        // The bare line gives up the whole claim.
        assertSame([], BacklogClaims::fromPosts([$claim, $this->post('p2', 'mara', "release\n\nCould not reproduce.", '2026-11-23T09:00:00Z', parent: 'p1')], $this->items(), self::NOW));

        // A stranger's release does nothing; an operator's does.
        $stranger = BacklogClaims::fromPosts([$claim, $this->post('p2', 'rin', 'release', '2026-11-23T09:00:00Z', parent: 'p1')], $this->items(), self::NOW, $operator);
        assertSame('mara', $stranger[self::A]['holder']['name']);
        assertSame([], BacklogClaims::fromPosts([$claim, $this->post('p2', 'ilyag', 'release', '2026-11-23T09:00:00Z', parent: 'p1')], $this->items(), self::NOW, $operator));

        // No name, an unverified signature, a retracted release, or a release that answers something else: no effect.
        foreach ([
            $this->post('p2', '', 'release', '2026-11-23T09:00:00Z', parent: 'p1'),
            $this->post('p2', 'mara', 'release', '2026-11-23T09:00:00Z', parent: 'p1', counted: false),
            $this->post('p2', 'mara', 'release', '2026-11-23T09:00:00Z', parent: 'p1', retracted: true),
            $this->post('p2', 'mara', 'release', '2026-11-23T09:00:00Z', parent: 'p0'),
        ] as $release) {
            assertSame('mara', BacklogClaims::fromPosts([$claim, $release], $this->items(), self::NOW, $operator)[self::A]['holder']['name']);
        }

        // An operator can clear a guest claim; a holder whose key is not approved can still take their own claim back.
        $guest = $this->post('p1', '', 'claim: ' . self::A, '2026-11-23T08:00:00Z');
        assertSame([], BacklogClaims::fromPosts([$guest, $this->post('p2', 'ilyag', 'release', '2026-11-23T09:00:00Z', parent: 'p1')], $this->items(), self::NOW, $operator));
        $waiting = $this->post('p1', 'newkey', 'claim: ' . self::A, '2026-11-23T08:00:00Z', approved: false);
        assertSame([], BacklogClaims::fromPosts([$waiting, $this->post('p2', 'newkey', 'release', '2026-11-23T09:00:00Z', parent: 'p1', approved: false)], $this->items(), self::NOW));
    }

    public function testRetractedClaimsUnknownIdsAndEndedItemsShowNothing(): void
    {
        $items = [...$this->items(), $this->item('PF-20261102-B01', 'blocked'), $this->item('PF-20261102-B02', 'done')];
        $claims = BacklogClaims::fromPosts([
            $this->post('p1', 'mara', 'claim: ' . self::A, '2026-11-23T08:00:00Z', retracted: true),
            $this->post('p2', 'mara', 'claim: PF-20990101-F99, PF-20261102-B01, PF-20261102-B02, ' . self::B, '2026-11-23T08:00:00Z'),
        ], $items, self::NOW);

        assertSame([self::B], array_keys($claims));
        assertSame([], BacklogClaims::fromPosts([], $items, self::NOW));
        assertSame([], BacklogClaims::fromPosts([$this->post('p1', 'mara', 'Just a note in the thread.', '2026-11-23T08:00:00Z')], $items, self::NOW));
    }

    public function testAClaimGoesStaleAfterThreeDaysUnlessTheItemWasTouched(): void
    {
        $old = $this->post('p1', 'mara', 'claim: ' . self::A . ', ' . self::B, '2026-11-19T08:00:00Z');
        // B was touched on the 21st, so its claim is fresh until the 24th ends.
        $items = [$this->item(self::A), $this->item(self::B, notes: "2026-11-21: found the cause.\n")];
        $claims = BacklogClaims::fromPosts([$old], $items, self::NOW);

        assertSame(true, $claims[self::A]['holder']['stale']);
        assertSame(false, BacklogClaims::isHeld($claims[self::A]));
        assertSame(['Claimed by mara · stale'], BacklogClaims::lines($claims[self::A]));
        assertSame(false, $claims[self::B]['holder']['stale']);

        // Just inside and just outside three days.
        $edge = static fn (string $at): bool => BacklogClaims::fromPosts([(new self())->post('p1', 'mara', 'claim: ' . self::A, $at)], [(new self())->item(self::A)], self::NOW)[self::A]['holder']['stale'];
        assertSame(false, $edge('2026-11-20T12:00:00Z'));
        assertSame(true, $edge('2026-11-20T11:59:59Z'));

        // A fresh claim holds ahead of a stale one, whoever came first; claiming again refreshes your own.
        $taken = BacklogClaims::fromPosts([$old, $this->post('p2', 'rin', 'claim: ' . self::A, '2026-11-23T09:00:00Z')], $items, self::NOW);
        assertSame('rin', $taken[self::A]['holder']['name']);
        assertSame(['Claimed by rin', 'Also claimed by mara · stale'], BacklogClaims::lines($taken[self::A]));
        $again = BacklogClaims::fromPosts([$old, $this->post('p2', 'mara', 'claim: ' . self::A, '2026-11-23T09:00:00Z')], $items, self::NOW);
        assertSame('p2', $again[self::A]['holder']['post_id']);
        assertSame(false, $again[self::A]['holder']['stale']);
        assertSame([], $again[self::A]['also']);
    }

    public function testFiveHundredClaimPostsAreReadQuickly(): void
    {
        $items = [];
        for ($i = 1; $i <= 350; $i++) {
            $items[] = $this->item(sprintf('PF-20261101-F%03d', $i));
        }
        $posts = [];
        for ($i = 1; $i <= 500; $i++) {
            $ids = sprintf('PF-20261101-F%03d, PF-20261101-F%03d', ($i % 350) + 1, (($i * 7) % 350) + 1);
            $posts[] = $this->post('p' . $i, 'agent' . ($i % 9), 'claim: ' . $ids, '2026-11-2' . ($i % 4) . 'T08:00:00Z');
            if ($i % 3 === 0) {
                $posts[] = $this->post('r' . $i, 'agent' . ($i % 9), 'release', '2026-11-23T09:00:00Z', parent: 'p' . $i);
            }
        }
        $start = hrtime(true);
        $claims = BacklogClaims::fromPosts($posts, $items, self::NOW, static fn (string $identity): bool => false);
        $milliseconds = (hrtime(true) - $start) / 1e6;

        assertSame(true, count($claims) > 100);
        assertSame(true, $milliseconds < 50, 'read took ' . round($milliseconds, 1) . ' ms');
    }

    /** @return list<FeedbackItem> */
    private function items(): array
    {
        return [$this->item(self::A), $this->item(self::B), $this->item(self::C)];
    }

    private function item(string $id, string $workflow = 'untriaged', string $notes = ''): FeedbackItem
    {
        return new FeedbackItem('/tmp/' . $id . '.txt', ['ID' => $id, 'Title' => 'Item ' . $id, 'Kind' => 'bug', 'Workflow' => $workflow, 'Created' => '2026-11-04'], [], $notes);
    }

    /** @return array<string, mixed> a row shaped like ThreadRepository::replyPostsByThreadId() */
    private function post(string $id, string $author, string $body, string $at, string $parent = 'thread-1', bool $approved = true, bool $counted = true, bool $retracted = false): array
    {
        return [
            'post_id' => $id, 'thread_id' => 'thread-1', 'parent_id' => $parent, 'body' => $body, 'created_at' => $at,
            'author_identity_id' => $author === '' ? '' : 'id-' . $author, 'author_label' => $author === '' ? 'guest' : $author,
            'author_is_approved' => $approved ? 1 : 0, 'attribution_counted' => $counted ? 1 : 0,
            'retracted_at' => $retracted ? '2026-11-23T10:00:00Z' : null,
        ];
    }
}
