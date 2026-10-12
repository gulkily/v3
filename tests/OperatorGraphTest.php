<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Operator\OperatorGraph;

final class OperatorGraphTest
{
    private function profiles(): array
    {
        $profiles = [];
        foreach (['root', 'alice', 'bob', 'carol'] as $name) {
            $profiles[$name] = ['username_token' => $name, 'is_approved' => 1,
                'bootstrap_thread_id' => $name, 'bootstrap_post_id' => $name];
        }
        return $profiles;
    }
    private function event(int $sequence, string $from, string $to, string $action = 'grant', string $grant = ''): array
    {
        return ['post_id' => 'g' . $sequence, 'sequence' => $sequence, 'action' => $action,
            'account' => $to, 'author_identity_id' => $from, 'target_identity_id' => $to,
            'thread_id' => $to, 'parent_id' => $to, 'grant_id' => $grant];
    }
    public function testCascadeRestoresUnrevokedDescendantsAndProtectsSuspendedAccount(): void
    {
        $events = [$this->event(1, 'root', 'alice'), $this->event(2, 'alice', 'bob'), $this->event(3, 'bob', 'carol')];
        $state = OperatorGraph::derive($this->profiles(), ['root'], $events);
        assertSame(['bob', 'carol'], OperatorGraph::preview($state, 'g2'));
        $events[] = $this->event(4, 'root', 'bob', 'revoke', 'g2');
        $state = OperatorGraph::derive($this->profiles(), ['root'], $events);
        assertSame(false, isset($state['accounts']['carol']));
        assertSame(true, isset($state['protected']['bob']));
        $events[] = $this->event(5, 'alice', 'bob');
        $state = OperatorGraph::derive($this->profiles(), ['root'], $events);
        assertSame(true, isset($state['accounts']['carol']));
        assertSame(true, $state['grants']['g2']['revoked']);
    }
    public function testAlternatePathAndDisconnectedCycle(): void
    {
        $events = [$this->event(1, 'root', 'alice'), $this->event(2, 'alice', 'bob'),
            $this->event(3, 'bob', 'carol'), $this->event(4, 'carol', 'bob'), $this->event(5, 'alice', 'carol')];
        $state = OperatorGraph::derive($this->profiles(), ['root'], $events);
        assertSame([], OperatorGraph::preview($state, 'g2'));
        $events[] = $this->event(6, 'root', 'alice', 'revoke', 'g1');
        $state = OperatorGraph::derive($this->profiles(), ['root'], $events);
        assertSame(['root'], array_keys($state['accounts']));
    }
    public function testUnauthorizedAndReplayedHistoryFailsClosed(): void
    {
        foreach ([[$this->event(1, 'bob', 'carol')], [$this->event(2, 'root', 'bob')],
            [$this->event(1, 'root', 'bob'), $this->event(1, 'root', 'alice')]] as $events) {
            $threw = false;
            try { OperatorGraph::derive($this->profiles(), ['root'], $events); }
            catch (RuntimeException) { $threw = true; }
            assertSame(true, $threw);
        }
    }
    public function testApprovedAccountKeyCanDelegateButPendingKeyCannot(): void
    {
        $profiles = $this->profiles();
        $profiles['alice-key2'] = $profiles['alice'];
        $events = [$this->event(1, 'root', 'alice'), $this->event(2, 'alice-key2', 'bob')];
        assertSame(true, isset(OperatorGraph::derive($profiles, ['root'], $events)['accounts']['bob']));
        $profiles['alice-key2']['is_approved'] = 0;
        $threw = false;
        try { OperatorGraph::derive($profiles, ['root'], $events); }
        catch (RuntimeException) { $threw = true; }
        assertSame(true, $threw);
    }
}
