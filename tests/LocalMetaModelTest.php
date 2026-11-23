<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\LocalMetaModel;

final class LocalMetaModelTest
{
    /** @return list<array{subject:string, body:string, meta:bool}> */
    private static function examples(): array
    {
        $meta = ['Bug: reply button broken on phone', 'Feature request: dark theme toggle', 'The page crashes when I post',
            'Bug in the theme menu', 'Please add a button to export posts', 'Feature request: search the board'];
        $other = ['Notes on a paper about proteins', 'Weekend hike at Plum Island', 'Thoughts on rap and linguistics',
            'A paper on protein folding', 'Seals at the beach this weekend', 'A beach walk with seals', 'Rap lyrics and rhyme schemes'];
        $examples = [];
        foreach ($meta as $text) $examples[] = ['subject' => $text, 'body' => '', 'meta' => true];
        foreach ($other as $text) $examples[] = ['subject' => $text, 'body' => '', 'meta' => false];
        return $examples;
    }

    public function testSeparatesSupportFromOrdinaryPosts(): void
    {
        $model = LocalMetaModel::train(self::examples());
        $bug = $model->score('Bug: the reply button does nothing', '');
        $hike = $model->score('A weekend at the beach', 'We saw seals.');
        assertTrue($bug['probability'] > 0.6);
        assertTrue($hike['probability'] < 0.4);
        assertTrue(in_array('bug', $bug['top_words'], true));
        assertTrue(in_array('beach', $hike['top_words'], true) || in_array('weekend', $hike['top_words'], true));
    }

    public function testLongPostsDoNotDriftToTheEndsJustByBeingLong(): void
    {
        $model = LocalMetaModel::train(self::examples());
        $short = $model->score('Notes on a paper', 'about proteins');
        $long = $model->score('Notes on a paper', str_repeat('about proteins and rap and a weekend hike, then the page and a button. ', 40));
        // Summing evidence would push this mixed long post toward 0 or 1; averaging keeps it near its words' typical lean.
        assertTrue($long['probability'] > 0.2 && $long['probability'] < 0.8);
        assertTrue($short['probability'] < 0.5);
        assertSame($model->score('Bug in the theme', 'see #meta and https://example.com/bug-theme-button'), $model->score('Bug in the theme', 'see'));
    }

    public function testCyrillicIsNeverMetaAndIsLeftOutOfTraining(): void
    {
        $examples = self::examples();
        $examples[] = ['subject' => 'Баг: кнопка не работает', 'body' => 'bug button', 'meta' => true];
        $model = LocalMetaModel::train($examples);
        assertSame(6, $model->counts()['meta']);
        $score = $model->score('Баг в теме', 'кнопка bug не работает на телефоне');
        assertSame(0.0, $score['probability']);
        assertSame([LocalMetaModel::CYRILLIC_SIGNAL], $score['signals']);
        assertFalse(LocalMetaModel::isCyrillic('Bug report: тема'));
    }

    public function testJsonRoundTripKeepsScoresAndBuildId(): void
    {
        $model = LocalMetaModel::train(self::examples(), null, '2026-11-14T00:00:00Z')->withThreshold(0.8);
        $copy = LocalMetaModel::fromJson($model->toJson());
        assertSame($model->buildId(), $copy->buildId());
        assertSame(0.8, $copy->threshold());
        assertSame($model->score('theme button bug', ''), $copy->score('theme button bug', ''));
        $again = LocalMetaModel::train(self::examples(), null, '2026-11-15T00:00:00Z');
        assertSame($model->buildId(), $again->buildId());
    }

    public function testRejectsTamperedModel(): void
    {
        $data = json_decode(LocalMetaModel::train(self::examples())->toJson(), true);
        $data['meta_documents']++;
        $threw = false;
        try { LocalMetaModel::fromJson(json_encode($data)); } catch (InvalidArgumentException) { $threw = true; }
        assertTrue($threw);
    }
}
