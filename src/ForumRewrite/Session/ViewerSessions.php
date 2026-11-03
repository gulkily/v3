<?php

declare(strict_types=1);

namespace ForumRewrite\Session;

/**
 * The one answer to "who is signed in" for a request, and the one place that changes it.
 *
 * Created once per request from that request's cookies. Every route reads the same
 * session; there is no list of routes that get one. A request without the session
 * cookie never opens the store and never receives a cookie, so keyless readers stay
 * on static pages.
 */
final class ViewerSessions
{
    private ?ViewerSessionStore $store = null;
    private ?ViewerSession $current = null;
    private bool $resolved = false;

    /**
     * @param \Closure(): ViewerSessionStore $openStore
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        private readonly \Closure $openStore,
        private readonly array $cookies,
        private readonly bool $https,
    ) {
    }

    /** True when the request carries this scheme's session cookie. */
    public function hasCookie(): bool
    {
        return $this->token() !== '';
    }

    public function current(): ?ViewerSession
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $token = $this->token();
            $this->current = $token === '' ? null : $this->store()->resolve($token);
        }
        return $this->current;
    }

    /** The signed-in identity, or '' when nobody is signed in. */
    public function identityId(): string
    {
        return $this->current()?->identityId ?? '';
    }

    /** The identity this browser signed out of, for the Lobby; '' when none. */
    public function lobbyIdentityId(): string
    {
        return $this->current()?->lobbyIdentityId ?? '';
    }

    /** Records a verified sign-in and sends the cookie the browser should keep. */
    public function signIn(string $identityId): ViewerSession
    {
        $token = $this->token();
        $session = $this->store()->signIn($identityId, $token === '' ? null : $token);
        // A new token, or the same one with a renewed lifetime: either way the cookie's
        // expiry must match the session's.
        ViewerSessionCookie::send($session, $this->https);
        $this->current = $session;
        $this->resolved = true;
        return $session;
    }

    /** Ends the signed-in state on the server at once; the Lobby keeps the identity. */
    public function signOut(): void
    {
        $token = $this->token();
        if ($token === '') {
            return;
        }
        $this->current = $this->store()->signOut($token);
        $this->resolved = true;
    }

    /** The Lobby identity's profile is gone; stop offering it. */
    public function forgetLobbyIdentity(): void
    {
        $token = $this->token();
        if ($token !== '') {
            $this->store()->forgetLobbyIdentity($token);
        }
        if ($this->current !== null) {
            $this->current = new ViewerSession($this->current->token, $this->current->identityId, '', $this->current->expiresAt);
        }
    }

    public function createChallenge(): string
    {
        return $this->store()->createChallenge();
    }

    public function challengeIsOpen(string $challenge): bool
    {
        return $this->store()->challengeIsOpen($challenge);
    }

    public function consumeChallenge(string $challenge): bool
    {
        return $this->store()->consumeChallenge($challenge);
    }

    /** Asks the browser to drop PHP's old session cookie, if it still sends one. */
    public function retireLegacyCookie(): void
    {
        if (isset($this->cookies[ViewerSessionCookie::LEGACY_NAME])) {
            ViewerSessionCookie::expireLegacy($this->https);
        }
    }

    private function token(): string
    {
        return ViewerSessionCookie::tokenFromRequest($this->cookies, $this->https);
    }

    private function store(): ViewerSessionStore
    {
        return $this->store ??= ($this->openStore)();
    }
}
