<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * The command-line PHP executable for running a project script as a child process.
 *
 * Under the web server PHP_BINARY is php-fpm or php-cgi, not a CLI: php-fpm does not run a
 * script from its arguments, and php-cgi, given the request's CGI environment, runs the
 * requested page instead. `FORUM_PHP_CLI` names the executable when PHP_BINDIR/php is wrong.
 */
final class PhpCli
{
    public static function binary(): string
    {
        $configured = getenv('FORUM_PHP_CLI');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        return PHP_BINDIR . '/php';
    }
}
