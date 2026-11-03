<?php

declare(strict_types=1);

namespace ForumRewrite\Session;

/** What a request's session cookie stands for: who is signed in, if anyone. */
final class ViewerSession
{
    public function __construct(
        /** Raw token as the browser sent it or as it was just issued. */
        public readonly string $token,
        /** Signed-in identity, or '' when signed out. */
        public readonly string $identityId,
        /** The identity this browser signed out of, kept for the Lobby; '' when none. */
        public readonly string $lobbyIdentityId,
        public readonly int $expiresAt,
    ) {
    }

    public function isSignedIn(): bool
    {
        return $this->identityId !== '';
    }
}
