<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

final class QdbExperienceRouteResult
{
    private function __construct(
        private readonly string $html,
        private readonly ?string $redirectLocation,
        private readonly ?string $redirectMessage,
    ) {
    }

    public static function page(string $html): self
    {
        return new self($html, null, null);
    }

    public static function redirect(string $location, string $message): self
    {
        return new self('', $location, $message);
    }

    public function html(): string
    {
        return $this->html;
    }

    public function isRedirect(): bool
    {
        return $this->redirectLocation !== null;
    }

    public function redirectLocation(): string
    {
        if ($this->redirectLocation === null) {
            throw new \LogicException('The QDB route result is not a redirect.');
        }

        return $this->redirectLocation;
    }

    public function redirectMessage(): string
    {
        if ($this->redirectMessage === null) {
            throw new \LogicException('The QDB route result is not a redirect.');
        }

        return $this->redirectMessage;
    }
}
