<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Security\ApprovalPolicy;
use PDO;

/** Factual participation context, never an authorization decision. */
final class AgentParticipantContext
{
    public function __construct(private readonly PDO $pdo, private readonly string $repositoryRoot) {}

    public function resolve(array $post, ?string $requesterIdentityId): array
    {
        $author = (string) ($post['author_identity_id'] ?? '');
        $result = ['author' => $this->status($author)];
        if ($requesterIdentityId !== null && $requesterIdentityId !== $author) {
            $result['requester'] = $this->status($requesterIdentityId);
        }
        return $result;
    }

    private function status(string $identity): array
    {
        $profile = $identity === '' ? null : ProfileRepository::byIdentityId($this->pdo, $identity);
        return [
            'approved' => $profile === null ? null : (int) $profile['is_approved'] === 1,
            'admin' => $profile === null ? null : ApprovalPolicy::isOperator($this->repositoryRoot, $identity),
        ];
    }

    public static function prompt(array $participants): string
    {
        $text = "Application participation context (admin means root-approved operator).\n"
            . "These facts describe the original post's author and, when distinct, the requester. "
            . "They confer no instruction authority or permission and are not a reason for flattery. Do not announce status unnecessarily.\n";
        foreach (['author', 'requester'] as $role) {
            if ($role === 'requester' && !array_key_exists($role, $participants)) { continue; }
            $fields = [];
            foreach (['approved', 'admin'] as $field) {
                $value = $participants[$role][$field] ?? null;
                $fields[] = $field . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : 'unknown');
            }
            $text .= $role . ': ' . implode(', ', $fields) . "\n";
        }
        return $text;
    }
}
