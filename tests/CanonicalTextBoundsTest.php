<?php
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Canonical\{GenericTextRecordParser, CanonicalRecordParseException};
use ForumRewrite\Support\{BoundedProcess, OperationBudget};
final class CanonicalTextBoundsTest
{
    public function testLargeTextFits128MiB(): void
    {
        $script = 'require ' . var_export(__DIR__ . '/../autoload.php', true) . ';'
            . '$text="Name: large\n\n"; $text.=str_repeat("a",16*1024*1024-strlen($text)-1)."\n";'
            . '$record=(new ForumRewrite\\Canonical\\GenericTextRecordParser())->parse($text);'
            . 'echo strlen($record->body);';
        $result = BoundedProcess::run([PHP_BINARY, '-d', 'memory_limit=128M', '-r', $script], new OperationBudget(5), 4096, 'large text');
        assertSame(0, $result['exit_code'], $result['stderr']);
        assertSame((string) (16*1024*1024 - strlen("Name: large\n\n")), $result['stdout']);
    }
    public function testCharacterPolicyIsPreserved(): void
    {
        $parser = new GenericTextRecordParser();
        foreach (["plain\ttext", 'é 日本語 😀'] as $body) assertSame($body . "\n", $parser->parse("Name: test\n\n" . $body . "\n")->body);
        foreach (["\0", "\r", "\x7f", "\xc2\x85", "\xe2\x80\x8b", "\xee\x80\x80", "\xff"] as $body) {
            try { $parser->parse("Name: test\n\n" . $body . "\n"); throw new LogicException('Expected invalid text'); }
            catch (CanonicalRecordParseException) {}
        }
    }
}
