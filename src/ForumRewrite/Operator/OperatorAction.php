<?php

declare(strict_types=1);

namespace ForumRewrite\Operator;

use ForumRewrite\Canonical\PostRecord;
use RuntimeException;

final class OperatorAction
{
    public static function isAction(PostRecord $post): bool
    {
        return in_array('identity', $post->boardTags, true) && in_array('operator', $post->boardTags, true);
    }

    public static function parse(PostRecord $post): array
    {
        if (!self::isAction($post) || !in_array('identity', $post->boardTags, true) || !$post->isReply()) {
            throw new RuntimeException('Operator actions must be tagged identity operator bootstrap replies.');
        }
        $fields = [];
        foreach (explode("\n", trim($post->body)) as $line) {
            if (preg_match('/^(Operator-Action|Operator-Account|Operator-Sequence|Operator-Target-Identity|Operator-Grant-ID): ([A-Za-z0-9._:-]+)$/D', $line, $match) !== 1 || isset($fields[$match[1]])) {
                throw new RuntimeException('Malformed operator action.');
            }
            $fields[$match[1]] = $match[2];
        }
        foreach (['Operator-Action', 'Operator-Account', 'Operator-Sequence', 'Operator-Target-Identity'] as $field) {
            if (!isset($fields[$field])) {
                throw new RuntimeException('Missing operator action field: ' . $field);
            }
        }
        $action = $fields['Operator-Action'];
        if (!in_array($action, ['grant', 'revoke'], true) || !ctype_digit($fields['Operator-Sequence'])
            || (int) $fields['Operator-Sequence'] < 1
            || preg_match('/^openpgp:[a-f0-9]{40}$/D', $fields['Operator-Target-Identity']) !== 1
            || ($action === 'revoke') !== isset($fields['Operator-Grant-ID'])) {
            throw new RuntimeException('Invalid operator action fields.');
        }
        return [
            'post_id' => $post->postId, 'sequence' => (int) $fields['Operator-Sequence'],
            'action' => $action, 'account' => $fields['Operator-Account'],
            'target_identity_id' => $fields['Operator-Target-Identity'],
            'grant_id' => $fields['Operator-Grant-ID'] ?? '',
            'author_identity_id' => $post->authorIdentityId,
            'thread_id' => $post->threadId, 'parent_id' => $post->parentId,
        ];
    }
}
