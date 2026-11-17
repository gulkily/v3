<?php

declare(strict_types=1);

/**
 * The tag index's original preview step, kept as a reference: TagsIndexMemoryTest
 * checks that the streaming TagGrouping::previewsFromUniqueThreads() gives the same
 * groups, and tags_index_memory.php replays its memory use under a cap.
 */
final class TagGroupingReference
{
    /**
     * @param list<array<string, mixed>> $groups output of TagGrouping::byTag()
     * @return list<array<string, mixed>>
     */
    public static function limitPreview(array $groups, int $limit): array
    {
        $limited = [];
        foreach ($groups as $group) {
            $threads = $group['threads'];
            $group['preview_threads'] = array_slice($threads, 0, $limit);
            $group['has_more'] = count($threads) > $limit;
            $group['href'] = '/tags/' . $group['tag'];
            $limited[] = $group;
        }

        return $limited;
    }
}
