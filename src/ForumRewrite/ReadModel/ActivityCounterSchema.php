<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

/** Transactional counters cover every SQL writer, including local source-less events. */
final class ActivityCounterSchema
{
    public static function statements(): array
    {
        $sql = [
            'CREATE TABLE activity_counts (view TEXT PRIMARY KEY, total INTEGER NOT NULL, without_profile_names INTEGER NOT NULL)',
            "INSERT INTO activity_counts VALUES ('all',0,0),('content',0,0),('identity',0,0),('bootstrap',0,0),('approval',0,0),('security',0,0),('commits',0,0)",
            'CREATE TABLE activity_local_state (singleton INTEGER PRIMARY KEY, events INTEGER NOT NULL, revision TEXT NOT NULL)',
            "INSERT INTO activity_local_state VALUES (1,0,'')",
            "CREATE INDEX activity_page_idx ON activity(created_at DESC, COALESCE(action_key, 'local:' || id) DESC)",
            'CREATE INDEX activity_date_key_idx ON activity(created_at, action_key, id)',
            'CREATE INDEX activity_kind_key_idx ON activity(kind, action_key, id)',
            'CREATE INDEX activity_label_key_idx ON activity(label, action_key, id)',
        ];
        $columns = 'id, created_at, kind, record_family, action_key, post_id, thread_id, label, board_tags_json, author_identity_id';
        foreach (['INSERT', 'DELETE', 'UPDATE OF ' . $columns] as $event) {
            $name = strtolower(explode(' ', $event)[0]);
            $body = '';
            foreach (['OLD' => '-', 'NEW' => '+'] as $row => $sign) {
                if (($name === 'insert' && $row === 'OLD') || ($name === 'delete' && $row === 'NEW')) continue;
                foreach (self::membership($row) as $view => $predicate) {
                    $body .= "UPDATE activity_counts SET total = total $sign ($predicate), without_profile_names = without_profile_names $sign (($predicate) AND $row.record_family != 'profile_name') WHERE view = '$view';\n";
                }
                $body .= "UPDATE activity_local_state SET events = events $sign ($row.action_key IS NULL), revision = CASE WHEN $row.action_key IS NULL THEN lower(hex(randomblob(16))) ELSE revision END WHERE singleton = 1;\n";
            }
            $sql[] = "CREATE TRIGGER activity_counts_$name AFTER $event ON activity BEGIN $body END";
        }
        // A content event's category also depends on the target post's current visibility.
        // Subtract before and add after: the indexed post_id lookup touches only affected events.
        foreach (['INSERT' => 'NEW', 'DELETE' => 'OLD', 'UPDATE OF post_id, is_hidden, retracted_at' => 'OLD'] as $event => $row) {
            $name = strtolower(explode(' ', $event)[0]);
            foreach (['BEFORE' => '-', 'AFTER' => '+'] as $when => $sign) {
                $body = '';
                // A post-id change can affect both the old and new identity.
                $where = $name === 'update' ? 'a.post_id IN (OLD.post_id, NEW.post_id)' : "a.post_id = $row.post_id";
                $visible = self::membership('a')['content'];
                $body = "UPDATE activity_counts SET total = total $sign (SELECT COUNT(*) FROM activity a WHERE $where AND ($visible)),
                    without_profile_names = without_profile_names $sign (SELECT COUNT(*) FROM activity a WHERE $where AND ($visible) AND a.record_family != 'profile_name') WHERE view='content';";
                $sql[] = "CREATE TRIGGER activity_post_counts_{$name}_" . strtolower($when) . " $when $event ON posts BEGIN $body END";
            }
        }
        $sql[] = "CREATE TRIGGER activity_commit_count_insert AFTER INSERT ON commits BEGIN UPDATE activity_counts SET total=total+1, without_profile_names=without_profile_names+1 WHERE view='commits'; END";
        $sql[] = "CREATE TRIGGER activity_commit_count_delete AFTER DELETE ON commits BEGIN UPDATE activity_counts SET total=total-1, without_profile_names=without_profile_names-1 WHERE view='commits'; END";
        return $sql;
    }

    /** Exact existing SQL plus PHP membership semantics, including case-sensitive tag values. */
    private static function membership(string $r): array
    {
        $json = "CASE WHEN json_valid($r.board_tags_json) THEN $r.board_tags_json ELSE '[]' END";
        $tag = static fn (string $tag): string => "json_type($json) IN ('array','object') AND EXISTS (SELECT 1 FROM json_each($json) WHERE type='text' AND value='$tag' COLLATE BINARY)";
        $identity = $tag('identity');
        $visible = "NOT EXISTS (SELECT 1 FROM posts WHERE post_id=$r.post_id AND (COALESCE(is_hidden,0) != 0 OR retracted_at IS NOT NULL))";
        return [
            'all' => '1',
            'content' => "$r.board_tags_json NOT LIKE '%\"identity\"%' AND NOT ($identity) AND ($visible)",
            'identity' => "$r.board_tags_json LIKE '%\"identity\"%' AND ($identity)",
            'bootstrap' => "$r.board_tags_json LIKE '%\"identity\"%' AND $r.board_tags_json LIKE '%\"internal\"%' AND ($identity) AND (" . $tag('internal') . ')',
            'approval' => "$r.board_tags_json LIKE '%\"identity\"%' AND $r.board_tags_json LIKE '%\"approval\"%' AND ($identity) AND (" . $tag('approval') . ')',
            'security' => "$r.kind='approval' OR ($r.kind='invitation' AND $r.label LIKE 'Invitation redeem:%') OR ($r.kind='site_feature_flag' AND $r.label LIKE '%FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=%')",
        ];
    }
}
