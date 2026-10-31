<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

/** Each step runs in its own PHP process, as separate requests do. */
final class SessionRotationTest
{
    public function testOldIdForwardsToTheSignedInSessionDuringTheGracePeriod(): void
    {
        $this->withSessions(function (string $dir): void {
            $old = $this->step($dir, null, '$_SESSION["token"] = "kept"; echo session_id();');
            $new = $this->step($dir, $old, 'ForumRewrite\Http\SessionRotation::rotate(); $_SESSION["authenticated_identity_id"] = "openpgp:abc"; echo session_id();');
            assertSame(true, $new !== $old);

            // A request still carrying the old ID lands in the signed-in session.
            $late = $this->step($dir, $old, 'echo json_encode([ForumRewrite\Http\SessionRotation::follow(), session_id(), $_SESSION]);');
            assertSame([true, $new, ['token' => 'kept', 'authenticated_identity_id' => 'openpgp:abc']], json_decode($late, true));

            // Its writes reach the signed-in session too: a sign-out on the old ID signs out.
            $this->step($dir, $old, 'ForumRewrite\Http\SessionRotation::follow(); unset($_SESSION["authenticated_identity_id"]);');
            assertSame('{"token":"kept"}', $this->step($dir, $new, 'echo json_encode($_SESSION);'));

            // The old ID keeps only the forwarding marker, never the signed-in data.
            $raw = (string) file_get_contents($dir . '/sess_' . $old);
            assertSame(false, str_contains($raw, 'openpgp:abc') || str_contains($raw, 'kept'));
        });
    }

    public function testOldIdStartsOverAfterTheGracePeriod(): void
    {
        $this->withSessions(function (string $dir): void {
            $old = $this->step($dir, null, '$_SESSION["authenticated_identity_id"] = "openpgp:abc"; echo session_id();');
            $new = $this->step($dir, $old, 'ForumRewrite\Http\SessionRotation::rotate(); echo session_id();');
            $grace = ForumRewrite\Http\SessionRotation::GRACE_SECONDS;
            $late = $this->step($dir, $old, 'echo json_encode([ForumRewrite\Http\SessionRotation::follow(time() + ' . ($grace + 1) . '), session_id(), $_SESSION]);');
            [$forwarded, $id, $data] = json_decode($late, true);
            assertSame(false, $forwarded);
            assertSame(true, $id !== $old && $id !== $new);
            assertSame([], $data);
            assertSame(false, is_file($dir . '/sess_' . $old), 'the expired marker is deleted');
            assertSame('{"authenticated_identity_id":"openpgp:abc"}', $this->step($dir, $new, 'echo json_encode($_SESSION);'));
        });
    }

    public function testRepeatedSignInsForwardThroughTheChain(): void
    {
        $this->withSessions(function (string $dir): void {
            $first = $this->step($dir, null, 'echo session_id();');
            $second = $this->step($dir, $first, 'ForumRewrite\Http\SessionRotation::rotate(); echo session_id();');
            $third = $this->step($dir, $second, 'ForumRewrite\Http\SessionRotation::rotate(); $_SESSION["authenticated_identity_id"] = "openpgp:def"; echo session_id();');
            $late = $this->step($dir, $first, 'echo json_encode([ForumRewrite\Http\SessionRotation::follow(), session_id(), $_SESSION]);');
            assertSame([true, $third, ['authenticated_identity_id' => 'openpgp:def']], json_decode($late, true));
        });
    }

    private function withSessions(callable $test): void
    {
        $dir = sys_get_temp_dir() . '/session-rotation-' . bin2hex(random_bytes(6));
        mkdir($dir);
        try {
            $test($dir);
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    /** Runs one request: start (or resume) the session, run $code, write the session back. */
    private function step(string $dir, ?string $sessionId, string $code): string
    {
        $script = 'require ' . var_export(dirname(__DIR__) . '/autoload.php', true) . ';'
            . 'session_save_path(' . var_export($dir, true) . ');'
            . ($sessionId !== null ? 'session_id(' . var_export($sessionId, true) . ');' : '')
            . 'session_start(["use_cookies" => false, "use_strict_mode" => true]);'
            . $code
            . 'session_write_close();';
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);
        assertSame(0, $status, implode("\n", $output));
        return implode("\n", $output);
    }
}
