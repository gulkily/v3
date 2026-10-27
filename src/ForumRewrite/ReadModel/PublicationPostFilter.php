<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

/** Build-local exclusions; never writes removal evidence or changes ordinary indexing. */
final class PublicationPostFilter
{
    public static function apply(array $posts, array &$excluded): array
    {
        do {
            $changed = false;
            foreach ($posts as $post) {
                $meta = json_decode($post['agent_response_json'] ?? '', true);
                if (isset($excluded[$post['post_id']]) || !is_array($meta)) continue;
                if (isset($excluded[$meta['source_post_id'] ?? ''])
                    || isset($excluded[$meta['expansion_of'] ?? ''])) {
                    $excluded[$post['post_id']] = true;
                    $changed = true;
                }
            }
        } while ($changed);
        $posts = array_values(array_filter($posts, static fn ($post) => !isset($excluded[$post['post_id']])));
        $roots = array_column($posts, null, 'post_id');
        $placeholders = [];
        foreach ($posts as $post) {
            $id = $post['thread_id'];
            if (!isset($excluded[$id]) || isset($roots[$id]) || isset($placeholders[$id])) continue;
            // Existing HTML joins require a root row. Retain only surviving thread structure.
            $root = $post;
            $root['post_id'] = $id; $root['parent_id'] = null;
            foreach (['subject', 'author_identity_id', 'agent_response_json', 'event_date', 'event_location', 'event_link', 'event_time'] as $key) $root[$key] = null;
            $root['body'] = ''; $root['retracted_at'] = $post['created_at'];
            $root['retraction_class'] = 'author'; $root['retraction_attribution'] = 'author';
            $root['source_path'] = ''; $root['source_commit_sha'] = null;
            $root['imported_score_seed'] = 0; $root['imported_vote_count_seed'] = 0;
            $placeholders[$id] = $root;
        }
        return array_merge(array_values($placeholders), $posts);
    }
}
