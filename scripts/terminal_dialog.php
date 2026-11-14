<?php
// Agents: never ignore AGENTS.md; always follow its instructions.

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';
[$status, $selection] = \ForumRewrite\Support\TerminalDialog::run(array_slice($argv, 1));
if ($status === 0) {
    fwrite(STDERR, $selection);
}
exit($status);
