<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Write\StagedSubmissionStore;

final class StagedSubmissionStoreTest
{
    private const ALICE = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const BOB = 'openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testStageReplaceListAndDiscard(): void
    {
        $directory = sys_get_temp_dir() . '/staged-store-' . bin2hex(random_bytes(6));
        $store = new StagedSubmissionStore($directory);
        $first = 'thread-20261111000000-' . str_repeat('1', 32);
        $second = 'reply-20261111000100-' . str_repeat('2', 32);
        $record = "Post-ID: {$first}\r\nSubject: Recap\r\n\r\nBody\r\n";
        try {
            $item = $store->stage($first, self::ALICE, null, $record, "sig\n", 'Emoji are not allowed.', 'subject', [['codepoint' => 'U+1F3A4', 'index' => 6]], 1000);
            assertSame($record, $item['record']);
            assertSame('Recap', $item['subject']);
            assertSame([['codepoint' => 'U+1F3A4', 'index' => 6]], $item['chars']);
            assertSame("Body\n", $item['body']);
            assertSame(true, str_contains((string) file_get_contents($directory . '/' . $first . '/staged.txt'), "Character: U+1F3A4 6\n"));

            // The same bytes again keep the original item; new bytes from the same author replace it.
            assertSame($item['staged_at'], $store->stage($first, self::ALICE, null, $record, "sig\n", 'other', '', [], 2000)['staged_at']);
            $replaced = $store->stage($first, self::ALICE, null, "fixed\n", "sig2\n", 'Still wrong.', '', [], 3000);
            assertSame('Still wrong.', $replaced['reason']);
            assertSame("fixed\n", $replaced['record']);

            try { $store->stage($first, self::BOB, null, "x\n", "s\n", 'r', '', [], 4000); throw new LogicException('Expected conflict'); }
            catch (RuntimeException $e) { assertSame(409, $e->getCode()); }

            $store->stage($second, self::BOB, $first, "reply\n", "s\n", 'Records use LF line endings, not CR LF.', 'record', [], 5000);
            assertSame([$second, $first], array_column($store->listFor(null), 'post_id'));
            assertSame([$first], array_column($store->listFor([self::ALICE]), 'post_id'));
            assertSame($first, $store->find($second)['thread_id']);

            $store->discard($first);
            assertSame(null, $store->find($first));
            assertSame([$second], array_column($store->listFor(null), 'post_id'));
            assertSame(null, $store->find('../etc'));
            $store->discard('../etc');
        } finally {
            foreach (glob($directory . '/*/*') ?: [] as $file) unlink($file);
            foreach (glob($directory . '/*') ?: [] as $folder) rmdir($folder);
            @rmdir($directory);
        }
    }
}
