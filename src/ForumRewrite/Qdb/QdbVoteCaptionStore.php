<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use PDO;

final class QdbVoteCaptionStore
{
    /** @var array<int, array{active:int, positive:string, negative:string}> */
    private const ARCHIVED_SETS = [
        0 => ['active' => 0, 'positive' => 'good', 'negative' => 'bad'],
        1 => ['active' => 0, 'positive' => 'funny', 'negative' => 'not'],
        2 => ['active' => 0, 'positive' => 'funny', 'negative' => 'bad'],
        3 => ['active' => 1, 'positive' => 'funny', 'negative' => 'unfunny'],
        4 => ['active' => 1, 'positive' => 'good', 'negative' => 'bad'],
        5 => ['active' => 1, 'positive' => 'funny', 'negative' => 'boring'],
        6 => ['active' => 1, 'positive' => 'funny', 'negative' => 'awful'],
        7 => ['active' => 1, 'positive' => 'good', 'negative' => 'awful'],
        8 => ['active' => 1, 'positive' => 'funny', 'negative' => 'awful'],
        9 => ['active' => 1, 'positive' => 'worthy', 'negative' => 'sucks'],
        10 => ['active' => 1, 'positive' => 'keep-it', 'negative' => 'trash-it'],
        11 => ['active' => 1, 'positive' => 'merry', 'negative' => 'humbug'],
    ];

    /** @var array<string, array{label:string, score:int}> */
    private const ARCHIVED_CAPTIONS = [
        'good' => ['label' => 'Good', 'score' => 1],
        'bad' => ['label' => 'Bad', 'score' => -1],
        'funny' => ['label' => 'Funny', 'score' => 1],
        'not' => ['label' => 'Not', 'score' => -1],
        'unfunny' => ['label' => 'Unfunny', 'score' => -1],
        'boring' => ['label' => 'Boring', 'score' => -1],
        'awful' => ['label' => 'Awful', 'score' => -1],
        'worthy' => ['label' => 'Worthy', 'score' => 1],
        'sucks' => ['label' => 'Sucks', 'score' => -1],
        'keep-it' => ['label' => 'Keep It', 'score' => 1],
        'trash-it' => ['label' => 'Trash It', 'score' => -1],
        'merry' => ['label' => 'Merry', 'score' => 1],
        'humbug' => ['label' => 'Humbug', 'score' => -1],
    ];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function open(string $path): self
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the QDB vote-caption database directory.');
        }

        return new self(new PDO('sqlite:' . $path));
    }

    public function bootstrap(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS qdb_vote_captions (
                tag TEXT PRIMARY KEY,
                label TEXT NOT NULL,
                score INTEGER NOT NULL CHECK (score IN (-1, 1))
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS qdb_vote_caption_sets (
                caption_set_id INTEGER PRIMARY KEY,
                active INTEGER NOT NULL CHECK (active IN (0, 1))
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS qdb_vote_caption_set_members (
                caption_set_id INTEGER NOT NULL,
                direction INTEGER NOT NULL CHECK (direction IN (-1, 1)),
                tag TEXT NOT NULL,
                PRIMARY KEY (caption_set_id, direction),
                FOREIGN KEY (caption_set_id) REFERENCES qdb_vote_caption_sets(caption_set_id),
                FOREIGN KEY (tag) REFERENCES qdb_vote_captions(tag)
            )'
        );

        $this->pdo->beginTransaction();
        try {
            $caption = $this->pdo->prepare(
                'INSERT OR IGNORE INTO qdb_vote_captions (tag, label, score) VALUES (:tag, :label, :score)'
            );
            foreach (self::ARCHIVED_CAPTIONS as $tag => $row) {
                $caption->execute(['tag' => $tag, 'label' => $row['label'], 'score' => $row['score']]);
            }

            $set = $this->pdo->prepare(
                'INSERT OR IGNORE INTO qdb_vote_caption_sets (caption_set_id, active) VALUES (:caption_set_id, :active)'
            );
            $member = $this->pdo->prepare(
                'INSERT OR IGNORE INTO qdb_vote_caption_set_members (caption_set_id, direction, tag) VALUES (:caption_set_id, :direction, :tag)'
            );
            foreach (self::ARCHIVED_SETS as $setId => $row) {
                $set->execute(['caption_set_id' => $setId, 'active' => $row['active']]);
                $member->execute(['caption_set_id' => $setId, 'direction' => 1, 'tag' => $row['positive']]);
                $member->execute(['caption_set_id' => $setId, 'direction' => -1, 'tag' => $row['negative']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    /** @return list<array{caption_set_id:int, active:int, direction:int, tag:string, label:string, score:int}> */
    public function members(): array
    {
        $rows = $this->pdo->query(
            'SELECT sets.caption_set_id, sets.active, members.direction, captions.tag, captions.label, captions.score
             FROM qdb_vote_caption_sets AS sets
             JOIN qdb_vote_caption_set_members AS members USING (caption_set_id)
             JOIN qdb_vote_captions AS captions USING (tag)
             ORDER BY sets.caption_set_id ASC, members.direction DESC'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'caption_set_id' => (int) $row['caption_set_id'],
            'active' => (int) $row['active'],
            'direction' => (int) $row['direction'],
            'tag' => (string) $row['tag'],
            'label' => (string) $row['label'],
            'score' => (int) $row['score'],
        ], $rows);
    }

    /** @return list<array{caption_set_id:int, positive:array{tag:string,label:string,score:int}, negative:array{tag:string,label:string,score:int}}> */
    public function activePairs(): array
    {
        $pairs = [];
        foreach ($this->members() as $member) {
            if ($member['active'] !== 1) {
                continue;
            }

            $setId = $member['caption_set_id'];
            $pairs[$setId] ??= ['caption_set_id' => $setId, 'positive' => null, 'negative' => null];
            $caption = ['tag' => $member['tag'], 'label' => $member['label'], 'score' => $member['score']];
            if ($member['direction'] === 1) {
                $pairs[$setId]['positive'] = $caption;
            } else {
                $pairs[$setId]['negative'] = $caption;
            }
        }

        return array_values(array_map(
            static fn (array $pair): array => [
                'caption_set_id' => $pair['caption_set_id'],
                'positive' => $pair['positive'],
                'negative' => $pair['negative'],
            ],
            array_filter($pairs, static fn (array $pair): bool => is_array($pair['positive']) && is_array($pair['negative']))
        ));
    }

    /** @return array{tag:string,label:string,score:int}|null */
    public function caption(string $tag): ?array
    {
        $stmt = $this->pdo->prepare('SELECT tag, label, score FROM qdb_vote_captions WHERE tag = :tag');
        $stmt->execute(['tag' => $tag]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return ['tag' => (string) $row['tag'], 'label' => (string) $row['label'], 'score' => (int) $row['score']];
    }

    public function isActiveTag(string $tag): bool
    {
        foreach ($this->activePairs() as $pair) {
            if ($pair['positive']['tag'] === $tag || $pair['negative']['tag'] === $tag) {
                return true;
            }
        }

        return false;
    }
}
