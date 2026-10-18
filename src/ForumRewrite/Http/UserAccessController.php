<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Tools\ToolsPageSupport;

final class UserAccessController
{
    public function __construct(private readonly RouteServices $services)
    {
    }

    public function index(): string
    {
        return $this->render('user_access.php', [
            'users' => ProfileRepository::approvedDirectoryUsers($this->services->pdo()),
        ]);
    }

    public function account(string $username, array $query): ?string
    {
        $username = strtolower($username);
        $profiles = ProfileRepository::byUsernameToken($this->services->pdo(), $username);
        if ($profiles === []) {
            return null;
        }
        return $this->render('user_access_account.php', [
            'usernameToken' => $username,
            'profiles' => $profiles,
            'approvalHistory' => $this->services->approvalHistory()->forUsername($username, 50, max(0, (int) ($query['key_offset'] ?? 0))),
        ]);
    }

    private function render(string $template, array $data): string
    {
        return $this->services->renderPageTemplate($template, $data + [
            'toolNavOptions' => ToolsPageSupport::navOptions('user-access'),
        ], 'User Access', 'tools');
    }
}
